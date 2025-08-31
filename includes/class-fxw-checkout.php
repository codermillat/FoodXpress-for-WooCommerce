<?php
/**
 * Manages the checkout process.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Checkout {

/**
 * Initialize the class and set its properties.
 *
 * @since    1.0.0
 */
public function __construct() {
add_action( 'wp_loaded', array( $this, 'load_saved_address' ) );
add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );
add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_delivery_zone' ), 20, 2 );
add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_checkout_fields' ) );
add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
add_action( 'wp_ajax_fxw_get_restaurant_location', array( $this, 'get_restaurant_location' ) );
add_action( 'wp_ajax_nopriv_fxw_get_restaurant_location', array( $this, 'get_restaurant_location' ) );
add_action( 'wp_ajax_fxw_update_customer_location', array( $this, 'update_customer_location' ) );
add_action( 'wp_ajax_nopriv_fxw_update_customer_location', array( $this, 'update_customer_location' ) );
add_action( 'wp_ajax_fxw_debug_status', array( $this, 'debug_status' ) );
add_action( 'wp_ajax_nopriv_fxw_debug_status', array( $this, 'debug_status' ) );
add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'save_customer_address' ), 10, 2 );
add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_delivery_fee' ) );
add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'append_eta_to_label' ), 10, 2 );
add_action( 'woocommerce_checkout_create_order', array( $this, 'save_unit_to_order' ), 10, 2 );
add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_show_unit' ), 10, 1 );
add_action( 'woocommerce_order_details_after_order_table', array( $this, 'frontend_show_unit' ), 10, 1 );

// Register script loader filter once for Google Maps defer attribute
add_filter( 'script_loader_tag', array( $this, 'add_async_defer_to_maps_script' ), 10, 2 );
}

/**
 * Get restaurant location via AJAX for map centering and zone drawing.
 *
 * @since    1.0.0
 */
public function get_restaurant_location() {
    $rate_limit_check = FXW_Rate_Limiter::check_rate_limit( 'get_restaurant_location', 10 );
    if ( is_wp_error( $rate_limit_check ) ) {
        wp_send_json_error( array( 'message' => $rate_limit_check->get_error_message() ), 429 );
        return;
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'fxw-checkout-nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Session has expired. Please reload the page.', 'foodxpress' ) ), 403 );
        return;
    }

    $options = get_option( 'fxw_settings' );
    $restaurant_address = isset( $options['fxw_restaurant_address'] ) ? trim( $options['fxw_restaurant_address'] ) : '';
    if ( empty( $restaurant_address ) ) {
        wp_send_json_error( array( 'message' => __( 'Restaurant address not configured.', 'foodxpress' ) ), 400 );
        return;
    }

    $mapping = new FXW_Mapping_Service();
    $coords = $mapping->get_coords( $restaurant_address );
    if ( is_wp_error( $coords ) ) {
        wp_send_json_error( array( 'message' => $coords->get_error_message() ), 500 );
        return;
    }

    $lat = is_object( $coords ) ? ( $coords->lat ?? null ) : ( is_array( $coords ) ? ( $coords['lat'] ?? null ) : null );
    $lng = is_object( $coords ) ? ( $coords->lng ?? null ) : ( is_array( $coords ) ? ( $coords['lng'] ?? null ) : null );

    if ( $lat && $lng ) {
        wp_send_json_success( array( 'lat' => (float) $lat, 'lng' => (float) $lng ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Could not determine restaurant coordinates from the provided address.', 'foodxpress' ) ), 500 );
    }
}

	/**
	 * Enqueue the scripts for the checkout page.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		// Get the Google Maps API key first
		$options = get_option( 'fxw_settings' );
		$api_key = isset( $options['fxw_google_maps_api_key'] ) ? trim( $options['fxw_google_maps_api_key'] ) : '';

		// Bail early if no API key - don't load any map-related scripts
		if ( empty( $api_key ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning( 'Google Maps API key missing - map functionality disabled', array( 'source' => 'foodxpress' ) );
			}
			return;
		}

// Register the checkout script (will be enqueued below)
wp_register_script( 'fxw-checkout', plugin_dir_url( __FILE__ ) . '../assets/js/checkout.js', array( 'jquery' ), FXW_VERSION, true );

// Enqueue frontend CSS for map styling
wp_enqueue_style( 'fxw-frontend', FXW_PLUGIN_URL . 'assets/css/frontend.css', array(), FXW_VERSION );

		// Build Google Maps URL with async loading and proper callback
		$maps_url = add_query_arg( array(
			'key' => $api_key,
			'v' => 'weekly',
			'libraries' => 'places,marker,geocoding',
			'callback' => 'fxwInitMap',
			'loading' => 'async'
		), 'https://maps.googleapis.com/maps/api/js' );

		// Register Google Maps script
		wp_register_script( 'fxw-google-maps', $maps_url, array(), null, true );

		// Add inline stub BEFORE Google Maps loads to guarantee callback exists
		$inline_stub = '
		window.fxwInitMap = window.fxwInitMap || function() {
			if (window.console && window.console.log) {
				console.log("FXW: Stub callback called, checking for internal init");
			}
			if (typeof window.fxwInternalInit === "function") {
				try {
					window.fxwInternalInit();
				} catch(e) {
					if (window.console && window.console.error) {
						console.error("FXW init error:", e);
					}
				}
			} else {
				if (window.console && window.console.log) {
					console.log("FXW: Internal init not ready, scheduling retry");
				}
				var retries = 0;
				var waitForInternal = function() {
					if (typeof window.fxwInternalInit === "function") {
						try {
							window.fxwInternalInit();
						} catch(e) {
							if (window.console && window.console.error) {
								console.error("FXW delayed init error:", e);
							}
						}
					} else if (retries < 50) {
						retries++;
						setTimeout(waitForInternal, 100);
					} else {
						if (window.console && window.console.error) {
							console.error("FXW: Timeout waiting for internal init function");
						}
					}
				};
				setTimeout(waitForInternal, 100);
			}
		};';

wp_add_inline_script( 'fxw-google-maps', $inline_stub, 'before' );

// Enqueue scripts in proper order
wp_enqueue_script( 'fxw-checkout' );
wp_enqueue_script( 'fxw-google-maps' );

// Localize script with minimal required data (security: avoid exposing full options)
$saved_address = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_fxw_delivery_profile', true ) : null;
wp_localize_script( 'fxw-checkout', 'fxw_checkout_params', array(
'ajax_url' => admin_url( 'admin-ajax.php' ),
'nonce' => wp_create_nonce( 'fxw-checkout-nonce' ),
'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
'prep_time' => isset( $options['fxw_preparation_time'] ) ? (int) $options['fxw_preparation_time'] : FXW_Config::DEFAULT_PREP_TIME,
'saved_address' => $saved_address,
'max_retries' => FXW_Config::MAX_RETRIES,
'retry_delay' => FXW_Config::RETRY_DELAY,
) );
	}

	/**
	 * Add async and defer attributes to Google Maps script for optimal loading.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_async_defer_to_maps_script( $tag, $handle ) {
		if ( 'fxw-google-maps' === $handle ) {
			// Add defer for non-blocking execution while maintaining order
			$tag = str_replace( '<script ', '<script defer ', $tag );
		}
		return $tag;
	}

/**
 * Debug status endpoint to surface FXW settings, shipping zones, and session state.
 *
 * @since 1.0.0
 */
public function debug_status() {
$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ( isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '' );
if ( ! wp_verify_nonce( $nonce, 'fxw-checkout-nonce' ) ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->warning( 'ajax invalid_nonce: debug_status', array( 'source' => 'foodxpress' ) );
    }
    wp_send_json_error( array( 'code' => 'invalid_nonce' ), 403 );
}
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
}

$options = get_option( 'fxw_settings' );
$masked_options = is_array( $options ) ? $options : array();
if ( isset( $masked_options['fxw_google_maps_api_key'] ) ) {
    $key = (string) $masked_options['fxw_google_maps_api_key'];
    $len = strlen( $key );
    if ( $len > 8 ) {
        $masked_options['fxw_google_maps_api_key'] = substr( $key, 0, 4 ) . str_repeat( '*', max( 0, $len - 8 ) ) . substr( $key, -4 );
    } else {
        $masked_options['fxw_google_maps_api_key'] = str_repeat( '*', $len );
    }
}

$zones_data = array();
if ( class_exists( 'WC_Shipping_Zones' ) ) {
    $zones = WC_Shipping_Zones::get_zones();
    foreach ( $zones as $zone ) {
        $zone_obj = new WC_Shipping_Zone( $zone['zone_id'] );
        $methods = array();
        foreach ( $zone_obj->get_shipping_methods() as $m ) {
            $methods[] = array(
                'id'          => $m->id,
                'instance_id' => $m->instance_id,
                'enabled'     => ( $m->enabled === 'yes' || $m->enabled === true ),
                'title'       => method_exists( $m, 'get_title' ) ? $m->get_title() : '',
            );
        }
        $zones_data[] = array(
            'zone_id'   => $zone['zone_id'],
            'zone_name' => $zone['zone_name'],
            'methods'   => $methods,
        );
    }
    $zone0 = new WC_Shipping_Zone( 0 );
    $methods0 = array();
    foreach ( $zone0->get_shipping_methods() as $m ) {
        $methods0[] = array(
            'id'          => $m->id,
            'instance_id' => $m->instance_id,
            'enabled'     => ( $m->enabled === 'yes' || $m->enabled === true ),
            'title'       => method_exists( $m, 'get_title' ) ? $m->get_title() : '',
        );
    }
    $zones_data[] = array(
        'zone_id'   => 0,
        'zone_name' => $zone0->get_zone_name(),
        'methods'   => $methods0,
    );
}

$session = array(
    'customer_lat' => WC()->session ? WC()->session->get( 'customer_lat' ) : null,
    'customer_lng' => WC()->session ? WC()->session->get( 'customer_lng' ) : null,
    'chosen_shipping_methods' => WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : null,
    'fxw_coords_locked' => WC()->session ? WC()->session->get( 'fxw_coords_locked' ) : null,
);

$customer = array(
    'shipping_country'   => WC()->customer ? WC()->customer->get_shipping_country() : '',
    'shipping_state'     => WC()->customer ? WC()->customer->get_shipping_state() : '',
    'shipping_postcode'  => WC()->customer ? WC()->customer->get_shipping_postcode() : '',
    'shipping_city'      => WC()->customer ? WC()->customer->get_shipping_city() : '',
);

wp_send_json_success( array(
    'fxw_settings' => $masked_options,
    'zones'        => $zones_data,
    'session'      => $session,
    'customer'     => $customer,
) );
}

/**
 * Append ETA minutes to our shipping method label.
 *
 * @param string           $label
 * @param WC_Shipping_Rate $rate
 * @return string
 */
public function append_eta_to_label( $label, $rate ) {
    // Identify our shipping method
    $method_id = '';
    if ( is_object( $rate ) ) {
        if ( isset( $rate->method_id ) ) {
            $method_id = $rate->method_id;
        } elseif ( method_exists( $rate, 'get_method_id' ) ) {
            $method_id = $rate->get_method_id();
        } elseif ( isset( $rate->id ) && is_string( $rate->id ) ) {
            $method_id = strpos( $rate->id, ':' ) !== false ? substr( $rate->id, 0, strpos( $rate->id, ':' ) ) : $rate->id;
        }
    }
    if ( $method_id !== 'foodxpress_delivery' ) {
        return $label;
    }

    $distance_data = WC()->session ? WC()->session->get( 'fxw_distance_data' ) : null;
    if ( ! $distance_data || ! isset( $distance_data['duration'] ) || ! is_object( $distance_data['duration'] ) || ! isset( $distance_data['duration']->value ) ) {
        return $label;
    }

    $options   = get_option( 'fxw_settings' );
    $prep_time = isset( $options['fxw_preparation_time'] ) ? (int) $options['fxw_preparation_time'] : FXW_Config::DEFAULT_PREP_TIME;
    $seconds   = (int) $distance_data['duration']->value + ( $prep_time * 60 );
    $mins      = max( 1, (int) round( $seconds / 60 ) );

    $eta_html = sprintf( ' <small class="fxw-eta-label">%s</small>', esc_html( sprintf( __( 'ETA ~ %d mins', 'foodxpress' ), $mins ) ) );
    return $label . $eta_html;
}

	/**
	 * Add the delivery fee to the cart.
	 *
	 * @param   WC_Cart    $cart    The cart object.
	 * @since   1.0.0
	 */
public function add_delivery_fee( $cart ) {
if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
return;
}

$options = get_option( 'fxw_settings' );
$enable_extra_fee = isset( $options['fxw_enable_extra_delivery_fee'] ) ? in_array( $options['fxw_enable_extra_delivery_fee'], array( 'yes', 'true', 1, '1' ), true ) : false;
if ( ! $enable_extra_fee ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( 'add_delivery_fee: disabled via settings', array( 'source' => 'foodxpress' ) );
}
return;
}

// Avoid double-charging: if our shipping method is chosen, skip adding a separate fee
$chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();
if ( is_array( $chosen ) ) {
foreach ( $chosen as $method_id ) {
if ( is_string( $method_id ) && strpos( $method_id, 'foodxpress_delivery' ) === 0 ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( 'add_delivery_fee: skipping fee because FoodXpress shipping is chosen (' . $method_id . ')', array( 'source' => 'foodxpress' ) );
}
return;
}
}
}

$distance_data = WC()->session->get( 'fxw_distance_data' );

if ( ! $distance_data ) {
return;
}

$options = get_option( 'fxw_settings' );
$base_fee = isset( $options['fxw_delivery_fee_base'] ) ? (float) $options['fxw_delivery_fee_base'] : 5;
$fee_per_km = isset( $options['fxw_delivery_fee_per_km'] ) ? (float) $options['fxw_delivery_fee_per_km'] : 1.5;

$distance_in_km = $distance_data['distance']->value / 1000;
$delivery_fee = $base_fee + ( $distance_in_km * $fee_per_km );

$cart->add_fee( __( 'Delivery Fee', 'foodxpress' ), $delivery_fee );
}

	/**
	 * Update customer location via AJAX.
	 *
	 * @since    1.0.0
	 */
public function update_customer_location() {
    $rate_limit_check = FXW_Rate_Limiter::check_rate_limit( 'update_customer_location', 30 );
    if ( is_wp_error( $rate_limit_check ) ) {
        wp_send_json_error( array( 'message' => $rate_limit_check->get_error_message() ), 429 );
        return;
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'fxw-checkout-nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Session has expired. Please reload the page.', 'foodxpress' ) ), 403 );
        return;
    }

$lat = isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : 0;
$lng = isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : 0;

// Parse and sanitize address array with defaults to prevent notices
$raw_address = isset( $_POST['address'] ) ? wp_parse_args( (array) wc_clean( wp_unslash( $_POST['address'] ) ), array(
    'country'   => '',
    'state'     => '',
    'postcode'  => '',
    'city'      => '',
    'address_1' => '',
    'address_2' => '',
) ) : array(
    'country'   => '',
    'state'     => '',
    'postcode'  => '',
    'city'      => '',
    'address_1' => '',
    'address_2' => '',
);

if ( ! $lat || ! $lng ) {
wp_send_json_error( 'Invalid location data.' );
}

WC()->customer->set_shipping_location( $raw_address['country'], $raw_address['state'], $raw_address['postcode'], $raw_address['city'] );
WC()->customer->set_shipping_address_1( $raw_address['address_1'] );
WC()->customer->set_shipping_address_2( $raw_address['address_2'] );

// Also set billing to keep destination in sync if store forces shipping to billing
WC()->customer->set_billing_country( $raw_address['country'] );
WC()->customer->set_billing_state( $raw_address['state'] );
WC()->customer->set_billing_postcode( $raw_address['postcode'] );
WC()->customer->set_billing_city( $raw_address['city'] );
WC()->customer->set_billing_address_1( $raw_address['address_1'] );
WC()->customer->set_billing_address_2( $raw_address['address_2'] );

if ( method_exists( WC()->customer, 'save' ) ) {
WC()->customer->save();
}

WC()->session->set( 'customer_lat', $lat );
WC()->session->set( 'customer_lng', $lng );
WC()->session->set( 'fxw_coords_locked', true );

// Ensure WC session cookie exists for guests and log state
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( 'fxw_update_customer_location: ensuring session cookie + verifying session values', array( 'source' => 'foodxpress' ) );
}
if ( WC()->session && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
WC()->session->set_customer_session_cookie( true );
}

// Log update for debugging
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( sprintf( 'fxw_update_customer_location lat=%s lng=%s city=%s state=%s country=%s', $lat, $lng, $raw_address['city'], $raw_address['state'], $raw_address['country'] ), array( 'source' => 'foodxpress' ) );
$lat_check = WC()->session ? WC()->session->get( 'customer_lat' ) : null;
$lng_check = WC()->session ? WC()->session->get( 'customer_lng' ) : null;
wc_get_logger()->debug( sprintf( 'fxw_update_customer_location: session_after_set lat=%s lng=%s logged_in=%s', $lat_check, $lng_check, is_user_logged_in() ? 'yes' : 'no' ), array( 'source' => 'foodxpress' ) );
}

// Trigger cart recalculation
WC()->cart->calculate_totals();

wp_send_json_success( array( 'lat' => $lat, 'lng' => $lng ) );
}

	/**
	 * Save customer address to user meta.
	 *
	 * @param int   $customer_id The customer ID.
	 * @param array $data        The posted data.
	 * @since 1.0.0
	 */
public function save_customer_address( $customer_id, $data ) {
    if ( ! empty( $_POST['fxw_location-search-input'] ) ) {
        $distance_data = WC()->session->get( 'fxw_distance_data' );
        $profile = array(
            'address_1'     => $data['shipping_address_1'],
            'address_2'     => $data['shipping_address_2'],
            'city'          => $data['shipping_city'],
            'state'         => $data['shipping_state'],
            'postcode'      => $data['shipping_postcode'],
            'country'       => $data['shipping_country'],
            'lat'           => WC()->session->get( 'customer_lat' ),
            'lng'           => WC()->session->get( 'customer_lng' ),
            'unit'          => isset( $_POST['fxw_address_unit'] ) ? sanitize_text_field( $_POST['fxw_address_unit'] ) : '',
            'distance_data' => $distance_data,
        );
        update_user_meta( $customer_id, '_fxw_delivery_profile', $profile );
    }
}

    /**
     * Save Flat/House/Unit to order meta during checkout.
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public function save_unit_to_order( $order, $data ) {
        if ( isset( $_POST['fxw_address_unit'] ) ) {
            $unit = sanitize_text_field( wp_unslash( $_POST['fxw_address_unit'] ) );
            if ( is_a( $order, 'WC_Order' ) && $unit !== '' ) {
                $order->update_meta_data( '_fxw_address_unit', $unit );
            }
        }
    }

    /**
     * Show Flat/House/Unit in admin order details.
     *
     * @param WC_Order $order
     */
    public function admin_show_unit( $order ) {
        $unit = is_a( $order, 'WC_Order' ) ? $order->get_meta( '_fxw_address_unit', true ) : '';
        if ( ! empty( $unit ) ) {
            echo '<p><strong>' . esc_html__( 'Flat/House/Unit', 'foodxpress' ) . ':</strong> ' . esc_html( $unit ) . '</p>';
        }
    }

    /**
     * Show Flat/House/Unit on customer order details page.
     *
     * @param WC_Order|int $order
     */
    public function frontend_show_unit( $order ) {
        if ( ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) {
            return;
        }
        $unit = $order->get_meta( '_fxw_address_unit', true );
        if ( ! empty( $unit ) ) {
            echo '<p class="fxw-order-unit"><strong>' . esc_html__( 'Flat/House/Unit', 'foodxpress' ) . ':</strong> ' . esc_html( $unit ) . '</p>';
        }
    }

/**
 * Add custom fields to the checkout page.
	 *
	 * @since    1.0.0
	 */
public function add_checkout_fields() {
    $saved_address_value = '';
    if ( is_user_logged_in() ) {
        $profile = get_user_meta( get_current_user_id(), '_fxw_delivery_profile', true );
        if ( ! empty( $profile['address_1'] ) ) {
            $saved_address_value = $profile['address_1'];
        }
    }
    ?>
<div id="fxw-location-picker-container">
<h3><?php esc_html_e( 'Delivery Location', 'foodxpress' ); ?></h3>
<p><?php esc_html_e( 'Search for your address or use the map to set your delivery location.', 'foodxpress' ); ?></p>

<div class="fxw-location-search-wrapper">
<input id="fxw-location-search-input" type="text" placeholder="<?php esc_attr_e( 'Search for your address...', 'foodxpress' ); ?>" class="input-text" value="<?php echo esc_attr( $saved_address_value ); ?>" />
<a href="#" id="fxw-get-location" class="button"><?php esc_html_e( 'Use My Location', 'foodxpress' ); ?></a>
</div>

<div id="fxw-map" style="height: 300px; margin: 20px 0;"></div>

<div id="fxw-geolocation-error" class="woocommerce-error" style="display:none;"></div>

<p class="form-row form-row-wide">
    <label for="fxw_address_unit"><?php esc_html_e( 'Flat/House/Unit No. (optional)', 'foodxpress' ); ?></label>
    <input type="text" name="fxw_address_unit" id="fxw_address_unit" class="input-text" placeholder="<?php esc_attr_e( 'e.g. Flat 12B, Tower 3', 'foodxpress' ); ?>" value="<?php echo esc_attr( WC()->checkout->get_value( 'fxw_address_unit' ) ); ?>" autocomplete="off" />
</p>

<?php if ( is_user_logged_in() ) : ?>
<p class="form-row form-row-wide">
<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="fxw_save_address" id="fxw_save_address" value="1" /> <span><?php esc_html_e( 'Save this address for future use', 'foodxpress' ); ?></span>
</label>
</p>
<?php endif; ?>
</div>
<?php
woocommerce_form_field( 'fxw_delivery_notes', array(
'type'        => 'textarea',
'class'       => array( 'form-row-wide' ),
'label'       => __( 'Delivery Notes', 'foodxpress' ),
'placeholder' => __( 'e.g. knock loudly, beware of the dog', 'foodxpress' ),
), WC()->checkout->get_value( 'fxw_delivery_notes' ) );
}

/**
 * Validate that the customer's address is within the delivery zone.
 *
 * @param   array      $data      The checkout data.
 * @param   WP_Error   $errors    The errors object.
 * @since   1.0.0
 */
public function validate_delivery_zone( $data, $errors ) {
$options = get_option( 'fxw_settings' );
$radius  = isset( $options['fxw_delivery_zone_radius'] ) ? (float) $options['fxw_delivery_zone_radius'] : FXW_Config::DEFAULT_DELIVERY_RADIUS;
$is_open_raw = isset( $options['fxw_is_open'] ) ? $options['fxw_is_open'] : 'yes';
$is_open = in_array( $is_open_raw, array( 'yes', 'true', 1, '1', true ), true );

// Check if restaurant address is configured
$restaurant_address = isset( $options['fxw_restaurant_address'] ) ? trim( $options['fxw_restaurant_address'] ) : '';
if ( empty( $restaurant_address ) ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->error( 'validate_delivery_zone: restaurant address not configured', array( 'source' => 'foodxpress' ) );
    }
    $errors->add( 'delivery_zone', __( 'Delivery service is not properly configured. Please contact support.', 'foodxpress' ) );
    return;
}
if ( ! $is_open ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->info( 'validate_delivery_zone: store closed (fxw_is_open=false)', array( 'source' => 'foodxpress' ) );
    }
    $errors->add( 'delivery_zone', __( 'We are currently closed for deliveries. Please try again later.', 'foodxpress' ) );
    return;
}

$customer_lat = WC()->session->get( 'customer_lat' );
$customer_lng = WC()->session->get( 'customer_lng' );

if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( sprintf( 'validate_delivery_zone session lat=%s lng=%s', $customer_lat, $customer_lng ), array( 'source' => 'foodxpress' ) );
}

if ( ! $customer_lat || ! $customer_lng ) {
    // Fallback: try to geocode current shipping address when session coords are missing
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->warning( 'validate_delivery_zone: missing coords, attempting fallback geocode from shipping address', array( 'source' => 'foodxpress' ) );
    }

    $addr1    = WC()->customer ? WC()->customer->get_shipping_address_1() : '';
    $addr2    = WC()->customer ? WC()->customer->get_shipping_address_2() : '';
    $city     = WC()->customer ? WC()->customer->get_shipping_city() : '';
    $state    = WC()->customer ? WC()->customer->get_shipping_state() : '';
    $postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
    $country  = WC()->customer ? WC()->customer->get_shipping_country() : '';

    $parts        = array_filter( array( $addr1, $addr2, $city, $state, $postcode, $country ) );
    $full_address = trim( implode( ', ', $parts ) );

    if ( $full_address ) {
        $mapping_service = new FXW_Mapping_Service();
        $coords = $mapping_service->get_coords( $full_address );

        if ( is_wp_error( $coords ) ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->error( 'validate_delivery_zone: fallback geocode failed - ' . $coords->get_error_message(), array( 'source' => 'foodxpress' ) );
            }
            $errors->add( 'delivery_zone', __( 'Please select your location on the map.', 'foodxpress' ) );
            return;
        }

        $lat_val = is_array( $coords ) ? ( $coords['lat'] ?? null ) : ( ( is_object( $coords ) && isset( $coords->lat ) ) ? $coords->lat : null );
        $lng_val = is_array( $coords ) ? ( $coords['lng'] ?? null ) : ( ( is_object( $coords ) && isset( $coords->lng ) ) ? $coords->lng : null );

        if ( $lat_val && $lng_val ) {
            WC()->session->set( 'customer_lat', $lat_val );
            WC()->session->set( 'customer_lng', $lng_val );
            $customer_lat = $lat_val;
            $customer_lng = $lng_val;

            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->debug( sprintf( 'validate_delivery_zone: fallback geocode success lat=%s lng=%s from "%s"', $lat_val, $lng_val, $full_address ), array( 'source' => 'foodxpress' ) );
            }
        } else {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->error( 'validate_delivery_zone: fallback geocode returned invalid coords', array( 'source' => 'foodxpress' ) );
            }
            $errors->add( 'delivery_zone', __( 'Please select your location on the map.', 'foodxpress' ) );
            return;
        }
    } else {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->warning( 'validate_delivery_zone: no shipping address to geocode', array( 'source' => 'foodxpress' ) );
        }
        $errors->add( 'delivery_zone', __( 'Please select your location on the map.', 'foodxpress' ) );
        return;
    }
}

		$customer_location = array( 'lat' => $customer_lat, 'lng' => $customer_lng );

		$mapping_service = new FXW_Mapping_Service();
		$distance_data   = $mapping_service->get_distance(
			$options['fxw_restaurant_address'],
			$customer_location
		);

if ( is_wp_error( $distance_data ) ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'validate_delivery_zone distance error: ' . $distance_data->get_error_message(), array( 'source' => 'foodxpress' ) );
}
$errors->add( 'delivery_zone', $distance_data->get_error_message() );
return;
}

$distance_in_km = $distance_data['distance']->value / 1000;

if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( sprintf( 'validate_delivery_zone: distance=%.3f km radius=%.3f', $distance_in_km, $radius ), array( 'source' => 'foodxpress' ) );
}

if ( $distance_in_km > $radius ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->info( 'validate_delivery_zone: out of zone', array( 'source' => 'foodxpress' ) );
}
$errors->add( 'delivery_zone', __( 'Sorry, we do not deliver to your location.', 'foodxpress' ) );
}
	}


public function customize_checkout_fields( $fields ) {
    // Hide the default address fields
    $fields['billing']['billing_address_1']['class'][] = 'fxw-hidden-field';
    $fields['billing']['billing_address_2']['class'][] = 'fxw-hidden-field';
    $fields['billing']['billing_city']['class'][] = 'fxw-hidden-field';
    $fields['billing']['billing_state']['class'][] = 'fxw-hidden-field';
    $fields['billing']['billing_postcode']['class'][] = 'fxw-hidden-field';
    $fields['billing']['billing_country']['class'][] = 'fxw-hidden-field';

    $fields['shipping']['shipping_address_1']['class'][] = 'fxw-hidden-field';
    $fields['shipping']['shipping_address_2']['class'][] = 'fxw-hidden-field';
    $fields['shipping']['shipping_city']['class'][] = 'fxw-hidden-field';
    $fields['shipping']['shipping_state']['class'][] = 'fxw-hidden-field';
    $fields['shipping']['shipping_postcode']['class'][] = 'fxw-hidden-field';
    $fields['shipping']['shipping_country']['class'][] = 'fxw-hidden-field';

    return $fields;
}

public function load_saved_address() {
    if ( is_user_logged_in() && is_checkout() ) {
        $profile = get_user_meta( get_current_user_id(), '_fxw_delivery_profile', true );
        if ( ! empty( $profile ) && ! empty( $profile['lat'] ) && ! empty( $profile['lng'] ) ) {
            WC()->customer->set_shipping_address_1( $profile['address_1'] );
            WC()->customer->set_shipping_address_2( $profile['address_2'] );
            WC()->customer->set_shipping_city( $profile['city'] );
            WC()->customer->set_shipping_state( $profile['state'] );
            WC()->customer->set_shipping_postcode( $profile['postcode'] );
            WC()->customer->set_shipping_country( $profile['country'] );

            WC()->customer->set_billing_address_1( $profile['address_1'] );
            WC()->customer->set_billing_address_2( $profile['address_2'] );
            WC()->customer->set_billing_city( $profile['city'] );
            WC()->customer->set_billing_state( $profile['state'] );
            WC()->customer->set_billing_postcode( $profile['postcode'] );
            WC()->customer->set_billing_country( $profile['country'] );

            WC()->session->set( 'customer_lat', $profile['lat'] );
            WC()->session->set( 'customer_lng', $profile['lng'] );
            if ( ! empty( $profile['distance_data'] ) ) {
                WC()->session->set( 'fxw_distance_data', $profile['distance_data'] );
            }
        }
    }
}
}

new FXW_Checkout();
