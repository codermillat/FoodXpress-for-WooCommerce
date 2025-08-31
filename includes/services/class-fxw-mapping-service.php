<?php
/**
 * Handles all communication with the mapping service API.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Mapping_Service {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$options = get_option( 'fxw_settings' );
		$this->api_key = isset( $options['fxw_google_maps_api_key'] ) ? $options['fxw_google_maps_api_key'] : '';
	}

	/**
	 * Get the distance between two points.
	 *
	 * @param   string  $origin         The origin address.
	 * @param   string  $destination    The destination address.
	 * @return  array|WP_Error          The distance and duration, or an error.
	 * @since   1.0.0
	 */
public function get_distance( $origin, $destination ) {
if ( empty( $this->api_key ) ) {
return new WP_Error( 'api_key_missing', __( 'Google Maps API key is missing.', 'foodxpress' ) );
}

$orig = $this->normalize_location( $origin );
if ( is_wp_error( $orig ) ) {
return $orig;
}

$dest = $this->normalize_location( $destination );
if ( is_wp_error( $dest ) ) {
return $dest;
}

$args = array(
'origins'      => $orig,
'destinations' => $dest,
'units'        => 'metric',
'mode'         => 'driving',
'key'          => $this->api_key,
);

$request_url = add_query_arg( $args, $this->base_url );

$response = wp_remote_get( $request_url, array(
'timeout' => 15,
'headers' => array(
'User-Agent' => 'FoodXpress/' . FXW_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
),
) );

if ( is_wp_error( $response ) ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Distance API request failed: ' . $response->get_error_message(), array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'delivery_service_error', __( 'Unable to calculate delivery distance. Please try again or contact support.', 'foodxpress' ) );
}

$response_code = wp_remote_retrieve_response_code( $response );
if ( $response_code !== 200 ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( sprintf( 'Distance API returned HTTP %d', $response_code ), array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'delivery_service_error', __( 'Delivery service temporarily unavailable. Please try again later.', 'foodxpress' ) );
}

$body = wp_remote_retrieve_body( $response );
$data = json_decode( $body );

if ( empty( $data ) || ! isset( $data->status ) ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Distance API returned invalid response', array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'delivery_service_error', __( 'Unable to calculate delivery distance. Please try again.', 'foodxpress' ) );
}

if ( 'OK' !== $data->status ) {
$api_message = isset( $data->error_message ) ? $data->error_message : 'Unknown API error';
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Distance API status not OK: ' . $api_message, array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'delivery_service_error', __( 'Unable to calculate delivery distance for your location.', 'foodxpress' ) );
}

if ( empty( $data->rows[0]->elements[0] ) ) {
return new WP_Error( 'no_results', __( 'Could not calculate distance.', 'foodxpress' ) );
}

$element = $data->rows[0]->elements[0];

if ( ! isset( $element->status ) || 'OK' !== $element->status ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->warning( 'Distance API element status: ' . ( $element->status ?? 'UNKNOWN' ), array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'no_results', __( 'Could not calculate distance.', 'foodxpress' ) );
}

return array(
'distance' => $element->distance, // In meters
'duration' => $element->duration, // In seconds
);
}

	/**
	 * Get the coordinates for an address.
	 *
	 * @param   string  $address    The address.
	 * @return  array|WP_Error      The coordinates, or an error.
	 * @since   1.0.0
	 */
	/**
 * Normalize a location value into a "lat,lng" string or pass-through address.
 *
 * @param mixed $value Array with ['lat'=>..,'lng'=>..] or string ("lat,lng" or address)
 * @return string|WP_Error
 */
private function normalize_location( $value ) {
if ( is_array( $value ) ) {
$lat = isset( $value['lat'] ) ? (float) $value['lat'] : null;
$lng = isset( $value['lng'] ) ? (float) $value['lng'] : null;
if ( null === $lat || null === $lng ) {
return new WP_Error( 'invalid_location', __( 'Invalid location array. Expected lat,lng.', 'foodxpress' ) );
}
return $lat . ',' . $lng;
}

if ( is_string( $value ) ) {
$value = trim( $value );
// Already looks like "lat,lng"?
if ( preg_match( '/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value ) ) {
$parts = array_map( 'trim', explode( ',', $value ) );
return (float) $parts[0] . ',' . (float) $parts[1];
}
return $value; // Treat as address string
}

return new WP_Error( 'invalid_location', __( 'Invalid location type.', 'foodxpress' ) );
}

public function get_coords( $address ) {
if ( empty( $this->api_key ) ) {
return new WP_Error( 'api_key_missing', __( 'Google Maps API key is missing.', 'foodxpress' ) );
}

$request_url = add_query_arg(
array(
'address' => $address, // add_query_arg will URL-encode; avoid double-encoding
'key'     => $this->api_key,
),
$this->geocode_url
);

$response = wp_remote_get( $request_url, array(
'timeout' => 15,
'headers' => array(
'User-Agent' => 'FoodXpress/' . FXW_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
),
) );

if ( is_wp_error( $response ) ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Geocoding API request failed: ' . $response->get_error_message(), array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'geocoding_service_error', __( 'Unable to find location. Please try a different address.', 'foodxpress' ) );
}

$response_code = wp_remote_retrieve_response_code( $response );
if ( $response_code !== 200 ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( sprintf( 'Geocoding API returned HTTP %d', $response_code ), array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'geocoding_service_error', __( 'Location service temporarily unavailable. Please try again later.', 'foodxpress' ) );
}

$body = wp_remote_retrieve_body( $response );
$data = json_decode( $body );

if ( empty( $data ) || ! isset( $data->status ) ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Geocoding API returned invalid response', array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'geocoding_service_error', __( 'Unable to find location. Please try again.', 'foodxpress' ) );
}

if ( 'OK' !== $data->status ) {
$api_message = isset( $data->error_message ) ? $data->error_message : 'Unknown geocoding error';
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->error( 'Geocoding API status not OK: ' . $api_message, array( 'source' => 'foodxpress' ) );
}
return new WP_Error( 'geocoding_service_error', __( 'Unable to find the specified address. Please check and try again.', 'foodxpress' ) );
}

if ( empty( $data->results ) || ! isset( $data->results[0]->geometry->location ) ) {
return new WP_Error( 'geocoding_service_error', __( 'No location found for the specified address.', 'foodxpress' ) );
}

return $data->results[0]->geometry->location;
}
}
