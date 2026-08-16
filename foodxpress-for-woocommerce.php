<?php
/**
 * Plugin Name:       FoodXpress for WooCommerce
 * Plugin URI:        https://github.com/codermillat/FoodXpress-for-WooCommerce
 * Description:       A complete delivery management system for single-restaurant WooCommerce stores.
 * Version:           1.2.7
 * Author:            MD MILLAT HOSEN
 * Author URI:        https://github.com/codermillat
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       foodxpress
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

if (!defined('FXW_VERSION')) {
    define('FXW_VERSION', '1.2.7');
}
if (!defined('FXW_PLUGIN_DIR')) {
    define('FXW_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('FXW_PLUGIN_URL')) {
    define('FXW_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Display notice when WooCommerce is not active
 */
if (!function_exists('fxw_woocommerce_not_active_notice')) {
    function fxw_woocommerce_not_active_notice()
    {
        ?>
        <div class="error">
            <p><?php esc_html_e('FoodXpress for WooCommerce requires WooCommerce to be installed and active.', 'foodxpress'); ?></p>
        </div>
        <?php
    }
}

/**
 * Declare HPOS compatibility early (before_woocommerce_init fires before plugins_loaded).
 *
 * @since 1.1.0
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
});

add_action('plugins_loaded', 'fxw_init_plugin');

if (!function_exists('fxw_init_plugin')) {
    function fxw_init_plugin()
    {
        // Runtime PHP version check.
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>';
                esc_html_e('FoodXpress for WooCommerce requires PHP 7.4 or higher.', 'foodxpress');
                echo '</p></div>';
            });
            return;
        }

        // Runtime WordPress version check.
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>';
                esc_html_e('FoodXpress for WooCommerce requires WordPress 6.0 or higher.', 'foodxpress');
                echo '</p></div>';
            });
            return;
        }

        // Check if WooCommerce is active.
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', 'fxw_woocommerce_not_active_notice');
            return;
        }

        // Check WooCommerce version.
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.0', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>';
                esc_html_e('FoodXpress for WooCommerce requires WooCommerce 7.0 or higher.', 'foodxpress');
                echo '</p></div>';
            });
            return;
        }

        load_plugin_textdomain('foodxpress', false, dirname(plugin_basename(__FILE__)) . '/languages');

        require_once FXW_PLUGIN_DIR . 'includes/class-fxw-core.php';

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
        // Register rewrite rules first, then flush so they're persisted.
        if (class_exists('FXW_Core')) {
            $core = new FXW_Core();
            $core->add_rewrite_rules();
        } else {
            add_rewrite_rule('^delivery-dashboard/?$', 'index.php?is_delivery_dashboard=true', 'top');
        }
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
