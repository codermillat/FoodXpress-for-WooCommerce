<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the delivery boy view.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Delivery_Boy_View
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_filter('woocommerce_login_redirect', array($this, 'login_redirect'), 10, 2);
		add_action('admin_post_fxw_mark_picked_up', array($this, 'mark_picked_up'));
		add_action('admin_post_fxw_mark_delivered', array($this, 'mark_delivered'));
		add_action('wp_ajax_fxw_update_delivery_status', array($this, 'ajax_update_delivery_status'));
	}

	/**
	 * Handle AJAX delivery status updates.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_delivery_status()
	{
		check_ajax_referer('fxw_delivery_action', 'nonce');

		if (!is_user_logged_in() || !current_user_can('fxw_delivery_access')) {
			wp_send_json_error(array('message' => __('Unauthorized access.', 'foodxpress')));
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

		if (!$order_id || !$status) {
			wp_send_json_error(array('message' => __('Missing order ID or status.', 'foodxpress')));
		}

		// Whitelist allowed status transitions for delivery agents
		$allowed_statuses = array('fxw-picked-up', 'completed');
		if (!in_array($status, $allowed_statuses, true)) {
			wp_send_json_error(array('message' => __('Invalid status transition.', 'foodxpress')), 400);
		}

		$order = wc_get_order($order_id);

		if (!$order) {
			wp_send_json_error(array('message' => __('Invalid order.', 'foodxpress')));
		}

		$assigned_id = $order->get_meta('_fxw_delivery_boy_id', true);

		if (empty($assigned_id) || (int) $assigned_id !== (int) get_current_user_id()) {
			wp_send_json_error(array('message' => __('You are not assigned to this order.', 'foodxpress')));
		}

		// Update status
		if (is_callable(array($order, 'update_status'))) {
			$note = ('fxw-picked-up' === $status) ? __('Order picked up by delivery agent (AJAX).', 'foodxpress') : __('Order delivered by delivery agent (AJAX).', 'foodxpress');
			if ($order->update_status($status, $note)) {
				wp_send_json_success(array('message' => __('Status updated successfully.', 'foodxpress')));
			} else {
				wp_send_json_error(array('message' => __('Failed to update order status.', 'foodxpress')));
			}
		} else {
			wp_send_json_error(array('message' => __('Order update not callable.', 'foodxpress')));
		}
	}

	/**
	 * Redirect delivery boys to their dashboard after login.
	 *
	 * @param   string    $redirect   The redirect URL.
	 * @param   WP_User   $user       The user object.
	 * @return  string                The modified redirect URL.
	 * @since   1.0.0
	 */
	public function login_redirect($redirect, $user)
	{
		if (in_array('delivery_boy', (array) $user->roles)) {
			$redirect = home_url('/delivery-dashboard/');
		}
		return $redirect;
	}


	/**
	 * Handle "Picked Up" action from Delivery Dashboard.
	 *
	 * @since 1.0.0
	 */
	public function mark_picked_up()
	{
		$order = $this->verify_delivery_action();
		$order->update_status('fxw-picked-up', __('Order picked up by delivery agent.', 'foodxpress'));

		wp_safe_redirect(home_url('/delivery-dashboard/?updated=1'));
		exit;
	}

	/**
	 * Handle "Delivered" action from Delivery Dashboard.
	 *
	 * @since 1.0.0
	 */
	public function mark_delivered()
	{
		$order = $this->verify_delivery_action();
		$order->update_status('completed', __('Order delivered by delivery agent.', 'foodxpress'));

		wp_safe_redirect(home_url('/delivery-dashboard/?delivered=1'));
		exit;
	}

	/**
	 * Verify delivery action: auth, nonce, order ownership.
	 *
	 * @return WC_Order Validated order object.
	 * @since 1.1.0
	 */
	private function verify_delivery_action()
	{
		if (!is_user_logged_in() || !current_user_can('fxw_delivery_access')) {
			wp_die(__('Unauthorized.', 'foodxpress'));
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw_delivery_action')) {
			wp_die(__('Invalid nonce.', 'foodxpress'));
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$order = $order_id ? wc_get_order($order_id) : false;

		if (!$order) {
			wp_die(__('Invalid order.', 'foodxpress'));
		}

		$assigned_id = $order->get_meta('_fxw_delivery_boy_id', true);
		if (empty($assigned_id) || (int) $assigned_id !== (int) get_current_user_id()) {
			wp_die(__('You are not assigned to this order.', 'foodxpress'));
		}

		return $order;
	}
}

new FXW_Delivery_Boy_View();
