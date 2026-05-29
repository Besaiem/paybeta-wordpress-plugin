<?php
defined( 'ABSPATH' ) || exit;

/**
 * Paybeta HTTP client.
 *
 * Wraps wp_remote_request so every outbound call carries the API key header,
 * JSON content-type, and a consistent error response.
 */
class Paybeta_API {

    private const LIVE_BASE_URL = 'https://api.paybeta.com';

    private string $api_key;
    private string $base_url;

    public function __construct( string $api_key ) {
        $this->api_key  = $api_key;
        $this->base_url = self::LIVE_BASE_URL;
    }

    // -------------------------------------------------------------------------
    // Public resource methods
    // -------------------------------------------------------------------------

    /**
     * Create a hosted payment link.
     *
     * @param  array $params  Fields: merchantId, amount, currency, description,
     *                        buyerEmail, buyerName, sellerEmail, redirectUrl,
     *                        reference, expiresInHours.
     * @return array|WP_Error Decoded response body or WP_Error on failure.
     */
    public function create_payment_link( array $params ): array|WP_Error {
        return $this->post( '/payment-links', $params );
    }

    /**
     * Retrieve the status of a payment link after the customer returns.
     *
     * @param  string $token  64-char hex token returned by create_payment_link().
     * @return array|WP_Error Decoded response body or WP_Error on failure.
     */
    public function get_payment_status( string $token ): array|WP_Error {
        return $this->get( '/payment-links/complete/' . rawurlencode( $token ) );
    }

    // -------------------------------------------------------------------------
    // Private HTTP helpers
    // -------------------------------------------------------------------------

    private function post( string $path, array $body ): array|WP_Error {
        return $this->request( 'POST', $path, $body );
    }

    private function get( string $path ): array|WP_Error {
        return $this->request( 'GET', $path );
    }

    /**
     * @param  string $method  HTTP verb (GET, POST, …)
     * @param  string $path    API path starting with /
     * @param  array  $body    Request body (ignored for GET)
     * @return array|WP_Error
     */
    private function request( string $method, string $path, array $body = [] ): array|WP_Error {
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'X-API-Key'    => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ];

        if ( $method !== 'GET' && ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $this->base_url . $path, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $decoded     = json_decode( $raw_body, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $code    = $decoded['error']['code']    ?? (string) $status_code;
            $message = $decoded['error']['message'] ?? __( 'Paybeta API request failed.', 'paybeta' );
            return new WP_Error( 'paybeta_api_error', $message, [ 'status' => $status_code, 'code' => $code ] );
        }

        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'paybeta_parse_error', __( 'Unexpected response from Paybeta API.', 'paybeta' ) );
        }

        return $decoded;
    }
}
