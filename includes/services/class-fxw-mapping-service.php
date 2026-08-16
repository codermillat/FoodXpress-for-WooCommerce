<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Handles all communication with the mapping service API.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Mapping_Service
{

	/**
	 * The API key for the mapping service.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_key    The API key.
	 */
	private $api_key;

	/**
	 * The base URL for the mapping service API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $base_url    The base URL.
	 */
	private $base_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
	private $geocode_url = 'https://maps.googleapis.com/maps/api/geocode/json';

	/**
	 * Cache lifetime for geocoding results (addresses rarely move).
	 *
	 * @since 1.2.1
	 */
	const GEOCODE_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Cache lifetime for distance results (traffic-dependent, keep short).
	 *
	 * @since 1.2.1
	 */
	const DISTANCE_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		$options = get_option('fxw_settings');
		$this->api_key = isset($options['fxw_google_maps_api_key']) ? $options['fxw_google_maps_api_key'] : '';
	}

	/**
	 * Get the distance between two points.
	 *
	 * @param   string  $origin         The origin address.
	 * @param   string  $destination    The destination address.
	 * @return  array|WP_Error          The distance and duration, or an error.
	 * @since   1.0.0
	 */
	public function get_distance($origin, $destination)
	{
		if (empty($this->api_key)) {
			return new WP_Error('api_key_missing', __('Google Maps API key is missing.', 'foodxpress'));
		}

		$orig = $this->normalize_location($origin);
		if (is_wp_error($orig)) {
			return $orig;
		}

		$dest = $this->normalize_location($destination);
		if (is_wp_error($dest)) {
			return $dest;
		}

		// Serve from cache when possible — calculate_shipping() runs on
		// every cart/checkout render, so an uncached API call here burns
		// quota and adds latency on each one.
		$cache_key = 'fxw_dist_' . md5($this->location_hash($orig) . '|' . $this->location_hash($dest));
		$cached = get_transient($cache_key);
		if (false !== $cached && is_array($cached) && isset($cached['distance'], $cached['duration'])) {
			return $cached;
		}

		$args = array(
			'origins' => $orig,
			'destinations' => $dest,
			'units' => 'metric',
			'mode' => 'driving',
			'key' => $this->api_key,
		);

		$request_url = add_query_arg($args, $this->base_url);

		$response = wp_remote_get($request_url, array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'FoodXpress/' . FXW_VERSION . ' WordPress/' . get_bloginfo('version'),
			),
		));

		if (is_wp_error($response)) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Distance API request failed: ' . $response->get_error_message(), array('source' => 'foodxpress'));
			}
			return new WP_Error('delivery_service_error', __('Unable to calculate delivery distance. Please try again or contact support.', 'foodxpress'));
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error(sprintf('Distance API returned HTTP %d', $response_code), array('source' => 'foodxpress'));
			}
			return new WP_Error('delivery_service_error', __('Delivery service temporarily unavailable. Please try again later.', 'foodxpress'));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (empty($data) || !isset($data->status)) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Distance API returned invalid response', array('source' => 'foodxpress'));
			}
			return new WP_Error('delivery_service_error', __('Unable to calculate delivery distance. Please try again.', 'foodxpress'));
		}

		if ('OK' !== $data->status) {
			$api_message = isset($data->error_message) ? $data->error_message : 'Unknown API error';
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Distance API status not OK: ' . $api_message, array('source' => 'foodxpress'));
			}
			return new WP_Error('delivery_service_error', __('Unable to calculate delivery distance for your location.', 'foodxpress'));
		}

		if (empty($data->rows[0]->elements[0])) {
			return new WP_Error('no_results', __('Could not calculate distance.', 'foodxpress'));
		}

		$element = $data->rows[0]->elements[0];

		if (!isset($element->status) || 'OK' !== $element->status) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->warning('Distance API element status: ' . ($element->status ?? 'UNKNOWN'), array('source' => 'foodxpress'));
			}
			return new WP_Error('no_results', __('Could not calculate distance.', 'foodxpress'));
		}

		$result = array(
			'distance' => $element->distance, // In meters
			'duration' => $element->duration, // In seconds
		);

		set_transient($cache_key, $result, self::DISTANCE_CACHE_TTL);

		return $result;
	}

	/**
	 * Get the coordinates for an address.
	 *
	 * @param   string  $address    The address.
	 * @return  array|WP_Error      The coordinates, or an error.
	 * @since   1.0.0
	 */
	/**
	 * Resolve the restaurant's coordinates from plugin settings.
	 *
	 * Uses the explicit `fxw_restaurant_latlng` setting ("lat, lng") when
	 * present; otherwise falls back to geocoding `fxw_restaurant_address`
	 * (cached 24 h via get_coords). Distance/fee calculations must always
	 * go through this so they depend on coordinates only — never on any
	 * customer-entered field.
	 *
	 * @param   array|mixed      $options    Plugin settings (fxw_settings option).
	 * @return  array|WP_Error               array('lat' => float, 'lng' => float) or error.
	 * @since   1.2.3
	 */
	public function get_restaurant_location($options = null)
	{
		if (!is_array($options)) {
			$options = get_option('fxw_settings');
		}

		$raw = isset($options['fxw_restaurant_latlng']) ? trim((string) $options['fxw_restaurant_latlng']) : '';
		if (preg_match('/^(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $raw, $m)) {
			$lat = (float) $m[1];
			$lng = (float) $m[2];
			if (abs($lat) <= 90 && abs($lng) <= 180) {
				return array('lat' => $lat, 'lng' => $lng);
			}
		}

		$address = isset($options['fxw_restaurant_address']) ? trim((string) $options['fxw_restaurant_address']) : '';
		if ('' === $address) {
			return new WP_Error('restaurant_location_missing', __('Restaurant address or coordinates are not configured.', 'foodxpress'));
		}

		$coords = $this->get_coords($address);
		if (is_wp_error($coords)) {
			return $coords;
		}

		$lat = is_array($coords) ? $coords['lat'] : $coords->lat;
		$lng = is_array($coords) ? $coords['lng'] : $coords->lng;

		return array('lat' => (float) $lat, 'lng' => (float) $lng);
	}

	/**
	 * Produce a stable cache identity for a normalized location.
	 *
	 * Coordinates are rounded to 4 decimals (~11 m) so the same drop on the
	 * map reuses the cached result across page loads; addresses are
	 * lowercased and trimmed.
	 *
	 * @param   string  $normalized    Output of normalize_location().
	 * @return  string
	 * @since   1.2.1
	 */
	private function location_hash($normalized)
	{
		if (preg_match('/^(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/', $normalized, $m)) {
			return round((float) $m[1], 4) . ',' . round((float) $m[2], 4);
		}
		return strtolower(trim($normalized));
	}

	/**
	 * Normalize a location value into a "lat,lng" string or pass-through address.
	 *
	 * @param mixed $value Array with ['lat'=>..,'lng'=>..] or string ("lat,lng" or address)
	 * @return string|WP_Error
	 */
	private function normalize_location($value)
	{
		if (is_array($value)) {
			$lat = isset($value['lat']) ? (float) $value['lat'] : null;
			$lng = isset($value['lng']) ? (float) $value['lng'] : null;
			if (null === $lat || null === $lng) {
				return new WP_Error('invalid_location', __('Invalid location array. Expected lat,lng.', 'foodxpress'));
			}
			return $lat . ',' . $lng;
		}

		if (is_string($value)) {
			$value = trim($value);
			// Already looks like "lat,lng"?
			if (preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value)) {
				$parts = array_map('trim', explode(',', $value));
				return (float) $parts[0] . ',' . (float) $parts[1];
			}
			return $value; // Treat as address string
		}

		return new WP_Error('invalid_location', __('Invalid location type.', 'foodxpress'));
	}

	public function get_coords($address)
	{
		if (empty($this->api_key)) {
			return new WP_Error('api_key_missing', __('Google Maps API key is missing.', 'foodxpress'));
		}

		$cache_key = 'fxw_geo_' . md5(strtolower(trim($address)));
		$cached = get_transient($cache_key);
		if (false !== $cached && is_object($cached) && isset($cached->lat, $cached->lng)) {
			return $cached;
		}

		$request_url = add_query_arg(
			array(
				'address' => $address, // add_query_arg will URL-encode; avoid double-encoding
				'key' => $this->api_key,
			),
			$this->geocode_url
		);

		$response = wp_remote_get($request_url, array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'FoodXpress/' . FXW_VERSION . ' WordPress/' . get_bloginfo('version'),
			),
		));

		if (is_wp_error($response)) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Geocoding API request failed: ' . $response->get_error_message(), array('source' => 'foodxpress'));
			}
			return new WP_Error('geocoding_service_error', __('Unable to find location. Please try a different address.', 'foodxpress'));
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error(sprintf('Geocoding API returned HTTP %d', $response_code), array('source' => 'foodxpress'));
			}
			return new WP_Error('geocoding_service_error', __('Location service temporarily unavailable. Please try again later.', 'foodxpress'));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (empty($data) || !isset($data->status)) {
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Geocoding API returned invalid response', array('source' => 'foodxpress'));
			}
			return new WP_Error('geocoding_service_error', __('Unable to find location. Please try again.', 'foodxpress'));
		}

		if ('OK' !== $data->status) {
			$api_message = isset($data->error_message) ? $data->error_message : 'Unknown geocoding error';
			if (function_exists('wc_get_logger')) {
				wc_get_logger()->error('Geocoding API status not OK: ' . $api_message, array('source' => 'foodxpress'));
			}
			return new WP_Error('geocoding_service_error', __('Unable to find the specified address. Please check and try again.', 'foodxpress'));
		}

		if (empty($data->results) || !isset($data->results[0]->geometry->location)) {
			return new WP_Error('geocoding_service_error', __('No location found for the specified address.', 'foodxpress'));
		}

		$location = $data->results[0]->geometry->location;

		set_transient($cache_key, $location, self::GEOCODE_CACHE_TTL);

		return $location;
	}
}
