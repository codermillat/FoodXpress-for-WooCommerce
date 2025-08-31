<?php
/**
 * Manages the settings page for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Settings {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page to the WordPress admin menu.
	 *
	 * @since    1.0.0
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'FoodXpress Settings', 'foodxpress' ),
			__( 'FoodXpress', 'foodxpress' ),
			'manage_options',
			'foodxpress-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'fxw_settings_group' );
				do_settings_sections( 'foodxpress-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the settings, sections, and fields.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting( 'fxw_settings_group', 'fxw_settings' );

		// General Settings Section
		add_settings_section(
			'fxw_general_settings_section',
			__( 'General Settings', 'foodxpress' ),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_google_maps_api_key',
			__( 'Google Maps API Key', 'foodxpress' ),
			array( $this, 'render_text_field' ),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array( 'id' => 'fxw_google_maps_api_key' )
		);

		add_settings_field(
			'fxw_restaurant_address',
			__( 'Restaurant Address', 'foodxpress' ),
			array( $this, 'render_text_field' ),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array( 'id' => 'fxw_restaurant_address' )
		);

		add_settings_field(
			'fxw_preparation_time',
			__( 'Default Preparation Time (minutes)', 'foodxpress' ),
			array( $this, 'render_number_field' ),
			'foodxpress-settings',
			'fxw_general_settings_section',
			array( 'id' => 'fxw_preparation_time', 'default' => 20 )
		);

		// Delivery Fee Settings Section
		add_settings_section(
			'fxw_delivery_fee_settings_section',
			__( 'Delivery Fee Settings', 'foodxpress' ),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_delivery_fee_base',
			__( 'Base Fee', 'foodxpress' ),
			array( $this, 'render_number_field' ),
			'foodxpress-settings',
			'fxw_delivery_fee_settings_section',
			array( 'id' => 'fxw_delivery_fee_base', 'default' => 5.00, 'step' => 0.01 )
		);

		add_settings_field(
			'fxw_delivery_fee_per_km',
			__( 'Fee Per Kilometer', 'foodxpress' ),
			array( $this, 'render_number_field' ),
			'foodxpress-settings',
			'fxw_delivery_fee_settings_section',
			array( 'id' => 'fxw_delivery_fee_per_km', 'default' => 1.50, 'step' => 0.01 )
		);

		// Delivery Zone Settings Section
		add_settings_section(
			'fxw_delivery_zone_settings_section',
			__( 'Delivery Zone Settings', 'foodxpress' ),
			null,
			'foodxpress-settings'
		);

		add_settings_field(
			'fxw_delivery_zone_radius',
			__( 'Delivery Radius (km)', 'foodxpress' ),
			array( $this, 'render_number_field' ),
			'foodxpress-settings',
			'fxw_delivery_zone_settings_section',
			array( 'id' => 'fxw_delivery_zone_radius', 'default' => 10 )
		);
	}

	/**
	 * Render a generic text field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'fxw_settings' );
		$id      = $args['id'];
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : '';
		?>
		<input type="text" name="fxw_settings[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<?php
	}

	/**
	 * Render a generic number field.
	 *
	 * @param   array   $args   The arguments for the field.
	 * @since   1.0.0
	 */
	public function render_number_field( $args ) {
		$options = get_option( 'fxw_settings' );
		$id      = $args['id'];
		$default = isset( $args['default'] ) ? $args['default'] : '';
		$step    = isset( $args['step'] ) ? $args['step'] : '1';
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		?>
		<input type="number" step="<?php echo esc_attr( $step ); ?>" name="fxw_settings[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="small-text">
		<?php
	}
}

new FXW_Settings();
