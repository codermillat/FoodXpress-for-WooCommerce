<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Controller for Checkout operations.
 *
 * @since      1.1.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_REST_Checkout_Controller extends WP_REST_Controller
{

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'foodxpress/v1';

    /**
     * Endpoint base.
     *
     * @var string
     */
    protected $rest_base = 'checkout';

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        // GET /wp-json/foodxpress/v1/checkout/settings
        register_rest_route($this->namespace, '/' . $this->rest_base . '/settings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_settings'),
                'permission_callback' => '__return_true', // Public endpoint for frontend config
            ),
        ));

        // POST /wp-json/foodxpress/v1/checkout/validate-location
        register_rest_route($this->namespace, '/' . $this->rest_base . '/validate-location', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'validate_location'),
                'permission_callback' => '__return_true', // Public endpoint but rate limited
                'args' => $this->get_validate_location_args(),
            ),
        ));
    }

    /**
     * Get public settings for the checkout frontend.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_settings($request)
    {
        $options = get_option('fxw_settings');

        // Return only safe, public settings
        $data = array(
            'radius' => isset($options['fxw_delivery_zone_radius']) ? (float) $options['fxw_delivery_zone_radius'] : 10,
            'is_open' => isset($options['fxw_is_open']) ? filter_var($options['fxw_is_open'], FILTER_VALIDATE_BOOLEAN) : true,
            'messages' => array(
                'out_of_zone' => __('Sorry, we do not deliver to this location.', 'foodxpress'),
                'store_closed' => __('Sorry, we are currently closed for deliveries.', 'foodxpress'),
            )
        );

        return rest_ensure_response($data);
    }

    /**
     * Validate a location and calculate delivery fee.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function validate_location($request)
    {
        // Rate Limiting Check
        if (class_exists('FXW_Rate_Limiter')) {
            $limit_check = FXW_Rate_Limiter::check_rate_limit('validate_location_rest', 30, MINUTE_IN_SECONDS);
            if (is_wp_error($limit_check)) {
                return $limit_check;
            }
        }

        $params = $request->get_params();
        $lat = (float) $params['lat'];
        $lng = (float) $params['lng'];

        // Basic coordinate validation
        if (abs($lat) > 90 || abs($lng) > 180) {
            return new WP_Error('invalid_coordinates', __('Invalid coordinates provided.', 'foodxpress'), array('status' => 400));
        }

        $options = get_option('fxw_settings');

        // Check if store is open
        $is_open = isset($options['fxw_is_open']) ? filter_var($options['fxw_is_open'], FILTER_VALIDATE_BOOLEAN) : true;
        if (!$is_open) {
            return new WP_Error('store_closed', __('Sorry, we are currently closed for deliveries.', 'foodxpress'), array('status' => 400));
        }

        // Restaurant coordinates — explicit setting, else geocoded address (cached)
        $mapping_service = new FXW_Mapping_Service();
        $restaurant = $mapping_service->get_restaurant_location($options);

        if (is_wp_error($restaurant)) {
            return new WP_Error('configuration_error', $restaurant->get_error_message(), array('status' => 500));
        }

        $distance_data = $mapping_service->get_distance($restaurant, array('lat' => $lat, 'lng' => $lng));

        if (is_wp_error($distance_data)) {
            return $distance_data;
        }

        if (!isset($distance_data['distance']) || !is_object($distance_data['distance']) || !isset($distance_data['distance']->value)) {
            return new WP_Error('distance_error', __('Could not calculate delivery distance.', 'foodxpress'), array('status' => 500));
        }

        $distance_in_km = $distance_data['distance']->value / 1000;
        $radius = isset($options['fxw_delivery_zone_radius']) ? (float) $options['fxw_delivery_zone_radius'] : 10;

        if ($distance_in_km > $radius) {
            return new WP_Error('out_of_zone', __('Sorry, we do not deliver to your location.', 'foodxpress'), array('status' => 400));
        }

        // Calculate Fee (estimate — honors configured tiers + threshold)
        $base_fee = isset($options['fxw_delivery_fee_base']) ? (float) $options['fxw_delivery_fee_base'] : 5;
        $fee_per_km = isset($options['fxw_delivery_fee_per_km']) ? (float) $options['fxw_delivery_fee_per_km'] : 1.5;
        $cost = $base_fee + ($distance_in_km * $fee_per_km);
        if (class_exists('FXW_Pricing')) {
            $tier_fee = FXW_Pricing::fee_for_distance($distance_in_km, $options);
            if (null !== $tier_fee && false !== $tier_fee) {
                $cost = $tier_fee;
            }
            if (FXW_Pricing::is_free_delivery()) {
                $cost = 0;
            }
        }

        // Store in session for checkout (Critical for order processing).
        // Note: REST requests from same-origin checkout page typically have WooCommerce session cookies.
        // Cross-origin or stateless clients may not have WC()->session.
        if (WC()->session) {
            WC()->session->set('customer_lat', $lat);
            WC()->session->set('customer_lng', $lng);
            WC()->session->set('fxw_distance_data', $distance_data);
        }

        // Store-formatted fee (auto currency, decimals, symbol position)
        $fee_formatted = '';
        if (function_exists('wc_price')) {
            $fee_formatted = wp_strip_all_tags(wc_price($cost));
        }

        return rest_ensure_response(array(
            'status' => 'success',
            'in_zone' => true,
            'distance_km' => round($distance_in_km, 2),
            'fee' => round($cost, 2),
            'fee_formatted' => $fee_formatted,
            'duration_text' => (isset($distance_data['duration']) && is_object($distance_data['duration']) && isset($distance_data['duration']->text)) ? $distance_data['duration']->text : ''
        ));
    }

    /**
     * Get arguments for the validate location endpoint.
     *
     * @return array
     */
    public function get_validate_location_args()
    {
        return array(
            'lat' => array(
                'required' => true,
                'type' => 'number',
                'description' => __('Latitude coordinate (-90 to 90).', 'foodxpress'),
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param) && abs((float) $param) <= 90;
                },
                'sanitize_callback' => 'floatval',
            ),
            'lng' => array(
                'required' => true,
                'type' => 'number',
                'description' => __('Longitude coordinate (-180 to 180).', 'foodxpress'),
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param) && abs((float) $param) <= 180;
                },
                'sanitize_callback' => 'floatval',
            ),
        );
    }
}
