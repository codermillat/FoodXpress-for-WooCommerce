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

		// Schedule-aware state — single source of truth shared with the
		// classic + blocks checkout validators and the REST endpoints
		// (v1.2.18). Read it from FXW_Store_Hours, not FXW_Checkout:
		// FXW_Core only loads the checkout classes on frontend/AJAX
		// requests, so in the admin the FXW_Checkout branch never ran and
		// the bar always said "Open" (v1.2.19).
		$is_open = class_exists('FXW_Store_Hours') ? FXW_Store_Hours::is_store_open() : true;

		if ($is_open) {
			$title = __('Deliveries: Open', 'foodxpress');
		} else {
			$hint = class_exists('FXW_Store_Hours') ? FXW_Store_Hours::reopen_hint() : '';
			$title = '' !== $hint
				? __('Deliveries: Closed', 'foodxpress') . ' (' . $hint . ')'
				: __('Deliveries: Closed', 'foodxpress');
		}
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
	 * The label shows the EFFECTIVE state (manual toggle AND schedule), so
	 * clicking must act on the effective state too — otherwise a closed
	 * weekly schedule made "Open" clicks invisible: each click flipped the
	 * hidden fxw_is_open flag while the schedule kept the store closed and
	 * the button never changed ("always Closed" bug).
	 *
	 * Close: set the manual flag off. Done — nothing else can keep it open.
	 * Open: set the manual flag on AND apply an automatic force-open until
	 * end of day (reusing the special-occasion override) so the button's
	 * promise matches reality even when the weekly schedule says closed.
	 *
	 * @since    1.0.0
	 */
	public function toggle_delivery_status()
	{
		// Capability matches the nodes we display (admins OR shop managers)
		// — previously manage_options only, so shop managers saw the toggle
		// but could not use it. Fixed in 1.2.10.
		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
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

		// Effective state BEFORE the click decides the action.
		$effective_before = class_exists('FXW_Store_Hours') ? FXW_Store_Hours::is_store_open() : true;

		if ($effective_before) {
			// Closing just closes.
			$options['fxw_is_open'] = false;
			unset($options['fxw_hours_override_enabled'], $options['fxw_hours_override_until']);
		} else {
			// Opening must beat whatever closed the store. Manual flag on +
			// force-open until 23:59 today via the special-occasion override
			// (an existing later override time is kept as-is).
			$options['fxw_is_open'] = true;
			$existing_until = isset($options['fxw_hours_override_until']) ? strtotime((string) $options['fxw_hours_override_until']) : false;
			$eod = strtotime('today 23:59', (int) current_time('timestamp'));
			if (false === $existing_until || $existing_until < $eod) {
				$options['fxw_hours_override_enabled'] = 'yes';
				$options['fxw_hours_override_until'] = date('Y-m-d\TH:i', $eod);
			}
		}

		update_option('fxw_settings', $options);

		// Effective (schedule-aware) state after the toggle.
		$effective = class_exists('FXW_Store_Hours') ? FXW_Store_Hours::is_store_open() : !empty($options['fxw_is_open']);
		$label = $effective
			? __('Deliveries: Open', 'foodxpress')
			: __('Deliveries: Closed', 'foodxpress')
				. (class_exists('FXW_Store_Hours') && '' !== ($h = FXW_Store_Hours::reopen_hint()) ? ' (' . $h . ')' : '');

		wp_send_json_success(array(
			'is_open' => $effective,
			'label'   => $label,
		));
	}
}

new FXW_Admin_Bar();
