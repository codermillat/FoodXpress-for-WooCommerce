<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the notification functionality for the plugin.
 *
 * This class has been updated to use WooCommerce's email system instead of wp_mail().
 * The actual email sending is now handled by the WC_Email classes which trigger
 * automatically on status changes.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Notifications
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * Note: Email notifications are now handled by WC_Email classes registered
	 * in class-fxw-core.php. Those classes register their own triggers for:
	 * - woocommerce_order_status_fxw-in-kitchen
	 * - woocommerce_order_status_fxw-assigned
	 * - woocommerce_order_status_fxw-picked-up
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		// Email notifications are now handled by WC_Email classes:
		// - FXW_Email_In_Kitchen (triggered on fxw-in-kitchen status)
		// - FXW_Email_Assigned (triggered on fxw-assigned status)
		// - FXW_Email_Picked_Up (triggered on fxw-picked-up status)
		//
		// These can be customized in WooCommerce → Settings → Emails
	}
}

new FXW_Notifications();
