<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the admin bar functionality.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Admin_Bar
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_action('admin_bar_menu', array($this, 'add_delivery_status_toggle'), 999);
		add_action('wp_ajax_fxw_toggle_delivery_status', array($this, 'toggle_delivery_status'));
	}

	/**
	 * Add the delivery status toggle to the admin bar.
	 *
	 * @param   WP_Admin_Bar  $wp_admin_bar   The admin bar object.
	 * @since   1.0.0
	 */
	public function add_delivery_status_toggle($wp_admin_bar)
	{
		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
			return;
		}

		$options = get_option('fxw_settings');
		$is_open = isset($options['fxw_is_open']) ? filter_var($options['fxw_is_open'], FILTER_VALIDATE_BOOLEAN) : true;

		$title = $is_open ? __('Deliveries: Open', 'foodxpress') : __('Deliveries: Closed', 'foodxpress');
		$href = '#';

		$wp_admin_bar->add_node(array(
			'id' => 'fxw-delivery-status',
			'title' => $title,
			'href' => $href,
			'meta' => array(
				'class' => 'fxw-delivery-status-toggle',
				'onclick' => 'fxw_toggle_delivery_status(this); return false;',
			),
		));
	}

	/**
	 * Handle the AJAX request to toggle the delivery status.
	 *
	 * @since    1.0.0
	 */
	public function toggle_delivery_status()
	{
		// Security: Check user capability
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized access.', 'foodxpress')), 403);
		}

		// Security: Verify nonce to prevent CSRF attacks
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw-admin-nonce')) {
			wp_send_json_error(array('message' => __('Security verification failed.', 'foodxpress')), 403);
		}

		$options = get_option('fxw_settings', array());
		if (!is_array($options)) {
			$options = array();
		}
		$is_open = isset($options['fxw_is_open']) ? filter_var($options['fxw_is_open'], FILTER_VALIDATE_BOOLEAN) : true;
		$options['fxw_is_open'] = !$is_open;
		update_option('fxw_settings', $options);

		wp_send_json_success(array(
			'is_open' => $options['fxw_is_open'],
			'label' => $options['fxw_is_open'] ? __('Deliveries: Open', 'foodxpress') : __('Deliveries: Closed', 'foodxpress'),
		));
	}
}

new FXW_Admin_Bar();
