<?php
/**
 * The template for displaying the delivery boy view.
 *
 * @since      1.0.0
 * @package    FoodXpress
 */

if ( ! is_user_logged_in() || ! current_user_can( 'fxw_delivery_access' ) ) {
	wp_redirect( home_url() );
	exit;
}

get_header();
?>
<div class="fxw-delivery-dashboard">
	<div class="fxw-container">
		<h1><?php _e( 'My Assigned Deliveries', 'foodxpress' ); ?></h1>
		<?php
		$delivery_boy_id = get_current_user_id();
		$orders = wc_get_orders( array(
			'limit' => -1,
			'meta_query' => array(
				array(
					'key' => '_fxw_delivery_boy_id',
					'value' => $delivery_boy_id,
					'compare' => '='
				)
			),
			'status' => array( 'fxw-assigned', 'fxw-picked-up' ),
		) );

		if ( $orders ) :
			?>
			<div class="fxw-orders-grid">
				<?php
				foreach ( $orders as $order ) :
					$shipping_address = $order->get_formatted_shipping_address();
					$unit = $order->get_meta( '_fxw_address_unit', true );
					$status = $order->get_status();
					?>
					<div class="fxw-order-card">
						<div class="fxw-card-header">
							<h3><?php printf( __( 'Order #%s', 'foodxpress' ), $order->get_order_number() ); ?></h3>
							<span class="fxw-status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
						</div>
						<div class="fxw-card-body">
							<div class="fxw-card-section">
								<h4><?php _e( 'Customer Details', 'foodxpress' ); ?></h4>
								<p><strong><?php _e( 'Name:', 'foodxpress' ); ?></strong> <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></p>
								<p><strong><?php _e( 'Address:', 'foodxpress' ); ?></strong> <?php echo wp_kses_post( $shipping_address ); ?></p>
								<?php if ( $unit ) : ?>
									<p><strong><?php _e( 'Unit:', 'foodxpress' ); ?></strong> <?php echo esc_html( $unit ); ?></p>
								<?php endif; ?>
								<p><strong><?php _e( 'Phone:', 'foodxpress' ); ?></strong> <a href="tel:<?php echo esc_attr( $order->get_billing_phone() ); ?>"><?php echo esc_html( $order->get_billing_phone() ); ?></a></p>
							</div>
							<div class="fxw-card-section">
								<h4><?php _e( 'Payment Information', 'foodxpress' ); ?></h4>
								<p><strong><?php _e( 'Method:', 'foodxpress' ); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></p>
								<?php if ( 'cod' === $order->get_payment_method() ) : ?>
									<p class="fxw-collect-amount"><strong><?php _e( 'Collect:', 'foodxpress' ); ?></strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="fxw-card-footer">
							<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode( strip_tags( $shipping_address ) ); ?>" target="_blank" class="fxw-button button-secondary"><?php _e( 'Open in Map', 'foodxpress' ); ?></a>
							<a href="#" class="fxw-button button-secondary fxw-print-receipt" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"><?php _e( 'Print Receipt', 'foodxpress' ); ?></a>
							<?php if ( 'fxw-assigned' === $status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'fxw_delivery_action' ); ?>
									<input type="hidden" name="action" value="fxw_mark_picked_up" />
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
									<button type="submit" class="fxw-button button-primary"><?php _e( 'Mark Picked Up', 'foodxpress' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( 'fxw-picked-up' === $status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'fxw_delivery_action' ); ?>
									<input type="hidden" name="action" value="fxw_mark_delivered" />
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
									<button type="submit" class="fxw-button button-primary"><?php _e( 'Mark Delivered', 'foodxpress' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="fxw-no-orders">
				<p><?php _e( 'You have no assigned deliveries at the moment.', 'foodxpress' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
?>
