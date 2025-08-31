<?php
/**
 * A simple rate-limiting service using WordPress transients.
 *
 * @since      1.0.1
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Rate_Limiter {

    /**
     * Check and enforce a rate limit for a given action and identifier.
     *
     * @param string $action    A unique name for the action being limited (e.g., 'geocode_request').
     * @param int    $limit     The number of allowed requests per period.
     * @param int    $period    The time period in seconds.
     * @return bool|WP_Error    True if the request is within the limit, otherwise a WP_Error object.
     */
    public static function check_rate_limit( $action, $limit = 20, $period = MINUTE_IN_SECONDS ) {
        $ip_address = self::get_ip_address();
        if ( ! $ip_address ) {
            // Cannot determine IP, so we can't rate limit.
            return true;
        }

        $transient_key = 'fxw_rl_' . $action . '_' . md5( $ip_address );
        $requests = get_transient( $transient_key );

        if ( false === $requests ) {
            $requests = array();
        }

        $current_time = time();
        // Remove requests older than the defined period
        $requests = array_filter( $requests, function( $timestamp ) use ( $current_time, $period ) {
            return ( $current_time - $timestamp ) < $period;
        } );

        if ( count( $requests ) >= $limit ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'You are making too many requests. Please wait a moment and try again.', 'foodxpress' ) );
        }

        // Add current request timestamp
        $requests[] = $current_time;
        set_transient( $transient_key, $requests, $period );

        return true;
    }

    /**
     * Get the user's IP address, respecting proxies.
     *
     * @return string|null The user's IP address or null if not found.
     */
    private static function get_ip_address() {
        $ip_address = '';
        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
            $ip_address = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
            $ip_address = $_SERVER['HTTP_FORWARDED'];
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }

        if ( ! empty( $ip_address ) ) {
            // The HTTP_X_FORWARDED_FOR can contain a comma-separated list of IPs.
            // The client's IP will be the first one in the list.
            $ip_array = explode( ',', $ip_address );
            $ip_address = trim( reset( $ip_array ) );
        }

        if ( ! filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
            return null;
        }

        return $ip_address;
    }
}
