<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Manages the checkout process.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Checkout
{

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('wp_loaded', array($this, 'load_saved_address'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_delivery_zone'), 20, 2);
        add_action('woocommerce_before_checkout_billing_form', array($this, 'add_checkout_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_fxw_get_restaurant_location', array($this, 'get_restaurant_location'));
        add_action('wp_ajax_nopriv_fxw_get_restaurant_location', array($this, 'get_restaurant_location'));
        add_action('wp_ajax_fxw_update_customer_location', array($this, 'update_customer_location'));
        add_action('wp_ajax_nopriv_fxw_update_customer_location', array($this, 'update_customer_location'));
        add_action('wp_ajax_fxw_debug_status', array($this, 'debug_status'));
        add_action('woocommerce_checkout_update_user_meta', array($this, 'save_customer_address'), 10, 2);
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_delivery_fee'));
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'append_eta_to_label'), 10, 2);
        add_action('woocommerce_checkout_create_order', array($this, 'save_delivery_details_to_order'), 10, 2);

        // Register script loader filter once for Google Maps defer attribute
        add_filter('script_loader_tag', array($this, 'add_async_defer_to_maps_script'), 10, 2);
    }

    /**
     * Get restaurant location via AJAX for map centering and zone drawing.
     *
     * @since    1.0.0
     */
    public function get_restaurant_location()
    {
        $rate_limit_check = FXW_Rate_Limiter::check_rate_limit('get_restaurant_location', 10);
        if (is_wp_error($rate_limit_check)) {
            wp_send_json_error(array('message' => $rate_limit_check->get_error_message()), 429);
            return;
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'fxw-checkout-nonce')) {
            wp_send_json_error(array('message' => __('Session has expired. Please reload the page.', 'foodxpress')), 403);
            return;
        }

        $options = get_option('fxw_settings');
        $restaurant_address = isset($options['fxw_restaurant_address']) ? trim($options['fxw_restaurant_address']) : '';
        if (empty($restaurant_address)) {
            wp_send_json_error(array('message' => __('Restaurant address not configured.', 'foodxpress')), 400);
            return;
        }

        $mapping = new FXW_Mapping_Service();
        $coords = $mapping->get_coords($restaurant_address);
        if (is_wp_error($coords)) {
            wp_send_json_error(array('message' => $coords->get_error_message()), 500);
            return;
        }

        $lat = is_object($coords) ? ($coords->lat ?? null) : (is_array($coords) ? ($coords['lat'] ?? null) : null);
        $lng = is_object($coords) ? ($coords->lng ?? null) : (is_array($coords) ? ($coords['lng'] ?? null) : null);

        if ($lat && $lng) {
            wp_send_json_success(array('lat' => (float) $lat, 'lng' => (float) $lng));
        } else {
            wp_send_json_error(array('message' => __('Could not determine restaurant coordinates from the provided address.', 'foodxpress')), 500);
        }
    }

    /**
     * Enqueue the scripts for the checkout page.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        // Get the Google Maps API key first
        $options = get_option('fxw_settings');
        $api_key = isset($options['fxw_google_maps_api_key']) ? trim($options['fxw_google_maps_api_key']) : '';

        // Bail early if no API key - don't load any map-related scripts
        if (empty($api_key)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->warning('Google Maps API key missing - map functionality disabled', array('source' => 'foodxpress'));
            }
            return;
        }

        // Register the checkout script (will be enqueued below)
        wp_register_script('fxw-checkout', plugin_dir_url(__FILE__) . '../assets/js/checkout.js', array('jquery'), FXW_VERSION, true);

        // Enqueue frontend CSS for map styling
        wp_enqueue_style('fxw-frontend', FXW_PLUGIN_URL . 'assets/css/frontend.css', array(), FXW_VERSION);

        // Build Google Maps URL with async loading and proper callback
        $maps_url = add_query_arg(array(
            'key' => $api_key,
            'v' => 'weekly',
            'libraries' => 'places,marker,geocoding',
            'callback' => 'fxwInitMap',
            'loading' => 'async'
        ), 'https://maps.googleapis.com/maps/api/js');

        // Register Google Maps script
        wp_register_script('fxw-google-maps', $maps_url, array(), null, true);

        // Add inline stub BEFORE Google Maps loads to guarantee callback exists
        $inline_stub = '
		window.fxwInitMap = window.fxwInitMap || function() {
			if (typeof window.fxwInternalInit === "function") {
				try {
					window.fxwInternalInit();
				} catch(e) {
					// Silent failure in production
				}
			} else {
				var retries = 0;
				var waitForInternal = function() {
					if (typeof window.fxwInternalInit === "function") {
						try {
							window.fxwInternalInit();
						} catch(e) {
							// Silent failure in production
						}
					} else if (retries < 50) {
						retries++;
						setTimeout(waitForInternal, 100);
					}
				};
				setTimeout(waitForInternal, 100);
			}
		};';

        wp_add_inline_script('fxw-google-maps', $inline_stub, 'before');

        // Enqueue scripts in proper order
        wp_enqueue_script('fxw-checkout');
        wp_enqueue_script('fxw-google-maps');

        // Localize script with minimal required data (security: avoid exposing full options)
        $saved_address = is_user_logged_in() ? get_user_meta(get_current_user_id(), '_fxw_delivery_profile', true) : null;
        wp_localize_script('fxw-checkout', 'fxw_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fxw-checkout-nonce'),
            'rest_url' => esc_url_raw(rest_url('foodxpress/v1/checkout')),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '৳',
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'prep_time' => isset($options['fxw_preparation_time']) ? (int) $options['fxw_preparation_time'] : FXW_Config::DEFAULT_PREP_TIME,
            'saved_address' => $saved_address,
            'max_retries' => FXW_Config::MAX_RETRIES,
            'retry_delay' => FXW_Config::RETRY_DELAY,
            'translations' => array(
                'calculating' => __('Calculating delivery fee...', 'foodxpress'),
                'out_of_zone' => __('Sorry, we do not deliver to this location.', 'foodxpress'),
                'store_closed' => __('Sorry, we are currently closed for deliveries.', 'foodxpress'),
                'error_generic' => __('An error occurred. Please try again.', 'foodxpress'),
                'geolocation_unsupported' => __('Geolocation is not supported by your browser.', 'foodxpress'),
                'locating' => __('Locating…', 'foodxpress'),
                'location_denied' => __('Location permission denied. Please allow access in your browser settings.', 'foodxpress'),
                'location_unavailable' => __('Location unavailable. Please try again.', 'foodxpress'),
                'location_timeout' => __('Location request timed out. Please try again.', 'foodxpress'),
            )
        ));
    }

    /**
     * Add async and defer attributes to Google Maps script for optimal loading.
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @return string Modified script tag.
     */
    public function add_async_defer_to_maps_script($tag, $handle)
    {
        if ('fxw-google-maps' === $handle) {
            // Add defer for non-blocking execution while maintaining order
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        return $tag;
    }

    /**
     * Debug status endpoint to surface FXW settings, shipping zones, and session state.
     *
     * @since 1.0.0
     */
    public function debug_status()
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_POST['security'] ?? ''));
        if (!wp_verify_nonce($nonce, 'fxw-checkout-nonce')) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->warning('ajax invalid_nonce: debug_status', array('source' => 'foodxpress'));
            }
            wp_send_json_error(array('code' => 'invalid_nonce'), 403);
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('code' => 'forbidden'), 403);
        }

        $options = get_option('fxw_settings');
        $masked_options = is_array($options) ? $options : array();
        if (isset($masked_options['fxw_google_maps_api_key'])) {
            $key = (string) $masked_options['fxw_google_maps_api_key'];
            $len = strlen($key);
            if ($len > 8) {
                $masked_options['fxw_google_maps_api_key'] = substr($key, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($key, -4);
            } else {
                $masked_options['fxw_google_maps_api_key'] = str_repeat('*', $len);
            }
        }

        $zones_data = array();
        if (class_exists('WC_Shipping_Zones')) {
            $zones = WC_Shipping_Zones::get_zones();
            foreach ($zones as $zone) {
                $zone_obj = new WC_Shipping_Zone($zone['zone_id']);
                $methods = array();
                foreach ($zone_obj->get_shipping_methods() as $m) {
                    $methods[] = array(
                        'id' => $m->id,
                        'instance_id' => $m->instance_id,
                        'enabled' => ($m->enabled === 'yes' || $m->enabled === true),
                        'title' => method_exists($m, 'get_title') ? $m->get_title() : '',
                    );
                }
                $zones_data[] = array(
                    'zone_id' => $zone['zone_id'],
                    'zone_name' => $zone['zone_name'],
                    'methods' => $methods,
                );
            }
            $zone0 = new WC_Shipping_Zone(0);
            $methods0 = array();
            foreach ($zone0->get_shipping_methods() as $m) {
                $methods0[] = array(
                    'id' => $m->id,
                    'instance_id' => $m->instance_id,
                    'enabled' => ($m->enabled === 'yes' || $m->enabled === true),
                    'title' => method_exists($m, 'get_title') ? $m->get_title() : '',
                );
            }
            $zones_data[] = array(
                'zone_id' => 0,
                'zone_name' => $zone0->get_zone_name(),
                'methods' => $methods0,
            );
        }

        $session = array(
            'customer_lat' => WC()->session ? WC()->session->get('customer_lat') : null,
            'customer_lng' => WC()->session ? WC()->session->get('customer_lng') : null,
            'chosen_shipping_methods' => WC()->session ? WC()->session->get('chosen_shipping_methods') : null,
            'fxw_coords_locked' => WC()->session ? WC()->session->get('fxw_coords_locked') : null,
        );

        $customer = array(
            'shipping_country' => WC()->customer ? WC()->customer->get_shipping_country() : '',
            'shipping_state' => WC()->customer ? WC()->customer->get_shipping_state() : '',
            'shipping_postcode' => WC()->customer ? WC()->customer->get_shipping_postcode() : '',
            'shipping_city' => WC()->customer ? WC()->customer->get_shipping_city() : '',
        );

        wp_send_json_success(array(
            'fxw_settings' => $masked_options,
            'zones' => $zones_data,
            'session' => $session,
            'customer' => $customer,
        ));
    }

    /**
     * Append ETA minutes to our shipping method label.
     *
     * @param string           $label
     * @param WC_Shipping_Rate $rate
     * @return string
     */
    public function append_eta_to_label($label, $rate)
    {
        // Identify our shipping method
        $method_id = '';
        if (is_object($rate)) {
            if (isset($rate->method_id)) {
                $method_id = $rate->method_id;
            } elseif (method_exists($rate, 'get_method_id')) {
                $method_id = $rate->get_method_id();
            } elseif (isset($rate->id) && is_string($rate->id)) {
                $method_id = strpos($rate->id, ':') !== false ? substr($rate->id, 0, strpos($rate->id, ':')) : $rate->id;
            }
        }
        if ($method_id !== 'foodxpress_delivery') {
            return $label;
        }

        $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;
        if (!$distance_data || !isset($distance_data['duration']) || !is_object($distance_data['duration']) || !isset($distance_data['duration']->value)) {
            return $label;
        }

        $options = get_option('fxw_settings');
        $prep_time = isset($options['fxw_preparation_time']) ? (int) $options['fxw_preparation_time'] : FXW_Config::DEFAULT_PREP_TIME;
        $seconds = (int) $distance_data['duration']->value + ($prep_time * 60);
        $mins = max(1, (int) round($seconds / 60));

        $eta_html = sprintf(' <small class="fxw-eta-label">%s</small>', esc_html(sprintf(__('ETA ~ %d mins', 'foodxpress'), $mins)));
        return $label . $eta_html;
    }

    /**
     * Add the delivery fee to the cart.
     *
     * @param   WC_Cart    $cart    The cart object.
     * @since   1.0.0
     */
    public function add_delivery_fee($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $options = get_option('fxw_settings');
        $enable_extra_fee = isset($options['fxw_enable_extra_delivery_fee']) ? in_array($options['fxw_enable_extra_delivery_fee'], array('yes', 'true', 1, '1'), true) : false;
        if (!$enable_extra_fee) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->debug('add_delivery_fee: disabled via settings', array('source' => 'foodxpress'));
            }
            return;
        }

        // Avoid double-charging: if our shipping method is chosen, skip adding a separate fee
        $chosen = WC()->session ? WC()->session->get('chosen_shipping_methods') : array();
        if (is_array($chosen)) {
            foreach ($chosen as $method_id) {
                if (is_string($method_id) && strpos($method_id, 'foodxpress_delivery') === 0) {
                    if (function_exists('wc_get_logger')) {
                        wc_get_logger()->debug('add_delivery_fee: skipping fee because FoodXpress shipping is chosen (' . $method_id . ')', array('source' => 'foodxpress'));
                    }
                    return;
                }
            }
        }

        $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;

        if (!$distance_data) {
            return;
        }

        $base_fee = isset($options['fxw_delivery_fee_base']) ? (float) $options['fxw_delivery_fee_base'] : 5;
        $fee_per_km = isset($options['fxw_delivery_fee_per_km']) ? (float) $options['fxw_delivery_fee_per_km'] : 1.5;

        if (!isset($distance_data['distance']) || !is_object($distance_data['distance']) || !isset($distance_data['distance']->value)) {
            return;
        }

        $distance_in_km = $distance_data['distance']->value / 1000;
        $delivery_fee = $base_fee + ($distance_in_km * $fee_per_km);

        $cart->add_fee(__('Delivery Fee', 'foodxpress'), $delivery_fee);
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
     * Save customer address to user meta.
     *
     * @param int   $customer_id The customer ID.
     * @param array $data        The posted data.
     * @since 1.0.0
     */
    public function save_customer_address($customer_id, $data)
    {
        if (!empty(sanitize_text_field(wp_unslash($_POST['fxw_location-search-input'] ?? ''))) || !empty($_POST['fxw_delivery_address'])) {
            $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;
            $delivery_address = isset($_POST['fxw_delivery_address']) ? sanitize_textarea_field(wp_unslash($_POST['fxw_delivery_address'])) : '';

            $profile = array(
                'address_1' => $data['shipping_address_1'],
                'address_2' => $data['shipping_address_2'],
                'city' => $data['shipping_city'],
                'state' => $data['shipping_state'],
                'postcode' => $data['shipping_postcode'],
                'country' => $data['shipping_country'],
                'lat' => WC()->session ? WC()->session->get('customer_lat') : null,
                'lng' => WC()->session ? WC()->session->get('customer_lng') : null,
                'delivery_address' => $delivery_address,
                'distance_data' => $distance_data,
            );
            update_user_meta($customer_id, '_fxw_delivery_profile', $profile);
        }
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

        // Get the structured delivery details from POST data
        $detail_fields = array(
            'fxw_house_flat_no'       => '_fxw_house_flat_no',
            'fxw_floor_no'            => '_fxw_floor_no',
            'fxw_society_building'    => '_fxw_society_building',
            'fxw_block_tower_area'    => '_fxw_block_tower_area',
            'fxw_landmark'            => '_fxw_landmark',
        );

        $detail_values = array();
        foreach ($detail_fields as $post_key => $meta_key) {
            $value = isset($_POST[$post_key]) ? sanitize_text_field(wp_unslash($_POST[$post_key])) : '';
            if (!empty($value)) {
                $order->update_meta_data($meta_key, $value);
                $detail_values[] = $value;
            }
        }

        $delivery_instructions = isset($_POST['fxw_delivery_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['fxw_delivery_instructions'])) : '';
        if (!empty($delivery_instructions)) {
            $order->update_meta_data('_fxw_delivery_instructions', $delivery_instructions);
        }

        // Shorthand references for logging
        $house_flat_no = isset($_POST['fxw_house_flat_no']) ? sanitize_text_field(wp_unslash($_POST['fxw_house_flat_no'])) : '';
        $society_building = isset($_POST['fxw_society_building']) ? sanitize_text_field(wp_unslash($_POST['fxw_society_building'])) : '';

        // Get coordinates from session (guard against null session in REST/CLI contexts)
        $lat = WC()->session ? WC()->session->get('customer_lat') : null;
        $lng = WC()->session ? WC()->session->get('customer_lng') : null;

        // Get distance data from session
        $distance_data = WC()->session ? WC()->session->get('fxw_distance_data') : null;
        $distance_km = 0;
        if ($distance_data && isset($distance_data['distance']) && is_object($distance_data['distance']) && isset($distance_data['distance']->value)) {
            $distance_km = round($distance_data['distance']->value / 1000, 2);
        }

        // Build full delivery address for display purposes
        $full_delivery_address = implode(', ', $detail_values);
        if (!empty($full_delivery_address)) {
            $order->update_meta_data('_fxw_delivery_address', $full_delivery_address);
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
                'save_delivery_details_to_order: order_id=%d, house=%s, society=%s, lat=%s, lng=%s, distance=%s km',
                $order->get_id(),
                $house_flat_no,
                $society_building,
                $lat,
                $lng,
                $distance_km
            ), array('source' => 'foodxpress'));
        }
    }

    /**
     * Add custom fields to the checkout page.
     *
     * @since    1.0.0
     */
    public function add_checkout_fields()
    {
        // Get saved delivery details for logged-in users
        $saved_profile = array();
        if (is_user_logged_in()) {
            $saved_profile = get_user_meta(get_current_user_id(), '_fxw_delivery_profile', true);
            if (!is_array($saved_profile)) {
                $saved_profile = array();
            }
        }
        ?>
        <div id="fxw-location-picker-container">
            <h3><?php esc_html_e('Step 1: Select Your Location on Map', 'foodxpress'); ?></h3>
            <p><?php esc_html_e('Search for your area, use current location, or drag the marker to set your exact delivery point.', 'foodxpress'); ?>
            </p>

            <div class="fxw-location-search-wrapper">
                <input id="fxw-location-search-input" type="text"
                    placeholder="<?php esc_attr_e('Search for your area or landmark...', 'foodxpress'); ?>" class="input-text"
                    value="" />
                <a href="#" id="fxw-get-location" class="button"><?php esc_html_e('Use My Location', 'foodxpress'); ?></a>
            </div>

            <div id="fxw-map" style="height: 300px; margin: 20px 0;"></div>

            <div id="fxw-geolocation-error" class="woocommerce-error" style="display:none;"></div>

            <!-- Display selected location (read-only, from map) -->
            <div id="fxw-selected-location" class="fxw-selected-location"
                style="display:none; padding: 10px; background: #f8f9fa; border-left: 3px solid #28a745; margin-bottom: 20px;">
                <strong><?php esc_html_e('Selected Location:', 'foodxpress'); ?></strong>
                <span id="fxw-selected-address"></span>
            </div>

            <!-- Hidden fields for POST fallback when session is empty (populated by checkout.js) -->
            <input type="hidden" name="fxw_lat" id="fxw_lat" value="" />
            <input type="hidden" name="fxw_lng" id="fxw_lng" value="" />
        </div>

        <div id="fxw-delivery-details-container" style="margin-top: 20px;">
            <h3><?php esc_html_e('Step 2: Enter Your Delivery Details', 'foodxpress'); ?></h3>
            <p><?php esc_html_e('Please provide complete details to help our delivery agent find you easily.', 'foodxpress'); ?>
            </p>

            <?php
            // House/Flat Number - Required
            woocommerce_form_field('fxw_house_flat_no', array(
                'type' => 'text',
                'class' => array('form-row-first'),
                'label' => __('House / Flat No.', 'foodxpress'),
                'placeholder' => __('e.g., Flat 4B, House 25', 'foodxpress'),
                'required' => true,
            ), isset($saved_profile['house_flat_no']) ? $saved_profile['house_flat_no'] : WC()->checkout->get_value('fxw_house_flat_no'));

            // Floor Number - Optional but helpful
            woocommerce_form_field('fxw_floor_no', array(
                'type' => 'text',
                'class' => array('form-row-last'),
                'label' => __('Floor No.', 'foodxpress'),
                'placeholder' => __('e.g., Ground Floor, 3rd Floor', 'foodxpress'),
                'required' => false,
            ), isset($saved_profile['floor_no']) ? $saved_profile['floor_no'] : WC()->checkout->get_value('fxw_floor_no'));

            // Society/Building Name - Required
            woocommerce_form_field('fxw_society_building', array(
                'type' => 'text',
                'class' => array('form-row-first'),
                'label' => __('Society / Building Name', 'foodxpress'),
                'placeholder' => __('e.g., Green Valley Apartments, Rose Garden Society', 'foodxpress'),
                'required' => true,
            ), isset($saved_profile['society_building']) ? $saved_profile['society_building'] : WC()->checkout->get_value('fxw_society_building'));

            // Block/Tower/Area/Section - Optional
            woocommerce_form_field('fxw_block_tower_area', array(
                'type' => 'text',
                'class' => array('form-row-last'),
                'label' => __('Block / Tower / Area / Section', 'foodxpress'),
                'placeholder' => __('e.g., Tower A, Block 2, Sector 5', 'foodxpress'),
                'required' => false,
            ), isset($saved_profile['block_tower_area']) ? $saved_profile['block_tower_area'] : WC()->checkout->get_value('fxw_block_tower_area'));

            // Landmark - Optional but helpful
            woocommerce_form_field('fxw_landmark', array(
                'type' => 'text',
                'class' => array('form-row-wide'),
                'label' => __('Nearby Landmark', 'foodxpress'),
                'placeholder' => __('e.g., Near City Mall, Opposite Park', 'foodxpress'),
                'required' => false,
            ), isset($saved_profile['landmark']) ? $saved_profile['landmark'] : WC()->checkout->get_value('fxw_landmark'));

            // Delivery Instructions - Optional
            woocommerce_form_field('fxw_delivery_instructions', array(
                'type' => 'textarea',
                'class' => array('form-row-wide'),
                'label' => __('Delivery Instructions', 'foodxpress'),
                'placeholder' => __('e.g., Ring the bell twice, Leave at the door, Call before arriving...', 'foodxpress'),
                'required' => false,
                'custom_attributes' => array(
                    'rows' => 2,
                ),
            ), isset($saved_profile['delivery_instructions']) ? $saved_profile['delivery_instructions'] : WC()->checkout->get_value('fxw_delivery_instructions'));
            ?>

            <?php if (is_user_logged_in()): ?>
                <p class="form-row form-row-wide">
                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                        <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                            name="fxw_save_address" id="fxw_save_address" value="1" />
                        <span><?php esc_html_e('Save this address for future orders', 'foodxpress'); ?></span>
                    </label>
                </p>
            <?php endif; ?>
        </div>
        <?php
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

        // Validate required delivery detail fields (separate from map location)
        $house_flat_no = isset($_POST['fxw_house_flat_no']) ? trim(sanitize_text_field(wp_unslash($_POST['fxw_house_flat_no']))) : '';
        $society_building = isset($_POST['fxw_society_building']) ? trim(sanitize_text_field(wp_unslash($_POST['fxw_society_building']))) : '';

        if (empty($house_flat_no)) {
            $errors->add('house_flat_no', __('Please enter your House / Flat Number. This helps our delivery agent find you.', 'foodxpress'));
            return;
        }

        if (empty($society_building)) {
            $errors->add('society_building', __('Please enter your Society / Building Name. This is essential for delivery.', 'foodxpress'));
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
                'validate_delivery_zone: success - distance=%.3f km, house=%s, society=%s',
                $distance_in_km,
                $house_flat_no,
                $society_building
            ), array('source' => 'foodxpress'));
        }
    }

    /**
     * Validate address completeness for delivery with detailed feedback.
     *
     * @param string $address The delivery address to validate.
     * @return array Array with 'is_complete' boolean and 'message' string.
     * @since 1.0.0
     */
    private function validate_address_completeness($address)
    {
        if (empty($address)) {
            return array(
                'is_complete' => false,
                'message' => __('address field is empty', 'foodxpress')
            );
        }

        if (strlen($address) < 20) {
            return array(
                'is_complete' => false,
                'message' => __('address is too short - please provide more details', 'foodxpress')
            );
        }

        // Check for essential address components
        $address_lower = strtolower($address);
        $has_building_info = false;
        $has_location_info = false;
        $has_numbers = false;

        // Enhanced building/location indicators
        $building_keywords = array('flat', 'apartment', 'apt', 'floor', 'building', 'block', 'house', 'home', 'tower', 'complex', 'society', 'villa', 'bungalow', 'street', 'road', 'lane', 'avenue', 'plaza', 'square', 'mall', 'shop', 'office');
        $location_keywords = array('near', 'opposite', 'behind', 'next to', 'beside', 'landmark', 'gate', 'entrance', 'main', 'sector', 'area', 'locality', 'colony', 'nagar', 'city', 'town', 'metro', 'station', 'market', 'hospital', 'school');

        foreach ($building_keywords as $keyword) {
            if (strpos($address_lower, $keyword) !== false) {
                $has_building_info = true;
                break;
            }
        }

        foreach ($location_keywords as $keyword) {
            if (strpos($address_lower, $keyword) !== false) {
                $has_location_info = true;
                break;
            }
        }

        // Check for numbers (house/flat numbers, postal codes)
        $has_numbers = preg_match('/\d+/', $address);

        // Specific validation checks with helpful messages
        if (!$has_numbers) {
            return array(
                'is_complete' => false,
                'message' => __('please include building/house/flat number', 'foodxpress')
            );
        }

        if (!$has_building_info && !$has_location_info) {
            return array(
                'is_complete' => false,
                'message' => __('please add building name, street name, or nearby landmark', 'foodxpress')
            );
        }

        // Check for delivery instructions or additional details
        $has_delivery_hints = false;
        $delivery_keywords = array('floor', 'gate', 'entrance', 'lift', 'stairs', 'parking', 'security', 'guard', 'bell', 'intercom', 'buzzer', 'call', 'ring', 'door', 'left', 'right', 'behind', 'front');

        foreach ($delivery_keywords as $keyword) {
            if (strpos($address_lower, $keyword) !== false) {
                $has_delivery_hints = true;
                break;
            }
        }

        // All basic checks passed
        if (strlen($address) > 30 && $has_building_info && $has_numbers) {
            return array(
                'is_complete' => true,
                'message' => __('address looks complete', 'foodxpress')
            );
        }

        // Moderate completeness - warn but allow
        if ($has_building_info && $has_numbers) {
            return array(
                'is_complete' => true,
                'message' => __('address is acceptable but could use more detail', 'foodxpress')
            );
        }

        // Fallback - minimal requirements met
        return array(
            'is_complete' => ($has_building_info || $has_location_info) && $has_numbers,
            'message' => __('please add more specific delivery details like floor, gate number, or landmarks', 'foodxpress')
        );
    }


    public function customize_checkout_fields($fields)
    {
        // Hide the default address fields - map + delivery detail fields replace them
        $hidden_suffixes = array('address_1', 'address_2', 'city', 'state', 'postcode', 'country');
        foreach (array('billing', 'shipping') as $group) {
            foreach ($hidden_suffixes as $suffix) {
                $key = $group . '_' . $suffix;
                if (isset($fields[$group][$key])) {
                    $fields[$group][$key]['class'][] = 'fxw-hidden-field';
                }
            }
        }

        return $fields;
    }

    public function load_saved_address()
    {
        if (is_user_logged_in() && is_checkout()) {
            $profile = get_user_meta(get_current_user_id(), '_fxw_delivery_profile', true);
            if (!empty($profile) && !empty($profile['lat']) && !empty($profile['lng']) && WC()->customer) {
                WC()->customer->set_shipping_address_1($profile['address_1']);
                WC()->customer->set_shipping_address_2($profile['address_2']);
                WC()->customer->set_shipping_city($profile['city']);
                WC()->customer->set_shipping_state($profile['state']);
                WC()->customer->set_shipping_postcode($profile['postcode']);
                WC()->customer->set_shipping_country($profile['country']);

                WC()->customer->set_billing_address_1($profile['address_1']);
                WC()->customer->set_billing_address_2($profile['address_2']);
                WC()->customer->set_billing_city($profile['city']);
                WC()->customer->set_billing_state($profile['state']);
                WC()->customer->set_billing_postcode($profile['postcode']);
                WC()->customer->set_billing_country($profile['country']);

                if (WC()->session) {
                    WC()->session->set('customer_lat', $profile['lat']);
                    WC()->session->set('customer_lng', $profile['lng']);
                    if (!empty($profile['distance_data'])) {
                        WC()->session->set('fxw_distance_data', $profile['distance_data']);
                    }
                }
            }
        }
    }
}

new FXW_Checkout();
