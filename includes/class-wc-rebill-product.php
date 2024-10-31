<?php
/**
 * Rebill
 *
 * Product Class
 *
 * @package    Rebill
 * @subpackage WC_Rebill_Product
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! class_exists( 'WC_Rebill_Product' ) ) :
	/**
	 * WooCommerce Rebill Product main class.
	 */
	class WC_Rebill_Product extends WC_Rebill_Core {

		/**
		 * Constructor for the gateway.
		 *
		 * @return void
		 */
		public function __construct() {
			WC_Rebill_Core::__construct();
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'after_add_to_cart_button' ) );
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'before_add_to_cart_form' ), 10000000 );
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'after_add_to_cart_form' ), 1 );
			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_text' ), 10, 2 );
			$this->btn_suscribe = $this->get_option( 'btn_suscribe', __( 'Subscribe to this product', 'wc-rebill-subscription' ) );
			$this->btn_onetime  = $this->get_option( 'btn_onetime', __( 'One-time purchase', 'wc-rebill-subscription' ) );
		}

		/**
		 * Get add to cart text
		 *
		 * @param   int        $var Current text.
		 * @param   WC_Product $product_object Product instance.
		 *
		 * @return int Final text.
		 */
		public function add_to_cart_text( $var, $product_object ) {
			if ( 'yes' === $product_object->get_meta( 'rebill', true ) && 'yes' === $product_object->get_meta( 'rebill_only', true ) ) {
				return $this->btn_suscribe;
			} elseif ( 'yes' === $product_object->get_meta( 'rebill', true ) ) {
				return $this->btn_onetime;
			}
			return $var;
		}

		/**
		 * Hook after add to cart button.
		 *
		 * @return void
		 */
		public function after_add_to_cart_button() {
			global $product;
		}

		/**
		 * Hook before add to cart button.
		 *
		 * @return void
		 */
		public function before_add_to_cart_form() {
			global $product;
			if ( 'yes' === $product->get_meta( 'rebill', true ) ) {
				echo '<div class="rebill_product_wc_form">';
			}
		}
		/**
		 * Hook after add to cart form.
		 *
		 * @return void
		 */
		public function after_add_to_cart_form() {
			global $product;
			if ( 'yes' === $product->get_meta( 'rebill', true ) ) {
				echo '</div>';
				$frequency      = $product->get_meta( 'rebill_frequency', true );
				$frequency_type = $product->get_meta( 'rebill_frequency_type', true );
				if ( ! $frequency || is_array( $frequency ) && ! count( $frequency ) ) {
					$frequency = array( 1 );
				}
				if ( ! is_array( $frequency ) ) {
					$frequency = array( $frequency );
				}
				foreach ( $frequency as $f ) {
					if ( (int) $f < 1 ) {
						return;
					}
				}
				echo '<div class="rebill_product_form">';
				$price = (float) $product->get_meta( 'rebill_price', true );
				?>
				<div class="rebill_multi_frequency">
					<div class="rebill_multi_freq_content">
						<b>
						<?php
						if ( $price > 0 ) {
							// translators: %s: Price value.
							echo wp_kses( sprintf( __( 'Subscribe to this product for %s:', 'wc-rebill-subscription' ), wc_price( $price ) ), wp_kses_allowed_html( 'post' ) );
						} else {
							echo esc_html( __( 'Subscribe to this product:', 'wc-rebill-subscription' ) );
						}
						?>
						</b><br />
						<?php
						echo esc_html( __( 'Delivery this', 'wc-rebill-subscription' ) ) . ' ';

						if ( count( $frequency ) > 1 ) {
							?>
						<select class="rebill_subscription_frequency" onchange="changeRebillFrequency(this);">
							<?php
							foreach ( $frequency as $f ) {
								echo '<option value="' . (int) $f . '">';
								switch ( $frequency_type ) {
									case 'day':
										// translators: %s: Frequency value.
										echo esc_html( sprintf( _n( 'Every %s day', 'Every %s days', $f, 'wc-rebill-subscription' ), $f ) );
										break;
									case 'month':
										// translators: %s: Frequency value.
										echo esc_html( sprintf( _n( 'Every %s month', 'Every %s months', $f, 'wc-rebill-subscription' ), $f ) );
										break;
									case 'year':
										// translators: %s: Frequency value.
										echo esc_html( sprintf( _n( 'Every %s year', 'Every %s years', $f, 'wc-rebill-subscription' ), $f ) );
										break;
								}
								echo '</option>';
							}
							?>
						</select>
							<?php
						} else {
							switch ( $frequency_type ) {
								case 'day':
									// translators: %s: Frequency value.
									echo esc_html( sprintf( _n( 'every %s day', 'every %s days', $frequency[0], 'wc-rebill-subscription' ), $frequency[0] ) );
									break;
								case 'month':
									// translators: %s: Frequency value.
									echo esc_html( sprintf( _n( 'every %s month', 'every %s months', $frequency[0], 'wc-rebill-subscription' ), $frequency[0] ) );
									break;
								case 'year':
									// translators: %s: Frequency value.
									echo esc_html( sprintf( _n( 'every %s year', 'every %s years', $frequency[0], 'wc-rebill-subscription' ), $frequency[0] ) );
									break;
							}
						}
						?>
					</div>
					<form method="POST" class="is_rebill_subscription">
						<input name="is_rebill_subscription" value="1" type="hidden" />
						<input name="rebill_nonce" value="<?php echo esc_html( wp_create_nonce('rebill_nonce') ); ?>" type="hidden" />
						<input name="add-to-cart" value="<?php echo esc_html( $product->get_id() ); ?>" type="hidden" />
						<div class="rebill_other_inputs">

						</div>
						<button onclick="return submitRebillSubscriptionForm(this);" type="button" class="subscription_add_to_cart_button button alt" name="add-to-cart-subscription" value="<?php echo esc_html( $product->get_id() ); ?>"><?php echo esc_html( $this->btn_suscribe ); ?></button>
					</form>
				</div>
				<style>
					.rebill_multi_frequency {
						background: #eaeaea;
						padding: 15px;
						border-radius: 10px;
						text-align: center;
						line-height: 30px;
					}
					<?php
					if ( 'yes' === $product->get_meta( 'rebill_only', true ) ) {
						echo '.rebill_product_wc_form .quantity, .rebill_product_wc_form [type=submit] { display: none !important; }';
					}
					?>
				</style>
				<?php
				$frequency_max  = (int) $product->get_meta( 'rebill_frequency_max', true );
				$frequency_date = '';
				$frequency      = $frequency[0];
				echo '<br /><b>' . esc_html( __( 'Subscription details', 'wc-rebill-subscription' ) ) . '</b><br />' . esc_html( __( 'You pay', 'wc-rebill-subscription' ) ) . ' <span class="subscription_detail_frequency">';
				switch ( $frequency_type ) {
					case 'day':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'every %s day', 'every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'month':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'every %s month', 'every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
					case 'year':
						// translators: %s: Frequency value.
						echo esc_html( sprintf( _n( 'every %s year', 'every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
						break;
				}
				echo '</span>';
				if ( $frequency_max ) {
					$frequency_date      = '+' . ( $frequency * $frequency_max ) . ' ' . $frequency_type;
					$frequency_date_sync = '+' . ( $frequency * ( $frequency_max - 1 ) ) . ' ' . $frequency_type;
				}
				$signup_price = (float) $product->get_meta( 'rebill_signup_price', true );
				if ( $price > 0 ) {
					echo ' ' . wp_kses( wc_price( $price ), wp_kses_allowed_html( 'post' ) );
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
				echo '</div>';
			}
		}
	}
endif;
