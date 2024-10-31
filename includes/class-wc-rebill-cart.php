<?php
/**
 * Rebill
 *
 * Cart Class
 *
 * @package    Rebill
 * @subpackage WC_Rebill_Cart
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! class_exists( 'WC_Rebill_Cart' ) ) :

	/**
	 * WooCommerce Rebill Product main class.
	 */
	class WC_Rebill_Cart extends WC_Rebill_Core {

		/**
		 * Constructor for the gateway.
		 *
		 * @return void
		 */
		public function __construct() {
			WC_Rebill_Core::__construct();
			$this->mix_cart               = ( 'yes' === $this->get_option( 'mix_cart' ) );
			$this->only_for_sub           = ( 'yes' === $this->get_option( 'only_for_sub' ) );
			$this->one_by_cart            = ( 'yes' === $this->get_option( 'one_by_cart' ) );
			$this->one_by_customer        = ( 'yes' === $this->get_option( 'one_by_customer' ) );
			add_filter( 'woocommerce_cart_loaded_from_session', array( $this, 'validate_cart_contents_for_mixed_checkout' ), 10 );
			add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'can_add_product_to_cart' ), 10, 6 );
			add_filter( 'woocommerce_after_cart_item_quantity_update', array( $this, 'change_cart_item_quantity' ), 10, 4 );
			add_filter( 'woocommerce_remove_cart_item', array( $this, 'remove_cart_item' ), 10, 2 );
			add_filter( 'woocommerce_before_calculate_totals', array( $this, 'calculate_totals' ), 100000, 1 );
			add_filter( 'woocommerce_after_cart_item_name', array( $this, 'cart_item_name' ), 100000, 2 );
			add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'checkout_cart_item_quantity' ), 100000, 3 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'available_payment_gateways' ), 100000 );
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_line_item_custom_meta' ), 10, 4 );
			add_filter( 'woocommerce_get_price_html', array( $this, 'subscription_product_price_html' ), 10, 2 );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		}
		/**
		 * Redirect URL for invalid cart.
		 *
		 * @param array $fragments Cart fragments.
		 *
		 * @return array
		 */
		public function redirect_ajax_add_to_cart( $fragments ) {

			$fragments['error']       = true;
			$fragments['product_url'] = wc_get_cart_url();
			add_filter( 'woocommerce_add_to_cart_validation', '__return_false', 10 );
			add_filter( 'woocommerce_cart_redirect_after_error', 'wc_get_cart_url', 10 );
			do_action( 'wc_ajax_add_to_cart' );

			return $fragments;
		}
		/**
		 * Get cart shipping method.
		 *
		 * @param string $cart_item_key Cart item key.
		 *
		 * @return string
		 */
		private function get_cart_item_shipping_method( $cart_item_key = false ) {
			WC_Rebill_Core::check_session();
			$chosen_shippings = WC()->session->get( 'chosen_shipping_methods' );
			foreach ( WC()->cart->get_shipping_packages() as $id => $package ) {
				$chosen = $chosen_shippings[ $id ];
				if ( WC()->session->__isset( 'shipping_for_package_' . $id ) ) {
					return WC()->session->get( 'shipping_for_package_' . $id )['rates'][ $chosen ];
				}
			}
			return false;
		}


		/**
		 * Filter product price if it's subscription.
		 *
		 * @param float      $price Current price.
		 * @param WC_Product $product Cart item key.
		 *
		 * @return float
		 */
		public function subscription_product_price_html( $price, $product ) {
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variable' ) && ! is_a( $product, 'WC_Product_Variation' ) ) {
				$product = wc_get_product( $product );
			}
			if ( ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) && ! is_a( $product, 'WC_Product' ) ) {
				return $price;
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			WC_Rebill_Core::check_session();
			$rebill_cart = WC()->session->get( 'rebill_cart', array() );
			$new_price   = (float) $product->get_meta( 'rebill_price', true );
			if ( empty( $price ) ) {
				$price = wc_price( 0 );
			}
			if ( $new_price > 0 ) {
				$frequency_type = $product->get_meta( 'rebill_frequency_type', true );
				$frequency      = $product->get_meta( 'rebill_frequency', true );
				if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
					$frequency = 1;
				}
				if ( is_array( $frequency ) ) {
					$frequency = $frequency[0];
				}
				$result = '';
				switch ( $frequency_type ) {
					case 'day':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'every %s day', 'every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'month':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'every %s month', 'every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'year':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'every %s year', 'every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
				}
				if ( 'yes' !== $product->get_meta( 'rebill_only', true ) ) {
					// translators: %1$s One-time price, %2$s subscription price, %3$s Frequency.
					$price = sprintf( __( 'One-time %1$s or %2$s %3$s', 'wc-rebill-subscription' ), $price, wc_price( $new_price ), $result );
				} else {
					$price = wc_price( $new_price ) . ' ' . $result;
				}
			}
			return $price;
		}
		/**
		 * Filter product price if it's subscription.
		 *
		 * @param float      $price Current price.
		 * @param WC_Product $product Cart item key.
		 *
		 * @return float
		 */
		public function subscription_product_price( $price, $product ) {
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			if ( ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				return $price;
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			WC_Rebill_Core::check_session();
			$rebill_cart = WC()->session->get( 'rebill_cart', array() );
			if ( isset( $rebill_cart[ $product_id ] ) || 'yes' === $product->get_meta( 'rebill_only', true ) ) {
				$new_price = (float) $product->get_meta( 'rebill_price', true );
				if ( $new_price > 0 ) {
					$price = $new_price;
				}
			}
			return $price;
		}
		/**
		 * Save rebill_subscription meta data from cart to order.
		 *
		 * @param Object   $order_item Order item.
		 * @param string   $cart_item_key Cart item key.
		 * @param int      $values Values.
		 * @param WC_Order $order Order.
		 *
		 * @return void
		 */
		public function add_order_line_item_custom_meta( $order_item, $cart_item_key, $values, $order ) {
			WC_Rebill_Core::check_session();
			$item        = WC()->cart->cart_contents[ $cart_item_key ];
			$rebill_cart = WC()->session->get( 'rebill_cart', array() );
			self::log( 'INIT add_order_line_item_custom_meta: ' . $cart_item_key . '->' . self::pl( $rebill_cart, true ) . '->' . self::pl( $item, true ) );
			$product = $item['data'];
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			$quantity   = 0;
			$frequency  = 0;
			if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
				if ( isset( $rebill_cart[ $product_id ] ) ) {
					if ( ! empty( $variation_id ) && $variation_id > 0 ) {
						if ( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] === $item['unique_key'] ) {
							if ( isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
								$quantity = $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'];
							}
						}
					} else {
						if ( $rebill_cart[ $product_id ]['s_unique_key'] === $item['unique_key'] ) {
							$quantity = $rebill_cart[ $product_id ]['quantity'];
						}
					}
					if ( $quantity > 0 ) {
						$frequency = $rebill_cart[ $product_id ]['frequency'];
					}
				}
			}
			if ( $quantity > 0 && ! $frequency ) {
				$frequency = $product->get_meta( 'rebill_frequency', true );
				if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
					$frequency = 1;
				}
				if ( is_array( $frequency ) ) {
					$frequency = $frequency[0];
				}
			}
			self::log( 'add_order_line_item_custom_meta: ' . $cart_item_key . '->' . self::pl( $quantity, true ) . ' - Values: ' . self::pl( $values, true ) );
			if ( $quantity > 0 ) {
				$order->update_meta_data( 'is_rebill', true );
				$order_item->add_meta_data( '_is_rebill_product', true );
				$order_id           = $order->get_id();
				$order_subscription = $order->get_meta( 'rebill_subscription' );
				if ( ! $order_subscription || ! is_array( $order_subscription ) ) {
					$order_subscription = array();
				}
				if ( ! isset( $subscription[ $product_id ] ) ) {
					$subscription[ $product_id ] = array();
				}
				$frequency_type = $product->get_meta( 'rebill_frequency_type', true );
				$rate           = $this->get_cart_item_shipping_method( $cart_item_key );
				self::log( 'add_order_line_item_custom_meta shipping: ' . $cart_item_key . '->' . self::pl( $rate, true ) );
				$rate_tax = 0;
				if ( isset( $rate->taxes ) && count( $rate->taxes ) ) {
					foreach ( $rate->taxes as $st ) {
						$rate_tax += $st;
					}
				}
				$d_hash   = array( (int) $frequency );
				$d_hash[] = $frequency_type;
				$d_hash[] = (int) $product->get_meta( 'rebill_frequency_max', true );
				if ( 'yes' === $product->get_meta( 'rebill_free_trial', true ) ) {
					$d_hash[] = $product->get_meta( 'rebill_frequency_type_trial', true );
					$d_hash[] = (int) $product->get_meta( 'rebill_frequency_trial', true );
				}
				$synchronization = (int) $product->get_meta( 'rebill_synchronization', true );
				if ( $synchronization && 'month' === $frequency_type ) {
					$d_hash[] = $synchronization;
					$d_hash[] = $product->get_meta( 'rebill_synchronization_type', true );
				}
				$hash = substr( md5( implode( ';', $d_hash ) ), 0, 8 );
				if ( ! isset( $order_subscription[ $hash ] ) ) {
					$order_subscription[ $hash ] = array(
						'products'             => array(),
						'hash'                 => $hash,
						'd_hash'               => $d_hash,
						'total'                => 0,
						'total_items'          => 0,
						'shipping_id'          => $rate->id,
						'shipping_label'       => $rate->label,
						'shipping_cost'        => $rate->cost + $rate_tax,
						'frequency'            => (int) $frequency,
						'frequency_type'       => $product->get_meta( 'rebill_frequency_type', true ),
						'frequency_max'        => (int) $product->get_meta( 'rebill_frequency_max', true ),
						'synchronization'      => (int) $product->get_meta( 'rebill_synchronization', true ),
						'synchronization_type' => $product->get_meta( 'rebill_synchronization_type', true ),
						'free_trial'           => $product->get_meta( 'rebill_free_trial', true ),
						'frequency_trial'      => $product->get_meta( 'rebill_frequency_trial', true ),
						'frequency_type_trial' => $product->get_meta( 'rebill_frequency_type_trial', true ),
					);
				}
				if ( (int) $quantity !== (int) $item['quantity'] ) {
					$order->update_meta_data( 'rebill_with_onetime', true );
				}
				$order_subscription[ $hash ]['total_items'] += $quantity;
				$subscription                                = $order_subscription[ $hash ]['products'];
				$total                                       = ( $item['line_total'] + $item['line_tax'] ) / $item['quantity'];
				$order_subscription[ $hash ]['total']       += $total * $quantity;
				if ( $variation_id > 0 ) {
					if ( ! isset( $subscription[ $product_id ]['variation'] ) ) {
						$subscription[ $product_id ]['variation'] = array();
					}
					$subscription[ $product_id ]['variation'][ $variation_id ] = array(
						'quantity'   => $quantity,
						'line_price' => $total,
					);
				} else {
					$subscription[ $product_id ]['line_price'] = $total;
					$subscription[ $product_id ]['quantity']   = $quantity;
				}
				$thankpage = $product->get_meta( 'rebill_thankpage', true );
				if ( ! empty( $thankpage ) ) {
					$order_thankpage = $order->get_meta( 'rebill_thankpage' );
					if ( $order_thankpage && ! empty( $order_thankpage ) && $order_thankpage !== $thankpage ) {
						$thankpage = 'ignore';
					}
					$order->update_meta_data( 'rebill_thankpage', $thankpage );
				}
				$frequency_date = '';
				if ( $frequency_max ) {
					$frequency_date      = '+' . ( $frequency * $frequency_max ) . ' ' . $frequency_type;
					$frequency_date_sync = '+' . ( $frequency * ( $frequency_max - 1 ) ) . ' ' . $frequency_type;
				}
				$price                                = (float) $product->get_meta( 'rebill_price', true );
				$free_trial                           = $product->get_meta( 'rebill_free_trial', true );
				$frequency_type_trial                 = $product->get_meta( 'rebill_frequency_type_trial', true );
				$frequency_trial                      = (int) $product->get_meta( 'rebill_frequency_trial', true );
				$subscription[ $product_id ]['price'] = $price;
				if ( 'month' === $frequency_type ) {
					$synchronization      = (int) $product->get_meta( 'rebill_synchronization', true );
					$synchronization_type = $product->get_meta( 'rebill_synchronization_type', true );
					if ( 1 <= $synchronization && 27 >= $synchronization && (int) gmdate( 'd' ) !== $synchronization ) {
						$subscription[ $product_id ]['synchronization']      = $synchronization;
						$subscription[ $product_id ]['synchronization_type'] = $synchronization_type;
						$subscription[ $product_id ]['next_payment']         = $this->calculate_next_payment( $synchronization );
						if ( ! empty( $frequency_date_sync ) ) {
							$subscription[ $product_id ]['last_payment'] = strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) );
						}
					} elseif ( ! empty( $frequency_date ) ) {
						$subscription[ $product_id ]['last_payment'] = strtotime( $frequency_date );
					}
				} elseif ( ! empty( $frequency_date ) ) {
					$subscription[ $product_id ]['last_payment'] = strtotime( $frequency_date );
				}
				$order_subscription[ $hash ]['products'] = $subscription;
				self::log( 'rebill_subscription: ->' . self::pl( $order_subscription, true ) );
				$order->update_meta_data( 'rebill_subscription', $order_subscription );
			} else {
				$order->update_meta_data( 'rebill_with_onetime', true );
			}
		}
		/**
		 * Check mixed cart.
		 *
		 * @param   WC_Cart $cart Current Cart.
		 *
		 * @return WC_Cart $cart
		 */
		public function validate_cart_contents_for_mixed_checkout( $cart ) {
			global  $wpdb;
			
			$s_qty = 0;
			$subscription = 0;
			$products     = 0;
			foreach ( $cart->cart_contents as $key => $item ) {
				$product = $item['data'];
				if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
					$product = wc_get_product( $product );
				}
				$variation_id = 0;
				$variation    = false;
				if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
					$variation    = $product;
					$variation_id = $variation->get_id();
					$parent       = $variation->get_parent_id();
					$product      = new WC_Product( $parent );
				}
				if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
					if ( 'yes' === $product->get_meta( 'rebill_only', true ) ) {
						++$subscription;
						$s_qty += $item['quantity'];
						continue;
					}
					WC_Rebill_Core::check_session();
					$rebill_cart = WC()->session->get( 'rebill_cart', array() );
					if ( ! isset( $rebill_cart[ $product->get_id() ] ) || $variation_id && $rebill_cart[ $product->get_id() ]['s_unique_key'][ $variation_id ] !== $item['unique_key'] || ! $variation_id && $rebill_cart[ $product->get_id() ]['s_unique_key'] !== $item['unique_key'] ) {
						++$products;
					} else {
						++$subscription;
						$s_qty += $item['quantity'];
					}
				} else {
					++$products;
				}
			}
			$customer_id = get_current_user_id();
			if ( $products > 0 && $subscription > 0 && ! $this->mix_cart ) {
				wc_add_notice( __( 'Products and subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'redirect_ajax_add_to_cart' ) );
			} elseif ( $s_qty > 1 && $this->one_by_cart ) {
				wc_add_notice( __( 'Multiple subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'redirect_ajax_add_to_cart' ) );
			} elseif ( $subscription > 0 && $this->one_by_customer && $customer_id > 0 ) {
				if ( $wpdb->get_var(
					"SELECT
						MAX(p.ID) FROM {$wpdb->prefix}posts p
					INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
					WHERE p.post_type = 'shop_order'
						AND p.post_status = 'wc-rebill-toclone'
						AND pm.meta_key = '_customer_user'
						AND pm.meta_value = '" . (int) $customer_id . "'"
				) ) {
					wc_add_notice( __( 'Multiple subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
					add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'redirect_ajax_add_to_cart' ) );
				}
			}
			return $cart;
		}

		/**
		 * Don't allow new subscription products to be added to the cart if it contains a subscription or product already.
		 *
		 * @param bool  $can_add Old can_add value.
		 * @param int   $product_id Product ID.
		 * @param int   $quantity Quantity.
		 * @param int   $variation_id Variation ID.
		 * @param array $variations Variations.
		 * @param array $item_data Ttem data.
		 *
		 * @return bool
		 */
		public function can_add_product_to_cart( $can_add, $product_id, $quantity, $variation_id = '', $variations = array(), $item_data = array() ) {
			global  $wpdb;
			$is_subscription               = false;
			$s_qty                         = 0;
			$subscription                  = 0;
			$products                      = 0;
			$new_product                   = wc_get_product( $product_id );
			$rebill_subscription_frequency = false;
			if ( $can_add ) {
				$is_subscription = false;
				if ( isset( $_POST['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rebill_nonce'] ) ), 'rebill_nonce' )
				|| isset( $_GET['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rebill_nonce'] ) ), 'rebill_nonce' ) ) {
					$is_subscription = isset( $_GET['is_rebill_subscription'] ) || isset( $_POST['is_rebill_subscription'] );
					if ( isset( $_POST['rebill_subscription_frequency'] ) ) {
						$rebill_subscription_frequency = sanitize_text_field( wp_unslash( $_POST['rebill_subscription_frequency'] ) );
					}
				}
				if ( ! $is_subscription ) {
					if ( 'yes' === $new_product->get_meta( 'rebill', true ) && 'yes' === $new_product->get_meta( 'rebill_only', true ) ) {
						$is_subscription = true;
					}
				}
				foreach ( WC()->cart->cart_contents as $key => $item ) {
					$product = $item['data'];
					if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
						$product = wc_get_product( $product );
					}
					$variation = false;
					if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
						$variation = $product;
						$parent    = $variation->get_parent_id();
						$product   = new WC_Product( $parent );
					}
					//self::log( var_export($product, true) );
					if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
						if ( $is_subscription && (int) $product->get_id() === (int) $product_id ) {
							$s_qty += $item['quantity'] + $quantity;
						}
						if ( 'yes' === $product->get_meta( 'rebill_only', true ) ) {
							self::log( "rebill_only - ".$product->get_id()." $product_id || ! $is_subscription" );
							if ( (int) $product->get_id() !== (int) $product_id || ! $is_subscription ) {
								++$subscription;
								$s_qty += $item['quantity'];
							}
							continue;
						}
						WC_Rebill_Core::check_session();
						$rebill_cart = WC()->session->get( 'rebill_cart', array() );
						if ( ! isset( $rebill_cart[ $product->get_id() ] ) ) {
							if ( (int) $product->get_id() !== (int) $product_id || $is_subscription ) {
								++$products;
							}
						} else {
							if ( (int) $product->get_id() !== (int) $product_id || ! $is_subscription ) {
								++$subscription;
								$s_qty += $item['quantity'];
							}
						}
					} else {
						if ( is_object( $product ) && (int) $product->get_id() !== (int) $product_id || $is_subscription ) {
							++$products;
						}
					}
				}
				$customer_id = get_current_user_id();
				if ( ( $is_subscription && $products > 0 || ! $is_subscription && $subscription > 0 ) && ! $this->mix_cart ) {
					wc_add_notice( __( 'Products and subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
					$can_add = false;
				} elseif ( $is_subscription && ( $this->one_by_cart || $this->one_by_customer ) && $s_qty > 1 ) {
					wc_add_notice( __( 'Multiple subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
					$can_add = false;
				} elseif ( $is_subscription && $this->one_by_customer && $customer_id > 0 ) {
					if ( $wpdb->get_var(
						"SELECT
							MAX(p.ID) FROM {$wpdb->prefix}posts p
						INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
						WHERE p.post_type = 'shop_order'
							AND p.post_status = 'wc-rebill-toclone'
							AND pm.meta_key = '_customer_user'
							AND pm.meta_value = '" . (int) $customer_id . "'"
					) ) {
						wc_add_notice( __( 'Multiple subscriptions can not be purchased at the same time.', 'wc-rebill-subscription' ), 'error' );
						$can_add = false;
					}
				}
			}
			if ( $is_subscription && $can_add ) {
				WC_Rebill_Core::check_session();
				$rebill_cart = WC()->session->get( 'rebill_cart', array() );
				if ( ! isset( $rebill_cart[ $product_id ] ) ) {
					$rebill_cart[ $product_id ] = array(
						'variation' => array(),
						'quantity'  => 0,
					);
				}
				$frequency = $new_product->get_meta( 'rebill_frequency', true );
				if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
					$frequency = array( 1 );
				}
				if ( ! is_array( $frequency ) ) {
					$frequency = array( $frequency );
				}
				$current = $frequency[0];
				if ( $rebill_subscription_frequency ) {
					$c = (int) $rebill_subscription_frequency;
					if ( in_array( $c, $frequency ) ) {
						$current = $c;
					}
				}
				$rebill_cart[ $product_id ]['frequency'] = $current;
				if ( ! empty( $variation_id ) && $variation_id > 0 ) {
					if ( ! isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
						$rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] = 0;
					}
					$rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] += $quantity;
				} else {
					$rebill_cart[ $product_id ]['quantity'] += $quantity;
				}
				WC()->session->set( 'rebill_cart', $rebill_cart );
			}
			return $can_add;
		}
		/**
		 * Filter of remove cart item
		 *
		 * @param string  $cart_item_key Item Key.
		 * @param WC_Cart $cart Cart.
		 *
		 * @return void
		 */
		public function remove_cart_item( $cart_item_key, $cart ) {
			$item    = $cart->cart_contents[ $cart_item_key ];
			$product = $item['data'];
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
				WC_Rebill_Core::check_session();
				$rebill_cart = WC()->session->get( 'rebill_cart', array() );
				if ( isset( $rebill_cart[ $product_id ] ) ) {
					if ( ! empty( $variation_id ) && $variation_id > 0 ) {
						if ( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] === $item['unique_key'] ) {
							if ( isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
								unset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] );
							}
							if ( 0 === count( $rebill_cart[ $product_id ]['variation'] ) ) {
								unset( $rebill_cart[ $product_id ] );
							}
						}
					} else {
						if ( $rebill_cart[ $product_id ]['s_unique_key'] === $item['unique_key'] ) {
							if ( isset( $rebill_cart[ $product_id ] ) ) {
								unset( $rebill_cart[ $product_id ] );
							}
						}
					}
				}
				WC()->session->set( 'rebill_cart', $rebill_cart );
			}
		}
		/**
		 * Filter of change cart item quantity
		 *
		 * @param string  $cart_item_key Item Key.
		 * @param int     $quantity Quantity.
		 * @param int     $old_quantity Old Quantity.
		 * @param WC_Cart $cart Cart.
		 *
		 * @return void
		 */
		public function change_cart_item_quantity( $cart_item_key, $quantity, $old_quantity, $cart ) {
			$item    = $cart->cart_contents[ $cart_item_key ];
			$product = $item['data'];
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
				$is_subscription = ! $this->mix_cart || 'yes' === $product->get_meta( 'rebill_only', true );
				WC_Rebill_Core::check_session();
				$rebill_cart = WC()->session->get( 'rebill_cart', array() );
				if ( isset( $rebill_cart[ $product_id ] ) ) {
					if ( ! empty( $variation_id ) && $variation_id > 0 ) {
						if ( isset( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] ) && $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] === $item['unique_key'] ) {
							if ( isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
									$rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] = $quantity;
							}
							if ( $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] <= 0 ) {
								unset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] );
							}
							if ( 0 === count( $rebill_cart[ $product_id ]['variation'] ) ) {
								unset( $rebill_cart[ $product_id ] );
							}
						}
					} else {
						if ( $rebill_cart[ $product_id ]['s_unique_key'] === $item['unique_key'] ) {
							$rebill_cart[ $product_id ]['quantity'] = $quantity;
							if ( $rebill_cart[ $product_id ]['quantity'] <= 0 ) {
								unset( $rebill_cart[ $product_id ] );
							}
						}
					}
					WC()->session->set( 'rebill_cart', $rebill_cart );
				}
			}
		}
		/**
		 * Filter of cart calculate totals
		 *
		 * @param WC_Cart $cart Cart.
		 *
		 * @return void
		 */
		public function calculate_totals( $cart ) {
			foreach ( $cart->cart_contents as $key => $item ) {
				$product = $item['data'];
				if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
					$product = wc_get_product( $product );
				}
				$variation    = false;
				$variation_id = false;
				if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
					$variation    = $product;
					$variation_id = $variation->get_id();
					$parent       = $variation->get_parent_id();
					$product      = new WC_Product( $parent );
				}
				$product_id = $product->get_id();
				if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
					WC_Rebill_Core::check_session();
					$rebill_cart = WC()->session->get( 'rebill_cart', array() );
					if ( isset( $rebill_cart[ $product->get_id() ] ) ) {
						$r_item = $rebill_cart[ $product->get_id() ];
						if ( isset( $item['unique_key'] ) && $variation_id && isset( $r_item['s_unique_key'][ $variation_id ] ) && $r_item['s_unique_key'][ $variation_id ] === $item['unique_key'] || ! $variation_id && $r_item['s_unique_key'] === $item['unique_key'] ) {
							$new_price = (float) $item['data']->get_meta( 'rebill_price', true );
							if ( $new_price > 0 ) {
								$item['data']->set_price( $new_price );
							}
						} else {
							continue;
						}
						$signup_price = (float) $product->get_meta( 'rebill_signup_price', true );
						if ( $signup_price > 0 ) {
							if ( $variation && isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
								if ( $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] > 0 ) {
									// translators: %1$s Sign-up fee.
									$cart->add_fee( sprintf( __( 'Sign-up fee of "%1$s"', 'woocommerce-rebill' ), $variation->get_name() ), $signup_price * $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'], true, '' );
								}
							} else {
								if ( $rebill_cart[ $product_id ]['quantity'] > 0 ) {
									// translators: %1$s Sign-up fee.
									$cart->add_fee( sprintf( __( 'Sign-up fee of "%1$s"', 'woocommerce-rebill' ), $product->get_name() ), $signup_price * $rebill_cart[ $product_id ]['quantity'], true, '' );
								}
							}
						}
					}
				}
			}
		}
		/**
		 * Filter of cart item data
		 *
		 * @param object $item Item DATA.
		 * @param int    $product_id Product ID.
		 * @param int    $variation_id Variation ID.
		 *
		 * @return object
		 */
		public function add_cart_item_data( $item, $product_id, $variation_id ) {
			$new_product = wc_get_product( $product_id );
			$rebill      = $new_product->get_meta( 'rebill', true );
			$rebill_only = $new_product->get_meta( 'rebill_only', true );
			if ( 'yes' !== $rebill ) {
				return $item;
			}
			WC_Rebill_Core::check_session();
			$rebill_cart  = WC()->session->get( 'rebill_cart', array() );
			$unique_key   = false;
			$s_unique_key = false;
			if ( ! empty( $variation_id ) && (int) $variation_id > 0 ) {
				if ( isset( $rebill_cart[ $product_id ] ) && isset( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] ) && ! empty( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] ) ) {
					$unique_key   = $rebill_cart[ $product_id ]['unique_key'][ $variation_id ];
					$s_unique_key = $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ];
				}
			} else {
				if ( isset( $rebill_cart[ $product_id ] ) && isset( $rebill_cart[ $product_id ]['s_unique_key'] ) && ! empty( $rebill_cart[ $product_id ]['s_unique_key'] ) ) {
					$unique_key   = $rebill_cart[ $product_id ]['unique_key'];
					$s_unique_key = $rebill_cart[ $product_id ]['s_unique_key'];
				}
			}
			if ( ! $unique_key ) {
				$unique_key   = uniqid();
				$s_unique_key = uniqid();
				if ( ! isset( $rebill_cart[ $product_id ] ) ) {
					$rebill_cart[ $product_id ] = array(
						'variation' => array(),
						'quantity'  => 0,
					);
				}
				if ( ! empty( $variation_id ) && (int) $variation_id > 0 ) {
					$rebill_cart[ $product_id ]['unique_key'][ $variation_id ]   = $unique_key;
					$rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] = $s_unique_key;
				} else {
					$rebill_cart[ $product_id ]['unique_key']   = $unique_key;
					$rebill_cart[ $product_id ]['s_unique_key'] = $s_unique_key;
				}
				WC()->session->set( 'rebill_cart', $rebill_cart );
			}
			$is_subscription = false;
			if ( isset( $_POST['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rebill_nonce'] ) ), 'rebill_nonce' )
			|| isset( $_GET['rebill_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['rebill_nonce'] ) ), 'rebill_nonce' ) ) {
				$is_subscription = isset( $_GET['is_rebill_subscription'] ) || isset( $_POST['is_rebill_subscription'] );
			}
			if ( 'yes' === $rebill_only || $is_subscription ) {
				$item['unique_key'] = $s_unique_key;
			} else {
				$item['unique_key'] = $unique_key;
			}
			return $item;
		}
		/**
		 * Filter of checkout cart item quantity.
		 *
		 * @param int    $qty Quantity.
		 * @param array  $item Current item cart.
		 * @param string $cart_item_key Current item Key.
		 *
		 * @return string
		 */
		public function checkout_cart_item_quantity( $qty, $item, $cart_item_key ) {
			$result  = '';
			$product = $item['data'];
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$quantity   = 0;
			$frequency  = 0;
			$product_id = $product->get_id();
			if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
				$is_subscription = ! $this->mix_cart || 'yes' === $product->get_meta( 'rebill_only', true );
				WC_Rebill_Core::check_session();
				$rebill_cart = WC()->session->get( 'rebill_cart', array() );
				if ( isset( $rebill_cart[ $product_id ] ) ) {
					if ( ! empty( $variation_id ) && $variation_id > 0 ) {
						if ( isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
							if ( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] === $item['unique_key'] ) {
								$quantity = $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'];
							}
						}
					} else {
						if ( $rebill_cart[ $product_id ]['s_unique_key'] === $item['unique_key'] ) {
							$quantity = $rebill_cart[ $product_id ]['quantity'];
						}
					}
					if ( $quantity > 0 ) {
						$frequency = $rebill_cart[ $product_id ]['frequency'];
					}
				}
			}
			if ( $quantity > 0 ) {
				if ( ! $frequency ) {
					$frequency = $product->get_meta( 'rebill_frequency', true );
					if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
						$frequency = 1;
					}
					if ( is_array( $frequency ) ) {
						$frequency = $frequency[0];
					}
				}
				$frequency_type = $product->get_meta( 'rebill_frequency_type', true );
				$frequency_max  = (int) $product->get_meta( 'rebill_frequency_max', true );
				$frequency_date = '';
				$result        .= '<br /><b>' . esc_html( __( 'Subscription Quantity', 'wc-rebill-subscription' ) . ': ' . $quantity ) . '</b><br />';
				$result        .= '<b>' . esc_html( __( 'Subscription details', 'wc-rebill-subscription' ) ) . '</b><br />';

				switch ( $frequency_type ) {
					case 'day':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'You pay every %s day', 'You pay every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'month':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'You pay every %s month', 'You pay every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'year':
						// translators: %s: Frequency value.
						$result .= esc_html( sprintf( _n( 'You pay every %s year', 'You pay every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
				}
				if ( $frequency_max ) {
					$frequency_date      = '+' . ( $frequency * $frequency_max ) . ' ' . $frequency_type;
					$frequency_date_sync = '+' . ( $frequency * ( $frequency_max - 1 ) ) . ' ' . $frequency_type;
				}
				$price        = (float) $product->get_meta( 'rebill_price', true );
				$signup_price = (float) $product->get_meta( 'rebill_signup_price', true );
				if ( $price > 0 ) {

					$result .= ' ' . wc_price( $price );
				}
				if ( $signup_price > 0 ) {

					$result .= ' ' . esc_html( __( 'and it has an extra sign-up fee of', 'wc-rebill-subscription' ) ) . ' ' . wc_price( $signup_price );
				}
				$result .= '.';

				$free_trial           = $product->get_meta( 'rebill_free_trial', true );
				$frequency_type_trial = $product->get_meta( 'rebill_frequency_type_trial', true );
				$frequency_trial      = (int) $product->get_meta( 'rebill_frequency_trial', true );

				if ( 'yes' === $free_trial ) {
					$result .= ' ';
					switch ( $frequency_type_trial ) {
						case 'day':
							// translators: %s: Frequency value.
							$result .= esc_html( sprintf( _n( 'Free trial for %s day.', 'Free trial for %s days.', $frequency_trial, 'wc-rebill-subscription' ), $frequency_trial ) );
							break;
						case 'month':
							// translators: %s: Frequency value.
							$result .= esc_html( sprintf( _n( 'Free trial for %s month.', 'Free trial for %s months.', $frequency_trial, 'wc-rebill-subscription' ), $frequency_trial ) );
							break;
					}
				}

				if ( 'month' === $frequency_type ) {
					$synchronization      = (int) $product->get_meta( 'rebill_synchronization', true );
					$synchronization_type = $product->get_meta( 'rebill_synchronization_type', true );
					if ( $synchronization >= 1 && $synchronization <= 27 && 'first_free' === $synchronization_type ) {
						$result .= '<br /><small>' . esc_html( __( 'First payment', 'wc-rebill-subscription' ) ) . ': ';
						$result .= esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						$result .= '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							$result .= '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							$result .= esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							$result .= '</small>';
						}
					} elseif ( $synchronization >= 1 && $synchronization <= 27 && 'proration' === $synchronization_type ) {
						$result .= '<br /><small>' . esc_html( __( 'First payment prorated. Next payment', 'wc-rebill-subscription' ) ) . ': ';
						$result .= esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						$result .= '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							$result .= '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							$result .= esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							$result .= '</small>';
						}
					} elseif ( $synchronization >= 1 && $synchronization <= 27 && 'full_sharge' === $synchronization_type ) {
						$result .= '<br /><small>' . esc_html( __( 'Next payment', 'wc-rebill-subscription' ) ) . ': ';
						$result .= esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						$result .= '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							$result .= '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							$result .= esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							$result .= '</small>';
						}
					} elseif ( ! empty( $frequency_date ) ) {
						$result .= '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
						$result .= esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date, $this->calculate_next_payment( gmdate( 'd' ) ) ) ) );
						$result .= '</small>';
					}
				} elseif ( ! empty( $frequency_date ) ) {
					$result .= '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
					$result .= esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date ) ) );
					$result .= '</small>';
				}
			}
			return $qty . ' ' . $result;
		}
		/**
		 * Filter of cart item name.
		 *
		 * @param array  $item Current item cart.
		 * @param string $cart_item_key Current item Key.
		 *
		 * @return void
		 */
		public function cart_item_name( $item, $cart_item_key ) {
			$product = $item['data'];
			if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
				$product = wc_get_product( $product );
			}
			$variation_id = 0;
			$variation    = false;
			if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
				$variation    = $product;
				$variation_id = $variation->get_id();
				$parent       = $variation->get_parent_id();
				$product      = new WC_Product( $parent );
			}
			$product_id = $product->get_id();
			$quantity   = 0;
			$frequency  = 0;
			if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
				$is_subscription = ! $this->mix_cart || 'yes' === $product->get_meta( 'rebill_only', true );
				WC_Rebill_Core::check_session();
				$rebill_cart = WC()->session->get( 'rebill_cart', array() );
				if ( isset( $rebill_cart[ $product_id ] ) ) {
					if ( ! empty( $variation_id ) && $variation_id > 0 ) {
						if ( isset( $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] ) && $rebill_cart[ $product_id ]['s_unique_key'][ $variation_id ] === $item['unique_key'] ) {
							if ( isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) ) {
								$quantity = $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'];
							}
						}
					} else {
						if ( $rebill_cart[ $product_id ]['s_unique_key'] === $item['unique_key'] ) {
							$quantity = $rebill_cart[ $product_id ]['quantity'];
						}
					}
					if ( $quantity > 0 ) {
						$frequency = $rebill_cart[ $product_id ]['frequency'];
					}
				}
			}
			if ( $quantity > 0 ) {
				if ( ! $frequency ) {
					$frequency = $product->get_meta( 'rebill_frequency', true );
					if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
						$frequency = 1;
					}
					if ( is_array( $frequency ) ) {
						$frequency = $frequency[0];
					}
				}
				$frequency_type = $product->get_meta( 'rebill_frequency_type', true );
				$frequency_max  = (int) $product->get_meta( 'rebill_frequency_max', true );
				$frequency_date = '';
				echo '<br /><b>' . esc_html( __( 'Subscription Quantity', 'wc-rebill-subscription' ) . ': ' . $quantity ) . '</b><br />';
				echo '<b>' . esc_html( __( 'Subscription details', 'wc-rebill-subscription' ) ) . '</b><br />';
				switch ( $frequency_type ) {
					case 'day':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'You pay every %s day', 'You pay every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'month':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'You pay every %s month', 'You pay every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'year':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'You pay every %s year', 'You pay every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
				}
				if ( $frequency_max ) {
					$frequency_date      = '+' . ( $frequency * $frequency_max ) . ' ' . $frequency_type;
					$frequency_date_sync = '+' . ( $frequency * ( $frequency_max - 1 ) ) . ' ' . $frequency_type;
				}
				$price        = (float) $product->get_meta( 'rebill_price', true );
				$signup_price = (float) $product->get_meta( 'rebill_signup_price', true );
				if ( $price > 0 ) {

					echo ' ' . wp_kses( wc_price( $price * $quantity ), wp_kses_allowed_html( 'post' ) );
				}
				if ( $signup_price > 0 ) {

					echo ' ' . esc_html( __( 'and it has an extra sign-up fee of', 'wc-rebill-subscription' ) ) . ' ' . wp_kses( wc_price( $signup_price ), wp_kses_allowed_html( 'post' ) );
				}
				echo '.';

				$free_trial           = $product->get_meta( 'rebill_free_trial', true );
				$frequency_type_trial = $product->get_meta( 'rebill_frequency_type_trial', true );
				$frequency_trial      = (int) $product->get_meta( 'rebill_frequency_trial', true );

				if ( 'yes' === $free_trial ) {
					echo ' ';
					switch ( $frequency_type_trial ) {
						case 'day':
							// translators: %s: Frequency value.
							echo esc_html( sprintf( _n( 'Free trial for %s day.', 'Free trial for %s days.', $frequency_trial, 'wc-rebill-subscription' ), $frequency_trial ) );
							break;
						case 'month':
							// translators: %s: Frequency value.
							echo esc_html( sprintf( _n( 'Free trial for %s month.', 'Free trial for %s months.', $frequency_trial, 'wc-rebill-subscription' ), $frequency_trial ) );
							break;
					}
				}

				if ( 'month' === $frequency_type ) {
					$synchronization      = (int) $product->get_meta( 'rebill_synchronization', true );
					$synchronization_type = $product->get_meta( 'rebill_synchronization_type', true );
					if ( $synchronization >= 1 && $synchronization <= 27 && 'first_free' === $synchronization_type ) {
						echo '<br /><small>' . esc_html( __( 'First payment', 'wc-rebill-subscription' ) ) . ': ';
						echo esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						echo '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							echo '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							echo esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							echo '</small>';
						}
					} elseif ( $synchronization >= 1 && $synchronization <= 27 && 'proration' === $synchronization_type ) {
						echo '<br /><small>' . esc_html( __( 'First payment prorated. Next payment', 'wc-rebill-subscription' ) ) . ': ';
						echo esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						echo '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							echo '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							echo esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							echo '</small>';
						}
					} elseif ( $synchronization >= 1 && $synchronization <= 27 && 'full_sharge' === $synchronization_type ) {
						echo '<br /><small>' . esc_html( __( 'Next payment', 'wc-rebill-subscription' ) ) . ': ';
						echo esc_html( date_i18n( wc_date_format(), $this->calculate_next_payment( $synchronization ) ) );
						echo '</small>';
						if ( ! empty( $frequency_date_sync ) ) {
							echo '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
							echo esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date_sync, $this->calculate_next_payment( $synchronization ) ) ) );
							echo '</small>';
						}
					} elseif ( ! empty( $frequency_date ) ) {
						echo '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
						echo esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date, $this->calculate_next_payment( gmdate( 'd' ) ) ) ) );
						echo '</small>';
					}
				} elseif ( ! empty( $frequency_date ) ) {
					echo '<br /><small>' . esc_html( __( 'Last payment', 'wc-rebill-subscription' ) ) . ': ';
					echo esc_html( date_i18n( wc_date_format(), strtotime( $frequency_date ) ) );
					echo '</small>';
				}
			}
		}
		/**
		 * Filter of available gateways.
		 *
		 * @param array $available_gateways Current available gateways.
		 *
		 * @return array
		 */
		public function available_payment_gateways( $available_gateways ) {
			if ( ! is_admin() ) {
				$is_subscription = false;
				foreach ( WC()->cart->cart_contents as $key => $item ) {
					$product = $item['data'];
					if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) && ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) ) {
						$product = wc_get_product( $product );
					}
					$variation    = false;
					$variation_id = false;
					if ( is_a( $product, 'WC_Product_Variation' ) || is_a( $product, 'WC_Product_Variable' ) ) {
						$variation    = $product;
						$variation_id = $variation->get_id();
						$parent       = $variation->get_parent_id();
						$product      = new WC_Product( $parent );
					}
					$product_id = $product->get_id();
					if ( is_object( $product ) && 'yes' === $product->get_meta( 'rebill', true ) ) {
						WC_Rebill_Core::check_session();
						$rebill_cart = WC()->session->get( 'rebill_cart', array() );
						if ( isset( $rebill_cart[ $product->get_id() ] ) ) {
							if ( $variation && isset( $rebill_cart[ $product_id ]['variation'][ $variation_id ] ) && $rebill_cart[ $product_id ]['variation'][ $variation_id ]['quantity'] > 0 ) {
								$is_subscription = true;
								break;
							} elseif ( ! $variation && $rebill_cart[ $product_id ]['quantity'] > 0 ) {
								$is_subscription = true;
								break;
							}
						}
					}
				}
				if ( $is_subscription ) {
					foreach ( array_keys( $available_gateways ) as $id ) {
						if ( 'rebill-gateway' !== $id ) {
							unset( $available_gateways[ $id ] );
						}
					}
				} elseif ( $this->only_for_sub ) {
					if ( isset( $available_gateways['rebill-gateway'] ) ) {
						unset( $available_gateways['rebill-gateway'] );
					}
				}
			}
			return $available_gateways;
		}
	}
endif;
