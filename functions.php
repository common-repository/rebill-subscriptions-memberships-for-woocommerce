<?php
/**
 * Rebill
 *
 * Functions
 *
 * @package    Rebill
 * @link       https://rebill.to
 * @since      1.0.0
 */

add_filter(
	'plugin_action_links_' . plugin_basename( WC_Rebill_Subscription::$plugin_file ),
	function ( $links ) {
		return array_merge(
			$links,
			array(
				'<a style="font-weight: bold;color: red" href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rebill-gateway' ) . '">' . __( 'Setting', 'wc-rebill-subscription' ) . '</a>',
			)
		);
	}
);

add_action(
	'template_redirect',
	function () {
		global $wpdb;
		if ( isset( $_POST['rebill_order_parent_nonce'] ) && isset( $_POST['order_parent'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rebill_order_parent_nonce'] ) ), 'rebill_front_order_update' ) ) {
			$rebill_order_parent = wc_get_order( (int) $_POST['order_parent'] );
			$customer            = get_current_user_id();
			if ( $rebill_order_parent && (int) $rebill_order_parent->get_customer_id() === (int) $customer ) {
				$api             = new Rebill_API();
				$subscription    = $rebill_order_parent->get_meta( 'rebill_subscription' );
				$subscription_id = $rebill_order_parent->get_meta( 'rebill_subscription_id' );
				$r_subscription  = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
				if ( $r_subscription && isset( $r_subscription['response'] ) && isset( $r_subscription['response'][0]['id'] ) ) {
					$r_subscription = $r_subscription['response'][0];
				} else {
					die(
						wp_json_encode(
							array(
								'error'      => __( 'Error connecting to rebill servers', 'wc-rebill-subscription' ),
								'error_code' => 1,
							)
						)
					);
				}
				$ref                  = $rebill_order_parent->get_meta( 'rebill_ref' );
				$status_rebill        = $rebill_order_parent->get_meta( 'rebill_sub_status' );
				$is_editable          = false;
				$product_edit         = false;
				$frequency_edit       = false;
				$address_edit         = false;
				$frequency_max_edit   = false;
				$synchronization_edit = false;
				WC_Rebill_Core::log( 'set_subscription find ' . WC_Rebill_Core::pL( $ref, true ) . ' in ' . WC_Rebill_Core::pL( $subscription, true ) );
				if ( $subscription ) {
					$rebill_subscription = $subscription[ $ref ];
					$frequency           = $rebill_subscription['frequency'];
					$frequency_type      = $rebill_subscription['frequency_type'];
					$is_synchronization  = ( 'month' === $frequency_type );
					foreach ( $rebill_order_parent->get_items() as $item_id => $item ) {
						$pid     = $item['product_id'];
						$product = wc_get_product( $pid );
						if ( ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) && ! is_a( $product, 'WC_Product' ) ) {
							continue;
						}
						$variation_id = 0;
						$variation    = false;
						if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
							$parent = $product->get_parent_id();
							if ( $parent ) {
								$variation    = $product;
								$variation_id = $variation->get_id();
								WC_Rebill_Core::log( 'set_subscription parent ' . WC_Rebill_Core::pL( $parent, true ) );
								$product = new WC_Product( $parent );
							}
						}
						$product_edit         = ( 'yes' === $product->get_meta( 'rebill_product_edit', true ) );
						$frequency_edit       = ( 'yes' === $product->get_meta( 'rebill_frequency_edit', true ) );
						$address_edit         = ( 'yes' === $product->get_meta( 'rebill_address_edit', true ) );
						$frequency_max_edit   = ( 'yes' === $product->get_meta( 'rebill_frequency_max_edit', true ) );
						$synchronization_edit = $is_synchronization && ( 'yes' === $product->get_meta( 'rebill_synchronization_edit', true ) );
						$is_editable          = $is_editable || $product_edit || $frequency_edit || $address_edit || $frequency_max_edit || $synchronization_edit;
					}
					WC_Rebill_Core::log( 'set_subscription is_editable ' . WC_Rebill_Core::pL( $is_editable, true ) );
					if ( $is_editable ) {
						if ( isset( $_POST['rebill_address_update'] ) ) {
							if ( ! $address_edit ) {
								die(
									wp_json_encode(
										array(
											'error'      => __( 'Address not editable', 'wc-rebill-subscription' ),
											'error_code' => 10,
										)
									)
								);
							}
							$order_id = (int) $_POST['order_parent'];
							$customer = array( 'id' => $r_subscription['customer_id'] );
							if ( isset( $_POST['shipping_address_1'] ) ) {
								update_post_meta( $order_id, '_shipping_address_1', esc_attr( $_POST['shipping_address_1'] ) );
								$customer['address_street'] = esc_attr( $_POST['shipping_address_1'] );
							}
							if ( isset( $_POST['shipping_address_2'] ) ) {
								update_post_meta( $order_id, '_shipping_address_2', esc_attr( $_POST['shipping_address_2'] ) );
								$customer['address_street'] = trim( $customer['address_street'] . ' ' . esc_attr( $_POST['shipping_address_2'] ) );
							}
							if ( isset( $_POST['shipping_city'] ) ) {
								update_post_meta( $order_id, '_shipping_city', esc_attr( $_POST['shipping_city'] ) );
								$customer['address_city'] = esc_attr( $_POST['shipping_city'] );
							}
							if ( isset( $_POST['shipping_state'] ) ) {
								update_post_meta( $order_id, '_shipping_state', esc_attr( $_POST['shipping_state'] ) );
								$states = WC()->countries->get_states( get_post_meta( $order_id, '_shipping_country', true ) );
								$state  = esc_attr( $_POST['shipping_state'] );
								if ( $states && isset( $states[ $state ] ) ) {
									$state = $states[ $state ];
								}
								$customer['address_province'] = $state;
							}
							if ( isset( $_POST['shipping_postcode'] ) ) {
								update_post_meta( $order_id, '_shipping_postcode', esc_attr( $_POST['shipping_postcode'] ) );
								$customer['address_zipcode'] = esc_attr( $_POST['shipping_postcode'] );
							}
							$api->callApiPut( '/customers', $customer );
							die( wp_json_encode( array( 'error' => false ) ) );
						}
						if ( $product_edit ) {
							$total = 0;
							foreach ( $rebill_order_parent->get_items() as $item_id => $item ) {
								if ( isset( $_POST['rebill_quantity'] ) && isset( $_POST['rebill_quantity'][ $item_id ] ) ) {
									$qty = (int) $_POST['rebill_quantity'][ $item_id ];
									if ( $qty >= 0 ) {
										$total += $qty;
									}
								}
							}
							if ( $total < 1 ) {
								die( wp_json_encode( array( 'error' => __( 'You must have at least one product in the subscription.', 'wc-rebill-subscription' ) ) ) );
							}
							$old_items = $rebill_order_parent->get_items();
							foreach ( $old_items as $item_id => $item ) {
								if ( isset( $_POST['rebill_quantity'] ) && isset( $_POST['rebill_quantity'][ $item_id ] ) ) {
									$sqty = (int) $_POST['rebill_quantity'][ $item_id ];
									$qty  = (int) $item['qty'];
									if ( $sqty > 0 && $sqty !== $qty ) {
										$pid = $item['product_id'];
										if ( ! isset( $subscription[ $ref ]['products'][ $pid ] ) ) {
											WC_Rebill_Core::debug( 'not found product - ' . $pid . ' -> ' . WC_Rebill_Core::pL( $subscription[ $ref ], true ) );
											continue;
										}
										$vid = $item['variation_id'];
										if ( $vid && ! isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
											WC_Rebill_Core::debug( 'not found product variation - ' . $pid . ':' . $vid . ' -> ' . WC_Rebill_Core::pL( $subscription[ $ref ], true ) );
											continue;
										}
										if ( isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
											$subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ]['quantity'] = $sqty;
										} else {
											$subscription[ $ref ]['products'][ $pid ]['quantity'] = $sqty;
										}
										$name       = $item['name'];
										$line_total = $item['line_total'] / $qty;
										$line_tax   = $item['line_tax'] / $qty;
										$new_data   = array(
											'name'      => $name,
											'quantity'  => $sqty,
											'tax_class' => $item['tax_class'],
											'total'     => $line_total * $sqty,
											'subtotal'  => $line_total * $sqty,
										);
										$taxes      = $item->get_taxes();
										foreach ( $taxes as &$line ) {
											foreach ( $line as &$tax ) {
												$tax = ( $tax / $qty ) * $sqty;
											}
										}
										$new_data['taxes'] = $taxes;
										$item->set_props( $new_data );
										WC_Rebill_Core::log( 'set_props update order: ' . WC_Rebill_Core::pL( $taxes, true ) . WC_Rebill_Core::pL( $new_data, true ) );
										do_action( 'woocommerce_before_save_order_item', $item );
										$item->save();
									} elseif ( 0 === $sqty ) {
										$pid = $item['product_id'];
										if ( ! isset( $subscription[ $ref ]['products'][ $pid ] ) ) {
											WC_Rebill_Core::debug( 'delete not found product - ' . $pid . ' -> ' . WC_Rebill_Core::pL( $subscription[ $ref ], true ) );
											continue;
										}
										$vid = $item['variation_id'];
										if ( $vid && ! isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
											WC_Rebill_Core::debug( 'delete not found product variation - ' . $pid . ':' . $vid . ' -> ' . WC_Rebill_Core::pL( $subscription[ $ref ], true ) );
											continue;
										}
										if ( isset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] ) ) {
											unset( $subscription[ $ref ]['products'][ $pid ]['variation'][ $vid ] );
										} else {
											unset( $subscription[ $ref ]['products'][ $pid ]['quantity'] );
										}
										wc_delete_order_item( $item_id );
									}
								}
							}
							$rebill_order_parent->update_taxes();
							$rebill_order_parent->calculate_totals( false );
							update_post_meta( $rebill_order_parent->get_id(), 'rebill_subscription', $subscription );
							if ( abs( $rebill_order_parent->get_total() - $r_subscription['transaction_amount'] ) > 0.001 ) {
								$u                       = array( 'id' => $r_subscription['id'] );
								$u['transaction_amount'] = $rebill_order_parent->get_total();
								$result                  = $api->callApiPut( '/subscriptions', $u );
								if ( ! $result || ! $result['success'] ) {
									die(
										wp_json_encode(
											array(
												'error' => __( 'Error connecting to rebill servers', 'wc-rebill-subscription' ),
												'error_code' => 2,
											)
										)
									);
								}
							}
						}
						$rebill_frequency       = isset( $_POST['rebill_frequency'] ) ? (int) $_POST['rebill_frequency'] : (int) $subscription[ $ref ]['frequency'];
						$rebill_frequency_max   = isset( $_POST['rebill_frequency_max'] ) ? (int) $_POST['rebill_frequency_max'] : (int) $subscription[ $ref ]['frequency_max'];
						$rebill_synchronization = isset( $_POST['rebill_synchronization'] ) ? (int) $_POST['rebill_synchronization'] : (int) $subscription[ $ref ]['synchronization'];
						$u                      = array();
						$sync_edited            = false;
						if ( $synchronization_edit && 'month' === $subscription[ $ref ]['frequency_type'] && $rebill_synchronization !== (int) $subscription[ $ref ]['synchronization'] ) {
							if ( $rebill_synchronization > 0 ) {
								$u['debit_date'] = min( $rebill_synchronization, 27 );
							} else {
								$u['debit_date'] = null;
							}
							$sync_edited = true;
						}
						if ( $frequency_edit && $rebill_frequency !== (int) $subscription[ $ref ]['frequency'] ) {
							$u['frequency'] = $rebill_frequency;
						}
						if ( $frequency_max_edit && $rebill_frequency_max !== (int) $subscription[ $ref ]['frequency_max'] ) {
							$u['repetitions'] = $rebill_frequency_max > 0 ? $rebill_frequency_max : null;
						}
						if ( count( $u ) > 0 ) {
							$u['id'] = $r_subscription['id'];
							$result  = $api->callApiPut( '/subscriptions', $u );
							if ( $result && $result['success'] ) {
								if ( $sync_edited ) {
									$subscription[ $ref ]['synchronization'] = $u['debit_date'];
								}
								if ( isset( $u['frequency'] ) ) {
									$subscription[ $ref ]['frequency'] = $u['frequency'];
								}
								if ( isset( $u['repetitions'] ) ) {
									$subscription[ $ref ]['frequency_max'] = $u['repetitions'];
								}
								WC_Rebill_Core::log( 'new setting update: ' . WC_Rebill_Core::pL( $u, true ) . WC_Rebill_Core::pL( $subscription, true ) );
								update_post_meta( $rebill_order_parent->get_id(), 'rebill_subscription', $subscription );
							} else {
								die(
									wp_json_encode(
										array(
											'error'      => __( 'Error connecting to rebill servers', 'wc-rebill-subscription' ),
											'error_code' => 3,
										)
									)
								);
							}
						}
						die( wp_json_encode( array( 'error' => false ) ) );
					}
				}
			}
			die( wp_json_encode( array( 'error' => __( 'Invalid Order', 'wc-rebill-subscription' ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['rebill_client_response_error'] ) ) {
			$data = json_decode( file_get_contents( 'php://input' ), true );
			// phpcs:ignore WordPress.Security.NonceVerification
			$result = WC_Rebill_Gateway::get_instance()->check_client_response_error( (int) $_GET['rebill_client_response_error'], $data );
			die( wp_json_encode( array( 'status' => $result ) ) );
		}
		if ( isset( $_GET['rebill_client_response'] ) ) {
			$data = json_decode( file_get_contents( 'php://input' ), true );
			// phpcs:ignore WordPress.Security.NonceVerification
			$result = WC_Rebill_Gateway::get_instance()->check_client_response( (int) $_GET['rebill_client_response'], $data );
			die( wp_json_encode( array( 'status' => $result ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['ipn-rebill'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$action = wp_unslash( sanitize_key( $_GET['ipn-rebill'] ) );
			$data   = json_decode( file_get_contents( 'php://input' ), true );

			WC_Rebill_Gateway::get_instance()->check_ipn( $action, $data );
			die( 'Rebill -ALL OK' );
		}
	}
);
add_action(
	'woocommerce_admin_process_product_object',
	function ( $product ) {
		if ( isset( $_POST['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rebill_nonce'] ) ), 'rebill_update_product_nonce' ) ) {
			foreach ( $_POST as $k => $v ) {
				if ( false !== strstr( $k, 'rebill' ) && 'rebill_nonce' !== $k ) {
					if ( 'rebill_frequency' === $k ) {
						$v = array_map(
							function( $v ) {
								return (int) trim( $v );
							},
							explode( ',', sanitize_text_field( wp_unslash( $v ) ) )
						);
						sort( $v );
						$product->update_meta_data( $k, (array) $v );
					} else {
						$product->update_meta_data( $k, sanitize_text_field( wp_unslash( $v ) ) );
					}
				}
			}
			if ( ! isset( $_POST['rebill'] ) ) {
				$product->update_meta_data( 'rebill', 0 );
			}
			if ( ! isset( $_POST['rebill_only'] ) ) {
				$product->update_meta_data( 'rebill_only', 0 );
			}
			if ( ! isset( $_POST['rebill_free_trial'] ) ) {
				$product->update_meta_data( 'rebill_free_trial', 0 );
			}
		}
	}
);
add_filter(
	'woocommerce_product_data_tabs',
	function( $default_tabs ) {
		$default_tabs['rebill_product_tab'] = array(
			'label'    => __( 'Rebill Subscription', 'wc-rebill-subscription' ),
			'target'   => 'rebill_product_tab_data',
			'priority' => 60,
			'class'    => array(),
		);
		return $default_tabs;
	},
	10,
	1
);
add_action(
	'woocommerce_product_data_panels',
	function () {
		global $woocommerce, $post, $product_object;
		echo '<div id="rebill_product_tab_data" class="panel woocommerce_options_panel">';
		echo '<div class="options_group">';
		echo '<input type="hidden" name="rebill_nonce" value="' . esc_html( wp_create_nonce( 'rebill_update_product_nonce' ) ) . '" />';
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill',
				'label'       => __( 'Subscription enable', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_only',
				'label'       => __( 'Only available for subscription', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_only', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_autoupdate',
				'label'       => __( 'Update active subscriptions automatically if you change the cost of the product', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_autoupdate', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_product_edit',
				'label'       => __( 'Will the client be able to modify the quantity of products in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_frequency_edit', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_address_edit',
				'label'       => __( 'Will the client be able to modify the shipping address in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_address_edit', true ),
			)
		);
		echo '</div>';
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_price',
				'label'       => __( 'Subscription price', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Empty to use the base price of product', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_price', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_signup_price',
				'label'       => __( 'Sign-up price', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Empty to ignore', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_signup_price', true ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => 'rebill_frequency_type',
				'label'   => __( 'Frequency Type', 'wc-rebill-subscription' ),
				'options' => array(
					'month' => __( 'Month', 'wc-rebill-subscription' ),
					'day'   => __( 'Day', 'wc-rebill-subscription' ),
					'year'  => __( 'Year', 'wc-rebill-subscription' ),
				),
				'value'   => $product_object->get_meta( 'rebill_frequency_type', true ),
			)
		);
		$d = $product_object->get_meta( 'rebill_frequency', true );
		if ( ! is_array( $d ) ) {
			$d = array( $d );
		}
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_frequency',
				'label'       => __( 'Frequency (comma separated if customer can select from multiple options)', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Frequency of collection, for example: every 1 month or every 2 months.', 'wc-rebill-subscription' ),
				'value'       => implode( ',', $d ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_frequency_edit',
				'label'       => __( 'The "Frequency" field can be edited by the customer in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_frequency_edit', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_address_edit',
				'label'       => __( 'The "Shipping Address" field can be edited by the customer in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_address_edit', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_frequency_max',
				'label'       => __( 'Maximum number of recurring payment', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Leave empty if it will never expire', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_frequency_max', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_frequency_max_edit',
				'label'       => __( 'The "Maximum number of recurring payment" field can be edited by the customer in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_frequency_max_edit', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_thankpage',
				'label'       => __( 'URL of the Thank You Page for Subscribing', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Leave empty to use the default woocommerce page', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_thankpage', true ),
			)
		);
		echo '</div>';
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_free_trial',
				'label'       => __( 'Free Trial', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_free_trial', true ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => 'rebill_frequency_type_trial',
				'label'   => __( 'Free Trial Frequency Type', 'wc-rebill-subscription' ),
				'options' => array(
					'month' => __( 'Month', 'wc-rebill-subscription' ),
					'day'   => __( 'Day', 'wc-rebill-subscription' ),
				),
				'value'   => $product_object->get_meta( 'rebill_frequency_type_trial', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_frequency_trial',
				'label'       => __( 'Free Trial Frequency', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'description' => __( 'Trial free for N Day/Month, for example: 30.', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_frequency_trial', true ),
			)
		);
		echo '</div>';
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => 'rebill_synchronization',
				'label'       => __( 'Force collection date to a specific day number', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'default'     => '',
				'description' => __( 'Empty to ignore, Input 1 to 27, only applies to frequency monthly', 'wc-rebill-subscription' ),
				'value'       => $product_object->get_meta( 'rebill_synchronization', true ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => 'rebill_synchronization_edit',
				'label'       => __( 'The "Collection date" field can be edited by the customer in an active subscription?', 'wc-rebill-subscription' ),
				'placeholder' => '',
				'value'       => $product_object->get_meta( 'rebill_synchronization_edit', true ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => 'rebill_synchronization_type',
				'label'   => __( 'First Collection type', 'wc-rebill-subscription' ),
				'options' => array(
					// 'proration'   => __( 'Prorate the first payment', 'wc-rebill-subscription' ),
					// 'full_sharge' => __( 'Charge the full subscription cost at the moment', 'wc-rebill-subscription' ),
					'first_free' => __( 'Do not charge at the moment, everything is charged on the established day', 'wc-rebill-subscription' ),
				),
				'value'   => $product_object->get_meta( 'rebill_synchronization_type', true ),
			)
		);
		echo '</div>';
		echo '</div>';
	}
);

if ( ! class_exists( 'WC_Rebill_Cart' ) ) {
	include_once 'includes/class-wc-rebill-product.php';
	include_once 'includes/class-wc-rebill-cart.php';
}

add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		$base_location = wc_get_base_location();
		$country       = strtolower( $base_location['country'] );
		$options       = array();
		switch ( $country ) {
			case 'ar':
				$options['DNI']  = __( 'DNI', 'wc-rebill-subscription' );
				$options['CI']   = __( 'Cédula', 'wc-rebill-subscription' );
				$options['LC']   = __( 'L.C.', 'wc-rebill-subscription' );
				$options['LE']   = __( 'L.E.', 'wc-rebill-subscription' );
				$options['Otro'] = __( 'Other', 'wc-rebill-subscription' );
				break;
			case 'br':
				$options['CPF']  = __( 'Cadastro de Pessoas Físicas', 'wc-rebill-subscription' );
				$options['CNPJ'] = __( 'Cadastro Nacional da Pessoa Jurídica', 'wc-rebill-subscription' );
				break;
			case 'cl':
				$options['RUT']  = __( 'RUT', 'wc-rebill-subscription' );
				$options['Otro'] = __( 'Other', 'wc-rebill-subscription' );
				break;
			case 'co':
				$options['CC']   = __( 'Cédula de Ciudadanía', 'wc-rebill-subscription' );
				$options['CE']   = __( 'Cédula de Extranjería', 'wc-rebill-subscription' );
				$options['NIT']  = __( 'Número de Identificación Tributaria', 'wc-rebill-subscription' );
				$options['Otro'] = __( 'Other', 'wc-rebill-subscription' );
				break;
			case 'pe':
				$options['DNI'] = __( 'DNI', 'wc-rebill-subscription' );
				$options['CE']  = __( 'Carné de Extranjería', 'wc-rebill-subscription' );
				$options['RUC'] = __( 'Registro Único de Contribuyentes', 'wc-rebill-subscription' );
				break;
			case 'uy':
				$options['CI']   = __( 'Cédula de Identidad', 'wc-rebill-subscription' );
				$options['Otro'] = __( 'Other', 'wc-rebill-subscription' );
				break;
			default:
				return $fields;
		}
		if ( ! count( $options ) ) {
			$options['Otro'] = __( 'Other', 'wc-rebill-subscription' );
		}
		$options                                      = apply_filters( 'rebill_billing_vat_type', $options );
		$priority                                     = $fields['billing']['billing_last_name']['priority'];
		$fields['billing']['billing_rebill_vat']      = array(
			'label'       => __( 'Identification Number', 'wc-rebill-subscription' ),
			'type'        => 'text',
			'priority'    => $priority + 1,
			'required'    => true,
			'class'       => apply_filters( 'rebill_form_row_first_field', array( 'form-row-wide', 'form-group', 'col-sm-12', 'col-md-12' ) ),
			'input_class' => apply_filters( 'rebill_form_row_first_input', array( 'form-control' ) ),
			'clear'       => true,
		);
		$fields['billing']['billing_rebill_vat_type'] = array(
			'label'       => __( 'Identification Type', 'wc-rebill-subscription' ),
			'type'        => 'select',
			'required'    => true,
			'priority'    => $priority + 2,
			'options'     => $options,
			'class'       => apply_filters( 'rebill_form_row_first_field', array( 'form-row-wide', 'form-group', 'col-sm-12', 'col-md-12' ) ),
			'input_class' => apply_filters( 'rebill_form_row_first_input', array( 'form-control' ) ),
			'clear'       => true,
		);
		return $fields;
	}
);

// Add a custom order status to list of WC Order statuses.
add_filter(
	'wc_order_statuses',
	function ( $order_statuses ) {
		unset( $order_statuses['wc-rebill-wait'] );
		$new_order_statuses = array();
		// add new order status before processing.
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-rebill-wait']      = __( 'Waiting for payment date', 'wc-rebill-subscription' );
				$new_order_statuses['wc-rebill-toclone']   = __( 'Subscription', 'wc-rebill-subscription' );
				$new_order_statuses['wc-rebill-subcancel'] = __( 'Subscription Cancelled', 'wc-rebill-subscription' );
			}
		}
		return $new_order_statuses;
	}
);
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['rebill_5min'] ) ) {
			$schedules['rebill_5min'] = array(
				'interval' => 5 * 60,
				'display'  => __( 'Once every 5 minutes', 'wc-rebill-subscription' ),
			);
		}
		if ( ! isset( $schedules['rebill_1min'] ) ) {
			$schedules['rebill_1min'] = array(
				'interval' => 1 * 60,
				'display'  => __( 'Once every 1 minute', 'wc-rebill-subscription' ),
			);
		}
		return $schedules;
	}
);
add_action(
	'init',
	function() {
		if ( ! wp_next_scheduled( 'do_rebill_check_update_product_price' ) ) {
			wp_schedule_event( time(), 'rebill_5min', 'do_rebill_check_update_product_price' );
		}
		if ( ! wp_next_scheduled( 'do_rebill_cronjob' ) ) {
			wp_schedule_event( time(), 'rebill_1min', 'do_rebill_cronjob' );
		}
		add_rewrite_endpoint( 'rebill-subscriptions', EP_ROOT | EP_PAGES );
		register_post_status(
			'wc-rebill-wait',
			array(
				'label'                     => __( 'Waiting for payment date', 'wc-rebill-subscription' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s: Total of order with status "Awaiting payment date".
				'label_count'               => _n_noop( 'Awaiting payment date (%s)', 'Awaiting payment date (%s)' ),
			)
		);
		register_post_status(
			'wc-rebill-toclone',
			array(
				'label'                     => __( 'Subscription', 'wc-rebill-subscription' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => false,
				// translators: %s: Total of order with status "Awaiting payment date".
				'label_count'               => _n_noop( 'Subscription (%s)', 'Subscription (%s)' ),
			)
		);
		register_post_status(
			'wc-rebill-subcancel',
			array(
				'label'                     => __( 'Subscription Cancelled', 'wc-rebill-subscription' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => false,
				// translators: %s: Total of order with status "Awaiting payment date".
				'label_count'               => _n_noop( 'Subscription Cancelled (%s)', 'Subscription Cancelled (%s)' ),
			)
		);
	}
);
add_action(
	'admin_head',
	function() {
		?>
<style>
.order-status.status-rebill-wait {
	background: #b7e6ff;
	color: #1749a0;
}
.order-status.status-rebill-toclone {
	background: #ff0070;
	color: #fff;
	font-weight: bold;
}
.order-status.status-rebill-subcancel {
	background: red;
	color: white;
}
</style>
		<?php
	}
);

// Adding columns to Order Page Admin.
add_filter(
	'manage_edit-shop_order_columns',
	function( $columns ) {
		$reordered_columns = array();
		// Inserting columns to a specific location.
		foreach ( $columns as $key => $column ) {
			$reordered_columns[ $key ] = $column;
			if ( 'order_status' === $key ) {
				$reordered_columns['rebill-txid']   = __( 'Rebill ID', 'wc-rebill-subscription' );
				$reordered_columns['rebill-parent'] = __( 'Subscription', 'wc-rebill-subscription' );
			}
		}
		return $reordered_columns;
	}
);
add_action(
	'manage_shop_order_posts_custom_column',
	function ( $column, $post_id ) {
		$settings = get_option( 'woocommerce_rebill-gateway_settings' );
		$order    = new WC_order( $post_id );
		switch ( $column ) {
			case 'rebill-txid':
				$rebill_transaction_id = $order->get_meta( 'rebill_transaction_id' );
				if ( $rebill_transaction_id ) {
						echo '<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/payments/view?id=' . esc_html( $rebill_transaction_id ) . '">#' . esc_html( $rebill_transaction_id ) . '</a>';
				}
				break;

			case 'rebill-parent':
				$all_order              = $order->get_meta( 'rebill_order_subscription_id' );
				$all_subscription       = $order->get_meta( 'rebill_all_subscription_id' );
				$rebill_subscription_id = $order->get_meta( 'rebill_subscription_id' );
				if ( $rebill_subscription_id ) {
					if ( is_array( $all_subscription ) && count( $all_subscription ) ) {
						echo '<b>' . esc_html( __( 'Subscription ID', 'wc-rebill-subscription' ) ) . ':</b> ';
						foreach ( $all_subscription as $id ) {
							if ( empty( $id ) ) {
								continue;
							}
							echo ' <a href="edit.php?post_status=all&post_type=shop_order&filter_shop_order_rebill=only-sub-id&filter_shop_order_rebill_id=' . esc_html( $id ) . '">#' . esc_html( $id ) . '</a> (<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/subscriptions/view?id=' . esc_html( $id ) . '">' . esc_html( __( 'On Rebill', 'wc-rebill-subscription' ) ) . '</a>)
								';
						}
						echo '<br />';
					} else {
						echo '<b>' . esc_html( __( 'Subscription ID', 'wc-rebill-subscription' ) ) . ':</b>
								<a href="edit.php?post_status=all&post_type=shop_order&filter_shop_order_rebill=only-sub-id&filter_shop_order_rebill_id=' . esc_html( $rebill_subscription_id ) . '">#' . esc_html( $rebill_subscription_id ) . '</a><br />
								<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/subscriptions/view?id=' . esc_html( $rebill_subscription_id ) . '">' . esc_html( __( 'See this on Rebill', 'wc-rebill-subscription' ) ) . '</a>
								<br />';
					}
					$rebill_subscription_parent = $order->get_meta( 'rebill_renew_from' );
					if ( $rebill_subscription_parent && (int) $rebill_subscription_parent !== (int) $post_id ) {
						echo '<b>' . esc_html( __( 'Parent Order', 'wc-rebill-subscription' ) ) . ':</b> <a href="post.php?post=' . (int) $rebill_subscription_parent . '&action=edit">#' . (int) $rebill_subscription_parent . '</a><br />';
					} elseif ( is_array( $all_order ) && count( $all_order ) ) {
							echo '<b>' . esc_html( __( 'Subscriptions', 'wc-rebill-subscription' ) ) . ':</b> ';
						foreach ( $all_order as $id ) {
							if ( empty( $id ) ) {
								continue;
							}
							echo ' <a href="post.php?post=' . (int) $id . '&action=edit">#' . (int) $id . '</a> ';
						}
							echo '<br />';
					}
				}
				break;
		}
	},
	20,
	2
);
add_action(
	'restrict_manage_posts',
	function() {
		global $pagenow, $post_type;
		if ( 'shop_order' === $post_type && 'edit.php' === $pagenow && is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$current = isset( $_GET['filter_shop_order_rebill'] ) ? wp_unslash( sanitize_key( $_GET['filter_shop_order_rebill'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification
			$current_id = isset( $_GET['filter_shop_order_rebill_id'] ) ? wp_unslash( sanitize_key( $_GET['filter_shop_order_rebill_id'] ) ) : '';
			echo '<div style="float: left;"><select name="filter_shop_order_rebill" onchange="if (jQuery(this).val() == \'only-rebill-id\' || jQuery(this).val() == \'only-sub-id\') jQuery(\'#filter_shop_order_rebill_id\').show(); else jQuery(\'#filter_shop_order_rebill_id\').hide();">
		<option value="">' . esc_html( __( 'Filter By', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-rebill' === $current ? 'selected' : '' ) . ' value="only-rebill">' . esc_html( __( 'Only Rebill Order', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-rebill-id' === $current ? 'selected' : '' ) . ' value="only-rebill-id">' . esc_html( __( 'Only this Rebill Transaction ID', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-sub-id' === $current ? 'selected' : '' ) . ' value="only-sub-id">' . esc_html( __( 'Only this Rebill Subscription ID', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-rebill-without-sub' === $current ? 'selected' : '' ) . ' value="only-rebill-without-sub">' . esc_html( __( 'Only Rebill Order (Without Subscription)', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-sub' === $current ? 'selected' : '' ) . ' value="only-sub">' . esc_html( __( 'Only Subscription (Parent and Renew)', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-parent' === $current ? 'selected' : '' ) . ' value="only-parent">' . esc_html( __( 'Only Subscription Parent', 'wc-rebill-subscription' ) ) . '</option>
		<option ' . ( 'only-renew' === $current ? 'selected' : '' ) . ' value="only-renew">' . esc_html( __( 'Only Subscription Renew', 'wc-rebill-subscription' ) ) . '</option>';
			echo '</select><input placeholder="' . esc_html( __( 'Rebill ID', 'wc-rebill-subscription' ) ) . '" value="' . esc_html( $current_id ) . '" id="filter_shop_order_rebill_id" name="filter_shop_order_rebill_id" type="text" style="' . ( 'only-sub-id' !== $current && 'only-rebill-id' !== $current ? 'display:none' : '' ) . '"/></div>';
		}
	}
);
add_action(
	'pre_get_posts',
	function( $query ) {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( is_admin() && $query->is_admin && 'edit.php' === $pagenow && isset( $_GET['filter_shop_order_rebill'] ) && ! empty( $_GET['filter_shop_order_rebill'] ) && isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type'] ) {
			$meta_query = $query->get( 'meta_query' ); // Get the current "meta query".
			if ( ! is_array( $meta_query ) ) {
				$meta_query = array();
			}

			switch ( $_GET['filter_shop_order_rebill'] ) {
				case 'only-rebill':
					$meta_query[] = array(
						'key'     => 'is_rebill',
						'compare' => 'EXISTS',
					);
					break;
				case 'only-rebill-without-sub':
					$meta_query[] = array(
						'key'     => 'is_rebill_one_payment',
						'compare' => 'EXISTS',
					);
					break;
				case 'only-rebill-id':
					$meta_query[] = array(
						'key'   => 'rebill_transaction_id',
						// phpcs:ignore WordPress.Security.NonceVerification
						'value' => isset( $_GET['filter_shop_order_rebill_id'] ) ? (int) $_GET['filter_shop_order_rebill_id'] : 0,
					);
					break;
				case 'only-sub-id':
					$meta_query[] = array(
						'key'   => 'rebill_subscription_id',
						// phpcs:ignore WordPress.Security.NonceVerification
						'value' => isset( $_GET['filter_shop_order_rebill_id'] ) ? (int) $_GET['filter_shop_order_rebill_id'] : 0,
					);
					break;
				case 'only-sub':
					$meta_query[] = array(
						'key'     => 'rebill_subscription_id',
						'compare' => 'EXISTS',
					);
					break;
				case 'only-parent':
					$meta_query[] = array(
						'key'     => 'is_rebill_to_clone',
						'compare' => 'EXISTS',
					);
					break;
				case 'only-renew':
					$meta_query[] = array(
						'key'     => 'is_rebill_renew',
						'compare' => 'EXISTS',
					);
					break;
			}
			$query->set( 'meta_query', $meta_query );
		}
	}
);
add_filter(
	'query_vars',
	function( $vars ) {
		$vars[] = 'rebill-subscriptions';
		return $vars;
	},
	0
);

add_filter(
	'woocommerce_account_menu_items',
	function( $items ) {
		$items['rebill-subscriptions'] = __( 'My Subscriptions', 'wc-rebill-subscription' );
		return $items;
	}
);
add_action(
	'woocommerce_order_details_after_order_table_items',
	function( $rebill_order ) {
		$subscription_id = $rebill_order->get_meta( 'rebill_subscription_id' );
		if ( ! $subscription_id ) {
			return;
		}
		$is_rebill_first_order = $rebill_order->get_meta( 'is_rebill_first_order' );
		$subscription_id       = $rebill_order->get_meta( 'rebill_subscription_id' );
		$renew_from            = $rebill_order->get_meta( 'rebill_renew_from' );
		$all_subscription_id   = $rebill_order->get_meta( 'rebill_all_subscription_id' );
		$all_order_id          = $rebill_order->get_meta( 'rebill_order_subscription_id' );
		if ( $is_rebill_first_order ) {
			echo '<tr><td><b>' . esc_html( __( 'Subscriptions', 'wc-rebill-subscription' ) ) . '</td><td>';
			foreach ( $all_order_id as $order_id ) {
					echo '<a href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . (int) $order_id . '">#' . (int) $order_id . '</a><br />';
			}
			echo '</td></tr>';
			return;
		} else {
			if ( $renew_from ) {
				$rebill_order_parent = new WC_Order( $renew_from );
			} else {
				$rebill_order_parent = $rebill_order;
			}
			$rebill_subscription = $rebill_order_parent->get_meta( 'rebill_subscription' );
			$ref                 = $rebill_order_parent->get_meta( 'rebill_ref' );
			$status_rebill       = $rebill_order_parent->get_meta( 'rebill_sub_status' );
			$status_txt          = $status_rebill;
			$rebill_subscription = $rebill_subscription[ $ref ];
			$next_payment        = json_decode( $rebill_order_parent->get_meta( 'rebill_next_payment' ), true );
			if ( $next_payment && is_array( $next_payment ) && count( $next_payment ) > 0 ) {
				$next_payment_d = $next_payment[0];
				$next_payment   = date_i18n( wc_date_format(), strtotime( $next_payment_d ) );
			} else {
				$next_payment   = '-';
				$next_payment_d = '';
			}
		}
		wc_get_template(
			'myaccount-table-items.php',
			array(
				'rebill_order'          => $rebill_order,
				'subscription_id'       => $subscription_id,
				'is_rebill_first_order' => $is_rebill_first_order,
				'renew_from'            => $renew_from,
				'all_subscription_id'   => $all_subscription_id,
				'all_order_id'          => $all_order_id,
				'rebill_order_parent'   => $rebill_order_parent,
				'rebill_subscription'   => $rebill_subscription,
				'ref'                   => $ref,
				'status_rebill'         => $status_rebill,
				'status_txt'            => $status_txt,
				'next_payment'          => $next_payment,
				'next_payment_d'        => $next_payment_d,
			),
			'',
			plugin_dir_path( WC_Rebill_Subscription::$plugin_file ) . 'templates/'
		);
	}
);

add_action(
	'woocommerce_order_details_after_order_table',
	function( $order ) {
		$subscription_id          = $order->get_meta( 'rebill_subscription_id' );
		$subscription_first_order = $order->get_meta( 'rebill_subscription_first_order' );
		if ( ! $subscription_id && $subscription_first_order > 0 && (int) $subscription_first_order !== (int) $order->get_id() ) {
			$p_order         = wc_get_order( $subscription_first_order );
			$subscription_id = $p_order->get_meta( 'rebill_subscription_id' );
		}
		if ( ! $subscription_id ) {
			return;
		}
		$customer       = get_current_user_id();
		$args           = array(
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'post_type'   => wc_get_order_types(),
			'post_status' => 'any',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => '_customer_user',
					'value' => $customer,
				),
				array(
					'key'   => 'rebill_subscription_id',
					'value' => $subscription_id,
				),
				array(
					'key'     => 'is_rebill_renew',
					'compare' => 'EXISTS',
				),
			),
		);
		$related_orders = get_posts(
			apply_filters( 'rebill_query_related_orders', $args )
		);
		if ( $related_orders && count( $related_orders ) > 0 ) {
			wc_get_template(
				'myaccount-list-related-orders.php',
				array(
					'related_orders' => $related_orders,
					'current_order'  => $order,
				),
				'',
				plugin_dir_path( WC_Rebill_Subscription::$plugin_file ) . 'templates/'
			);
		}
	}
);

add_action(
	'woocommerce_account_rebill-subscriptions_endpoint',
	function() {
		global $wpdb;
		$customer = get_current_user_id();
		if ( ! $customer ) {
			echo '<b style="color:red">' . esc_html( __( 'User Invalid', 'wc-rebill-subscription' ) ) . '</b>';
			return;
		}
		$order_id = (int) get_query_var( 'rebill-subscriptions' );
		if ( $order_id > 0 ) {
			$rebill_order = wc_get_order( $order_id );
			if ( ! $rebill_order || ! current_user_can( 'view_order', $rebill_order->get_id() ) ) {
				echo '<b style="color:red">' . esc_html( __( 'User or Order is Invalid', 'wc-rebill-subscription' ) ) . '</b>';
				return;
			}
			$is_rebill_to_clone = $rebill_order->get_meta( 'is_rebill_to_clone' );
			if ( $is_rebill_to_clone ) {
				echo '<h1>' . esc_html( __( 'Subscription', 'wc-rebill-subscription' ) ) . ' #' . (int) $order_id . '</h1>';
			} else {
				echo '<h1>' . esc_html( __( 'Subscription Renew', 'wc-rebill-subscription' ) ) . ' #' . (int) $order_id . '</h1>';
			}
			$subscription_id = $rebill_order->get_meta( 'rebill_subscription_id' );
			$card_id         = $rebill_order->get_meta( 'rebill_card_id' );
			$ref             = $rebill_order->get_meta( 'rebill_ref' );
			if ( isset( $_GET['request_cancel'] ) ) {
				$api          = new Rebill_API();
				$subscription = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
				if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
					$subscription = $subscription['response'][0];
					$u            = array(
						'id'     => $subscription['id'],
						'status' => 'cancelled',
					);
					$result       = $api->callApiPut( '/subscriptions', $u );
					if ( $result && $result['success'] ) {
						$rebill_subscription_parent = $rebill_order->get_meta( 'rebill_to_clone' );
						$p_order                    = $rebill_order;
						if ( isset( $rebill_subscription_parent[ $ref ] ) && (int) $rebill_subscription_parent[ $ref ] !== (int) $rebill_order->get_id() ) {
							$p_order = wc_get_order( $rebill_subscription_parent[ $ref ] );
						}
						update_post_meta( $p_order->get_id(), 'rebill_sub_status', 'cancelled' );
						$p_order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by customer.', 'wc-rebill-subscription' ) );
						$wait_payment = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = '" . (int) $subscription['id'] . "' LIMIT 1" );
						if ( $wait_payment ) {
							$wait_order = new WC_Order( $wait_payment );
							if ( (int) $wait_order->get_id() === (int) $wait_payment ) {
								$wait_order->update_status( 'cancelled', __( 'Rebill: The subscription was canceled by customer.', 'wc-rebill-subscription' ) );
								// $wait_order->update_meta_data( 'rebill_wait_payment', false );
							}
						}
						echo '<b style="color:green">' . esc_html( __( 'Subscription successfully canceled', 'wc-rebill-subscription' ) ) . '</b>';
					} else {
						echo '<b style="color:red">' . esc_html( __( 'Error requesting the cancellation of this subscription', 'wc-rebill-subscription' ) ) . '</b>';
					}
				}
			} elseif ( isset( $_GET['request_renew_card'] ) ) {
				$api = new Rebill_API();
				if ( ! $card_id ) {
					$subscription = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
					if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
						$subscription = $subscription['response'][0];
						$card_id      = $subscription['card_id'];
						$rebill_order->update_meta_data( 'rebill_card_id', $card_id );
					}
				}
				if ( ! $card_id ) {
					echo '<b style="color:red">' . esc_html( __( 'Error generating card renewal request', 'wc-rebill-subscription' ) ) . '</b>';
				} else {
					$result = $api->callApiPost(
						'/cards/requestRenewCard',
						array(
							'id'                  => $card_id,
							'request_destination' => 'customer',
							'vendor_id'           => null,
						)
					);
					if ( $result && $result['success'] ) {
						echo '<b style="color:green">' . esc_html( __( 'An e-mail has been sent to your e-mail from Rebill.to to update the card details.', 'wc-rebill-subscription' ) ) . '</b>';
					} else {
						echo '<b style="color:red">' . esc_html( __( 'Error generating card renewal request', 'wc-rebill-subscription' ) ) . '</b>';
					}
				}
			}
			wc_get_template(
				'myaccount/view-order.php',
				array(
					'order'    => $rebill_order,
					'order_id' => $rebill_order->get_id(),
				)
			);
			return;
		}
		echo '<h1>' . esc_html( __( 'My Subscriptions', 'wc-rebill-subscription' ) ) . '</h1>';
		// Get all subscription orders.
		$args            = array(
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'post_type'   => wc_get_order_types(),
			'post_status' => 'any',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => '_customer_user',
					'value' => $customer,
				),
				array(
					'key'     => 'is_rebill_to_clone',
					'compare' => 'EXISTS',
				),
			),
		);
		$customer_orders = get_posts(
			apply_filters( 'rebill_query_my_account', $args )
		);
		if ( count( $customer_orders ) < 1 ) {
			echo '<b>' . esc_html( __( 'Without subscription', 'wc-rebill-subscription' ) ) . '</b>';
			return;
		}
		wc_get_template( 'myaccount-list-orders.php', compact( 'customer_orders' ), '', plugin_dir_path( WC_Rebill_Subscription::$plugin_file ) . 'templates/' );
		?>
		<?php
	}
);

/**
 * Metabox Content
 *
 * @return void
 */
function rebill_metabox_cb() {
	global $post, $wpdb;
	$order = wc_get_order( $post );

	WC_Rebill_Subscription::get_instance();

	$subscription_id          = $order->get_meta( 'rebill_subscription_id' );
	$subscription_first_order = $order->get_meta( 'rebill_subscription_first_order' );
	$transaction_id           = $order->get_meta( 'rebill_transaction_id' );
	$p_order                  = $order;
	$c_order                  = $order;
	if ( $subscription_first_order > 0 && (int) $subscription_first_order !== (int) $order->get_id() ) {
		$p_order = wc_get_order( $subscription_first_order );
	}
	$subscription_to_clone = $order->get_meta( 'rebill_renew_from' );
	if ( $subscription_to_clone > 0 && (int) $subscription_to_clone !== (int) $order->get_id() ) {
		$c_order = wc_get_order( $subscription_to_clone );
	}
	$subscription_id   = $c_order->get_meta( 'rebill_subscription_id' );
	$related_orders    = false;
	$rebill_sub_status = false;
	if ( $subscription_id ) {
		$rebill_sub_status = $c_order->get_meta( 'rebill_sub_status' );
		$args              = array(
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'post_type'   => wc_get_order_types(),
			'post_status' => 'any',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'rebill_subscription_id',
					'value' => $subscription_id,
				),
				array(
					'key'     => 'is_rebill_renew',
					'compare' => 'EXISTS',
				),
			),
		);
		$related_orders    = get_posts(
			apply_filters( 'rebill_query_admin_related_orders', $args )
		);
		if ( ! $related_orders || count( $related_orders ) < 1 ) {
			$related_orders = false;
		}
		if ( isset( $_GET['rebill_request_cancel'] ) && isset( $_GET['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rebill_nonce'] ) ), 'admin_rebill_request_cancel' ) ) {
			$api             = new Rebill_API();
			$subscription_id = $order->get_meta( 'rebill_subscription_id' );
			$subscription    = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
			if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
				$subscription = $subscription['response'][0];
				$u            = array(
					'id'     => $subscription['id'],
					'status' => 'cancelled',
				);
				$result       = $api->callApiPut( '/subscriptions', $u );
				if ( $result && $result['success'] ) {
					update_post_meta( $order->get_id(), 'rebill_sub_status', 'cancelled' );
					$rebill_sub_status = 'cancelled';
					$order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by admin.', 'wc-rebill-subscription' ) );
					$wait_payment = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = '" . (int) $subscription['id'] . "' LIMIT 1" );
					if ( $wait_payment ) {
						$wait_order = new WC_Order( $wait_payment );
						if ( (int) $wait_order->get_id() === (int) $wait_payment ) {
							$wait_order->update_status( 'cancelled', __( 'Rebill: The subscription was canceled by admin.', 'wc-rebill-subscription' ) );
							// $wait_order->update_meta_data( 'rebill_wait_payment', false );
						}
					}
					echo '<b style="color:green;padding: 20px;text-align: center;display: block;font-size: 15px;">' . esc_html( __( 'Subscription successfully canceled', 'wc-rebill-subscription' ) ) . '</b>';
				} else {
					echo '<b style="color:red;padding: 20px;text-align: center;display: block;font-size: 15px;">' . esc_html( __( 'Error requesting the cancellation of this subscription', 'wc-rebill-subscription' ) ) . '</b>';
				}
			}
		}
		if ( isset( $_GET['rebill_request_new_card'] ) && isset( $_GET['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rebill_nonce'] ) ), 'admin_rebill_request_new_card' ) ) {
			$card_id = $order->get_meta( 'rebill_card_id' );
			$api     = new Rebill_API();
			if ( ! $card_id ) {
				$subscription_id = $order->get_meta( 'rebill_subscription_id' );
				$subscription    = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
				if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
					$subscription = $subscription['response'][0];
					$card_id      = $subscription['card_id'];
					$rebill_order->update_meta_data( 'rebill_card_id', $card_id );
				}
			}
			if ( ! $card_id ) {
				echo '<b style="color:red;padding: 20px;text-align: center;display: block;font-size: 15px;">' . esc_html( __( 'Error generating card renewal request', 'wc-rebill-subscription' ) ) . '</b>';
			} else {
				$result = $api->callApiPost(
					'/cards/requestRenewCard',
					array(
						'id'                  => $card_id,
						'request_destination' => 'customer',
						'vendor_id'           => null,
					)
				);
				if ( $result && $result['success'] ) {
					echo '<b style="color:green;padding: 20px;text-align: center;display: block;font-size: 15px;">' . esc_html( __( 'An e-mail has been sent to customer from Rebill.to to update the card details.', 'wc-rebill-subscription' ) ) . '</b>';
				} else {
					echo '<b style="color:red;padding: 20px;text-align: center;display: block;font-size: 15px;">' . esc_html( __( 'Error generating card renewal request', 'wc-rebill-subscription' ) ) . '</b>';
				}
			}
		}
	}
	$rebill_subscription = $c_order->get_meta( 'rebill_subscription' );
	$rebill_ref          = $c_order->get_meta( 'rebill_ref' );
	$is_renew_order      = $order->get_meta( 'is_rebill_renew' );
	$all_transactions    = $order->get_meta( 'rebill_transactions' );
	$is_rebill_first     = $order->get_meta( 'is_rebill_first_order' );
	$all_subscription_id = $order->get_meta( 'rebill_all_subscription_id' );
	$all_order_id        = $order->get_meta( 'rebill_order_subscription_id' );
	$settings            = get_option( 'woocommerce_rebill-gateway_settings' );
	wc_get_template(
		'admin-metabox.php',
		array(
			'transaction_id'      => $transaction_id,
			'is_renew_order'      => $is_renew_order,
			'all_transactions'    => $all_transactions,
			'subscription_id'     => $subscription_id,
			'is_rebill_first'     => $is_rebill_first,
			'all_subscription_id' => $all_subscription_id,
			'all_order_id'        => $all_order_id,
			'rebill_order'        => $order,
			'p_order'             => $p_order,
			'c_order'             => $c_order,
			'related_orders'      => $related_orders,
			'rebill_subscription' => $rebill_subscription,
			'rebill_ref'          => $rebill_ref,
			'settings'            => $settings,
			'sub_status'          => $rebill_sub_status,
		),
		'',
		plugin_dir_path( WC_Rebill_Subscription::$plugin_file ) . 'templates/'
	);
}

add_action(
	'add_meta_boxes',
	function() {
		global $post;
		$order = wc_get_order( $post );
		if ( $order ) {
			$is_rebill = $order->get_meta( 'is_rebill' );
			if ( ! $is_rebill ) {
				return;
			}
			add_meta_box( 'rebill-metabox', __( 'Rebill Information', 'wc-rebill-subscription' ), 'rebill_metabox_cb', 'shop_order', 'normal', 'high' );
		}
	}
);

add_action(
	'woocommerce_after_order_object_save',
	function( $order ) {
		global $wpdb;
		$subscription_id = $order->get_meta( 'rebill_subscription_id' );
		$is_first        = $order->get_meta( 'is_rebill_first_order' );
		$is_renew        = $order->get_meta( 'rebill_renew_from' );
		$order_id        = (int) $order->get_id();
		if ( $subscription_id ) {
			$api          = new Rebill_API();
			$subscription = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
			if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
				$subscription     = $subscription['response'][0];
				$old_subscription = $order->get_meta( 'rebill_subscription' );
				$ref              = $order->get_meta( 'rebill_ref' );
				if ( ! $is_first && ! $is_renew && abs( $order->get_total() - $subscription['transaction_amount'] ) > 0.001 ) {
					$u                       = array( 'id' => $subscription['id'] );
					$u['transaction_amount'] = $order->get_total();
					$api->callApiPut( '/subscriptions', $u );
					$ref          = explode( '-', $subscription['external_reference'] );
					$wait_payment = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = %d LIMIT 1", (int) $subscription['id'] ) );
					if ( ! $wait_payment ) {
						$wait_payment = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND  meta_value = %d LIMIT 1", 'rebill_wait_payment_' . wp_unslash( sanitize_key( $ref[1] ) ), (int) $subscription['id'] ) );
					}
					if ( $wait_payment ) {
						$wait_order = new WC_Order( $wait_payment );
						if ( (int) $wait_order->get_id() === (int) $wait_payment ) {
							$wait_order->update_status( 'cancelled', __( 'Rebill: The subscription was canceled by total order is changed.', 'wc-rebill-subscription' ) );
							delete_post_meta( $wait_payment, 'rebill_wait_payment' );
							$gateway   = WC_Rebill_Gateway::get_instance();
							$new_order = $gateway->duplicate_order( $order, true );
							if ( $new_order->get_id() > 0 ) {
								$new_order->update_meta_data( 'is_rebill', true );
								$new_order->update_meta_data( 'is_rebill_renew', true );
								$new_order->update_meta_data( 'rebill_wait_payment', $subscription['id'] );
								$new_order->update_meta_data( 'rebill_subscription_id', $subscription['id'] );
								$new_order->update_meta_data( 'rebill_subscription_first_order', (int) $ref[0] );
								$new_order->update_status( 'rebill-wait', __( 'Rebill: Waiting for payment date.', 'wc-rebill-subscription' ) );
							}
						}
					}
				}
				if ( ! $is_first && ! $is_renew && isset( $subscription['customer_id'] ) && ! empty( $subscription['customer_id'] ) ) {
					$order_id                    = (int) $order->get_id();
					$customer                    = array( 'id' => $subscription['customer_id'] );
					$customer['address_street']  = trim( get_post_meta( $order_id, '_shipping_address_1', true ) . ' ' . get_post_meta( $order_id, '_shipping_address_2', true ) );
					$customer['address_city']    = get_post_meta( $order_id, '_shipping_city', true );
					$customer['address_zipcode'] = get_post_meta( $order_id, '_shipping_postcode', true );
					$states                      = WC()->countries->get_states( get_post_meta( $order_id, '_shipping_country', true ) );
					$state                       = get_post_meta( $order_id, '_shipping_state', true );
					if ( $states && isset( $states[ $state ] ) ) {
						$state = $states[ $state ];
					}
					$customer['address_province'] = $state;
					if ( empty( $customer['address_street'] ) ) {
						$customer['address_street']  = trim( get_post_meta( $order_id, '_billing_address_1', true ) . ' ' . get_post_meta( $order_id, '_billing_address_2', true ) );
						$customer['address_city']    = get_post_meta( $order_id, '_billing_city', true );
						$customer['address_zipcode'] = get_post_meta( $order_id, '_billing_postcode', true );
						$states                      = WC()->countries->get_states( get_post_meta( $order_id, '_billing_country', true ) );
						$state                       = get_post_meta( $order_id, '_billing_state', true );
						if ( $states && isset( $states[ $state ] ) ) {
							$state = $states[ $state ];
						}
						$customer['address_province'] = $state;
					}
					$api->callApiPut( '/customers', $customer );
				}
				if ( isset( $_POST['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rebill_nonce'] ) ), 'rebill_admin_order_update' ) && $old_subscription && isset( $_POST['rebill_frequency_type'] ) && isset( $_POST['rebill_frequency'] ) && isset( $_POST['rebill_frequency_max'] ) ) {
					$rebill_subscription   = $old_subscription[ $ref ];
					$rebill_frequency_type = sanitize_key( $_POST['rebill_frequency_type'] );
					$rebill_frequency      = (int) $_POST['rebill_frequency'];
					$rebill_frequency_max  = (int) $_POST['rebill_frequency_max'];
					$u                     = array();
					if ( $rebill_frequency_type !== $rebill_subscription['frequency_type'] ) {
						$u['frequency_type'] = $rebill_frequency_type . 's';
					}
					if ( $rebill_frequency !== (int) $rebill_subscription['frequency'] ) {
						$u['frequency'] = $rebill_frequency;
					}
					if ( $rebill_frequency_max !== (int) $rebill_subscription['frequency_max'] ) {
						$u['repetitions'] = $rebill_frequency_max > 0 ? $rebill_frequency_max : null;
					}
					if ( count( $u ) > 0 ) {
						$u['id'] = $subscription['id'];
						$result  = $api->callApiPut( '/subscriptions', $u );
						if ( $result && $result['success'] ) {
							$old_subscription[ $ref ]['frequency_type'] = $rebill_frequency_type;
							$old_subscription[ $ref ]['frequency']      = $rebill_frequency;
							$old_subscription[ $ref ]['frequency_max']  = $rebill_frequency_max;
							$order->update_meta_data( 'rebill_subscription', $old_subscription );
						}
					}
				}
			}
		}
	},
	10,
	1
);

add_filter(
	'wc_order_is_editable',
	function( $editable, $order ) {
		if ( 'rebill-toclone' === $order->get_status() ) {
			$editable = true;
		}
		return $editable;
	},
	10,
	2
);

add_filter(
	'woocommerce_hidden_order_itemmeta',
	function( $list ) {
		$list[] = '_is_rebill_product';
		return $list;
	},
	10,
	1
);

add_action(
	'do_rebill_sync_status_payments',
	function ( $payment_id ) {
		WC_Rebill_Core::debug( 'do_rebill_sync_status_payments cronjob - ' . WC_Rebill_Core::pl( $payment_id, true ) );
		$api     = new Rebill_API();
		$payment = $api->callApiGet( '/payments/' . $payment_id, false, 120 );
		if ( $payment && isset( $payment['response'] ) && isset( $payment['response']['id'] ) ) {
			$payment      = $payment['response'];
			$order_id     = false;
			$ref          = false;
			$subscription = false;
			if ( isset( $payment['subscription_id'] ) && ! empty( $payment['subscription_id'] ) && is_numeric( $payment['subscription_id'] ) ) {
				$subscription_id = (int) $payment['subscription_id'];
				$subscription    = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
				if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
					$subscription = $subscription['response'][0];
					$reference    = explode( '-', $subscription['external_reference'] );
					$order_id     = (int) $reference[0];
					$ref          = $reference[1];
					WC_Rebill_Core::debug( 'do_rebill_sync_status_payments subscription payment: ' . WC_Rebill_Core::pl( $order_id, true ) . ' - ' . WC_Rebill_Core::pl( $ref, true ) );
				}
			}
			if ( ! $order_id && isset( $payment['external_reference'] ) && ! empty( $payment['external_reference'] ) && is_numeric( $payment['external_reference'] ) ) {
				$order_id = (int) $payment['external_reference'];
			} elseif ( ! $order_id && isset( $payment['external_reference'] ) && ! empty( $payment['external_reference'] ) ) {
				$reference = explode( '-', $payment['external_reference'] );
				if ( count( $reference ) === 2 && is_numeric( $reference[0] ) ) {
					$order_id = (int) $reference[0];
					$ref      = $reference[1];
				}
			}
			WC_Rebill_Core::debug( 'do_rebill_sync_status_payments order_id - ' . WC_Rebill_Core::pl( $order_id, true ) . ' - ' . WC_Rebill_Core::pl( $ref, true ) );
			if ( $order_id > 0 ) {
				$order = new WC_Order( $order_id );
				WC_Rebill_Core::debug( 'do_rebill_sync_status_payments order - ' . WC_Rebill_Core::pl( $order, true ) );
				if ( (int) $order->get_id() === $order_id ) {
					$gateway = WC_Rebill_Gateway::get_instance();
					$gateway->check_rebill_response( $order, $payment, $subscription, false );
				} else {
					WC_Rebill_Core::debug( 'do_rebill_sync_status_payments ERROR - order not found ' . WC_Rebill_Core::pl( $order_id, true ) );
				}
			}
		}
		WC_Rebill_Core::debug( 'do_rebill_sync_status_payments end-cronjob - ' . WC_Rebill_Core::pl( $payment_id, true ) );
		wp_clear_scheduled_hook( 'do_rebill_sync_status_payments', array( (int) $payment_id ) ); //TODO: Remove deprecated cronjob handle...
	}
);

add_action(
	'do_rebill_sync_status_subscriptions',
	function ( $subscription_id ) {
		global $wpdb;
		WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions cronjob - ' . WC_Rebill_Core::pl( $subscription_id, true ) );
		$api          = new Rebill_API();
		$subscription = $api->callApiGet( '/subscriptions/id/' . $subscription_id, false, 120 );
		WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions rsp - ' . WC_Rebill_Core::pl( $subscription, true ) );
		if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
			$subscription = $subscription['response'][0];
			$reference    = explode( '-', $subscription['external_reference'] );
			WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions rsp2 - ' . WC_Rebill_Core::pl( $subscription, true ) );
			$order_id = (int) $reference[0];
			$ref      = $reference[1];
			WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions order_id - ' . WC_Rebill_Core::pl( $order_id, true ) );
			WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions ref - ' . WC_Rebill_Core::pl( $ref, true ) );
			if ( $order_id ) {
				$order = new WC_Order( $order_id );
				if ( (int) $order->get_id() === $order_id ) {
					WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions found - ' . WC_Rebill_Core::pl( $order_id, true ) );
					// $gateway = WC_Rebill_Gateway::get_instance();
					// $gateway->update_meta_order_first_order( $order, $subscription, $ref );
					$all_metas = get_post_meta( $order_id );
					WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions all metas - ' . WC_Rebill_Core::pl( $all_metas, true ) );
					$subscription_to_clone = (int) $order->get_meta( 'rebill_renew_from' );
					$c_order               = $order;
					if ( $subscription_to_clone > 0 && (int) $subscription_to_clone !== (int) $order->get_id() ) {
						$c_order = wc_get_order( $subscription_to_clone );
					} else {
						$subscription_to_clone = $order->get_meta( 'rebill_to_clone' );
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions subscription_to_clone3 - ' . WC_Rebill_Core::pl( $subscription_to_clone, true ) );
						if ( is_array( $subscription_to_clone ) && isset( $subscription_to_clone[ $ref ] ) ) {
							$c_order = wc_get_order( $subscription_to_clone[ $ref ] );
						}
					}
					$old_subscription = $c_order->get_meta( 'rebill_subscription' );
					if ( isset( $old_subscription[ $ref ] ) && isset( $subscription['debit_date'] ) && $subscription['debit_date'] > 0 ) {
						$old_subscription[ $ref ]['synchronization'] = $subscription['debit_date'];
						update_post_meta( $c_order->get_id(), 'rebill_subscription', $old_subscription );
					}
					if ( 'active' === $subscription['status'] ) {
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions cronjob-end 1 - ' . WC_Rebill_Core::pl( $subscription_id, true ) );
						wp_clear_scheduled_hook( 'do_rebill_sync_status_subscriptions', array( (int) $subscription_id ) );
						return;
					}
					if ( 'cancelled' === $subscription['status'] || 'defaulted' === $subscription['status'] ) {
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions parent order - ' . $c_order->get_id() );
						$wait_payment = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = '" . (int) $subscription_id . "' LIMIT 1" );
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions wait_payment - ' . WC_Rebill_Core::pl( $wait_payment, true ) );
						if ( ! $wait_payment ) {
							$wait_payment = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND  meta_value = %d LIMIT 1", 'rebill_wait_payment_' . $ref, (int) $subscription_id ) );
						}
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions step - 1' );
						if ( $wait_payment ) {
							$wait_order = new WC_Order( $wait_payment );
							if ( (int) $wait_order->get_id() === $wait_payment ) {
								WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions cancelled - ' . WC_Rebill_Core::pl( $wait_payment, true ) );
								$wait_order->update_status( 'cancelled', __( 'Rebill: The subscription was canceled, is in default or failure.', 'wc-rebill-subscription' ) );
							}
						}
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions subcancel - ' . $c_order->get_id() );
						update_post_meta( $c_order->get_id(), 'rebill_sub_status', 'cancelled' );
						$c_order->update_status( 'rebill-subcancel', __( 'Rebill: The subscription was canceled by rebill.', 'wc-rebill-subscription' ) );
						WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions step - 3' );
					}
				}
			}
		}
		WC_Rebill_Core::debug( 'do_rebill_sync_status_subscriptions cronjob-end 2 - ' . WC_Rebill_Core::pl( $subscription_id, true ) );
		wp_clear_scheduled_hook( 'do_rebill_sync_status_subscriptions', array( (int) $subscription_id ) ); //TODO: Remove deprecated cronjob handle...
	}
);

add_action(
	'do_rebill_subscription_charge_in_24_hours',
	function ( $subscription_id ) {
		global $wpdb;
		WC_Rebill_Core::debug( 'do_rebill_subscription_charge_in_24_hours cronjob - ' . WC_Rebill_Core::pl( $subscription_id, true ) );
		$api          = new Rebill_API();
		$subscription = $api->callApiGet( '/subscriptions/id/' . $d['id'], false, 120 );
		WC_Rebill_Core::debug( 'check_ipn result - ' . WC_Rebill_Core::pl( $subscription, true ) );
		if ( $subscription && isset( $subscription['response'] ) && isset( $subscription['response'][0]['id'] ) ) {
			$subscription = $subscription['response'][0];
			$reference    = explode( '-', $subscription['external_reference'] );
			$order_id     = (int) $reference[0];
			$ref          = $reference[1];
			if ( $order_id ) {
				$order = new WC_Order( $order_id );
				if ( (int) $order->get_id() === $order_id ) {
					$gateway = WC_Rebill_Gateway::get_instance();
					$gateway->update_meta_order_first_order( $order, $subscription, false, $ref );

					$subscription_to_clone = (int) $order->get_meta( 'rebill_renew_from' );
					$c_order               = $order;
					if ( $subscription_to_clone > 0 && (int) $subscription_to_clone !== (int) $order->get_id() ) {
						$c_order = wc_get_order( $subscription_to_clone );
					} else {
						$subscription_to_clone = $order->get_meta( 'rebill_to_clone' );
						if ( is_array( $subscription_to_clone ) && isset( $subscription_to_clone[ $ref ] ) ) {
							$c_order = wc_get_order( $subscription_to_clone[ $ref ] );
						}
					}
					$old_subscription = $c_order->get_meta( 'rebill_subscription' );
					WC_Rebill_Core::debug( 'do_rebill_subscription_charge_in_24_hours - parent: ' . WC_Rebill_Core::pl( $subscription_to_clone, true ) );
					if ( isset( $old_subscription[ $ref ] ) && isset( $subscription['debit_date'] ) && $subscription['debit_date'] > 0 ) {
						$old_subscription[ $ref ]['synchronization'] = $subscription['debit_date'];
						update_post_meta( $c_order->get_id(), 'rebill_subscription', $old_subscription );
					}
					/*
					// TODO: No se recomienda este tipo de update por el momento, PD: CASO BIURY
					$wait_payment = (int) $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rebill_wait_payment' AND  meta_value = '" . (int) $subscription['id'] . "' LIMIT 1" );
					if ( ! $wait_payment ) {
						$new_order = $gateway->duplicate_order( $order, $ref, true );
						if ( $new_order->get_id() > 0 ) {
							$new_order->update_meta_data( 'is_rebill', true );
							$new_order->update_meta_data( 'is_rebill_renew', true );
							$new_order->update_meta_data( 'rebill_wait_payment', $subscription['id'] );
							$new_order->update_meta_data( 'rebill_subscription_id', $subscription['id'] );
							$new_order->update_meta_data( 'rebill_subscription_first_order', $order_id );
							$new_order->update_status( 'rebill-wait', __( 'Rebill: Waiting for payment date.', 'wc-rebill-subscription' ) );
							if ( abs( $new_order->get_total() - $subscription['transaction_amount'] ) > 0.001 ) {
								$u                       = array( 'id' => $subscription['id'] );
								$u['transaction_amount'] = $new_order->get_total();
								$api->callApiPut( '/subscriptions', $u );
							}
						}
					}
					*/
				}
			}
		}
		WC_Rebill_Core::debug( 'do_rebill_subscription_charge_in_24_hours cronjob end - ' . WC_Rebill_Core::pl( $subscription_id, true ) );
		wp_clear_scheduled_hook( 'do_rebill_subscription_charge_in_24_hours', array( (int) $payment_id ) ); //TODO: Remove deprecated cronjob handle...
	}
);

add_action(
	'do_rebill_check_update_product_price',
	function () {
		global $wpdb;
		static $api             = false;
		$checked_subscriptions  = get_option( 'rebill_subscriptions_with_totals_updated', array() );
		$subscriptions_to_check = get_posts(
			array(
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'post_type'      => 'shop_order',
				'posts_per_page' => 10,
				'post__not_in'   => $checked_subscriptions,
				'post_status'    => array(
					'wc-rebill-toclone',
				),
			)
		);
		$found                  = false;
		WC_Rebill_Core::log( 'cronjob init: ' . WC_Rebill_Core::pL( $subscriptions_to_check, true ) );
		foreach ( $subscriptions_to_check as $order_id ) {
			WC_Rebill_Core::log( 'cronjob checking ' . $order_id );
			$found  = true;
			$change = false;
			$order  = wc_get_order( $order_id );
			foreach ( $order->get_items() as $item_id => $item ) {
				$pid        = $item['product_id'];
				$vid        = false;
				$product    = wc_get_product( $pid );
				$autoupdate = ( 'yes' === $product->get_meta( 'rebill_autoupdate', true ) );
				if ( ! $autoupdate ) {
					continue;
				}
				$variation = false;
				if ( isset( $item['variation_id'] ) && ! empty( $item['variation_id'] ) ) {
					$vid       = $item['variation_id'];
					$variation = wc_get_product( $vid );
				}
				$new_line_total = (float) $product->get_meta( 'rebill_price', true );
				if ( $new_line_total < 0.001 ) {
					$new_line_total = $variation ? $variation->get_price() : $product->get_price();
				}
				$line_total = $item['line_total'] / $qty;
				if ( abs( $new_line_total - $line_total ) > 0.001 ) {
					WC_Rebill_Core::log( 'cronjob new price update order ' . $order_id . ': ' . WC_Rebill_Core::pL( $new_line_total, true ) . ' ' . WC_Rebill_Core::pL( $pid, true ) . ' ' . WC_Rebill_Core::pL( $vid, true ) );
					$change   = true;
					$qty      = $item['quantity'];
					$name     = $item['name'];
					$line_tax = $item['line_tax'] / $qty;
					$ptax     = $line_tax / $line_total;
					$new_data = array(
						'name'      => $name,
						'quantity'  => $qty,
						'tax_class' => $item['tax_class'],
						'total'     => $new_line_total * $qty,
						'subtotal'  => $new_line_total * $qty,
					);
					$taxes    = $item->get_taxes();
					foreach ( $taxes as &$line ) {
						foreach ( $line as &$itax ) {
							$tax  = $itax / $qty;
							$ptax = $tax / $line_total;
							$itax = $new_line_total * $ptax * $qty;
						}
					}
					$new_data['taxes'] = $taxes;
					$item->set_props( $new_data );
					WC_Rebill_Core::log( 'cronjob set_props update order: ' . WC_Rebill_Core::pL( $taxes, true ) . WC_Rebill_Core::pL( $new_data, true ) );
					do_action( 'woocommerce_before_save_order_item', $item );
					$item->save();
				}
			}
			if ( $change ) {
				$order->update_taxes();
				$order->calculate_totals( false );
				$subscription_id         = get_post_meta( $order_id, 'rebill_subscription_id' );
				$u                       = array( 'id' => $subscription_id );
				$u['transaction_amount'] = $order->get_total();
				if ( ! $api ) {
					$api = new Rebill_API();
				}
				$result = $api->callApiPut( '/subscriptions', $u );
			}
			$checked_subscriptions[] = $order_id;
			update_option( 'rebill_subscriptions_with_totals_updated', $checked_subscriptions, false );
		}
		WC_Rebill_Core::log( 'cronjob checked_subscriptions: ' . WC_Rebill_Core::pL( $checked_subscriptions, true ) );
		if ( ! $found ) {
			WC_Rebill_Core::log( 'cronjob checked_subscriptions: reset' );
			update_option( 'rebill_subscriptions_with_totals_updated', array(), false );
		}
	}
);

add_action(
	'do_rebill_cronjob',
	function () {
		global $wpdb;
		$lock_key     = wp_generate_password( 32 );
		$max_locktime = time() - 30 * 60;
		$wpdb->query(
			'UPDATE `' . $wpdb->prefix . 'rebill_cronjob`
			SET `lock_key`= null,  `lock_date` = null
			WHERE `run_at` IS NULL AND `lock_date` IS NOT NULL AND `lock_date` < ' . (int) $max_locktime
		);
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE `' . $wpdb->prefix . 'rebill_cronjob`
					SET `lock_key`= %s,  `lock_date` = %d
					WHERE `run_at` IS NULL AND `lock_key` IS NULL AND `next_retry` < %d ORDER BY `next_retry` ASC LIMIT 5',
				$lock_key,
				time(),
				time()
			)
		);
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . $wpdb->prefix . 'rebill_cronjob` WHERE `lock_key` = %s',
				$lock_key
			)
		);
		WC_Rebill_Core::log( 'cronjob tasks: ' . WC_Rebill_Core::pL( $jobs, true ) );
		if ( isset( $jobs[0] ) ) {
			foreach ( $jobs as $job ) {
				do_action( $job->cmd, $job->arg );
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE `' . $wpdb->prefix . 'rebill_cronjob`
							SET `lock_key`= null, `run_at` = %s
							WHERE `id` = %s',
						time(),
						$job->id
					)
				);
			}
		}
		WC_Rebill_Core::log( 'cronjob end-tasks' );
	}
);
