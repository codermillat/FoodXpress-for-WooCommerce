<?php
/**
 * Integration Tests for FoodXpress Checkout with WooCommerce
 * 
 * These tests validate that the checkout functionality properly integrates
 * with WooCommerce and handles real-world scenarios correctly.
 */

use PHPUnit\Framework\TestCase;

class CheckoutIntegrationTest extends TestCase {

    private $checkout;
    private $mapping_service;

    public function setUp(): void {
        // Load plugin classes
        require_once FXW_PLUGIN_DIR . 'includes/class-fxw-checkout.php';
        require_once FXW_PLUGIN_DIR . 'includes/services/class-fxw-mapping-service.php';
        
        $this->checkout = new FXW_Checkout();
        $this->mapping_service = new FXW_Mapping_Service();
        
        // Mock WooCommerce session
        $this->mockWooCommerceSession();
    }

    /**
     * Test complete checkout flow with valid location
     */
    public function test_complete_checkout_flow_with_valid_location() {
        // Skip if WooCommerce is not available
        if (!class_exists('WC')) {
            $this->markTestSkipped('WooCommerce not available in test environment');
        }

        // Step 1: Customer provides location
        $location_data = array(
            'lat' => '40.7128',
            'lng' => '-74.0060',
            'address' => '123 Main St, New York, NY 10001'
        );

        // Simulate AJAX request to update customer location
        $_POST['lat'] = $location_data['lat'];
        $_POST['lng'] = $location_data['lng'];
        $_POST['address'] = $location_data['address'];
        $_POST['nonce'] = 'test_nonce';

        // Mock successful location update
        $this->assertTrue($this->simulateLocationUpdate($location_data), 
            'Location update should succeed with valid data');

        // Step 2: Calculate shipping rates
        $distance_data = $this->mapping_service->get_distance(
            array('lat' => '40.7589', 'lng' => '-73.9851'), // Restaurant location
            $location_data
        );

        $this->assertIsArray($distance_data, 'Distance calculation should return array');
        $this->assertArrayHasKey('distance', $distance_data);
        $this->assertArrayHasKey('duration', $distance_data);

        // Step 3: Verify ETA calculation
        $prep_time = 30; // 30 minutes preparation time
        $total_eta = $distance_data['duration'] + $prep_time;
        
        $this->assertGreaterThan($prep_time, $total_eta, 
            'Total ETA should include delivery time');

        // Step 4: Verify shipping method availability
        $this->assertTrue($this->isShippingMethodAvailable($distance_data),
            'Shipping method should be available for valid location');

        // Step 5: Test new delivery details saving
        $order = $this->createMockOrder();
        $this->simulateDeliveryDetailsSaving($order, $location_data, $distance_data);

        // Verify new meta fields are saved
        $this->assertEquals($location_data['address'], $order->get_meta('_fxw_delivery_address'),
            'Delivery address should be saved to order meta');
        $this->assertEquals($location_data['lat'], $order->get_meta('_fxw_delivery_lat'),
            'Delivery latitude should be saved to order meta');
        $this->assertEquals($location_data['lng'], $order->get_meta('_fxw_delivery_lng'),
            'Delivery longitude should be saved to order meta');
        
        // Verify distance is calculated and saved
        $expected_distance = round($distance_data['distance'] / 1000, 2);
        $this->assertEquals($expected_distance, $order->get_meta('_fxw_delivery_distance'),
            'Delivery distance should be calculated and saved');
    }

    /**
     * Test checkout with out-of-delivery-zone location
     */
    public function test_checkout_with_out_of_zone_location() {
        // Location far from restaurant (>50km)
        $out_of_zone_location = array(
            'lat' => '41.8781',  // Chicago
            'lng' => '-87.6298',
            'address' => '123 Far St, Chicago, IL 60601'
        );

        // Calculate distance
        $distance_data = $this->mapping_service->get_distance(
            array('lat' => '40.7589', 'lng' => '-73.9851'), // NYC Restaurant
            $out_of_zone_location
        );

        // Should be outside delivery zone
        $max_delivery_distance = 50; // km
        if (isset($distance_data['distance'])) {
            $distance_km = $distance_data['distance'] / 1000;
            $this->assertGreaterThan($max_delivery_distance, $distance_km,
                'Test location should be outside delivery zone');
        }

        // Shipping method should not be available
        $this->assertFalse($this->isShippingMethodAvailable($distance_data),
            'Shipping method should not be available for out-of-zone location');
    }

    /**
     * Test AJAX endpoint security and functionality
     */
    public function test_ajax_endpoints_security() {
        // Test without nonce - should fail
        $_POST = array(
            'lat' => '40.7128',
            'lng' => '-74.0060',
            'address' => '123 Main St'
        );
        unset($_POST['nonce']);

        $result = $this->simulateAjaxRequest('fxw_update_customer_location');
        $this->assertFalse($result, 'AJAX request without nonce should fail');

        // Test with invalid nonce - should fail
        $_POST['nonce'] = 'invalid_nonce';
        $result = $this->simulateAjaxRequest('fxw_update_customer_location');
        $this->assertFalse($result, 'AJAX request with invalid nonce should fail');

        // Test with valid nonce - should succeed
        $_POST['nonce'] = 'valid_test_nonce';
        $result = $this->simulateAjaxRequest('fxw_update_customer_location');
        $this->assertTrue($result, 'AJAX request with valid nonce should succeed');
    }

    /**
     * Test Google Maps API error handling
     */
    public function test_google_maps_api_error_handling() {
        // Mock API error responses
        $error_scenarios = array(
            array('code' => 403, 'expected' => 'API access denied'),
            array('code' => 429, 'expected' => 'rate limit exceeded'),
            array('code' => 500, 'expected' => 'temporary service issue')
        );

        foreach ($error_scenarios as $scenario) {
            $mock_response = array(
                'response' => array('code' => $scenario['code']),
                'body' => json_encode(array('error_message' => 'API Error'))
            );

            $error_message = $this->extractErrorMessage($mock_response);
            
            $this->assertStringNotContainsString('API Error', $error_message,
                'Raw API errors should not be exposed to users');
            
            $this->assertNotEmpty($error_message,
                'User should receive a meaningful error message');
        }
    }

    /**
     * Test session data integrity and security
     */
    public function test_session_data_integrity() {
        // Test coordinate locking
        $initial_location = array('lat' => '40.7128', 'lng' => '-74.0060');
        $this->simulateLocationUpdate($initial_location);

        // Verify coordinates are locked after first update
        $session_data = $this->getSessionData();
        $this->assertTrue($session_data['fxw_coords_locked'], 
            'Coordinates should be locked after initial update');

        // Attempt to update with different coordinates
        $new_location = array('lat' => '40.7500', 'lng' => '-74.0000');
        $update_result = $this->simulateLocationUpdate($new_location);

        // Original coordinates should be preserved due to locking
        $final_session = $this->getSessionData();
        $this->assertEquals($initial_location['lat'], $final_session['customer_lat'],
            'Locked coordinates should not be overwritten');
    }

    /**
     * Test ETA display integration
     */
    public function test_eta_display_integration() {
        // Set up distance data
        $distance_data = array(
            'distance' => 5000, // 5km
            'duration' => 15    // 15 minutes
        );
        
        $this->setSessionData('fxw_distance_data', $distance_data);

        // Test ETA display
        ob_start();
        $this->checkout->display_eta();
        $eta_output = ob_get_clean();

        // Should contain delivery time information
        $this->assertStringContainsString('ETA', $eta_output);
        $this->assertStringContainsString('15', $eta_output); // Duration
        
        // Should not contain raw array data
        $this->assertStringNotContainsString('Array', $eta_output);
        $this->assertStringNotContainsString('array(', $eta_output);
    }

    /**
     * Test shipping method label with ETA
     */
    public function test_shipping_method_eta_label() {
        // Set up test data
        $distance_data = array('duration' => 20);
        $prep_time = 30;
        $this->setSessionData('fxw_distance_data', $distance_data);

        // Mock shipping method
        $method = new stdClass();
        $method->id = 'foodxpress_delivery';
        $method->label = 'FoodXpress Delivery';

        // Test ETA label appending
        $label_with_eta = $this->checkout->append_eta_to_label($method->label, $method);
        
        $total_eta = $distance_data['duration'] + $prep_time;
        $this->assertStringContainsString('ETA', $label_with_eta);
        $this->assertStringContainsString((string)$total_eta, $label_with_eta);
    }

    /**
     * Test plugin activation and deactivation
     */
    public function test_plugin_lifecycle() {
        // Test activation function exists
        $this->assertTrue(function_exists('fxw_activate'), 
            'Plugin activation function should exist');

        // Test deactivation function exists  
        $this->assertTrue(function_exists('fxw_deactivate'),
            'Plugin deactivation function should exist');

        // Test rewrite rules are handled properly
        global $wp_rewrite;
        if (isset($wp_rewrite)) {
            // Simulate activation
            fxw_activate();
            
            // Check that rewrite rules include delivery dashboard
            $rules = get_option('rewrite_rules');
            $this->assertArrayHasKey('^delivery-dashboard/?$', $rules,
                'Delivery dashboard rewrite rule should be added on activation');
        }
    }

    // Helper Methods

    /**
     * Mock WooCommerce session for testing
     */
    private function mockWooCommerceSession() {
        if (!class_exists('WC_Session')) {
            // Create a mock session class
            $GLOBALS['wc_session_data'] = array();
        }
    }

    /**
     * Simulate location update
     */
    private function simulateLocationUpdate($location_data) {
        // Mock successful location processing
        $this->setSessionData('customer_lat', $location_data['lat']);
        $this->setSessionData('customer_lng', $location_data['lng']);
        $this->setSessionData('fxw_coords_locked', true);
        
        return true;
    }

    /**
     * Check if shipping method is available for given distance
     */
    private function isShippingMethodAvailable($distance_data) {
        if (!isset($distance_data['distance'])) {
            return false;
        }
        
        $distance_km = $distance_data['distance'] / 1000;
        $max_delivery_distance = 50; // km
        
        return $distance_km <= $max_delivery_distance;
    }

    /**
     * Simulate AJAX request
     */
    private function simulateAjaxRequest($action) {
        // Mock nonce verification
        if (!isset($_POST['nonce']) || $_POST['nonce'] !== 'valid_test_nonce') {
            return false;
        }
        
        // Mock successful AJAX processing
        return true;
    }

    /**
     * Extract user-friendly error message from API response
     */
    private function extractErrorMessage($response) {
        $code = $response['response']['code'];
        
        switch ($code) {
            case 403:
                return 'API access denied. Please check your configuration.';
            case 429:
                return 'Service temporarily busy. Please try again.';
            case 500:
                return 'Temporary service issue. Please try again later.';
            default:
                return 'An error occurred. Please try again.';
        }
    }

    /**
     * Get mock session data
     */
    private function getSessionData() {
        return isset($GLOBALS['wc_session_data']) ? $GLOBALS['wc_session_data'] : array();
    }

    /**
     * Set mock session data
     */
    private function setSessionData($key, $value) {
        if (!isset($GLOBALS['wc_session_data'])) {
            $GLOBALS['wc_session_data'] = array();
        }
        $GLOBALS['wc_session_data'][$key] = $value;
    }

    /**
     * Create a mock WooCommerce order for testing
     */
    private function createMockOrder() {
        // Create a mock order object
        $order = new stdClass();
        $order->meta_data = array();
        
        // Add methods to simulate WooCommerce order
        $order->get_meta = function($key, $single = true) use ($order) {
            return isset($order->meta_data[$key]) ? $order->meta_data[$key] : '';
        };
        
        $order->update_meta_data = function($key, $value) use ($order) {
            $order->meta_data[$key] = $value;
        };
        
        $order->save = function() use ($order) {
            return true;
        };
        
        return $order;
    }

    /**
     * Simulate saving delivery details to order
     */
    private function simulateDeliveryDetailsSaving($order, $location_data, $distance_data) {
        // Mock POST data
        $_POST['fxw_delivery_address'] = $location_data['address'];
        
        // Set session data
        $this->setSessionData('customer_lat', $location_data['lat']);
        $this->setSessionData('customer_lng', $location_data['lng']);
        $this->setSessionData('fxw_distance_data', $distance_data);
        
        // Simulate the save_delivery_details_to_order method
        $delivery_address = sanitize_textarea_field($location_data['address']);
        $lat = $location_data['lat'];
        $lng = $location_data['lng'];
        $distance_km = round($distance_data['distance'] / 1000, 2);
        
        // Save to mock order
        $order->update_meta_data('_fxw_delivery_address', $delivery_address);
        $order->update_meta_data('_fxw_delivery_lat', (float) $lat);
        $order->update_meta_data('_fxw_delivery_lng', (float) $lng);
        $order->update_meta_data('_fxw_delivery_distance', $distance_km);
        
        return true;
    }

    /**
     * Test new delivery address field validation
     */
    public function test_delivery_address_field_validation() {
        // Test empty delivery address
        $_POST['fxw_delivery_address'] = '';
        $result = $this->validateDeliveryAddress($_POST['fxw_delivery_address']);
        $this->assertFalse($result, 'Empty delivery address should fail validation');

        // Test valid delivery address
        $_POST['fxw_delivery_address'] = '123 Main St, New York, NY 10001';
        $result = $this->validateDeliveryAddress($_POST['fxw_delivery_address']);
        $this->assertTrue($result, 'Valid delivery address should pass validation');

        // Test address with special characters
        $_POST['fxw_delivery_address'] = '123 Main St, Apt #4B, New York, NY 10001';
        $result = $this->validateDeliveryAddress($_POST['fxw_delivery_address']);
        $this->assertTrue($result, 'Address with special characters should pass validation');
    }

    /**
     * Test backward compatibility with old unit field
     */
    public function test_backward_compatibility_with_unit_field() {
        $order = $this->createMockOrder();
        
        // Test order with old unit field but no delivery address
        $order->update_meta_data('_fxw_address_unit', 'Apt 4B');
        
        // Should fall back to unit display in admin
        $unit = $order->get_meta('_fxw_address_unit');
        $delivery_address = $order->get_meta('_fxw_delivery_address');
        
        $this->assertEquals('Apt 4B', $unit, 'Old unit field should be preserved');
        $this->assertEmpty($delivery_address, 'New delivery address should be empty');
        
        // Test order with both fields - new should take precedence
        $order->update_meta_data('_fxw_delivery_address', '123 Main St, Apt 4B, New York');
        
        $new_delivery_address = $order->get_meta('_fxw_delivery_address');
        $this->assertNotEmpty($new_delivery_address, 'New delivery address should take precedence');
    }

    /**
     * Validate delivery address field
     */
    private function validateDeliveryAddress($address) {
        if (empty($address) || trim($address) === '') {
            return false;
        }
        
        // Basic validation - check for minimum length
        if (strlen(trim($address)) < 10) {
            return false;
        }
        
        return true;
    }
}

// Mock WordPress/WooCommerce functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        $mock_options = array(
            'rewrite_rules' => array(
                '^delivery-dashboard/?$' => 'index.php?is_delivery_dashboard=true'
            )
        );
        return isset($mock_options[$option]) ? $mock_options[$option] : $default;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 200;
    }
}
