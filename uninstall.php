<?php
/**
 * FoodXpress Uninstall
 *
 * Fired when the plugin is deleted.
 * Cleans up options, user meta, and custom data.
 *
 * @package FoodXpress
 * @since   1.1.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options.
delete_option('fxw_settings');

// Remove delivery boy role.
remove_role('delivery_boy');

// Clean up user meta for delivery boys (delivery profile data).
$users = get_users(array('meta_key' => '_fxw_delivery_profile'));
foreach ($users as $user) {
    delete_user_meta($user->ID, '_fxw_delivery_profile');
}

// Clean up order meta (optional: only if full cleanup is desired).
// Order meta keys: _fxw_delivery_boy_id, _fxw_delivery_boy_name, _fxw_delivery_status
// Note: Uncomment below for complete cleanup. Left commented to preserve order history by default.
// global $wpdb;
// $wpdb->delete($wpdb->postmeta, array('meta_key' => '_fxw_delivery_boy_id'));
// $wpdb->delete($wpdb->postmeta, array('meta_key' => '_fxw_delivery_boy_name'));
// $wpdb->delete($wpdb->postmeta, array('meta_key' => '_fxw_delivery_status'));

// Flush rewrite rules.
flush_rewrite_rules();
