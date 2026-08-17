<?php
/**
 * Scheduled opening hours.
 *
 * Enterprise expectation for a food-delivery store: the toggle stays the
 * manual master switch (close NOW regardless of schedule), and an
 * optional per-day schedule additionally closes ordering outside opening
 * hours. Registered as an extra settings section on the FoodXpress tab
 * via the fxw_settings_register_extra_fields /
 * fxw_sanitize_settings_extra extension points, and checked through
 * FXW_Checkout::is_store_open() — the single open/closed source of truth.
 *
 * @since      1.2.12
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */

if (!defined('ABSPATH')) {
	exit;
}

class FXW_Store_Hours
{

	/**
	 * Register settings-section extension hooks.
	 *
	 * @since 1.2.12
	 */
	public function __construct()
	{
		add_action('fxw_settings_register_extra_fields', array($this, 'register_fields'));
		add_filter('fxw_sanitize_settings_extra', array($this, 'sanitize_hours'), 10, 2);
	}

	/**
	 * Default schedule: every day 09:00 – 22:00.
	 *
	 * @return array
	 * @since 1.2.12
	 */
	public static function defaults()
	{
		$days = array();
		for ($i = 0; $i < 7; $i++) {
			$days[$i] = array('open' => '09:00', 'close' => '22:00', 'closed' => '');
		}
		return $days;
	}

	/**
	 * Register the Opening Hours section on the FoodXpress settings tab.
	 *
	 * @since 1.2.12
	 */
	public function register_fields()
	{
		add_settings_section(
			'fxw_hours_section',
			__('Opening Hours', 'foodxpress'),
			array($this, 'render_section_description'),
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_hours_enabled',
			__('Enable Schedule', 'foodxpress'),
			array($this, 'render_enable_field'),
			'foodxpress-settings',
			'fxw_hours_section'
		);

		add_settings_field(
			'fxw_hours',
			__('Weekly Hours', 'foodxpress'),
			array($this, 'render_hours_field'),
			'foodxpress-settings',
			'fxw_hours_section'
		);
	}

	/** Describe the hours section. */
	public function render_section_description()
	{
		echo '<p>' . esc_html__('The admin-bar toggle closes deliveries immediately at any time; this schedule additionally pauses ordering outside your opening hours.', 'foodxpress') . '</p>';
	}

	/** Render the enable checkbox. */
	public function render_enable_field()
	{
		$options = get_option('fxw_settings');
		$enabled = !empty($options['fxw_hours_enabled']);
		echo '<label><input type="checkbox" name="fxw_settings[fxw_hours_enabled]" value="1"' . checked($enabled, true, false) . ' /> ' . esc_html__('Close ordering automatically outside the hours below', 'foodxpress') . '</label>';
	}

	/** Render the per-day open/close/closed grid. */
	public function render_hours_field()
	{
		$options = get_option('fxw_settings');
		$hours = isset($options['fxw_hours']) && is_array($options['fxw_hours']) ? wp_parse_args($options['fxw_hours'], self::defaults()) : self::defaults();

		$day_names = array(
			__('Sunday', 'foodxpress'), __('Monday', 'foodxpress'), __('Tuesday', 'foodxpress'),
			__('Wednesday', 'foodxpress'), __('Thursday', 'foodxpress'), __('Friday', 'foodxpress'), __('Saturday', 'foodxpress'),
		);

		echo '<table class="fxw-hours-table">';
		foreach ($day_names as $i => $name) {
			$day = isset($hours[$i]) && is_array($hours[$i]) ? wp_parse_args($hours[$i], array('open' => '09:00', 'close' => '22:00', 'closed' => '')) : array('open' => '09:00', 'close' => '22:00', 'closed' => '');
			printf(
				'<tr><th scope="row">%1$s</th><td><input type="time" name="fxw_settings[fxw_hours][%2$d][open]" value="%3$s" /> – <input type="time" name="fxw_settings[fxw_hours][%2$d][close]" value="%4$s" /> <label style="margin-left:10px;"><input type="checkbox" name="fxw_settings[fxw_hours][%2$d][closed]" value="1"%5$s /> %6$s</label></td></tr>',
				esc_html($name),
				$i,
				esc_attr($day['open']),
				esc_attr($day['close']),
				checked(!empty($day['closed']), true, false),
				esc_html__('Closed all day', 'foodxpress')
			);
		}
		echo '</table>';
	}

	/**
	 * Sanitize the hours settings (attached to fxw_sanitize_settings_extra).
	 *
	 * @param array $sanitized Sanitized settings so far.
	 * @param array $input     Raw input.
	 * @return array
	 * @since 1.2.12
	 */
	public function sanitize_hours($sanitized, $input)
	{
		$sanitized['fxw_hours_enabled'] = isset($input['fxw_hours_enabled']) ? 'yes' : 'no';

		if (!isset($input['fxw_hours']) || !is_array($input['fxw_hours'])) {
			return $sanitized;
		}

		$hours = array();
		for ($i = 0; $i < 7; $i++) {
			$raw = isset($input['fxw_hours'][$i]) && is_array($input['fxw_hours'][$i]) ? $input['fxw_hours'][$i] : array();
			$open = isset($raw['open']) ? sanitize_text_field(wp_unslash($raw['open'])) : '09:00';
			$close = isset($raw['close']) ? sanitize_text_field(wp_unslash($raw['close'])) : '22:00';
			if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $open)) {
				$open = '09:00';
			}
			if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $close)) {
				$close = '22:00';
			}
			$hours[$i] = array(
				'open' => $open,
				'close' => $close,
				'closed' => isset($raw['closed']) ? 'yes' : '',
			);
		}
		$sanitized['fxw_hours'] = $hours;

		return $sanitized;
	}

	/**
	 * Is "now" within the configured opening hours? Returns true when the
	 * schedule is disabled. Supports overnight spans (e.g. 18:00 – 02:00).
	 *
	 * @return bool
	 * @since 1.2.12
	 */
	public static function is_open_now()
	{
		$options = get_option('fxw_settings');
		if (empty($options['fxw_hours_enabled'])) {
			return true;
		}

		$hours = isset($options['fxw_hours']) && is_array($options['fxw_hours']) ? $options['fxw_hours'] : self::defaults();
		$now = current_time('timestamp');
		$day = (int) gmdate('w', $now);
		$minutes = (int) gmdate('G', $now) * 60 + (int) gmdate('i', $now);

		$today = isset($hours[$day]) && is_array($hours[$day]) ? $hours[$day] : array('open' => '09:00', 'close' => '22:00', 'closed' => '');
		if (!empty($today['closed'])) {
			// Maybe we are inside yesterday's overnight span.
			return self::in_overnight_span($hours, $day - 1, $minutes);
		}

		$open = self::to_minutes($today['open']);
		$close = self::to_minutes($today['close']);

		if ($open <= $close) {
			return $minutes >= $open && $minutes < $close;
		}

		// Overnight span: open until midnight, or inside yesterday's tail.
		return $minutes >= $open || self::in_overnight_span($hours, $day - 1, $minutes);
	}

	/**
	 * Is $minutes inside the closing tail of $day_index's overnight span?
	 *
	 * @param array $hours     Weekly hours.
	 * @param int   $day_index Day index (may wrap).
	 * @param int   $minutes   Minutes since midnight.
	 * @return bool
	 * @since 1.2.12
	 */
	private static function in_overnight_span($hours, $day_index, $minutes)
	{
		$day_index = (($day_index % 7) + 7) % 7;
		$day = isset($hours[$day_index]) && is_array($hours[$day_index]) ? $hours[$day_index] : null;
		if (!$day || !empty($day['closed'])) {
			return false;
		}
		$open = self::to_minutes($day['open']);
		$close = self::to_minutes($day['close']);
		// Only an overnight span (close < open) reaches past midnight.
		return $close < $open && $minutes < $close;
	}

	/**
	 * "HH:MM" to minutes since midnight.
	 *
	 * @param string $time Time string.
	 * @return int
	 * @since 1.2.12
	 */
	private static function to_minutes($time)
	{
		$parts = explode(':', (string) $time);
		$h = isset($parts[0]) ? (int) $parts[0] : 0;
		$m = isset($parts[1]) ? (int) $parts[1] : 0;
		return $h * 60 + $m;
	}

	/**
	 * Human hint for the closed notices: when the schedule says we reopen
	 * today or on the next open day. Empty string when unknown.
	 *
	 * @return string
	 * @since 1.2.12
	 */
	public static function reopen_hint()
	{
		$options = get_option('fxw_settings');
		if (empty($options['fxw_hours_enabled'])) {
			return '';
		}

		$hours = isset($options['fxw_hours']) && is_array($options['fxw_hours']) ? $options['fxw_hours'] : self::defaults();
		$now = current_time('timestamp');
		$day = (int) gmdate('w', $now);
		$minutes = (int) gmdate('G', $now) * 60 + (int) gmdate('i', $now);

		$day_names = array(__('Sunday', 'foodxpress'), __('Monday', 'foodxpress'), __('Tuesday', 'foodxpress'), __('Wednesday', 'foodxpress'), __('Thursday', 'foodxpress'), __('Friday', 'foodxpress'), __('Saturday', 'foodxpress'));

		// Still open later today?
		$today = isset($hours[$day]) && is_array($hours[$day]) ? $hours[$day] : null;
		if ($today && empty($today['closed']) && self::to_minutes($today['open']) > $minutes) {
			return sprintf(__('We open today at %s.', 'foodxpress'), esc_html($today['open']));
		}

		// Next open day.
		for ($offset = 1; $offset <= 7; $offset++) {
			$idx = ($day + $offset) % 7;
			$candidate = isset($hours[$idx]) && is_array($hours[$idx]) ? $hours[$idx] : null;
			if ($candidate && empty($candidate['closed'])) {
				return sprintf(__('We reopen %s at %s.', 'foodxpress'), $day_names[$idx], esc_html($candidate['open']));
			}
		}
		return '';
	}
}

new FXW_Store_Hours();
