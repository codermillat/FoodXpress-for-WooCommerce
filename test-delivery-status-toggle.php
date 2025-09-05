<?php
/**
 * Test script to verify and debug the delivery status toggle functionality
 */

// WordPress environment setup
if (!defined('ABSPATH')) {
    // Try to find wp-load.php from the plugin directory
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        echo "WordPress environment not found. Running static analysis only.\n\n";
    }
}

echo "=== Delivery Status Toggle Functionality Test ===\n\n";

// Test 1: Check if classes exist
echo "1. Class Availability Check:\n";
if (class_exists('FXW_Admin_Bar')) {
    echo "   ✓ FXW_Admin_Bar class exists\n";
} else {
    echo "   ✗ FXW_Admin_Bar class missing\n";
}

if (class_exists('FXW_Shipping_Method')) {
    echo "   ✓ FXW_Shipping_Method class exists\n";
} else {
    echo "   ✗ FXW_Shipping_Method class missing\n";
}

// Test 2: Check admin bar file implementation
echo "\n2. Admin Bar Implementation Check:\n";
$admin_bar_content = file_get_contents('includes/class-fxw-admin-bar.php');

if (strpos($admin_bar_content, 'add_delivery_status_toggle') !== false) {
    echo "   ✓ add_delivery_status_toggle method exists\n";
} else {
    echo "   ✗ add_delivery_status_toggle method missing\n";
}

if (strpos($admin_bar_content, 'toggle_delivery_status') !== false) {
    echo "   ✓ toggle_delivery_status AJAX handler exists\n";
} else {
    echo "   ✗ toggle_delivery_status AJAX handler missing\n";
}

if (strpos($admin_bar_content, 'fxw_is_open') !== false) {
    echo "   ✓ fxw_is_open setting referenced\n";
} else {
    echo "   ✗ fxw_is_open setting not found\n";
}

// Test 3: Check JavaScript implementation
echo "\n3. JavaScript Implementation Check:\n";
$admin_js_content = file_get_contents('assets/js/admin.js');

if (strpos($admin_js_content, 'fxw_toggle_delivery_status') !== false) {
    echo "   ✓ fxw_toggle_delivery_status function exists\n";
} else {
    echo "   ✗ fxw_toggle_delivery_status function missing\n";
}

if (strpos($admin_js_content, 'fxw_toggle_delivery_status') !== false) {
    echo "   ✓ AJAX action correctly named\n";
} else {
    echo "   ✗ AJAX action name issue\n";
}

// Test 4: Check shipping method integration
echo "\n4. Shipping Method Integration Check:\n";
$shipping_content = file_get_contents('includes/class-fxw-shipping-method.php');

if (strpos($shipping_content, 'fxw_is_open') !== false) {
    echo "   ✓ Shipping method checks fxw_is_open setting\n";
} else {
    echo "   ✗ Shipping method doesn't check fxw_is_open\n";
}

if (strpos($shipping_content, '! $is_open') !== false) {
    echo "   ✓ Shipping method returns early when closed\n";
} else {
    echo "   ✗ Shipping method doesn't handle closed state\n";
}

// Test 5: WordPress environment tests (if available)
if (function_exists('get_option')) {
    echo "\n5. WordPress Environment Tests:\n";
    
    $options = get_option('fxw_settings', array());
    $is_open = isset($options['fxw_is_open']) ? $options['fxw_is_open'] : 'not set';
    
    echo "   Current fxw_is_open value: " . var_export($is_open, true) . "\n";
    
    if (isset($options['fxw_is_open'])) {
        echo "   ✓ fxw_is_open setting exists in database\n";
    } else {
        echo "   ⚠ fxw_is_open setting not found (defaults to true)\n";
    }
    
    // Test AJAX action registration
    if (has_action('wp_ajax_fxw_toggle_delivery_status')) {
        echo "   ✓ AJAX action properly registered\n";
    } else {
        echo "   ✗ AJAX action not registered\n";
    }
    
    // Test admin bar hook
    if (has_action('admin_bar_menu')) {
        echo "   ✓ Admin bar menu hook exists\n";
    } else {
        echo "   ✗ Admin bar menu hook missing\n";
    }
} else {
    echo "\n5. WordPress Environment Tests: SKIPPED (WordPress not loaded)\n";
}

echo "\n=== Potential Issues & Fixes ===\n";

// Check for common issues
$issues = array();

// Issue 1: JavaScript not loaded
if (!strpos($admin_js_content, 'ajaxurl')) {
    $issues[] = "JavaScript may not have access to ajaxurl variable";
}

// Issue 2: Admin bar not instantiated
if (!strpos($admin_bar_content, 'new FXW_Admin_Bar()')) {
    $issues[] = "FXW_Admin_Bar class may not be instantiated";
}

// Issue 3: Core class not loading admin bar
$core_content = file_get_contents('includes/class-fxw-core.php');
if (strpos($core_content, 'class-fxw-admin-bar.php') === false) {
    $issues[] = "Core class may not be loading admin bar file";
}

if (empty($issues)) {
    echo "No obvious issues detected in static analysis.\n";
} else {
    foreach ($issues as $issue) {
        echo "⚠ " . $issue . "\n";
    }
}

echo "\n=== Next Steps ===\n";
echo "1. Verify WordPress environment and plugin activation\n";
echo "2. Check browser console for JavaScript errors\n";
echo "3. Test AJAX endpoint manually\n";
echo "4. Verify admin bar permissions\n";
echo "5. Test shipping calculation with different statuses\n";
?>
