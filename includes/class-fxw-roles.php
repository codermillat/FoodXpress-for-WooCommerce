<?php
/**
 * Manages custom user roles for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Roles {

/**
 * Initialize the class and set its properties.
 *
 * @since    1.0.0
 */
public function __construct() {
add_action( 'init', array( $this, 'add_delivery_boy_role' ) );
add_action( 'init', array( $this, 'ensure_admin_cap' ), 20 );
add_action( 'init', array( $this, 'ensure_delivery_role_cap' ), 20 );
}

/**
 * Add the custom "Delivery Boy" role.
 *
 * This role has limited capabilities, primarily to read orders.
 * We grant `edit_posts` capability so they can access the WP admin
 * to see their dedicated view, but further restrictions will apply.
 *
 * @since    1.0.0
 */
public function add_delivery_boy_role() {
// Check if the role already exists to avoid errors on reactivation.
$role = get_role( 'delivery_boy' );
if ( $role ) {
return;
}

add_role(
'delivery_boy',
__( 'Delivery Boy', 'foodxpress' ),
array(
'read'         => true,  // Core capability
'edit_posts'   => true,  // Required to access the admin area at all
'upload_files' => false,
'delete_posts' => false,
'fxw_delivery_access' => true,  // Custom capability for delivery access
)
);

// Grant delivery access to administrators for testing purposes
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
$admin_role->add_cap( 'fxw_delivery_access' );
}
}

/**
 * Ensure administrator role has delivery access capability.
 * 
 * This method always runs on init to ensure administrators
 * have the fxw_delivery_access capability, even if the 
 * delivery_boy role already exists.
 *
 * @since    1.0.0
 */
public function ensure_admin_cap() {
$admin_role = get_role( 'administrator' );
if ( $admin_role && ! $admin_role->has_cap( 'fxw_delivery_access' ) ) {
$admin_role->add_cap( 'fxw_delivery_access' );
}
}

/**
 * Ensure delivery_boy role has delivery access capability.
 * 
 * This method always runs on init to ensure delivery_boy users
 * have the fxw_delivery_access capability, even if the role
 * was created in a previous version without this capability.
 *
 * @since    1.0.0
 */
public function ensure_delivery_role_cap() {
$role = get_role( 'delivery_boy' );
if ( $role && ! $role->has_cap( 'fxw_delivery_access' ) ) {
$role->add_cap( 'fxw_delivery_access' );
}
}
}

new FXW_Roles();
