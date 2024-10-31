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
$is_editable = false;
if ( ! $status_txt ) {
	$status_txt = '-';
} else {
	switch ( $status_rebill ) {
		case 'active':
			$status_txt = __( 'Active', 'wc-rebill-subscription' );
			break;
		case 'payment_pending':
			$status_txt = __( 'Collection failed', 'wc-rebill-subscription' );
			break;
		case 'failed':
			$status_txt = __( 'Failed', 'wc-rebill-subscription' );
			break;
		case 'paused':
			$status_txt = __( 'Paused', 'wc-rebill-subscription' );
			break;
		case 'cancelled':
			$status_txt = __( 'Cancelled', 'wc-rebill-subscription' );
			break;
		case 'defaulted':
			$status_txt = __( 'Defaulted', 'wc-rebill-subscription' );
			break;
		case '':
			$status_txt = '-';
			break;
	}
}
if ( 'cancelled' !== $status_rebill ) {
	$result             = '';
	$frequency          = $rebill_subscription['frequency'];
	$frequency_type     = $rebill_subscription['frequency_type'];
	$is_synchronization = false;
	switch ( $frequency_type ) {
		case 'day':
			$result = __( 'Pay every N days', 'wc-rebill-subscription' );
			break;
		case 'month':
			$is_synchronization = true;
			$result             = __( 'Pay every N months', 'wc-rebill-subscription' );
			break;
		case 'year':
			$result = __( 'Pay every N years', 'wc-rebill-subscription' );
			break;
	}
	echo '<tr id="input-rebill-change" style="display:none;"><td><b>' . esc_html( __( 'Modify Subscription', 'wc-rebill-subscription' ) ) . '</td><td>';
	if ( $is_synchronization ) {
		$synchronization = $rebill_order_parent->get_date_created()->date( 'd' );
		if ( isset( $rebill_subscription['synchronization'] ) ) {
			$synchronization = $rebill_subscription['synchronization'];
		}
		$synchronization = max( 0, min( 27, (int) $synchronization ) );
	}
	if ( ! isset( $rebill_subscription['frequency_max'] ) ) {
		$rebill_subscription['frequency_max'] = '0';
	}
	echo '<form id="form-rebill-order-update" method="get" action="javascript:void(0);" onsubmit="return false;"> <table>';
	
	$product_edit         = false;
	$frequency_edit       = false;
	$address_edit         = false;
	$frequency_max_edit   = false;
	$synchronization_edit = false;
	foreach ( $rebill_order_parent->get_items() as $item_id => $item ) {
		$product_name = $item['name'];
		$quantity     = $item['qty'];
		$pid          = $item['product_id'];
		$product      = wc_get_product( $pid );
		if ( ! is_a( $product, 'WC_Product_Variation' ) && ! is_a( $product, 'WC_Product_Variable' ) && ! is_a( $product, 'WC_Product' ) ) {
			continue;
		}
		$product_edit         = $product_edit || ( 'yes' === $product->get_meta( 'rebill_product_edit', true ) );
		$frequency_edit       = $frequency_edit || ( 'yes' === $product->get_meta( 'rebill_frequency_edit', true ) );
		$address_edit         = $address_edit || ( 'yes' === $product->get_meta( 'rebill_address_edit', true ) );
		$frequency_max_edit   = $frequency_max_edit || ( 'yes' === $product->get_meta( 'rebill_frequency_max_edit', true ) );
		$list_frequency       = $list_frequency || $product->get_meta( 'rebill_frequency', true );
		$synchronization_edit = $synchronization_edit || $is_synchronization && ( 'yes' === $product->get_meta( 'rebill_synchronization_edit', true ) );
		$is_editable          = $is_editable || $product_edit || $frequency_edit || $address_edit || $frequency_max_edit || $synchronization_edit;
		if ( $product_edit ) {
			?>
			<tr><td><b><?php echo esc_html( __( 'Quantity of', 'wc-rebill-subscription' ) ) . ': ' . esc_html( $product_name ) . ''; ?></b></td><td><input class="form-control" type="text" name="rebill_quantity[<?php echo esc_html( $item_id ); ?>]" value="<?php echo esc_html( $quantity ); ?>" /></td></tr>
			<?php
		}
	}
	if ( $frequency_edit ) {
		if ( ! $list_frequency || is_array( $list_frequency ) && ! count( $list_frequency ) ) {
			$list_frequency = array( 1 );
		}
		if ( ! is_array( $list_frequency ) ) {
			$list_frequency = array( $list_frequency );
		}
		if ( 1 === count( $list_frequency ) ) {
			?>
			<tr><td><b><?php echo esc_html( __( 'Frequency', 'wc-rebill-subscription' ) ) . ' (' . esc_html( $result ) . ')'; ?></b></td><td><input class="form-control" type="text" name="rebill_frequency" value="<?php echo esc_html( $frequency ); ?>" /></td></tr>
			<?php
		} else {
			?>
			<tr><td><b><?php echo esc_html( __( 'Frequency', 'wc-rebill-subscription' ) ) . ' (' . esc_html( $result ) . ')'; ?></b></td><td><select class="form-control" name="rebill_frequency">
			<?php
			foreach ( $list_frequency as $f ) {
				if ( (int) $f === (int) $frequency ) {
					echo '<option selected value="' . (int) $f . '" selected>' . (int) $f . '</option>';
				} else {
					echo '<option value="' . (int) $f . '">' . (int) $f . '</option>';
				}
			}
			?>
			</select></td></tr>
			<?php
		}
	}
	if ( $frequency_max_edit ) {
		?>
		<tr><td><b><?php echo esc_html( __( 'Maximum number of recurring payment (Zero or Empty to infinity recurring payment)', 'wc-rebill-subscription' ) ); ?></b></td><td><input class="form-control" name="rebill_frequency_max" type="text" value="<?php echo esc_html( $rebill_subscription['frequency_max'] ); ?>" /></td></tr>
		<?php
	}
	if ( $synchronization_edit ) {
		?>
		<tr><td><b><?php echo esc_html( __( 'Force collection date to a specific day number (Zero or Empty to use the first payment day)', 'wc-rebill-subscription' ) ); ?></b></td><td><input class="form-control" name="rebill_synchronization" type="text" value="<?php echo esc_html( $synchronization ); ?>" /></td></tr>
		<?php
	}
	?>
	<tr><td></td><td>
		<input type="hidden" name="rebill_order_parent_nonce" value="<?php echo esc_html( wp_create_nonce( 'rebill_front_order_update' ) ); ?>" />
		<input type="hidden" name="order_parent" value="<?php echo esc_html( $rebill_order_parent->get_id() ); ?>" />
		<button class="btn btn-default" type="button" onclick="sendRebillModifySubscription(this);"><?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?></button>
	</td></tr>
	</table>
	<script>
		var rebill_update_order = false;
		function sendRebillModifySubscription(btn) {
			if (rebill_update_order) return;
			jQuery(btn).html('<?php echo esc_html( __( 'Loading...', 'wc-rebill-subscription' ) ); ?>');
			rebill_update_order = true;
			jQuery.ajax({
				type: "POST",
					url: '<?php echo esc_url( rtrim( home_url(), '/' ) . '/' ); ?>'.replaceAll('&#038;', '&').replaceAll('&amp;', '&'),
				data: jQuery('#form-rebill-order-update').serialize(),
				success: function(result) {
					result = JSON.parse(result);
					jQuery(btn).html('<?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?>');
					rebill_update_order = false;
					console.log(result);
					if (result && result.error) {
						alert(result.error);
					} else {
						location.reload(true);
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(xhr.status);
					console.log(thrownError);
					rebill_update_order = false;
						jQuery(btn).html('<?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?>');
				}
			});
		}
		function sendRebillModifyAddress(btn) {
			if (rebill_update_order) return;
			jQuery(btn).html('<?php echo esc_html( __( 'Loading...', 'wc-rebill-subscription' ) ); ?>');
			rebill_update_order = true;
			jQuery.ajax({
				type: "POST",
				url: '<?php echo esc_url( rtrim( home_url(), '/' ) . '/' ); ?>'.replaceAll('&#038;', '&').replaceAll('&amp;', '&'),
				data: jQuery('#form-rebill-address-update').serialize(),
				success: function(result) {
					result = JSON.parse(result);
					jQuery(btn).html('<?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?>');
					rebill_update_order = false;
					console.log(result);
					if (result && result.error) {
						alert(result.error);
					} else {
						location.reload(true);
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(xhr.status);
					console.log(thrownError);
					rebill_update_order = false;
						jQuery(btn).html('<?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?>');
				}
			});
		}
	</script>
	</form>
	<?php
	echo '</td></tr>';
}
if ( $renew_from > 0 && (int) $renew_from !== (int) $renew_from->get_id() ) {
	echo '<tr><td><b>' . esc_html( __( 'Subscription Parent', 'wc-rebill-subscription' ) ) . '</td><td><a href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . (int) $renew_from . '">#' . (int) $renew_from . '</a></td></tr>';
}
echo '<tr><td><b>' . esc_html( __( 'Subscription Status', 'wc-rebill-subscription' ) ) . '</td><td>' . esc_html( $status_txt );
if ( 'payment_pending' !== $status_rebill && 'failed' !== $status_rebill ) {
	?>
	<br /><br />
	<a
	<?php
	echo 'href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . ( (int) $rebill_order->get_id() ) . '?request_renew_card"';
	?>
		class="woocommerce-button button view"><?php echo esc_html( __( 'Change Card', 'wc-rebill-subscription' ) ); ?></a>
	<?php
	if ( 'cancelled' !== $status_rebill ) {
		// translators: %d: Order ID.
		echo '<a onclick="return confirm(\'' . esc_html( sprintf( __( 'Are you completely sure to cancel the subscription #%d? This will be irreversible', 'wc-rebill-subscription' ), $rebill_order->get_id() ) ) . '\');" ';
		echo 'href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . ( (int) $rebill_order->get_id() ) . '?request_cancel" class="woocommerce-button button view">' . esc_html( __( 'Cancel subscription', 'wc-rebill-subscription' ) ) . '</a>';
		if ( $is_editable ) {
			echo ' <a onclick="return jQuery(\'#input-rebill-change\').show() && false;" class="woocommerce-button button view">' . esc_html( __( 'Modify Subscription', 'wc-rebill-subscription' ) ) . '</a>';
		}
	}
}
echo '</td></tr>';
echo '<tr><td><b>' . esc_html( __( 'Next Payment', 'wc-rebill-subscription' ) ) . '</td><td>' . esc_html( $next_payment );
if ( $is_editable && $frequency_max_edit ) {
	echo ' <a onclick="return jQuery(\'#input-rebill-change\').show() && false;">[' . esc_html( __( 'Modify Subscription', 'wc-rebill-subscription' ) ) . ']</a>';
}
echo '</td></tr>';

if ( $rebill_subscription ) {
	$frequency_type = $rebill_subscription['frequency_type'];
	$frequency      = $rebill_subscription['frequency'];
	$result         = '';
	switch ( $frequency_type ) {
		case 'day':
			// translators: %s: Frequency value.
			$result = esc_html( sprintf( _n( 'You pay every %s day', 'You pay every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
			break;
		case 'month':
			// translators: %s: Frequency value.
			$result = esc_html( sprintf( _n( 'You pay every %s month', 'You pay every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
			break;
		case 'year':
			// translators: %s: Frequency value.
			$result = esc_html( sprintf( _n( 'You pay every %s year', 'You pay every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
			break;
	}
	echo '<tr><td><b>' . esc_html( __( 'Frequency', 'wc-rebill-subscription' ) ) . '</b></td><td>' . esc_html( $result );
	if ( $is_editable && $frequency_edit ) {
		echo ' <a onclick="return jQuery(\'#input-rebill-change\').show() && false;">[' . esc_html( __( 'Modify Subscription', 'wc-rebill-subscription' ) ) . ']</a>';
	}
	echo '</td></tr>';
	if ( isset( $rebill_subscription['frequency_max'] ) && $rebill_subscription['frequency_max'] > 0 ) {
		// translators: %s: Repetitions value.
		echo '<tr><td><b>' . esc_html( __( 'Repetitions', 'wc-rebill-subscription' ) ) . '</b></td><td>' . esc_html( sprintf( _n( '%s repetition', ' %s repetitions', $rebill_subscription['frequency_max'], 'wc-rebill-subscription' ), $rebill_subscription['frequency_max'] ) ) . '</td></tr>';
	}
	echo '<tr><td><b>' . esc_html( __( 'Shipping Address', 'wc-rebill-subscription' ) ) . '</td><td>';
	echo $rebill_order_parent->get_formatted_shipping_address();
	if ( $is_editable && $address_edit ) {
		$countries = new WC_Countries();

		$order_id        = (int) $rebill_order_parent->get_id();
		$shipping_fields = $countries->get_address_fields( '', 'shipping_' );
		echo '<br /><a onclick="return jQuery(\'#input-rebill-change-address\').toggle() && false;">[' . esc_html( __( 'Modify Address', 'wc-rebill-subscription' ) ) . ']</a>';
		?>
		<div id="input-rebill-change-address" style="display:none"><br />
		<form id="form-rebill-address-update" method="get" action="javascript:void(0);" onsubmit="return false;">
		<input type="hidden" name="rebill_order_parent_nonce" value="<?php echo esc_html( wp_create_nonce( 'rebill_front_order_update' ) ); ?>" />
		<input type="hidden" name="rebill_address_update" value="1" />
		<input type="hidden" name="order_parent" value="<?php echo esc_html( $order_id ); ?>" />
		<?php foreach ( $shipping_fields as $key => $field ) : ?>

			<?php
			if ( in_array(
				$key,
				array(
					'shipping_country',
					'shipping_first_name',
					'shipping_last_name',
					'shipping_company',
					'shipping_country',
				)
			) ) {
				continue;
			}
			woocommerce_form_field( $key, $field, get_post_meta( $order_id, '_' . $key, true ) );
			?>

		<?php endforeach; ?><br />
		<button class="btn btn-default" type="button" onclick="sendRebillModifyAddress(this);"><?php echo esc_html( __( 'Save', 'wc-rebill-subscription' ) ); ?></button>
		</form>
		</div>
		<?php
	}
	echo '</td></tr>';
}
