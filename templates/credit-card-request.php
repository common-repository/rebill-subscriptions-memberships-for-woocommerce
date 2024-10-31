<?php
/**
 * Rebill My Account page
 *
 * @package    Rebill
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_first = true;
if ( $list_cards && count( $list_cards ) > 0 ) {
	?>
<div id="card-olds">
	<b><?php echo esc_html( __( 'Select one credit card', 'wc-rebill-subscription' ) ); ?>:</b><br />
	<?php
	foreach ( $list_cards as $card ) {
		echo '<div class="card-old ' . ( $is_first ? 'card-selected' : '' ) . '">
			<input type="radio" name="rebill_card_id" class="rebill_card_id" value="' . esc_html( $card['id'] ) . '" ' . ( $is_first ? 'checked' : '' ) . ' style="display:none" />
			<div class="card-old-number">' . esc_html( $card['first_six_digits'] ) . '-*****-' . esc_html( $card['last_four_digits'] ) . '</div>
			<div class="card-old-expiration">' . esc_html( $card['expiration_month'] ) . '/' . esc_html( $card['expiration_year'] ) . '</div>
			<div class="card-old-img">' . ( isset( $card['secure_thumbnail'] ) && ! empty( $card['secure_thumbnail'] ) ? '<img src="' . esc_html( $card['secure_thumbnail'] ) . '" />' : '<span class="cc-types__img--' . esc_html( $card['payment_method_id'] ) . '"></span>' ) . '</div>
			<br style="clear:both" />
		</div>';
		$is_first = false;
	}
	?>
	<br />
	<a href="javascript:void(0)" onclick="jQuery('#card-olds').hide();jQuery('#card-form-container').show();jQuery('#card-form-container .rebill_card_id').prop('checked', true);"><?php echo esc_html( __( 'Add new credit card', 'wc-rebill-subscription' ) ); ?></a>
</div>
<?php } ?>
<div id="card-form-container"
<?php
if ( $list_cards && count( $list_cards ) > 0 ) {
	echo 'style="display:none"';
}
?>
>
<?php
if ( $list_cards && count( $list_cards ) > 0 ) {
	?>
	<a href="javascript:void(0)" onclick="jQuery('#card-olds').show();jQuery('#card-form-container').hide();jQuery('#card-olds >div').first().trigger('click');"><?php echo esc_html( __( 'Use old credit card', 'wc-rebill-subscription' ) ); ?></a>
	<?php
}
?>
	<div id="card-front">
		<input type="radio" name="rebill_card_id" class="rebill_card_id" value="" <?php echo ( $is_first ? 'checked' : '' ); ?> style="display:none" />
		<div id="shadow"></div>
		<div id="card-head-container">
			<span id="amount"><?php echo esc_html( __( 'Paying', 'wc-rebill-subscription' ) ); ?>: <strong><?php echo wp_kses( wc_price( $order_total ), wp_kses_allowed_html( 'post' ) ); ?></strong></span>
			<span id="card-image">
				<div class="cc-types">
					<img class="cc-types__img cc-types__img--amex">
					<img class="cc-types__img cc-types__img--visa">
					<img class="cc-types__img cc-types__img--mastercard">
					<!-- <img class="cc-types__img cc-types__img--disc"> -->
					<img class="cc-types__img cc-types__img--genric">
				</div>
			</span>
		</div>
		<!--- end card image container --->
		<label for="card-number">
			<?php echo esc_html( __( ' Card Number', 'wc-rebill-subscription' ) ); ?>
		</label>
		<input type="text" id="card-number" placeholder="1234 5678 9101 1112" maxlength="19">
		<div id="cardholder-container">
			<label for="card-holder"><?php echo esc_html( __( 'Card Holder', 'wc-rebill-subscription' ) ); ?>
		</label>
			<input type="text" id="card-holder" placeholder="<?php echo esc_html( __( 'e.g. John Doe', 'wc-rebill-subscription' ) ); ?>" />
		</div>
		<!--- end card holder container --->
		<div id="exp-container">
			<label for="card-exp">
				<?php echo esc_html( __( 'Expiration', 'wc-rebill-subscription' ) ); ?>
			</label>
			<input id="card-month" type="password" placeholder="MM" maxlength="2">
			<input id="card-year" type="password" placeholder="YY" maxlength="2">
		</div>
		<div id="cvc-container">
			<label for="card-cvc"> <?php echo esc_html( __( 'CVC/CVV', 'wc-rebill-subscription' ) ); ?></label>
			<input id="card-cvc" placeholder="XXX" type="password" min-length="3" maxlength="4">
			<p id="card-is-other"><?php echo esc_html( __( 'Last 3 digits', 'wc-rebill-subscription' ) ); ?></p>
			<p id="card-is-amex" style="display:none"><?php echo esc_html( __( 'Last 4 digits', 'wc-rebill-subscription' ) ); ?></p>
		</div>
		<!--- end CVC container --->
		<!--- end exp container --->
	</div>
	<!--- end card front --->
	<div id="card-back">
		<div id="card-rebill">
		</div>
	</div>
</div>
<br /><br />
<button class="btn btn-default" id="theRebillButton" onclick="runRebillPayment(this)" type="button"><?php echo esc_html( __( 'Pay Now', 'wc-rebill-subscription' ) ); ?></button>
<script>
	var ccOld = document.querySelectorAll('div.card-old'),
		ccNumberInput = document.querySelector('#card-number'),
		ccNumberPattern = /^\d{0,16}$/g,
		ccNumberSeparator = " ",
		ccNumberInputOldValue,
		ccNumberInputOldCursor,
		checkSeparator = function (position, interval) { return Math.floor(position / (interval + 1)); },
		mask = function(value, limit, separator) {
			var output = [];
			for (var i = 0; i < value.length; i++) {
				if ( i !== 0 && i % limit === 0) {
					output.push(separator);
				}
				output.push(value[i]);
			}
			return output.join("");
		},
		unmask = function(value) { return value.replace(/[^\d]/g, '') },
		ccNumberInputKeyDownHandler = function(e) {
			var el = e.target;
			ccNumberInputOldValue = el.value;
			ccNumberInputOldCursor = el.selectionEnd;
		},
		ccNumberInputInputHandler = function(e) {
			var el = e.target,
					newValue = unmask(el.value),
					newCursorPosition;
			if ( newValue.match(ccNumberPattern) ) {
				newValue = mask(newValue, 4, ccNumberSeparator);
				newCursorPosition =
					ccNumberInputOldCursor - checkSeparator(ccNumberInputOldCursor, 4) +
					checkSeparator(ccNumberInputOldCursor + (newValue.length - ccNumberInputOldValue.length), 4) +
					(unmask(newValue).length - unmask(ccNumberInputOldValue).length);
				el.value = (newValue !== "") ? newValue : "";
			} else {
				el.value = ccNumberInputOldValue;
				newCursorPosition = ccNumberInputOldCursor;
			}
			el.setSelectionRange(newCursorPosition, newCursorPosition);
			highlightCC(el.value);
		},
		highlightCC = function (ccValue) {
			var ccCardType = '',
					ccCardTypePatterns = {
						amex: /^3/,
						visa: /^4/,
						mastercard: /^5/,
						//disc: /^6/,
						genric: /(^1|^2|^7|^8|^9|^0|^6)/,
					};
			for (const cardType in ccCardTypePatterns) {
				if ( ccCardTypePatterns[cardType].test(ccValue) ) {
					ccCardType = cardType;
					if (cardType == 'amex') {
						jQuery('#card-is-other').hide();
						jQuery('#card-is-amex').show();
						jQuery('#card-cvc').attr('placeholder', 'XXXX');
					} else {
						jQuery('#card-cvc').attr('placeholder', 'XXX');
						jQuery('#card-is-other').show();
						jQuery('#card-is-amex').hide();
					}
					break;
				}
			}
			var activeCC = document.querySelector('.cc-types__img--active'),
					newActiveCC = document.querySelector(`.cc-types__img--${ccCardType}`);
			if (activeCC) activeCC.classList.remove('cc-types__img--active');
			if (newActiveCC) newActiveCC.classList.add('cc-types__img--active');
		};
	ccNumberInput.addEventListener('keydown', ccNumberInputKeyDownHandler);
	ccNumberInput.addEventListener('input', ccNumberInputInputHandler);
	Array.from(ccOld).forEach(function(el) {
		el.addEventListener('click', function(e) {
			var $ = jQuery;
			$('.card-old').removeClass('card-selected');
			$(e.currentTarget).addClass('card-selected');
			jQuery('.rebill_card_id', e.currentTarget).prop('checked', true);
		});
	});
	var subscriptions = [];
	var onetime = false;
	<?php
	$shipping_ready = false;
	if ( $rebill_subscription_data && is_array( $rebill_subscription_data ) && count( $rebill_subscription_data ) > 0 ) {
		foreach ( $rebill_subscription_data as $hash => $s_data ) {
			$total = $s_data['total'];
			if ( ! $shipping_ready ) {
				$shipping_ready = true;
				$total         += $s_data['shipping_cost'];
			}
			$total        = min( $total, $order_total );
			$order_total -= $total;
			?>


			subscriptions.push({
				amount: <?php echo esc_html( round( $total, 2 ) ); ?>,
				currency: "<?php echo esc_html( get_woocommerce_currency() ); ?>",
				externalReference: "<?php echo esc_html( $order_id . '-' . $hash ); ?>",
				<?php
				// translators: %s ID.
				echo 'title: "' . esc_html( sprintf( __( 'Order Number %1$s: %2$s', 'wc-rebill-subscription' ), $order_id, $title_sub[ $order_id . '-' . $hash ] ) ) . '",';
				// translators: %s ID.
				echo 'description: "' . esc_html( sprintf( __( 'Order Number %1$s: %2$s', 'wc-rebill-subscription' ), $order_id, $title_sub[ $order_id . '-' . $hash ] ) ) . '",';
				?>
				frequency: <?php echo esc_html( $s_data['frequency'] ); ?>,
				frequencyType: "<?php echo esc_html( $s_data['frequency_type'] ); ?>s",
				<?php
				if ( isset( $s_data['frequency_max'] ) && $s_data['frequency_max'] > 0 ) {
					echo 'repetitions: ' . (int) $s_data['frequency_max'] . ', ';
				}
				if ( isset( $s_data['free_trial'] ) && 'yes' === $s_data['free_trial'] && isset( $s_data['frequency_trial'] ) && (int) $s_data['frequency_trial'] > 0 ) {
					echo 'free_trial_frequency: ' . (int) $s_data['frequency_trial'] . ', ';
					echo 'free_trial_frequency_type: "' . esc_html( $s_data['frequency_type_trial'] ) . 's", ';
				}
				if ( isset( $s_data['synchronization'] ) && (int) $s_data['synchronization'] > 0 && (int) $s_data['synchronization'] <= 27 ) {
					echo 'debitDate: ' . (int) $s_data['synchronization'] . ', ';
				}
				?>
			});


			<?php
		}
	}
	if ( $order_total > 0 ) {
		?>
		onetime = {
			amount: <?php echo esc_html( round( $order_total, 2 ) ); ?>,
			currency: "<?php echo esc_html( get_woocommerce_currency() ); ?>",
			<?php
			// translators: %s: ID.
			echo 'description: "' . esc_html( sprintf( __( 'Order Number %1$s: %2$s', 'wc-rebill-subscription' ), $order_id, $title ) ) . '",';
			?>
			externalReference: "<?php echo esc_html( $order_id ); ?>",
		};
		<?php
	}
	?>
	var isRebillRunning  =  false;
	var rebillError = false;
	var current_subscriptions = 0;
	var ok_subscriptions = 0;
	var ok_onetime = 0;
	var payments = [];
	var callbackRebillNext = function(result) {
		if (result) {
			payments.push(result);
			++ok_subscriptions;
		}
		++current_subscriptions;
		if (subscriptions.length > current_subscriptions) {
			executeRebillPayment(subscriptions[current_subscriptions], false, callbackRebillNext);
		} else {
			subscriptionsRebillReady();
		}
	}
	function runRebillPayment(btn) {
		if (isRebillRunning) return;
		isRebillRunning = true;
		jQuery(btn).html('<?php echo esc_html( __( 'Please wait...', 'wc-rebill-subscription' ) ); ?>').prop('disabled', true);
		current_subscriptions = 0;
		ok_subscriptions = 0;
		ok_onetime = 0;
		rebillError = false;
		payments = [];
		if (subscriptions.length) {
			executeRebillPayment(subscriptions[0], false, callbackRebillNext);
		} else {
			subscriptionsRebillReady();
		}
	}
	function subscriptionsRebillReady() {
		if (onetime) {
			executeRebillPayment(onetime, true, function(result) {
				if (result) {
					payments.push(result);
					++ok_onetime;
				}
				allRebillReady();
			});
		} else {
			allRebillReady();
		}
	}
	function allRebillReady() {
		if (payments.length == 0) {
			isRebillRunning  =  false;
			jQuery('#theRebillButton').html('<?php echo esc_html( __( 'Pay Now', 'wc-rebill-subscription' ) ); ?>').prop('disabled', false);
			if (rebillError)
				alert(rebillError);
			else
				alert('<?php echo esc_html( __( 'Payment rejected, please verify your card details or contact your bank.', 'wc-rebill-subscription' ) ); ?>');
		} else {
			jQuery.ajax({
				type: "POST",
				url: '<?php echo esc_url( rtrim( home_url(), '/' ) . '/?nonce=' . wp_create_nonce( 'rebill_client_response' ) . '&rebill_client_response=' . $order_id ); ?>'.replaceAll('&#038;', '&').replaceAll('&amp;', '&'),
				data: JSON.stringify(payments),
				success: function(result) {
					try {
						result = JSON.parse(result);
						console.log(result);
						if (result.status) {
							document.location.href = result.status;
						} else {
							isRebillRunning  =  false;
							jQuery('#theRebillButton').html('<?php echo esc_html( __( 'Pay Now', 'wc-rebill-subscription' ) ); ?>').prop('disabled', false);
							alert('<?php echo esc_html( __( 'Internal server error, please do not retry payment without first contacting a store administrator.', 'wc-rebill-subscription' ) ); ?>');
						}
					} catch (e) {
						isRebillRunning  =  false;
						jQuery('#theRebillButton').html('<?php echo esc_html( __( 'Pay Now', 'wc-rebill-subscription' ) ); ?>').prop('disabled', false);
						alert('<?php echo esc_html( __( 'Internal server error, please do not retry payment without first contacting a store administrator.', 'wc-rebill-subscription' ) ); ?>');
					}
				},
				error: function() {
					isRebillRunning  =  false;
					jQuery('#theRebillButton').html('<?php echo esc_html( __( 'Pay Now', 'wc-rebill-subscription' ) ); ?>').prop('disabled', false);
					alert('<?php echo esc_html( __( 'Internal server error, please do not retry payment without first contacting a store administrator.', 'wc-rebill-subscription' ) ); ?>');
				}
			});
		}
	}
	window.current_rebill_callback = false;
	function executeRebillPayment(data, is_onetime, callback) {
		if (typeof Rebill == 'undefined') {
			var scriptTag = document.createElement('script');
			scriptTag.src = "https://sdk.rebill.to/rebill.min.js";
			scriptTag.onload = function() {
				executeRebillPayment(data, is_onetime, callback);
			};
			document.body.appendChild(scriptTag);
			return;
		}
		var card_id = jQuery('.rebill_card_id:checked').val();
		if (!card_id) {
			var number = jQuery('#card-number').val();
			var month = jQuery('#card-month').val();
			var year = jQuery('#card-year').val();
			var cvc = jQuery('#card-cvc').val();
			var holder = jQuery('#card-holder').val();

			if (number.length < 18) {
				rebillError = '<?php echo esc_html( __( 'Invalid credit card number', 'wc-rebill-subscription' ) ); ?>';
				return callback(false);
			}
			if (month*1 < 1 || month*1 > 12) {
				rebillError = '<?php echo esc_html( __( 'Invalid expiration month', 'wc-rebill-subscription' ) ); ?>';
				return callback(false);
			}
			if (year*1 < <?php echo (int) gmdate( 'y' ); ?>) {
				rebillError = '<?php echo esc_html( __( 'Invalid expiration year', 'wc-rebill-subscription' ) ); ?>';
				return callback(false);
			}
			if (cvc.length < 3 || cvc.length > 4) {
				rebillError = '<?php echo esc_html( __( 'Invalid cvc/cvv number', 'wc-rebill-subscription' ) ); ?>';
				return callback(false);
			}
			if (holder.length < 3) {
				rebillError = '<?php echo esc_html( __( 'Invalid card holder', 'wc-rebill-subscription' ) ); ?>';
				return callback(false);
			}
		}

		const rebill = new Rebill({
			orgUuid: "<?php echo esc_html( $org_uuid ); ?>",
			customApiUrl: "<?php echo esc_url( $endpoint ); ?>/",
		});
		rebill.setCustomer(<?php echo wp_json_encode( $customer ); ?>);
		if (is_onetime) {
			rebill.setOneTimePaymentFlow(data);
		} else {
			rebill.setSubscriptionOnTheFlyFlow(data);
		}
		if (card_id) {
			rebill.setCard({
				card_id: card_id
			});
		} else {
			rebill.setCard({
				cardNumber: jQuery('#card-number').val(),
				expirationDate: jQuery('#card-month').val()+"/"+jQuery('#card-year').val(),
				cvcCode: jQuery('#card-cvc').val(),
				cardholderName: jQuery('#card-holder').val(),
				<?php if (!empty($customer['personalID_type'])) { ?>
				documentType: "<?php echo esc_attr( $customer['personalID_type'] ); ?>",
				documentNumber: "<?php echo esc_attr( $customer['personalID_number'] ); ?>",
				<?php } ?>
			});
		}
		rebill.onProcessSuccess(function(response) {
			console.log('onProcessSuccess:', response);
			if (response && response.success) {
				window.current_rebill_callback(response);
			} else {
				jQuery.ajax({
					type: "POST",
					url: '<?php echo esc_url( rtrim( home_url(), '/' ) . '/?nonce=' . wp_create_nonce( 'rebill_client_response_error' ) . '&rebill_client_response_error=' . $order_id ); ?>'.replaceAll('&#038;', '&').replaceAll('&amp;', '&'),
					data: JSON.stringify(payments),
					success: function(result) {
						console.log('Error sending: ', result);
					},
					error: function() {
						console.log('Error on error: ', result);
					}
				});
				window.current_rebill_callback(false);
			}
		});
		rebill.onProcessFailed(function(response) {
			window.current_rebill_callback(false);
			jQuery.ajax({
				type: "POST",
				url: '<?php echo esc_url( rtrim( home_url(), '/' ) . '/?nonce=' . wp_create_nonce( 'rebill_client_response_error' ) . '&rebill_client_response_error=' . $order_id ); ?>'.replaceAll('&#038;', '&').replaceAll('&amp;', '&'),
				data: JSON.stringify(response),
				success: function(result) {
					console.log('Error sending: ', result);
				},
				error: function() {
					console.log('Error on error: ', result);
				}
			});
			console.log('onProcessFailed:', response);
		});
		window.current_rebill_callback = callback;
		rebill.submitTransaction();
	}
</script>

<style>
	.card-old-img img {
		max-height: 19px;
		height: auto;
		max-width: 100%;
	}
	.card-old-img span {
		height: 25px;
		display: block;
		width: 25px;
		margin: auto;
	}
	.card-old  {
		width: 100%;
		max-width: 500px;
		border: 1px solid black;
		border-radius: 8px;
		padding: 5px 10px;
		margin: 5px;
		cursor: pointer;
	}
	.card-old > div {
		width: 33.33%;
		float: left;
		text-align: center;
	}
	.card-old.card-selected {
		-webkit-box-shadow: 0px 0px 11px 2px rgba(0,0,0,0.48);
		box-shadow: 0px 0px 11px 2px rgba(0,0,0,0.48);
	}
	.card-old:hover, .card-old.card-selected {
		background: #eaeaea;
		font-weight: bold;
	}
	#amount {
		font-size: 12px;
	}

	#amount strong {
		font-size: 14px;
	}

	#card-back {
		top: 40px;
		right: 0;
		z-index: -2;
	}
	#card-cvc {
		width: 60px;
		margin-bottom: 0;
	}

	#card-front,
	#card-back {
		position: absolute;
		background-color: #eee;
		width: 390px;
		height: 250px;
		border-radius: 6px;
		padding: 20px 30px 0;
		box-sizing: border-box;
		font-size: 10px;
		letter-spacing: 1px;
		font-weight: 300;
		color: black;
	}

	#card-head-container {
		height: 50px;
	}

	#card-image {
		float: right;
		width: 50%;
	}

	#card-image i {
		font-size: 40px;
	}

	#card-month {
		width: 45% !important;
	}

	#card-number,
	#card-holder {
		width: 100%;
	}

	#card-rebill {
		width: 100%;
		height: 55px;
		background-color: #3d5266;
		position: absolute;
		right: 0;
	}

	#card-success {
		color: #00b349;
	}

	#card-token {
		display: none;
	}

	#card-year {
		width: 45%;
		float: right;
	}

	#cardholder-container {
		width: 60%;
		display: inline-block;
	}

	#cvc-container {
		position: absolute;
		width: 110px;
		right: -115px;
		bottom: -30px;
		padding-left: 20px;
		box-sizing: border-box;
	}

	#cvc-container label {
		width: 100%;
	}

	#cvc-container p {
		font-size: 6px;
		text-transform: uppercase;
		opacity: inherit;
		letter-spacing: .5px;
	}

	#card-form-container {
		width: 500px;
		height: 290px;
		position: relative;
	}

	#form-errors {
		color: #eb0000;
	}

	#form-errors,
	#card-success {
		background-color: white;
		width: 500px;
		margin: 0 auto 10px;
		height: 50px;
		border-radius: 8px;
		padding: 0 20px;
		font-weight: 400;
		box-sizing: border-box;
		line-height: 46px;
		letter-spacing: .5px;
		text-transform: none;
	}

	#form-errors p,
	#card-success p {
		margin: 0 5px;
		display: inline-block;
	}

	#exp-container {
		margin-left: 10px;
		width: 32%;
		display: inline-block;
		float: right;
	}

	.hidden {
		display: none;
	}

	#image-container {
		width: 100%;
		position: relative;
		height: 55px;
		margin-bottom: 5px;
		line-height: 55px;
	}

	#card-form-container input {
		border: none;
		outline: none;
		background-color: #424c5a;
		height: 30px;
		line-height: 30px;
		padding: 0 10px;
		margin: 0 2px 25px;
		color: white;
		font-size: 10px;
		box-sizing: border-box;
		border-radius: 4px;
		font-family: lato, 'helvetica-light', 'sans-serif';
		letter-spacing: .7px;
		float: left;
	}

	#card-form-container input::-webkit-input-placeholder {
		color: #fff;
		opacity: 0.7;
		font-family: lato, 'helvetica-light', 'sans-serif';
		letter-spacing: 1px;
		font-weight: 300;
		letter-spacing: 1px;
		font-size: 10px;
	}

	#card-form-container input:-moz-placeholder {
		color: #fff;
		opacity: 0.7;
		font-family: lato, 'helvetica-light', 'sans-serif';
		letter-spacing: 1px;
		font-weight: 300;
		letter-spacing: 1px;
		font-size: 10px;
	}

	#card-form-container input::-moz-placeholder {
		color: #fff;
		opacity: 0.7;
		font-family: lato, 'helvetica-light', 'sans-serif';
		letter-spacing: 1px;
		font-weight: 300;
		letter-spacing: 1px;
		font-size: 10px;
	}

	#card-form-container input:-ms-input-placeholder {
		color: #fff;
		opacity: 0.7;
		font-family: lato, 'helvetica-light', 'sans-serif';
		letter-spacing: 1px;
		font-weight: 300;
		letter-spacing: 1px;
		font-size: 10px;
	}

	#card-form-container input.invalid {
		border: solid 2px #eb0000;
		height: 34px;
	}

	#card-form-container label {
		display: block;
		margin: 0 auto 7px;
	}

	#shadow {
		position: absolute;
		right: 0;
		width: 284px;
		height: 214px;
		top: 36px;
		background-color: #000;
		z-index: -1;
		border-radius: 8px;
		box-shadow: 3px 3px 0 rgba(0, 0, 0, 0.1);
		-moz-box-shadow: 3px 3px 0 rgba(0, 0, 0, 0.1);
		-webkit-box-shadow: 3px 3px 0 rgba(0, 0, 0, 0.1);
	}
	@media only screen and (max-width: 530px) {
		#cvc-container {
			position: absolute;
			width: 170px;
			left: 0;
			bottom: -170px;
			padding-left: 20px;
			box-sizing: border-box;
		}
		#card-front, #card-back {
			width: 100%;
		}
		#card-back {
			display:  none;
		}
		#shadow {
			display: none;
		}
		#card-form-container {
			width: 100%;
			height: 425px;
			position: relative;
		}
	}

	.cc-types__img {
		display: inline-block;
		margin-left: -4px;
		width: 25%;
		vertical-align: middle;

		filter: sepia(.3) contrast(1.1) brightness(1.1) grayscale(1);
	}

	.cc-types {
		text-align: right;
	}

	.cc-types__img--active {
		filter: none;
	}

	.cc-types__img {
		content: url(data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDkwIiBoZWlnaHQ9IjM4MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4gPGc+ICA8dGl0bGU+YmFja2dyb3VuZDwvdGl0bGU+ICA8cmVjdCBmaWxsPSJub25lIiBpZD0iY2FudmFzX2JhY2tncm91bmQiIGhlaWdodD0iMzgyIiB3aWR0aD0iNDkyIiB5PSItMSIgeD0iLTEiLz4gPC9nPiA8Zz4gIDx0aXRsZT5MYXllciAxPC90aXRsZT4gIDxwYXRoIGlkPSJzdmdfNCIgZmlsbD0iIzVmZDRhZiIgZD0ibTEzMC41NzAxMjMsOTkuNzA0Nzc3bDIzMC41ODQsMGMwLDAgMTUuMjY0LDAuOTk2IDE1Ljk4NywxNS45NzhsMCwxMzguMTMyYzAsMCAwLjk5MSwxNC42OTYgLTEzLjcwNSwxNS40MWwtMjMyLjg2NSwwLjU2OGMwLDAgLTE1Ljk3OSwxLjE0NiAtMTUuOTc5LC0xNS45NzhsMC41NiwtMTM4LjEzMmMtMC4wMDEsMCAtMS41NiwtMTMuMjY5IDE1LjQxOCwtMTUuOTc4eiIvPiAgPHBhdGggaWQ9InN2Z181IiBmaWxsPSIjREVFNEU3IiBkPSJtMTc3LjIyMzEyMywxMjguNTQ1Nzc3YzAuNzQ4LC0xIDIuMjAxLC0wLjU2IDEuNDY2LC0zLjE5MmMtMC42MTYsMC4wNzcgLTEuMjI3LDAuMTY3IC0xLjg0NiwwLjI1MmMtMS4yMTQsMS4yOTkgMC42OTIsMC4zMDggLTAuOTc1LDAuOTc1Yy0wLjA2MywxLjM1IDAuODM0LDAuODI0IDEuMzU1LDEuOTY1em0tMC41LDIuMjFjMC40NzksMC4yNjkgLTAuMTQ5LDEuNjg4IDEuNzE4LDAuNDg3Yy0wLjcsLTEuMzE2IC0xLjIzOSwtMC4yMTggLTEuNzE4LC0wLjQ4N3ptLTYuMjYsLTUuNzc4YzEuODY3LC0wLjAyMSAzLjAwOSwtMC45MzIgNC45MSwtMC40ODNjLTIuMDc3LC0yLjg1NCAtMy4xNDUsLTIuNDAyIC02LjEyOCwtMC45NzhjLTEuMDg2LDIuMzkzIDAuNzA5LDAuMzYzIDEuMjE4LDEuNDYxem0yLjQ1NywtNC4wNDNjMC40MDYsLTEuNzUyIC0wLjY3NSwwLjA2NCAtMS40NzksLTAuNjExYzAuNDQ5LDAuMzkzIC0xLjE1NCwyLjA3MyAxLjQ3OSwwLjYxMXptMjUuNTk0LDMzLjcyN2MxLjYyNCwwLjUzNCAyLjY4OCwwLjg3NiAzLjI0MywwLjkxNGMwLjgyNSwtMy4yNzggLTAuNDY2LC0zLjAwNCAtMS45MDEsLTMuNDM2Yy0wLjQxNSwxLjI4NiAtMS40OTIsMC43MjcgLTEuMzQyLDIuNTIyem0tMjEuMjkxLC0zNC43MWMtMC43MzEsMS4wNiAxLjcyMiwwLjU1MSAxLjgzNywwLjQ5MWMtMS4xMjgsLTEuMDA0IC0xLjMwMywtMS4yMzkgLTIuNzA5LC0xLjk2NmMtMS4xMSwxLjA4NyAwLjYyLDEuODE3IDAuODcyLDEuNDc1em0wLjExNiwyLjgyNWMwLjAxNywtMC4yODIgLTIuMzA0LDAuMzE2IDEuODM3LDAuOTg3YzAuNTI2LC0yLjg0MiAtMS4wOTQsLTAuNDUzIC0xLjgzNywtMC45ODd6bTMuNDQsMC42MmMwLjM4OSwwLjczIDAuODE2LDEuNTIxIDAsMGwwLDB6bS0wLjUsLTMuMzE2YzAuNzkxLC0wLjIyNiAxLjA5NCwxLjcxNCAxLjcxOCwtMC4zNzZjLTEuNDc5LC0xLjk1MyAtMS4xMzcsMC4yIC0xLjcxOCwwLjM3NnptMC44NjMsLTIuODNjLTEuNjg4LDAuNzM2IC0xLjI2MSwwLjU1MiAwLDBsMCwwem0tMjcuMzA3LDM1LjI0YzAuMzEyLDEuMjY5IDEuMjg2LDAuODAzIDEuNjkyLDEuMTI0Yy0wLjg1NSwtMC42NjMgLTAuNTgxLC0xLjc4MiAtMS42OTIsLTEuMTI0em00NS40MDYsODYuODExYy0wLjU0MywxLjA5IC0xLjQzMiwtMC4wOTggLTEuMzM4LDEuNzc4YzIuNTcyLC0wLjM5IDEuMTMyLC0xLjM0NyAxLjMzOCwtMS43Nzh6bS00Mi4yMjMsLTEwOC4wMjljMC4wMDQsMCAwLjAwNCwwIDAuMDA0LDBjMC40MjcsLTAuMjI2IDAuODMzLC0wLjQ3OCAxLjIyMiwtMC44MDNjLTAuNDEzLDAuMjczIC0wLjgyNCwwLjUzOCAtMS4yMjYsMC44MDN6bTQ2LjMwNCw2NS43MDljMC42MTEsLTAuMDgxIDAuNDc4LC0xLjEyOCAwLjIxNCwtMS4zODVjLTAuMjY5LDAuMzI1IC0xLjY5NywxLjAzNCAtMi4xOCwxLjQ3NGM0LjI0LC02LjcwMSAtMTQuMTkyLC0xNS44MTIgLTE3LjI3NywtNy45ODNjLTQuNjc5LC0wLjQ0OSAtNy44NTksLTcuNTA0IC02Ljg2NywtMTEuMDQ3Yy05LjY5Miw4LjA2NCAtOC4xMzIsLTExLjIzNSAtMC44NTksLTYuNDQ0YzIuNSwtMi4yMzUgNS4xODQsMC4xODggNi4xODgsNC4wNzdjLTEuOTkxLC03LjU5OCA2LjYwNywtMTcuNDEgMTIuODkzLC0yMC4wMjVjLTAuMTQxLDAuMzIgLTEuNjQxLDIuNzY1IC0xLjU4NSwyLjY5MmMxLjE4NCwtMC41NiAyLjMxNiwtMS41MDggMy40ODcsLTIuMTQ1Yy0xLjUxNywtMS4wNjQgLTEuODAzLC0yLjI2MSAtMi4yMDUsLTIuNzAxYy0xLjM1LC0wLjAzNCAtMS43MDEsMC4xNDEgLTMuMTkyLDAuNTU1YzMuNDIzLC0yLjM5NyA4LjY5MiwtMS4yMTMgOS4wODEsLTUuNTI2Yy0wLjc5OSwwLjIzNSAtMS43NTIsMC40MDYgLTIuNTA0LDAuMjk1YzAuMDk0LC0wLjA5OCAxLjk3NCwtMC42MTEgMS42NSwtMS40MDJjLTEuNzI2LC0xLjAwOSAtMi43NTYsLTIuNzIyIC0zLjYyOCwtNS41MjFjLTQuMDEzLDQuNTc3IC0zLjU2NCwtNS44MjUgLTguNDAyLC0wLjMxMmMtMS4yODIsMS40NTMgLTIuODUxLDE2LjE2MiAtMy45OTEsNS43MDljLTEzLjEyLC0zLjA5OCAtMC4yOTUsLTcuOTkyIC0yLjUwOSwtMTIuMDM0YzMuMjIyLDAuNTg5IDUuNjg4LC0wLjYwNyA1LjQ1NywtNC4wNDNjLTEuMDQ3LDAuNzU3IC0yLjU5NCwxLjIyNyAtMy42MjQsMi42MzdjLTAuMjE0LC0wLjk3OSAwLjMyNSwtMS4zMTYgLTAuMzY4LC0yLjI3M2MtMC4yNjUsMC4zMjkgLTAuNTI2LDAuNjQ5IC0wLjc5OSwwLjk4N2MtMC42NjcsLTEuNTg5IC0xLjA2OSwtMi41MzQgLTEuNzY5LC0zLjg3NmMtMC4wMTMsMC4wNDcgLTEuNzUyLDEuNTc3IC0xLjk3NCwxLjY2N2MyLjgzNyw2LjExMSAtMTguMzQyLC0wLjI5NSAtMjEuNTM0LDAuOTE0Yy02LjA2OCwzLjE3NSAtMTUuNTksLTIuOTI3IC0yMi43NzgsMS42NWMxLjE5MiwwLjU3MyAyLjE1LDEuMzU5IDMuNDM2LDEuODVjLTIuNTU2LC0wLjEwMiAtMy4zMDMsMC44MjkgLTUuMjgyLDEuMTAzYzEuMzU1LDAuMzQ2IDIuMDUxLDAuOTgzIDMuOTMyLDEuMjI3Yy0yLjc4NiwxLjQ5MSAtNi4xNzUsNC4wNDcgLTIuODE2LDQuNTUxYzAuNjcxLDAuNjggLTAuNzQ4LDEuNDk2IC0wLjI1MiwyLjMyNWMwLjg5OCwwLjA1MSAxLjQ4MywtMC40NzkgMi4zMzgsLTAuMzcyYy0xLjI0NCwxLjA2IC0zLjc1MiwzLjg1OSAtNS42NjMsNC43OWM1LjUsLTAuNjU4IDguNTc3LC01LjIxNCAxMi4xMDMsLTcuMjk1Yy0wLjkxOSwxLjA5IC0xLjU0MywxLjIzOSAtMi4wMywyLjE0MWM3LjQzMSwtNS45NzkgMTMuOTE0LDQuOTY2IDE2LjA4NSwxMS43MjZjLTAuNTI2LC0wLjA5IC0wLjU3MywtMC43MzEgLTEuMzUsLTAuODA4Yy0xLjExNSw4LjMxMiAwLjIzNSwxNi43OSA3LjMwMywyMi43ODJjLTEuMzI5LC0yLjQyNyAtMi4xOTIsLTUuMjgyIC0zLjMxMiwtNy42MTFjNS42MjgsNy45MTQgNy44NSwxMC4zNTkgMTYuOTEsMTUuMThjOS4wODEsNC44MTIgNC42MDcsNi40NzggNS45NzksMTQuNTE3Yy0xLjMxNiwxLjA2OSA2LjkyNywxMy42ODQgNy4xMTksMTcuOTkyYzAuMzY4LDcuODg5IC00LjEwNywxOS44NDYgMy4xNDEsMjUuODk3YzIuMjk1LC00Ljk0IDEuOTY2LC04LjcyMiAyLjMyNSwtMTMuMjgyYzIuNjM3LC0wLjU5NCA1LjEwNywtMi4zMjUgNC4yMDEsLTUuNDk2YzMuNTEzLC0xLjAxMyAxLjY4LDAuMTk2IDMuODQyLC0zLjY1YzAuNzA5LDAuNDcgMC4wOTgsMC4zNDIgMC4xNzksMS4wOThjMy4wNjQsLTcuNzk1IDEwLjkxOSwtMTMuMTI4IDEwLjgzOCwtMjEuOTQ0Yy0zLjI1MSwtMS45NTYgLTYuMjE2LC0zLjgzMiAtOS45NTEsLTIuNjYxem0tMjMuODc2LC0zNi4zMjljLTEuMDIxLC0zLjg4OSAtMC40MSwtMy44ODkgMS4xMDMsLTMuNzQ0Yy0wLjI2MSwwLjk3IC0wLjg3MywyLjc2MSAtMS4xMDMsMy43NDR6bS0xLjI3NCwtNi40OTJjMS44NDIsLTAuMjkxIDMuODEyLDAuMzMzIDUuNjU4LDMuMjkxYy0wLjM5OCwwLjQ1MyAtMC41NjgsMC4yODYgLTAuOTg3LDEuNDE5Yy0wLjMwOCwtMC43OTUgLTAuNzc0LC0wLjQxOSAtMS4yOTUsLTAuMzA0Yy0wLjYzNywtMy42NjIgLTEuOTQ1LC0zLjgxNiAtNC43OSwtMi45NTNjMC4xMTEsLTAuNjcxIDAuNjkyLC0xLjExOSAxLjQxNCwtMS40NTN6bS0zLjA2NCwtMi4xNjJjLTIuMjc4LC0wLjMyMSAtMS4zNDYsLTEuNjExIC0xLjgzNywtMy41NjljMC4yMzksMC43MzYgMS4yMDUsMi40NTQgMS44MzcsMy41Njl6bTguMjM5LC0xNS4zNTRjLTAuNDQsLTAuMjM1IDAuNDEsMS44MDMgMC42MjQsMS45NjFjMC41ODYsLTAuNDQ5IDEuNTksLTAuOTE5IDIuMDgxLC0xLjM0NmMtMC45OTUsLTEuODU5IC0xLjY5NiwtMC4wODUgLTIuNzA1LC0wLjYxNXptMTAuNzc4LDEwNS4zOTZjLTAuMTU4LDAuNDQ1IC0xLjQ4MywtMC44OTMgLTEuNjU4LDAuOTE1YzMuNDM2LDEuNTI5IDEuMzU1LC0wLjA2OSAxLjY1OCwtMC45MTV6bS01Ni4xOTYsLTk2LjgzMmMxLjM1OSwwLjYwNiAwLjE1NCwwLjA2OCAwLDBsMCwwem0zMC4wODEsLTIzLjE2M2MxLjI4MiwxLjMwNCAxLjIwMSwtMS4wMDQgMS4xMDMsLTEuMzU5Yy0xLjcwNSwwLjc4MiAtMy4wODYsMS41NDMgLTQuMTcxLDIuMzM4YzEuOTI3LDAuNzM1IDEuNjg4LC0wLjU2OCAzLjA2OCwtMC45Nzl6bTEyLjg4NCw1LjAyNmMwLjEzNywtMC4yMjcgMS45MjMsLTAuNzYxIDIuMzM3LC0xLjQ2NmMtMy43NiwwLjM4IC0yLjM5NiwxLjc2MSAtMi4zMzcsMS40NjZ6bS0xMi43NiwtMC42MTFjLTMuNzEsLTMuNTA0IC03LjAzLC0wLjk1MyAtNS4yODIsMi43MDFjMS43OTUsLTAuNTUyIDMuNjM3LC0xLjg0MiA1LjI4MiwtMi43MDF6bS0yLjIxNCwzLjkxOGMzLjUzLDIuMDYgMTAuOTE0LDEuOTgzIDguNDY2LC0xLjM0NmMtMC41MDUsLTAuOTI3IC0wLjkyOCwtMS4zMDggLTAuMzU1LC0yLjMyNWMtMS43ODYsMC40MjMgLTEuMDM0LC0wLjYzNyAtMS44NSwxLjM1Yy0yLjA4NiwtMS43ODYgLTQuNDI4LC0xLjE1NCAtNi41LDAuOTc0YzAuODU5LDAuMTcxIDEuNzE4LDAuMzI1IDIuNTY4LDAuNDkxYy0wLjU1MSwwLjQyMSAtMi4wNTUsMC4wMTUgLTIuMzI5LDAuODU2em01LjAzNCwtMTAuNjc0YzAuODEyLDAgMS43MjYsLTAuNSAyLjQ1MywtMC40OTFjLTEuMDI1LC0wLjAxIC0xLjM2MywtMS41NDQgLTIuNDUzLDAuNDkxem0xMDEuNTQ3LDkuMzAzYy0yLjQ3OSwtMy42MjggMi4xODgsLTYuMTI4IDYuMDczLC02Ljk5NmMtNC42OCwtMC40MSAtMTAuOTQ0LDIuMjAxIC04LjI4Niw3LjQ5MWMwLjIzOCwtMC4zNjMgMS42OTUsMC40NDEgMi4yMTMsLTAuNDk1em0xOS4yNjksLTExLjQ3NGMwLjM4LC0xLjMxNiAyLjI1NiwwLjM2OCAxLjgzNywtMS45NjJjLTMuNTksLTAuNDcgLTEuNTQ3LDAuOTYyIC0xLjgzNywxLjk2MnptMS4xNjIsMC4xMjRjMC4zMDMsMC4wNDMgMS40NTcsMS40MzIgMy4wMTcsMC45ODdjLTAuNjY3LC0yLjM1OSAtMS40ODcsLTAuNzcgLTMuMDE3LC0wLjk4N3ptLTE5LjY0MSwxMS45MWMwLjQ2NiwxLjUyMSAwLjE1LDAuNDc4IDAsMGwwLDB6bS0yNy4wMywtMTAuNjU5YzAuMzY4LDEuNDA2IDAuOTE1LDEuNjkzIDEuNDgzLDIuOTRjLTAuMTE5LDAuMDQzIDEuMDU1LC0yLjEzNyAxLjIyNywtMi4zMjljMS45NDQsMS4yNDggMC43MjYsMS4wNiAyLjgyOSwwLjQ5MWMtMy40MTUsLTMuMzUgLTYuOTUzLC0zLjYyNCAtNy43NDMsLTAuNzM5YzAuNTQ2LDAuNDkzIDEuODQ1LC0wLjIwNCAyLjIwNCwtMC4zNjN6bTIxLjUxMywxMi44MDRjLTAuMjE4LC0wLjY3NSAtMC40MzYsLTEuMzQyIDAsMGwwLDB6bS0xNi41OTgsLTEzLjQxNGMwLjU2OCwtMC42NjYgMS43ODYsLTAuNzE4IDIuNDYyLC0xLjYwN2MtMS41LC0wLjE1OCAtMy4wNiwtMC4yMjcgLTQuNTUxLC0wLjExNmMwLjA4LDEuOTQ1IDEuMzkyLDAuNjUgMi4wODksMS43MjN6bTc1LjE4Myw0Ljg1OGMwLjAxNywwLjIwNSAwLjA5NCwwLjkxMSAwLjA4MSwwLjg4OWMtMC4wMzQsLTAuNTIxIC0wLjA3MywtMC43ODEgLTAuMDgxLC0wLjg4OXptLTEzOC40NjUsMS4yMWMwLjg4LC0wLjAyNSAxLjk4NywxLjI5NSAxLjcxOCwtMC4xNzljLTAuMjc4LC0xLjU2NCAtNi44MjUsLTIuMTA3IC03LjczNSwtMi4wMjZjMS41NDMsMi43OTkgMy4xNDUsMi40NTcgNi4wMTcsMi4yMDV6bTE2MS4zODQsNC43MDVjLTAuNTMsLTAuMjk5IDAuMDUxLC0xLjg4NSAtMS41OSwtMC41NTFjLTAuMzg5LDEuNjU4IDAuNzc3LDAuMDg1IDEuNTksMC41NTF6bS0yNy43MzUsLTQuNzljMC44OTMsLTAuMzg1IDEuNTY4LC0wLjE5NiAyLjUxMywtMC42NjNjLTIuMjEzLC0wLjkyMyAtMi4yMjcsLTEuMDA0IC00LjU5OCwtMC45ODNjLTAuMDUyLDEuOTgzIDEuMTgzLDAuNzkxIDIuMDg1LDEuNjQ2em0tMjMuODEyLC00LjQ3OWMtMC4wNTEsLTAuNzA5IDIuMDEzLC0wLjk5MSAtMC4yNDQsLTIuMDEzYy0yLjI1NiwxLjcwMSAwLjIwMSwxLjQxNCAwLjI0NCwyLjAxM3ptLTY1LjcwOSw4LjkyM2MwLjA3MywwLjc4NiAwLjE4LDIuMDU1IDAsMGwwLDB6bTAuNjc1LDE1LjUzNGMxLjA5NSwtMS4wMzQgMC4zMDQsLTAuMjgyIDAsMGwwLDB6bTE2LjUwOSwtMTMuMjAxYy0wLjAzNSwtMC43OTEgLTAuMDIyLC0wLjUyMSAwLDBsMCwwem0tNTMuMzMzLC0xLjQwNmMtMi41MDQsLTIuMjUyIC00Ljg3NiwtMy4xMzcgLTcuMzY3LC0xLjg0NmMwLjEyNCwtMC43MzUgMC4yNDQsLTEuNDcgMC4zOCwtMi4yMDFjLTEuODYzLDAuNTYgLTIuMzA3LDAuODcyIC0zLjU2OCwxLjcxNGMwLjE2NywtMC42MiAwLjMyOSwtMS4yMzEgMC40ODcsLTEuODQ2Yy03LjIzMSw2LjQzNiAxNS4wNDMsNi4xMjggMi44MjUsMTEuMTcxYzIuMDksMC44OTMgNC4zNjgsMi4xMDIgNywzLjA3MmMtMC4yODcsLTAuNjU0IC0wLjU2OCwtMS4zMTYgLTAuODYzLC0xLjk2NmMwLjU3NywwLjM2OCAxLjE0NSwwLjczMSAxLjcyMiwxLjA5NGMtMC41MzQsLTEuNjcxIC0wLjM2MywtMi44MzMgLTAuOTgzLC00LjQxYzEuMDM4LDAuNDAyIDEuNTk0LDAuODkzIDIuNTc3LDEuODQ2YzAuMTYyLC0yLjQwNSAtMC42OTMsLTQuMzggLTIuMjEsLTYuNjI4em0tMTIuMzg5LC0xMS41MzhjNS43ODYsMi41ODEgMC4yMTQsLTUuMTkyIDAsMGwwLDB6bTguNTksLTIuMzMzYzAuMDE3LC0wLjAxMyAtMS42NDUsMS42MiAtMS45OTIsMi4xMmMtMC44MTIsMC4xNDEgLTEuNDU3LC0wLjU1MSAtMi4wNTUsLTAuNTNjLTEuMzU1LDEuNzkxIC0xLjA0MywyLjI1NiAtMy4yMDEsMy44MDhjNi4wNzMsMy45NzkgMTEuNTk4LC01LjE4NCAxNy4zMTIsLTcuMjQ0Yy00LjQ3NCwtMS4xNDEgLTE1LjEyNCwtMS4zMiAtMTQuOTc0LDIuMzQyYzEuODM3LC0wLjEyMSAyLjk0NCwwLjY3OSA0LjkxLC0wLjQ5NnptNTUuNzI2LDE1LjM0MWMwLjI2NSwwLjAwOSAxLjQwNiwwLjAzOCAwLDBsMCwwem0tMTMuODcyLDEzLjk4N2MwLjQ0NSwxLjIyNyAtMC4zNzYsMS45NDUgMS45MDIsMi43MTRjLTEuNjAyLDIuMjIyIC0wLjYxNSwxLjA3MyAtMC40OTEsMi41NjhjLTAuMTQ1LDAuMzA4IC0xLjEyLDEuMzI1IC0xLjQxLDEuNzI2YzguMTE0LC0wLjkwNSAxLjM2MiwtMTAuMTE5IC0wLjAwMSwtNy4wMDh6bTEyLjMzMywtMTMuNjIzYzAuOTE1LC0wLjMyOSAxLjA0NywtMC4zNzIgMCwwbDAsMHptLTkuNTEzLDEwLjM4NGMtMC43NTEsLTEuNzE0IC0wLjExMSwtMC4yNjEgMCwwbDAsMHptLTAuNjcsMC43OTVjLTAuNTM1LDAuMTY2IC0wLjU4NiwwLjE3OSAwLDBsMCwwem0tNS40MDIsNi44MTJjNC4zNjcsMC4xMTkgMi4xOTcsLTQuNzM1IDAsMGwwLDB6bTEwNC4yODUsNy42NjZjLTAuOTcsLTAuMzQyIC0xLjQ4NywtMC43MjIgLTEuNjU4LC0xLjgzM2MtMC4xMzMsMC45NjEgLTAuODEyLDIuNTY0IC0wLjg2MywzLjU1NmMwLjM2MywwLjEwMiAyLjM4OSwtMC4xMzcgMi4zODksMC43MzVjMCwwLjIxOCAwLjMzMywtMy40MjQgMC4xMzIsLTIuNDU4em0tMC45NjUsMzkuMDgxYy0wLjIxNCwtMC4wMjEgLTAuNDI4LC0xLjI3MyAtMS40NjYsLTAuMTE5YzAuMTY2LDEuMzk3IDAuNzE4LDAuMDM4IDEuNDY2LDAuMTE5em0tMi43NTcsLTQ4LjQ4N2MwLjY5MiwzLjIyMyAxLjI1MiwzLjc3MyAyLjYzNyw1LjAzNGMtMC41OTQsLTEuNzIyIC0wLjkwMiwtMi4wNzcgLTEuMTI5LC0zLjE0NWMtMS4wODUsLTAuODQxIC0wLjE0OSwtMS40MTQgLTEuNTA4LC0xLjg4OXptLTEuMzgsMTkuNzg3Yy0xLjA1NiwwLjE5NiAtMC45NCwwLjE4MyAwLDBsMCwwem00LjkzNiwzNC4yODFjMy4yNDgsLTAuNzk5IDYuMjAxLC0wLjQwMiA5LjE4OCwxLjI1NmMtMS40MjMsLTIuMjkgLTMuMzU1LC00LjQwMSAtNS4wNDIsLTUuNTI2Yy0yLjg3NiwtMS4wNjggLTQuMjUyLC0xLjg0MiAtNi45MSwwLjIxOGMyLjQwOSwyLjA3IDEuODk2LDIuMzgyIDIuNzY0LDQuMDUyem0tNi40MDYsLTM1Ljc2NGMzLjAwOSwwLjg4NCA4Ljc3MywtMC43NTcgNC45MTUsLTYuMDA0Yy0wLjI5NSwyLjk3IC0xLjQwMiwyLjg0NiAtMi4yMDUsNC44NDZjLTAuODMsMC41OSAtMS44MDksLTAuMDg2IC0yLjcxLDEuMTU4em0tNi41NDcsMzcuMTE1YzEuMzc2LC0wLjkxIDAuNjA3LC0wLjQwMiAwLDBsMCwwem02LjE3OSwtMzMuOTE5YzAuODYzLC0wLjkxIC0wLjE0NSwtMS42NTQgMC4wNiwtMi41NjhjLTAuMzA4LDEuMzkzIC0xLjY4NCwxLjE0MSAtMC4wNiwyLjU2OHptLTUuOTk2LDI5Ljk5MWMxLjAwNCwwLjI3IDAuNjI0LC0wLjkxIDEuMzU5LC0xLjQxYzAuODY3LDAuNTQ3IC0wLjQyMywxLjU2IDEuMjgyLDEuNTM4Yy0wLjc2NSwtMS45MjMgLTAuMTU0LC0yLjAwNCAtMC4wNTYsLTMuNTY0Yy0xLjU5LC0wLjU3NyAtMC41NTYsLTAuMDQ3IC0xLjU4MSwtMS4zOTNjLTEuMiwwLjgyNSAtMS43NiwyLjY3MiAtMS4wMDQsNC44Mjl6bTIuOTQ5LDMuOTkyYzAuMzQ3LC0xLjQ5MiAtMC4wNDcsMC4yMzkgMCwwbDAsMHptNC41MzksLTUuNjU0YzAuMDEzLDAgMC4wMTMsMCAwLjAxMywwYzAuMjU2LDAuMjYxIDAuMTcxLDAuMTc1IC0wLjAxMywwem0tNS4zOTgsNC4zNjNjLTEuNzA1LDAuMDQ3IC0yLjAxNywwLjA2IDAsMGwwLDB6bTEuOTAyLC03LjY3MWMtMS4wODEsLTAuNDk2IC0yLjA4NiwtMC40MjMgLTIuODUxLDAuMDQzYzEuMTEyLDAuNDkyIDEuNzE0LC0wLjA4MSAyLjg1MSwtMC4wNDN6bS04MS45NjEsLTM1LjM5MmMtMC40MjcsMS4xOTIgLTAuNTc3LDEuNjE5IDAsMGwwLDB6bTMuMzI5LDQuOTRjMC4zNTksLTAuODUxIDAuMjAxLC0wLjQ1MyAwLDBsMCwwem0tNC4wOSwtMy4wMTdjMC4zOTQsMC42NTggMS4yNDgsMi4wNTEgMCwwbDAsMHptMC4yNywtMTcuMDE3YzEuNjU0LDAuNjE5IC0wLjM0NiwtMC4xMjggMCwwbDAsMHptMTAzLjY4NywtMTMuMzQyYy00LjE2NywtMS4wMyAtNi4yOTUsMC43NDggLTEwLjE4LDAuMjQzYy0zLjUxNywtMC40NjEgLTcuOTY2LC0zLjU2NCAtMTEuNDc4LC0zLjc0OGMtMy44NSwtMC4yMDEgLTUuNzE4LDEuNzUyIC0xMC42ODQsMC42NjZjLTAuMDc3LC0yLjAyMSAtOC42MDMsLTMuMTU4IC0xMy4xMzcsLTEuMTVjNy43MzUsLTQuNTMgLTIuOTc0LC02Ljk3OCAtMy44NjMsLTQuMjM1Yy0zLjYzMywwLjEwMyAtNy45OTUsMC4xMiAtOC4wNDMsMy4zMTJjLTMuMDg2LDEuMTQ1IC00LjI0MywwLjIyMiAtMS42NTgsMy44MDhjLTAuNzQzLC0wLjI2NSAtMi43MDEsLTEuNjExIC0zLjE5MiwtMS45NzRjLTAuODk4LC0wLjIxNCAwLjE4NCwxLjY0MSAwLjA2NCwxLjY1NGMtMS4yNzQsLTAuOTgzIC0xLjMzMywtMS4yMzUgLTIuMjE0LC0yLjUxM2MtMC41NTYsMy4yNTcgMS4yNjksNC45ODMgMy43NDgsNi42OTJjLTMuMjUyLC0wLjQ0OSAtMi40OTYsLTAuODQyIC01LjY0MSwwLjk4M2M1Ljc5NSwtNi4yMTggLTYuMzg1LC0xMS4zNzYgLTEuMDQ3LC0yLjE1M2MtMy4zNzIsLTEuNjQ1IC0zLjQzMiwtMS4xMjQgLTUuMDk4LC0wLjEyYy0yLjQ1MywtMC42OTIgLTUuMTAyLDAuMDYgLTguMzQyLDEuOTY2Yy0wLjc4NiwtMS42NzUgLTAuNjc1LC0xLjA5OCAtMi4wOTQsLTEuODQ2YzAuMTIsMC43ODYgMC41ODYsMi4xODQgMC44NTksMi44ODRjLTIuOTYxLDAuODIxIC0xLjI4NiwtMC4xNTQgLTIuNTgxLDEuOTY2Yy0wLjEyLDAuMDYgLTEuNDU3LC0wLjcwMSAtMi4wODYsLTAuNzk5YzAuNDAyLC0wLjAwNCAwLjQ3OSwxLjIyNyAwLjM4LDEuNDdjLTEuMjUyLC0xLjEzNyAtMS41MTMsLTIuMDEzIC0yLjE2MiwtMy40OTJjMS43OTksMC4wOSAyLjgwOCwtMC42MDcgNC4wMDQsLTAuNzMxYy0yLjIyMiwtMS4yODYgLTMuNjY3LC0yLjE1OCAtNS44MjQsLTIuMjljLTAuODk4LC0yLjY1IC0zLjkzMiwtMS40NzkgLTUuNjIsLTEuOTIzYy0wLjA5OCwwLjQ5MSAtMC40MzYsMC43NTIgLTAuOTc4LDAuNzI3Yy0xLjQ2Niw0LjUzIC0xMS41MjYsNy41ODkgLTguOTcsMTMuODg0YzMuNTEzLDAuMDk4IDMuNzQ0LC0wLjM4IDUuNDExLDIuNzQ4YzEuODc2LC0yLjExNSAxLjY0OSwtMy4wMzggMS40NjIsLTQuMzU1YzAuNTQzLC0yLjM4OSAxLjk2NiwtNy42ODMgNS41MywtNS44MjVjLTMuNTksMS43MzEgLTMuNjI4LDUuMTU4IDEuMTExLDQuNjc1YzAuMjM1LDAuMzcyIDAuNDcsMC43MzUgMC43MjMsMS4xMDNjLTMuOTE1LDAuODg5IC0xLjIwNSwwLjczMSAtMi40ODMsMi4yOTVjLTAuNjg0LDAuMjA1IC0xLjExMSwtMC4wNiAtMS4yOTksLTAuNzk1Yy0wLjg5OCw0LjczIC03Ljk3NCw2LjYzMiAtNy44MTIsMC43MDFjLTAuNjgsMi41MTMgLTEuMjY1LDMuOTQ5IC0yLjc5NSw2LjkwMmMtMi41MjYsLTAuMTc1IC00LjMzOCwwLjk1NyAtNi43MzEsMi4yMzVjMC4wMDQsMC4xNzEgLTAuMDA4LDAuMzM4IC0wLjAzNCwwLjQ5NmM1LjM0Miw0LjkxOSAtMi4wNTUsMy4yNTIgLTIuODMzLDUuNTgxYy0xLjIxMywzLjU2OCAtMC4xMzcsNS4zMjUgMi44MjUsNC45NzljNC45ODMsLTAuNTk4IDguODgsLTEzLjM5MyAxMy43NDQsLTEuNDFjMC40NywtMC4zMDMgMS45MzYsLTEuMzcyIDIuMDIxLC0xLjQ3NGMtMi41NTEsLTEuNzQ4IC0zLjE0MSwtMy44MjEgLTMuOTEsLTUuODEyYzIuNTYsMi4zNSA0LjM1NSw0LjU2NCA2LjIxNCw2LjkyM2MwLjA2OSwwLjI2MSAtMC44MTIsMC4wMzQgLTAuODIxLDAuMzY4YzAuNDM2LDAuMDMgMC41NzcsMC4yNDggMC40MjgsMC42NzVjLTAuMDE3LDAuMDgxIDEuODA4LC0wLjYzNyAxLjkwMiwtMC42OGMtMC4yMzksLTAuOTMyIC0wLjk4NywtMC45NyAtMS4xOTcsLTEuNjg0YzEuMDA0LC0wLjEzNyAxLjg1MSwtMC43MDUgMy4xNzUsLTEuMDk4Yy0wLjQxNCwtMC41MDUgLTAuOTE5LC0wLjYyNCAtMS40NzksLTAuMzg1YzEuNSwtMS42NSAyLjMwNCwtMi44NDYgMy41MTcsLTUuMDE3YzAuNzM1LC0wLjA5NCA0LjE2NywtMC4xODggNS40NTcsLTAuMDZjLTAuMDQzLDEuMDUxIDEuNTM5LDUuNjE1IDEuMDIxLDUuNjMyYy0yLjU3NywwLjExMSAtNS43MDksLTIuMTc1IC04LjI5OSwtMC4xMjRjLTMuMzYzLDIuNjUgMi40ODMsNC45NzkgNS42MjQsNC4zNTljMC4xNzUsMS4yMzkgLTAuMDg2LDQuMzcyIC0wLjU1Niw1LjU4NmMtMi41NjgsLTEuNTQ3IC0zLjY1NCwtMC45MTUgLTYuNTk4LC0xLjA5OGMwLjAwOSwwLjc5NSAtMC4wMjUsMS42MiAtMC4wNiwyLjQ0NGMtMC4yMzksLTMuMjE0IC0xLjAyNSwtNS4zNDIgLTMuNzEzLC0xLjc5NWMtNy4zNDYsLTEuMDk4IC03LjMxMiwtOS43NDggLTE2LjgxNiwtNC4xNDVjLTUuOTE0LDMuNDg3IC02LjIwOSwxMC44MzMgLTkuMTE1LDE3LjMyOWMxLjExMSwyLjI5MSAzLjE3NSw1LjAyNiA1LjUxMyw3Ljc2OWMyLjY1OCwzLjEwNyA5LjQ4NywtMi4yNjEgMTMuNjAyLDEuNTEzYzAuMDU2LDAuMDUxIDIsOC4yNjUgMi4wODEsOS4xNzFjMC40NjEsNC45OTEgLTAuNzgyLDEwLjQxOSAxLjU0NywxNi4xMDdjNC4xMzIsMTAuMTA3IDEzLjkxOSw0LjA5NCAxNC4yOTUsLTUuNDI4YzAuMDk4LC0yLjY0OSAzLjU2OCwtMi40ODcgMy45NjIsLTQuMzg0YzAuNjQ5LC0zLjE2MyAtMS40NzQsLTYuMjM1IC0wLjcxNCwtOC42ODRjMS42NDksLTUuMjAxIDkuMjM1LC04LjE0NSA3LjU1NSwtMTMuODcyYy03LjEzMiw0LjY2MiAtMTEuMjA5LC05Ljg5NyAtMTMuMTkyLC0xNC44MDNjNS45MTUsMy43MTggNC44ODUsMTcuMDQyIDE0LjE2NywxMi4yMThjMTAuOTIzLC01LjY1NCAtMC44MzMsLTYuNDE0IC0zLjczNSwtMTIuMzM4YzAuMzcyLDAuMjgyIDEuNDM2LC0wLjA5OCAxLjY1NCwtMC4yOTVjMi4yMTgsMi45MzIgMTQuNjYyLDcuMjEzIDE2LjAyMSw2LjY3OWMwLjUyNiw0LjE2MiAyLjIwNiw4LjE2MyAzLjk4NywxMS44NWMyLjUyMSwtNS4yODYgNS4xMDIsLTkuMjY5IDkuMTU0LC0xMy4wNzNjMC44OTgsMi4yOTEgMi4xODQsMy41NDcgMi43Niw2LjI2MWMwLjU4MiwtMC4wNzcgMS4yNywtMC43NjkgMS41OSwtMC43OTljMC45NTMsNC4xODggMi4xOTIsMTAuNDk1IDYuMDMsMTIuNjQxYy0wLjM1OSwtMy42MjQgLTIuMjM5LC01LjcyMiAtMi44MzcsLTkuMDE3YzEuMTkyLDEuOTI3IDIuMjQ0LDEuNSAzLjA2OCwzLjc0NGM1LjY0NSwtMy42MDcgLTAuMzQ2LC03LjA2NCAxLjc3OCwtMTEuMjM1YzEwLjAzLDQuMjU2IDYuMjYxLC0xMC45NDkgNy44NTksLTEzLjg3MmMtMi43NTIsLTEuMjgyIC0zLjY2MiwwLjYwMyAtMS4wMzgsLTIuNTgxYzAuMTcxLDAuNTQzIDAuMjQ4LDEuMDk0IDAuMjQ4LDEuNjU4YzEsLTAuMzY4IDEuNjQ1LC0xLjEyNCAyLjY4MywtMC43MzljMC4yMDEsMS43ODYgMS41OTksMy4yNDQgMi4yMTgsNS4xNTRjMC4yMTQsLTIuNTgxIDAuODI1LC0xLjc2OSAtMS4xMDcsLTUuMDNjMS44OCwtMi42NSA0LjE4MywtMi42MTEgNS44NTksLTYuMDk4YzIuMzA0LC00Ljc2OSAtMi4xODQsLTcuNjU0IC0yLjE3OSwtNy43NjVjMC40NDksLTQuODg1IDEwLjMwNywtOC4yMDEgMTQuMzcyLC04LjQ3Yy0xLjgyOSwzLjA4OSAtMy45NDUsNy41MDggLTAuNDMyLDExLjA0MmMzLjM1LC0zLjcyMiAtMC43MzEsLTEwLjY5MiA1LjcwOSwtOC41MjVjMC41NjQsLTIuNTk4IDEuNzM5LC0xLjYzNyAzLjM3MiwtMy4wNzNjMC4wNzcsMC4zNzIgMS42NSwtMC40NTcgLTAuOTE5LC0yLjE1YzAuNSwtMS4wNjggMS41MzgsLTEuNjU4IDEuODQyLC0yLjUxN2MxLjM3NiwxLjcyMiAyLjYxNSwxLjYyNCA0LjM1NSwzLjEyOGMwLjM1NCwtMC41NTYgMS4yMjIsLTIuMTI5IDEuNDE5LC0zLjA2OGMtNC41LC0wLjA3NiAtNS41ODEsLTIuNTEyIC0xMC4zMjQsLTMuNjc0em0tMTcuNzM5LDY3LjEzNmMtMC40MTksLTAuMzggLTEuNDAyLC0xLjI3NCAwLDBsMCwwem0xNC43MTgsMC43NjFjLTAuMzQ2LDAuOTEgLTIuMzYzLDAuODg5IC0wLjUsMS40NzRjMS43MzUsLTAuOTIzIDAuNTA0LC0xLjQ4MyAwLjUsLTEuNDc0em0tMTYuODU5LC00LjM4MWMwLjYxNSwxLjkxNSAxLjQ0OSwwLjM5MyAxLjE2MiwwLjE4OGMtMC43MTMsLTAuNDkxIDAuMTQ2LC0yLjQ5OSAtMS4xNjIsLTAuMTg4em0yNS44OCwxNy43MDVjMC4yMDEsMC4zMzQgMS41OTQsMi41OTUgMCwwbDAsMHptLTguMTQxLC0xNC41NzJjMC40MzYsLTAuMTE1IDAuNzc4LDEuODc2IDAuODYzLC0wLjIzOWMtMS43OTksLTEuMzIxIC0wLjk0LDAuMjYgLTAuODYzLDAuMjM5em0tMjguOTcsMC41NmM0Ljk5MSwxLjkyMyA2LjEyOCwtNS44MjkgNC40MzYsLTcuODg1Yy0xLjE4OCwxLjAzIC0xMC4wMjEsNS43MjIgLTQuNDM2LDcuODg1em0tNDguNzA1LDE2LjE5MmMxLjg4OSw2LjgwOCA1LjQxOSwtNi4yOTQgNS4wOTksLTguOTg3Yy0xLjY4OSwxLjMzNyAtNi4wOTksNS4zNTkgLTUuMDk5LDguOTg3em0tMzguOTc0LC03OS4zMjljLTIuNzEsLTAuMjE4IC00LjYwMywtMC4wMyAtNi4yNTYsMC45NzVjMC44NjMsMS4wOTQgMC4zNDYsMC43ODIgMC45ODMsMS45NjFjMS41MjUsLTAuNjA3IDEuNTE3LDAuMjE0IDIuNTc3LDAuNzQ4YzEuNzgyLC0xLjE3OSAyLjk4MywtMS4yMjIgMi42OTYsLTMuNjg0em05NC40MjcsNTkuNjk3YzAuMDY0LDAuMDczIDAuMDk4LDAuMTI0IDAuMTQxLDAuMjAxYzAuMDQ3LC0wLjAzNCAwLjA4MSwtMC4wODEgMC4xMzcsLTAuMDk4Yy0wLjEwMywtMC4wNDQgLTAuMTcxLC0wLjA0NCAtMC4yNzgsLTAuMTAzem0tMTguNDY2LC0zLjVjMi42MTUsMi41NzcgNS4zNDYsOS40MjcgNy45NzQsNy42MTZjLTEuMTUzLC0zLjEzNCAtNC4yNiwtNi43NjIgLTcuOTc0LC03LjYxNnptLTgzLjM3NSwtNjEuNDE1YzEuNjQxLC0wLjIxNCAwLjYxMSwtMC42NzUgMy4wNiwwLjEyYy0wLjM0NiwtMC42MzcgLTAuODU5LC0yLjUgLTEuMzQ2LC0zLjMxNmMzLjI2OSwtMC4wMzkgNC4wMTcsLTIuNDE5IDQuMDUxLC00LjY2N2MtMS4xNzUsLTEuMTggLTAuNSwtMC40NDkgLTIuMjEzLC0xLjM0MmMwLjQyNywtMS40MzEgMS4zMDMsLTEuNDQ1IDIuMjEzLC0yLjQ2MmMtMC40MTksLTAuMjAxIC0wLjgyNCwtMC40MDYgLTEuMjMxLC0wLjYxMWMxLjc5MSwtMC41MjIgMy4yMzksLTEuNDQ1IDQuOTEsLTIuMDg2Yy0yLjgzMywwLjUyMiAtNC4xMDMsMC4wMjEgLTYuODcyLDEuMzU1YzAuMzYzLC0wLjY1OCAwLjc0LC0xLjMwOCAxLjEwMywtMS45NzRjLTMuMDg2LDAuNDE5IC0yLjUwOCwtMC4yMTQgLTYuMzg1LDAuMTI0YzIuMDk4LC0wLjQxOSA0LjE0NSwtMS4wODEgNi4yNjEsLTEuNDc0Yy0yLjE4OCwtMC4wODEgLTMuODIxLC0wLjQxOSAtNi4yNjEsLTAuMTE5YzAuNzc0LC0wLjMyNSAxLjU1NiwtMC42NTQgMi4zNDIsLTAuOTg3Yy0zLjM2MywwLjQ5NiAtNS45NTMsMC43MjIgLTguNzE4LDAuNzM1YzAuMDk4LDAuNzM5IC0wLjAzLDEuNDMxIC0wLjM3NiwyLjA5NGMtNS41OSwtMC43OTkgLTkuNjU4LDAuNTQ3IC0xNi4yMDEsNS43NjljMC4zNzYsMC4xNTggMi44OCwwLjU3NyAzLjA2LDAuNjExYy0wLjcwOSwwLjIwOSAtMS40NTMsMC4zMjUgLTIuMjA1LDAuMzY4YzYuNzg2LDAuNzA1IDcuMDk4LDQuNDc0IDkuNzU2LDcuODI5Yy0wLjQyMywwLjE1NCAtMC44NDYsMC4yOTUgLTEuMjUyLDAuNDRjMC4xNDEsMC4yNzggMC4yMDksMC43NTcgMC4yNiwxLjM1NWMwLjI4MiwzLjI4MiAtMC4yNTIsMTAuMzcyIDQuOTg3LDEwLjM4OWMwLjE0MSwwIDMuMjI2LC02LjAyNSA1LjM5MywtNi41MDRjMi41NTEsLTAuNTY4IDUuNTksLTMuMzggOC4zNTUsLTUuMDM0Yy0wLjkxOCwtMC4yMzIgLTEuNzY0LC0wLjUwMSAtMi42OTEsLTAuNjEzem05Mi4yMDQsNjcuNzk1Yy0xLjI4MiwtMS4yMDkgLTEuMzcyLC0xLjI4NiAwLDBsMCwwem0tMTA4LjI0NywtNjYuMDM0Yy0xLjMzMywwLjAyMiAtMS4xNjIsMC4wMjIgMCwwbDAsMHptMTEzLjc3Myw3MC4xNDljLTEuODM4LC0xIC00LjAyNiwtMS44MzggLTYuNjIsLTAuODU5YzIuMTUsMS4zMDggNC41ODEsMS4zNjggNi42MiwwLjg1OXptLTEzMS44MTEsLTIwLjMyNGMxLjA3MywtMC4zNzYgMi41NiwwLjAyNiAzLjUzNCwtMC4zNzZjLTMuMTcxLC0xLjg5OCAtMy4xNDksLTAuNjIgLTMuNTM0LDAuMzc2em00LjA1NSwtMC4yMTRjMS4zLDAuMDUxIDEuMzg5LDAuMDUxIDAsMGwwLDB6bS0xMi4wMDgsLTMuMTI4YzMuMTU0LDAuNTU1IDUuMDk4LDEuMDEzIDcuMjM5LDEuNjU4Yy0yLjU4OSwtMS43MzUgLTQuMzQ2LC0zLjAyMiAtNy4yMzksLTEuNjU4em00LjIwOSwzLjIyMmMxLjU2OSwtMC4xMzcgMi4wNjUsLTAuMTg0IDAsMGwwLDB6bTExMS43MzksOS4yNzNjMC40MTksLTEuNjA3IC0wLjcxOCwtMS43MDUgLTAuOTIzLC0yLjc2OWMwLjI3MywxLjQ3OSAtMS42MjQsMi43MjMgMC45MjMsMi43Njl6bTI4LjcsLTQuMDNjMS4wMzUsLTAuMTQ5IDEuMDQ3LC0wLjE0OSAwLDBsMCwwem0wLjMxMiwtMi4yMzljMS4xNSwwLjczMSAxLjg5NCwxLjIwMSAwLDBsMCwwem0wLjQ4Myw0LjUwOWMwLjQzNiwwIC0yLjI2OSwwLjAzIDAsMGwwLDB6bS0xMC4xNTgsLTcuNjYzYzEuMjAxLC0xLjgyNSAtMC4xOTIsLTEuNDMxIC0wLjI0OCwtMS41OThjMC4yMzEsMC43MDUgLTEuMzgsMS4zODUgMC4yNDgsMS41OTh6bTkuMDY0LDQuMjI3Yy0wLjg4LC0yLjYxNSAwLjI5NSwwLjkxIDAsMGwwLDB6bTIuOTM2LDAuODU5Yy0xLjEyNCwtMC4zODkgLTAuNDUzLC0wLjE1NCAwLDBsMCwwem0tMS41OSwyLjc2NWMwLjI4MiwwLjA0MyAxLjIxNCwxLjYzNyAxLjQ3LDEuOTAyYzEuODU5LC0zLjcyMyAwLjAxOCwtMS42MzcgLTEuNDcsLTEuOTAyem0tMy43ODYsMTMuMTE1YzAuNjg4LDAuMTk2IDAuOTE5LDAuMjY1IDAsMGwwLDB6bS0xMjAuNDU2LC01MC4wOThjMC4zODksMC4wNDMgMC4wOTgsMC4wMTMgMCwwbDAsMHptMTIzLjM4MywzMS41NzdjLTAuMTI4LC0wLjM3NiAtMC40MjMsLTEuNzE0IC0wLjEyNCwtMi4xNWMtMS4xNTgsMC40MTUgLTMuNDIzLDAuMTM3IDAuMTI0LDIuMTV6bTE0LjM2NCwyMC4yNjVjLTAuMDczLDUuOTk2IC00LjQ0LDYuNzk1IC0zLjk4NywxLjM0NmMtMS4xMzYsLTAuNTM0IC0yLjg1NCwtMC41OTggLTQuMDUxLC0wLjkyM2MtMC4wNiwwLjM1OSAwLjk1NywwLjgzOCAwLjYxMSwxLjE3MWMtMi44MzMsMS41MDQgLTQuOTk2LDIuMzg0IC03LjI0OCwzLjM3NmMtMS4yMzUsMi41NzcgLTcuMTI0LDQuMDU2IC02Ljk2MSw2LjA1MWMwLjE2MiwyLjA2IDAuNzE4LDMuNDk2IDAuODYzLDYuODAzYzAuMzMzLDcuODY3IDkuNzEzLC00LjY3NSAxMy4wNDMsMi43MjZjMC4xODgsLTAuMTY2IDEuODMzLC0xLjcwOSAxLjk1NywtMS43NzNjLTAuMjMxLDAuNzMxIDAuMDc3LDEuMTU4IC0wLjYxNSwxLjgzN2MwLjQyOCwwLjYxNSAwLjgxMiwtMC40MzYgMC45MjMsLTAuNjhjMS41NjQsOS41OSAxNC41NTYsLTEuMzEyIDEwLjczOSwtOC43NzNjLTIuNTczLC0zLjA1OSAtMy45ODgsLTcuMzkyIC01LjI3NCwtMTEuMTYxeiIvPiAgPHBhdGggaWQ9InN2Z183IiBmaWxsPSIjN0U4NDhCIiBkPSJtMTQ3LjM4MjEyMywyNTcuNzU4Nzc3YzEuODUsMCAzLjQzMSwtMC42ODggNC43MjYsLTIuMDg1YzEuMjc4LC0xLjM4MSAyLjE2MywtMi45NDkgMi42MjQsLTQuNjg0YzAuNDc0LC0xLjcyMiAwLjY4OCwtMy4xNjcgMC42ODgsLTQuMzU5YzAsLTIuNzM1IC0wLjU3NywtNC43NzQgLTEuNzc4LC02LjEyNGMtMS4xNjcsLTEuMzI1IC0yLjYxNSwtMi4wMDkgLTQuMzI5LC0yLjAwOWMtMi41NzMsMCAtNC41OSwxLjE5MiAtNi4wNTEsMy41OWMtMS40NDksMi4zNzYgLTIuMTc1LDQuOTI3IC0yLjE3NSw3LjY2MmMwLDIuMTc1IDAuNTIxLDQuMDQzIDEuNTU2LDUuNjExYzEuMDU5LDEuNTgyIDIuNjE5LDIuMzk4IDQuNzM5LDIuMzk4em0tMS45MjMsLTEzLjQxOWMwLjc3NCwtMi4yMTMgMS45OTIsLTMuMzUgMy41OTQsLTMuMzVjMS4wMTcsMCAxLjgyNSwwLjM2MyAyLjM4LDEuMTM3YzAuNTksMC43NTIgMC44ODksMS44MzMgMC44ODksMy4yNjVjMCwxLjk5NSAtMC40MDYsNC4xMzIgLTEuMTg4LDYuNDA2Yy0wLjc4MiwyLjI5MSAtMi4wMTcsMy40NTMgLTMuNzI3LDMuNDUzYy0wLjk3OCwwIC0xLjc1MiwtMC4zOTcgLTIuMzE2LC0xLjE4NGMtMC41NjQsLTAuNzgyIC0wLjgyMSwtMS44NTkgLTAuODIxLC0zLjI5MWMwLjAwMSwtMi4wNDMgMC4zODEsLTQuMjAxIDEuMTg5LC02LjQzNnptMTMuODc2LC0yLjI1MmMtMS40NDUsMi4zNzYgLTIuMTcxLDQuOTI3IC0yLjE3MSw3LjY2MmMwLDIuMTc1IDAuNTA1LDQuMDQzIDEuNTU2LDUuNjExYzEuMDU1LDEuNTgxIDIuNjE5LDIuMzk3IDQuNzM5LDIuMzk3YzEuODM3LDAgMy40MTksLTAuNjg4IDQuNzE4LC0yLjA4NWMxLjI3MywtMS4zODEgMi4xNzEsLTIuOTQ5IDIuNjE2LC00LjY4NGMwLjQ4MywtMS43MjIgMC42OTcsLTMuMTY3IDAuNjk3LC00LjM1OWMwLC0yLjczNSAtMC41ODYsLTQuNzc0IC0xLjc4MiwtNi4xMjRjLTEuMTYyLC0xLjMyNSAtMi42MTEsLTIuMDA5IC00LjMyLC0yLjAwOWMtMi41ODMsMC4wMDIgLTQuNTk2LDEuMTk0IC02LjA1MywzLjU5MXptNS43OTQsLTEuMDk4YzEuMDEzLDAgMS44MTIsMC4zNjMgMi4zODEsMS4xMzdjMC41ODEsMC43NTIgMC44OCwxLjgzMyAwLjg4LDMuMjY1YzAsMS45OTUgLTAuNDA2LDQuMTMyIC0xLjE4OCw2LjQwNmMtMC43NzQsMi4yOTEgLTIuMDE3LDMuNDUzIC0zLjcyNywzLjQ1M2MtMC45NywwIC0xLjc1MiwtMC4zOTcgLTIuMzEyLC0xLjE4NGMtMC41NjgsLTAuNzgyIC0wLjgyOSwtMS44NTkgLTAuODI5LC0zLjI5MWMwLC0yLjA0MyAwLjM5MywtNC4yMDEgMS4xODQsLTYuNDM2YzAuNzkxLC0yLjIxMyAyLjAwMSwtMy4zNSAzLjYxMSwtMy4zNXptMTYuMzQyLC0yLjQ5MWMtMi41NzcsMCAtNC41ODUsMS4xOTIgLTYuMDU2LDMuNTljLTEuNDU3LDIuMzc2IC0yLjE4OCw0LjkyNyAtMi4xODgsNy42NjJjMCwyLjE3NSAwLjUwOCw0LjA0MyAxLjU3Nyw1LjYxMWMxLjA0MiwxLjU4MSAyLjYyNCwyLjM5NyA0Ljc0NCwyLjM5N2MxLjg0MSwwIDMuNDE5LC0wLjY4OCA0LjY5NiwtMi4wODVjMS4yOTksLTEuMzgxIDIuMTk2LC0yLjk0OSAyLjY0MSwtNC42ODRjMC40ODMsLTEuNzIyIDAuNzAxLC0zLjE2NyAwLjcwMSwtNC4zNTljMCwtMi43MzUgLTAuNTksLTQuNzc0IC0xLjc3OCwtNi4xMjRjLTEuMTk2LC0xLjMyNSAtMi42MTksLTIuMDA4IC00LjMzNywtMi4wMDh6bTEuODI1LDEzLjI5OGMtMC43NzQsMi4yOTEgLTIuMDA5LDMuNDUzIC0zLjcyNywzLjQ1M2MtMC45OTEsMCAtMS43NTIsLTAuMzk3IC0yLjMxMiwtMS4xODRjLTAuNTY4LC0wLjc4MiAtMC44NTUsLTEuODU5IC0wLjg1NSwtMy4yOTFjMCwtMi4wNDMgMC40MTQsLTQuMjAxIDEuMTkyLC02LjQzNmMwLjgxMiwtMi4yMTMgMiwtMy4zNSAzLjYwMiwtMy4zNWMxLjAxNywwIDEuODEyLDAuMzYzIDIuNDA2LDEuMTM3YzAuNTgxLDAuNzUyIDAuODYzLDEuODMzIDAuODYzLDMuMjY1YzAuMDAyLDEuOTk2IC0wLjM5NSw0LjEzMyAtMS4xNjksNi40MDZ6bTE0LjI1MiwtMTMuMjk4Yy0yLjU4MSwwIC00LjU4NSwxLjE5MiAtNi4wNDcsMy41OWMtMS40NDksMi4zNzYgLTIuMTg4LDQuOTI3IC0yLjE4OCw3LjY2MmMwLDIuMTc1IDAuNTI2LDQuMDQzIDEuNTY0LDUuNjExYzEuMDU1LDEuNTgxIDIuNjE2LDIuMzk3IDQuNzQ0LDIuMzk3YzEuODMzLDAgMy40MjMsLTAuNjg4IDQuNzIyLC0yLjA4NWMxLjI2OSwtMS4zODEgMi4xNTgsLTIuOTQ5IDIuNjE5LC00LjY4NGMwLjQ3OSwtMS43MjIgMC42OTcsLTMuMTY3IDAuNjk3LC00LjM1OWMwLC0yLjczNSAtMC41ODksLTQuNzc0IC0xLjc3OCwtNi4xMjRjLTEuMTY2LC0xLjMyNSAtMi42MjMsLTIuMDA4IC00LjMzMywtMi4wMDh6bTEuODIsMTMuMjk4Yy0wLjc4MiwyLjI5MSAtMi4wMjUsMy40NTMgLTMuNzI2LDMuNDUzYy0wLjk3OCwwIC0xLjc1NywtMC4zOTcgLTIuMzI1LC0xLjE4NGMtMC41NTEsLTAuNzgyIC0wLjgyLC0xLjg1OSAtMC44MiwtMy4yOTFjMCwtMi4wNDMgMC4zOTgsLTQuMjAxIDEuMTk3LC02LjQzNmMwLjc3MywtMi4yMTMgMS45OTUsLTMuMzUgMy41OSwtMy4zNWMxLjAyNiwwIDEuODIsMC4zNjMgMi4zODksMS4xMzdjMC41ODEsMC43NTIgMC44ODksMS44MzMgMC44ODksMy4yNjVjLTAuMDAxLDEuOTk2IC0wLjQyLDQuMTMzIC0xLjE5NCw2LjQwNnptMTUuMDQ3LC05LjcwOWMtMS40NDgsMi4zNzYgLTIuMTU4LDQuOTI3IC0yLjE1OCw3LjY2MmMwLDIuMTc1IDAuNTE3LDQuMDQzIDEuNTU1LDUuNjExYzEuMDM1LDEuNTgxIDIuNjE2LDIuMzk3IDQuNzM5LDIuMzk3YzEuODUxLDAgMy40MDYsLTAuNjg4IDQuNzA5LC0yLjA4NWMxLjI5LC0xLjM4MSAyLjE1NCwtMi45NDkgMi42MzcsLTQuNjg0YzAuNDUzLC0xLjcyMiAwLjY5MywtMy4xNjcgMC42OTMsLTQuMzU5YzAsLTIuNzM1IC0wLjU4MiwtNC43NzQgLTEuNzc4LC02LjEyNGMtMS4xOTIsLTEuMzI1IC0yLjY0MSwtMi4wMDkgLTQuMzI5LC0yLjAwOWMtMi41NzIsMC4wMDIgLTQuNjE0LDEuMTk0IC02LjA2OCwzLjU5MXptNS43ODIsLTEuMDk4YzEuMDIxLDAgMS44MjUsMC4zNjMgMi40MDYsMS4xMzdjMC41OSwwLjc1MiAwLjg3MiwxLjgzMyAwLjg3MiwzLjI2NWMwLDEuOTk1IC0wLjM4OSw0LjEzMiAtMS4xNzksNi40MDZjLTAuNzc0LDIuMjkxIC0yLjAwNCwzLjQ1MyAtMy43MjIsMy40NTNjLTAuOTkxLDAgLTEuNzc4LC0wLjM5NyAtMi4zMTIsLTEuMTg0Yy0wLjU3MywtMC43ODIgLTAuODM4LC0xLjg1OSAtMC44MzgsLTMuMjkxYzAsLTIuMDQzIDAuMzgsLTQuMjAxIDEuMTc5LC02LjQzNmMwLjgwNCwtMi4yMTMgMS45OTIsLTMuMzUgMy41OTQsLTMuMzV6bTE0LjQzMiwxNi43NjljMS44NSwwIDMuMzkzLC0wLjY4OCA0LjY5MiwtMi4wODVjMS4zMDQsLTEuMzgxIDIuMTc1LC0yLjk0OSAyLjY1NCwtNC42ODRjMC40NTMsLTEuNzIyIDAuNjkyLC0zLjE2NyAwLjY5MiwtNC4zNTljMCwtMi43MzUgLTAuNTg2LC00Ljc3NCAtMS43NzgsLTYuMTI0Yy0xLjE5MiwtMS4zMjUgLTIuNjQ5LC0yLjAwOSAtNC4zMjksLTIuMDA5Yy0yLjU5LDAgLTQuNjI0LDEuMTkyIC02LjA2OCwzLjU5Yy0xLjQ1OCwyLjM3NiAtMi4xNjcsNC45MjcgLTIuMTY3LDcuNjYyYzAsMi4xNzUgMC41MTMsNC4wNDMgMS41NjQsNS42MTFjMS4wMzksMS41ODIgMi42MjUsMi4zOTggNC43NCwyLjM5OHptLTEuOTQ1LC0xMy40MTljMC43OTEsLTIuMjEzIDEuOTc5LC0zLjM1IDMuNTksLTMuMzVjMS4wMTMsMCAxLjgxMiwwLjM2MyAyLjQxLDEuMTM3YzAuNTc3LDAuNzUyIDAuODU5LDEuODMzIDAuODU5LDMuMjY1YzAsMS45OTUgLTAuMzg5LDQuMTMyIC0xLjE2Nyw2LjQwNmMtMC43ODYsMi4yOTEgLTIuMDA5LDMuNDUzIC0zLjczMSwzLjQ1M2MtMC45ODcsMCAtMS43NzgsLTAuMzk3IC0yLjMxMiwtMS4xODRjLTAuNTY1LC0wLjc4MiAtMC44NTUsLTEuODU5IC0wLjg1NSwtMy4yOTFjMC4wMDEsLTIuMDQzIDAuNDAzLC00LjIwMSAxLjIwNiwtNi40MzZ6bTE4LjAxMywxMy40MTljMS44MzgsMCAzLjM5OCwtMC42ODggNC43MDEsLTIuMDg1YzEuMjk5LC0xLjM4MSAyLjE2MiwtMi45NDkgMi42NDEsLTQuNjg0YzAuNDYxLC0xLjcyMiAwLjY5NywtMy4xNjcgMC42OTcsLTQuMzU5YzAsLTIuNzM1IC0wLjU5NCwtNC43NzQgLTEuNzc4LC02LjEyNGMtMS4xODgsLTEuMzI1IC0yLjY0NSwtMi4wMDkgLTQuMzQyLC0yLjAwOWMtMi41NzMsMCAtNC42MDcsMS4xOTIgLTYuMDU5LDMuNTljLTEuNDQ1LDIuMzc2IC0yLjE2Nyw0LjkyNyAtMi4xNjcsNy42NjJjMCwyLjE3NSAwLjUyNiw0LjA0MyAxLjU2LDUuNjExYzEuMDQyLDEuNTgyIDIuNjE5LDIuMzk4IDQuNzQ3LDIuMzk4em0tMS45NDksLTEzLjQxOWMwLjgxMiwtMi4yMTMgMiwtMy4zNSAzLjYwMywtMy4zNWMxLjAxNywwIDEuODEyLDAuMzYzIDIuNDAyLDEuMTM3YzAuNTc3LDAuNzUyIDAuODY3LDEuODMzIDAuODY3LDMuMjY1YzAsMS45OTUgLTAuNDAyLDQuMTMyIC0xLjE3MSw2LjQwNmMtMC43NzQsMi4yOTEgLTIuMDE3LDMuNDUzIC0zLjcyNywzLjQ1M2MtMSwwIC0xLjc3OCwtMC4zOTcgLTIuMzIxLC0xLjE4NGMtMC41NiwtMC43ODIgLTAuODUsLTEuODU5IC0wLjg1LC0zLjI5MWMwLjAwMSwtMi4wNDMgMC4zOTQsLTQuMjAxIDEuMTk3LC02LjQzNnptMTkuOTQ1LC01Ljg0MWMtMi41NjgsMCAtNC41NzcsMS4xOTIgLTYuMDQyLDMuNTljLTEuNDYyLDIuMzc2IC0yLjE4NCw0LjkyNyAtMi4xODQsNy42NjJjMCwyLjE3NSAwLjUyMSw0LjA0MyAxLjU2LDUuNjExYzEuMDMsMS41ODEgMi42MiwyLjM5NyA0Ljc0OCwyLjM5N2MxLjgzNywwIDMuNDIzLC0wLjY4OCA0LjY5MiwtMi4wODVjMS4yOTksLTEuMzgxIDIuMTg4LC0yLjk0OSAyLjY1LC00LjY4NGMwLjQ3NCwtMS43MjIgMC42ODQsLTMuMTY3IDAuNjg0LC00LjM1OWMwLC0yLjczNSAtMC41NzcsLTQuNzc0IC0xLjc2NSwtNi4xMjRjLTEuMTk0LC0xLjMyNSAtMi42MjUsLTIuMDA4IC00LjM0MywtMi4wMDh6bTEuODI5LDEzLjI5OGMtMC43ODIsMi4yOTEgLTIuMDI2LDMuNDUzIC0zLjcyMiwzLjQ1M2MtMS4wMDQsMCAtMS43NjksLTAuMzk3IC0yLjMyOSwtMS4xODRjLTAuNTU2LC0wLjc4MiAtMC44MzgsLTEuODU5IC0wLjgzOCwtMy4yOTFjMCwtMi4wNDMgMC40MDYsLTQuMjAxIDEuMTg0LC02LjQzNmMwLjgwMywtMi4yMTMgMS45OTIsLTMuMzUgMy42MDIsLTMuMzVjMS4wMTMsMCAxLjgyMSwwLjM2MyAyLjQxLDEuMTM3YzAuNTc3LDAuNzUyIDAuODUxLDEuODMzIDAuODUxLDMuMjY1YzAsMS45OTYgLTAuMzgxLDQuMTMzIC0xLjE1OCw2LjQwNnptMjEuMDg1LC0xMy4yOThjLTIuNTY4LDAgLTQuNTg2LDEuMTkyIC02LjA0NywzLjU5Yy0xLjQ0OCwyLjM3NiAtMi4xNzksNC45MjcgLTIuMTc5LDcuNjYyYzAsMi4xNzUgMC41MTcsNC4wNDMgMS41NzcsNS42MTFjMS4wNDcsMS41ODEgMi42MjQsMi4zOTcgNC43MjIsMi4zOTdjMS44NjgsMCAzLjQyMywtMC42ODggNC43MjcsLTIuMDg1YzEuMzA4LC0xLjM4MSAyLjE2NywtMi45NDkgMi42NDUsLTQuNjg0YzAuNDUzLC0xLjcyMiAwLjY5MiwtMy4xNjcgMC42OTIsLTQuMzU5YzAsLTIuNzM1IC0wLjYxMSwtNC43NzQgLTEuNzc4LC02LjEyNGMtMS4xOTIsLTEuMzI1IC0yLjY0MSwtMi4wMDggLTQuMzU5LC0yLjAwOHptMS44NDYsMTMuMjk4Yy0wLjc4MiwyLjI5MSAtMi4wMywzLjQ1MyAtMy43MzEsMy40NTNjLTAuOTg3LDAgLTEuNzY5LC0wLjM5NyAtMi4zMjksLTEuMTg0Yy0wLjU0MywtMC43ODIgLTAuODI1LC0xLjg1OSAtMC44MjUsLTMuMjkxYzAsLTIuMDQzIDAuMzg5LC00LjIwMSAxLjE5MiwtNi40MzZjMC43NjksLTIuMjEzIDEuOTg3LC0zLjM1IDMuNTksLTMuMzVjMS4wMjEsMCAxLjgyNSwwLjM2MyAyLjQwMiwxLjEzN2MwLjU3MywwLjc1MiAwLjg3MiwxLjgzMyAwLjg3MiwzLjI2NWMwLDEuOTk2IC0wLjM4NSw0LjEzMyAtMS4xNzEsNi40MDZ6bTE0LjI0OCwtMTMuMjk4Yy0yLjU2OCwwIC00LjYwMywxLjE5MiAtNi4wNiwzLjU5Yy0xLjQ1MywyLjM3NiAtMi4xNjIsNC45MjcgLTIuMTYyLDcuNjYyYzAsMi4xNzUgMC41MTMsNC4wNDMgMS41NTYsNS42MTFjMS4wNDMsMS41ODEgMi42MTksMi4zOTcgNC43MzksMi4zOTdjMS44MzcsMCAzLjM5NywtMC42ODggNC42OTIsLTIuMDg1YzEuMjk5LC0xLjM4MSAyLjE4LC0yLjk0OSAyLjY1LC00LjY4NGMwLjQ2MiwtMS43MjIgMC43MDEsLTMuMTY3IDAuNzAxLC00LjM1OWMwLC0yLjczNSAtMC41OTksLTQuNzc0IC0xLjc4MiwtNi4xMjRjLTEuMTkzLC0xLjMyNSAtMi42NDYsLTIuMDA4IC00LjMzNCwtMi4wMDh6bTEuODE2LDEzLjI5OGMtMC43NjUsMi4yOTEgLTIsMy40NTMgLTMuNzE4LDMuNDUzYy0wLjk5MSwwIC0xLjc3OCwtMC4zOTcgLTIuMzEyLC0xLjE4NGMtMC41NjgsLTAuNzgyIC0wLjg1OSwtMS44NTkgLTAuODU5LC0zLjI5MWMwLC0yLjA0MyAwLjQwMiwtNC4yMDEgMS4xOTcsLTYuNDM2YzAuNzk5LC0yLjIxMyAyLC0zLjM1IDMuNjAyLC0zLjM1YzEuMDA5LDAgMS44MTIsMC4zNjMgMi4zOTgsMS4xMzdjMC41ODksMC43NTIgMC44NjMsMS44MzMgMC44NjMsMy4yNjVjMCwxLjk5NiAtMC4zODUsNC4xMzMgLTEuMTcxLDYuNDA2em0xNC4yNTIsLTEzLjI5OGMtMi41NjgsMCAtNC42MDcsMS4xOTIgLTYuMDY0LDMuNTljLTEuNDQ4LDIuMzc2IC0yLjE1OCw0LjkyNyAtMi4xNTgsNy42NjJjMCwyLjE3NSAwLjUxMyw0LjA0MyAxLjU2LDUuNjExYzEuMDM0LDEuNTgxIDIuNjA3LDIuMzk3IDQuNzM5LDIuMzk3YzEuODQyLDAgMy40MDYsLTAuNjg4IDQuNjk3LC0yLjA4NWMxLjI5OSwtMS4zODEgMi4xNzEsLTIuOTQ5IDIuNjQxLC00LjY4NGMwLjQ1MywtMS43MjIgMC42OTcsLTMuMTY3IDAuNjk3LC00LjM1OWMwLC0yLjczNSAtMC41NzcsLTQuNzc0IC0xLjc3OCwtNi4xMjRjLTEuMTkzLC0xLjMyNSAtMi42MzMsLTIuMDA4IC00LjMzNCwtMi4wMDh6bTEuODI1LDEzLjI5OGMtMC43NzQsMi4yOTEgLTIuMDE3LDMuNDUzIC0zLjcxOCwzLjQ1M2MtMS4wMTMsMCAtMS43ODYsLTAuMzk3IC0yLjMyNSwtMS4xODRjLTAuNTY0LC0wLjc4MiAtMC44NDIsLTEuODU5IC0wLjg0MiwtMy4yOTFjMCwtMi4wNDMgMC4zODUsLTQuMjAxIDEuMTkyLC02LjQzNmMwLjc5OSwtMi4yMTMgMS45OTIsLTMuMzUgMy41OTQsLTMuMzVjMS4wMTMsMCAxLjgxMiwwLjM2MyAyLjQxLDEuMTM3YzAuNTY4LDAuNzUyIDAuODUsMS44MzMgMC44NSwzLjI2NWMwLjAwMSwxLjk5NiAtMC4zODgsNC4xMzMgLTEuMTYxLDYuNDA2em0xNC4yNDQsLTEzLjI5OGMtMi41NzMsMCAtNC42MDcsMS4xOTIgLTYuMDUyLDMuNTljLTEuNDUzLDIuMzc2IC0yLjE3NSw0LjkyNyAtMi4xNzUsNy42NjJjMCwyLjE3NSAwLjUxNyw0LjA0MyAxLjU2OCw1LjYxMWMxLjAzLDEuNTgxIDIuNjExLDIuMzk3IDQuNzQ4LDIuMzk3YzEuODI1LDAgMy4zOTgsLTAuNjg4IDQuNjg0LC0yLjA4NWMxLjI5OSwtMS4zODEgMi4xNzEsLTIuOTQ5IDIuNjQ1LC00LjY4NGMwLjQ1NywtMS43MjIgMC43MDEsLTMuMTY3IDAuNzAxLC00LjM1OWMwLC0yLjczNSAtMC41ODYsLTQuNzc0IC0xLjc3NCwtNi4xMjRjLTEuMTk2LC0xLjMyNSAtMi42NDksLTIuMDA4IC00LjM0NSwtMi4wMDh6bTEuODI0LDEzLjI5OGMtMC43NzMsMi4yOTEgLTIuMDA4LDMuNDUzIC0zLjcyMiwzLjQ1M2MtMSwwIC0xLjc3OCwtMC4zOTcgLTIuMzI1LC0xLjE4NGMtMC41NiwtMC43ODIgLTAuODQyLC0xLjg1OSAtMC44NDIsLTMuMjkxYzAsLTIuMDQzIDAuNDAyLC00LjIwMSAxLjE5MiwtNi40MzZjMC44MDMsLTIuMjEzIDEuOTk1LC0zLjM1IDMuNTk4LC0zLjM1YzEuMDE3LDAgMS44MTYsMC4zNjMgMi40MDYsMS4xMzdjMC41ODEsMC43NTIgMC44NjMsMS44MzMgMC44NjMsMy4yNjVjMC4wMDEsMS45OTYgLTAuMzg0LDQuMTMzIC0xLjE3LDYuNDA2eiIvPiA8L2c+PC9zdmc+);
	}

	.cc-types__img--visa {
		content: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iNTEycHgiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDUxMiA1MTI7IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCA1MTIgNTEyIiB3aWR0aD0iNTEycHgiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPjxnIGlkPSLlvaLnirZfMV8zXyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAgICA7Ij48ZyBpZD0i5b2i54q2XzEiPjxnPjxwYXRoIGQ9Ik0yMTEuMzI4LDE4NC40NDVsLTIzLjQ2NSwxNDQuMjA4aDM3LjU0MmwyMy40NjgtMTQ0LjIwOCAgICAgSDIxMS4zMjh6IE0xNTYuMjc2LDE4NC40NDVsLTM1Ljc5NCw5OS4xODVsLTQuMjM0LTIxLjM1OGwwLjAwMywwLjAwN2wtMC45MzMtNC43ODdjLTQuMzMyLTkuMzM2LTE0LjM2NS0yNy4wOC0zMy4zMS00Mi4yMjMgICAgIGMtNS42MDEtNC40NzYtMTEuMjQ3LTguMjk2LTE2LjcwNS0xMS41NTlsMzIuNTMxLDEyNC45NDNoMzkuMTE2bDU5LjczMy0xNDQuMjA4SDE1Ni4yNzZ6IE0zMDIuNzk3LDIyNC40OCAgICAgYzAtMTYuMzA0LDM2LjU2My0xNC4yMDksNTIuNjI5LTUuMzU2bDUuMzU3LTMwLjk3MmMwLDAtMTYuNTM0LTYuMjg4LTMzLjc2OC02LjI4OGMtMTguNjMyLDAtNjIuODc1LDguMTQ4LTYyLjg3NSw0Ny43MzkgICAgIGMwLDM3LjI2LDUxLjkyOCwzNy43MjMsNTEuOTI4LDU3LjI4NWMwLDE5LjU2Mi00Ni41NzQsMTYuMDY2LTYxLjk0NCwzLjcyNmwtNS41ODYsMzIuMzczYzAsMCwxNi43NjMsOC4xNDgsNDIuMzgyLDguMTQ4ICAgICBjMjUuNjE2LDAsNjQuMjcyLTEzLjI3MSw2NC4yNzItNDkuMzdDMzU1LjE5MiwyNDQuMjcyLDMwMi43OTcsMjQwLjc4LDMwMi43OTcsMjI0LjQ4eiBNNDU1Ljk5NywxODQuNDQ1aC0zMC4xODUgICAgIGMtMTMuOTM4LDAtMTcuMzMyLDEwLjc0Ny0xNy4zMzIsMTAuNzQ3bC01NS45ODgsMTMzLjQ2MWgzOS4xMzFsNy44MjgtMjEuNDE5aDQ3LjcyOGw0LjQwMywyMS40MTloMzQuNDcyTDQ1NS45OTcsMTg0LjQ0NXogICAgICBNNDEwLjI3LDI3Ny42NDFsMTkuNzI4LTUzLjk2NmwxMS4wOTgsNTMuOTY2SDQxMC4yN3oiIHN0eWxlPSJmaWxsLXJ1bGU6ZXZlbm9kZDtjbGlwLXJ1bGU6ZXZlbm9kZDtmaWxsOiMwMDVCQUM7Ii8+PC9nPjwvZz48L2c+PGcgaWQ9IuW9oueKtl8xXzJfIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3ICAgIDsiPjxnIGlkPSLlvaLnirZfMV8xXyI+PGc+PHBhdGggZD0iTTEwNC4xMzIsMTk4LjAyMmMwLDAtMS41NTQtMTMuMDE1LTE4LjE0NC0xMy4wMTVIMjUuNzE1ICAgICBsLTAuNzA2LDIuNDQ2YzAsMCwyOC45NzIsNS45MDYsNTYuNzY3LDI4LjAzM2MyNi41NjIsMjEuMTQ4LDM1LjIyNyw0Ny41MSwzNS4yMjcsNDcuNTFMMTA0LjEzMiwxOTguMDIyeiIgc3R5bGU9ImZpbGwtcnVsZTpldmVub2RkO2NsaXAtcnVsZTpldmVub2RkO2ZpbGw6I0Y2QUMxRDsiLz48L2c+PC9nPjwvZz48L3N2Zz4=);
	}

	.cc-types__img--mastercard {
		content: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGhlaWdodD0iNi44MjY2NmluIiBzdHlsZT0ic2hhcGUtcmVuZGVyaW5nOmdlb21ldHJpY1ByZWNpc2lvbjsgdGV4dC1yZW5kZXJpbmc6Z2VvbWV0cmljUHJlY2lzaW9uOyBpbWFnZS1yZW5kZXJpbmc6b3B0aW1pemVRdWFsaXR5OyBmaWxsLXJ1bGU6ZXZlbm9kZDsgY2xpcC1ydWxlOmV2ZW5vZGQiIHZpZXdCb3g9IjAgMCA2LjgyNjY2IDYuODI2NjYiIHdpZHRoPSI2LjgyNjY2aW4iIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPjxkZWZzPjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+CiAgIDwhW0NEQVRBWwogICAgLmZpbDUge2ZpbGw6bm9uZX0KICAgIC5maWwwIHtmaWxsOiMwMTFEMzh9CiAgICAuZmlsMSB7ZmlsbDojMDEzNjY4fQogICAgLmZpbDMge2ZpbGw6I0REMkMwMH0KICAgIC5maWwyIHtmaWxsOiNGQUMyM0N9CiAgICAuZmlsNCB7ZmlsbDojRkZGRkZFfQogICBdXT4KICA8L3N0eWxlPjwvZGVmcz48ZyBpZD0iTGF5ZXJfeDAwMjBfMSI+PGcgaWQ9Il8zOTg5NjY2MDgiPjxwYXRoIGNsYXNzPSJmaWwwIiBkPSJNMS4yNTcwNiAxLjc4OTI1bDQuMzEyNTQgMGMwLjIyMjA1OSwwIDAuNDAzNzMyLDAuMTgxNjY5IDAuNDAzNzMyLDAuNDAzNzI4bDAgMi40NDA3MWMwLDAuMjIyMDU5IC0wLjE4MTY3MywwLjQwMzcyOCAtMC40MDM3MzIsMC40MDM3MjhsLTQuMzEyNTQgMGMtMC4yMjIwNTEsMCAtMC40MDM3MjQsLTAuMTgxNjY5IC0wLjQwMzcyNCwtMC40MDM3MjhsMCAtMi40NDA3MWMwLC0wLjIyMjA1OSAwLjE4MTY3MywtMC40MDM3MjggMC40MDM3MjQsLTAuNDAzNzI4eiIgaWQ9Il8zOTg5NzA0OTYiLz48cGF0aCBjbGFzcz0iZmlsMSIgZD0iTTEuMjU3MDYgMS44NjkyNGMtMC4xNzc4OTgsMCAtMC4zMjM3MjgsMC4xNDU4MzUgLTAuMzIzNzI4LDAuMzIzNzMybDAgMi40NDA3MWMwLDAuMTc3ODk4IDAuMTQ1ODMxLDAuMzIzNzMyIDAuMzIzNzI4LDAuMzIzNzMybDQuMzEyNTQgMGMwLjE3NzkwMiwwIDAuMzIzNzM2LC0wLjE0NTgyNyAwLjMyMzczNiwtMC4zMjM3MzJsMCAtMi40NDA3MWMwLC0wLjE3NzkwNiAtMC4xNDU4MzUsLTAuMzIzNzMyIC0wLjMyMzczNiwtMC4zMjM3MzJsLTQuMzEyNTQgMHoiIGlkPSJfMzk4OTY5OTIwIi8+PGNpcmNsZSBjbGFzcz0iZmlsMiIgY3g9IjQuMjE4NzQiIGN5PSIzLjQxMzMzIiBpZD0iXzM5ODk3MDQyNCIgcj0iMS4xOTg0NSIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy40MTMzNyAyLjUyNTljMC4wMjIxOTY5LDAuMDIwMTYxNCAwLjA0MzYyNiwwLjA0MTE0OTYgMC4wNjQyNTk4LDAuMDYyOTAxNmwtMC4xMjg2MDYgMGMwLjAyMDYzMzksLTAuMDIxNzUyIDAuMDQyMDYzLC0wLjA0Mjc0MDIgMC4wNjQyNTk4LC0wLjA2MjkwMTZsOC42NjE0MmUtMDA1IDB6IiBpZD0iXzM5ODk2OTYwOCIvPjxjaXJjbGUgY2xhc3M9ImZpbDMiIGN4PSIyLjYwNzkyIiBjeT0iMy40MTMzMyIgaWQ9Il8zOTg5Njk1NjAiIHI9IjEuMTk4NDUiLz48cGF0aCBjbGFzcz0iZmlsMiIgZD0iTTMuNTM4MDUgMi42NTc1NmMwLjAxNjYxNDIsMC4wMjA0MjEzIDAuMDMyNTc0OCwwLjA0MTM5NzYgMC4wNDc4Mzg2LDAuMDYyOTAxNmwtMC4zNDUxMSAwYzAuMDE1MjYzOCwtMC4wMjE1MDM5IDAuMDMxMjI0NCwtMC4wNDI0ODAzIDAuMDQ3ODM4NiwtMC4wNjI5MDE2bDAuMjQ5NDMzIDB6IiBpZD0iXzM5ODk2OTMyMCIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy42MzEyIDIuNzg5MjFjMC4wMTI1NzQ4LDAuMDIwNTcwOSAwLjAyNDU1MTIsMC4wNDE1Mzk0IDAuMDM1ODkzNywwLjA2MjkwMTZsLTAuNTA3NTI4IDBjMC4wMTEzNDI1LC0wLjAyMTM2MjIgMC4wMjMzMTg5LC0wLjA0MjMzMDcgMC4wMzU4OTM3LC0wLjA2MjkwMTZsMC40MzU3NCAweiIgaWQ9Il8zOTg5Njk1MzYiLz48cGF0aCBjbGFzcz0iZmlsMiIgZD0iTTMuNzAwODIgMi45MjA4NmMwLjAwOTMyMjgzLDAuMDIwNjYxNCAwLjAxODA2NjksMC4wNDE2Mzc4IDAuMDI2MjMyMywwLjA2MjkwMTZsLTAuNjI3NDQ5IDBjMC4wMDgxNjUzNSwtMC4wMjEyNjM4IDAuMDE2OTA5NCwtMC4wNDIyNDAyIDAuMDI2MjMyMywtMC4wNjI5MDE2bDAuNTc0OTg0IDB6IiBpZD0iXzM5ODk2ODM4NCIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy43NTEwOSAzLjA1MjUyYzAuMDA2NTM1NDMsMC4wMjA3MjgzIDAuMDEyNTE1NywwLjA0MTcwNDcgMC4wMTc5MzcsMC4wNjI5MDE2bC0wLjcxMTM4NiAwYzAuMDA1NDIxMjYsLTAuMDIxMTk2OSAwLjAxMTQwMTYsLTAuMDQyMTczMiAwLjAxNzkzNywtMC4wNjI5MDE2bDAuNjc1NTEyIDB6IiBpZD0iXzM5ODk2ODg2NCIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy43ODQ0NiAzLjE4NDE3YzAuMDA0MDIzNjIsMC4wMjA3ODM1IDAuMDA3NTExODEsMC4wNDE3NTIgMC4wMTA0NDg4LDAuMDYyOTAxNmwtMC43NjMxNSAwYzAuMDAyOTM3MDEsLTAuMDIxMTQ5NiAwLjAwNjQyNTIsLTAuMDQyMTE4MSAwLjAxMDQ0ODgsLTAuMDYyOTAxNmwwLjc0MjI1MiAweiIgaWQ9Il8zOTg5Njg1MDQiLz48cGF0aCBjbGFzcz0iZmlsMiIgZD0iTTMuODAyNDYgMy4zMTU4MmMwLjAwMTY3NzE3LDAuMDIwODIyOCAwLjAwMjgxMTAyLDAuMDQxNzk1MyAwLjAwMzQwOTQ1LDAuMDYyOTAxNmwtMC43ODUwNzEgMGMwLjAwMDU5ODQyNSwtMC4wMjExMDYzIDAuMDAxNzMyMjgsLTAuMDQyMDc4NyAwLjAwMzQwOTQ1LC0wLjA2MjkwMTZsMC43NzgyNTIgMHoiIGlkPSJfMzk4OTY4MTY4Ii8+PHBhdGggY2xhc3M9ImZpbDIiIGQ9Ik0zLjgwNTg4IDMuNDQ3NDhjLTAuMDAwNTk0NDg4LDAuMDIxMTA2MyAtMC4wMDE3MjQ0MSwwLjA0MjA4MjcgLTAuMDAzMzkzNywwLjA2MjkwMTZsLTAuNzc4MzE1IDBjLTAuMDAxNjY5MjksLTAuMDIwODE4OSAtMC4wMDI3OTkyMSwtMC4wNDE3OTUzIC0wLjAwMzM5MzcsLTAuMDYyOTAxNmwwLjc4NTEwMiAweiIgaWQ9Il8zOTg5Njc4MDgiLz48cGF0aCBjbGFzcz0iZmlsMiIgZD0iTTMuNzk0OTcgMy41NzkxM2MtMC4wMDI5MjkxMywwLjAyMTE0NTcgLTAuMDA2NDA5NDUsMC4wNDIxMTgxIC0wLjAxMDQyNTIsMC4wNjI5MDE2bC0wLjc0MjQzMyAwYy0wLjAwNDAxNTc1LC0wLjAyMDc4MzUgLTAuMDA3NDk2MDYsLTAuMDQxNzU1OSAtMC4wMTA0MjUyLC0wLjA2MjkwMTZsMC43NjMyODMgMHoiIGlkPSJfMzk4OTY4MjQwIi8+PHBhdGggY2xhc3M9ImZpbDIiIGQ9Ik0zLjc2OTE0IDMuNzEwNzhjLTAuMDA1NDEzMzksMC4wMjExOTY5IC0wLjAxMTM4MTksMC4wNDIxNzMyIC0wLjAxNzkwOTQsMC4wNjI5MDE2bC0wLjY3NTc5NSAwYy0wLjAwNjUyNzU2LC0wLjAyMDcyODMgLTAuMDEyNDk2MSwtMC4wNDE3MDQ3IC0wLjAxNzkwOTQsLTAuMDYyOTAxNmwwLjcxMTYxNCAweiIgaWQ9Il8zOTg5Njc2NDAiLz48cGF0aCBjbGFzcz0iZmlsMiIgZD0iTTMuNzI3MjMgMy44NDI0NGMtMC4wMDgxNTc0OCwwLjAyMTI2MzggLTAuMDE2ODg1OCwwLjA0MjI0MDIgLTAuMDI2MjAwOCwwLjA2MjkwMTZsLTAuNTc1Mzk0IDBjLTAuMDA5MzE0OTYsLTAuMDIwNjYxNCAtMC4wMTgwNDMzLC0wLjA0MTYzNzggLTAuMDI2MjAwOCwtMC4wNjI5MDE2bDAuNjI3Nzk1IDB6IiBpZD0iXzM5ODk2Njk5MiIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy42NjczNCAzLjk3NDA5Yy0wLjAxMTMzMDcsMC4wMjEzNTgzIC0wLjAyMzI5OTIsMC4wNDIzMzA3IC0wLjAzNTg1ODMsMC4wNjI5MDE2bC0wLjQzNjI5OSAwYy0wLjAxMjU1OTEsLTAuMDIwNTcwOSAtMC4wMjQ1Mjc2LC0wLjA0MTU0MzMgLTAuMDM1ODU4MywtMC4wNjI5MDE2bDAuNTA4MDE2IDB6IiBpZD0iXzM5ODk2NzQ3MiIvPjxwYXRoIGNsYXNzPSJmaWwyIiBkPSJNMy41ODYyMSA0LjEwNTc0Yy0wLjAxNTI0OCwwLjAyMTUgLTAuMDMxMTg1LDAuMDQyNDgwMyAtMC4wNDc3ODM1LDAuMDYyOTAxNmwtMC4yNTAxODkgMGMtMC4wMTY1OTg0LC0wLjAyMDQyMTMgLTAuMDMyNTM1NCwtMC4wNDE0MDE2IC0wLjA0Nzc4MzUsLTAuMDYyOTAxNmwwLjM0NTc1NiAweiIgaWQ9Il8zOTg5NjcxMTIiLz48cGF0aCBjbGFzcz0iZmlsNCIgZD0iTTUuMTgyNDEgMy42MTY4MWwwIC0wLjQwNjk1NyAtMC4xMTQyNDQgMCAwIDAuMTQwNTgzYy0wLjAxMTA4MjcsLTAuMDExODQ2NSAtMC4wMjM3Nzk1LC0wLjAyMDY1MzUgLTAuMDM4MTc3MiwtMC4wMjY2MjYgLTAuMDE0Mjk5MiwtMC4wMDU4NzAwOCAtMC4wMzAyMTY1LC0wLjAwODgwMzE1IC0wLjA0NzgzMDcsLTAuMDA4ODAzMTUgLTAuMDM2MDk4NCwwIC0wLjA2NTc0NDEsMC4wMTI5NzY0IC0wLjA4OTE0MTcsMC4wMzg5MzMxIC0wLjAyMzQwNTUsMC4wMjU5NTY3IC0wLjAzNTE0OTYsMC4wNjMzNzAxIC0wLjAzNTE0OTYsMC4xMTIzNDMgMCwwLjA0MzY3MzIgMC4wMTA2MTAyLDAuMDgwODk3NiAwLjAzMTczNjIsMC4xMTE1IDAuMDIxMjIwNSwwLjAzMDU5ODQgMC4wNTE5MTM0LDAuMDQ1ODUwNCAwLjA5MTk4MDMsMC4wNDU4NTA0IDAuMDE5OTg4MiwwIDAuMDM4MTc3MiwtMC4wMDQyNjc3MiAwLjA1NDM3OCwtMC4wMTI3OTEzIDAuMDEyMjIwNSwtMC4wMDY1MzU0MyAwLjAyNTc2MzgsLTAuMDE5MTMzOSAwLjA0MDU0MzMsLTAuMDM3NzA0N2wwIDAuMDQzNjczMiAwLjEwNTkwNiAwem0tMC4xMjgwNzUgLTAuMjAwNTQzYzAuMDA5NzU5ODQsMC4wMTEzNzAxIDAuMDE0Njg1LDAuMDI4NzA0NyAwLjAxNDY4NSwwLjA1MTkxMzQgMCwwLjAyNTY3MzIgLTAuMDA0ODMwNzEsMC4wNDQxNDU3IC0wLjAxNDM5NzYsMC4wNTU1MTE4IC0wLjAwOTY2MTQyLDAuMDExMjcxNyAtMC4wMjE2MDI0LDAuMDE2OTU2NyAtMC4wMzU5OTYxLDAuMDE2OTU2NyAtMC4wMTM0NTI4LDAgLTAuMDI0ODIyOCwtMC4wMDU1OTA1NSAtMC4wMzM5MTczLC0wLjAxNjg2MjIgLTAuMDA5MTg4OTgsLTAuMDExMjc1NiAtMC4wMTM3MzIzLC0wLjAyODg5MzcgLTAuMDEzNzMyMywtMC4wNTMwNDcyIDAsLTAuMDI1ODYyMiAwLjAwNDQ0ODgyLC0wLjA0NDI0NDEgMC4wMTMyNTk4LC0wLjA1NTEzMzkgMC4wMDg5MDU1MSwtMC4wMTA5ODgyIDAuMDE5ODkzNywtMC4wMTY0ODQzIDAuMDMyOTY4NSwtMC4wMTY0ODQzIDAuMDE0OTY4NSwwIDAuMDI3Mzc0LDAuMDA1NjgxMSAwLjAzNzEyOTksMC4wMTcxNDU3eiIgaWQ9Il8zOTg5NjY4OTYiLz48cGF0aCBjbGFzcz0iZmlsNCIgZD0iTTQuNzEyNjQgMy4zNjk5NGwwIC0wLjA0ODEyMiAtMC4xMDU5MDYgMCAwIDAuMjk0OTg0IDAuMTEzNjczIDAgMCAtMC4wOTkwODY2YzAsLTAuMDQ3MjcxNyAwLjAwNTg3NDAyLC0wLjA3OTU3NDggMC4wMTc1MjM2LC0wLjA5NjgxMSAwLjAwODE0OTYxLC0wLjAxMjIyNDQgMC4wMTk3MDQ3LC0wLjAxODM4MTkgMC4wMzQ0ODQzLC0wLjAxODM4MTkgMC4wMDc3NjM3OCwwIDAuMDE4Mzc4LDAuMDAyNzQ4MDMgMC4wMzE3MzIzLDAuMDA4MzM0NjVsMC4wMzUwNTEyIC0wLjA4MDIyODNjLTAuMDE5Nzk5MiwtMC4wMTA0MjEzIC0wLjAzODA4MjcsLTAuMDE1NjI5OSAtMC4wNTQ4NTQzLC0wLjAxNTYyOTkgLTAuMDE1OTA5NCwwIC0wLjAyOTI2NzcsMC4wMDM4Nzc5NSAtMC4wNDAxNTc1LDAuMDExNzQ0MSAtMC4wMTA3OTkyLDAuMDA3ODU4MjcgLTAuMDIxMzE4OSwwLjAyMjI1NTkgLTAuMDMxNTQ3MiwwLjA0MzE5Njl6IiBpZD0iXzM5ODk2Njc3NiIvPjxwYXRoIGNsYXNzPSJmaWw0IiBkPSJNNC41Mzc5NiAzLjQyMzY1YzAsLTAuMDEzOTI1MiAtMC4wMDI3NDQwOSwtMC4wMjg1MTE4IC0wLjAwODMzNDY1LC0wLjA0Mzc1OTggLTAuMDA1NDk2MDYsLTAuMDE1MjU5OCAtMC4wMTMwNzA5LC0wLjAyNzA5NDUgLTAuMDIyNzQwMiwtMC4wMzU0MzMxIC0wLjAxMzYzNzgsLTAuMDEyMDMxNSAtMC4wMzA3ODM1LC0wLjAxOTk4NDMgLTAuMDUxMjQ4LC0wLjAyMzc3NTYgLTAuMDIwNTU1MSwtMC4wMDM3OTEzNCAtMC4wNDc2NDU3LC0wLjAwNTY4MTEgLTAuMDgxNDYwNiwtMC4wMDU2ODExIC0wLjAyMTEyOTksMCAtMC4wNDA3NDAyLDAuMDAxNjA2MyAtMC4wNTg4MzA3LDAuMDA0NzMyMjggLTAuMDE4MDk0NSwwLjAwMzEyNTk4IC0wLjAzMjMwMzEsMC4wMDc1NzQ4IC0wLjA0MjYyOTksMC4wMTMzNTgzIC0wLjAxNDQ5MjEsMC4wMDc5NTY2OSAtMC4wMjU0ODQzLDAuMDE3MzM4NiAtMC4wMzMxNTc1LDAuMDI4MjI4MyAtMC4wMDc2NjkyOSwwLjAxMDc5OTIgLTAuMDEzNTQzMywwLjAyNTY2OTMgLTAuMDE3NjE4MSwwLjA0NDYxODFsMC4xMDc5OTIgMC4wMTEzNjYxYzAuMDA0NDU2NjksLTAuMDEyODg1OCAwLjAxMDIzMjMsLTAuMDIxNTk4NCAwLjAxNzQzMzEsLTAuMDI2MzM0NiAwLjAwOTI4MzQ2LC0wLjAwNTk2NDU3IDAuMDIzMTEwMiwtMC4wMDg5MDE1NyAwLjA0MTU4NjYsLTAuMDA4OTAxNTcgMC4wMTQzOTc2LDAgMC4wMjQ0MzcsMC4wMDI3NDQwOSAwLjAzMDEyMiwwLjAwODMzNDY1IDAuMDA1Nzc5NTMsMC4wMDU1ODY2MSAwLjAwODYyMjA1LDAuMDE1MjQ4IDAuMDA4NjIyMDUsMC4wMjkxNzcyIC0wLjAxNDAyMzYsMC4wMDU1ODY2MSAtMC4wMjczNzgsMC4wMTAwMzk0IC0wLjAzOTk3NjQsMC4wMTM0NDg4IC0wLjAxMjU5ODQsMC4wMDM1MDc4NyAtMC4wNDAwNzQ4LDAuMDA5NDcyNDQgLTAuMDgyNDE3MywwLjAxOCAtMC4wMzUzMzA3LDAuMDA3MDA3ODcgLTAuMDU5NTgyNywwLjAxNzgxMSAtMC4wNzI3NTIsMC4wMzIzMDMxIC0wLjAxMzE2NTQsMC4wMTQ0OTIxIC0wLjAxOTcwMDgsMC4wMzI5NjQ2IC0wLjAxOTcwMDgsMC4wNTU0MTczIDAsMC4wMjM4NzAxIDAuMDA5MDkwNTUsMC4wNDQwNTEyIDAuMDI3Mzc0LDAuMDYwMzM4NiAwLjAxODE4OSwwLjAxNjM5MzcgMC4wNDQ5MDU1LDAuMDI0NTM5NCAwLjA4MDA0NzIsMC4wMjQ1Mzk0IDAuMDI2NDI5MSwwIDAuMDQ5NjQxNywtMC4wMDQwNzA4NyAwLjA2OTYyOTksLTAuMDEyMjIwNSAwLjAxNDY4MTEsLTAuMDA2MDYyOTkgMC4wMjkzNjYxLC0wLjAxNjIwMDggMC4wNDQxNDE3LC0wLjAzMDQwOTQgMC4wMDEzMjI4MywwLjAwODQzMzA3IDAuMDAyNjQ5NjEsMC4wMTQ1OTA2IDAuMDAzODg1ODMsMC4wMTg1NjY5IDAuMDAxMzIyODMsMC4wMDM5ODAzMSAwLjAwNDA2NjkzLDAuMDA5NjY1MzUgMC4wMDgzMzQ2NSwwLjAxNzI0MDJsMC4xMDU4MTUgMGMtMC4wMDU4Nzc5NSwtMC4wMTIyMjA1IC0wLjAwOTc2Mzc4LC0wLjAyMjI1OTggLTAuMDExNDY0NiwtMC4wMzAxMjIgLTAuMDAxNzk5MjEsLTAuMDA3ODYyMiAtMC4wMDI2NTM1NCwtMC4wMTg3NTk4IC0wLjAwMjY1MzU0LC0wLjAzMjY4MTFsMCAtMC4xMzAzNXptLTAuMTU4OTU3IDAuMDY3NjM3OGMwLjAxNjk1NjcsLTAuMDA0MzU4MjcgMC4wMzMxNTM1LC0wLjAwOTI4MzQ2IDAuMDQ4NjkyOSwtMC4wMTQ4NzAxbDAgMC4wMTgxODVjMCwwLjAxNDIxMjYgLTAuMDAyMjc1NTksMC4wMjU3Njc3IC0wLjAwNjgyNjc3LDAuMDM0NjY5MyAtMC4wMDQ1NDMzMSwwLjAwODkwOTQ1IC0wLjAxMjIxNjUsMC4wMTYzODk4IC0wLjAyMzAxNTcsMC4wMjI1NDcyIC0wLjAxMDg5MzcsMC4wMDYxNTc0OCAtMC4wMjI2Mzc4LDAuMDA5Mjg3NCAtMC4wMzUyMzYyLDAuMDA5Mjg3NCAtMC4wMTIwMzU0LDAgLTAuMDIxMTI5OSwtMC4wMDI4NDY0NiAtMC4wMjczNzgsLTAuMDA4NTI3NTYgLTAuMDA2MTU3NDgsLTAuMDA1Njg1MDQgLTAuMDA5MjgzNDYsLTAuMDEzMDcwOSAtMC4wMDkyODM0NiwtMC4wMjIwNzA5IDAsLTAuMDA3ODYyMiAwLjAwMzEyNTk4LC0wLjAxNDg3OCAwLjAwOTQ3MjQ0LC0wLjAyMTAzNTQgMC4wMDYwNTkwNiwtMC4wMDU5NjQ1NyAwLjAyMDY0OTYsLTAuMDEyMDI3NiAwLjA0MzU3NDgsLTAuMDE4MTg1eiIgaWQ9Il8zOTg5NjcyNTYiLz48cGF0aCBjbGFzcz0iZmlsNCIgZD0iTTQuMTY5MTggMy4zMjk2OWMtMC4wMTQyMDg3LC0wLjA0MzAwNzkgLTAuMDM2MDk0NSwtMC4wNzQ4Mzg2IC0wLjA2NTM2MjIsLTAuMDk1NTg2NiAtMC4wMjkzNjYxLC0wLjAyMDc0MDIgLTAuMDY5NzI0NCwtMC4wMzEwNjY5IC0wLjEyMDk3MiwtMC4wMzEwNjY5IC0wLjA2NTU1MTIsMCAtMC4xMTYyMzIsMC4wMTgwOTA2IC0wLjE1MjEzNCwwLjA1NDE4MTEgLTAuMDM1ODA3MSwwLjAzNjE4OSAtMC4wNTM3MTI2LDAuMDg3OTA5NCAtMC4wNTM3MTI2LDAuMTU1MTY5IDAsMC4wNTAzOTM3IDAuMDEwMjI4MywwLjA5MTg4NTggMC4wMzA1OTQ1LDAuMTI0MTg5IDAuMDIwMzY2MSwwLjAzMjMwMzEgMC4wNDQ1MjM2LDAuMDU0OTQ0OSAwLjA3MjU2MywwLjA2NzgzMDcgMC4wMjgxMzc4LDAuMDEyNzgzNSAwLjA2NDIyNDQsMC4wMTkyMjgzIDAuMTA4NTYzLDAuMDE5MjI4MyAwLjAzNjQ3MjQsMCAwLjA2NjQ5NjEsLTAuMDA1MzA3MDkgMC4wOTAwODY2LC0wLjAxNTgyMjggMC4wMjM2ODExLC0wLjAxMDUxNTcgMC4wNDMzODU4LC0wLjAyNjE0NTcgMC4wNTkzMDMxLC0wLjA0Njg4OTggMC4wMTYwMDc5LC0wLjAyMDY0OTYgMC4wMjc2NTc1LC0wLjA0NjUxMTggMC4wMzUwNDcyLC0wLjA3NzM5MzdsLTAuMTEwMjY0IC0wLjAzMzI1MmMtMC4wMDU1OTA1NSwwLjAyNTY3MzIgLTAuMDE0NDkyMSwwLjA0NTI4MzUgLTAuMDI2ODExLDAuMDU4NzMyMyAtMC4wMTIzMTUsMC4wMTM1NDcyIC0wLjAzMDUsMC4wMjAyNzE3IC0wLjA1NDU2MywwLjAyMDI3MTcgLTAuMDI0ODE4OSwwIC0wLjA0NDA1MTIsLTAuMDA4MzM0NjUgLTAuMDU3NzgzNSwtMC4wMjUwMDc5IC0wLjAxMzczNjIsLTAuMDE2NzY3NyAtMC4wMjA1NTkxLC0wLjA0NzU1NTEgLTAuMDIwNTU5MSwtMC4wOTI2NDU3IDAsLTAuMDM2MjgzNSAwLjAwNTc3OTUzLC0wLjA2Mjk5NjEgMC4wMTcyNDQxLC0wLjA3OTk0ODggMC4wMTUxNTM1LC0wLjAyMjgyNjggMC4wMzcwMzU0LC0wLjAzNDI5MTMgMC4wNjU2NDU3LC0wLjAzNDI5MTMgMC4wMTI1OTg0LDAgMC4wMjM5NjQ2LDAuMDAyNTU1MTIgMC4wMzQyMDA4LDAuMDA3NzYzNzggMC4wMTAxMzM5LDAuMDA1MTE4MTEgMC4wMTg3NTIsMC4wMTI1MDc5IDAuMDI1ODU4MywwLjAyMjA3MDkgMC4wMDQyNjM3OCwwLjAwNTY4ODk4IDAuMDA4MzM4NTgsMC4wMTQ2ODUgMC4wMTIyMjA1LDAuMDI3bDAuMTEwODM1IC0wLjAyNDUzMTV6IiBpZD0iXzM5ODk2NjQ2NCIvPjxwYXRoIGNsYXNzPSJmaWw0IiBkPSJNMy43MzA2NyAzLjQxMDg2bDAuMDM1MDUxMiAtMC4wODAyMjgzYy0wLjAxOTc5NTMsLTAuMDEwNDIxMyAtMC4wMzgwODI3LC0wLjAxNTYyOTkgLTAuMDU0ODQ2NSwtMC4wMTU2Mjk5IC0wLjAxNTkxNzMsMCAtMC4wMjkyNzU2LDAuMDAzODc3OTUgLTAuMDQwMTY1NCwwLjAxMTc0NDEgLTAuMDEwNzk5MiwwLjAwNzg1ODI3IC0wLjAyMTMxMSwwLjAyMjI1NTkgLTAuMDMxNTQzMywwLjA0MzE5NjlsMCAtMC4wNDgxMjIgLTAuMTA1OTA5IDAgMCAwLjI5NDk4NCAwLjExMzY3MyAwIDAgLTAuMDk5MDg2NmMwLC0wLjA0NzI3MTcgMC4wMDU4NzQwMiwtMC4wNzk1NzQ4IDAuMDE3NTIzNiwtMC4wOTY4MTEgMC4wMDgxNDk2MSwtMC4wMTIyMjQ0IDAuMDE5NzA4NywtMC4wMTgzODE5IDAuMDM0NDg0MywtMC4wMTgzODE5IDAuMDA3NzY3NzIsMCAwLjAxODM3OCwwLjAwMjc0ODAzIDAuMDMxNzMyMywwLjAwODMzNDY1eiIgaWQ9Il8zOTg5NjYxNzYiLz48cGF0aCBjbGFzcz0iZmlsNCIgZD0iTTMuNDgwNCAzLjQ4NDY2YzAsLTAuMDM4OTMzMSAtMC4wMDYzNDY0NiwtMC4wNzA1NzQ4IC0wLjAxOTEzMzksLTAuMDk0ODIyOCAtMC4wMTI3ODc0LC0wLjAyNDM1MDQgLTAuMDMxNDQ4OCwtMC4wNDI4MTg5IC0wLjA1NTg4NTgsLTAuMDU1NjEwMiAtMC4wMjQ0NDQ5LC0wLjAxMjc4MzUgLTAuMDU3NjkyOSwtMC4wMTkyMjQ0IC0wLjA5OTk0MDksLTAuMDE5MjI0NCAtMC4wNTIwMDc5LDAgLTAuMDkyODM0NiwwLjAxNDI5OTIgLTAuMTIyMjk5LDAuMDQyODE1IC0wLjAyOTU1NTEsMC4wMjg1MTU3IC0wLjA0NDMzMDcsMC4wNjU5MzMxIC0wLjA0NDMzMDcsMC4xMTIwNjcgMCwwLjAzMjM5NzYgMC4wMDczODk3NiwwLjA2MDYyNiAwLjAyMjA3MDksMC4wODQ2ODUgMC4wMTQ3NzU2LDAuMDIzOTY0NiAwLjAzMzM0NjUsMC4wNDE0ODgyIDAuMDU1Njk2OSwwLjA1MjQ4NDMgMC4wMjI0NTI4LDAuMDExMDc4NyAwLjA1MzE0NTcsMC4wMTY1NzQ4IDAuMDkyMjcxNywwLjAxNjU3NDggMC4wNDQ5OTYxLDAgMC4wNzk1NjY5LC0wLjAwNjQ0NDg4IDAuMTAzNjMsLTAuMDE5MzI2OCAwLjAyNDA2MywtMC4wMTI3ODc0IDAuMDQ0NjE4MSwtMC4wMzQxMDI0IDAuMDYxNjczMiwtMC4wNjM2NTc1bC0wLjExMTQwNiAtMC4wMTAyMzIzYy0wLjAwNzAwNzg3LDAuMDA4ODExMDIgLTAuMDEzNjM3OCwwLjAxNDk2ODUgLTAuMDE5Nzk5MiwwLjAxODQ3MjQgLTAuMDA5OTQ0ODgsMC4wMDU0OTYwNiAtMC4wMjA1NTUxLDAuMDA4MjQwMTYgLTAuMDMxNzMyMywwLjAwODI0MDE2IC0wLjAxNzYxODEsMCAtMC4wMzE5MjUyLC0wLjAwNjM0MjUyIC0wLjA0MjgxNSwtMC4wMTkxMjk5IC0wLjAwNzg2NjE0LC0wLjAwODkwNTUxIC0wLjAxMjY5MjksLTAuMDIyNDU2NyAtMC4wMTQ3Nzk1LC0wLjA0MDU0NzJsMC4yMjY3OCAwIDAgLTAuMDEyNzg3NHptLTAuMTMyNTI0IC0wLjA4ODM4MTljMC4wMDk1NjY5MywwLjAwOTQ3MjQ0IDAuMDE1NTM5NCwwLjAyNTE5NjkgMC4wMTc3MTY1LDAuMDQ3MTczMmwtMC4xMTE2ODUgMGMwLjAwMTg5MzcsLTAuMDE3NzE2NSAwLjAwNjI0ODAzLC0wLjAzMDk3NjQgMC4wMTMwNjY5LC0wLjAzOTg4MTkgMC4wMTA4MDMxLC0wLjAxNDMwMzEgMC4wMjUyMDA4LC0wLjAyMTUgMC4wNDMyMDA4LC0wLjAyMTUgMC4wMTU2Mjk5LDAgMC4wMjgxMzc4LDAuMDA0NzMyMjggMC4wMzc3MDA4LDAuMDE0MjA4N3oiIGlkPSJfMzk4OTY2MTI4Ii8+PHBhdGggY2xhc3M9ImZpbDQiIGQ9Ik0zLjA5OTExIDMuNDA0OGwwIC0wLjA4Mjk3NjQgLTAuMDYxOTUyOCAwIDAgLTAuMTExOTcyIC0wLjExMzExIDAuMDU3ODgxOSAwIDAuMDU0MDkwNiAtMC4wNDE0OTIxIDAgMCAwLjA4Mjk3NjQgMC4wNDE0OTIxIDAgMCAwLjEwMzczNmMwLDAuMDMyODcwMSAwLjAwMzIyNDQxLDAuMDU2NzQwMiAwLjAwOTU2NjkzLDAuMDcxNDI1MiAwLjAwNjQ0MDk0LDAuMDE0NjgxMSAwLjAxNjI5OTIsMC4wMjU2NjU0IDAuMDI5NTU5MSwwLjAzMjg2NjEgMC4wMTMzNTgzLDAuMDA3MjA0NzIgMC4wMzQxMDI0LDAuMDEwODAzMSAwLjA2MjI0MDIsMC4wMTA4MDMxIDAuMDI0MjQ4LDAgMC4wNTAyOTkyLC0wLjAwMzAzMTUgMC4wNzgyNDAyLC0wLjAwOTE4ODk4bC0wLjAwODMzNDY1IC0wLjA3ODE1MzVjLTAuMDE1MDYzLDAuMDA0ODMwNzEgLTAuMDI2NzEyNiwwLjAwNzIwMDc5IC0wLjAzNTA0NzIsMC4wMDcyMDA3OSAtMC4wMDkyODM0NiwwIC0wLjAxNTgxODksLTAuMDAzMTI1OTggLTAuMDE5NTE1NywtMC4wMDkzNzc5NSAtMC4wMDIzNjYxNCwtMC4wMDQwNzQ4IC0wLjAwMzU5ODQzLC0wLjAxMjQxMzQgLTAuMDAzNTk4NDMsLTAuMDI0OTEzNGwwIC0wLjEwNDM5OCAwLjA2MTk1MjggMHoiIGlkPSJfMzk4OTY2MDU2Ii8+PHBvbHlnb24gY2xhc3M9ImZpbDQiIGlkPSJfMzk4OTY2NTM2IiBwb2ludHM9IjIuMTAxOCwzLjYxNjgxIDIuMTAxOCwzLjIwOTg1IDEuOTM2NjgsMy4yMDk4NSAxLjg3MzMxLDMuNDU3NDcgMS44MTAxMywzLjIwOTg1IDEuNjQ0MjYsMy4yMDk4NSAxLjY0NDI2LDMuNjE2ODEgMS43NDcxMywzLjYxNjgxIDEuNzQ3MTMsMy4zMDY1NyAxLjgyNjMyLDMuNjE2ODEgMS45MTk1NCwzLjYxNjgxIDEuOTk4OTIsMy4zMDY1NyAxLjk5ODkyLDMuNjE2ODEgIi8+PHBhdGggY2xhc3M9ImZpbDQiIGQ9Ik0yLjE2OTkxIDMuNDA1OTRsMC4xMDc5OTYgMC4wMTEzNjYxYzAuMDA0NDUyNzYsLTAuMDEyODg1OCAwLjAxMDIzMjMsLTAuMDIxNTk4NCAwLjAxNzQyOTEsLTAuMDI2MzM0NiAwLjAwOTI4NzQsLTAuMDA1OTY0NTcgMC4wMjMxMTQyLC0wLjAwODkwMTU3IDAuMDQxNTg2NiwtMC4wMDg5MDE1NyAwLjAxNDQwMTYsMCAwLjAyNDQ0MDksMC4wMDI3NDQwOSAwLjAzMDEyNiwwLjAwODMzNDY1IDAuMDA1Nzc1NTksMC4wMDU1ODY2MSAwLjAwODYyMjA1LDAuMDE1MjQ4IDAuMDA4NjIyMDUsMC4wMjkxNzcyIC0wLjAxNDAyMzYsMC4wMDU1ODY2MSAtMC4wMjczNzgsMC4wMTAwMzk0IC0wLjAzOTk3NjQsMC4wMTM0NDg4IC0wLjAxMjYwMjQsMC4wMDM1MDc4NyAtMC4wNDAwNzQ4LDAuMDA5NDcyNDQgLTAuMDgyNDE3MywwLjAxOCAtMC4wMzUzMzQ2LDAuMDA3MDA3ODcgLTAuMDU5NTg2NiwwLjAxNzgxMSAtMC4wNzI3NTIsMC4wMzIzMDMxIC0wLjAxMzE2NTQsMC4wMTQ0OTIxIC0wLjAxOTcwNDcsMC4wMzI5NjQ2IC0wLjAxOTcwNDcsMC4wNTU0MTczIDAsMC4wMjM4NzAxIDAuMDA5MDkwNTUsMC4wNDQwNTEyIDAuMDI3Mzc4LDAuMDYwMzM4NiAwLjAxODE4OSwwLjAxNjM5MzcgMC4wNDQ5MDE2LDAuMDI0NTM5NCAwLjA4MDA0NzIsMC4wMjQ1Mzk0IDAuMDI2NDI5MSwwIDAuMDQ5NjM3OCwtMC4wMDQwNzA4NyAwLjA2OTYyOTksLTAuMDEyMjIwNSAwLjAxNDY4MTEsLTAuMDA2MDYyOTkgMC4wMjkzNjIyLC0wLjAxNjIwMDggMC4wNDQxNDE3LC0wLjAzMDQwOTQgMC4wMDEzMjI4MywwLjAwODQzMzA3IDAuMDAyNjQ5NjEsMC4wMTQ1OTA2IDAuMDAzODg1ODMsMC4wMTg1NjY5IDAuMDAxMzIyODMsMC4wMDM5ODAzMSAwLjAwNDA2NjkzLDAuMDA5NjY1MzUgMC4wMDgzMzQ2NSwwLjAxNzI0MDJsMC4xMDU4MTEgMGMtMC4wMDU4NzQwMiwtMC4wMTIyMjA1IC0wLjAwOTc1OTg0LC0wLjAyMjI1OTggLTAuMDExNDYwNiwtMC4wMzAxMjIgLTAuMDAxNzk5MjEsLTAuMDA3ODYyMiAtMC4wMDI2NTM1NCwtMC4wMTg3NTk4IC0wLjAwMjY1MzU0LC0wLjAzMjY4MTFsMCAtMC4xMzAzNWMwLC0wLjAxMzkyNTIgLTAuMDAyNzQ4MDMsLTAuMDI4NTExOCAtMC4wMDgzMzQ2NSwtMC4wNDM3NTk4IC0wLjAwNTQ5NjA2LC0wLjAxNTI1OTggLTAuMDEzMDc0OCwtMC4wMjcwOTQ1IC0wLjAyMjc0MDIsLTAuMDM1NDMzMSAtMC4wMTM2NDE3LC0wLjAxMjAzMTUgLTAuMDMwNzgzNSwtMC4wMTk5ODQzIC0wLjA1MTI0OCwtMC4wMjM3NzU2IC0wLjAyMDU1NTEsLTAuMDAzNzkxMzQgLTAuMDQ3NjQ5NiwtMC4wMDU2ODExIC0wLjA4MTQ2NDYsLTAuMDA1NjgxMSAtMC4wMjExMjk5LDAgLTAuMDQwNzM2MiwwLjAwMTYwNjMgLTAuMDU4ODMwNywwLjAwNDczMjI4IC0wLjAxODA5NDUsMC4wMDMxMjU5OCAtMC4wMzIyOTkyLDAuMDA3NTc0OCAtMC4wNDI2MjYsMC4wMTMzNTgzIC0wLjAxNDQ5NjEsMC4wMDc5NTY2OSAtMC4wMjU0ODQzLDAuMDE3MzM4NiAtMC4wMzMxNTc1LDAuMDI4MjI4MyAtMC4wMDc2NzMyMywwLjAxMDc5OTIgLTAuMDEzNTQzMywwLjAyNTY2OTMgLTAuMDE3NjIyLDAuMDQ0NjE4MXptMC4xNTcwNjMgMC4wODUzNTA0YzAuMDE2OTYwNiwtMC4wMDQzNTgyNyAwLjAzMzE1NzUsLTAuMDA5MjgzNDYgMC4wNDg2OTY5LC0wLjAxNDg3MDFsMCAwLjAxODE4NWMwLDAuMDE0MjEyNiAtMC4wMDIyNzU1OSwwLjAyNTc2NzcgLTAuMDA2ODI2NzcsMC4wMzQ2NjkzIC0wLjAwNDU0MzMxLDAuMDA4OTA5NDUgLTAuMDEyMjE2NSwwLjAxNjM4OTggLTAuMDIzMDE1NywwLjAyMjU0NzIgLTAuMDEwODkzNywwLjAwNjE1NzQ4IC0wLjAyMjYzNzgsMC4wMDkyODc0IC0wLjAzNTIzNjIsMC4wMDkyODc0IC0wLjAxMjAzNTQsMCAtMC4wMjExMjk5LC0wLjAwMjg0NjQ2IC0wLjAyNzM4MTksLTAuMDA4NTI3NTYgLTAuMDA2MTUzNTQsLTAuMDA1Njg1MDQgLTAuMDA5Mjc5NTMsLTAuMDEzMDcwOSAtMC4wMDkyNzk1MywtMC4wMjIwNzA5IDAsLTAuMDA3ODYyMiAwLjAwMzEyNTk4LC0wLjAxNDg3OCAwLjAwOTQ3MjQ0LC0wLjAyMTAzNTQgMC4wMDYwNTkwNiwtMC4wMDU5NjQ1NyAwLjAyMDY0NTcsLTAuMDEyMDI3NiAwLjA0MzU3MDksLTAuMDE4MTg1eiIgaWQ9Il8zOTg5NjY3MjgiLz48cGF0aCBjbGFzcz0iZmlsNCIgZD0iTTIuNTYzMDQgMy4zNTY5N2MtMC4wMTEyNzU2LDAuMDE0MDE1NyAtMC4wMTY4NjIyLDAuMDMwNzgzNSAtMC4wMTY4NjIyLDAuMDUwMjA0NyAwLDAuMDE3ODA3MSAwLjAwNTIwNDcyLDAuMDMzNjI2IDAuMDE1NjI2LDAuMDQ3NTU1MSAwLjAxMDUxMTgsMC4wMTM4MjY4IDAuMDIzMzA3MSwwLjAyMzc3NTYgMC4wMzg1NTUxLDAuMDI5NTUxMiAwLjAxNTM1MDQsMC4wMDU4Nzc5NSAwLjA0MjUzOTQsMC4wMTIyMjA1IDAuMDgxNzUyLDAuMDE5MDQzMyAwLjAyNjI0MDIsMC4wMDQ2NDE3MyAwLjA0MjQ0MDksMC4wMDg1Mjc1NiAwLjA0ODUwMzksMC4wMTE2NTM1IDAuMDA4NTI3NTYsMC4wMDQ0NDg4MiAwLjAxMjc4NzQsMC4wMTA3OTkyIDAuMDEyNzg3NCwwLjAxODk0NDkgMCwwLjAwNzE5Njg1IC0wLjAwMzIyMDQ3LDAuMDEzMjU1OSAtMC4wMDk3NTU5MSwwLjAxNzk5NjEgLTAuMDA4MzM0NjUsMC4wMDY1MzU0MyAtMC4wMjAwODI3LDAuMDA5NzU1OTEgLTAuMDM1MjQwMiwwLjAwOTc1NTkxIC0wLjAxMzkyMTMsMCAtMC4wMjUwMDc5LC0wLjAwMjgzODU4IC0wLjAzMzM0MjUsLTAuMDA4NTIzNjIgLTAuMDA4MzQyNTIsLTAuMDA1NjgxMSAtMC4wMTQ3Nzk1LC0wLjAxNTE1MzUgLTAuMDE5NDIxMywtMC4wMjg0MjEzbC0wLjExMTk2OSAwLjAxMDIzMjNjMC4wMDY0NDA5NCwwLjAyODMyNjggMC4wMjExMjIsMC4wNTAyMDg3IDAuMDQzOTU2NywwLjA2NTU1NTEgMC4wMjI4MjI4LDAuMDE1NDM3IDAuMDYxMTg5LDAuMDIzMTE0MiAwLjExNDk5MiwwLjAyMzExNDIgMC4wMzgwODY2LDAgMC4wNjgyMTI2LC0wLjAwNDczNjIyIDAuMDkwMjgzNSwtMC4wMTQzMDMxIDAuMDIyMDcwOSwtMC4wMDk1NjY5MyAwLjAzODI3MTcsLTAuMDIyMzYyMiAwLjA0ODUsLTAuMDM4NDY0NiAwLjAxMDMyNjgsLTAuMDE2MDk4NCAwLjAxNTQ0NDksLTAuMDMyNjc3MiAwLjAxNTQ0NDksLTAuMDQ5NzI4MyAwLC0wLjAxNjg2NjEgLTAuMDA0ODM0NjUsLTAuMDMyMzA3MSAtMC4wMTQ1OTA2LC0wLjA0NjQyMTMgLTAuMDA5NjYxNDIsLTAuMDE0MTE0MiAtMC4wMjM2ODUsLTAuMDI0OTE3MyAtMC4wNDE5NjQ2LC0wLjAzMjQ5MjEgLTAuMDE4Mzc4LC0wLjAwNzU3ODc0IC0wLjA0NjIyODMsLTAuMDEzNTQ3MiAtMC4wODM4Mzg2LC0wLjAxNzgwNzEgLTAuMDI0NzI0NCwtMC4wMDI5MzcwMSAtMC4wNDA4MjY4LC0wLjAwNjI1NTkxIC0wLjA0ODEyMiwtMC4wMTAwNDMzIC0wLjAwNzI5NTI4LC0wLjAwMzY5MjkxIC0wLjAxMDk4ODIsLTAuMDA5MDk0NDkgLTAuMDEwOTg4MiwtMC4wMTYzODU4IDAsLTAuMDA2NDQwOTQgMC4wMDI5MzcwMSwtMC4wMTE5MzcgMC4wMDg3MTY1NCwtMC4wMTY0ODgyIDAuMDA1Nzc1NTksLTAuMDA0NTQzMzEgMC4wMTUwNjMsLTAuMDA2ODE0OTYgMC4wMjc3NTIsLTAuMDA2ODE0OTYgMC4wMTI1OTg0LDAgMC4wMjMzMDMxLDAuMDAyOTI5MTMgMC4wMzIxMTQyLDAuMDA4ODA3MDkgMC4wMDY0NDQ4OCwwLjAwNDQ0ODgyIDAuMDExMDgyNywwLjAxMTM2NjEgMC4wMTM4MzA3LDAuMDIwNzQ4bDAuMTA2ODU0IC0wLjAxMDIzMjNjLTAuMDA3Mzg1ODMsLTAuMDE5MzIyOCAtMC4wMTY2NjkzLC0wLjAzNDU3ODcgLTAuMDI3ODUwNCwtMC4wNDU5NDQ5IC0wLjAxMTE3MzIsLTAuMDExMjc1NiAtMC4wMjUxMDI0LC0wLjAxOTYwNjMgLTAuMDQxODcwMSwtMC4wMjUwMTE4IC0wLjAxNjY2OTMsLTAuMDA1MzkzNyAtMC4wNDI2MjIsLTAuMDA4MDQzMzEgLTAuMDc3Njc3MiwtMC4wMDgwNDMzMSAtMC4wMzMyNTIsMCAtMC4wNTk3NzU2LDAuMDAzNSAtMC4wNzk1NzA5LDAuMDEwNDE3MyAtMC4wMTk3OTkyLDAuMDA2OTEzMzkgLTAuMDM1MzM4NiwwLjAxNzQyOTEgLTAuMDQ2NjA2MywwLjAzMTU0NzJ6IiBpZD0iXzM5ODk2NjE1MiIvPjwvZz48L2c+PHJlY3QgY2xhc3M9ImZpbDUiIGhlaWdodD0iNi44MjY2NiIgd2lkdGg9IjYuODI2NjYiLz48L3N2Zz4=);
	}

	.cc-types__img--amex {
		content: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDQwIDQwIiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PGcgaWQ9IkUtQ29tIj48ZyBpZD0iQ1ZDXzVfIi8+PGcgaWQ9Ik1hc3RlcmNhcmRfNV8iLz48ZyBpZD0iVmlzYV82XyIvPjxnIGlkPSJEaXNjb3ZlciIvPjxnIGlkPSJBbWV4XzNfIj48ZyBpZD0iQW1leCI+PGc+PHBhdGggY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMzQsOS41SDZjLTEuMSwwLTIsMC45LTIsMnYxN2MwLDEuMSwwLjksMiwyLDJoMjggICAgICBjMS4xLDAsMi0wLjksMi0ydi0xN0MzNiwxMC40LDM1LjEsOS41LDM0LDkuNXoiIGZpbGw9IiMzNDk4RDgiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48L2c+PGcgaWQ9IkFtZXhfMV8iPjxnPjxwYXRoIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTEwLjcsMjAuM2gxLjZsLTAuOC0yTDEwLjcsMjAuM3ogTTMzLDE2LjVoLTQuMWwtMSwxLjEgICAgICBsLTAuOS0xLjFoLTguN2wtMC44LDEuOGwtMC44LTEuOGgtMy41djAuOGwtMC40LTAuOGgtM2wtMi45LDdoMy41bDAuNC0xLjFoMWwwLjQsMS4xaDMuOXYtMC44bDAuMywwLjhoMmwwLjMtMC45djAuOWg4bDEtMS4xICAgICAgbDAuOSwxLjFsNC4xLDBMMzAuMSwyMEwzMywxNi41eiBNMjAuOSwyMi41aC0xLjFsMC0zLjlsLTEuNywzLjloLTFsLTEuNy0zLjl2My45aC0yLjNsLTAuNC0xLjFoLTIuNGwtMC40LDEuMUg4LjZsMi4xLTVoMS43ICAgICAgbDEuOSw0Ljd2LTQuN2gxLjlsMS41LDMuNGwxLjQtMy40aDEuOVYyMi41eiBNMzAuOCwyMi41aC0xLjVMMjgsMjAuOGwtMS41LDEuN2gtNC41di01aDQuNmwxLjQsMS42bDEuNS0xLjZoMS40TDI4LjcsMjAgICAgICBMMzAuOCwyMi41eiBNMjMuMSwxOC41djAuOWgyLjV2MWgtMi41djFoMi44bDEuMy0xLjVMMjYsMTguNUgyMy4xeiIgZmlsbD0iI0ZGRkZGRiIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjwvZz48L2c+PGcgaWQ9IkJpdGNvaW5fM18iLz48ZyBpZD0iR29vZ2xlX1dhbGxldF81XyIvPjxnIGlkPSJQYXlQYWxfM18iLz48ZyBpZD0iU3F1YXJlX1BheW1lbnRfMV8iLz48ZyBpZD0iU2hvcF81XyIvPjxnIGlkPSJQb3N0YWdlIi8+PGcgaWQ9IlBhY2thZ2VfN18iLz48ZyBpZD0iRGlzY291bnRfM18iLz48ZyBpZD0iRWFydGhfM18iLz48ZyBpZD0iQmFyY29kZV8zXyIvPjxnIGlkPSJDYXJ0X1BsdXNfNl8iLz48ZyBpZD0iQ2FydF9NaW51c182XyIvPjxnIGlkPSJDYXJ0XzRfIi8+PGcgaWQ9IlJlY2VpcHRfNV8iLz48ZyBpZD0iVHJ1Y2tfOV8iLz48ZyBpZD0iQ2FsY3VsYXRvcl82XyIvPjxnIGlkPSJFdXJvX1N5bWJvbCIvPjxnIGlkPSJDZW50X1N5bWJvbCIvPjxnIGlkPSJEb2xsYXJfU3ltYm9sIi8+PGcgaWQ9IlBvdW5kX1N5bWJvbCIvPjxnIGlkPSJCYW5rXzVfIi8+PGcgaWQ9IldhbGxldF8zXyIvPjxnIGlkPSJDb2luc182XyIvPjxnIGlkPSJCaWxsc182XyIvPjxnIGlkPSJEb2xsYXJfQWx0Ii8+PGcgaWQ9IkRvbGxhciIvPjwvZz48ZyBpZD0iTG9ja3VwIi8+PC9zdmc+)
	}

	.cc-types__img--disc {
		content: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/PjwhRE9DVFlQRSBzdmcgIFBVQkxJQyAnLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4nICAnaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkJz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDQwIDQwIiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PGcgaWQ9IkUtQ29tIj48ZyBpZD0iQ1ZDXzVfIi8+PGcgaWQ9Ik1hc3RlcmNhcmRfNV8iLz48ZyBpZD0iVmlzYV82XyIvPjxnIGlkPSJEaXNjb3ZlciI+PGcgaWQ9IkRpc2NvdmVyXzNfIj48Zz48cGF0aCBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0zNCw5LjVINmMtMS4xLDAtMiwwLjktMiwydjE3YzAsMS4xLDAuOSwyLDIsMmgyOCAgICAgIGMxLjEsMCwyLTAuOSwyLTJ2LTE3QzM2LDEwLjQsMzUuMSw5LjUsMzQsOS41eiIgZmlsbD0iI0VDRjBGMSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjwvZz48ZyBpZD0iRGlzY292ZXJfMl8iPjxnPjxwYXRoIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTE4LjUsMzAuNUgzNGMxLjEsMCwyLTAuOSwyLTJ2LTYuNkMzMSwyNi4xLDI1LjEsMjksMTguNSwzMC41ICAgICAgeiIgZmlsbD0iI0U2N0UyMiIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjwvZz48ZyBpZD0iRGlzY292ZXJfMV8iPjxnPjxwYXRoIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTEzLjUsMTcuNWMtMC4zLTAuMy0wLjctMC41LTEuMi0wLjZjLTAuNS0wLjEtMC45LTAuMi0xLjQtMC4yICAgICAgSDh2Ni43aDIuN2MwLjQsMCwwLjktMC4xLDEuNC0wLjJjMC41LTAuMSwwLjktMC4zLDEuMy0wLjZjMC40LTAuMywwLjctMC42LDAuOS0xYzAuMi0wLjQsMC40LTAuOSwwLjQtMS41ICAgICAgYzAtMC42LTAuMS0xLjEtMC4zLTEuNUMxNC4xLDE4LjEsMTMuOCwxNy44LDEzLjUsMTcuNXogTTEzLDIxYy0wLjIsMC4zLTAuNCwwLjUtMC42LDAuN2MtMC4zLDAuMi0wLjYsMC4zLTEsMC40ICAgICAgYy0wLjQsMC4xLTAuOCwwLjEtMS4yLDAuMWgtMXYtNC41aDEuMmMwLjQsMCwwLjgsMCwxLjEsMC4xczAuNiwwLjIsMC45LDAuNGMwLjIsMC4yLDAuNCwwLjQsMC42LDAuN3MwLjIsMC42LDAuMiwxICAgICAgQzEzLjMsMjAuNCwxMy4yLDIwLjgsMTMsMjF6IE0xNS45LDIzLjNoMS4zdi02LjdoLTEuM1YyMy4zeiBNMjkuOSwyMS42Yy0wLjIsMC4zLTAuNSwwLjUtMC44LDAuNmMtMC4zLDAuMS0wLjYsMC4yLTAuOSwwLjIgICAgICBjLTAuNCwwLTAuNy0wLjEtMS0wLjJjLTAuMy0wLjEtMC42LTAuMy0wLjgtMC41cy0wLjQtMC41LTAuNS0wLjhjLTAuMS0wLjMtMC4yLTAuNi0wLjItMWMwLTAuMywwLjEtMC42LDAuMi0wLjkgICAgICBzMC4zLTAuNSwwLjUtMC44czAuNS0wLjQsMC44LTAuNWMwLjMtMC4xLDAuNy0wLjIsMS0wLjJjMC4zLDAsMC41LDAsMC44LDAuMWMwLjMsMC4xLDAuNSwwLjMsMC44LDAuNWwxLTAuNyAgICAgIGMtMC40LTAuNC0wLjgtMC43LTEuMi0wLjhjLTAuNC0wLjItMC45LTAuMi0xLjQtMC4yYy0wLjYsMC0xLjEsMC4xLTEuNiwwLjJjLTAuNSwwLjItMC45LDAuNC0xLjIsMC43Yy0wLjMsMC4zLTAuNiwwLjctMC44LDEuMSAgICAgIHMtMC4zLDAuOS0wLjMsMS41YzAsMC41LDAuMSwxLDAuMywxLjRjMC4yLDAuNCwwLjUsMC44LDAuOCwxLjFjMC4zLDAuMywwLjgsMC41LDEuMiwwLjdjMC41LDAuMiwxLDAuMiwxLjYsMC4yICAgICAgYzAuNSwwLDEuMS0wLjEsMS41LTAuM2MwLjUtMC4yLDAuOS0wLjUsMS4yLTAuOUwyOS45LDIxLjZ6IE0zMC44LDE3LjZDMzAuNSwxNy4yLDMwLjgsMTcuNiwzMC44LDE3LjZMMzAuOCwxNy42eiBNMjIuNSwxOS44ICAgICAgYy0wLjMtMC4xLTAuNS0wLjMtMC44LTAuM2MtMC4zLTAuMS0wLjYtMC4yLTAuOC0wLjNjLTAuMy0wLjEtMC41LTAuMi0wLjYtMC4zYy0wLjItMC4xLTAuMy0wLjMtMC4zLTAuNWMwLTAuMiwwLTAuMywwLjEtMC40ICAgICAgYzAuMS0wLjEsMC4yLTAuMiwwLjMtMC4zYzAuMS0wLjEsMC4yLTAuMSwwLjQtMC4yYzAuMSwwLDAuMywwLDAuNCwwYzAuMywwLDAuNSwwLDAuNywwLjFjMC4yLDAuMSwwLjQsMC4yLDAuNiwwLjRsMS0wLjkgICAgICBjLTAuMy0wLjItMC42LTAuNC0xLTAuNWMtMC40LTAuMS0wLjctMC4yLTEuMS0wLjJjLTAuMywwLTAuNywwLTEsMC4xYy0wLjMsMC4xLTAuNiwwLjItMC45LDAuNGMtMC4zLDAuMi0wLjUsMC40LTAuNiwwLjYgICAgICBjLTAuMiwwLjMtMC4yLDAuNS0wLjIsMC45YzAsMC40LDAuMSwwLjcsMC4zLDAuOXMwLjQsMC40LDAuNiwwLjZjMC4zLDAuMSwwLjUsMC4zLDAuOCwwLjNjMC4zLDAuMSwwLjYsMC4yLDAuOCwwLjMgICAgICBjMC4zLDAuMSwwLjUsMC4yLDAuNiwwLjNjMC4yLDAuMSwwLjMsMC4zLDAuMywwLjZjMCwwLjIsMCwwLjMtMC4xLDAuNGMtMC4xLDAuMS0wLjIsMC4yLTAuMywwLjNjLTAuMSwwLjEtMC4zLDAuMS0wLjQsMC4yICAgICAgYy0wLjIsMC0wLjMsMC4xLTAuNSwwLjFjLTAuMywwLTAuNi0wLjEtMC44LTAuMmMtMC4zLTAuMS0wLjUtMC4zLTAuNi0wLjVsLTEsMC45YzAuMywwLjMsMC43LDAuNiwxLjEsMC43ICAgICAgYzAuNCwwLjEsMC45LDAuMiwxLjMsMC4yYzAuNCwwLDAuNywwLDEtMC4xYzAuMy0wLjEsMC42LTAuMiwwLjktMC40czAuNC0wLjQsMC42LTAuN2MwLjEtMC4zLDAuMi0wLjYsMC4yLTAuOSAgICAgIGMwLTAuNC0wLjEtMC43LTAuMy0xUzIyLjcsMjAsMjIuNSwxOS44eiBNMjMuNCwxNy4yQzIzLjQsMTcuMiwyMy4xLDE2LjksMjMuNCwxNy4yTDIzLjQsMTcuMnoiIGZpbGw9IiMzNDQ5NUUiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48L2c+PC9nPjxnIGlkPSJBbWV4XzNfIi8+PGcgaWQ9IkJpdGNvaW5fM18iLz48ZyBpZD0iR29vZ2xlX1dhbGxldF81XyIvPjxnIGlkPSJQYXlQYWxfM18iLz48ZyBpZD0iU3F1YXJlX1BheW1lbnRfMV8iLz48ZyBpZD0iU2hvcF81XyIvPjxnIGlkPSJQb3N0YWdlIi8+PGcgaWQ9IlBhY2thZ2VfN18iLz48ZyBpZD0iRGlzY291bnRfM18iLz48ZyBpZD0iRWFydGhfM18iLz48ZyBpZD0iQmFyY29kZV8zXyIvPjxnIGlkPSJDYXJ0X1BsdXNfNl8iLz48ZyBpZD0iQ2FydF9NaW51c182XyIvPjxnIGlkPSJDYXJ0XzRfIi8+PGcgaWQ9IlJlY2VpcHRfNV8iLz48ZyBpZD0iVHJ1Y2tfOV8iLz48ZyBpZD0iQ2FsY3VsYXRvcl82XyIvPjxnIGlkPSJFdXJvX1N5bWJvbCIvPjxnIGlkPSJDZW50X1N5bWJvbCIvPjxnIGlkPSJEb2xsYXJfU3ltYm9sIi8+PGcgaWQ9IlBvdW5kX1N5bWJvbCIvPjxnIGlkPSJCYW5rXzVfIi8+PGcgaWQ9IldhbGxldF8zXyIvPjxnIGlkPSJDb2luc182XyIvPjxnIGlkPSJCaWxsc182XyIvPjxnIGlkPSJEb2xsYXJfQWx0Ii8+PGcgaWQ9IkRvbGxhciIvPjwvZz48ZyBpZD0iTG9ja3VwIi8+PC9zdmc+);
	}
</style>