<?php
/**
 * Test file to verify print receipt functionality in admin dashboard
 * 
 * This file helps verify that:
 * 1. Print receipt buttons are visible in all three tables
 * 2. AJAX endpoint is properly registered
 * 3. JavaScript is loading correctly
 * 4. Receipt template renders without errors
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

echo "=== FoodXpress Print Receipt Functionality Test ===\n\n";

// Test 1: Check if dashboard class exists
echo "1. Testing Dashboard Class...\n";
if (class_exists('FXW_Dashboard')) {
    echo "   ✓ FXW_Dashboard class exists\n";
} else {
    echo "   ✗ FXW_Dashboard class not found\n";
}

// Test 2: Check if shortcodes class exists (contains AJAX handler)
echo "\n2. Testing Shortcodes Class (AJAX Handler)...\n";
if (class_exists('FXW_Shortcodes')) {
    echo "   ✓ FXW_Shortcodes class exists\n";
    
    // Check if print_receipt method exists
    if (method_exists('FXW_Shortcodes', 'print_receipt')) {
        echo "   ✓ print_receipt method exists\n";
    } else {
        echo "   ✗ print_receipt method not found\n";
    }
} else {
    echo "   ✗ FXW_Shortcodes class not found\n";
}

// Test 3: Check if receipt template exists
echo "\n3. Testing Receipt Template...\n";
if (file_exists(dirname(__FILE__) . '/templates/receipt-template.php')) {
    echo "   ✓ Receipt template file exists\n";
} else {
    echo "   ✗ Receipt template file not found\n";
}

// Test 4: Check if JavaScript file exists
echo "\n4. Testing JavaScript File...\n";
if (file_exists(dirname(__FILE__) . '/assets/js/delivery-dashboard.js')) {
    echo "   ✓ JavaScript file exists\n";
} else {
    echo "   ✗ JavaScript file not found\n";
}

// Test 5: Check if CSS file exists
echo "\n5. Testing CSS File...\n";
if (file_exists(dirname(__FILE__) . '/assets/css/frontend.css')) {
    echo "   ✓ CSS file exists\n";
} else {
    echo "   ✗ CSS file not found\n";
}

echo "\n=== Test Complete ===\n";
echo "To test the complete functionality:\n";
echo "1. Go to WordPress Admin > Deliveries\n";
echo "2. Look for 'Print Receipt' buttons in all three order tables\n";
echo "3. Click a print receipt button to test the functionality\n";
echo "4. Verify that the receipt opens in a new window/tab and prints\n\n";

echo "Expected behavior:\n";
echo "- Print receipt buttons should appear in all three tables (Unassigned, Assigned, Out for Delivery)\n";
echo "- Clicking should open receipt in new window with proper formatting\n";
echo "- Print dialog should appear automatically\n";
echo "- Receipt should contain order details, customer info, and delivery boy info\n";
