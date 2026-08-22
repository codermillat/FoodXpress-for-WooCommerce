<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * FXW_Settings_Maps — Map provider settings.
 *
 * Registers the Map Provider section on the FoodXpress settings tab and
 * sanitises its three fields. Lives in its own sibling class because
 * FXW_Settings sits exactly at the 500-LOC cap; hooks onto the same two
 * documented extension points the rest of the settings code uses
 * (`fxw_settings_register_extra_fields` + `fxw_sanitize_settings_extra`),
 * so FXW_Settings stays the single owner of the tab.
 *
 * @since      1.3.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Settings_Maps
{

	/**
	 * Wire the section + sanitiser.
	 *
	 * @since 1.3.0
	 */
	public function __construct()
	{
		add_action('fxw_settings_register_extra_fields', array($this, 'register_fields'));
		// Google's three key fields live in THIS section too (right under
		// the provider dropdown) — FXW_Settings fires this hook at the point
		// where its General section used to hold them.
		add_action('fxw_settings_register_google_fields', array($this, 'register_google_fields'));
		// Priority 50: after the core sanitize, before the range-check and
		// preserve-keys passes in FXW_Settings_Extra (100 / 999).
		add_filter('fxw_sanitize_settings_extra', array($this, 'sanitize'), 50, 2);
	}

	/**
	 * Register Google's key fields inside the Map Provider section.
	 *
	 * Shown only while "Google Maps" is the selected provider
	 * (`data-fxw-show-when` contract consumed by admin-settings.js).
	 *
	 * @since 1.4.0
	 */
	public function register_google_fields()
	{
		add_settings_field(
			'fxw_google_maps_api_key',
			__('Google Maps API Key', 'foodxpress'),
			array($this, 'render_google_key_field'),
			'foodxpress-settings',
			'fxw_map_provider_section'
		);

		add_settings_field(
			'fxw_google_maps_server_key',
			__('Google Server Key (optional)', 'foodxpress'),
			array($this, 'render_text_field_maps'),
			'foodxpress-settings',
			'fxw_map_provider_section',
			array(
				'id' => 'fxw_google_maps_server_key',
				'description' => __('Optional separate key for Geocoding/Distance Matrix — lets you restrict the main key to your site domain and this one to your server IP. Falls back to the main key when empty.', 'foodxpress'),
				'show_when' => 'provider:google',
			)
		);

		add_settings_field(
			'fxw_google_maps_map_id',
			__('Google Map ID (optional)', 'foodxpress'),
			array($this, 'render_text_field_maps'),
			'foodxpress-settings',
			'fxw_map_provider_section',
			array(
				'id' => 'fxw_google_maps_map_id',
				'description' => __('Optional Map ID from your Cloud Console (enables Advanced Markers). Leave empty for classic markers.', 'foodxpress'),
				'show_when' => 'provider:google',
			)
		);
	}

	/**
	 * Google Maps API key: masked, shown only for the google provider.
	 *
	 * @since 1.4.0
	 */
	public function render_google_key_field()
	{
		$options = get_option('fxw_settings');
		$value = isset($options['fxw_google_maps_api_key']) ? $options['fxw_google_maps_api_key'] : '';
		?>
		<div class="fxw-key-wrap" data-fxw-show-when="provider:google">
			<input type="password" name="fxw_settings[fxw_google_maps_api_key]" value="<?php echo esc_attr($value); ?>"
				class="regular-text fxw-key-input" autocomplete="new-password">
			<button type="button" class="button fxw-key-toggle"
				data-show="<?php esc_attr_e('Show', 'foodxpress'); ?>"
				data-hide="<?php esc_attr_e('Hide', 'foodxpress'); ?>"><?php esc_html_e('Show', 'foodxpress'); ?></button>
			<p class="description"><?php esc_html_e('Paste your Google Maps JavaScript API key. Only needed when Google Maps is selected above — OpenStreetMap needs no key at all.', 'foodxpress'); ?></p>
		</div>
		<?php
	}

	/** Text field with conditional visibility support (maps section). */
	public function render_text_field_maps($args)
	{
		$options = get_option('fxw_settings');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : '';
		$description = isset($args['description']) ? $args['description'] : '';
		$dep = isset($args['show_when']) ? ' data-fxw-show-when="' . esc_attr($args['show_when']) . '"' : '';
		printf(
			'<input type="text" name="fxw_settings[%1$s]" value="%2$s" class="regular-text"%3$s />%4$s',
			esc_attr($id),
			esc_attr($value),
			$dep // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute template
			,
			$description ? '<p class="description">' . esc_html($description) . '</p>' : ''
		);
	}

	/**
	 * Register the Map Provider section.
	 *
	 * Field rows carry `data-fxw-depends` attributes read by
	 * FXW_Settings_Ui's script: a row is visible only while its provider
	 * is selected (`provider:<id>`), or hidden for providers that don't
	 * need it (`hide-for-provider:<id>,<id>`).
	 *
	 * @since 1.3.0
	 */
	public function register_fields()
	{
		add_settings_section(
			'fxw_map_provider_section',
			__('Map Provider', 'foodxpress'),
			array($this, 'render_section_description'),
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_map_provider',
			__('Map Provider', 'foodxpress'),
			array($this, 'render_provider_field'),
			'foodxpress-settings',
			'fxw_map_provider_section',
			array('row_class' => 'fxw-row-controls-provider')
		);

		add_settings_field(
			'fxw_map_provider_key',
			__('Provider API Key', 'foodxpress'),
			array($this, 'render_key_field'),
			'foodxpress-settings',
			'fxw_map_provider_section',
			array('row_class' => 'fxw-row-shows-provider-key')
		);

		add_settings_field(
			'fxw_road_distance_factor',
			__('Road Distance Factor', 'foodxpress'),
			array($this, 'render_factor_field'),
			'foodxpress-settings',
			'fxw_map_provider_section',
			array('row_class' => 'fxw-row-hide-has-routing')
		);
	}

	/** Describe the section. */
	public function render_section_description()
	{
		echo '<p>' . esc_html__('Choose which mapping service powers the checkout location picker, address lookup and distance calculation. OpenStreetMap works with no key and no billing account; the others need a free key.', 'foodxpress') . '</p>';
	}

	/**
	 * Provider dropdown, with each provider's free-tier terms listed
	 * beneath so the choice can be made without leaving the page.
	 * `data-fxw-provider-select` marks it as THE controlling select for
	 * the conditional rows in this section.
	 *
	 * @since 1.3.0
	 */
	public function render_provider_field()
	{
		$active = FXW_Map_Providers::active_id();
		$all = FXW_Map_Providers::all();

		echo '<select id="fxw_map_provider" name="fxw_settings[fxw_map_provider]" data-fxw-provider-select>';
		foreach ($all as $id => $provider) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr($id),
				selected($active, $id, false),
				esc_html($provider['label'])
			);
		}
		echo '</select>';

		echo '<ul class="fxw-provider-notes" style="margin-top:8px;">';
		foreach ($all as $id => $provider) {
			$caps = array();
			if (in_array('routing', $provider['capabilities'], true)) {
				$caps[] = __('road distance', 'foodxpress');
			} else {
				$caps[] = __('straight-line distance only', 'foodxpress');
			}

			$signup = '';
			if (!empty($provider['signup_url'])) {
				$signup = sprintf(
					' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url($provider['signup_url']),
					esc_html__('Get a key', 'foodxpress')
				);
			}

			printf(
				'<li class="fxw-provider-note fxw-provider-note--%1$s"%2$s><strong>%3$s</strong> — %4$s <em>(%5$s)</em>%6$s</li>',
				esc_attr($id),
				$active === $id ? '' : ' style="display:none;"',
				esc_html($provider['label']),
				esc_html($provider['free_tier']),
				esc_html(implode(', ', $caps)),
				$signup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url/esc_html above
			);
		}
		echo '</ul>';
	}

	/**
	 * Single key field used by every provider except Google (which keeps
	 * its three historical fields) and OpenStreetMap (which needs none).
	 * Shown only for providers whose key this is.
	 *
	 * @since 1.3.0
	 */
	public function render_key_field()
	{
		$options = get_option('fxw_settings');
		$value = isset($options['fxw_map_provider_key']) ? (string) $options['fxw_map_provider_key'] : '';

		printf(
			'<input type="text" id="fxw_map_provider_key" name="fxw_settings[fxw_map_provider_key]" value="%s" class="regular-text fxw-key-input" autocomplete="off" data-fxw-show-when="provider:maptiler,geoapify" data-fxw-hide-when="provider:osm,google" />',
			esc_attr($value)
		);

		echo '<p class="description">' . esc_html__('Paste the API key from your MapTiler or Geoapify account. Not needed for OpenStreetMap; Google uses its own fields below.', 'foodxpress') . '</p>';
	}

	/**
	 * Road-correction factor, only meaningful for providers without
	 * routing (MapTiler/Geoapify without routing add-ons).
	 *
	 * @since 1.3.0
	 */
	public function render_factor_field()
	{
		printf(
			'<input type="number" step="0.05" min="1" max="3" id="fxw_road_distance_factor" name="fxw_settings[fxw_road_distance_factor]" value="%s" class="small-text" data-fxw-show-when="provider:maptiler,geoapify" />',
			esc_attr((string) FXW_Map_Providers::road_factor())
		);

		echo '<p class="description">' . esc_html__('Only for providers that cannot measure real road distance: straight-line distance is multiplied by this to approximate a driving route. 1.3 suits most towns and cities.', 'foodxpress') . '</p>';
	}

	/**
	 * Sanitise the three fields.
	 *
	 * @param array $sanitized Sanitized settings so far.
	 * @param array $input     Raw input.
	 * @return array
	 * @since 1.3.0
	 */
	public function sanitize($sanitized, $input)
	{
		if (!is_array($sanitized)) {
			$sanitized = array();
		}

		if (isset($input['fxw_map_provider'])) {
			$candidate = sanitize_key(wp_unslash($input['fxw_map_provider']));
			$all = FXW_Map_Providers::all();
			if (isset($all[$candidate])) {
				$sanitized['fxw_map_provider'] = $candidate;
			} else {
				set_transient('fxw_admin_notice', __('Unknown map provider submitted; previous provider kept.', 'foodxpress'), 30);
			}
		}

		if (isset($input['fxw_map_provider_key'])) {
			$sanitized['fxw_map_provider_key'] = sanitize_text_field(wp_unslash($input['fxw_map_provider_key']));
		}

		if (isset($input['fxw_road_distance_factor'])) {
			$factor = (float) wp_unslash($input['fxw_road_distance_factor']);
			if ($factor >= 1.0 && $factor <= 3.0) {
				$sanitized['fxw_road_distance_factor'] = $factor;
			} else {
				set_transient('fxw_admin_notice', __('Road Distance Factor must be between 1.0 and 3.0; previous value kept.', 'foodxpress'), 30);
			}
		}

		return $sanitized;
	}
}

new FXW_Settings_Maps();
