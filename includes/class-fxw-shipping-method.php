<?php
/**
 * Manages the custom shipping method.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against loading before WooCommerce is initialized
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

if ( ! class_exists( 'FXW_Shipping_Method' ) ) {
	class FXW_Shipping_Method extends WC_Shipping_Method {
		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'foodxpress_delivery';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'FoodXpress Delivery', 'foodxpress' );
			$this->method_description = __( 'Dynamic distance-based shipping for FoodXpress.', 'foodxpress' );
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->init();
		}

		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		function init() {
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title      = $this->get_option( 'title' );
			$this->enabled    = $this->get_option( 'enabled' );

			// Save settings in admin if you have any defined
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Define settings field for this shipping
		 * @return void
		 */
		function init_form_fields() {
			$this->instance_form_fields = array(
				'title' => array(
					'title'       => __( 'Title', 'foodxpress' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'foodxpress' ),
					'default'     => __( 'FoodXpress Delivery', 'foodxpress' ),
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * This function is used to calculate the shipping cost.
		 *
		 * @param array $package
		 */
		public function calculate_shipping( $package = array() ) {
			$options = get_option( 'fxw_settings' );
			$api_key = isset( $options['fxw_google_maps_api_key'] ) ? $options['fxw_google_maps_api_key'] : '';

			if ( empty( $api_key ) ) {
				return;
			}

			// Ensure session exists and attempt fallback geocoding if lat/lng missing.
			if ( ! WC()->session ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->debug( 'calculate_shipping: WC session missing', array( 'source' => 'foodxpress' ) );
				}
				return;
			}

			$__lat = WC()->session->get( 'customer_lat' );
			$__lng = WC()->session->get( 'customer_lng' );

			if ( ! $__lat || ! $__lng ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->debug( 'calculate_shipping: session lat/lng missing, attempting fallback geocode from shipping address', array( 'source' => 'foodxpress' ) );
				}

				$addr1    = WC()->customer ? WC()->customer->get_shipping_address_1() : '';
				$addr2    = WC()->customer ? WC()->customer->get_shipping_address_2() : '';
				$city     = WC()->customer ? WC()->customer->get_shipping_city() : '';
				$state    = WC()->customer ? WC()->customer->get_shipping_state() : '';
				$postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
				$country  = WC()->customer ? WC()->customer->get_shipping_country() : '';
				$parts    = array_filter( array( $addr1, $addr2, $city, $state, $postcode, $country ) );
				$full_address = trim( implode( ', ', $parts ) );

				if ( $full_address ) {
					$mapping_service = new FXW_Mapping_Service();
					$coords = $mapping_service->get_coords( $full_address );

					if ( is_wp_error( $coords ) ) {
						if ( function_exists( 'wc_get_logger' ) ) {
							wc_get_logger()->error( 'calculate_shipping: fallback geocode failed - ' . $coords->get_error_message(), array( 'source' => 'foodxpress' ) );
						}
						return;
					}

					$lat_val = is_array( $coords ) ? ( $coords['lat'] ?? null ) : ( ( is_object( $coords ) && isset( $coords->lat ) ) ? $coords->lat : null );
					$lng_val = is_array( $coords ) ? ( $coords['lng'] ?? null ) : ( ( is_object( $coords ) && isset( $coords->lng ) ) ? $coords->lng : null );

					if ( $lat_val && $lng_val ) {
						WC()->session->set( 'customer_lat', $lat_val );
						WC()->session->set( 'customer_lng', $lng_val );
						if ( function_exists( 'wc_get_logger' ) ) {
							wc_get_logger()->debug( sprintf( 'calculate_shipping: fallback geocode success lat=%s lng=%s from "%s"', $lat_val, $lng_val, $full_address ), array( 'source' => 'foodxpress' ) );
						}
					} else {
						if ( function_exists( 'wc_get_logger' ) ) {
							wc_get_logger()->error( 'calculate_shipping: fallback geocode returned invalid coords', array( 'source' => 'foodxpress' ) );
						}
						return;
					}
				} else {
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->debug( 'calculate_shipping: no shipping address available for fallback geocode', array( 'source' => 'foodxpress' ) );
					}
					return;
				}
			}

			if ( function_exists( 'wc_get_logger' ) ) {
				$addr_dbg   = isset( $options['fxw_restaurant_address'] ) ? $options['fxw_restaurant_address'] : '';
				$radius_dbg = isset( $options['fxw_delivery_zone_radius'] ) ? $options['fxw_delivery_zone_radius'] : '';
				$is_open_dbg = isset( $options['fxw_is_open'] ) ? $options['fxw_is_open'] : true;
				wc_get_logger()->debug( sprintf( 'calculate_shipping: settings is_open=%s radius=%s address="%s"', $is_open_dbg ? 'true' : 'false', $radius_dbg, $addr_dbg ), array( 'source' => 'foodxpress' ) );
			}

			// Check if deliveries are open
			$is_open = isset( $options['fxw_is_open'] ) ? $options['fxw_is_open'] : true;
			if ( ! $is_open ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->info( 'calculate_shipping: store closed (fxw_is_open=false)', array( 'source' => 'foodxpress' ) );
				}
				return;
			}

			// Get customer's lat/lng from the session, which is set by our AJAX call
			$customer_lat = WC()->session->get( 'customer_lat' );
			$customer_lng = WC()->session->get( 'customer_lng' );

			if ( ! $customer_lat || ! $customer_lng ) {
				return; // Can't calculate shipping without a location
			}

			$customer_location = array( 'lat' => $customer_lat, 'lng' => $customer_lng );

			$restaurant_address = isset( $options['fxw_restaurant_address'] ) ? $options['fxw_restaurant_address'] : '';

			$mapping_service = new FXW_Mapping_Service();
			$distance_data = $mapping_service->get_distance( $restaurant_address, $customer_location );

			if ( is_wp_error( $distance_data ) ) {
				// Log and surface a user-friendly error at checkout
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error( 'FoodXpress distance error: ' . $distance_data->get_error_message(), array( 'source' => 'foodxpress' ) );
				}
				if ( function_exists( 'wc_add_notice' ) && function_exists( 'is_checkout' ) && is_checkout() ) {
					wc_add_notice( __( 'We could not calculate delivery distance. Please verify your address and try again.', 'foodxpress' ), 'error' );
				}
				return;
			}

			if ( function_exists( 'wc_get_logger' ) ) {
				$dist_val = ( isset( $distance_data['distance'] ) && is_object( $distance_data['distance'] ) && isset( $distance_data['distance']->value ) ) ? $distance_data['distance']->value : null;
				$dur_val  = ( isset( $distance_data['duration'] ) && is_object( $distance_data['duration'] ) && isset( $distance_data['duration']->value ) ) ? $distance_data['duration']->value : null;
				wc_get_logger()->debug( sprintf( 'calculate_shipping: distance=%s m, duration=%s s', $dist_val, $dur_val ), array( 'source' => 'foodxpress' ) );
			}

			// Store distance data in session for UI/ETA reuse
			if ( WC()->session ) {
				WC()->session->set( 'fxw_distance_data', array(
					'distance' => $distance_data['distance'],
					'duration' => $distance_data['duration'],
				) );
			}

			$radius = isset( $options['fxw_delivery_zone_radius'] ) ? (float) $options['fxw_delivery_zone_radius'] : 10;
			$distance_in_km = $distance_data['distance']->value / 1000;

			if ( $distance_in_km > $radius ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->info( sprintf( 'calculate_shipping: out of zone (distance=%.3f km, radius=%.3f)', $distance_in_km, $radius ), array( 'source' => 'foodxpress' ) );
				}
				return; // Outside delivery zone
			}

			$base_fee = isset( $options['fxw_delivery_fee_base'] ) ? (float) $options['fxw_delivery_fee_base'] : 5;
			$fee_per_km = isset( $options['fxw_delivery_fee_per_km'] ) ? (float) $options['fxw_delivery_fee_per_km'] : 1.5;
			$cost = $base_fee + ( $distance_in_km * $fee_per_km );

			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->debug( sprintf( 'calculate_shipping: adding rate cost=%.2f (distance=%.3f km, base=%.2f, per_km=%.2f)', $cost, $distance_in_km, $base_fee, $fee_per_km ), array( 'source' => 'foodxpress' ) );
			}

			$rate_id = $this->id . ( $this->instance_id ? ':' . $this->instance_id : '' );
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->debug( 'calculate_shipping: rate_id=' . $rate_id, array( 'source' => 'foodxpress' ) );
			}

			$this->add_rate( array(
				'id'      => $rate_id,
				'label'   => $this->title,
				'cost'    => $cost,
				'package' => $package,
			) );
		}
	}
}
