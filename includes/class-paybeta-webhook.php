<?php
defined( 'ABSPATH' ) || exit;

/**
 * Paybeta incoming webhook handler.
 *
 * Verifies the HMAC-SHA512 signature on the raw request body, then updates
 * the corresponding WooCommerce order based on the event type.
 */
class Paybeta_Webhook_Handler {

    public function __construct( private readonly string $secret ) {}

    /**
     * Entry point — called by WooCommerce API hook `woocommerce_api_paybeta_webhook`.
     * Reads the raw POST body, verifies signature, dispatches event, and exits.
     */
    public function handle(): void {
        $raw_body  = file_get_contents( 'php://input' );
        $signature = $_SERVER['HTTP_X_PAYBETA_SIGNATURE'] ?? '';

        if ( empty( $this->secret ) ) {
            // No secret configured — log and acknowledge so Paybeta stops retrying
            $this->log( 'Webhook received but no webhook secret is configured. Configure it in WooCommerce → Settings → Payments → Paybeta.' );
            $this->respond( 200, [ 'received' => true ] );
        }

        if ( ! $this->verify_signature( $raw_body, $signature ) ) {
            $this->log( 'Webhook signature verification failed.' );
            $this->respond( 401, [ 'error' => 'Invalid signature' ] );
        }

        $event = json_decode( $raw_body, true );

        if ( ! is_array( $event ) ) {
            $this->respond( 400, [ 'error' => 'Invalid JSON' ] );
        }

        $type = $event['type'] ?? '';
        $this->log( sprintf( 'Received webhook event: %s', $type ) );

        match ( $type ) {
            'payment.completed',
            'transaction.funded'  => $this->handle_payment_success( $event ),
            'payment.failed'      => $this->handle_payment_failed( $event ),
            default               => $this->log( sprintf( 'Unhandled event type: %s', $type ) ),
        };

        $this->respond( 200, [ 'received' => true ] );
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    private function handle_payment_success( array $event ): void {
        $order = $this->find_order_from_event( $event );
        if ( ! $order ) {
            return;
        }

        if ( $order->is_paid() ) {
            $this->log( sprintf( 'Order #%s is already paid. Skipping.', $order->get_id() ) );
            return;
        }

        $transaction_id = $event['data']['transactionId'] ?? $event['data']['id'] ?? '';
        $order->payment_complete( $transaction_id );
        $order->add_order_note(
            sprintf(
                /* translators: %s: Paybeta transaction ID */
                __( 'Payment confirmed by Paybeta. Transaction ID: %s', 'paybeta' ),
                $transaction_id ?: __( 'N/A', 'paybeta' )
            )
        );

        $this->log( sprintf( 'Order #%s marked as paid (transaction: %s).', $order->get_id(), $transaction_id ) );
    }

    private function handle_payment_failed( array $event ): void {
        $order = $this->find_order_from_event( $event );
        if ( ! $order ) {
            return;
        }

        $reason = $event['data']['failureReason'] ?? __( 'Unknown reason', 'paybeta' );
        $order->update_status(
            'failed',
            sprintf(
                /* translators: %s: failure reason */
                __( 'Payment failed via Paybeta. Reason: %s', 'paybeta' ),
                $reason
            )
        );

        $this->log( sprintf( 'Order #%s marked as failed. Reason: %s', $order->get_id(), $reason ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a WC_Order using the payment reference stored in event data.
     * Tries event data → paymentId meta → reference (= order ID).
     */
    private function find_order_from_event( array $event ): WC_Order|false {
        $data       = $event['data'] ?? [];
        $payment_id = $data['paymentId'] ?? $data['id'] ?? '';
        $reference  = $data['reference'] ?? '';

        // 1. Search by stored _paybeta_payment_id meta
        if ( $payment_id ) {
            $orders = wc_get_orders( [
                'meta_key'   => '_paybeta_payment_id',
                'meta_value' => $payment_id,
                'limit'      => 1,
            ] );
            if ( ! empty( $orders ) ) {
                return $orders[0];
            }
        }

        // 2. Fall back to reference = order ID (set during process_payment)
        if ( $reference && is_numeric( $reference ) ) {
            $order = wc_get_order( (int) $reference );
            if ( $order instanceof WC_Order ) {
                return $order;
            }
        }

        $this->log( sprintf( 'Could not find order for event. payment_id=%s reference=%s', $payment_id, $reference ) );
        return false;
    }

    /**
     * Constant-time HMAC-SHA512 comparison.
     */
    private function verify_signature( string $body, string $signature ): bool {
        if ( empty( $signature ) ) {
            return false;
        }
        $expected = hash_hmac( 'sha512', $body, $this->secret );
        return hash_equals( $expected, strtolower( $signature ) );
    }

    private function log( string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info( $message, [ 'source' => 'paybeta' ] );
        }
    }

    /**
     * Send JSON response and exit.
     */
    private function respond( int $status, array $body ): never {
        status_header( $status );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( $body );
        exit;
    }
}
