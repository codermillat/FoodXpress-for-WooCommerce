<?php
/**
 * Test script to verify FoodXpress fixes
 * Run this by visiting: http://your-site.com/wp-content/plugins/FoodXpress%20for%20WooCommerce/test-fixes.php
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access. Please login as administrator.' );
}

echo '<h1>FoodXpress Delivery System Test</h1>';

// Flush rewrite rules
flush_rewrite_rules();
echo '<p>✅ Rewrite rules flushed</p>';

// Test delivery access capability
echo '<h2>Capability Tests</h2>';
if ( current_user_can( 'fxw_delivery_access' ) ) {
    echo '<p>✅ Administrator has delivery access capability</p>';
} else {
    echo '<p>❌ Administrator missing delivery access capability</p>';
}

// Test meta queries for dashboard
echo '<h2>Dashboard Query Tests</h2>';

// Create test order with delivery boy assigned
$test_order = wc_create_order();
$test_order->set_status( 'fxw-assigned' );
update_post_meta( $test_order->get_id(), '_fxw_delivery_boy_id', 1 );
$test_order->save();

// Test assigned orders query (matches actual dashboard query)
$assigned_orders = wc_get_orders( array(
    'limit'        => -1,
    'status'       => array( 'fxw-assigned', 'pending', 'processing', 'on-hold', 'fxw-in-kitchen' ),
    'meta_query'   => array(
        array(
            'key'     => '_fxw_delivery_boy_id',
            'value'   => 0,
            'type'    => 'NUMERIC',
            'compare' => '>',
        ),
    ),
) );

if ( count( $assigned_orders ) > 0 ) {
    echo '<p>✅ Assigned orders query returns results (' . count( $assigned_orders ) . ' orders)</p>';
} else {
    echo '<p>❌ Assigned orders query returns no results (Note: For complete validation, test via Admin Deliveries Dashboard)</p>';
}

// Meta-only sanity check (no status filter)
echo '<h3>Meta-only sanity check</h3>';
$meta_only = wc_get_orders( array(
    'limit'      => -1,
    'meta_query' => array(
        array(
            'key'     => '_fxw_delivery_boy_id',
            'value'   => 0,
            'type'    => 'NUMERIC',
            'compare' => '>',
        ),
    ),
) );
echo '<p>Meta-only query count: ' . count( $meta_only ) . '</p>';

// Debug output for assigned orders
if ( ! empty( $assigned_orders ) ) {
    echo '<details><summary>Assigned orders (debug)</summary><ul>';
    foreach ( $assigned_orders as $o ) {
        echo '<li>#' . esc_html( $o->get_id() ) . ' — status: ' . esc_html( $o->get_status() ) . ' — delivery_boy_id: ' . esc_html( $o->get_meta( '_fxw_delivery_boy_id', true ) ) . '</li>';
    }
    echo '</ul></details>';
}

// Test query variables
echo '<h2>Routing Tests</h2>';
$query_vars = apply_filters( 'query_vars', $GLOBALS['wp']->public_query_vars );
if ( in_array( 'is_delivery_dashboard', $query_vars ) ) {
    echo '<p>✅ Query variable "is_delivery_dashboard" registered</p>';
} else {
    echo '<p>❌ Query variable "is_delivery_dashboard" not registered</p>';
}

// Check if delivery boy role exists
echo '<h2>Role Tests</h2>';
if ( get_role( 'delivery_boy' ) ) {
    echo '<p>✅ Delivery boy role exists</p>';
} else {
    echo '<p>❌ Delivery boy role missing</p>';
}

// Test delivery dashboard URL
echo '<h2>URL Test</h2>';
$dashboard_url = home_url( '/delivery-dashboard/' );
echo '<p>Test delivery dashboard: <a href="' . esc_url( $dashboard_url ) . '" target="_blank">' . esc_html( $dashboard_url ) . '</a></p>';

// Clean up test order
wp_delete_post( $test_order->get_id(), true );
echo '<p>✅ Test cleanup completed</p>';

echo '<hr>';
echo '<h2>Summary of Fixes Applied</h2>';
echo '<ul>';
echo '<li>✅ Fixed meta_query in dashboard - removed redundant EXISTS + > 0 checks</li>';
echo '<li>✅ Added template_include filter for proper delivery dashboard routing</li>';
echo '<li>✅ Granted fxw_delivery_access capability to administrators</li>';
echo '<li>✅ Simplified template routing by consolidating to core class</li>';
echo '<li>✅ Flushed rewrite rules to ensure URL routing works</li>';
echo '</ul>';

echo '<p><strong>Next Steps:</strong></p>';
echo '<ul>';
echo '<li>Visit the <a href="' . esc_url( admin_url( 'admin.php?page=fxw-deliveries-dashboard' ) ) . '">Admin Deliveries Dashboard</a> to test order management</li>';
echo '<li>Create a test delivery boy user and test the <a href="' . esc_url( $dashboard_url ) . '">Delivery Dashboard</a></li>';
echo '<li>Test assigning orders and changing statuses</li>';
echo '</ul>';
