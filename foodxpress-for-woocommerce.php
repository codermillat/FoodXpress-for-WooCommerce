<?php
/**
 * Plugin Name:       FoodXpress for WooCommerce
 * Plugin URI:        https://github.com/codermillat/foodxpress-woocommerce
 * Description:       A complete delivery management system for single-restaurant WooCommerce stores.
 * Version:           1.1.0
 * Author:            MD MILLAT HOSEN
 * Author URI:        https://github.com/codermillat
 * License:           Proprietary
 * Text Domain:       foodxpress
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('FXW_VERSION', '1.1.0');
define('FXW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FXW_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Display notice when WooCommerce is not active
 */
if (!function_exists('fxw_woocommerce_not_active_notice')) {
    function fxw_woocommerce_not_active_notice()
    {
        ?>
        <div class="error">
            <p><?php _e('FoodXpress for WooCommerce requires WooCommerce to be installed and active.', 'foodxpress'); ?></p>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin after all plugins are loaded
 */
add_action('plugins_loaded', 'fxw_init_plugin');

if (!function_exists('fxw_init_plugin')) {
    function fxw_init_plugin()
    {
        /**
         * Check if WooCommerce is active
         */
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', 'fxw_woocommerce_not_active_notice');
            return;
        }

        /**
         * Declare HPOS (High-Performance Order Storage) compatibility
         * Required for WooCommerce 8.x+
         */
        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
            }
        });

        /**
         * Load plugin textdomain for internationalization
         */
        load_plugin_textdomain('foodxpress', false, dirname(plugin_basename(__FILE__)) . '/languages');

        /**
         * The core plugin class that is used to define internationalization,
         * admin-specific hooks, and public-facing site hooks.
         */
        require_once FXW_PLUGIN_DIR . 'includes/class-fxw-core.php';

        /**
         * Begins execution of the plugin.
         */
        $plugin = new FXW_Core();
    }
}

/**
 * Flush rewrite rules on activation.
 *
 * @since    1.0.0
 */
if (!function_exists('fxw_activate')) {
    function fxw_activate()
    {
        // Flush rewrite rules to ensure delivery dashboard URL works.
        flush_rewrite_rules();
    }
}

/**
 * Clean up rewrite rules on deactivation.
 *
 * @since    1.0.0
 */
if (!function_exists('fxw_deactivate')) {
    function fxw_deactivate()
    {
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, 'fxw_activate');
register_deactivation_hook(__FILE__, 'fxw_deactivate');
