<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * FXW_Settings_Ui — presentation layer for the FoodXpress settings tab.
 *
 * Turns the flat settings form into section cards with an anchor nav and
 * conditional field visibility, aimed at non-technical store owners:
 *   - Each settings section renders as a card with a one-sentence intro.
 *   - A sticky anchor nav lists the sections.
 *   - Fields that only matter for a specific choice (map provider,
 *     fee structure) carry data-attributes; a tiny script shows/hides the
 *     rows the moment the controlling select changes. No reload, no AJAX.
 *
 * Purely presentational: it changes nothing about how settings are
 * sanitized or saved — every field still posts under `fxw_settings[...]`
 * exactly as before. Field rows are tagged by the OTHER classes' existing
 * markup via a shared `data-fxw-depends` contract documented below.
 *
 * @since      1.4.0
 * @package    FoodXpress
 * @author     MD Millat Hosen <https://millat.is-a.dev/>
 */
class FXW_Settings_Ui
{

	/**
	 * Wire hooks: card rendering + assets on the WC settings page only.
	 *
	 * @since 1.4.0
	 */
	public function __construct()
	{
		add_action('woocommerce_settings_foodxpress', array($this, 'open_wrapper'), 1);
		add_action('woocommerce_settings_foodxpress', array($this, 'close_wrapper'), 99);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Only load on WooCommerce → Settings → FoodXpress.
	 *
	 * @since 1.4.0
	 */
	public function should_load()
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		return ('woocommerce_page_wc-settings' === ($screen ? $screen->id : '')) || ('wc-settings' === $page && isset($_GET['tab']) && 'foodxpress' === $_GET['tab']);
	}

	/**
	 * Open the card wrapper before the settings table prints.
	 *
	 * @since 1.4.0
	 */
	public function open_wrapper()
	{
		if (!$this->should_load()) {
			return;
		}
		echo '<div class="fxw-settings-cards" id="fxw-settings-root">';
	}

	/**
	 * Close the wrapper after the table.
	 *
	 * @since 1.4.0
	 */
	public function close_wrapper()
	{
		if (!$this->should_load()) {
			return;
		}
		echo '</div>';
	}

	/**
	 * Enqueue the card/conditional CSS + JS on this screen only.
	 *
	 * @since 1.4.0
	 */
	public function enqueue_assets()
	{
		if (!$this->should_load()) {
			return;
		}
		wp_enqueue_style(
			'fxw-settings-ui',
			FXW_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			FXW_VERSION
		);
		wp_enqueue_script(
			'fxw-settings-ui',
			FXW_PLUGIN_URL . 'assets/js/admin-settings.js',
			array('jquery'),
			FXW_VERSION,
			true
		);
	}
}

new FXW_Settings_Ui();
