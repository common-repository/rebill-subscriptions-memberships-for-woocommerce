<?php
/**
 * Plugin Name: Rebill Subscription for WooCommerce
 * Plugin URI: http://www.rebill.to/
 * Description: Rebill Subscription plugin for WooCommerce
 * Author: Rebill, Inc.
 * Author URI: http://rebill.to/
 * Version: 1.0.12
 * License: https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * Text Domain: wc-rebill-subscription
 * Domain Path: /languages/
 *
 * @package Rebill
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Rebill_Subscription' ) ) :

	include_once 'includes/class-wc-rebill-core.php';
	include_once 'includes/class-rebill-api.php';
	include_once 'includes/class-wc-rebill-product.php';
	include_once 'includes/class-wc-rebill-cart.php';

	/**
	 * WooCommerce Rebill main class.
	 */
	class WC_Rebill_Subscription {
		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.0.12';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		private static $instance = null;

		/**
		 * Plugin file path.
		 *
		 * @var string
		 */
		public static $plugin_file = null;

		/**
		 * Initialize the plugin.
		 */
		private function __construct() {
			// Load plugin text domain.
			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				include_once 'includes/class-wc-rebill-gateway.php';
				WC_Rebill_Gateway::get_instance();
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param   array $methods WooCommerce payment methods.
		 *
		 * @return  array          Payment methods with Mercantil.
		 */
		public function add_gateway( $methods ) {
			$methods[] = WC_Rebill_Gateway::get_instance();
			return $methods;
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return  void
		 */
		public function woocommerce_missing_notice() {
			// translators: %s: Plugin name.
			echo '<div class="error"><p>' . esc_html( sprintf( __( 'WooCommerce Rebill Gateway depends on the last version of %s to work!', 'wc-rebill-subscription' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'wc-rebill-subscription' ) . '</a>' ) ) . '</p></div>';
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Backwards compatibility with version prior to 2.1.
		 *
		 * @return object Returns database class.
		 */
		public static function woocommerce_database() {
			global $wpdb;
			return $wpdb;
		}
	}

	$rebill_locale = apply_filters( 'plugin_locale', get_locale(), 'wc-rebill-subscription' );
	load_textdomain( 'wc-rebill-subscription', trailingslashit( WP_LANG_DIR ) . 'wc-rebill-subscription/wc-rebill-subscription-' . $rebill_locale . '.mo' );
	load_plugin_textdomain( 'wc-rebill-subscription', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	WC_Rebill_Subscription::$plugin_file = __FILE__;

	add_action( 'plugins_loaded', array( 'WC_Rebill_Subscription', 'get_instance' ), 0 );

	include_once 'functions.php';

	new WC_Rebill_Cart();
	new WC_Rebill_Product();
endif;
