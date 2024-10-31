<?php
/**
 * Rebill
 *
 * API Connector
 *
 * @package    Rebill
 * @subpackage Rebill_API
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! class_exists( 'Rebill_API' ) ) :
	/**
	 * API connector
	 */
	class Rebill_API extends WC_Rebill_Core {

		/**
		 * API endpoint Sandbox
		 *
		 * @var string
		 */
		const API_SANDBOX = 'https://api-staging.rebill.to/v1';

		/**
		 * API endpoint Production
		 *
		 * @var string
		 */
		const API_PROD = 'https://api.rebill.to/v1';

		/**
		 * Sandbox mode
		 *
		 * @var bool
		 */
		private $is_sandbox = false;

		/**
		 * API constructor
		 */
		public function __construct() {
			WC_Rebill_Core::__construct();
		}

		/**
		 * Call method of API
		 *
		 * @param   string            $base_url Base Path URL.
		 * @param   string            $method Methood.
		 * @param   array             $headers Headers request.
		 * @param   bool|string|array $post_data Post DATA.
		 * @param   string            $http_method HTTP Method (GET/POST/PUT/DELETE).
		 * @param   bool|array        $args URL Params.
		 * @param   bool              $to_json Send post data in json encode.
		 * @param   bool              $return_decode Return json data decode.
		 * @param   bool|string|array $error_data Return error details.
		 *
		 * @return  bool|array
		 */
		private function call( $base_url, $method, $headers = array(), $post_data = false, $http_method = 'GET', $args = false, $to_json = true, $return_decode = true, &$error_data = null ) {
			static $is_retry = false;
			$wp_data         = array(
				'method'  => $http_method,
				'timeout' => 10,
				'headers' => array(),
			);
			$new_args = $args;
			$url             = $base_url . $method;
			self::log( 'call ' . $http_method . ' [' . $url . '] -> ' . self::pl( $headers, true ) . ' - ' . self::pl( $args, true ) . ' - ' . self::pl( $post_data, true ) );
			if ( is_array( $args ) ) {
				$new_args = http_build_query( $new_args );
				$url .= '?' . $new_args;
			} elseif ( is_string( $new_args ) && ! empty( $new_args ) ) {
				$url .= '?' . $new_args;
			}
			if ( $post_data ) {
				if ( $to_json ) {
					$post_data = wp_json_encode( $post_data );
					$headers   = array_merge(
						$headers,
						array(
							'Content-Type: application/json; charset=utf-8',
							'Content-Length: ' . strlen( $post_data ),
						)
					);
				} else {
					$headers = array_merge(
						$headers,
						array(
							'Content-Type: application/json; charset=utf-8',
							'Content-Length: ' . strlen( $post_data ),
						)
					);
				}
				self::log( 'DATA Send: ' . $post_data );
				$wp_data['body'] = $post_data;
			}
			foreach ( $headers as $h ) {
				$h2                                   = explode( ':', $h );
				$wp_data['headers'][ trim( $h2[0] ) ] = trim( $h2[1] );
			}
			$response = wp_remote_request(
				$url,
				$wp_data
			);
			if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				self::log( 'Result for ' . $http_method . ' [' . $url . ']: ' . self::pl( $response['body'], true ) );
				if ( $return_decode ) {
					return array(
						'headers' => $response,
						'data'    => json_decode( $response['body'], true ),
					);
				}
				return array(
					'headers' => $response,
					'data'    => $response['body'],
				);
			} else {
				$rsp = $response['body'];
				self::log( 'Error ' . $response['response']['code'] . ' [' . $url . ']: ' . self::pl( $rsp, true ) );
			}
			if ( ! $is_retry && ( stristr( $rsp, 'Invalid or expired token' ) !== false || stristr( $rsp, 'Unauthorized' ) !== false ) ) {
				$is_retry = true;
				$token    = $this->getToken( false, false, false, true );
				if ( $token ) {
					$found = false;
					foreach ( $headers as &$h ) {
						if ( stristr( $h, 'Authorization:' ) !== false ) {
							$h     = 'Authorization: Bearer ' . $token;
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						$headers[] = 'Authorization: Bearer ' . $token;
					}
					self::log( 'Error [' . $url . '] -> retry with new token...' );
					$result   = $this->call( $base_url, $method, $headers, $post_data, $http_method, $new_args, $to_json, $return_decode, $error_data );
					$is_retry = false;
					return $result;
				}
				$is_retry = false;
			}
			if ( func_num_args() >= 8 && ! empty( $rsp ) ) {
				if ( $return_decode ) {
					$error_data = json_decode( $rsp, true );
				} else {
					$error_data = $rsp;
				}
			}
			return false;
		}

		/**
		 * Get API Token
		 *
		 * @param   bool|string $user User.
		 * @param   bool|string $pass Pass.
		 * @param   bool        $is_sandbox Is Sandbox?.
		 * @param   bool        $forece_reload Force Reload.
		 *
		 * @return  bool|string
		 */
		public function getToken( $user = false, $pass = false, $is_sandbox = false, $forece_reload = false ) {
			if ( $user && $pass ) {
				$key = base64_encode( $user . ':' . $pass );
			} else {
				$key        = base64_encode( $this->config['user'] . ':' . $this->config['pass'] );
				$is_sandbox = isset( $this->config['sandbox'] ) && ( 'yes' === $this->config['sandbox'] || true === $this->config['sandbox'] );
			}
			$cache_id = 'getToken_' . md5( $key . self::pl( $is_sandbox, true ) );
			if ( ! $forece_reload ) {
				$result = self::get_cache( $cache_id );
				if ( $result ) {
					return 'error' === $result ? false : $result;
				}
			}
			if ( $is_sandbox ) {
				$result = $this->call( self::API_SANDBOX, '/getToken', array( 'Authorization: Basic ' . $key ) );
			} else {
				$result = $this->call( self::API_PROD, '/getToken', array( 'Authorization: Basic ' . $key ) );
			}
			if ( $result && isset( $result['data']['response'] ) && isset( $result['data']['response']['token'] ) && ! empty( $result['data']['response']['token'] ) ) {
				$current = strtotime( $result['data']['response']['currentTime'] );
				$expire  = strtotime( $result['data']['response']['expires'] );
				self::set_cache( $cache_id, $result['data']['response']['token'], $expire - $current );
				return $result['data']['response']['token'];
			}
			self::set_cache( $cache_id, 'error', 600 );
			return false;
		}

		/**
		 * Call GET method of API
		 *
		 * @param   string            $url Method.
		 * @param   bool|array        $args URL Params.
		 * @param   int               $ttl TTL.
		 * @param   array             $headers Headers request.
		 * @param   bool|string|array $error_data Return error details.
		 *
		 * @return  bool|array
		 */
		public function callApiGet( $url, $args = false, $ttl = 3600, $headers = array(), &$error_data = null ) {
			$token    = $this->getToken();
			$cache_id = 'callApiGet_' . md5( $url . self::pl( $token, true ) . self::pl( $args, true ) . self::pl( $headers, true ) );
			$result   = self::get_cache( $cache_id );
			if ( $result ) {
				self::log( 'GET-CACHE ' . $url . self::pl( $args, true ) );
				return 'error' !== $result ? $result : false;
			}
			$headers_request = array_merge( $headers, array() );
			if ( $token ) {
				$headers_request[] = 'Authorization: Bearer ' . $token;
			}
			$is_sandbox = isset( $this->config['sandbox'] ) && ( 'yes' === $this->config['sandbox'] || true === $this->config['sandbox'] );
			if ( $is_sandbox ) {
				$result = $this->call( self::API_SANDBOX, $url, $headers_request, false, 'GET', $args, true, true, $error_data );
			} else {
				$result = $this->call( self::API_PROD, $url, $headers_request, false, 'GET', $args, true, true, $error_data );
			}
			if ( $result ) {
				self::set_cache( $cache_id, $result['data'], $ttl );
				return $result['data'];
			}
			self::set_cache( $cache_id, 'error', 120 );
			return false;
		}

		/**
		 * Call DELETE method of API
		 *
		 * @param   string            $url Method.
		 * @param   bool|array        $args URL Params.
		 * @param   array             $headers Headers request.
		 * @param   bool|string|array $error_data Return error details.
		 *
		 * @return  bool|array
		 */
		public function callApiDelete( $url, $args = false, $headers = array(), &$error_data = null ) {
			$token           = $this->getToken();
			$headers_request = array_merge( $headers, array() );
			if ( $token ) {
				$headers_request[] = 'Authorization: Bearer ' . $token;
			}
			$is_sandbox = isset( $this->config['sandbox'] ) && ( 'yes' === $this->config['sandbox'] || true === $this->config['sandbox'] );
			if ( $is_sandbox ) {
				$result = $this->call( self::API_SANDBOX, $url, $headers_request, false, 'DELETE', $args, true, true, $error_data );
			} else {
				$result = $this->call( self::API_PROD, $url, $headers_request, false, 'DELETE', $args, true, true, $error_data );
			}
			if ( $result ) {
				return $result['data'];
			}
			return false;
		}

		/**
		 * Call POST method of API
		 *
		 * @param   string            $url Method.
		 * @param   array             $post_data URL Params.
		 * @param   bool|array        $args URL Params.
		 * @param   array             $headers Headers request.
		 * @param   bool|string|array $error_data Return error details.
		 *
		 * @return  bool|array
		 */
		public function callApiPost( $url, $post_data, $args = false, $headers = array(), &$error_data = null ) {
			$token = $this->getToken();

			$headers_request = array_merge( $headers, array() );
			if ( $token ) {
				$headers_request[] = 'Authorization: Bearer ' . $token;
			}
			$is_sandbox = isset( $this->config['sandbox'] ) && ( 'yes' === $this->config['sandbox'] || true === $this->config['sandbox'] );
			if ( $is_sandbox ) {
				$result = $this->call( self::API_SANDBOX, $url, $headers_request, $post_data, 'POST', $args, true, true, $error_data );
			} else {
				$result = $this->call( self::API_PROD, $url, $headers_request, $post_data, 'POST', $args, true, true, $error_data );
			}
			if ( $result ) {
				return $result['data'];
			}
			return false;
		}

		/**
		 * Call POST method of API
		 *
		 * @param   string            $url Method.
		 * @param   array             $post_data URL Params.
		 * @param   bool|array        $args URL Params.
		 * @param   array             $headers Headers request.
		 * @param   bool|string|array $error_data Return error details.
		 *
		 * @return  bool|array
		 */
		public function callApiPut( $url, $post_data, $args = false, $headers = array(), &$error_data = null ) {
			$token           = $this->getToken();
			$headers_request = array_merge( $headers, array() );
			if ( $token ) {
				$headers_request[] = 'Authorization: Bearer ' . $token;
			}
			$is_sandbox = isset( $this->config['sandbox'] ) && ( 'yes' === $this->config['sandbox'] || true === $this->config['sandbox'] );
			if ( $is_sandbox ) {
				$result = $this->call( self::API_SANDBOX, $url, $headers_request, $post_data, 'PUT', $args, true, true, $error_data );
			} else {
				$result = $this->call( self::API_PROD, $url, $headers_request, $post_data, 'PUT', $args, true, true, $error_data );
			}
			if ( $result ) {
				return $result['data'];
			}
			return false;
		}
	}
endif;
