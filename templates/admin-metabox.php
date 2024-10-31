<?php
/**
 * Rebill Admin Metabox
 *
 * @package    Rebill
 * @link       https://rebill.to
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$nonce = wp_create_nonce( 'rebill_admin_order_update' );
?>

<input type="hidden" name="rebill_nonce" value="<?php echo esc_html( $nonce ); ?>" />
<table width="95%" style="width:95%">
	<?php
	if ( $transaction_id ) {
		?>
	<tr>
		<td width="35%" style="width:35%">
			<strong><?php echo esc_html( __( 'Rebill Transaction ID', 'wc-rebill-subscription' ) ); ?>:</strong>
		</td>
		<td width="65%" style="width:65%">
		<?php
		if ( $all_transactions ) {
			foreach ( $all_transactions as $transaction ) {
				echo '<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/payments/view?id=' . esc_html( $transaction['id'] ) . '">#' . esc_html( $transaction['id'] ) . '</a> ';
			}
		} else {
			echo '<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/payments/view?id=' . esc_html( $transaction_id ) . '">#' . esc_html( $transaction_id ) . '</a>';
		}
		?>
	</td>
	<tr>
		<?php
	}
	if ( $all_transactions ) {
		?>
	<tr>
		<td width="35%" style="width:35%">
			<strong><?php echo esc_html( __( 'Gateway Transaction ID', 'wc-rebill-subscription' ) ); ?>:</strong>
		</td>
		<td width="65%" style="width:65%">
		<?php
		$list     = array();
		$is_first = true;
		foreach ( $all_transactions as $transaction ) {
			if ( ! $is_first ) {
				echo '<br />';
			}
			$is_first = false;
			echo '<b>' . esc_html( '#' . $transaction['gateway_payment_id'] ) . ':</b> ' . wp_kses( wc_price( $transaction['amount'] ), wp_kses_allowed_html( 'post' ) ) . ' - ' . esc_html( $transaction['status'] );
		}
		?>
		</td>
	</tr>
		<?php
	}
	if ( $subscription_id ) {
		?>
	<tr>
		<td width="35%" style="width:35%">
			<strong><?php echo esc_html( __( 'Rebill Subscription ID', 'wc-rebill-subscription' ) ); ?>:</strong>
		</td>
		<td width="65%" style="width:65%">
		<?php
		if ( $is_rebill_first ) {
			foreach ( $all_subscription_id as $s_id ) {
				if ( empty( $s_id ) ) {
					continue;
				}
				echo '<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/subscriptions/view?id=' . esc_html( $s_id ) . '">#' . esc_html( $s_id ) . '</a> ';
			}
		} else {
			echo '<a target="_blank" href="https://safe' . ( 'yes' === $settings['sandbox'] ? '-staging' : '' ) . '.rebill.to/subscriptions/view?id=' . esc_html( $subscription_id ) . '">#' . esc_html( $subscription_id ) . '</a> ';
		}

		?>
		</td>
	</tr>
		<?php
	}
	if ( $is_renew_order || (int) $c_order->get_id() !== (int) $rebill_order->get_id() || $is_rebill_first ) {
		if ( ! $is_rebill_first ) {
			?>
	<tr>
		<td width="35%" style="width:35%">
			<strong><?php echo esc_html( __( 'Parent Order', 'wc-rebill-subscription' ) ); ?>:</strong>
		</td>
		<td width="65%" style="width:65%"><a href="post.php?action=edit&post=<?php echo esc_html( $c_order->get_id() ); ?>">#<?php echo esc_html( $c_order->get_id() ); ?></a>
		<b style="color: red"><?php echo esc_html( __( 'Only changes to the parent order will be reflected in the following recurring payments', 'wc-rebill-subscription' ) ); ?></b>
		</td>
	</tr>
			<?php
		} else {
			?>
			<tr>
				<td width="35%" style="width:35%">
					<strong><?php echo esc_html( __( 'Parent Order', 'wc-rebill-subscription' ) ); ?>:</strong>
				</td>
				<td width="65%" style="width:65%">
				<?php
				foreach ( $all_order_id as $s_id ) {
					?>
					<a href="post.php?action=edit&post=<?php echo esc_html( $s_id ); ?>">#<?php echo esc_html( $s_id ); ?></a>
					<?php
				}
				?>
				<b style="color: red"><?php echo esc_html( __( 'Only changes to the parent order will be reflected in the following recurring payments', 'wc-rebill-subscription' ) ); ?></b>
				</td>
			</tr>
				<?php

		}
	}
	if ( ! $is_rebill_first ) {
		if ( $rebill_subscription && ( $is_renew_order || in_array( $sub_status, array( 'cancelled', 'payment_pending' ), true ) ) ) {
			$rebill_subscription = $rebill_subscription[ $rebill_ref ];
			$frequency_type      = $rebill_subscription['frequency_type'];
			$frequency           = $rebill_subscription['frequency'];
			$result              = '';
			switch ( $frequency_type ) {
				case 'day':
					// translators: %s: Frequency value.
					$result = esc_html( sprintf( _n( 'Client pay every %s day', 'Client pay every %s days', $frequency, 'wc-rebill-subscription' ), $frequency ) );
					break;
				case 'month':
					// translators: %s: Frequency value.
					$result = esc_html( sprintf( _n( 'Client pay every %s month', 'Client pay every %s months', $frequency, 'wc-rebill-subscription' ), $frequency ) );
					break;
				case 'year':
					// translators: %s: Frequency value.
					$result = esc_html( sprintf( _n( 'Client pay every %s year', 'Client pay every %s years', $frequency, 'wc-rebill-subscription' ), $frequency ) );
					break;
			}
			?>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'Frequency', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%"><?php echo esc_html( $result ); ?></td>
		</tr>
			<?php
			if ( $rebill_subscription['frequency_max'] > 0 ) {
				?>
				<tr>
					<td width="35%" style="width:35%">
						<strong><?php echo esc_html( __( 'Repetitions', 'wc-rebill-subscription' ) ); ?>:</strong>
					</td>
					<td width="65%" style="width:65%"><?php echo esc_html( $rebill_subscription['frequency_max'] ); ?></td>
				</tr>
				<?php
			}
		} elseif ( $rebill_subscription && isset( $rebill_subscription[ $rebill_ref ] ) ) {
			$rebill_subscription = $rebill_subscription[ $rebill_ref ];
			$frequency_type      = $rebill_subscription['frequency_type'];
			$frequency_max       = $rebill_subscription['frequency_max'];
			$frequency           = $rebill_subscription['frequency'];
			?>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'Actions', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%">
			<?php
			// translators: %d Order ID.
			echo '<a class="button button-primary" onclick="return confirm(\'' . esc_html( sprintf( __( 'Are you sure you want to request to renew card for the subscription #%d?', 'wc-rebill-subscription' ), $c_order->get_id() ) ) . '\');" ';
			echo 'href="post.php?action=edit&rebill_nonce=' . esc_html( wp_create_nonce( 'admin_rebill_request_new_card' ) ) . '&post=' . ( (int) $c_order->get_id() ) . '&rebill_request_new_card">' . esc_html( __( 'Request Renew Card', 'wc-rebill-subscription' ) ) . '</a>';
			// translators: %d Order ID.
			echo ' - <a class="button button-primary" onclick="return confirm(\'' . esc_html( sprintf( __( 'Are you completely sure to cancel the subscription #%d? This will be irreversible', 'wc-rebill-subscription' ), $c_order->get_id() ) ) . '\');" ';
			echo 'href="post.php?action=edit&rebill_nonce=' . esc_html( wp_create_nonce( 'admin_rebill_request_cancel' ) ) . '&post=' . ( (int) $c_order->get_id() ) . '&rebill_request_cancel">' . esc_html( __( 'Cancel Subscription', 'wc-rebill-subscription' ) ) . '</a>';
			?>
			</td>
		</tr>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'Frequency Type', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%">
			<?php
			woocommerce_wp_select(
				array(
					'id'      => 'rebill_frequency_type',
					'label'   => '',
					'options' => array(
						'month' => __( 'Month', 'wc-rebill-subscription' ),
						'day'   => __( 'Day', 'wc-rebill-subscription' ),
						'year'  => __( 'Year', 'wc-rebill-subscription' ),
					),
					'value'   => $frequency_type,
				)
			);
			?>
			</td>
		</tr>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'Frequency', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%">
			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => 'rebill_frequency',
					'placeholder' => '',
					'label'       => '',
					'description' => __( 'Frequency of collection, for example: every 1 month or every 2 months.', 'wc-rebill-subscription' ),
					'value'       => $frequency,
				)
			);
			?>
			</td>
		</tr>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'Repetitions', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%">
			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => 'rebill_frequency_max',
					'label'       => '',
					'label'       => __( 'Maximum number of recurring payment', 'wc-rebill-subscription' ),
					'placeholder' => '',
					'description' => __( 'Leave empty if it will never expire', 'wc-rebill-subscription' ),
					'value'       => $frequency_max,
				)
			);
			?>
			</td>
		</tr>
			<?php
		}
		if ( $related_orders ) {
			?>
		<tr>
			<td width="35%" style="width:35%">
				<strong><?php echo esc_html( __( 'All orders for this subscription', 'wc-rebill-subscription' ) ); ?>:</strong>
			</td>
			<td width="65%" style="width:65%">
				<table class="woocommerce-orders-table woocommerce-orders-table-rebill" width="100%" style="width:100%">
					<thead>
						<tr>
							<th><span class="nobr">
								<?php echo esc_html( __( 'Order', 'wc-rebill-subscription' ) ); ?>
							</span></th>
							<th><span class="nobr"><?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?></span></th>
							<th><span class="nobr"><?php echo esc_html( __( 'Status', 'wc-rebill-subscription' ) ); ?></span></th>
							<th><span class="nobr"><?php echo esc_html( __( 'Amount', 'wc-rebill-subscription' ) ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $related_orders as $customer_order ) {
							$r_order    = wc_get_order( $customer_order );
							$status_txt = wc_get_order_status_name( $r_order->get_status() );
							?>
							<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-on-hold order">
								<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="<?php echo esc_html( __( 'Order', 'wc-rebill-subscription' ) ); ?>">
								<?php
								if ( $r_order->get_id() !== $rebill_order->get_id() ) {
									echo '<a href="post.php?action=edit&post=' . (int) $r_order->get_id() . '">#' . (int) $r_order->get_id() . '</a>';
								} else {
									?>
									<b>#<?php echo (int) $r_order->get_id(); ?></b>
									<?php
								}
								?>
								</td>
								<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php echo esc_html( __( 'Date', 'wc-rebill-subscription' ) ); ?>">
									<time datetime="<?php echo esc_html( $r_order->get_date_created() ); ?>"><?php echo esc_html( date_i18n( wc_date_format(), strtotime( $r_order->get_date_created() ) ) ); ?></time>
								</td>
								<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php echo esc_html( __( 'Status', 'wc-rebill-subscription' ) ); ?>">
									<?php echo esc_html( $status_txt ); ?>
								</td>
								<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="<?php echo esc_html( __( 'Amount', 'wc-rebill-subscription' ) ); ?>">
									<?php
									echo wp_kses_post( wc_price( $r_order->get_total() ), wp_kses_allowed_html( 'post' ) );
									?>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</td>
		</tr>
			<?php
		}
	}
	?>
</table>

<style>
<?php
if ( $rebill_subscription && $c_order->get_id() === $rebill_order->get_id() ) {
	?>
.form-field.wc-order-status, .order_actions #actions {
	display: none;
}
	<?php
}
?>
.woocommerce-orders-table-rebill th {
	text-align: left;
}
</style>
