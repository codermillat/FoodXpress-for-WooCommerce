<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Manages custom user roles for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Roles
{

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('init', array($this, 'add_delivery_boy_role'));
        add_action('init', array($this, 'ensure_admin_cap'), 20);
        add_action('init', array($this, 'ensure_delivery_role_cap'), 20);
    }

    /**
     * Add the custom "Delivery Boy" role.
     *
     * Deliberately minimal. `read` alone is what grants basic wp-admin
     * access; `edit_posts` is NOT required for that and would needlessly
     * let riders edit posts, so it is not granted (1.2.15). Delivery
     * features are unlocked through the custom fxw_delivery_access
     * capability instead. Only affects fresh installs — add_role() is
     * skipped when the role already exists; the transient-gated
     * ensure_delivery_role_cap() below keeps existing installs in sync
     * for fxw_delivery_access.
     *
     * @since    1.0.0
     */
    public function add_delivery_boy_role()
    {
        // Check if the role already exists to avoid errors on reactivation.
        $role = get_role('delivery_boy');
        if ($role) {
            return;
        }

        add_role(
            'delivery_boy',
            __('Delivery Boy', 'foodxpress'),
            array(
                'read' => true,  // Core capability — grants basic wp-admin access on its own
                'upload_files' => false,
                'delete_posts' => false,
                'fxw_delivery_access' => true,  // Custom capability for delivery access
            )
        );

        // Grant delivery access to administrators for testing purposes
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('fxw_delivery_access');
        }
    }

    /**
     * Ensure administrator role has delivery access capability.
     * 
     * Uses transient caching to avoid database queries on every request.
     *
     * @since    1.0.0
     */
    public function ensure_admin_cap()
    {
        // Only check once per day to avoid DB queries on every request
        if (get_transient('fxw_admin_cap_checked')) {
            return;
        }

        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('fxw_delivery_access')) {
            $admin_role->add_cap('fxw_delivery_access');
        }

        set_transient('fxw_admin_cap_checked', true, DAY_IN_SECONDS);
    }

    /**
     * Ensure delivery_boy role has delivery access capability.
     * 
     * Uses transient caching to avoid database queries on every request.
     *
     * @since    1.0.0
     */
    public function ensure_delivery_role_cap()
    {
        // Only check once per day to avoid DB queries on every request
        if (get_transient('fxw_delivery_cap_checked')) {
            return;
        }

        $role = get_role('delivery_boy');
        if ($role && !$role->has_cap('fxw_delivery_access')) {
            $role->add_cap('fxw_delivery_access');
        }

        set_transient('fxw_delivery_cap_checked', true, DAY_IN_SECONDS);
    }
}

new FXW_Roles();
