<?php
/**
 * Plugin Name:       FoodXpress for WooCommerce
 * Plugin URI:        https://github.com/codermillat/FoodXpress-for-WooCommerce
 * Description:       A complete delivery management system for single-restaurant WooCommerce stores.
 * Version:           1.3.5
 * Author:            MD MILLAT HOSEN
 * Author URI:        https://millat.is-a.dev/
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
    define('FXW_VERSION', '1.3.5');
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
        // The blocks integration registers its delivery fields through the
        // Additional Checkout Fields API, which needs WooCommerce 8.9+. On
        // older WC versions the fields cannot register, persist, or validate,
        // so declaring blocks compatibility there would be false advertising
        // (1.2.15).
        $fxw_blocks_compat = defined('WC_VERSION') && version_compare(WC_VERSION, '8.9', '>=');
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, $fxw_blocks_compat);
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

        fxw_ensure_shipping_method_registered();
    }
}

/**
 * Make sure FoodXpress Delivery is enabled on every shipping zone that
 * exists today. Idempotent — re-running never creates a duplicate.
 *
 * The shipping method itself only adds a rate when WC is matching a
 * package to a zone that has the method enabled — without a zone that
 * has the method enabled, even a working shipping method renders as
 * "No available delivery option" in the Order summary panel. This used
 * to be a manual configuration step most stores missed, surfacing as
 * a stale rate from a previous session. (v1.3.5)
 *
 * @return bool True when the method is enabled on at least one zone
 *              after the call.
 */
if (!function_exists('fxw_ensure_shipping_method_registered')) {
    function fxw_ensure_shipping_method_registered()
    {
        if (!class_exists('WC_Shipping_Zones') || !class_exists('WC_Shipping_Zone')) {
            return false;
        }

        $touched_any = false;

        // Every named shipping zone.
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($zones as $zone_data) {
            if (!isset($zone_data['id'])) continue;
            $zone = new WC_Shipping_Zone($zone_data['id']);
            $methods = $zone->get_shipping_methods();
            $has_fxw = false;
            foreach ($methods as $method) {
                if (isset($method->id) && 'foodxpress_delivery' === $method->id) {
                    $has_fxw = true;
                    break;
                }
            }
            if (!$has_fxw) {
                try {
                    $zone->add_shipping_method('foodxpress_delivery');
                    $touched_any = true;
                } catch (\Throwable $e) {
                    // Zone data is transient; admin warning surfaces
                    // this.
                }
            } else {
                $touched_any = true;
            }
        }

        // Zone 0 — synthetic "Rest of the world" — not returned by
        // get_zones(). Handle it explicitly so stores shipping
        // everywhere have a fallback rate.
        $rest = new WC_Shipping_Zone(0);
        $methods = $rest->get_shipping_methods();
        $has_fxw = false;
        foreach ($methods as $method) {
            if (isset($method->id) && 'foodxpress_delivery' === $method->id) {
                $has_fxw = true;
                break;
            }
        }
        if (!$has_fxw) {
            try {
                $rest->add_shipping_method('foodxpress_delivery');
                $touched_any = true;
            } catch (\Throwable $e) {
                // Same recovery path as above.
            }
        } else {
            $touched_any = true;
        }

        return $touched_any;
    }
}

/**
 * Run the zone auto-registration once when an admin first visits a
 * WP/WC admin page after the upgrade. Gated by a `fxw_zone_sync_done`
 * transient so it never runs more than once per installation (and
 * never on the frontend, never on REST requests, never on cron). The
 * admin can clear the transient to re-run.
 *
 * @since 1.3.5
 */
if (!function_exists('fxw_maybe_sync_shipping_zones_admin')) {
    function fxw_maybe_sync_shipping_zones_admin()
    {
        if (!is_admin() || get_transient('fxw_zone_sync_done')) {
            return;
        }
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (function_exists('wp_doing_rest') && wp_doing_rest()) {
            return;
        }
        if (!class_exists('WC_Shipping_Zone')) {
            return;
        }
        // 6 hours is a safe re-run window — admin can also resync
        // manually by deleting the transient.
        fxw_ensure_shipping_method_registered();
        set_transient('fxw_zone_sync_done', 1, 6 * HOUR_IN_SECONDS);
    }
}
add_action('admin_init', 'fxw_maybe_sync_shipping_zones_admin');

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
