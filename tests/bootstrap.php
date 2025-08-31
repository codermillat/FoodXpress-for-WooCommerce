<?php
/**
 * PHPUnit bootstrap file for FoodXpress for WooCommerce tests
 */

// Define test environment
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'test@example.com' );
define( 'WP_TESTS_TITLE', 'FoodXpress Test Site' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_TABLE_PREFIX', 'fxwtest_' );

// Define WordPress test constants
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Load WordPress test environment if available
if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
    require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';
} else {
    // Fallback for local development
    echo "WordPress test environment not found. Setting up minimal test environment...\n";
    
    // Mock WordPress functions for isolated testing
    if ( ! function_exists( 'add_action' ) ) {
        function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
            // Mock implementation
            return true;
        }
    }
    
    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
            // Mock implementation
            return true;
        }
    }
    
    if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = 'default' ) {
            return $text;
        }
    }
    
    if ( ! function_exists( '_e' ) ) {
        function _e( $text, $domain = 'default' ) {
            echo $text;
        }
    }
    
    if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) {
            return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
        }
    }
    
    if ( ! function_exists( 'wp_remote_get' ) ) {
        function wp_remote_get( $url, $args = array() ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body' => '{"status":"OK"}'
            );
        }
    }
    
    if ( ! class_exists( 'WP_Error' ) ) {
        class WP_Error {
            public function __construct( $code = '', $message = '', $data = '' ) {
                $this->errors = array( $code => array( $message ) );
            }
            
            public function get_error_message() {
                return 'Test error';
            }
        }
    }
}

// Load plugin files for testing
define( 'FXW_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'FXW_VERSION', '1.0.0' );

echo "FoodXpress test environment loaded successfully.\n";
