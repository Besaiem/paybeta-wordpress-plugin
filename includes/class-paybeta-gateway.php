<?php
defined( 'ABSPATH' ) || exit;

/**
 * Paybeta WooCommerce Payment Gateway.
 *
 * Integrates Paybeta's hosted payment link flow:
 * 1. process_payment() creates a payment link and redirects the customer.
 * 2. handle_return() verifies status when the customer comes back.
 * 3. handle_webhook() processes asynchronous payment events from Paybeta.
 */
class WC_Paybeta_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'paybeta';
        $this->method_title       = __( 'Paybeta', 'paybeta' );
        $this->method_description = __( 'Accept payments via Paybeta — cards, bank transfers, and USSD with escrow protection.', 'paybeta' );
        $this->has_fields         = false;
        $this->supports           = [ 'products', 'refunds' ];

        $icon_url = plugin_dir_url( PAYBETA_PLUGIN_FILE ) . 'assets/icon.svg';
        $this->icon = apply_filters( 'paybeta_gateway_icon', $icon_url );

        $this->init_form_fields();
        $this->init_settings();

        // Persist admin settings when saved
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

        // Register WooCommerce HTTP API endpoints
        add_action( 'woocommerce_api_paybeta_return',  [ $this, 'handle_return' ] );
        add_action( 'woocommerce_api_paybeta_webhook', [ $this, 'handle_webhook' ] );
    }

    // -------------------------------------------------------------------------
    // Admin settings
    // -------------------------------------------------------------------------

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'paybeta' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Paybeta Payment Gateway', 'paybeta' ),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __( 'Title', 'paybeta' ),
                'type'        => 'text',
                'description' => __( 'Label shown to the customer at checkout.', 'paybeta' ),
                'default'     => __( 'Pay with Paybeta', 'paybeta' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'paybeta' ),
                'type'        => 'textarea',
                'description' => __( 'Description shown below the payment method at checkout.', 'paybeta' ),
                'default'     => __( 'Secure payment via Paybeta — card, bank transfer, or USSD.', 'paybeta' ),
                'desc_tip'    => true,
            ],
            'sandbox' => [
                'title'       => __( 'Sandbox Mode', 'paybeta' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable sandbox / test mode', 'paybeta' ),
                'default'     => 'yes',
                'description' => __( 'Use test API keys against the Paybeta sandbox environment.', 'paybeta' ),
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => __( 'Live API Key', 'paybeta' ),
                'type'        => 'password',
                'description' => __( 'Your live API key from the Paybeta dashboard (pb_live_…).', 'paybeta' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'test_api_key' => [
                'title'       => __( 'Test API Key', 'paybeta' ),
                'type'        => 'password',
                'description' => __( 'Your sandbox API key from the Paybeta dashboard (pb_test_…).', 'paybeta' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'merchant_id' => [
                'title'       => __( 'Merchant ID', 'paybeta' ),
                'type'        => 'text',
                'description' => __( 'Your Paybeta merchant ID (UUID from the dashboard).', 'paybeta' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'webhook_secret' => [
                'title'       => __( 'Webhook Secret', 'paybeta' ),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __( 'Secret for verifying Paybeta webhook payloads. Configure this URL in your Paybeta dashboard: %s', 'paybeta' ),
                    '<code>' . esc_url( add_query_arg( 'wc-api', 'paybeta_webhook', home_url( '/' ) ) ) . '</code>'
                ),
                'default'     => '',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Payment processing
    // -------------------------------------------------------------------------

    /**
     * Called by WooCommerce when the customer places an order using this gateway.
     */
    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        // Convert amount to smallest unit (kobo for NGN, cents for USD, etc.)
        $amount = (int) round( (float) $order->get_total() * 100 );

        $return_url = add_query_arg(
            [
                'wc-api'   => 'paybeta_return',
                'order_id' => $order_id,
            ],
            home_url( '/' )
        );

        $result = $this->api()->create_payment_link( [
            'merchantId'     => $this->get_option( 'merchant_id' ),
            'amount'         => $amount,
            'currency'       => get_woocommerce_currency(),
            'description'    => sprintf(
                /* translators: 1: order number, 2: site name */
                __( 'Order #%1$s from %2$s', 'paybeta' ),
                $order->get_order_number(),
                get_bloginfo( 'name' )
            ),
            'buyerEmail'     => $order->get_billing_email(),
            'buyerName'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'sellerEmail'    => get_option( 'admin_email' ),
            'redirectUrl'    => $return_url,
            'reference'      => (string) $order_id,
            'expiresInHours' => 2,
        ] );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Paybeta payment link creation failed: %s', 'paybeta' ),
                    $result->get_error_message()
                )
            );
            return [ 'result' => 'failure' ];
        }

        // Persist Paybeta identifiers on the order for webhook + return lookup
        $order->update_meta_data( '_paybeta_token',      $result['token'] ?? '' );
        $order->update_meta_data( '_paybeta_payment_id', $result['paymentId'] ?? '' );
        $order->update_status( 'pending', __( 'Awaiting payment via Paybeta.', 'paybeta' ) );
        $order->save();

        $checkout_url = $result['checkoutUrl'] ?? '';

        if ( empty( $checkout_url ) ) {
            wc_add_notice( __( 'Paybeta did not return a checkout URL. Please try again.', 'paybeta' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        return [
            'result'   => 'success',
            'redirect' => $checkout_url,
        ];
    }

    // -------------------------------------------------------------------------
    // Customer return URL  (?wc-api=paybeta_return)
    // -------------------------------------------------------------------------

    /**
     * Handles the customer's return from the Paybeta-hosted payment page.
     * Verifies status via the API and updates the order accordingly.
     */
    public function handle_return(): void {
        $order_id = absint( $_GET['order_id'] ?? 0 );
        $order    = $order_id ? wc_get_order( $order_id ) : false;

        if ( ! $order instanceof WC_Order ) {
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $token = sanitize_text_field( $order->get_meta( '_paybeta_token' ) );

        if ( empty( $token ) ) {
            // No token stored — go back to checkout
            wc_add_notice( __( 'Payment could not be verified. Please try again.', 'paybeta' ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $result = $this->api()->get_payment_status( $token );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $status = strtoupper( $result['status'] ?? '' );

        if ( in_array( $status, [ 'SUCCESS', 'COMPLETED', 'FUNDED' ], true ) ) {
            if ( ! $order->is_paid() ) {
                $transaction_id = $result['transactionId'] ?? '';
                $order->payment_complete( $transaction_id );
                $order->add_order_note( __( 'Payment confirmed via Paybeta.', 'paybeta' ) );
            }
            wp_safe_redirect( $this->get_return_url( $order ) );
            exit;
        }

        if ( in_array( $status, [ 'FAILED', 'CANCELLED' ], true ) ) {
            $reason = $result['failureReason'] ?? __( 'Payment was not completed.', 'paybeta' );
            $order->update_status( 'failed', sprintf( __( 'Paybeta: %s', 'paybeta' ), $reason ) );
            wc_add_notice( esc_html( $reason ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // PROCESSING / INITIATED — payment is in-flight; show pending order page
        $order->add_order_note( __( 'Payment is processing via Paybeta.', 'paybeta' ) );
        wp_safe_redirect( $order->get_checkout_order_received_url() );
        exit;
    }

    // -------------------------------------------------------------------------
    // Webhook endpoint  (?wc-api=paybeta_webhook)
    // -------------------------------------------------------------------------

    public function handle_webhook(): void {
        $secret  = $this->get_option( 'webhook_secret', '' );
        $handler = new Paybeta_Webhook_Handler( $secret );
        $handler->handle();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Instantiate the API client using the currently active key.
     */
    private function api(): Paybeta_API {
        $sandbox = $this->get_option( 'sandbox' ) === 'yes';
        $key     = $sandbox
            ? $this->get_option( 'test_api_key' )
            : $this->get_option( 'api_key' );

        return new Paybeta_API( $key );
    }

    /**
     * Append a sandbox badge to the gateway title in admin if test mode is on.
     */
    public function get_title(): string {
        $title = parent::get_title();
        if ( is_admin() && $this->get_option( 'sandbox' ) === 'yes' ) {
            $title .= ' <span style="color:#e65c00;font-size:11px;font-weight:600;">[TEST MODE]</span>';
        }
        return $title;
    }

    /**
     * Show an admin notice on the gateway settings page when sandbox mode is on.
     */
    public function admin_options(): void {
        if ( $this->get_option( 'sandbox' ) === 'yes' ) {
            echo '<div class="notice notice-warning inline"><p>'
                . '<strong>'
                . esc_html__( 'Paybeta is in Sandbox / Test mode.', 'paybeta' )
                . '</strong> '
                . esc_html__( 'No real payments will be taken. Switch to Live mode and use your live API key before going live.', 'paybeta' )
                . '</p></div>';
        }
        parent::admin_options();
    }
}
