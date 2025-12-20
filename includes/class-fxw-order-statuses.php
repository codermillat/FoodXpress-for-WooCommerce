<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages custom order statuses for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Order_Statuses
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_action('init', array($this, 'register_order_statuses'));
		add_filter('wc_order_statuses', array($this, 'add_custom_statuses_to_wc'));
	}

	/**
	 * Register our custom order statuses with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function register_order_statuses()
	{
		register_post_status('wc-fxw-in-kitchen', array(
			'label' => _x('In the Kitchen', 'Order status', 'foodxpress'),
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('In the Kitchen <span class="count">(%s)</span>', 'In the Kitchen <span class="count">(%s)</span>', 'foodxpress')
		));

		register_post_status('wc-fxw-assigned', array(
			'label' => _x('Assigned', 'Order status', 'foodxpress'),
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Assigned <span class="count">(%s)</span>', 'Assigned <span class="count">(%s)</span>', 'foodxpress')
		));

		register_post_status('wc-fxw-picked-up', array(
			'label' => _x('Picked Up', 'Order status', 'foodxpress'),
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Picked Up <span class="count">(%s)</span>', 'Picked Up <span class="count">(%s)</span>', 'foodxpress')
		));
	}

	/**
	 * Add our custom statuses to the list of WooCommerce order statuses.
	 *
	 * @param   array   $order_statuses     The existing order statuses.
	 * @return  array   $order_statuses     The modified order statuses.
	 * @since   1.0.0
	 */
	public function add_custom_statuses_to_wc($order_statuses)
	{
		$new_order_statuses = array();
		$inserted = false;

		// Add the new statuses after 'processing'
		foreach ($order_statuses as $key => $status) {
			$new_order_statuses[$key] = $status;

			if ('wc-processing' === $key) {
				$new_order_statuses['wc-fxw-in-kitchen'] = _x('In the Kitchen', 'Order status', 'foodxpress');
				$new_order_statuses['wc-fxw-assigned'] = _x('Assigned', 'Order status', 'foodxpress');
				$new_order_statuses['wc-fxw-picked-up'] = _x('Picked Up', 'Order status', 'foodxpress');
				$inserted = true;
			}
		}

		// If we didn't find 'wc-processing', append our statuses at the end
		if (!$inserted) {
			$new_order_statuses['wc-fxw-in-kitchen'] = _x('In the Kitchen', 'Order status', 'foodxpress');
			$new_order_statuses['wc-fxw-assigned'] = _x('Assigned', 'Order status', 'foodxpress');
			$new_order_statuses['wc-fxw-picked-up'] = _x('Picked Up', 'Order status', 'foodxpress');
		}

		return $new_order_statuses;
	}
}

new FXW_Order_Statuses();
