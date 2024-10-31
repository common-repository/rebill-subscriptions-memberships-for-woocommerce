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
?>
<style>
	table.my_account_rebill_orders td.woocommerce-orders-table__cell-order-actions {
		white-space: nowrap;
	}
</style>
<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders my_account_rebill_orders account-orders-table">
	<thead>
		<tr>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr">
				<?php echo esc_html( __( 'Subscription Order', 'wc-rebill-subscription' ) ); ?>
			</span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr"><?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr"><?php echo esc_html( __( 'Status' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr"><?php echo esc_html( __( 'Next Payment', 'wc-rebill-subscription' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr"><?php echo esc_html( __( 'Next payment amount', 'wc-rebill-subscription' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions"><span class="nobr"><?php echo esc_html( __( 'Actions', 'wc-rebill-subscription' ) ); ?></span></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $customer_orders as $customer_order ) {
			$rebill_order       = wc_get_order( $customer_order );
			$id_rebill_to_clone = $rebill_order->get_id();
			$next_payment       = json_decode( $rebill_order->get_meta( 'rebill_next_payment' ), true );
			$status_rebill      = $rebill_order->get_meta( 'rebill_sub_status' );
			$status_txt         = $status_rebill;
			if ( $next_payment && is_array( $next_payment ) && count( $next_payment ) > 0 ) {
				$next_payment_d = $next_payment[0];
				$next_payment   = date_i18n( wc_date_format(), strtotime( $next_payment_d ) );
			} else {
				$next_payment   = '-';
				$next_payment_d = '';
			}
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
			?>
			<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-on-hold order">
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php echo esc_html( __( 'Subscriptions Order', 'wc-rebill-subscription' ) ); ?>">
					<a href="
					<?php

					echo esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . (int) $id_rebill_to_clone;
					?>
					">#<?php echo (int) $id_rebill_to_clone; ?></a>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?>">
					<time datetime="<?php echo esc_html( $rebill_order->get_date_created() ); ?>"><?php echo esc_html( date_i18n( wc_date_format(), strtotime( $rebill_order->get_date_created() ) ) ); ?></time>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php echo esc_html( __( 'Status', 'wc-rebill-subscription' ) ); ?>">
					<?php echo esc_html( $status_txt ); ?>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php echo esc_html( __( 'Next Payment', 'wc-rebill-subscription' ) ); ?>">
					<time datetime="<?php echo esc_html( $next_payment_d ); ?>"><?php echo esc_html( $next_payment ); ?></time>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php echo esc_html( __( 'Next payment amount', 'wc-rebill-subscription' ) ); ?>">
					<?php
					echo wp_kses( wc_price( $rebill_order->get_total() ), wp_kses_allowed_html( 'post' ) );
					?>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="<?php echo esc_html( __( 'Actions', 'wc-rebill-subscription' ) ); ?>">
				<!-- <a
					<?php
					echo 'href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . ( (int) $id_rebill_to_clone ) . '"';
					?>
					class="woocommerce-button button view"><?php echo esc_html( __( 'View', 'wc-rebill-subscription' ) ); ?></a> -->
				<?php
				if ( 'payment_pending' !== $status_rebill && 'failed' !== $status_rebill ) {
					?>
				<a
					<?php
					echo 'href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . ( (int) $id_rebill_to_clone ) . '?request_renew_card"';
					?>
					class="woocommerce-button button view"><?php echo esc_html( __( 'Change Card', 'wc-rebill-subscription' ) ); ?></a>
					<?php
					if ( 'cancelled' !== $status_rebill ) {
						// translators: %d: Order ID.
						echo '<a onclick="return confirm(\'' . esc_html( sprintf( __( 'Are you completely sure to cancel the subscription #%d? This will be irreversible', 'wc-rebill-subscription' ), $rebill_order->get_id() ) ) . '\');" ';
						echo 'href="' . esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . ( (int) $id_rebill_to_clone ) . '?request_cancel" class="woocommerce-button button view">' . esc_html( __( 'Cancel subscription', 'wc-rebill-subscription' ) ) . '</a>';
					}
				}
				?>
				</td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>
