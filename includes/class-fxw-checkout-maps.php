<?php
/**
 * Checkout — Maps / Frontend Assets
 *
 * Owns the Google Maps script loading, map-related AJAX endpoints, and
 * debug status surface for the checkout flow. Split out of FXW_Checkout
 * in 1.2.0 to keep the orchestrator file lean.
 *
 * @since      1.2.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FXW_Checkout_Maps
 *
 * Frontend-facing checkout concerns:
 *  - Google Maps script registration / enqueue (with API-key guard)
 *  - Async/defer attribute injection for the Maps script tag
 *  - AJAX: get restaurant location (for map centering)
 *  - AJAX: debug status snapshot (settings + zones + session) for admins
 *
 * Each instance registers its own hooks in the constructor; no external
 * wiring required beyond `require_once`-ing this file.
 *
 * @since 1.2.0
 */
class FXW_Checkout_Maps
{

    /**
     * Register frontend + AJAX hooks.
     *
     * @since 1.2.0
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('script_loader_tag', array($this, 'add_async_defer_to_maps_script'), 10, 2);
        add_action('wp_ajax_fxw_get_restaurant_location', array($this, 'get_restaurant_location'));
        add_action('wp_ajax_nopriv_fxw_get_restaurant_location', array($this, 'get_restaurant_location'));
        add_action('wp_ajax_fxw_debug_status', array($this, 'debug_status'));
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

        // Restaurant centre for the radius circle + map default (coords only;
        // pure parse when fxw_restaurant_latlng is set, else 24h-cached geocode)
        $restaurant_center = (new FXW_Mapping_Service())->get_restaurant_location($options);
        $restaurant_center = is_wp_error($restaurant_center) ? null : $restaurant_center;

        wp_localize_script('fxw-checkout', 'fxw_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fxw-checkout-nonce'),
            'rest_url' => esc_url_raw(rest_url('foodxpress/v1/checkout')),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '৳',
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'prep_time' => isset($options['fxw_preparation_time']) ? (int) $options['fxw_preparation_time'] : FXW_Config::DEFAULT_PREP_TIME,
            'saved_address' => $saved_address,
            'restaurant_center' => $restaurant_center,
            'radius_km' => isset($options['fxw_delivery_zone_radius']) ? (float) $options['fxw_delivery_zone_radius'] : (float) FXW_Config::DEFAULT_DELIVERY_RADIUS,
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
}

new FXW_Checkout_Maps();
