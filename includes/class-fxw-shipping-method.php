<?php
/**
 * Manages the custom shipping method.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
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
		 * Calculate the shipping cost from the restaurant's coordinates to
		 * the customer's pinned coordinates. Nothing else influences the
		 * fee — by design, no address field ever reaches this method.
		 *
		 * @param array $package
		 */
		public function calculate_shipping( $package = array() ) {
			$options = get_option( 'fxw_settings' );
			$api_key = isset( $options['fxw_google_maps_api_key'] ) ? $options['fxw_google_maps_api_key'] : '';

			if ( empty( $api_key ) ) {
				return;
			}

			if ( ! WC()->session ) {
				return;
			}

			// The Open/Closed switch controls order placement (no rate when closed)
			if ( ! FXW_Checkout::is_store_open() ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->info( 'calculate_shipping: store closed (fxw_is_open=false)', array( 'source' => 'foodxpress' ) );
				}
				return;
			}

			// Customer's pinned coordinates (session, set by the map/REST flow)
			$customer_lat = WC()->session->get( 'customer_lat' );
			$customer_lng = WC()->session->get( 'customer_lng' );

			if ( ! $customer_lat || ! $customer_lng ) {
				return; // Can't calculate shipping without a pinned location
			}

			// Restaurant coordinates — explicit setting, else geocoded address (cached)
			$mapping_service = new FXW_Mapping_Service();
			$restaurant      = $mapping_service->get_restaurant_location( $options );

			if ( is_wp_error( $restaurant ) ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error( 'calculate_shipping: ' . $restaurant->get_error_message(), array( 'source' => 'foodxpress' ) );
				}
				return;
			}

			$distance_data = $mapping_service->get_distance(
				$restaurant,
				array( 'lat' => $customer_lat, 'lng' => $customer_lng )
			);

			if ( is_wp_error( $distance_data ) ) {
				// Log and surface a user-friendly error at checkout
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error( 'FoodXpress distance error: ' . $distance_data->get_error_message(), array( 'source' => 'foodxpress' ) );
				}
				if ( function_exists( 'wc_add_notice' ) && function_exists( 'is_checkout' ) && is_checkout() ) {
					wc_add_notice( __( 'We could not calculate delivery distance. Please verify your location and try again.', 'foodxpress' ), 'error' );
				}
				return;
			}

			if ( ! isset( $distance_data['distance'] ) || ! is_object( $distance_data['distance'] ) || ! isset( $distance_data['distance']->value ) ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error( 'calculate_shipping: distance response missing distance value', array( 'source' => 'foodxpress' ) );
				}
				return;
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

			// Admin-configurable pricing rules (FXW_Pricing): minimum order,
			// distance tiers, free-delivery threshold.
			if ( class_exists( 'FXW_Pricing' ) ) {
				$min_error = FXW_Pricing::minimum_order_error();
				if ( null !== $min_error ) {
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->info( 'calculate_shipping: below minimum order — no rate', array( 'source' => 'foodxpress' ) );
					}
					return;
				}
			}

			$base_fee = isset( $options['fxw_delivery_fee_base'] ) ? (float) $options['fxw_delivery_fee_base'] : 5;
			$fee_per_km = isset( $options['fxw_delivery_fee_per_km'] ) ? (float) $options['fxw_delivery_fee_per_km'] : 1.5;
			$cost = $base_fee + ( $distance_in_km * $fee_per_km );

			if ( class_exists( 'FXW_Pricing' ) ) {
				$tier_fee = FXW_Pricing::fee_for_distance( $distance_in_km, $options );
				if ( false === $tier_fee ) {
					if ( function_exists( 'wc_get_logger' ) ) {
						wc_get_logger()->info( sprintf( 'calculate_shipping: distance %.2f km beyond all tiers — no rate', $distance_in_km ), array( 'source' => 'foodxpress' ) );
					}
					return;
				}
				if ( null !== $tier_fee ) {
					$cost = $tier_fee;
				}
				if ( FXW_Pricing::is_free_delivery() ) {
					$cost = 0;
				}
			}

			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->debug( sprintf( 'calculate_shipping: adding rate cost=%.2f (distance=%.3f km, base=%.2f, per_km=%.2f)', $cost, $distance_in_km, $base_fee, $fee_per_km ), array( 'source' => 'foodxpress' ) );
			}

			$rate_id = $this->id . ( $this->instance_id ? ':' . $this->instance_id : '' );

			$this->add_rate( array(
				'id'      => $rate_id,
				'label'   => $this->title,
				'cost'    => $cost,
				'package' => $package,
			) );
		}
	}
}
