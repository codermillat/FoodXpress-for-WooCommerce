<?php
/**
 * Manages the notification functionality for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Notifications {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'send_delivery_status_notification' ), 10, 4 );
	}

	/**
	 * Sends an email notification when the delivery status changes.
	 *
	 * @param   int       $order_id         The order ID.
	 * @param   string    $old_status       The old order status.
	 * @param   string    $new_status       The new order status.
	 * @param   WC_Order  $order            The order object.
	 * @since   1.0.0
	 */
	public function send_delivery_status_notification( $order_id, $old_status, $new_status, $order ) {
		$customer_email = $order->get_billing_email();
		$subject        = '';
		$message        = '';

		switch ( $new_status ) {
			case 'fxw-in-kitchen':
				$subject = __( 'Your order is in the kitchen!', 'foodxpress' );
				$message = __( 'Your order is being prepared and will be out for delivery soon.', 'foodxpress' );
				break;
			case 'fxw-assigned':
				$subject = __( 'A driver has been assigned to your order!', 'foodxpress' );
				$message = __( 'A delivery driver is on their way to pick up your order.', 'foodxpress' );
				break;
			case 'fxw-picked-up':
				$subject = __( 'Your order has been picked up!', 'foodxpress' );
				$message = __( 'Your order is on its way to you.', 'foodxpress' );
				break;
		}

		if ( ! empty( $subject ) && ! empty( $message ) ) {
			wp_mail( $customer_email, $subject, $message );
		}
	}
}

new FXW_Notifications();
