<?php
/**
 * Deliveries Dashboard — Orchestrator.
 *
 * Owns the dashboard's admin surface: asset enqueuing and action
 * notices. The page rendering lives in FXW_Dashboard_Render and the
 * write handlers (form-POST + AJAX) in FXW_Dashboard_Actions, both
 * loaded below and self-registering — the same split pattern as the
 * v1.2.0 checkout split. Logic is unchanged from the pre-split class.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/class-fxw-dashboard-render.php';
require_once __DIR__ . '/class-fxw-dashboard-actions.php';

class FXW_Dashboard
{

	/**
	 * Register dashboard-surface hooks (menu, rendering and write
	 * handlers self-register from the sibling classes).
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_action('admin_notices', array($this, 'show_admin_notices'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
	}

	/**
	 * Enqueue admin scripts for the dashboard.
	 *
	 * @since 1.0.1
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Only load on our deliveries dashboard page
		if ('toplevel_page_fxw-deliveries-dashboard' !== $hook) {
			return;
		}

		// Enqueue jQuery
		wp_enqueue_script('jquery');

		// Enqueue our print receipt JavaScript
		wp_enqueue_script(
			'fxw-admin-dashboard',
			FXW_PLUGIN_URL . 'assets/js/delivery-dashboard.js',
			array('jquery'),
			FXW_VERSION,
			true
		);

		// Enqueue AJAX dashboard functionality
		wp_enqueue_script(
			'fxw-ajax-dashboard',
			FXW_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array('jquery'),
			FXW_VERSION,
			true
		);

		// Localize script for AJAX dashboard
		wp_localize_script('fxw-ajax-dashboard', 'fxwDashboard', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fxw_dashboard_nonce'),
		));

		// Localize script for the print-receipt flow. The handler
		// (FXW_Shortcodes::print_receipt) verifies the 'fxw_print_receipt'
		// nonce — fixed in 1.2.9, this previously created 'fxw_nonce' and
		// the admin print buttons always failed the check.
		wp_localize_script('fxw-admin-dashboard', 'fxw_checkout_params', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fxw_print_receipt'),
		));
	}

	/**
	 * Show admin notices for delivery actions.
	 *
	 * @since 1.0.0
	 */
	public function show_admin_notices()
	{
		$notice = get_transient('fxw_admin_notice');
		if ($notice) {
			delete_transient('fxw_admin_notice');
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html($notice); ?></p>
			</div>
			<?php
		}
	}
}

new FXW_Dashboard();
