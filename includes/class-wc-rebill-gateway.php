<?php
/**
 * Rebill
 *
 * Payment Gateway Class
 *
 * @package    Rebill
 * @subpackage WC_Rebill_Gateway
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! class_exists( 'WC_Rebill_Gateway' ) ) :

	/**
	 * WooCommerce Rebill Gateway main class.
	 */
	class WC_Rebill_Gateway extends WC_Payment_Gateway {
		/**
		 * Unique Instance of this class
		 *
		 * @var WC_Rebill_Gateway
		 */
		private static $is_load = null;

		/**
		 * Instance of Rebill_API
		 *
		 * @var Rebill_API
		 */
		private $api = false;

		/**
		 * Constructor for the gateway.
		 *
		 * @return void
		 */
		public function __construct() {

			$this->id           = 'rebill-gateway';
			$this->icon         = apply_filters( 'woocommerce_rebill_icon', plugins_url( 'images/logo-mini-v2.gif', plugin_dir_path( __FILE__ ) ) );
			$this->has_fields   = false;
			$this->method_title = __( 'Rebill', 'woocommerce-mercantil' );

			// Load the form fields.
			$this->init_form_fields();

			self::check_database();

			// Actions.
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_text' ), 10, 2 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 3 );
			add_action( 'woocommerce_order_refunded', array( $this, 'order_refunded' ), 10, 2 );

			add_action( 'wp_enqueue_scripts', array( $this, 'hook_js' ) );
			add_action( 'wp_head', array( $this, 'hook_css' ) );

			$this->sandbox          = 'yes' === $this->get_option( 'sandbox' );
			$this->show_debug       = 'yes' === $this->get_option( 'debug' );
			$this->mp_completed     = 'yes' === $this->get_option( 'mp_completed' );
			$this->org_uuid         = $this->get_option( 'UUID' );
			$this->title            = $this->get_option( 'title' );
			$this->description      = $this->get_option( 'description' );
			$this->btn_suscribe     = $this->get_option( 'btn_suscribe', __( 'Subscribe to this product', 'wc-rebill-subscription' ) );
			$this->btn_onetime      = $this->get_option( 'btn_suscribe', __( 'One-time purchase', 'wc-rebill-subscription' ) );
			$this->rebill_thankpage = $this->get_option( 'rebill_thankpage' );

			$user = $this->get_option( 'user' );
			$pass = $this->get_option( 'pass' );
			if ( ! empty( $user ) && ! empty( $pass ) ) {
				$this->api = new Rebill_API();
				$id_nonce  = 'woocommerce_' . $this->id . '_rebill_back_nonce';
				if ( is_admin() && current_user_can( 'administrator' ) && isset( $_POST[ $id_nonce ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $id_nonce ] ) ), 'rebill_back_nonce' ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification
					if ( isset( $_POST[ 'woocommerce_' . $this->id . '_user' ] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification
						$user = sanitize_text_field( wp_unslash( $_POST[ 'woocommerce_' . $this->id . '_user' ] ) );
					}
					// phpcs:ignore WordPress.Security.NonceVerification
					if ( isset( $_POST[ 'woocommerce_' . $this->id . '_pass' ] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification
						$pass = sanitize_text_field( wp_unslash( $_POST[ 'woocommerce_' . $this->id . '_pass' ] ) );
					}
					// phpcs:ignore WordPress.Security.NonceVerification
					if ( isset( $_POST[ 'woocommerce_' . $this->id . '_sandbox' ] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification
						$this->sandbox = '1' === $_POST[ 'woocommerce_' . $this->id . '_sandbox' ];
					}
				}
				$token = $this->api->getToken( $user, $pass, $this->sandbox );
				if ( ! $token && is_admin() ) {
					add_action( 'admin_notices', array( $this, 'woocommerce_invalid_user' ) );
				}
				if ( $token && is_admin() ) {
					// phpcs:ignore WordPress.Security.NonceVerification
					if ( isset( $_GET['section'] ) && 'rebill-gateway' === $_GET['section'] ) {
						$webhooks     = $this->api->callApiGet( '/webhooks', false, 1 );
						$list_webhook = array(
							'sync_status_payments'      => rtrim( home_url(), '/' ) . '/?ipn-rebill=sync_status_payments',
							'sync_status_subscriptions' => rtrim( home_url(), '/' ) . '/?ipn-rebill=sync_status_subscriptions',
							'subscription_charge_in_24_hours' => rtrim( home_url(), '/' ) . '/?ipn-rebill=subscription_charge_in_24_hours',
						);
						if ( $webhooks && isset( $webhooks['response'] ) && is_array( $webhooks['response'] ) ) {
							foreach ( $webhooks['response'] as $webhook ) {
								if ( isset( $list_webhook[ $webhook['action'] ] ) && $list_webhook[ $webhook['action'] ] === $webhook['sync_url'] && ! $webhook['deletedAt'] ) {
									unset( $list_webhook[ $webhook['action'] ] );
								} elseif ( isset( $list_webhook[ $webhook['action'] ] ) && ! $webhook['deletedAt'] ) {
									$this->api->callApiDelete( '/webhooks/' . $webhook['id'] );
								}
							}
						}
						foreach ( $list_webhook as $action => $sync_url ) {
							$this->api->callApiPost(
								'/webhooks',
								array(
									'frequency_type' => 'minutes',
									'frequency'      => 3,
									'action'         => $action,
									'sync_url'       => $sync_url,
								)
							);
						}
					}
				}
			}

			self::$is_load = $this;
		}

		/**
		 * Display Invalid User/Pass of Rebill in wp-admin
		 *
		 * @return void
		 */
		public function woocommerce_invalid_user() {
			echo '<div class="error"><p>' . esc_html( __( 'Rebill: User or Pass invalid', 'wc-rebill-subscription' ) ) . '</p></div>';
		}

		/**
		 * Display JS Files
		 *
		 * @return void
		 */
		public function hook_js() {
			?>
			<script>
				function changeRebillFrequency(input) {
					var $ = jQuery;
					$('.subscription_detail_frequency', $(input).closest('.rebill_product_form')).html($('option:selected', input).html().toLowerCase());
					var parent = $(input).parent();
					while($('form:not(.is_rebill_subscription)', parent).length == 0) {
						parent = parent.parent();
						if (!parent) {
							return;
						}
					}
					var form = $('form:not(.is_rebill_subscription)', parent);
					if ($('[name="rebill_subscription_frequency"]', form).length) {
						$('[name="rebill_subscription_frequency"]', form).val($(input).val());
					} else {
						$(form).append('<input name="rebill_subscription_frequency" type="hidden" value="'+$(input).val()+'" />');
					}
				}
				function submitRebillSubscriptionForm(input) {
					var $ = jQuery;
					var parent = $(input).parent();
					while($('form:not(.is_rebill_subscription)', parent).length == 0) {
						parent = parent.parent();
						if (!parent) {
							return;
						}
					}
					var form_product = $('form:not(.is_rebill_subscription)', parent);
					var form = $(input).closest('form');
					if (form.length > 0 && form_product.length > 0) {
						$('.rebill_other_inputs input', form).remove();
						$('input, select', form_product).each(function() {
							if($(this).attr('type') == 'checkbox' || $(this).attr('type') == 'radio') {
								if (!$(this).is(':checked')) return;
							}
							$('.rebill_other_inputs', form).append('<input name="'+$(this).attr('name')+'" type="hidden" value="'+$(this).val()+'" />');
						});
					}
					form.first().submit();
				}
			</script>
			<?php
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 *
		 * @return array           Redirect.
		 */
		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		/**
		 * Get configuration option value
		 *
		 * @param   int $synchronization_day Synchronization day.
		 * @param   int $time Current Unixtime.
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
		 * Generate the form.
		 *
		 * @param int $order_id Order ID.
		 *
		 * @return void           Payment form.
		 */
		public function receipt_page( $order_id ) {
			echo '<p>' . esc_html( __( 'Thank you for your order, fill in the following information to process your order with', 'wc-rebill-subscription' ) ) . ' <a href="https://rebill.to/" target="_blank">© Rebill</a></p>';
			$order = new WC_Order( $order_id );
			WC_Rebill_Core::check_session();
			WC()->session->set( 'rebill_cart', array() );
			WC()->cart->empty_cart();
			$base_location = wc_get_base_location();
			// $country            = strtolower( $base_location['country'] );
			$vat_type           = trim( $order->get_meta( '_billing_rebill_vat_type' ) );
			$vat                = trim( $order->get_meta( '_billing_rebill_vat' ) );
			$name               = $order->get_billing_first_name();
			$lastname           = $order->get_billing_last_name();
			$phone              = $order->get_billing_phone();
			$address_1          = $order->get_billing_address_1();
			$address_2          = $order->get_billing_address_2();
			$postcode           = $order->get_billing_postcode();
			$state              = $order->get_billing_state();
			$country            = $order->get_billing_country();
			$state              = $order->get_billing_state();
			$city               = $order->get_billing_city();
			$email              = $order->get_billing_email();
			$subscription       = $order->get_meta( 'rebill_subscription' );
			$order_total        = $order->get_total();
			$title              = '';
			$title_sub          = array();
			$order_subscription = $order->get_meta( 'rebill_subscription' );
			$var_required       = true;
			$states             = WC()->countries->get_states( $country );
			if ( $states && isset( $states[ $state ] ) ) {
				$state = $states[ $state ];
			}
			switch ( strtolower( $country ) ) {
				case 'ar':
				case 'br':
				case 'cl':
				case 'co':
				case 'pe':
				case 'uy':
					break;
				default:
					$var_required = false;
					break;
			}
			foreach ( $order->get_items() as $key => $item ) {
				if ( ! (bool) $item->get_meta( '_is_rebill_product' ) ) {
					if ( ! empty( $title ) ) {
						$title .= ' - ';
					}
					// translators: %s Quantity of product.
					$title .= sprintf( __( '%s U. of', 'wc-rebill-subscription' ), $item['qty'] ) . ' ';
					if ( isset( $item['variation_id'] ) && (int) $item['variation_id'] > 0 ) {
						$variation            = wc_get_product( (int) $item['variation_id'] );
						$formatted_attributes = function_exists( 'wc_get_formatted_variation' ) ? wc_get_formatted_variation( $variation, true ) : $variation->get_formatted_variation_attributes( true );
						$title               .= $variation->get_title() . ', ' . $formatted_attributes;
					} else {
						$title .= $item['name'];
					}
				} else {
					$p_hash = '';
					foreach ( $order_subscription as $hash => $s ) {
						if ( isset( $s['products'][ $item['product_id'] ] ) ) {
							$p_hash = $order_id . '-' . $hash;
							break;
						}
					}
					if ( ! isset( $title_sub[ $p_hash ] ) ) {
						$title_sub[ $p_hash ] = '';
					}
					if ( ! empty( $title_sub[ $p_hash ] ) ) {
						$title_sub[ $p_hash ] .= ' - ';
					}
					// translators: %s Quantity of product.
					$title_sub[ $p_hash ] .= sprintf( __( '%s U. of', 'wc-rebill-subscription' ), $item['qty'] ) . ' ';
					if ( isset( $item['variation_id'] ) && (int) $item['variation_id'] > 0 ) {
						$variation             = wc_get_product( (int) $item['variation_id'] );
						$formatted_attributes  = function_exists( 'wc_get_formatted_variation' ) ? wc_get_formatted_variation( $variation, true ) : $variation->get_formatted_variation_attributes( true );
						$title_sub[ $p_hash ] .= $variation->get_title() . ', ' . $formatted_attributes;
					} else {
						$title_sub[ $p_hash ] .= $item['name'];
					}
				}
			}

			$customer   = get_current_user_id();
			$list_cards = array();
			if ( $customer ) {
				$cards = get_user_meta( $customer, 'rebill_cards', true );
				if ( is_array( $cards ) && count( $cards ) ) {
					foreach ( $cards as $id ) {
						$card = $this->api->callApiGet( '/cards/id/' . $id, false, 60 );
						if ( $card && isset( $card ) && isset( $card['response'] ) && isset( $card['response']['id'] ) ) {
							$card = $card['response'];
							if ( (int) gmdate( 'Y' ) < (int) $card['expiration_year'] || (int) gmdate( 'Y' ) === (int) $card['expiration_year'] && (int) gmdate( 'n' ) <= (int) $card['expiration_month'] ) {
								$list_cards[] = $card;
							}
						}
					}
				}
			}
			$data = array(
				'order_total'              => $order_total,
				'list_cards'               => $list_cards,
				'rebill_subscription_data' => $subscription,
				'title_sub'                => $title_sub,
				'title'                    => $title,
				'current_order'            => $order,
				'order_id'                 => $order_id,
				'var_required'             => $var_required,
				'return_url'               => $this->get_return_url( $order ),
				'customer'                 => array(
					'firstName'        => $name,
					'lastName'         => $lastname,
					'email'            => $email,
					'phone_number'     => $phone,
					'address_street'   => trim( $address_1 . ' ' . $address_2 ),
					'address_province' => $state,
					'address_city'     => $city,
					'address_zipcode'  => $postcode,
				),
				'org_uuid'                 => $this->org_uuid,
				'endpoint'                 => $this->sandbox ? Rebill_API::API_SANDBOX : Rebill_API::API_PROD,
			);
			if ( $var_required || ! empty( $vat_type ) && ! empty( $vat ) ) {
				$data['customer']['vat_type']          = $vat_type;
				$data['customer']['vatID_number']      = $vat;
				$data['customer']['personalID_type']   = $vat_type;
				$data['customer']['personalID_number'] = $vat;
			}
			wc_get_template(
				'credit-card-request.php',
				$data,
				'',
				plugin_dir_path( WC_Rebill_Subscription::$plugin_file ) . 'templates/'
			);
		}

		/**
		 * Order refunded
		 *
		 * @param int $order_id Order ID.
		 * @param int $refund_id Refund ID.
		 * @return void
		 */
		public function order_refunded( $order_id, $refund_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$all_transactions = $order->get_meta( 'rebill_transactions' );
				if ( $all_transactions && $this->api ) {
					foreach ( $all_transactions as $transaction ) {
						$this->api->callApiPost(
							'/payments/refund',
							array(
								'payment_id' => $transaction['gateway_payment_id'],
							)
						);
					}
				}
				if ( (bool) $order->get_meta( 'is_rebill_first_order' ) ) {
					$id = $order->get_meta( 'rebill_subscription_id' );
					if ( $id ) {
						$api    = new Rebill_API();
						$u      = array(
							'id'     => $id,
							'status' => 'cancelled',
						);
						$result = $api->callApiPut( '/subscriptions', $u );
						if ( $result && $result['success'] ) {
							$id_order_parent = $order->get_meta( 'rebill_to_clone' );
							$ref             = $order->get_meta( 'rebill_ref' );
							update_post_meta( $order_id, 'rebill_sub_status', 'cancelled' );
							if ( isset( $id_order_parent[ $ref ] ) ) {
								$p_order = wc_get_order( $id_order_parent[ $ref ] );
								if ( (int) $id_order_parent[ $ref ] === (int) $p_order->get_id() ) {
									update_post_meta( $p_order->get_id(), 'rebill_sub_status', 'cancelled' );
									$p_order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by customer.', 'wc-rebill-subscription' ) );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Check status update
		 *
		 * @param int    $order_id Order ID.
		 * @param string $old_status Old Status.
		 * @param string $new_status New Status.
		 * @return void
		 */
		public function status_changed( $order_id, $old_status = false, $new_status = false ) {
			WC_Rebill_Core::debug( "new status $order_id: " . WC_Rebill_Core::pl( $new_status, true ) );
			if ( 'refunded' !== $new_status && 'cancelled' !== $new_status ) {
				return;
			}
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$all_transactions = $order->get_meta( 'rebill_transactions' );
				if ( $all_transactions && $this->api ) {
					foreach ( $all_transactions as $transaction ) {
						$this->api->callApiPost(
							'/payments/refund',
							array(
								'payment_id' => $transaction['gateway_payment_id'],
							)
						);
					}
				}
				if ( (bool) $order->get_meta( 'is_rebill_first_order' ) ) {
					$id = $order->get_meta( 'rebill_subscription_id' );
					if ( $id ) {
						$api    = new Rebill_API();
						$u      = array(
							'id'     => $id,
							'status' => 'cancelled',
						);
						$result = $api->callApiPut( '/subscriptions', $u );
						if ( $result && $result['success'] ) {
							$id_order_parent = $order->get_meta( 'rebill_to_clone' );
							$ref             = $order->get_meta( 'rebill_ref' );
							update_post_meta( $order_id, 'rebill_sub_status', 'cancelled' );
							if ( isset( $id_order_parent[ $ref ] ) ) {
								$p_order = wc_get_order( $id_order_parent[ $ref ] );
								if ( (int) $id_order_parent[ $ref ] === (int) $p_order->get_id() ) {
									update_post_meta( $p_order->get_id(), 'rebill_sub_status', 'cancelled' );
									$p_order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by customer.', 'wc-rebill-subscription' ) );
								}
							}
						}
					}
				}
			}
		}
		/**
		 * Check client-side error payment data
		 *
		 * @param int   $order_id Order ID.
		 * @param array $payments Rebill Payments DATA.
		 *
		 * @return void
		 */
		public function check_client_response_error( $order_id, $payments ) {
			WC_Rebill_Core::debug( "check_client_response_error $order_id - " . WC_Rebill_Core::pl( $payments, true ) );
		}

		/**
		 * Check client-side payment data
		 *
		 * @param int   $order_id Order ID.
		 * @param array $payments Rebill Payments DATA.
		 *
		 * @return string URL of thank page.
		 */
		public function check_client_response( $order_id, $payments ) {
			$order_id     = (int) $order_id;
			$subscription = false;
			$order        = new WC_Order( $order_id );
			WC_Rebill_Core::debug( 'check_client_response - ' . $order_id . ' - ' . WC_Rebill_Core::pl( $payments, true ) );
			$order_total    = $order->get_total();
			$subscription   = $order->get_meta( 'rebill_subscription' );
			$wait_payments  = array();
			$shipping_ready = false;
			if ( $subscription && is_array( $subscription ) && count( $subscription ) > 0 ) {
				foreach ( $subscription as $hash => $s ) {
					$total = $s['total'];
					if ( ! $shipping_ready ) {
						$shipping_ready = true;
						$total         += $s['shipping_cost'];
					}
					$total                  = min( $total, $order_total );
					$wait_payments[ $hash ] = $total;
					$order_total           -= $total;
				}
			}
			if ( $order_total > 0 ) {
				$wait_payments['onetime'] = $order_total;
			}
			foreach ( $payments as $data ) {
				if ( $this->api && (int) isset( $data['response'] ) && isset( $data['response']['subscription'] ) && isset( $data['response']['subscription']['id'] ) ) {
					if ( isset( $data['response']['payment'] ) && $data['response']['payment'] && isset( $data['response']['payment']['id'] ) ) {
						$payment = $this->api->callApiGet( '/payments/' . $data['response']['payment']['id'], false, 120 );
						if ( $payment && isset( $payment['response'] ) && isset( $payment['response']['id'] ) && (int) $payment['response']['subscription_id'] === (int) $data['response']['subscription']['id'] ) {
							$payment      = $payment['response'];
							$subscription = $this->api->callApiGet( '/subscriptions/id/' . $data['response']['subscription']['id'], false, 120 );
							if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
								$subscription = $subscription['response'][0];
								$ref          = explode( '-', $subscription['external_reference'] );
								if ( ! isset( $wait_payments[ $ref[1] ] ) || abs( $wait_payments[ $ref[1] ] - $subscription['transaction_amount'] ) > 0.001 ) {
									WC_Rebill_Core::debug( "wait_payments subscription1  {$wait_payments[ $ref[1] ]} != {$subscription['transaction_amount']} " );
									continue;
								} else {
									WC_Rebill_Core::debug( "wait_payments subscription2 {$ref[0]} === $order_id" );
								}
								if ( (int) $ref[0] === $order_id ) {
									$order->update_meta_data( 'is_rebill_first_order', true );
									$this->check_rebill_response( $order, $payment, $subscription, count( $payments ) > 1 );
								}
							}
						}
					} else {
						$subscription = $this->api->callApiGet( '/subscriptions/id/' . $data['response']['subscription']['id'], false, 120 );
						if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
							$subscription = $subscription['response'][0];
							$ref          = explode( '-', $subscription['external_reference'] );
							if ( ! isset( $wait_payments[ $ref[1] ] ) ) {
								WC_Rebill_Core::debug( 'wait_payments subscription3 ' . $ref[1] . ': not expected - ' . WC_Rebill_Core::pL( $wait_payments, true ) );
								continue;
							}
							if ( abs( $wait_payments[ $ref[1] ] - $subscription['transaction_amount'] ) > 0.001 ) {
								WC_Rebill_Core::debug( "wait_payments subscription4 '.$ref[1].': {$wait_payments[ $ref[1] ]} != {$subscription['transaction_amount']} - " . WC_Rebill_Core::pL( $wait_payments, true ) );
								continue;
							} else {
								WC_Rebill_Core::debug( "wait_payments subscription5 {$ref[0]} === $order_id" );
							}
							if ( (int) $ref[0] === $order_id ) {
								$order->update_meta_data( 'is_rebill_first_order', true );
								$this->check_rebill_response( $order, false, $subscription, count( $payments ) > 1 );
							}
						}
					}
				} elseif ( $this->api && (int) $order->get_id() === $order_id && isset( $data['response'] ) && isset( $data['response']['localResponse'] ) && isset( $data['response']['localResponse']['id'] ) ) {
					$payment = $this->api->callApiGet( '/payments/' . $data['response']['localResponse']['id'], false, 120 );
					if ( $payment && isset( $payment['response'] ) && isset( $payment['response']['id'] ) ) {
						$payment = $payment['response'];
						$ref     = explode( '-', $payment['external_reference'] );
						if ( (int) $ref[0] === $order_id ) {
							if ( ! isset( $wait_payments['onetime'] ) ) {
								WC_Rebill_Core::debug( 'wait_payments onetime: not expected - ' . WC_Rebill_Core::pL( $wait_payments, true ) );
								continue;
							}
							if ( abs( $wait_payments['onetime'] - $payment['transaction_amount'] ) > 0.001 ) {
								WC_Rebill_Core::debug( "wait_payments onetime: {$wait_payments['onetime']} != {$payment['transaction_amount']} - " . WC_Rebill_Core::pL( $wait_payments, true ) );
								continue;
							} else {
								WC_Rebill_Core::debug( "wait_payments onetime {$ref[0]} === $order_id" );
							}
							$order->update_meta_data( 'is_rebill_one_payment', true );
							$this->check_rebill_response( $order, $payment, false, count( $payments ) > 1 );
						}
					}
				}
			}
			$rebill_thankpage = $order->get_meta( 'rebill_thankpage' );
			if ( ! empty( $rebill_thankpage ) && 'ignore' !== $rebill_thankpage ) {
				return $rebill_thankpage;
			}
			if ( ! empty( $this->rebill_thankpage ) ) {
				return $this->rebill_thankpage;
			}
			return $this->get_return_url( $order );
		}
		/**
		 * Update meta data of first order with subscription data
		 *
		 * @param WC_Order $order Order.
		 * @param array    $subscription Rebill Subscription DATA.
		 * @param array    $payment Rebill Payment DATA.
		 * @param array    $ref Rebill Subscription Ref ID in WooCommerce.
		 * @return void
		 */
		private function update_meta_order_first_order( $order, $subscription, $payment, $ref ) {
			if ( $subscription ) {
				$id_first_order = $order->get_id();
				update_post_meta( $id_first_order, 'rebill_subscription_id', $subscription['id'] );
				update_post_meta( $id_first_order, 'rebill_card_id', $subscription['card_id'] );
				update_post_meta( $id_first_order, 'rebill_sub_status', $subscription['status'] );
				update_post_meta( $id_first_order, 'is_rebill_first_order', true );
				update_post_meta( $id_first_order, 'is_rebill_renew', true );
				$order_subscription_id = get_post_meta( $id_first_order, 'rebill_order_subscription_id', true );
				if ( ! is_array( $order_subscription_id ) ) {
					$order_subscription_id = array();
				}
				WC_Rebill_Core::debug( 'order_subscription_id ' . $id_first_order . ' -> ' . WC_Rebill_Core::pl( $order_subscription_id, true ) );
				if ( ! isset( $order_subscription_id[ $ref ] ) ) {
					$order_parent = $this->duplicate_order( $order, $ref, true );
					if ( $order_parent && $order_parent->get_id() > 0 ) {
						$order_subscription_id[ $ref ] = (int) $order_parent->get_id();
						WC_Rebill_Core::debug( 'new parent: ' . $order_subscription_id[ $ref ] );
						update_post_meta( $order_subscription_id[ $ref ], 'is_rebill', true );
						update_post_meta( $order_subscription_id[ $ref ], 'rebill_subscription_id', $subscription['id'] );
						update_post_meta( $order_subscription_id[ $ref ], 'rebill_subscription_first_order', $order->get_id() );
						WC_Rebill_Core::debug( 'new parent pre update_status: ' . $order_subscription_id[ $ref ] );
						$order_parent->update_status( 'rebill-toclone', __( 'Rebill: Parent Order.', 'wc-rebill-subscription' ) );
						WC_Rebill_Core::debug( 'new parent post update_status: ' . $order_subscription_id[ $ref ] );
						if ( function_exists( 'has_post_parent' ) ) {
							wp_update_post(
								array(
									'ID'          => $order->get_id(),
									'post_parent' => $order_subscription_id[ $ref ],
								)
							);
						}
						WC_Rebill_Core::debug( 'new parent post has_post_parent: ' . $order_subscription_id[ $ref ] );
						update_post_meta( $id_first_order, 'rebill_order_subscription_id', $order_subscription_id );
						WC_Rebill_Core::debug( 'order_subscription_id updated ' . $id_first_order . ' -> ' . WC_Rebill_Core::pl( $order_subscription_id, true ) );
					}
				}
				WC_Rebill_Core::debug( 'new parent pre general update order_parent: ' . $order_subscription_id[ $ref ] );
				if ( isset( $order_subscription_id[ $ref ] ) ) {
					WC_Rebill_Core::debug( 'new parent inner general update order_parent: ' . $order_subscription_id[ $ref ] );
					update_post_meta( $order_subscription_id[ $ref ], 'is_rebill_to_clone', true );
					update_post_meta( $order_subscription_id[ $ref ], 'rebill_subscription_id', $subscription['id'] );
					update_post_meta( $order_subscription_id[ $ref ], 'rebill_card_id', $subscription['card_id'] );
					update_post_meta( $order_subscription_id[ $ref ], 'rebill_sub_status', $subscription['status'] );
					update_post_meta( $order_subscription_id[ $ref ], 'rebill_next_payment', wp_json_encode( $subscription['next_payments'] ) );
					/*
					// TODO: No se recomienda este tipo de update por el momento, PD: CASO BIURY
					if ( $payment ) {
						$to_clone = new WC_Order( $order_subscription_id[ $ref ] );
						if ( abs( $to_clone->get_total() - $subscription['transaction_amount'] ) > 0.001 ) {
							$u                       = array( 'id' => $subscription['id'] );
							$u['transaction_amount'] = $to_clone->get_total();
							$this->api->callApiPut( '/subscriptions', $u );
						}
					}
					*/
					WC_Rebill_Core::debug( 'new parent end general update order_parent: ' . $order_subscription_id[ $ref ] . ': ' . WC_Rebill_Core::pl( $subscription, true ) );
				}
			}
		}
		/**
		 * Check rebill webhook data
		 *
		 * @param string $action Rebill action.
		 * @param array  $data Rebill webhook DATA.
		 * @return void
		 */
		public function check_ipn( $action, $data ) {
			global $wpdb;
			WC_Rebill_Core::debug( 'check_ipn - ' . $action . ' - ' . WC_Rebill_Core::pl( $data, true ) );
			switch ( $action ) {
				case 'sync_status_payments':
					foreach ( $data as $d ) {
						$wpdb->insert(
							$wpdb->prefix . 'rebill_cronjob',
							array(
								'cmd'         => 'do_rebill_sync_status_payments',
								'arg'         => $d['payment_id'],
								'next_retry' => time() + wp_rand( 0, 5 * 60 ),
							)
						);
					}
					break;
				case 'sync_status_subscriptions':
					foreach ( $data as $d ) {
						$wpdb->insert(
							$wpdb->prefix . 'rebill_cronjob',
							array(
								'cmd'         => 'do_rebill_sync_status_subscriptions',
								'arg'         => $d['subscription_id'],
								'next_retry' => time() + wp_rand( 0, 5 * 60 ),
							)
						);
					}
					break;
				case 'subscription_charge_in_24_hours':
					foreach ( $data as $d ) {
						$wpdb->insert(
							$wpdb->prefix . 'rebill_cronjob',
							array(
								'cmd'         => 'do_rebill_subscription_charge_in_24_hours',
								'arg'         => $d['id'],
								'next_retry' => time() + wp_rand( 0, 5 * 60 ),
							)
						);
					}
					break;
			}
		}

		/**
		 * Duplicate Order
		 *
		 * @param WC_Order $order Original Order.
		 * @param string   $ref Subscription reference hash.
		 * @param bool     $is_first_order First order.
		 *
		 * @return WC_Order New order.
		 */
		public function duplicate_order( $order, $ref, $is_first_order = false ) {
			$to_clone = $order->get_meta( 'rebill_to_clone' );
			if ( ! is_array( $to_clone ) ) {
				$to_clone = array();
			}
			$subscription     = $order->get_meta( 'rebill_subscription' );
			$old_subscription = $order->get_meta( 'rebill_subscription' );
			WC_Rebill_Core::debug( 'duplicate_order - ' . $order->get_id() );
			WC_Rebill_Core::debug( 'duplicate_order - subscription -> ' . WC_Rebill_Core::pL( $subscription, true ) );
			$first_order_id = $order->get_id();
			$is_from_clone  = $order->get_meta( 'is_rebill_to_clone' );
			update_post_meta( $first_order_id, 'rebill_ref', $ref );
			if ( $is_first_order && isset( $to_clone[ $ref ] ) && $to_clone[ $ref ] !== (int) $order->get_id() ) {
				$order = new WC_Order( $to_clone[ $ref ] );
				WC_Rebill_Core::debug( 'duplicate_order_parent - ' . $order->get_id() );
			}
			if ( ! $is_from_clone && isset( $to_clone[ $ref ] ) ) {
				$is_from_clone = ( $to_clone[ $ref ] === (int) $order->get_id() );
			}
			$new_post_date     = current_time( 'mysql' );
			$new_post_date_gmt = get_gmt_from_date( $new_post_date );
			$original_order_id = $order->get_id();
			$order_data        = array(
				'post_author'       => get_post_field( 'post_author', $original_order_id ),
				'post_date'         => $new_post_date,
				'post_date_gmt'     => $new_post_date_gmt,
				'post_type'         => 'shop_order',
				// translators: %s: ID.
				'post_title'        => sprintf( __( 'Subscription %s - New Payment Order', 'wc-rebill-subscription' ), $order->get_id() ),
				'post_status'       => 'wc-pending',
				'ping_status'       => 'closed',
				'post_modified'     => $new_post_date,
				'post_modified_gmt' => $new_post_date_gmt,
			);
			if ( function_exists( 'has_post_parent' ) && isset( $to_clone[ $ref ] ) ) {
				$order_data['post_parent'] = $to_clone[ $ref ];
			}
			$order_id = wp_insert_post( $order_data, true );
			if ( ! is_wp_error( $order_id ) ) {
				WC_Rebill_Core::debug( 'new duplicate_order - ' . $order->get_id() . ' - ' . $order_id );
				$new_order = new WC_Order( $order_id );
				update_post_meta( $order_id, '_order_shipping', get_post_meta( $original_order_id, '_order_shipping', true ) );
				update_post_meta( $order_id, '_order_discount', get_post_meta( $original_order_id, '_order_discount', true ) );
				update_post_meta( $order_id, '_cart_discount', get_post_meta( $original_order_id, '_cart_discount', true ) );
				update_post_meta( $order_id, '_order_tax', get_post_meta( $original_order_id, '_order_tax', true ) );
				update_post_meta( $order_id, '_order_shipping_tax', get_post_meta( $original_order_id, '_order_shipping_tax', true ) );
				update_post_meta( $order_id, '_order_total', get_post_meta( $original_order_id, '_order_total', true ) );
				update_post_meta( $order_id, '_order_key', 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
				update_post_meta( $order_id, '_customer_user', get_post_meta( $original_order_id, '_customer_user', true ) );
				update_post_meta( $order_id, '_order_currency', get_post_meta( $original_order_id, '_order_currency', true ) );
				update_post_meta( $order_id, '_prices_include_tax', get_post_meta( $original_order_id, '_prices_include_tax', true ) );
				update_post_meta( $order_id, '_customer_ip_address', get_post_meta( $original_order_id, '_customer_ip_address', true ) );
				update_post_meta( $order_id, '_customer_user_agent', get_post_meta( $original_order_id, '_customer_user_agent', true ) );
				update_post_meta( $order_id, '_billing_city', get_post_meta( $original_order_id, '_billing_city', true ) );
				update_post_meta( $order_id, '_billing_state', get_post_meta( $original_order_id, '_billing_state', true ) );
				update_post_meta( $order_id, '_billing_postcode', get_post_meta( $original_order_id, '_billing_postcode', true ) );
				update_post_meta( $order_id, '_billing_email', get_post_meta( $original_order_id, '_billing_email', true ) );
				update_post_meta( $order_id, '_billing_phone', get_post_meta( $original_order_id, '_billing_phone', true ) );
				update_post_meta( $order_id, '_billing_address_1', get_post_meta( $original_order_id, '_billing_address_1', true ) );
				update_post_meta( $order_id, '_billing_address_2', get_post_meta( $original_order_id, '_billing_address_2', true ) );
				update_post_meta( $order_id, '_billing_country', get_post_meta( $original_order_id, '_billing_country', true ) );
				update_post_meta( $order_id, '_billing_first_name', get_post_meta( $original_order_id, '_billing_first_name', true ) );
				update_post_meta( $order_id, '_billing_last_name', get_post_meta( $original_order_id, '_billing_last_name', true ) );
				update_post_meta( $order_id, '_billing_company', get_post_meta( $original_order_id, '_billing_company', true ) );
				update_post_meta( $order_id, '_shipping_country', get_post_meta( $original_order_id, '_shipping_country', true ) );
				update_post_meta( $order_id, '_shipping_first_name', get_post_meta( $original_order_id, '_shipping_first_name', true ) );
				update_post_meta( $order_id, '_shipping_last_name', get_post_meta( $original_order_id, '_shipping_last_name', true ) );
				update_post_meta( $order_id, '_shipping_company', get_post_meta( $original_order_id, '_shipping_company', true ) );
				update_post_meta( $order_id, '_shipping_address_1', get_post_meta( $original_order_id, '_shipping_address_1', true ) );
				update_post_meta( $order_id, '_shipping_address_2', get_post_meta( $original_order_id, '_shipping_address_2', true ) );
				update_post_meta( $order_id, '_shipping_city', get_post_meta( $original_order_id, '_shipping_city', true ) );
				update_post_meta( $order_id, '_shipping_state', get_post_meta( $original_order_id, '_shipping_state', true ) );
				update_post_meta( $order_id, '_shipping_postcode', get_post_meta( $original_order_id, '_shipping_postcode', true ) );
				foreach ( $order->get_items() as $item ) {
					$pid = $item['product_id'];
					if ( ! $is_from_clone && ! (bool) $item->get_meta( '_is_rebill_product' ) ) {
						WC_Rebill_Core::debug( 'not found product _is_rebill_product - ' . $ref . ' - ' . $pid );
						continue;
					}
					if ( ! isset( $subscription[ $ref ]['products'][ $pid ] ) ) {
						WC_Rebill_Core::debug( 'not found product - ' . $ref . ' - ' . $pid );
						continue;
					}
					$vid = $item['variation_id'];
					if ( $vid && ! isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
						WC_Rebill_Core::debug( 'not found product variation - ' . $ref . ' - ' . $pid . ':' . $vid );
						continue;
					}
					$sqty = 0;
					$qty  = $item['qty'];
					if ( isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
						$sqty = $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ]['quantity'];
						$old_subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ]['quantity'] = min( $qty, $sqty );
						update_post_meta( $original_order_id, 'rebill_subscription', $old_subscription );
						unset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] );
					} elseif ( isset( $subscription[ $ref ]['products'][ $pid ] ) ) {
						$sqty = $subscription[ $ref ]['products'][ $pid ]['quantity'];
						$old_subscription[ $ref ]['products'][ $pid ]['quantity'] = min( $qty, $sqty );
						update_post_meta( $original_order_id, 'rebill_subscription', $old_subscription );
						unset( $subscription[ $ref ]['products'][ $pid ] );
					}
					if ( ! $sqty ) {
						WC_Rebill_Core::debug( 'quantity null for product variation - ' . $ref . ' - ' . $pid . ':' . $vid );
						continue;
					}
					WC_Rebill_Core::debug( 'is found product variation - ' . $ref . ' - ' . $pid . ':' . $vid . ' -> ' . WC_Rebill_Core::pL( $item, true ) );
					$name              = $item['name'];
					$sqty              = min( $qty, $sqty );
					$line_subtotal     = $item['line_subtotal'] / $qty;
					$line_total        = $item['line_total'] / $qty;
					$line_tax          = $item['line_tax'] / $qty;
					$line_subtotal_tax = $item['line_subtotal_tax'] / $qty;
					WC_Rebill_Core::debug( 'is found product variation - ' . $pid . ':' . $vid . ' qty -> ' . WC_Rebill_Core::pL( $qty, true ) );
					WC_Rebill_Core::debug( 'is found product variation - ' . $pid . ':' . $vid . ' line_total -> ' . WC_Rebill_Core::pL( $line_total, true ) );
					WC_Rebill_Core::debug( 'is found product variation - ' . $pid . ':' . $vid . ' line_tax -> ' . WC_Rebill_Core::pL( $line_tax, true ) );
					WC_Rebill_Core::debug( 'is found product variation - ' . $pid . ':' . $vid . ' line_subtotal -> ' . WC_Rebill_Core::pL( $line_subtotal, true ) );
					WC_Rebill_Core::debug( 'is found product variation - ' . $pid . ':' . $vid . ' line_subtotal_tax -> ' . WC_Rebill_Core::pL( $line_subtotal_tax, true ) );

					$item_id = wc_add_order_item(
						$order_id,
						array(
							'order_item_name' => $name,
							'order_item_type' => 'line_item',
						)
					);
					wc_add_order_item_meta( $item_id, '_qty', $sqty );
					wc_add_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
					wc_add_order_item_meta( $item_id, '_product_id', $pid );
					wc_add_order_item_meta( $item_id, '_variation_id', $vid );
					wc_add_order_item_meta( $item_id, '_line_subtotal', wc_format_decimal( $line_subtotal * $sqty ) );
					wc_add_order_item_meta( $item_id, '_line_total', wc_format_decimal( $line_total * $sqty ) );
					wc_add_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $line_tax * $sqty ) );
					wc_add_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $line_subtotal_tax * $sqty ) );
					WC_Rebill_Core::debug( 'Added itemid - ' . $item_id . ':' . $sqty . ' -> ' . WC_Rebill_Core::pL( $line_subtotal, true ) . WC_Rebill_Core::pL( $line_total, true ) . WC_Rebill_Core::pL( $line_tax, true ) . WC_Rebill_Core::pL( $line_subtotal_tax, true ) );
					$item = new WC_Order_Item_Product( $item_id );
					$item->add_meta_data( '_is_rebill_product', true );
				}
				$original_order_shipping_items = $order->get_items( 'shipping' );
				foreach ( $original_order_shipping_items as $original_order_shipping_item ) {
					$item_id = wc_add_order_item(
						$order_id,
						array(
							'order_item_name' => $original_order_shipping_item['name'],
							'order_item_type' => 'shipping',
						)
					);
					if ( $item_id ) {
						WC_Rebill_Core::debug( 'Added original_order_shipping_item - ' . $item_id . ': ' . WC_Rebill_Core::pL( $original_order_shipping_item, true ) );
						wc_add_order_item_meta( $item_id, 'method_id', $original_order_shipping_item['method_id'] );
						wc_add_order_item_meta( $item_id, 'cost', wc_format_decimal( $original_order_shipping_item->get_total() ) );
						if ( $original_order_shipping_item->get_total_tax() > 0 ) {
							wc_add_order_item_meta( $item_id, 'taxes', $original_order_shipping_item['taxes'] );
						}
					}
				}
				$original_order_coupons = $order->get_items( 'coupon' );
				foreach ( $original_order_coupons as $original_order_coupon ) {
					$item_id = wc_add_order_item(
						$order_id,
						array(
							'order_item_name' => $original_order_coupon['name'],
							'order_item_type' => 'coupon',
						)
					);
					if ( $item_id ) {
						wc_add_order_item_meta( $item_id, 'discount_amount', $original_order_coupon['discount_amount'] );
					}
				}
				update_post_meta( $order_id, '_payment_method', get_post_meta( $original_order_id, '_payment_method', true ) );
				update_post_meta( $order_id, '_payment_method_title', get_post_meta( $original_order_id, '_payment_method_title', true ) );
				update_post_meta( $order_id, 'rebill_subscription', $old_subscription );
				update_post_meta( $order_id, 'rebill_ref', $ref );
				if ( $is_first_order ) {
					$to_clone[ $ref ] = $order_id;
					update_post_meta( $first_order_id, 'rebill_to_clone', $to_clone );
				}
				if ( isset( $to_clone[ $ref ] ) && (int) $to_clone[ $ref ] === (int) $original_order_id ) {
					update_post_meta( $order_id, 'rebill_renew_from', $original_order_id );
					$new_order->add_order_note( __( 'Rebill: Parent order', 'wc-rebill-subscription' ) . ' #' . $original_order_id );
				} elseif ( $is_from_clone ) {
					update_post_meta( $order_id, 'rebill_renew_from', $order->get_id() );
					$new_order->add_order_note( __( 'Rebill: Parent order', 'wc-rebill-subscription' ) . ' #' . $order->get_id() );
				}
				$new_order->calculate_totals( true );
				$new_order = new WC_Order( $order_id );
				if ( $is_first_order ) {
					do_action( 'rebill_new_order_subscription', $new_order, $order );
				} else {
					do_action( 'rebill_renew_order_subscription', $new_order, $order );
				}
				return $new_order;
			}
			WC_Rebill_Core::debug( 'fail duplicate_order - ' . $order->get_id() );
			return false;
		}
		/**
		 * Check rebill payment and subscription data
		 *
		 * @param WC_Order $order Order.
		 * @param array    $payment Rebill Payment DATA.
		 * @param array    $subscription Rebill Subscription DATA.
		 * @param bool     $is_multiple Check if it's multiple subscription + one-time payment.
		 *
		 * @return bool
		 */
		public function check_rebill_response( $order, $payment, $subscription = false, $is_multiple = true ) {
			global $wpdb;
			WC_Rebill_Core::debug( 'check_rebill_response - ' . $order->get_id() . ' - ' . WC_Rebill_Core::pl( $payment, true ) . ' - ' . WC_Rebill_Core::pl( $subscription, true ) );
			$ref                    = '';
			$rebill_subscription_id = array();
			$first_order_id         = (int) $order->get_id();
			if ( $subscription ) {
				$customer = get_current_user_id();
				if ( $subscription['card_id'] && $customer ) {
					$cards = get_user_meta( $customer, 'rebill_cards', true );
					if ( ! is_array( $cards ) ) {
						$cards = array();
					}
					if ( ! in_array( (int) $subscription['card_id'], $cards, true ) ) {
						$cards[] = (int) $subscription['card_id'];
					}
					update_user_meta( $customer, 'rebill_cards', $cards );
				}
				$order->update_meta_data( 'rebill_subscription_id', $subscription['id'] );
				$order->update_meta_data( 'rebill_subscription_first_order', $first_order_id );
				$rebill_subscription_id = $order->get_meta( 'rebill_all_subscription_id' );
				if ( ! is_array( $rebill_subscription_id ) ) {
					$rebill_subscription_id = array();
				}
				$reference      = explode( '-', $subscription['external_reference'] );
				$first_order_id = (int) $reference[0];
				$ref            = $reference[1];
				if ( $payment && ! in_array( $payment['status'], array( 'approved', 'authorized', 'in_process', 'pending' ), true ) ) {
					if ( $rebill_subscription_id && isset( $rebill_subscription_id[ $ref ] ) && (int) $rebill_subscription_id[ $ref ] !== (int) $subscription['id'] ) {
						WC_Rebill_Core::debug( 'check_rebill_response with problems - The payment was not accepted and does not belong to the subscription. Expected SubID: ' . $rebill_subscription_id[ $ref ] );
						return false;
					}
				}
				$this->update_meta_order_first_order( $order, $subscription, $payment, $ref );
				$rebill_subscription_id[ $ref ] = $subscription['id'];
				$order->update_meta_data( 'rebill_all_subscription_id', $rebill_subscription_id );
			}
			if ( $subscription ) {
				$current_payment = (int) $order->get_meta( 'rebill_transaction_id_' . $ref );
			} else {
				$current_payment = (int) $order->get_meta( 'rebill_transaction_id' );
			}
			if ( $subscription && $payment && $current_payment && (int) $current_payment !== (int) $payment['id'] ) {
				$order_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND  meta_value = %d LIMIT 1", 'rebill_transaction_id_' . $ref, (int) $payment['id'] ) );
				if ( ! $order_id ) {
					$order_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND  meta_value = %d LIMIT 1", 'rebill_wait_payment_' . $ref, (int) $subscription['id'] ) );
				}
				if ( ! $order_id ) {
					$order_id = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_transaction_id' AND  meta_value = '" . (int) $payment['id'] . "' LIMIT 1" );
				}
				if ( ! $order_id ) {
					$order_id = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = '" . (int) $subscription['id'] . "' LIMIT 1" );
				}
				if ( $order_id ) {
					$order = new WC_Order( $order_id );
				} else {
					$first_order     = $order;
					$id_order_parent = $order->get_meta( 'rebill_to_clone' );
					if ( ! is_array( $id_order_parent ) ) {
						$id_order_parent = array();
					}
					if ( isset( $id_order_parent[ $ref ] ) && $id_order_parent[ $ref ] !== (int) $order->get_id() ) {
						$order = new WC_Order( $id_order_parent[ $ref ] );
						$order = $this->duplicate_order( $order, $ref );
					} else {
						$order = $this->duplicate_order( $order, $ref, true );
					}
					if ( ! $order ) {
						return false;
					}
					/*
					// TODO: No se recomienda este tipo de update por el momento, PD: CASO BIURY
					if ( abs( $order->get_total() - $subscription['transaction_amount'] ) > 0.001 ) {
						$u                       = array( 'id' => $subscription['id'] );
						$u['transaction_amount'] = $order->get_total();
						$this->api->callApiPut( '/subscriptions', $u );
					}
					*/
					$order->update_meta_data( 'is_rebill_renew', true );
				}
			}
			$order->update_meta_data( 'is_rebill', true );
			if ( $subscription ) {
				$order->update_meta_data( 'rebill_subscription_id', $subscription['id'] );
				$order->update_meta_data( 'rebill_subscription_first_order', $first_order_id );
				if ( ! $payment ) {
					$order->update_meta_data( 'rebill_wait_payment_' . $ref, $subscription['id'] );
					if ( $first_order_id !== (int) $order->get_id() ) {
						$order->update_meta_data( 'rebill_wait_payment', $subscription['id'] );
					}
					$order->update_status( 'rebill-wait', __( 'Rebill: Waiting for payment date.', 'wc-rebill-subscription' ) );
					return true;
				}
				$current_payment = $order->get_meta( 'rebill_wait_payment_' . $ref );
				if ( ! $current_payment ) {
					$order->update_meta_data( 'rebill_transaction_id_' . $ref, $payment['id'] );
					$order->update_meta_data( 'rebill_wait_payment_' . $ref, false );
				}
			}
			if ( ! $subscription || $first_order_id !== (int) $order->get_id() || ! $is_multiple ) {
				$order->update_meta_data( 'rebill_transaction_id', $payment['id'] );
			}
			$all_transaction = $order->get_meta( 'rebill_transactions' );
			if ( ! is_array( $all_transaction ) ) {
				$all_transaction = array();
			}
			$customer = get_current_user_id();
			if ( $payment['card_id'] && $customer ) {
				$cards = get_user_meta( $customer, 'rebill_cards', true );
				if ( ! is_array( $cards ) ) {
					$cards = array();
				}
				if ( ! in_array( (int) $payment['card_id'], $cards, true ) ) {
					$cards[] = (int) $payment['card_id'];
				}
				update_user_meta( $customer, 'rebill_cards', $cards );
			}
			$all_transaction[ $payment['id'] ] = array(
				'amount'             => $payment['transaction_amount'],
				'status'             => $payment['status'],
				'gateway_payment_id' => $payment['gateway_payment_id'],
				'id'                 => $payment['id'],
			);
			WC_Rebill_Core::debug( 'check_rebill_response all_transaction - ' . WC_Rebill_Core::pL( $all_transaction, true ) );
			$transaction_amount = 0;
			$with_errors        = 0;
			$any_pending        = false;
			$status             = $payment['status'];
			foreach ( $all_transaction as $transaction ) {
				WC_Rebill_Core::debug( 'check_rebill_response transaction - ' . WC_Rebill_Core::pL( $transaction, true ) );
				if ( in_array( $transaction['status'], array( 'approved', 'authorized' ), true ) ) {
					$transaction_amount += $transaction['amount'];
				} elseif ( in_array( $transaction['status'], array( 'rejected', 'refunded', 'cancelled', 'in_mediation' ), true ) ) {
					$with_errors++;
					$status = $transaction['status'];
				} else {
					$transaction_amount += $transaction['amount'];
					$any_pending         = true;
				}
			}
			$order->update_meta_data( 'rebill_transactions', $all_transaction );
			if ( in_array( $status, array( 'approved', 'authorized', 'in_process', 'pending' ), true ) ) {
				WC_Rebill_Core::debug( 'check_rebill_response is ok - ' . $payment['status'] );
				$order_total = $order->get_total();
				if ( abs( $order_total - $transaction_amount ) < 0.01 ) {
					$order->update_meta_data( 'rebill_transaction_id', $payment['id'] );
					if ( $any_pending ) {
						$order->update_status( 'on-hold', __( 'Rebill: The payment is in review.', 'wc-rebill-subscription' ) );
						WC_Rebill_Core::debug( 'Payment pending' );
					} else {
						$order->add_order_note( __( 'Rebill: Payment approved.', 'wc-rebill-subscription' ) );
						WC_Rebill_Core::debug( 'Payment approved' );
						$order->payment_complete();
						if ( $this->mp_completed ) {
							$order->update_status( 'completed', __( 'Rebill: Order complete.', 'wc-rebill-subscription' ) );
						}
					}
				} elseif ( 0 === $with_errors ) {
					$order->add_order_note( __( 'Rebill: Incomplete payment, waiting for remaining payment.', 'wc-rebill-subscription' ) );
					// $order->update_status( 'on-hold', __( 'Rebill: Incomplete payment, waiting for remaining payment', 'wc-rebill-subscription' ) );
				}
			} else {
				WC_Rebill_Core::debug( 'check_rebill_response with problems - ' . $payment['status'] );
				$cancel_sub = false;
				switch ( $status ) {
					case 'rejected':
						$order->add_order_note( __( 'Rebill: Payment rejected, user must try again.', 'wc-rebill-subscription' ) );
						$order->update_status( 'failed', __( 'Rebill: Payment rejected.', 'wc-rebill-subscription' ) );
						$cancel_sub = true;
						break;
					case 'refunded':
						$order->update_status( 'refunded', __( 'Rebill: The payment was refunded to the customer.', 'wc-rebill-subscription' ) );
						$cancel_sub = true;
						do_action( 'woocommerce_order_fully_refunded_notification', $order_id );
						break;
					case 'cancelled':
						$order->update_status( 'cancelled', __( 'Rebill: Payment cancelled.', 'wc-rebill-subscription' ) );
						$cancel_sub = true;
						break;
					case 'in_mediation':
						$order->add_order_note( __( 'Rebill: A dispute has started over the payment.', 'wc-rebill-subscription' ) );
						$order->update_status( 'on-hold', __( 'Rebill: The payment is in dispute.', 'wc-rebill-subscription' ) );
						break;
				}
				if ( $cancel_sub && $subscription && $first_order_id === (int) $order->get_id() ) {
					$api    = new Rebill_API();
					$u      = array(
						'id'     => $subscription['id'],
						'status' => 'cancelled',
					);
					$result = $api->callApiPut( '/subscriptions', $u );
					if ( $result && $result['success'] ) {
						update_post_meta( $first_order_id, 'rebill_sub_status', 'cancelled' );
						$id_order_parent = $order->get_meta( 'rebill_to_clone' );
						if ( isset( $id_order_parent[ $ref ] ) ) {
							$p_order = wc_get_order( $id_order_parent[ $ref ] );
							if ( (int) $id_order_parent[ $ref ] === (int) $p_order->get_id() ) {
								update_post_meta( $p_order->get_id(), 'rebill_sub_status', 'cancelled' );
								$p_order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by customer.', 'wc-rebill-subscription' ) );
							}
						}
					}
				}
			}
			return true;
		}

		/**
		 * Display Thankyou text
		 *
		 * @param WC_Order $order Order.
		 * @return void
		 */
		public function thankyou_text( $order ) {

		}

		/**
		 * Display CSS Files
		 *
		 * @return void
		 */
		public function hook_css() {

		}

		/**
		 * Get if this gateway is available
		 *
		 * @return bool
		 */
		public function is_available() {
			// Test if is valid for use.
			$available = ( 'yes' === $this->settings['enabled'] );
			return $available;
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = include 'data-settings-rebill-gateway.php';
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			if ( is_null( self::$is_load ) ) {
				self::$is_load = new self();
			}
			return self::$is_load;
		}

		/**
		 * Create database tables
		 *
		 * @return void
		 */
		public static function check_database() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'rebill_cache';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "rebill_cache'" ) !== $table_name ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql             = "CREATE TABLE `$table_name`  (
					`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`cache_id` varchar(100) NOT NULL,
					`data` LONGTEXT NOT NULL,
					`ttl` INT(11) NOT NULL,
					UNIQUE(cache_id),
					INDEX(ttl)
				) $charset_collate;";
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			}
			$table_name = $wpdb->prefix . 'rebill_cronjob';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "rebill_cronjob'" ) !== $table_name ) {
				$charset_collate = $wpdb->get_charset_collate();
				$sql             = "CREATE TABLE `$table_name`  (
					`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`cmd` varchar(64) NOT NULL,
					`arg` varchar(128) NOT NULL,
					`lock_key` varchar(32) DEFAULT NULL,
					`lock_date` BIGINT DEFAULT NULL,
					`next_retry` BIGINT NOT NULL,
					`run_at` BIGINT DEFAULT NULL,
					INDEX(cmd),
					INDEX(lock_key),
					INDEX(lock_date),
					INDEX(next_retry),
					INDEX(run_at)
				) $charset_collate;";
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			}
		}
		public function generate_hidden_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'value' => '',
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
				<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $data['value'] ); ?>"  />
			<?php
			return ob_get_clean();
		}
	}
endif;
