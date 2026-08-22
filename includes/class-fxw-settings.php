<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the settings page for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Settings
{

		/** Initialize the class and set its properties. */
	public function __construct()
	{
		// Native tab under WooCommerce → Settings; shop managers can configure delivery (1.2.10).
		add_filter('woocommerce_settings_tabs_array', array($this, 'register_wc_settings_tab'), 50);
		add_action('woocommerce_settings_foodxpress', array($this, 'render_wc_settings_tab'));
		add_action('woocommerce_update_options_foodxpress', array($this, 'save_wc_settings_tab'));
		add_action('admin_init', array($this, 'register_settings'));
	}

	/** Add the FoodXpress tab to WooCommerce → Settings. */
	public function register_wc_settings_tab($tabs)
	{
		$tabs['foodxpress'] = __('FoodXpress', 'foodxpress');
		return $tabs;
	}

	/** Render tab content; WooCommerce provides form, nonce, save button. */
	public function render_wc_settings_tab()
	{
		echo '<table class="form-table">';
		do_settings_sections('foodxpress-settings');
		echo '</table>';
	}

	/** Save on WooCommerce tab save; explicit cap check + WC nonce both required (1.2.16). */
	public function save_wc_settings_tab()
	{
		if (!isset($_POST['fxw_settings'])) {
			return;
		}
		if (!current_user_can('manage_woocommerce')) {
			return;
		}
		$input = (array) wc_clean(wp_unslash($_POST['fxw_settings']));
		update_option('fxw_settings', $this->sanitize_settings($input));
	}

	/** See method name. */
	public function register_settings()
	{
		register_setting('fxw_settings_group', 'fxw_settings', array(
			'sanitize_callback' => array($this, 'sanitize_settings'),
			'capability' => 'manage_woocommerce',
		));

		// General Settings Section
		add_settings_section(
			'fxw_general_settings_section',
			__('General Settings', 'foodxpress'),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_restaurant_address',
			__('Restaurant Address', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array('id' => 'fxw_restaurant_address')
		);

		add_settings_field(
			'fxw_restaurant_latlng',
			__('Restaurant Coordinates (lat, lng)', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array(
				'id' => 'fxw_restaurant_latlng',
				'description' => __('Optional. Exact "lat, lng" used for fee & zone checks, e.g. 40.7128, -74.0060. Overrides the address above.', 'foodxpress')
			)
		);

		add_settings_field(
			'fxw_preparation_time',
			__('Default Preparation Time (minutes)', 'foodxpress'),
			array($this, 'render_prep_time_field'),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array(
				'id' => 'fxw_preparation_time',
				'default' => 20,
				'description' => __('How long your kitchen needs to prepare a typical order. FoodXpress adds this to travel time to show customers an estimated arrival, e.g. 20 min cooking + 15 min ride = “Arrives in ~35 minutes”. Most restaurants keep 15–30.', 'foodxpress'),
			)
		);

		// Google Maps key fields moved to the Map Provider section (1.4.0)
		// so every provider-related field lives together under the
		// provider dropdown.
		do_action('fxw_settings_register_google_fields');

		// Delivery Fee Settings Section
		// Base fee + per-km rows moved to FXW_Pricing (1.4.0) so the fee
		// structure choice controls their visibility in one place. The
		// section header itself is registered by FXW_Pricing.
		// (The "extra cart fee" option was removed in 1.3.0: it duplicated
		// the shipping-method charge whenever a non-FoodXpress rate was
		// selected, so customers paid the delivery cost twice. The Shipping
		// Method API is now the single place the charge is added.)
		// Uninstall opt-in (1.2.16).
		add_settings_field('fxw_remove_on_uninstall', __('Remove Data on Uninstall', 'foodxpress'), array($this, 'render_checkbox_field'), 'foodxpress-settings', 'fxw_general_settings_section', array('id' => 'fxw_remove_on_uninstall', 'label' => __('Delete all FoodXpress data (settings, saved profiles, the delivery_boy role) on uninstall. Order meta is never deleted either way.', 'foodxpress')));

		// Delivery Zone Settings Section
		add_settings_section(
			'fxw_delivery_zone_settings_section',
			__('Delivery Zone Settings', 'foodxpress'),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_delivery_zone_radius',
			__('Delivery Radius (km)', 'foodxpress'),
			array($this, 'render_number_field'),
			'foodxpress-settings',
			'fxw_delivery_zone_settings_section',
			array('id' => 'fxw_delivery_zone_radius', 'default' => 10, 'step' => 0.1)
		);

		add_settings_field(
			'fxw_auto_set_assigned_status',
			__('Auto-Set Status on Assign', 'foodxpress'),
			array($this, 'render_checkbox_field'),
			'foodxpress-settings',
			'fxw_delivery_zone_settings_section',
			array('id' => 'fxw_auto_set_assigned_status', 'label' => __('Automatically set order status to "Assigned" when a delivery boy is assigned from the order edit page', 'foodxpress'))
		);

		// Receipt Branding Settings Section
		add_settings_section(
			'fxw_receipt_branding_section',
			__('Receipt Branding', 'foodxpress'),
			array($this, 'render_receipt_branding_description'),
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_receipt_logo',
			__('Receipt Logo', 'foodxpress'),
			array($this, 'render_image_upload_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_logo', 'description' => __('Recommended: 200x80px, PNG or JPG', 'foodxpress'))
		);

		add_settings_field(
			'fxw_receipt_restaurant_name',
			__('Restaurant Name (for Receipt)', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_restaurant_name', 'description' => __('Leave empty to use site name', 'foodxpress'))
		);

		add_settings_field(
			'fxw_receipt_address',
			__('Restaurant Address (for Receipt)', 'foodxpress'),
			array($this, 'render_textarea_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_address', 'description' => __('Full address as it appears on receipts', 'foodxpress'))
		);

		add_settings_field(
			'fxw_receipt_phone',
			__('Restaurant Phone (for Receipt)', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_phone')
		);

		add_settings_field(
			'fxw_receipt_tagline',
			__('Receipt Tagline', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_tagline', 'description' => __('Optional tagline shown below logo (e.g., "Delicious Food, Delivered Fast!")', 'foodxpress'))
		);

		add_settings_field(
			'fxw_receipt_footer_message',
			__('Receipt Footer Message', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_receipt_branding_section',
			array('id' => 'fxw_receipt_footer_message', 'default' => 'Thank You! Have a great day!')
		);

		// Sibling sections (Pricing Rules, Opening Hours) register here — do_settings_sections() snapshots sections.
		do_action('fxw_settings_register_extra_fields');
	}

	/** Describe the receipt branding section. */
	public function render_receipt_branding_description()
	{
		?>
		<p class="description"><?php esc_html_e('Customize your delivery receipts with your restaurant branding. These settings are used only for printed receipts.', 'foodxpress'); ?></p>
		<?php
	}

	/** Render a generic text field. */
	public function render_text_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$value = isset($options[$id]) ? $options[$id] : $default;
		$description = isset($args['description']) ? $args['description'] : '';
		$dep = isset($args['show_when']) ? ' data-fxw-show-when="' . esc_attr($args['show_when']) . '"' : '';
		?>
		<input type="text" name="fxw_settings[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($value); ?>"
			class="regular-text"<?php echo $dep; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute template ?>>
		<?php if ($description) : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif;
	}

	/**
	 * Render an API-key field: password-masked with a show/hide toggle,
	 * optionally bound to a controlling choice via `show_when`.
	 *
	 * @since 1.4.0
	 */
	public function render_key_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		$dep = isset($args['show_when']) ? ' data-fxw-show-when="' . esc_attr($args['show_when']) . '"' : '';
		?>
		<div class="fxw-key-wrap"<?php echo $dep; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute template ?>>
			<input type="password" name="fxw_settings[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($value); ?>"
				class="regular-text fxw-key-input" autocomplete="new-password">
			<button type="button" class="button fxw-key-toggle"
				data-show="<?php esc_attr_e('Show', 'foodxpress'); ?>"
				data-hide="<?php esc_attr_e('Hide', 'foodxpress'); ?>"><?php esc_html_e('Show', 'foodxpress'); ?></button>
			<?php if ($description) : ?>
				<p class="description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render a textarea field. */
	public function render_textarea_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		?>
		<textarea name="fxw_settings[<?php echo esc_attr($id); ?>]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
		<?php if ($description) : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif;
	}

	/** Render an image-upload field. */
	public function render_image_upload_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		
		// Enqueue media scripts
		wp_enqueue_media();
		?>
		<div class="fxw-image-upload-wrapper">
			<input type="hidden" name="fxw_settings[<?php echo esc_attr($id); ?>]" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>">
			
			<div id="<?php echo esc_attr($id); ?>_preview" style="margin-bottom: 10px;">
				<?php if ($value) : ?>
					<img src="<?php echo esc_url($value); ?>" style="max-width: 200px; max-height: 80px; display: block; margin-bottom: 5px;">
				<?php endif; ?>
			</div>
			
			<button type="button" class="button fxw-upload-image-btn" data-target="<?php echo esc_attr($id); ?>">
				<?php esc_html_e('Upload Logo', 'foodxpress'); ?>
			</button>
			
			<?php if ($value) : ?>
				<button type="button" class="button fxw-remove-image-btn" data-target="<?php echo esc_attr($id); ?>">
					<?php esc_html_e('Remove Logo', 'foodxpress'); ?>
				</button>
			<?php endif; ?>
			
			<?php if ($description) : ?>
				<p class="description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		</div>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.fxw-upload-image-btn').on('click', function(e) {
				e.preventDefault();
				var targetId = $(this).data('target');
				var frame = wp.media({
					title: '<?php esc_html_e('Select or Upload Logo', 'foodxpress'); ?>',
					button: { text: '<?php esc_html_e('Use this logo', 'foodxpress'); ?>' },
					library: { type: 'image' },
					multiple: false
				});
				
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#' + targetId).val(attachment.url);
					$('#' + targetId + '_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 80px; display: block; margin-bottom: 5px;">');
					$('.fxw-upload-image-btn[data-target="' + targetId + '"]').after('<button type="button" class="button fxw-remove-image-btn" data-target="' + targetId + '"><?php esc_html_e('Remove Logo', 'foodxpress'); ?></button>');
				});
				
				frame.open();
			});
			
			$(document).on('click', '.fxw-remove-image-btn', function(e) {
				e.preventDefault();
				var targetId = $(this).data('target');
				$('#' + targetId).val('');
				$('#' + targetId + '_preview').html('');
				$(this).remove();
			});
		});
		</script>
		<?php
	}

	/** Render a checkbox field. */
	public function render_checkbox_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$label = isset($args['label']) ? $args['label'] : '';
		$value = isset($options[$id]) ? $options[$id] : '';
		$checked = in_array($value, array('yes', 'true', 1, '1'), true);
		?>
		<label>
			<input type="checkbox" name="fxw_settings[<?php echo esc_attr($id); ?>]" value="yes"
				<?php checked($checked); ?>>
			<?php echo esc_html($label); ?>
		</label>
		<?php
	}

	/** Render a number field. */
	public function render_number_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$step = isset($args['step']) ? $args['step'] : '1';
		$value = isset($options[$id]) ? $options[$id] : $default;
		?>
		<input type="number" step="<?php echo esc_attr($step); ?>" name="fxw_settings[<?php echo esc_attr($id); ?>]"
			value="<?php echo esc_attr($value); ?>" class="small-text">
		<?php
	}

	/**
	 * Number field with a plain-language description.
	 *
	 * Used for settings a non-technical store owner must understand at a
	 * glance (e.g. Default Preparation Time).
	 *
	 * @since 1.4.0
	 */
	public function render_prep_time_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$value = isset($options[$id]) ? $options[$id] : $default;
		$description = isset($args['description']) ? $args['description'] : '';
		?>
		<input type="number" min="0" step="1" name="fxw_settings[<?php echo esc_attr($id); ?>]"
			value="<?php echo esc_attr($value); ?>" class="small-text"> <?php esc_html_e('minutes', 'foodxpress'); ?>
		<?php if ($description) : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif;
	}

	/** Sanitize settings before saving. */
	public function sanitize_settings($input)
	{
		$existing = get_option('fxw_settings', array());
		if (!is_array($existing)) {
			$existing = array();
		}
		$sanitized = array();

		// General settings
		if (isset($input['fxw_google_maps_api_key'])) {
			$sanitized['fxw_google_maps_api_key'] = sanitize_text_field($input['fxw_google_maps_api_key']);
		}

		if (isset($input['fxw_google_maps_server_key'])) {
			$sanitized['fxw_google_maps_server_key'] = sanitize_text_field($input['fxw_google_maps_server_key']);
		}

		if (isset($input['fxw_google_maps_map_id'])) {
			$sanitized['fxw_google_maps_map_id'] = sanitize_text_field($input['fxw_google_maps_map_id']);
		}

		if (isset($input['fxw_restaurant_address'])) {
			$sanitized['fxw_restaurant_address'] = sanitize_text_field($input['fxw_restaurant_address']);
		}

		if (isset($input['fxw_restaurant_latlng'])) {
			$raw = sanitize_text_field($input['fxw_restaurant_latlng']);
			$sanitized['fxw_restaurant_latlng'] = preg_match('/^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/', $raw) ? $raw : '';
		}

		if (isset($input['fxw_preparation_time'])) {
			$sanitized['fxw_preparation_time'] = absint($input['fxw_preparation_time']);
		}

		// Delivery fee settings
		if (isset($input['fxw_delivery_fee_base'])) {
			$sanitized['fxw_delivery_fee_base'] = floatval($input['fxw_delivery_fee_base']);
		}

		if (isset($input['fxw_delivery_fee_per_km'])) {
			$sanitized['fxw_delivery_fee_per_km'] = floatval($input['fxw_delivery_fee_per_km']);
		}

		// Delivery zone settings
		if (isset($input['fxw_delivery_zone_radius'])) {
			// Fractional-km radii (1.2.16 — was clamped to int by absint).
			$radius = (float) $input['fxw_delivery_zone_radius'];
			$sanitized['fxw_delivery_zone_radius'] = $radius < 0 ? 0 : $radius;
		}

		if (isset($input['fxw_auto_set_assigned_status'])) {
			$sanitized['fxw_auto_set_assigned_status'] = 'yes';
		} else {
			$sanitized['fxw_auto_set_assigned_status'] = 'no';
		}

		// fxw_enable_extra_delivery_fee is intentionally NOT carried over
		// (removed in 1.3.0 — it double-charged delivery). Dropping it here
		// clears the stored value on the next save.

		// Extra settings (e.g. opening hours, uninstall opt-in) registered by other classes
		$sanitized = apply_filters('fxw_sanitize_settings_extra', $sanitized, $input);

		// Receipt branding settings
		if (isset($input['fxw_receipt_logo'])) {
			$sanitized['fxw_receipt_logo'] = esc_url_raw($input['fxw_receipt_logo']);
		}

		if (isset($input['fxw_receipt_restaurant_name'])) {
			$sanitized['fxw_receipt_restaurant_name'] = sanitize_text_field($input['fxw_receipt_restaurant_name']);
		}

		if (isset($input['fxw_receipt_address'])) {
			$sanitized['fxw_receipt_address'] = sanitize_textarea_field($input['fxw_receipt_address']);
		}

		if (isset($input['fxw_receipt_phone'])) {
			$sanitized['fxw_receipt_phone'] = sanitize_text_field($input['fxw_receipt_phone']);
		}

		if (isset($input['fxw_receipt_tagline'])) {
			$sanitized['fxw_receipt_tagline'] = sanitize_text_field($input['fxw_receipt_tagline']);
		}

		if (isset($input['fxw_receipt_footer_message'])) {
			$sanitized['fxw_receipt_footer_message'] = sanitize_text_field($input['fxw_receipt_footer_message']);
		}

		// Preserve fxw_is_open (admin-bar toggle — not in this form).
		if (isset($existing['fxw_is_open']) && !isset($sanitized['fxw_is_open'])) {
			$sanitized['fxw_is_open'] = $existing['fxw_is_open'];
		}

		return $sanitized;
	}
}

new FXW_Settings();

