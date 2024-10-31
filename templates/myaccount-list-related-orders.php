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
<h2><?php echo esc_html( __( 'All orders for this subscription', 'wc-rebill-subscription' ) ); ?></h2>
<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders my_account_rebill_orders account-orders-table">
	<thead>
		<tr>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr">
				<?php echo esc_html( __( 'Order', 'wc-rebill-subscription' ) ); ?>
			</span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr"><?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr"><?php echo esc_html( __( 'Status' ) ); ?></span></th>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr"><?php echo esc_html( __( 'Amount', 'wc-rebill-subscription' ) ); ?></span></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $related_orders as $customer_order ) {
			$rebill_order = wc_get_order( $customer_order );
			$status_txt   = wc_get_order_status_name( $rebill_order->get_status() );
			?>
			<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-on-hold order">
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php echo esc_html( __( 'Subscriptions Order', 'wc-rebill-subscription' ) ); ?>">
				<?php
				if ( $rebill_order->get_id() !== $current_order->get_id() ) {
					?>
					<a href="
					<?php
					echo esc_url( wc_get_account_endpoint_url( 'rebill-subscriptions' ) ) . (int) $rebill_order->get_id();
					?>
					">#<?php echo (int) $rebill_order->get_id(); ?></a>
					<?php
				} else {
					?>
					<b>#<?php echo (int) $rebill_order->get_id(); ?></b>
					<?php
				}
				?>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?>">
					<time datetime="<?php echo esc_html( $rebill_order->get_date_created() ); ?>"><?php echo esc_html( date_i18n( wc_date_format(), strtotime( $rebill_order->get_date_created() ) ) ); ?></time>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php echo esc_html( __( 'Status', 'wc-rebill-subscription' ) ); ?>">
					<?php echo esc_html( $status_txt ); ?>
				</td>
				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php echo esc_html( __( 'Amount', 'wc-rebill-subscription' ) ); ?>">
					<?php
					echo wp_kses( wc_price( $rebill_order->get_total() ), wp_kses_allowed_html( 'post' ) );
					?>
				</td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>
