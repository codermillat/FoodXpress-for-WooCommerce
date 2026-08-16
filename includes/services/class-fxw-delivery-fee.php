<?php
/**
 * Delivery Fee Service
 *
 * Owns the WooCommerce cart-fee and shipping-label hooks for the
 * FoodXpress delivery fee + ETA minutes display. Split out of
 * FXW_Checkout in 1.2.0 to keep the checkout orchestrator/handler
 * files lean and to give the fee logic a clear single home.
 *
 * @since      1.2.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FXW_Delivery_Fee
 *
 * Backend concerns:
 *  - Hook: woocommerce_cart_calculate_fees — add per-order delivery fee
 *  - Filter: woocommerce_cart_shipping_method_full_label — append ETA minutes
 *
 * Self-registers at the bottom of the file; no external wiring required.
 *
 * @since 1.2.0
 */
class FXW_Delivery_Fee
{

    /**
     * Register cart-fee + label hooks.
     *
     * @since 1.2.0
     */
    public function __construct()
    {
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_delivery_fee'));
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'append_eta_to_label'), 10, 2);
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
}

new FXW_Delivery_Fee();
