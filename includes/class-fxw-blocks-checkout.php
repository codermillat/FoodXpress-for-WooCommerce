<?php
/**
 * Checkout — Block-based Checkout support.
 *
 * Brings the full FoodXpress delivery flow to the WooCommerce Checkout
 * *block* using only documented extension points:
 *
 *  - Additional Checkout Fields API (WC 8.9+): registers the exact
 *    address / landmark / instructions fields; the required field is
 *    validated through `woocommerce_validate_additional_field`, which
 *    also enforces the map pin + delivery radius via the shared
 *    `FXW_Checkout_Handler::get_zone_error()` check.
 *  - `the_content`: renders the same Step-1 map picker above the
 *    Checkout block (markup shared with classic checkout via
 *    `FXW_Checkout::render_location_picker()`; checkout.js boots on
 *    the #fxw-map element, so both checkouts behave identically).
 *  - `woocommerce_store_api_checkout_update_order_from_request`:
 *    applies the shared `apply_delivery_data_to_order()` persistence
 *    to block orders, mirroring classic-checkout order meta exactly,
 *    and auto-saves the delivery profile for logged-in customers.
 *
 * Stores on WooCommerce older than 8.9 keep full classic-shortcode
 * support; on those versions the block fields simply don't register.
 *
 * @since      1.2.9
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */

if (!defined('ABSPATH')) {
    exit;
}

class FXW_Blocks_Checkout
{

	/**
	 * Register blocks-checkout hooks.
	 *
	 * @since 1.2.9
	 */
	public function __construct()
	{
		add_action('woocommerce_init', array($this, 'register_fields'));
		add_action('woocommerce_validate_additional_field', array($this, 'validate_address_details'), 10, 3);
		add_filter('the_content', array($this, 'prepend_map_to_blocks_checkout'));
		add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'apply_delivery_data'), 10, 2);
	}

	/**
	 * Register the delivery fields via the documented Additional
	 * Checkout Fields API (no-op below WC 8.9).
	 *
	 * @since 1.2.9
	 */
	public function register_fields()
	{
		if (!function_exists('woocommerce_register_additional_checkout_field')) {
			return;
		}

		woocommerce_register_additional_checkout_field(array(
			'id' => 'foodxpress/address-details',
			'label' => __('House / Flat / Building No.', 'foodxpress'),
			'location' => 'address',
			'type' => 'text',
			'required' => true,
			'error_message' => __('Please enter your exact address (house / flat / building no.) so our delivery agent can find you.', 'foodxpress'),
			'attributes' => array(
				'placeholder' => __('e.g., Flat 4B, House 25, Tower A, Block 2', 'foodxpress'),
				'autocomplete' => 'street-address',
			),
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'foodxpress/landmark',
			'label' => __('Nearby Landmark (optional)', 'foodxpress'),
			'location' => 'address',
			'type' => 'text',
			'required' => false,
			'attributes' => array(
				'placeholder' => __('e.g., Near City Mall, Opposite Park', 'foodxpress'),
			),
		));

		woocommerce_register_additional_checkout_field(array(
			'id' => 'foodxpress/delivery-instructions',
			'label' => __('Delivery Instructions', 'foodxpress'),
			'location' => 'order',
			'type' => 'text',
			'required' => false,
			'attributes' => array(
				'placeholder' => __('e.g., Ring the bell twice, call before arriving...', 'foodxpress'),
			),
		));
	}

	/**
	 * Validate the required exact-address field on the block checkout.
	 * Beyond the value itself, enforce the same delivery-zone rules as
	 * classic checkout: pin present + inside the radius + store open.
	 *
	 * @param WP_Error $errors     Errors object.
	 * @param string   $field_key  Field key being validated.
	 * @param string   $field_value Posted value.
	 * @since 1.2.9
	 */
	public function validate_address_details($errors, $field_key, $field_value)
	{
		if ('foodxpress/address-details' !== $field_key) {
			return;
		}

		if (mb_strlen(trim((string) $field_value)) < 5) {
			$errors->add('foodxpress_address_details', __('Please enter your exact address (house / flat / building no.) so our delivery agent can find you.', 'foodxpress'));
			return;
		}

		$zone_error = FXW_Checkout_Handler::get_zone_error();
		if (null !== $zone_error) {
			$errors->add('foodxpress_delivery_zone', $zone_error);
		}
	}

	/**
	 * Render the Step-1 map picker above the Checkout block. The classic
	 * checkout renders the picker through its own billing-form hook, so
	 * this only fires on pages whose content contains the block.
	 *
	 * @param string $content Page content.
	 * @return string
	 * @since 1.2.9
	 */
	public function prepend_map_to_blocks_checkout($content)
	{
		if (is_admin() || !is_page() || !in_the_loop() || !did_action('wp_enqueue_scripts')) {
			return $content;
		}

		$post = get_post();
		if (!$post || !has_block('woocommerce/checkout', $post)) {
			return $content;
		}

		$prefix = '';

		// The Open/Closed switch controls order placement — when closed,
		// say so clearly on the blocks checkout too (the field validation
		// blocks placement server-side).
		if (!FXW_Checkout::is_store_open()) {
			$prefix .= '<div class="woocommerce-error fxw-store-closed-notice" role="alert">' . esc_html__('We are currently closed for deliveries. You can browse and fill your cart — ordering will be available as soon as we reopen.', 'foodxpress') . '</div>';
		}

		$options = get_option('fxw_settings');
		$api_key = isset($options['fxw_google_maps_api_key']) ? trim((string) $options['fxw_google_maps_api_key']) : '';
		if ('' === $api_key) {
			return $prefix . $content; // map is useless without a key; admin sees the config warning
		}

		return $prefix . FXW_Checkout::render_location_picker(true) . $content;
	}

	/**
	 * Apply the shared delivery-data persistence to block-checkout
	 * orders and auto-save the delivery profile for logged-in customers
	 * — mirroring the classic checkout exactly.
	 *
	 * @param WC_Order      $order   Order being created by the Store API.
	 * @param WP_REST_Request $request Checkout request.
	 * @since 1.2.9
	 */
	public function apply_delivery_data($order, $request)
	{
		if (!is_a($order, 'WC_Order')) {
			return;
		}

		$details = $this->get_field_value($order, $request, 'foodxpress/address-details', 'billing');
		if ('' === $details) {
			return; // not a FoodXpress flow (fields not registered / not used)
		}

		$landmark = $this->get_field_value($order, $request, 'foodxpress/landmark', 'billing');
		$instructions = $this->get_field_value($order, $request, 'foodxpress/delivery-instructions', 'other');

		FXW_Checkout_Handler::apply_delivery_data_to_order($order, $details, $landmark, $instructions);

		if ($order->get_user_id()) {
			FXW_Checkout_Handler::save_delivery_profile_for_user($order->get_user_id(), $details, $landmark, $instructions);
		}
	}

	/**
	 * Read an additional checkout field value for the order. Depending on
	 * the WooCommerce version and the Store API timing, the value may live
	 * on the order (via the CheckoutFields service or prefixed meta) or in
	 * the request body — check each documented location in turn.
	 *
	 * @param WC_Order        $order    Order.
	 * @param WP_REST_Request $request  Checkout request.
	 * @param string          $field_id Field ID (e.g. foodxpress/landmark).
	 * @param string          $group    'billing'|'shipping'|'other' per field location.
	 * @return string
	 * @since 1.2.9
	 */
	private function get_field_value($order, $request, $field_id, $group)
	{
		// 1. CheckoutFields service against the order (documented reader).
		if (class_exists('\Automattic\WooCommerce\Blocks\Package')
			&& class_exists('\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields')) {
			try {
				$fields = \Automattic\WooCommerce\Blocks\Package::container()
					->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
				$value = $fields->get_field_from_object($field_id, $order, $group);
				if (null !== $value && '' !== (string) $value) {
					return (string) $value;
				}
			} catch (\Throwable $e) {
				if (function_exists('wc_get_logger')) {
					wc_get_logger()->debug('blocks checkout field read failed: ' . $e->getMessage(), array('source' => 'foodxpress'));
				}
			}
		}

		// 2. Prefixed order meta (storage shape used by the API).
		foreach (array('_' . $field_id, $field_id) as $meta_key) {
			$meta = $order->get_meta($meta_key, true);
			if ('' !== (string) $meta) {
				return (string) $meta;
			}
		}

		// 3. Request body (documented Store API extension context).
		if (is_a($request, 'WP_REST_Request')) {
			$body = $request->get_params();
			foreach (array('additional_fields', 'extensions') as $section) {
				if (isset($body[$section][$field_id]) && is_scalar($body[$section][$field_id])) {
					return sanitize_text_field((string) $body[$section][$field_id]);
				}
			}
		}

		return '';
	}
}

new FXW_Blocks_Checkout();
