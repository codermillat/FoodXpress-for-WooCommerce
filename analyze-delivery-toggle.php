<?php
/**
 * Static analysis of delivery status toggle functionality
 */

echo "=== Delivery Status Toggle Static Analysis ===\n\n";

// Test 1: Check admin bar implementation
echo "1. Admin Bar Implementation:\n";
$admin_bar_content = file_get_contents('includes/class-fxw-admin-bar.php');

$admin_bar_checks = [
    'add_delivery_status_toggle method' => strpos($admin_bar_content, 'add_delivery_status_toggle'),
    'toggle_delivery_status AJAX handler' => strpos($admin_bar_content, 'toggle_delivery_status'),
    'fxw_is_open setting usage' => strpos($admin_bar_content, 'fxw_is_open'),
    'wp_send_json_success response' => strpos($admin_bar_content, 'wp_send_json_success'),
    'Class instantiation' => strpos($admin_bar_content, 'new FXW_Admin_Bar()')
];

foreach ($admin_bar_checks as $check => $result) {
    echo "   " . ($result !== false ? "✓" : "✗") . " {$check}\n";
}

// Test 2: Check JavaScript implementation
echo "\n2. JavaScript Implementation:\n";
$admin_js_content = file_get_contents('assets/js/admin.js');

$js_checks = [
    'fxw_toggle_delivery_status function' => strpos($admin_js_content, 'function fxw_toggle_delivery_status'),
    'AJAX action name' => strpos($admin_js_content, 'fxw_toggle_delivery_status'),
    'jQuery.post usage' => strpos($admin_js_content, 'jQuery.post'),
    'ajaxurl variable' => strpos($admin_js_content, 'ajaxurl'),
    'Response handling' => strpos($admin_js_content, 'response.data.label')
];

foreach ($js_checks as $check => $result) {
    echo "   " . ($result !== false ? "✓" : "✗") . " {$check}\n";
}

// Test 3: Check shipping method integration
echo "\n3. Shipping Method Integration:\n";
$shipping_content = file_get_contents('includes/class-fxw-shipping-method.php');

$shipping_checks = [
    'fxw_is_open check' => strpos($shipping_content, 'fxw_is_open'),
    'Early return when closed' => strpos($shipping_content, '! $is_open'),
    'Settings option loading' => strpos($shipping_content, "get_option( 'fxw_settings' )"),
    'Debug logging' => strpos($shipping_content, 'wc_get_logger')
];

foreach ($shipping_checks as $check => $result) {
    echo "   " . ($result !== false ? "✓" : "✗") . " {$check}\n";
}

// Test 4: Check core class loading
echo "\n4. Core Class Integration:\n";
$core_content = file_get_contents('includes/class-fxw-core.php');

$core_checks = [
    'Admin bar file loading' => strpos($core_content, 'class-fxw-admin-bar.php'),
    'Admin script enqueueing' => strpos($core_content, 'enqueue_admin_scripts'),
    'is_admin() check' => strpos($core_content, 'is_admin()')
];

foreach ($core_checks as $check => $result) {
    echo "   " . ($result !== false ? "✓" : "✗") . " {$check}\n";
}

// Test 5: Identify potential issues
echo "\n5. Potential Issues Analysis:\n";

$issues = [];

// Check if admin bar is properly instantiated
if (strpos($admin_bar_content, 'new FXW_Admin_Bar()') === false) {
    $issues[] = "Admin bar class not instantiated at end of file";
}

// Check if ajaxurl is available
if (strpos($admin_js_content, 'ajaxurl') === false) {
    $issues[] = "JavaScript may not have access to ajaxurl";
}

// Check if core loads admin bar file
if (strpos($core_content, 'class-fxw-admin-bar.php') === false) {
    $issues[] = "Core class doesn't load admin bar file";
}

// Check script enqueueing
if (strpos($core_content, 'admin.js') === false) {
    $issues[] = "Admin JavaScript may not be enqueued";
}

if (empty($issues)) {
    echo "   ✓ No obvious issues found\n";
} else {
    foreach ($issues as $issue) {
        echo "   ✗ {$issue}\n";
    }
}

echo "\n=== Recommendations ===\n";

// Analyze line where admin bar is loaded
if (strpos($core_content, 'class-fxw-admin-bar.php') !== false) {
    echo "✓ Admin bar file is loaded by core class\n";
} else {
    echo "⚠ Need to ensure admin bar file is loaded\n";
}

// Check if admin script has proper localization for ajaxurl
$admin_script_line = '';
$core_lines = explode("\n", $core_content);
foreach ($core_lines as $line) {
    if (strpos($line, 'admin.js') !== false) {
        $admin_script_line = trim($line);
        break;
    }
}

if ($admin_script_line) {
    echo "✓ Admin script found: " . $admin_script_line . "\n";
    if (strpos($admin_script_line, 'wp_enqueue_script') !== false) {
        echo "✓ Script properly enqueued\n";
    }
} else {
    echo "⚠ Admin script enqueuing may need verification\n";
}

echo "\n=== Next Steps to Fix ===\n";
echo "1. Ensure WordPress admin area has access to ajaxurl\n";
echo "2. Verify admin bar button appears for administrators\n";
echo "3. Test AJAX functionality in browser console\n";
echo "4. Confirm shipping method respects the setting\n";

echo "\n=== Quick Test Commands ===\n";
echo "To test in WordPress admin:\n";
echo "console.log(typeof ajaxurl); // Should log 'string'\n";
echo "console.log(typeof fxw_toggle_delivery_status); // Should log 'function'\n";
?>
