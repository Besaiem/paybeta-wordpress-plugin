<?php
/**
 * Plugin Name:       Paybeta Payment Gateway
 * Plugin URI:        https://paybeta.com
 * Description:       Accept payments via Paybeta in your WooCommerce store. Supports cards, bank transfers, and USSD with built-in escrow protection.
 * Version:           1.0.1
 * Author:            Paybeta
 * Author URI:        https://paybeta.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 * Text Domain:       paybeta
 */

defined( 'ABSPATH' ) || exit;

define( 'PAYBETA_VERSION',    '1.0.1' );
define( 'PAYBETA_PLUGIN_FILE', __FILE__ );
define( 'PAYBETA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Boot the plugin after all plugins are loaded so WooCommerce classes exist.
 */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Paybeta Payment Gateway requires WooCommerce to be installed and active.', 'paybeta' )
                . '</p></div>';
        } );
        return;
    }

    require_once PAYBETA_PLUGIN_DIR . 'includes/class-paybeta-api.php';
    require_once PAYBETA_PLUGIN_DIR . 'includes/class-paybeta-webhook.php';
    require_once PAYBETA_PLUGIN_DIR . 'includes/class-paybeta-gateway.php';

    add_filter(
        'woocommerce_payment_gateways',
        fn ( array $gateways ) => array_merge( $gateways, [ 'WC_Paybeta_Gateway' ] )
    );
} );
