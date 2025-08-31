<?php
/**
 * Manages the reporting functionality for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Reporting {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'fxw_dashboard_content', array( $this, 'render_reports' ) );
	}

	/**
	 * Render the reports section on the dashboard.
	 *
	 * @since    1.0.0
	 */
	public function render_reports() {
		?>
		<div class="fxw-reports">
			<h2><?php _e( 'Today\'s Report', 'foodxpress' ); ?></h2>
			<?php
			$today = date( 'Y-m-d' );
			$args = array(
				'limit' => -1,
				'status' => 'wc-completed',
				'date_created' => $today,
				'meta_key' => '_fxw_delivery_boy_id',
				'meta_compare' => 'EXISTS',
			);
			$orders = wc_get_orders( $args );
			$total_deliveries = count( $orders );
			$total_fees = 0;
			foreach ( $orders as $order ) {
				$total_fees += (float) $order->get_shipping_total();
			}
			?>
			<p>
				<strong><?php _e( 'Total Deliveries:', 'foodxpress' ); ?></strong>
				<?php echo esc_html( $total_deliveries ); ?>
			</p>
			<p>
				<strong><?php _e( 'Total Delivery Fees:', 'foodxpress' ); ?></strong>
				<?php echo wp_kses_post( wc_price( $total_fees ) ); ?>
			</p>
		</div>
		<?php
	}
}

new FXW_Reporting();
