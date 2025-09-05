<?php
/**
 * Manages the shortcodes for the plugin.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Shortcodes {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_shortcode( 'fxw_track_order', array( $this, 'render_track_order_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_reorder_button' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'handle_reorder' ) );
		add_action( 'wp_ajax_fxw_print_receipt', array( $this, 'print_receipt' ) );
add_action( 'wp_ajax_nopriv_fxw_print_receipt', array( $this, 'print_receipt' ) );
	}

/**
 * AJAX handler for printing a receipt.
 *
 * @since 1.0.1
 */
public function print_receipt() {
// Check for order ID in both GET and POST
$order_id = 0;
if ( isset( $_GET['order_id'] ) && is_numeric( $_GET['order_id'] ) ) {
$order_id = intval( $_GET['order_id'] );
} elseif ( isset( $_POST['order_id'] ) && is_numeric( $_POST['order_id'] ) ) {
$order_id = intval( $_POST['order_id'] );
}

if ( ! $order_id ) {
wp_die( __( 'Invalid order ID.', 'foodxpress' ) );
}

$order = wc_get_order( $order_id );

if ( ! $order ) {
wp_die( __( 'Order not found.', 'foodxpress' ) );
}

// Security check: Ensure the current user is the assigned delivery boy or has admin capabilities
$current_user_id = get_current_user_id();
$assigned_delivery_boy = $order->get_meta( '_fxw_delivery_boy_id' );

// Allow access if:
// 1. User is an administrator
// 2. User is the assigned delivery boy
// 3. User can edit shop orders (shop managers, etc.)
if ( ! current_user_can( 'manage_options' ) && 
     ! current_user_can( 'edit_shop_orders' ) && 
     $current_user_id != $assigned_delivery_boy ) {
wp_die( __( 'You are not authorized to view this receipt.', 'foodxpress' ) );
}

// Set the global order variable for the template
global $order;
$GLOBALS['order'] = $order;

include_once FXW_PLUGIN_DIR . 'templates/receipt-template.php';
exit;
}

	/**
	 * Add a re-order button to the My Account page.
	 *
	 * @param   array      $actions   The existing actions.
	 * @param   WC_Order   $order     The order object.
	 * @return  array                 The modified actions.
	 * @since   1.0.0
	 */
	public function add_reorder_button( $actions, $order ) {
		if ( $order->has_status( 'completed' ) ) {
			$actions['fxw_reorder'] = array(
				'url'  => wp_nonce_url( add_query_arg( 'fxw_reorder', $order->get_id() ), 'fxw_reorder' ),
				'name' => __( 'Re-order', 'foodxpress' ),
			);
		}
		return $actions;
	}

	/**
	 * Handle the re-order action.
	 *
	 * @since   1.0.0
	 */
	public function handle_reorder() {
		if ( isset( $_GET['fxw_reorder'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fxw_reorder' ) ) {
				wp_die( __( 'Invalid nonce.', 'foodxpress' ) );
			}

			$order_id = intval( $_GET['fxw_reorder'] );
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			foreach ( $order->get_items() as $item ) {
				WC()->cart->add_to_cart( $item->get_product_id(), $item->get_quantity() );
			}

			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Enqueue scripts for the frontend.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'fxw-frontend', plugin_dir_url( __FILE__ ) . '../assets/css/frontend.css', array(), FXW_VERSION );
	}

	/**
	 * Renders the track order shortcode.
	 *
	 * @param   array    $atts    Shortcode attributes.
	 * @return  string            The shortcode output.
	 * @since   1.0.0
	 */
	public function render_track_order_shortcode( $atts ) {
		ob_start();
		echo '<div class="fxw-container fxw-track-order-page">';
		$this->track_order_form();
		if ( isset( $_POST['fxw_track_order_nonce'] ) && wp_verify_nonce( $_POST['fxw_track_order_nonce'], 'fxw_track_order' ) ) {
			$this->track_order_status();
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Renders the track order form.
	 *
	 * @since   1.0.0
	 */
	private function track_order_form() {
		?>
        <div class="fxw-track-order-form-container">
            <h2><?php _e( 'Track Your Order', 'foodxpress' ); ?></h2>
            <p><?php _e( 'Enter your order details below to see its current status.', 'foodxpress' ); ?></p>
            <form action="" method="post" class="fxw-track-order-form">
                <?php wp_nonce_field( 'fxw_track_order', 'fxw_track_order_nonce' ); ?>
                <div class="form-row">
                    <label for="fxw_order_id"><?php _e( 'Order ID', 'foodxpress' ); ?></label>
                    <input type="text" name="fxw_order_id" id="fxw_order_id" placeholder="<?php esc_attr_e( 'e.g. 123', 'foodxpress' ); ?>" required>
                </div>
                <div class="form-row">
                    <label for="fxw_billing_email"><?php _e( 'Billing Email', 'foodxpress' ); ?></label>
                    <input type="email" name="fxw_billing_email" id="fxw_billing_email" placeholder="<?php esc_attr_e( 'e.g. you@example.com', 'foodxpress' ); ?>" required>
                </div>
                <div class="form-row">
                    <button type="submit" class="fxw-button fxw-button-track"><?php _e( 'Track Order', 'foodxpress' ); ?></button>
                </div>
            </form>
        </div>
		<?php
	}

	/**
	 * Renders the track order status.
	 *
	 * @since   1.0.0
	 */
	private function track_order_status() {
		$order_id = isset( $_POST['fxw_order_id'] ) ? intval( $_POST['fxw_order_id'] ) : 0;
		$billing_email = isset( $_POST['fxw_billing_email'] ) ? sanitize_email( $_POST['fxw_billing_email'] ) : '';

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_billing_email() !== $billing_email ) {
			echo '<p>' . __( 'Invalid order details.', 'foodxpress' ) . '</p>';
			return;
		}

$statuses = array(
'wc-fxw-in-kitchen' => __( 'In the Kitchen', 'foodxpress' ),
'wc-fxw-assigned'   => __( 'Assigned', 'foodxpress' ),
'wc-fxw-picked-up'  => __( 'Picked Up', 'foodxpress' ),
'wc-completed'      => __( 'Delivered', 'foodxpress' ),
);

		$current_status = $order->get_status();
		$status_keys = array_keys( $statuses );
		$current_status_index = array_search( 'wc-' . $current_status, $status_keys );
$delivery_boy_id = $order->get_meta( '_fxw_delivery_boy_id', true );
?>
        <div class="fxw-order-status-wrapper fxw-mobile-friendly">
            <div class="fxw-order-status-header">
                <h3 style="font-size:1.2em;margin-bottom:0.5em;"><?php printf( __( 'Order #%s', 'foodxpress' ), $order->get_order_number() ); ?></h3>
                <p class="fxw-current-status-text" style="font-size:1em;"><?php echo esc_html( wc_get_order_status_name( $current_status ) ); ?></p>
            </div>
            <div class="fxw-status-tracker">
                <?php foreach ( $statuses as $status_key => $status_name ) : ?>
                    <?php
                    $status_index = array_search( $status_key, $status_keys );
                    $is_completed = ( $current_status_index !== false && $status_index < $current_status_index );
                    $is_current = ( $current_status_index !== false && $status_index === $current_status_index );
                    $is_delivered = ( $status_key === 'wc-completed' && $current_status === 'completed' );
                    $class = $is_completed ? 'completed' : '';
                    $class .= $is_current ? ' current' : '';
                    $class .= $is_delivered ? ' delivered' : '';
                    ?>
                    <div class="status-step <?php echo esc_attr( $class ); ?>">
                        <div class="dot">
                            <span class="fxw-icon">
                                <?php
                                if ( $is_completed || $is_current || $is_delivered ) {
                                    echo '&#10003;'; // Checkmark
                                }
                                ?>
                            </span>
                        </div>
                        <div class="label"><?php echo esc_html( $status_name ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ( $delivery_boy_id && $current_status_index >= 1 ) : ?>
                <?php $delivery_boy = get_user_by( 'id', $delivery_boy_id ); ?>
                <div class="fxw-delivery-boy-info" style="margin-top:1em;padding:0.75em;background:#fafafa;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    <h4 style="font-size:1em;margin-bottom:0.25em;"><?php _e( 'Your Delivery Rider', 'foodxpress' ); ?></h4>
                    <p style="display:flex;align-items:center;gap:8px;">
                        <span class="fxw-icon" style="font-size:1.3em;">&#128100;</span>
                        <strong style="font-size:1em;"><?php echo esc_html( $delivery_boy->display_name ); ?></strong>
                        <a href="tel:<?php echo esc_attr( get_user_meta( $delivery_boy_id, 'billing_phone', true ) ); ?>" class="fxw-button-call" style="padding:0.5em 1em;font-size:0.95em;border-radius:6px;background:#007cba;color:#fff;text-decoration:none;"><?php _e( 'Call', 'foodxpress' ); ?></a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
		<?php
	}
}

new FXW_Shortcodes();
