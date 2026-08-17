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
		// Native tab under WooCommerce → Settings via documented hooks,
		// gated by manage_woocommerce like every other WC settings tab
		// (shop managers can configure delivery). Moved from a standalone
		// manage_options page in 1.2.10.
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

	/** Save on WooCommerce tab save; WC verified nonce + capability. */
	public function save_wc_settings_tab()
	{
		if (!isset($_POST['fxw_settings'])) {
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
			'fxw_google_maps_api_key',
			__('Google Maps API Key', 'foodxpress'),
			array($this, 'render_text_field'),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array('id' => 'fxw_google_maps_api_key')
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
			array($this, 'render_number_field'),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array('id' => 'fxw_preparation_time', 'default' => 20)
		);

		// Delivery Fee Settings Section
		add_settings_section(
			'fxw_delivery_fee_settings_section',
			__('Delivery Fee Settings', 'foodxpress'),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_delivery_fee_base',
			__('Base Fee', 'foodxpress'),
			array($this, 'render_number_field'),
			'foodxpress-settings',
			'fxw_delivery_fee_settings_section',
			array('id' => 'fxw_delivery_fee_base', 'default' => 5.00, 'step' => 0.01)
		);

		add_settings_field(
			'fxw_delivery_fee_per_km',
			__('Fee Per Kilometer', 'foodxpress'),
			array($this, 'render_number_field'),
			'foodxpress-settings',
			'fxw_delivery_fee_settings_section',
			array('id' => 'fxw_delivery_fee_per_km', 'default' => 1.50, 'step' => 0.01)
		);

		add_settings_field(
			'fxw_enable_extra_delivery_fee',
			__('Enable Extra Delivery Fee (as Cart Fee)', 'foodxpress'),
			array($this, 'render_checkbox_field'),
			'foodxpress-settings',
			'fxw_delivery_fee_settings_section',
			array('id' => 'fxw_enable_extra_delivery_fee', 'label' => __('Add delivery fee as a separate cart fee when FoodXpress shipping is not selected', 'foodxpress'))
		);

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
			array('id' => 'fxw_delivery_zone_radius', 'default' => 10)
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
	}

	/**
	 * Render receipt branding section description.
	 *
	 * @since   1.0.0
	 */
	public function render_receipt_branding_description()
	{
		?>
		<p class="description"><?php esc_html_e('Customize your delivery receipts with your restaurant branding. These settings are used only for printed receipts.', 'foodxpress'); ?></p>
		<?php
	}

	/**
	 * Render a generic text field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
	public function render_text_field($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$default = isset($args['default']) ? $args['default'] : '';
		$value = isset($options[$id]) ? $options[$id] : $default;
		$description = isset($args['description']) ? $args['description'] : '';
		?>
		<input type="text" name="fxw_settings[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($value); ?>"
			class="regular-text">
		<?php if ($description) : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif;
	}

	/**
	 * Render a textarea field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
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

	/**
	 * Render an image upload field using WordPress Media Library.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
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

	/**
	 * Render a checkbox field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.1.0
	 */
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

	/**
	 * Render a generic number field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
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
	 * Sanitize settings before saving.
	 *
	 * @param   array   $input    The input values to sanitize.
	 * @return  array   The sanitized values.
	 * @since   1.0.0
	 */
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
			$sanitized['fxw_delivery_zone_radius'] = absint($input['fxw_delivery_zone_radius']);
		}

		if (isset($input['fxw_auto_set_assigned_status'])) {
			$sanitized['fxw_auto_set_assigned_status'] = 'yes';
		} else {
			$sanitized['fxw_auto_set_assigned_status'] = 'no';
		}

		if (isset($input['fxw_enable_extra_delivery_fee'])) {
			$sanitized['fxw_enable_extra_delivery_fee'] = 'yes';
		} else {
			$sanitized['fxw_enable_extra_delivery_fee'] = 'no';
		}

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

		// Preserve settings not in this form (fxw_is_open, fxw_enable_extra_delivery_fee, fxw_auto_set_assigned_status, etc.)
		$preserve_keys = array('fxw_is_open', 'fxw_enable_extra_delivery_fee', 'fxw_auto_set_assigned_status');
		foreach ($preserve_keys as $key) {
			if (isset($existing[$key]) && !isset($sanitized[$key])) {
				$sanitized[$key] = $existing[$key];
			}
		}

		return $sanitized;
	}
}

new FXW_Settings();

