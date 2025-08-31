<?php
/**
 * Manages the admin-specific functionality for orders.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Order_Admin {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_delivery_meta_box' ) );
		add_action( 'save_post_shop_order', array( $this, 'save_delivery_meta_box_data' ) );
		add_action( 'template_redirect', array( $this, 'print_receipt_template' ) );
		add_action( 'admin_init', array( $this, 'handle_order_actions' ) );
	}

/**
 * Handle order actions.
 *
 * @since    1.0.0
 */
public function handle_order_actions() {
if ( isset( $_GET['fxw_action'] ) && isset( $_GET['order_id'] ) && isset( $_GET['_wpnonce'] ) ) {
if ( ! current_user_can( 'edit_shop_orders' ) ) {
wp_die( __( 'Unauthorized.', 'foodxpress' ) );
}

if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fxw_order_action' ) ) {
wp_die( __( 'Invalid nonce.', 'foodxpress' ) );
}

$order_id = absint( $_GET['order_id'] );
$order    = wc_get_order( $order_id );

if ( ! $order ) {
wp_die( __( 'Invalid order.', 'foodxpress' ) );
}

switch ( sanitize_text_field( wp_unslash( $_GET['fxw_action'] ) ) ) {
case 'reject':
$order->update_status( 'cancelled', __( 'Order rejected by admin.', 'foodxpress' ) );
break;
case 'reassign':
delete_post_meta( $order_id, '_fxw_delivery_boy_id' );
$order->add_order_note( __( 'Delivery boy has been unassigned.', 'foodxpress' ) );
break;
}

wp_safe_redirect( remove_query_arg( array( 'fxw_action', '_wpnonce' ) ) );
exit;
}
}

/**
 * Load the receipt template.
 *
 * @since    1.0.0
 */
public function print_receipt_template() {
if ( ! isset( $_GET['fxw_print_receipt'] ) ) {
return;
}

if ( ! current_user_can( 'edit_shop_orders' ) ) {
wp_die( __( 'Unauthorized.', 'foodxpress' ) );
}

$order_id = absint( $_GET['fxw_print_receipt'] );
$order = wc_get_order( $order_id );

if ( ! $order ) {
wp_die( __( 'Invalid order.', 'foodxpress' ) );
}

include_once FXW_PLUGIN_DIR . 'templates/receipt-template.php';
exit;
}

	/**
	 * Adds the meta box to the order edit screen.
	 *
	 * @since    1.0.0
	 */
	public function add_delivery_meta_box() {
		add_meta_box(
			'fxw_delivery_meta_box',
			__( 'FoodXpress Delivery', 'foodxpress' ),
			array( $this, 'render_delivery_meta_box' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Renders the content of the meta box.
	 *
	 * @param   WP_Post   $post    The post object.
	 * @since   1.0.0
	 */
	public function render_delivery_meta_box( $post ) {
		wp_nonce_field( 'fxw_save_delivery_meta_box_data', 'fxw_meta_box_nonce' );

		$order = wc_get_order( $post->ID );
$delivery_boy_id = $order ? $order->get_meta( '_fxw_delivery_boy_id', true ) : '';
		$delivery_boys   = get_users( array( 'role' => 'delivery_boy' ) );
		$order           = wc_get_order( $post->ID );
		$payment_method  = $order->get_payment_method_title();
$shipping_address = $order->get_formatted_shipping_address();
$unit = get_post_meta( $post->ID, '_fxw_address_unit', true );
?>
<?php if ( $unit ) : ?>
<p><strong><?php _e( 'Flat/House/Unit:', 'foodxpress' ); ?></strong> <?php echo esc_html( $unit ); ?></p>
<?php endif; ?>
<p>
<strong><?php _e( 'Payment Method:', 'foodxpress' ); ?></strong><br>
			<?php echo esc_html( $payment_method ); ?>
			<?php if ( 'cod' === $order->get_payment_method() ) : ?>
				<br><strong><?php _e( 'Amount to Collect:', 'foodxpress' ); ?></strong>
				<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
			<?php endif; ?>
		</p>
		<p>
			<label for="fxw_delivery_boy_id"><?php _e( 'Assign Delivery Boy', 'foodxpress' ); ?></label>
			<?php if ( ! empty( $delivery_boys ) ) : ?>
				<select name="fxw_delivery_boy_id" id="fxw_delivery_boy_id" style="width:100%;">
					<option value=""><?php _e( 'Select a Delivery Boy', 'foodxpress' ); ?></option>
					<?php foreach ( $delivery_boys as $boy ) : ?>
						<option value="<?php echo esc_attr( $boy->ID ); ?>" <?php selected( $delivery_boy_id, $boy->ID ); ?>>
							<?php echo esc_html( $boy->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<p><?php _e( 'No delivery boys found. Please create a user with the "Delivery Boy" role.', 'foodxpress' ); ?></p>
			<?php endif; ?>
		</p>
		<p>
			<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode( $shipping_address ); ?>" target="_blank" class="button"><?php _e( 'View on Map', 'foodxpress' ); ?></a>
			<a href="#" class="button" onclick="window.open('<?php echo esc_url( add_query_arg( 'fxw_print_receipt', $post->ID ) ); ?>', '_blank'); return false;"><?php _e( 'Print Receipt', 'foodxpress' ); ?></a>
		</p>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fxw_action', 'reject', get_edit_post_link( $post->ID ) ), 'fxw_order_action' ) ); ?>" class="button button-danger"><?php _e( 'Reject Order', 'foodxpress' ); ?></a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fxw_action', 'reassign', get_edit_post_link( $post->ID ) ), 'fxw_order_action' ) ); ?>" class="button"><?php _e( 'Re-assign', 'foodxpress' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Saves the data from the meta box.
	 *
	 * @param   int   $post_id    The post ID.
	 * @since   1.0.0
	 */
public function save_delivery_meta_box_data( $post_id ) {
if ( ! isset( $_POST['fxw_meta_box_nonce'] ) ) {
return;
}

if ( ! wp_verify_nonce( $_POST['fxw_meta_box_nonce'], 'fxw_save_delivery_meta_box_data' ) ) {
return;
}

if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
return;
}

if ( ! current_user_can( 'edit_post', $post_id ) ) {
return;
}

// Save fxw_address_unit from checkout/order meta
if ( isset( $_POST['fxw_address_unit'] ) ) {
    update_post_meta( $post_id, '_fxw_address_unit', sanitize_text_field( $_POST['fxw_address_unit'] ) );
}

if ( isset( $_POST['fxw_delivery_boy_id'] ) ) {
$new_id  = sanitize_text_field( $_POST['fxw_delivery_boy_id'] );
$order = wc_get_order( $post_id );
$prev_id = $order ? $order->get_meta( '_fxw_delivery_boy_id', true ) : '';

if ( $new_id ) {
update_post_meta( $post_id, '_fxw_delivery_boy_id', $new_id );
} else {
delete_post_meta( $post_id, '_fxw_delivery_boy_id' );
}

if ( $new_id && $new_id !== $prev_id ) {
$order = wc_get_order( $post_id );
if ( $order ) {
$options     = get_option( 'fxw_settings' );
$auto_assign = true;
if ( is_array( $options ) && isset( $options['fxw_auto_set_assigned_status'] ) ) {
$auto_assign = in_array( $options['fxw_auto_set_assigned_status'], array( 'yes', 'true', 1, '1' ), true );
}
if ( $auto_assign ) {
$order->update_status( 'fxw-assigned', __( 'Order assigned to delivery boy.', 'foodxpress' ) );
} else {
$order->add_order_note( __( 'Delivery boy assigned (status unchanged).', 'foodxpress' ) );
}
}
}
}
}
}

new FXW_Order_Admin();
