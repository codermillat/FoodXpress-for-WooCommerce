<?php
/**
 * Checkout — Server-side Handler
 *
 * Owns all server-side checkout concerns: customer location updates,
 * delivery zone validation, address completeness checks, fee calculation,
 * shipping label enrichment, and order meta persistence. Split out of
 * FXW_Checkout in 1.2.0 to keep the orchestrator file lean.
 *
 * @since      1.2.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/services/class-fxw-delivery-fee.php';
require_once __DIR__ . '/services/class-fxw-address-validator.php';

/**
 * Class FXW_Checkout_Handler
 *
 * Backend concerns:
 *  - AJAX: update customer location (session + WC_Customer persistence)
 *  - Hook: woocommerce_after_checkout_validation — delivery zone validation
 *  - Hook: woocommerce_checkout_update_user_meta — save delivery profile to user meta
 *  - Hook: woocommerce_checkout_create_order — persist delivery details as order meta
 *
 * Cart-fee and shipping-label hooks live on FXW_Delivery_Fee (extracted
 * in 1.2.0). Address-completeness heuristic lives on FXW_Address_Validator
 * (also extracted in 1.2.0).
 *
 * Each instance registers its own hooks in the constructor; no external
 * wiring required beyond `require_once`-ing this file.
 *
 * @since 1.2.0
 */
class FXW_Checkout_Handler
{

    /**
     * Register AJAX + WooCommerce checkout hooks.
     *
     * @since 1.2.0
     */
    public function __construct()
    {
        add_action('wp_ajax_fxw_update_customer_location', array($this, 'update_customer_location'));
        add_action('wp_ajax_nopriv_fxw_update_customer_location', array($this, 'update_customer_location'));
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_delivery_zone'), 20, 2);
        add_action('woocommerce_checkout_update_user_meta', array($this, 'save_customer_address'), 10, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'save_delivery_details_to_order'), 10, 2);
    }

    /**
     * Update customer location via AJAX.
     *
     * @since    1.0.0
     */
    public function update_customer_location()
    {
        $rate_limit_check = FXW_Rate_Limiter::check_rate_limit('update_customer_location', 30);
        if (is_wp_error($rate_limit_check)) {
            wp_send_json_error(array('message' => $rate_limit_check->get_error_message()), 429);
            return;
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'fxw-checkout-nonce')) {
            wp_send_json_error(array('message' => __('Session has expired. Please reload the page.', 'foodxpress')), 403);
            return;
        }

        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

        // Parse and sanitize address array with defaults to prevent notices
        $address_defaults = array(
            'country' => '',
            'state' => '',
            'postcode' => '',
            'city' => '',
            'address_1' => '',
            'address_2' => '',
        );
        $raw_address = isset($_POST['address'])
            ? wp_parse_args((array) wc_clean(wp_unslash($_POST['address'])), $address_defaults)
            : $address_defaults;

        if (!$lat || !$lng) {
            wp_send_json_error('Invalid location data.');
            return;
        }

        if (!WC()->customer) {
            wp_send_json_error(array('message' => __('Customer session not available.', 'foodxpress')));
            return;
        }

        WC()->customer->set_shipping_location($raw_address['country'], $raw_address['state'], $raw_address['postcode'], $raw_address['city']);
        WC()->customer->set_shipping_address_1($raw_address['address_1']);
        WC()->customer->set_shipping_address_2($raw_address['address_2']);

        // Also set billing to keep destination in sync if store forces shipping to billing
        WC()->customer->set_billing_country($raw_address['country']);
        WC()->customer->set_billing_state($raw_address['state']);
        WC()->customer->set_billing_postcode($raw_address['postcode']);
        WC()->customer->set_billing_city($raw_address['city']);
        WC()->customer->set_billing_address_1($raw_address['address_1']);
        WC()->customer->set_billing_address_2($raw_address['address_2']);

        if (method_exists(WC()->customer, 'save')) {
            WC()->customer->save();
        }

        WC()->session->set('customer_lat', $lat);
        WC()->session->set('customer_lng', $lng);
        WC()->session->set('fxw_coords_locked', true);

        // Ensure WC session cookie exists for guests and log state
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug('fxw_update_customer_location: ensuring session cookie + verifying session values', array('source' => 'foodxpress'));
        }
        if (WC()->session && method_exists(WC()->session, 'set_customer_session_cookie')) {
            WC()->session->set_customer_session_cookie(true);
        }

        // Log update for debugging
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(sprintf('fxw_update_customer_location lat=%s lng=%s city=%s state=%s country=%s', $lat, $lng, $raw_address['city'], $raw_address['state'], $raw_address['country']), array('source' => 'foodxpress'));
            $lat_check = WC()->session ? WC()->session->get('customer_lat') : null;
            $lng_check = WC()->session ? WC()->session->get('customer_lng') : null;
            wc_get_logger()->debug(sprintf('fxw_update_customer_location: session_after_set lat=%s lng=%s logged_in=%s', $lat_check, $lng_check, is_user_logged_in() ? 'yes' : 'no'), array('source' => 'foodxpress'));
        }

        // Trigger cart recalculation
        WC()->cart->calculate_totals();

        wp_send_json_success(array('lat' => $lat, 'lng' => $lng));
    }

    /**
     * Save the delivery address to user meta when the customer ticked
     * "Save this address for future orders" at checkout.
     *
     * Until 1.2.2 this never ran: the gate required POST keys that no
     * form ever submitted, so _fxw_delivery_profile was never written
     * and the returning-customer pre-fill was dead.
     *
     * @param int   $customer_id The customer ID.
     * @param array $data        The posted data.
     * @since 1.0.0
     */
    public function save_customer_address($customer_id, $data)
    {
        $save_requested = isset($_POST['fxw_save_address']) ? absint(wp_unslash($_POST['fxw_save_address'])) : 0;
        if (!$save_requested) {
            return;
        }

        $address_details = isset($_POST['fxw_address_details']) ? sanitize_text_field(wp_unslash($_POST['fxw_address_details'])) : '';
        $landmark = isset($_POST['fxw_landmark']) ? sanitize_text_field(wp_unslash($_POST['fxw_landmark'])) : '';
        $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;

        $profile = array(
            'address_1' => isset($data['shipping_address_1']) ? $data['shipping_address_1'] : '',
            'address_2' => $address_details,
            'city' => isset($data['shipping_city']) ? $data['shipping_city'] : '',
            'state' => isset($data['shipping_state']) ? $data['shipping_state'] : '',
            'postcode' => isset($data['shipping_postcode']) ? $data['shipping_postcode'] : '',
            'country' => isset($data['shipping_country']) ? $data['shipping_country'] : '',
            'lat' => WC()->session ? WC()->session->get('customer_lat') : null,
            'lng' => WC()->session ? WC()->session->get('customer_lng') : null,
            'address_details' => $address_details,
            'landmark' => $landmark,
            'distance_data' => $distance_data,
        );
        update_user_meta($customer_id, '_fxw_delivery_profile', $profile);
    }

    /**
     * Save delivery details to order meta during checkout.
     *
     * @param WC_Order $order
     * @param array    $data
     * @since 1.0.0
     */
    public function save_delivery_details_to_order($order, $data)
    {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // Get the exact delivery address (single field + landmark)
        $address_details = isset($_POST['fxw_address_details']) ? trim(sanitize_text_field(wp_unslash($_POST['fxw_address_details']))) : '';
        $landmark = isset($_POST['fxw_landmark']) ? trim(sanitize_text_field(wp_unslash($_POST['fxw_landmark']))) : '';

        if (!empty($address_details)) {
            $order->update_meta_data('_fxw_address_details', $address_details);
        }
        if (!empty($landmark)) {
            $order->update_meta_data('_fxw_landmark', $landmark);
        }

        $delivery_instructions = isset($_POST['fxw_delivery_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['fxw_delivery_instructions'])) : '';
        if (!empty($delivery_instructions)) {
            $order->update_meta_data('_fxw_delivery_instructions', $delivery_instructions);
        }

        // Get coordinates from session (guard against null session in REST/CLI contexts)
        $lat = WC()->session ? WC()->session->get('customer_lat') : null;
        $lng = WC()->session ? WC()->session->get('customer_lng') : null;

        // Get distance data from session
        $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;
        $distance_km = 0;
        if ($distance_data && isset($distance_data['distance']) && is_object($distance_data['distance']) && isset($distance_data['distance']->value)) {
            $distance_km = round($distance_data['distance']->value / 1000, 2);
        }

        // Full delivery address for display: exact-address field + landmark
        $full_delivery_address = $address_details;
        if (!empty($landmark)) {
            $full_delivery_address .= sprintf(' (%s: %s)', __('Landmark', 'foodxpress'), $landmark);
        }
        if (!empty($full_delivery_address)) {
            $order->update_meta_data('_fxw_delivery_address', $full_delivery_address);

            // Also store as the address second line so receipts/emails using
            // get_formatted_shipping_address() show the exact flat/house info.
            $order->set_address_2($full_delivery_address);
            $order->set_shipping_address_2($full_delivery_address);
        }

        // Fallback to POST data if session is empty to prevent data loss.
        // Validate as numeric coordinates to prevent manipulation (WPCS: sanitize input).
        if (!$lat && isset($_POST['fxw_lat'])) {
            $raw_lat = is_scalar($_POST['fxw_lat']) ? sanitize_text_field(wp_unslash($_POST['fxw_lat'])) : '';
            if (is_numeric($raw_lat)) {
                $post_lat = (float) $raw_lat;
                if (abs($post_lat) <= 90) {
                    $lat = $post_lat;
                }
            }
        }
        if (!$lng && isset($_POST['fxw_lng'])) {
            $raw_lng = is_scalar($_POST['fxw_lng']) ? sanitize_text_field(wp_unslash($_POST['fxw_lng'])) : '';
            if (is_numeric($raw_lng)) {
                $post_lng = (float) $raw_lng;
                if (abs($post_lng) <= 180) {
                    $lng = $post_lng;
                }
            }
        }

        if ($lat && $lng && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            $order->update_meta_data('_fxw_delivery_lat', (float) $lat);
            $order->update_meta_data('_fxw_delivery_lng', (float) $lng);
        }

        if ($distance_km > 0) {
            $order->update_meta_data('_fxw_delivery_distance', $distance_km);
        }

        // Log the saved data for debugging
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(sprintf(
                'save_delivery_details_to_order: order_id=%d, details=%s, lat=%s, lng=%s, distance=%s km',
                $order->get_id(),
                $address_details,
                $lat,
                $lng,
                $distance_km
            ), array('source' => 'foodxpress'));
        }
    }

    /**
     * Validate that the customer's address is within the delivery zone.
     *
     * @param   array      $data      The checkout data.
     * @param   WP_Error   $errors    The errors object.
     * @since   1.0.0
     */
    public function validate_delivery_zone($data, $errors)
    {
        $options = get_option('fxw_settings');
        $radius = isset($options['fxw_delivery_zone_radius']) ? (float) $options['fxw_delivery_zone_radius'] : FXW_Config::DEFAULT_DELIVERY_RADIUS;
        $is_open_raw = isset($options['fxw_is_open']) ? $options['fxw_is_open'] : 'yes';
        $is_open = in_array($is_open_raw, array('yes', 'true', 1, '1', true), true);

        // Validate the single exact-address field (separate from map location)
        $address_details = isset($_POST['fxw_address_details']) ? trim(sanitize_text_field(wp_unslash($_POST['fxw_address_details']))) : '';

        if (mb_strlen($address_details) < 5) {
            $errors->add('address_details', __('Please enter your exact address (house / flat / building no.) so our delivery agent can find you.', 'foodxpress'));
            return;
        }

        // Enhanced coordinate validation (guard against null session)
        $customer_lat = WC()->session ? WC()->session->get('customer_lat') : null;
        $customer_lng = WC()->session ? WC()->session->get('customer_lng') : null;

        // Ensure coordinates are present and valid
        if (!$customer_lat || !$customer_lng || !is_numeric($customer_lat) || !is_numeric($customer_lng)) {
            $errors->add('delivery_zone', __('Please select your exact location on the map. This ensures accurate delivery and helps our delivery agent find you.', 'foodxpress'));
            return;
        }

        // Validate coordinate precision (must be reasonable GPS coordinates)
        if (abs($customer_lat) > 90 || abs($customer_lng) > 180) {
            $errors->add('delivery_zone', __('Invalid location coordinates detected. Please select your location again using the map.', 'foodxpress'));
            return;
        }

        // Check if restaurant address is configured
        $restaurant_address = isset($options['fxw_restaurant_address']) ? trim($options['fxw_restaurant_address']) : '';
        if (empty($restaurant_address)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('validate_delivery_zone: restaurant address not configured', array('source' => 'foodxpress'));
            }
            $errors->add('delivery_zone', __('Delivery service is not properly configured. Please contact support.', 'foodxpress'));
            return;
        }
        if (!$is_open) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info('validate_delivery_zone: store closed (fxw_is_open=false)', array('source' => 'foodxpress'));
            }
            $errors->add('delivery_zone', __('We are currently closed for deliveries. Please try again later.', 'foodxpress'));
            return;
        }

        // Re-read from session in case early validation updated them
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(sprintf('validate_delivery_zone session lat=%s lng=%s', $customer_lat, $customer_lng), array('source' => 'foodxpress'));
        }

        if (!$customer_lat || !$customer_lng) {
            // Fallback: try to geocode current shipping address when session coords are missing
            // Apply stricter rate limiting for automated fallback operations (5 per minute)
            $rate_limit_check = FXW_Rate_Limiter::check_rate_limit('fallback_geocode', 5, MINUTE_IN_SECONDS);
            if (is_wp_error($rate_limit_check)) {
                if (function_exists('wc_get_logger')) {
                    wc_get_logger()->warning('validate_delivery_zone: fallback geocode rate limit exceeded', array('source' => 'foodxpress'));
                }
                $errors->add('delivery_zone', __('Too many address verification attempts. Please use the map to select your exact location instead of relying on automatic address lookup.', 'foodxpress'));
                return;
            }

            if (function_exists('wc_get_logger')) {
                wc_get_logger()->warning('validate_delivery_zone: missing coords, attempting fallback geocode from shipping address', array('source' => 'foodxpress'));
            }

            $addr1 = WC()->customer ? WC()->customer->get_shipping_address_1() : '';
            $addr2 = WC()->customer ? WC()->customer->get_shipping_address_2() : '';
            $city = WC()->customer ? WC()->customer->get_shipping_city() : '';
            $state = WC()->customer ? WC()->customer->get_shipping_state() : '';
            $postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
            $country = WC()->customer ? WC()->customer->get_shipping_country() : '';

            $parts = array_filter(array($addr1, $addr2, $city, $state, $postcode, $country));
            $full_address = trim(implode(', ', $parts));

            if ($full_address) {
                $mapping_service = new FXW_Mapping_Service();
                $coords = $mapping_service->get_coords($full_address);

                if (is_wp_error($coords)) {
                    if (function_exists('wc_get_logger')) {
                        wc_get_logger()->error('validate_delivery_zone: fallback geocode failed - ' . $coords->get_error_message(), array('source' => 'foodxpress'));
                    }
                    $errors->add('delivery_zone', __('We could not verify your address automatically. Please use the interactive map above to pinpoint your exact delivery location.', 'foodxpress'));
                    return;
                }

                $lat_val = is_array($coords) ? ($coords['lat'] ?? null) : ((is_object($coords) && isset($coords->lat)) ? $coords->lat : null);
                $lng_val = is_array($coords) ? ($coords['lng'] ?? null) : ((is_object($coords) && isset($coords->lng)) ? $coords->lng : null);

                if ($lat_val && $lng_val) {
                    WC()->session->set('customer_lat', $lat_val);
                    WC()->session->set('customer_lng', $lng_val);
                    $customer_lat = $lat_val;
                    $customer_lng = $lng_val;

                    if (function_exists('wc_get_logger')) {
                        wc_get_logger()->debug(sprintf('validate_delivery_zone: fallback geocode success lat=%s lng=%s from "%s"', $lat_val, $lng_val, $full_address), array('source' => 'foodxpress'));
                    }
                } else {
                    if (function_exists('wc_get_logger')) {
                        wc_get_logger()->error('validate_delivery_zone: fallback geocode returned invalid coords', array('source' => 'foodxpress'));
                    }
                    $errors->add('delivery_zone', __('We could not determine your coordinates from the address provided. Please use the map to select your location.', 'foodxpress'));
                    return;
                }
            } else {
                if (function_exists('wc_get_logger')) {
                    wc_get_logger()->warning('validate_delivery_zone: no shipping address to geocode', array('source' => 'foodxpress'));
                }
                $errors->add('delivery_zone', __('Please select your location on the map to enable accurate delivery.', 'foodxpress'));
                return;
            }
        }

        $customer_location = array('lat' => $customer_lat, 'lng' => $customer_lng);

        $mapping_service = new FXW_Mapping_Service();
        $distance_data = $mapping_service->get_distance(
            $options['fxw_restaurant_address'],
            $customer_location
        );

        if (is_wp_error($distance_data)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('validate_delivery_zone distance error: ' . $distance_data->get_error_message(), array('source' => 'foodxpress'));
            }
            $errors->add('delivery_zone', $distance_data->get_error_message());
            return;
        }

        if (!isset($distance_data['distance']) || !is_object($distance_data['distance']) || !isset($distance_data['distance']->value)) {
            $errors->add('delivery_zone', __('Could not calculate delivery distance. Please try again.', 'foodxpress'));
            return;
        }

        $distance_in_km = $distance_data['distance']->value / 1000;

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(sprintf('validate_delivery_zone: distance=%.3f km radius=%.3f', $distance_in_km, $radius), array('source' => 'foodxpress'));
        }

        if ($distance_in_km > $radius) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info('validate_delivery_zone: out of zone', array('source' => 'foodxpress'));
            }
            $errors->add('delivery_zone', __('Sorry, we do not deliver to your location.', 'foodxpress'));
            return;
        }

        // Store the validated distance data for order processing
        WC()->session->set('fxw_distance_data', $distance_data);

        // Log successful validation with delivery details
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(sprintf(
                'validate_delivery_zone: success - distance=%.3f km, details=%s',
                $distance_in_km,
                $address_details
            ), array('source' => 'foodxpress'));
        }
    }

    /**
     * NOTE: `add_delivery_fee()` and `append_eta_to_label()` were extracted
     * to `FXW_Delivery_Fee` in 1.2.0. They now self-register via the
     * `woocommerce_cart_calculate_fees` and
     * `woocommerce_cart_shipping_method_full_label` hooks when that class
     * is loaded.
     *
     * Address-completeness heuristic was extracted to `FXW_Address_Validator`
     * in 1.2.0 as a static method. It was previously dead code (private,
     * never called) but is preserved as a public service for future use by
     * the multi-outlet editor (Phase 7) and any address-validating surface.
     */
}

new FXW_Checkout_Handler();
