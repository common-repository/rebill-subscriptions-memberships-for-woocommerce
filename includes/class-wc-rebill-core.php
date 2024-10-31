<?php
/**
 * Rebill
 *
 * Core Class
 *
 * @package    Rebill
 * @subpackage WC_Rebill_Core
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! class_exists( 'WC_Rebill_Core' ) ) :
	/**
	 * WooCommerce Rebill Core main class.
	 */
	class WC_Rebill_Core {
		/**
		 * Cache var
		 *
		 * @var array
		 */
		private static $module_cache = array();

		/**
		 * Show debug
		 *
		 * @var bool
		 */
		private static $show_debug = false;

		/**
		 * Configuration
		 *
		 * @var null|array
		 */
		protected $config = null;
		/**
		 * Constructor for the core.
		 *
		 * @return void
		 */
		public function __construct() {
			self::$show_debug = ( 'yes' === $this->get_option( 'debug' ) );
		}

		/**
		 * Get Option of Configuration
		 *
		 * @param   string      $key Keyword.
		 * @param   bool|string $default Default value.
		 *
		 * @return  bool|string
		 */
		public function get_option( $key, $default = false ) {
			if ( null === $this->config ) {
				$this->config = get_option( 'woocommerce_rebill-gateway_settings' );
			}
			if ( ! isset( $this->config[ $key ] ) ) {
				return $default;
			}
			return $this->config[ $key ];
		}

		/**
		 * Get configuration option value
		 *
		 * @param   int $synchronization_day Synchronization day.
		 * @param   int $time Unix time based.
		 *
		 * @return int Unix Time.
		 */
		protected function calculate_next_payment( $synchronization_day, $time = 0 ) {
			if ( ! $time ) {
				$time = time();
			}
			$current_day = gmdate( 'd', $time );
			if ( $current_day > $synchronization_day ) {
				return strtotime( gmdate( 'Y-m-', strtotime( '+1 month', $time ) ) . $synchronization_day );
			}
			return strtotime( gmdate( 'Y-m-' ) . $synchronization_day, $time );
		}

		/**
		 * Dump var
		 *
		 * @param   mixed $data Data.
		 * @param   bool  $return_log Return dump.
		 *
		 * @return  string
		 */
		public static function pl( &$data, $return_log = false ) {
			if ( ! self::$show_debug ) {
				return '';
			}
			return var_export( $data, $return_log );
		}

		/**
		 * Dump messaage in log file
		 *
		 * @param   string $message Message.
		 *
		 * @return  void
		 */
		public static function log( $message ) {
			self::debug( $message );
		}

		/**
		 * Dump messaage in log file
		 *
		 * @param   string $message Message.
		 * @param   mixed  $data Data.
		 *
		 * @return  void
		 */
		public static function debug( $message, $data = false ) {
			if ( self::$show_debug && ! empty( $message ) ) {
				$path = dirname( __FILE__ ) . '/..';
				if ( ! is_dir( $path . '/logs' ) ) {
					mkdir( $path . '/logs' );
				}
				if ( ! is_dir( $path . '/logs/' . gmdate( 'Y-m' ) ) ) {
					mkdir( $path . '/logs/' . gmdate( 'Y-m' ) );
				}
				$fp = fopen( $path . '/logs/' . gmdate( 'Y-m' ) . '/log-' . gmdate( 'Y-m-d' ) . '.log', 'a' );
				fwrite( $fp, "\n----- " . gmdate( 'Y-m-d H:i:s' ) . " -----\n" );
				fwrite( $fp, $message . ( $data ? self::pl( $data, true ) : '' ) );
				fclose( $fp );
			}
		}

		/**
		 * Get data from Cache Table
		 *
		 * @param   string $cache_id Cache ID.
		 *
		 * @return  mixed
		 */
		public static function get_cache( $cache_id ) {
			global $wpdb;
			if ( isset( self::$module_cache[ $cache_id ] ) ) {
				return self::$module_cache[ $cache_id ];
			}
			$d = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT `data`, `ttl` FROM
						`' . $wpdb->prefix . 'rebill_cache`
					WHERE
						`cache_id` = %s
                    LIMIT 1',
					$cache_id
				)
			);
			if ( $d && isset( $d[0] ) && isset( $d[0]->ttl ) ) {
				if ( $d[0]->ttl < time() ) {
					$d = false;

					$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . 'rebill_cache` WHERE ttl < %d OR cache_id = %s', time(), $cache_id ) );
				} else {
					$d = $d[0]->data;
				}
			} else {
				$d = false;
			}
			$data = false;
			if ( $d ) {
				$data                            = json_decode( $d, true );
				self::$module_cache[ $cache_id ] = $data;
			}
			return $data;
		}

		/**
		 * Save data in Cache Table
		 *
		 * @param   string $cache_id Cache ID.
		 * @param   mixed  $value DATA.
		 * @param   int    $ttl TTL.
		 *
		 * @return  void
		 */
		public static function set_cache( $cache_id, $value, $ttl = 21600 ) {
			global $wpdb;
			self::$module_cache[ $cache_id ] = $value;

			$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . 'rebill_cache` WHERE ttl < %d OR cache_id = %s', time(), $cache_id ) );

			$wpdb->insert(
				$wpdb->prefix . 'rebill_cache',
				array(
					'cache_id' => $cache_id,
					'data'     => wp_json_encode( $value ),
					'ttl'      => time() + $ttl,
				)
			);
		}
		/**
		 * Check if session exists
		 *
		 * @return  void
		 */
		public static function check_session() {
			if ( ! isset( WC()->session ) || null === WC()->session ) {
				$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
				WC()->session  = new $session_class();
				WC()->session->init();
			}
		}
	}
endif;
