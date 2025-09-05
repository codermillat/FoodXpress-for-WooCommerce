<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Core {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies and define the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version = FXW_VERSION;
		$this->plugin_name = 'foodxpress';

		$this->load_dependencies();
		$this->define_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Core services and configuration files
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-config.php';
		require_once FXW_PLUGIN_DIR . 'includes/services/class-fxw-mapping-service.php';
		require_once FXW_PLUGIN_DIR . 'includes/services/class-fxw-rate-limiter.php';

		// Core files - loaded on both admin and frontend
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-roles.php';
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-order-statuses.php';
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-notifications.php';

		/**
		 * Frontend and AJAX (admin-ajax.php) files
		 * Ensure FXW_Checkout loads during AJAX so wp_ajax hooks are registered.
		 */
		$fxw_is_ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		if ( ! is_admin() || $fxw_is_ajax ) {
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-checkout.php';
		}

		// Frontend-only files
		if ( ! is_admin() ) {
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-shortcodes.php';
		}
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-delivery-boy-view.php';

		// Admin-only files
		if ( is_admin() ) {
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-settings.php';
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-order-admin.php';
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-dashboard.php';
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-reporting.php';
			require_once FXW_PLUGIN_DIR . 'includes/class-fxw-admin-bar.php';

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}
	}

	/**
	 * Enqueue scripts for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/js/admin.js', array( 'jquery' ), $this->version, true );
	}

/**
 * Enqueue scripts for the delivery dashboard page.
 *
 * @since    1.0.0
 */
public function enqueue_delivery_dashboard_scripts() {
// Check if we're on the delivery dashboard page
if ( get_query_var( 'is_delivery_dashboard' ) || is_page_template( 'templates/delivery-dashboard-template.php' ) ) {
wp_enqueue_script( 'fxw-delivery-dashboard', plugin_dir_url( __FILE__ ) . '../assets/js/delivery-dashboard.js', array( 'jquery' ), $this->version, true );
       
// Localize script with AJAX parameters for delivery dashboard
wp_localize_script( 'fxw-delivery-dashboard', 'fxw_checkout_params', array(
'ajax_url' => admin_url( 'admin-ajax.php' ),
'nonce'    => wp_create_nonce( 'fxw_print_receipt' ),
) );
}
}

/**
 * Define the hooks for the plugin.
 *
 * @since    1.0.0
 * @access   private
 */
	private function define_hooks() {
		add_action( 'after_setup_theme', array( $this, 'disable_admin_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_delivery_dashboard_scripts' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_delivery_dashboard_access' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_method' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
		add_filter( 'woocommerce_shipping_chosen_method', array( $this, 'prefer_fxw_shipping' ), 10, 2 );
	}

/**
 * Add rewrite rules for delivery dashboard.
 *
 * @since    1.0.0
 */
public function add_rewrite_rules() {
add_rewrite_rule( '^delivery-dashboard/?$', 'index.php?is_delivery_dashboard=true', 'top' );
}

/**
 * Add custom query vars.
 *
 * @since    1.0.0
 * @param    array    $vars    Existing query vars.
 * @return   array    Modified query vars.
 */
public function add_query_vars( $vars ) {
$vars[] = 'is_delivery_dashboard';
return $vars;
}

/**
 * Handle access control for delivery dashboard before any output is sent.
 *
 * @since    1.0.0
 */
public function handle_delivery_dashboard_access() {
if ( get_query_var( 'is_delivery_dashboard' ) ) {
if ( ! is_user_logged_in() || ! current_user_can( 'fxw_delivery_access' ) ) {
wp_redirect( home_url() );
exit;
}
}
}

/**
 * Include custom template for delivery dashboard.
 *
 * @since    1.0.0
 * @param    string    $template    The template to include.
 * @return   string    Modified template path.
 */
public function template_include( $template ) {
if ( get_query_var( 'is_delivery_dashboard' ) ) {
$custom_template = FXW_PLUGIN_DIR . 'templates/delivery-dashboard-template.php';
if ( file_exists( $custom_template ) ) {
return $custom_template;
}
}
return $template;
}

	/**
	 * Load the shipping method class.
	 *
	 * @since    1.0.0
	 */
	public function load_shipping_method() {
		require_once FXW_PLUGIN_DIR . 'includes/class-fxw-shipping-method.php';
	}

	/**
	 * Add the shipping method to WooCommerce.
	 *
	 * @since    1.0.0
	 * @param    array    $methods    Existing shipping methods.
	 * @return   array    Modified shipping methods.
	 */
public function add_shipping_method( $methods ) {
$methods['foodxpress_delivery'] = 'FXW_Shipping_Method';
return $methods;
}

/**
 * Prefer FoodXpress shipping rate by default when available.
 * Ensures a chosen method exists to avoid "No shipping method has been selected".
 *
 * @param string $chosen_method
 * @param array  $available_methods
 * @param int    $package_index
 * @return string
 */
public function prefer_fxw_shipping( $chosen_method, $available_methods ) {
if ( $chosen_method && isset( $available_methods[ $chosen_method ] ) ) {
return $chosen_method;
}
foreach ( $available_methods as $rate_id => $rate ) {
if ( is_string( $rate_id ) && strpos( $rate_id, 'foodxpress_delivery' ) === 0 ) {
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( 'prefer_fxw_shipping: selecting ' . $rate_id, array( 'source' => 'foodxpress' ) );
}
return $rate_id;
}
}
return $chosen_method;
}

/**
 * Disable admin bar for customers and delivery boys.
 *
 * @since    1.0.0
 */
public function disable_admin_bar() {
    if ( ! current_user_can( 'manage_options' ) ) {
        $user = wp_get_current_user();
        if ( in_array( 'delivery_boy', (array) $user->roles ) || in_array( 'customer', (array) $user->roles ) ) {
            add_filter( 'show_admin_bar', '__return_false' );
        }
    }
}
}
