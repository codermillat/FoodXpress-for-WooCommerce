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
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fxw-checkout-maps.php';
require_once __DIR__ . '/class-fxw-checkout-handler.php';
require_once __DIR__ . '/class-fxw-blocks-checkout.php';

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
        add_action('woocommerce_before_cart', array($this, 'render_store_closed_notice'));
        add_action('woocommerce_before_checkout_form', array($this, 'render_store_closed_notice'));
    }

    /**
     * Is the store accepting delivery orders right now? Single shared
     * source of truth for the admin-bar "Deliveries: Open/Closed"
     * switch (the option value is a boolean written by FXW_Admin_Bar).
     *
     * @return bool
     * @since 1.2.11
     */
    public static function is_store_open()
    {
        $options = get_option('fxw_settings');
        $is_open = isset($options['fxw_is_open']) ? $options['fxw_is_open'] : true;

        if (is_bool($is_open)) {
            return $is_open;
        }

        return in_array($is_open, array('yes', 'true', '1', 1), true);
    }

    /**
     * The Open/Closed switch controls order placement: when closed,
     * customers can still browse and fill their cart, but a clear
     * notice explains why they cannot order (and validation blocks
     * placement server-side). Rendered on the cart and classic
     * checkout pages; the blocks checkout gets its notice from
     * FXW_Blocks_Checkout.
     *
     * @since 1.2.11
     */
    public function render_store_closed_notice()
    {
        if (self::is_store_open()) {
            return;
        }

        if (function_exists('wc_print_notice')) {
            wc_print_notice(
                __('We are currently closed for deliveries. You can browse and fill your cart — ordering will be available as soon as we reopen.', 'foodxpress'),
                'error',
                array('fxw-store-closed')
            );
        }
    }

    /**
     * Add custom fields to the classic checkout page.
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

        self::render_location_picker();
        ?>
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
     * Render the Step-1 map location picker — shared by the classic
     * checkout (via the billing-form hook) and block-based checkout
     * pages (via FXW_Blocks_Checkout's the_content filter). checkout.js
     * boots on the presence of #fxw-map, so both surfaces behave
     * identically.
     *
     * @param bool $return Return the markup as a string instead of echoing.
     * @return string|null Markup when $return is true.
     * @since 1.2.9
     */
    public static function render_location_picker($return = false)
    {
        ob_start();
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
        <?php
        $markup = ob_get_clean();

        if ($return) {
            return $markup;
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput -- static trusted markup only
        return null;
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
     * Seed the checkout context from official WooCommerce state.
     *
     * 1. Default the customer's billing/shipping country to the store
     *    base country when empty (WC_Customer CRUD). The checkout no
     *    longer renders the country select, and WooCommerce's own
     *    posted-data/tax/gateway pipeline expects a country to exist.
     * 2. For logged-in customers, seed the session from the saved
     *    delivery profile so the saved pin + distance become the
     *    default without any interaction. The map and fields are also
     *    pre-filled from the same profile (see add_checkout_fields and
     *    the localized saved_address param).
     *
     * @since 1.0.0
     */
    public function load_saved_address()
    {
        if (!is_checkout()) {
            return;
        }

        if (WC()->customer) {
            $changed = false;
            if (function_exists('wc_get_base_location')) {
                $base = wc_get_base_location();
                $base_country = ($base && !empty($base->country)) ? $base->country : '';
                if ('' !== $base_country) {
                    if ('' === WC()->customer->get_billing_country()) {
                        WC()->customer->set_billing_country($base_country);
                        $changed = true;
                    }
                    if ('' === WC()->customer->get_shipping_country()) {
                        WC()->customer->set_shipping_country($base_country);
                        $changed = true;
                    }
                }
            }
            if ($changed && method_exists(WC()->customer, 'save')) {
                WC()->customer->save();
            }
        }

        if (is_user_logged_in() && WC()->session) {
            $profile = get_user_meta(get_current_user_id(), '_fxw_delivery_profile', true);
            if (!empty($profile) && !empty($profile['lat']) && !empty($profile['lng'])) {
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
