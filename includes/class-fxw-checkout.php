<?php
/**
 * Checkout — Orchestrator
 *
 * Front-end integration surface for the checkout flow. Renders the
 * delivery fields on the WooCommerce checkout, customizes the default
 * field set, and pre-fills saved addresses for returning customers.
 *
 * As of 1.2.0, the larger checkout concerns live in two sibling classes
 * loaded below:
 *   - FXW_Checkout_Maps   (frontend map assets + location AJAX)
 *   - FXW_Checkout_Handler (validation, fees, save, label)
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fxw-checkout-maps.php';
require_once __DIR__ . '/class-fxw-checkout-handler.php';

/**
 * Class FXW_Checkout
 *
 * Orchestrates the checkout-page integration:
 *  - Renders the location picker and structured delivery-detail fields
 *  - Hides redundant WC billing/shipping fields
 *  - Pre-fills saved address for logged-in customers
 *
 * All hook registration for this class is in the constructor.
 *
 * @since 1.0.0
 */
class FXW_Checkout
{

    /**
     * Register checkout-render hooks.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('wp_loaded', array($this, 'load_saved_address'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_action('woocommerce_before_checkout_billing_form', array($this, 'add_checkout_fields'));
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
            // Exact delivery address — single line, Zomato/Swiggy-style:
            // everything the agent needs to find the door (flat, house,
            // building, block) in one required field next to the map pin.
            woocommerce_form_field('fxw_address_details', array(
                'type' => 'text',
                'class' => array('form-row-wide'),
                'label' => __('House / Flat / Building No.', 'foodxpress'),
                'placeholder' => __('e.g., Flat 4B, House 25, Tower A, Block 2', 'foodxpress'),
                'required' => true,
            ), isset($saved_profile['address_details']) ? $saved_profile['address_details'] : WC()->checkout->get_value('fxw_address_details'));

            // Landmark - Optional but helpful
            woocommerce_form_field('fxw_landmark', array(
                'type' => 'text',
                'class' => array('form-row-wide'),
                'label' => __('Nearby Landmark (optional)', 'foodxpress'),
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
        </div>
        <?php
    }

    /**
     * Remove the default WC billing/shipping address fields — the map pin
     * (coordinates) plus the exact-address field carry everything the
     * store needs. Unsetting (not CSS-hiding) keeps WooCommerce from
     * validating or submitting them; fees depend on the pin only.
     *
     * @param array $fields Checkout fields.
     * @return array
     * @since 1.0.0
     */
    public function customize_checkout_fields($fields)
    {
        $removed_suffixes = array('address_1', 'address_2', 'city', 'state', 'postcode', 'country');
        foreach (array('billing', 'shipping') as $group) {
            foreach ($removed_suffixes as $suffix) {
                $key = $group . '_' . $suffix;
                if (isset($fields[$group][$key])) {
                    unset($fields[$group][$key]);
                }
            }
        }

        return $fields;
    }

    /**
     * Seed the session from the saved delivery profile for logged-in
     * customers on checkout, so the saved pin + distance become the
     * default without any interaction. The map and fields are also
     * pre-filled from the same profile (see add_checkout_fields and
     * the localized saved_address param).
     *
     * @since 1.0.0
     */
    public function load_saved_address()
    {
        if (is_user_logged_in() && is_checkout()) {
            $profile = get_user_meta(get_current_user_id(), '_fxw_delivery_profile', true);
            if (!empty($profile) && !empty($profile['lat']) && !empty($profile['lng']) && WC()->session) {
                WC()->session->set('customer_lat', $profile['lat']);
                WC()->session->set('customer_lng', $profile['lng']);
                if (!empty($profile['distance_data'])) {
                    WC()->session->set('fxw_distance_data', $profile['distance_data']);
                }
            }
        }
    }
}

new FXW_Checkout();
