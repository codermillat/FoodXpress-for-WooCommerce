<?php
/**
 * Test script to verify receipt printing functionality
 * 
 * This script should be run from WordPress admin or with proper WP environment
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // For testing purposes, we'll load WordPress if run directly
    require_once( '../../../wp-load.php' );
}

echo "<h2>FoodXpress Receipt Printing Test</h2>";

// Check if WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
    echo "<p style='color: red;'>❌ WooCommerce is not active!</p>";
    exit;
}

echo "<p style='color: green;'>✅ WooCommerce is active</p>";

// Check if FoodXpress plugin is loaded
if ( ! defined( 'FXW_VERSION' ) ) {
    echo "<p style='color: red;'>❌ FoodXpress plugin is not loaded!</p>";
    exit;
}

echo "<p style='color: green;'>✅ FoodXpress plugin is loaded (v" . FXW_VERSION . ")</p>";

// Check if AJAX endpoint is registered
$ajax_actions = $GLOBALS['wp_filter']['wp_ajax_fxw_print_receipt'] ?? null;
$ajax_nopriv_actions = $GLOBALS['wp_filter']['wp_ajax_nopriv_fxw_print_receipt'] ?? null;

if ( $ajax_actions ) {
    echo "<p style='color: green;'>✅ AJAX endpoint 'fxw_print_receipt' is registered for logged-in users</p>";
} else {
    echo "<p style='color: red;'>❌ AJAX endpoint 'fxw_print_receipt' is NOT registered for logged-in users</p>";
}

if ( $ajax_nopriv_actions ) {
    echo "<p style='color: green;'>✅ AJAX endpoint 'fxw_print_receipt' is registered for non-logged-in users</p>";
} else {
    echo "<p style='color: red;'>❌ AJAX endpoint 'fxw_print_receipt' is NOT registered for non-logged-in users</p>";
}

// Check if receipt template exists
$template_path = FXW_PLUGIN_DIR . 'templates/receipt-template.php';
if ( file_exists( $template_path ) ) {
    echo "<p style='color: green;'>✅ Receipt template exists</p>";
    
    // Check if template has content
    $template_content = file_get_contents( $template_path );
    if ( strlen( $template_content ) > 100 ) {
        echo "<p style='color: green;'>✅ Receipt template has content (" . strlen( $template_content ) . " characters)</p>";
    } else {
        echo "<p style='color: red;'>❌ Receipt template is too short (may be empty)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Receipt template does not exist at: " . $template_path . "</p>";
}

// Check for JavaScript file
$js_path = FXW_PLUGIN_URL . 'assets/js/delivery-dashboard.js';
$js_file_path = FXW_PLUGIN_DIR . 'assets/js/delivery-dashboard.js';

if ( file_exists( $js_file_path ) ) {
    echo "<p style='color: green;'>✅ JavaScript file exists</p>";
    
    $js_content = file_get_contents( $js_file_path );
    if ( strpos( $js_content, 'fxw-print-receipt' ) !== false ) {
        echo "<p style='color: green;'>✅ JavaScript contains print receipt handler</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ JavaScript file exists but may not contain print receipt handler</p>";
    }
} else {
    echo "<p style='color: red;'>❌ JavaScript file does not exist</p>";
}

// Check for CSS file
$css_file_path = FXW_PLUGIN_DIR . 'assets/css/frontend.css';

if ( file_exists( $css_file_path ) ) {
    echo "<p style='color: green;'>✅ CSS file exists</p>";
    
    $css_content = file_get_contents( $css_file_path );
    if ( strpos( $css_content, 'fxw-button-print' ) !== false ) {
        echo "<p style='color: green;'>✅ CSS contains print button styling</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ CSS file exists but may not contain print button styling</p>";
    }
} else {
    echo "<p style='color: red;'>❌ CSS file does not exist</p>";
}

// Get sample order for testing
$orders = wc_get_orders( array(
    'limit' => 1,
    'status' => array( 'fxw-assigned', 'fxw-picked-up', 'completed' )
) );

if ( $orders ) {
    $order = $orders[0];
    echo "<p style='color: green;'>✅ Found test order #" . $order->get_id() . "</p>";
    
    // Create test receipt URL
    $receipt_url = admin_url( 'admin-ajax.php' ) . '?action=fxw_print_receipt&order_id=' . $order->get_id();
    echo "<p><strong>Test Receipt URL:</strong> <a href='" . esc_url( $receipt_url ) . "' target='_blank'>" . esc_url( $receipt_url ) . "</a></p>";
    
    echo "<p><em>Click the link above to test the receipt printing functionality.</em></p>";
} else {
    echo "<p style='color: orange;'>⚠️ No test orders found. Create an order with FoodXpress status to test.</p>";
}

echo "<hr>";
echo "<h3>Manual Test Instructions:</h3>";
echo "<ol>";
echo "<li>Go to the delivery dashboard (for delivery boys)</li>";
echo "<li>Look for orders with a 'Print Receipt' button</li>";
echo "<li>Click the 'Print Receipt' button</li>";
echo "<li>A new window should open with the receipt and print dialog</li>";
echo "</ol>";

echo "<h3>Admin Test Instructions:</h3>";
echo "<ol>";
echo "<li>Go to WooCommerce → Orders</li>";
echo "<li>Find an order with FoodXpress status</li>";
echo "<li>Look for a 'Print Receipt' link/button</li>";
echo "<li>Click it to test receipt printing</li>";
echo "</ol>";
?>
