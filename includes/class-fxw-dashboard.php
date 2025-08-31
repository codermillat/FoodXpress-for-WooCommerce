<?php
/**
 * Manages the admin dashboard functionality.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Dashboard {

/**
 * Initialize the class and set its properties.
 *
 * @since    1.0.0
 */
public function __construct() {
add_action( 'admin_menu', array( $this, 'add_dashboard_menu' ) );
add_action( 'admin_post_fxw_assign_delivery', array( $this, 'assign_delivery' ) );
add_action( 'admin_post_fxw_update_order_status', array( $this, 'update_order_status' ) );
add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
}

	/**
	 * Adds the dashboard menu page.
	 *
	 * @since    1.0.0
	 */
	public function add_dashboard_menu() {
		add_menu_page(
			__( 'Deliveries Dashboard', 'foodxpress' ),
			__( 'Deliveries', 'foodxpress' ),
			'manage_woocommerce',
			'fxw-deliveries-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-cart',
			25
		);
	}

	/**
	 * Renders the content of the dashboard page.
	 *
	 * @since    1.0.0
	 */
public function render_dashboard_page() {
// Unassigned orders: exclude fxw-assigned status to prevent overlap
$unassigned_orders = wc_get_orders( array(
'limit'        => -1,
'status'       => array( 'pending', 'processing', 'on-hold', 'fxw-in-kitchen' ),
'meta_query'   => array(
'relation' => 'OR',
array(
'key'     => '_fxw_delivery_boy_id',
'compare' => 'NOT EXISTS',
),
array(
'key'     => '_fxw_delivery_boy_id',
'value'   => array( '', '0', 0 ),
'compare' => 'IN',
),
),
) );

// Add orphaned orders (fxw-assigned status but no delivery boy)
$orphaned_orders = wc_get_orders( array(
'limit'        => -1,
'status'       => array( 'fxw-assigned' ),
'meta_query'   => array(
'relation' => 'OR',
array(
'key'     => '_fxw_delivery_boy_id',
'compare' => 'NOT EXISTS',
),
array(
'key'     => '_fxw_delivery_boy_id',
'value'   => array( '', '0', 0 ),
'compare' => 'IN',
),
),
) );

// Combine unassigned and orphaned orders
$unassigned_orders = array_merge( $unassigned_orders, $orphaned_orders );

$assigned_orders = wc_get_orders( array(
'limit'        => -1,
'status'       => array( 'fxw-assigned' ),
'meta_query'   => array(
array(
'key'     => '_fxw_delivery_boy_id',
'value'   => 0,
'type'    => 'NUMERIC',
'compare' => '>',
),
),
) );

$out_for_delivery = wc_get_orders( array(
'limit'        => -1,
'status'       => array( 'fxw-picked-up' ),
'meta_query'   => array(
array(
'key'     => '_fxw_delivery_boy_id',
'value'   => 0,
'type'    => 'NUMERIC',
'compare' => '>',
),
),
) );
?>
<div class="wrap">
<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
<?php do_action( 'fxw_dashboard_content' ); ?>

<h2><?php _e( 'Unassigned Orders', 'foodxpress' ); ?></h2>
<table class="widefat fixed" cellspacing="0">
<thead>
<tr>
<th class="manage-column"><?php _e( 'Order', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Customer', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Status', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Payment', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Actions', 'foodxpress' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( ! empty( $unassigned_orders ) ) : ?>
<?php foreach ( $unassigned_orders as $order ) : ?>
<tr>
<td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
<td>
<?php echo esc_html( $order->get_payment_method_title() ); ?>
<?php if ( 'cod' === $order->get_payment_method() ) : ?>
<br><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
<?php endif; ?>
</td>
<td>
<?php $delivery_boys = get_users( array( 'role' => 'delivery_boy' ) ); ?>
<?php if ( ! empty( $delivery_boys ) ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
<?php wp_nonce_field( 'fxw_assign_delivery' ); ?>
<input type="hidden" name="action" value="fxw_assign_delivery" />
<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
<select name="delivery_boy_id" style="width:140px; margin-right:5px;" onchange="this.form.submit();">
<option value=""><?php _e( 'Select Delivery Boy...', 'foodxpress' ); ?></option>
<?php foreach ( $delivery_boys as $boy ) : ?>
<option value="<?php echo esc_attr( $boy->ID ); ?>"><?php echo esc_html( $boy->display_name ); ?> (ID: <?php echo esc_html( $boy->ID ); ?>)</option>
<?php endforeach; ?>
</select>
<noscript>
<button type="submit" class="button button-small"><?php _e( 'Assign', 'foodxpress' ); ?></button>
</noscript>
</form>
<?php else : ?>
<span style="color:#999;"><?php _e( 'No delivery boys available', 'foodxpress' ); ?></span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else : ?>
<tr><td colspan="5"><?php _e( 'No unassigned orders.', 'foodxpress' ); ?></td></tr>
<?php endif; ?>
</tbody>
</table>

<h2 style="margin-top:24px;"><?php _e( 'Assigned Orders', 'foodxpress' ); ?></h2>
<table class="widefat fixed" cellspacing="0">
<thead>
<tr>
<th class="manage-column"><?php _e( 'Order', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Customer', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Delivery Boy', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Status', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Payment', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Actions', 'foodxpress' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( ! empty( $assigned_orders ) ) : ?>
<?php foreach ( $assigned_orders as $order ) : ?>
<?php
$delivery_boy_id = $order->get_meta( '_fxw_delivery_boy_id', true );
$delivery_boy = $delivery_boy_id ? get_user_by( 'id', $delivery_boy_id ) : null;
?>
<tr>
<td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
<td><?php echo esc_html( $delivery_boy ? $delivery_boy->display_name : '-' ); ?></td>
<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
<td>
<?php echo esc_html( $order->get_payment_method_title() ); ?>
<?php if ( 'cod' === $order->get_payment_method() ) : ?>
<br><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
<?php endif; ?>
</td>
<td>
<?php if ( 'fxw-assigned' === $order->get_status() ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:5px;">
<?php wp_nonce_field( 'fxw_update_status' ); ?>
<input type="hidden" name="action" value="fxw_update_order_status" />
<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
<input type="hidden" name="new_status" value="fxw-picked-up" />
<button type="submit" class="button button-small"><?php _e( 'Picked Up', 'foodxpress' ); ?></button>
</form>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
<?php wp_nonce_field( 'fxw_assign_delivery' ); ?>
<input type="hidden" name="action" value="fxw_assign_delivery" />
<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
<input type="hidden" name="delivery_boy_id" value="" />
<button type="submit" class="button button-small button-link-delete"><?php _e( 'Reassign', 'foodxpress' ); ?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else : ?>
<tr><td colspan="6"><?php _e( 'No assigned orders.', 'foodxpress' ); ?></td></tr>
<?php endif; ?>
</tbody>
</table>

<h2 style="margin-top:24px;"><?php _e( 'Out for Delivery', 'foodxpress' ); ?></h2>
<table class="widefat fixed" cellspacing="0">
<thead>
<tr>
<th class="manage-column"><?php _e( 'Order', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Customer', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Delivery Boy', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Status', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Payment', 'foodxpress' ); ?></th>
<th class="manage-column"><?php _e( 'Actions', 'foodxpress' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( ! empty( $out_for_delivery ) ) : ?>
<?php foreach ( $out_for_delivery as $order ) : ?>
<?php
$delivery_boy_id = $order->get_meta( '_fxw_delivery_boy_id', true );
$delivery_boy = $delivery_boy_id ? get_user_by( 'id', $delivery_boy_id ) : null;
?>
<tr>
<td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
<td><?php echo esc_html( $delivery_boy ? $delivery_boy->display_name : '-' ); ?></td>
<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
<td>
<?php echo esc_html( $order->get_payment_method_title() ); ?>
<?php if ( 'cod' === $order->get_payment_method() ) : ?>
<br><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
<?php endif; ?>
</td>
<td>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
<?php wp_nonce_field( 'fxw_update_status' ); ?>
<input type="hidden" name="action" value="fxw_update_order_status" />
<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
<input type="hidden" name="new_status" value="completed" />
<button type="submit" class="button button-small button-primary"><?php _e( 'Delivered', 'foodxpress' ); ?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else : ?>
<tr><td colspan="6"><?php _e( 'No orders out for delivery.', 'foodxpress' ); ?></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php
}

/**
 * Handle delivery assignment from dashboard.
 *
 * @since 1.0.0
 */
public function assign_delivery() {
if ( ! current_user_can( 'edit_shop_orders' ) ) {
wp_die( __( 'Unauthorized.', 'foodxpress' ) );
}

$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'fxw_assign_delivery' ) ) {
wp_die( __( 'Invalid nonce.', 'foodxpress' ) );
}

$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
$delivery_boy_id = isset( $_POST['delivery_boy_id'] ) ? absint( $_POST['delivery_boy_id'] ) : 0;

$order = wc_get_order( $order_id );
if ( ! $order ) {
wp_die( __( 'Invalid order.', 'foodxpress' ) );
}

// Log before assignment
if ( function_exists( 'wc_get_logger' ) ) {
wc_get_logger()->debug( sprintf( 'assign_delivery: Order #%d, delivery_boy_id=%d, current_status=%s', $order_id, $delivery_boy_id, $order->get_status() ), array( 'source' => 'foodxpress' ) );
}

if ( $delivery_boy_id ) {
$delivery_boy = get_user_by( 'id', $delivery_boy_id );
$delivery_boy_name = $delivery_boy ? $delivery_boy->display_name : "ID: {$delivery_boy_id}";

// Use WooCommerce CRUD instead of update_post_meta
$order->update_meta_data( '_fxw_delivery_boy_id', $delivery_boy_id );
$order->update_status( 'fxw-assigned', sprintf( __( 'Order assigned to delivery boy: %s', 'foodxpress' ), $delivery_boy_name ) );
$order->save();

// Clear any WooCommerce caches for this order
if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
wc_delete_shop_order_transients( $order_id );
}

// Log after assignment
if ( function_exists( 'wc_get_logger' ) ) {
$saved_delivery_boy_id = $order->get_meta( '_fxw_delivery_boy_id', true );
wc_get_logger()->debug( sprintf( 'assign_delivery: Order #%d saved, new_status=%s, saved_delivery_boy_id=%s', $order_id, $order->get_status(), $saved_delivery_boy_id ), array( 'source' => 'foodxpress' ) );
}

set_transient( 'fxw_admin_notice', sprintf( __( 'Order #%s successfully assigned to %s', 'foodxpress' ), $order->get_order_number(), $delivery_boy_name ), 30 );
} else {
// Use WooCommerce CRUD for deletion too
$order->delete_meta_data( '_fxw_delivery_boy_id' );
$order->add_order_note( __( 'Delivery boy unassigned.', 'foodxpress' ) );
$order->save();

// Clear any WooCommerce caches for this order
if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
wc_delete_shop_order_transients( $order_id );
}

set_transient( 'fxw_admin_notice', sprintf( __( 'Order #%s unassigned from delivery boy', 'foodxpress' ), $order->get_order_number() ), 30 );
}

wp_safe_redirect( wp_get_referer() );
exit;
}

/**
 * Handle order status updates from dashboard.
 *
 * @since 1.0.0
 */
public function update_order_status() {
if ( ! current_user_can( 'edit_shop_orders' ) ) {
wp_die( __( 'Unauthorized.', 'foodxpress' ) );
}

$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
if ( ! wp_verify_nonce( $nonce, 'fxw_update_status' ) ) {
wp_die( __( 'Invalid nonce.', 'foodxpress' ) );
}

$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';

$order = wc_get_order( $order_id );
if ( ! $order ) {
wp_die( __( 'Invalid order.', 'foodxpress' ) );
}

$valid_statuses = array( 'fxw-assigned', 'fxw-picked-up', 'completed', 'cancelled' );
if ( in_array( $new_status, $valid_statuses, true ) ) {
$order->update_status( $new_status, __( 'Status updated from dashboard.', 'foodxpress' ) );
}

wp_safe_redirect( wp_get_referer() );
exit;
}

/**
 * Show admin notices for delivery actions.
 *
 * @since 1.0.0
 */
public function show_admin_notices() {
$notice = get_transient( 'fxw_admin_notice' );
if ( $notice ) {
delete_transient( 'fxw_admin_notice' );
?>
<div class="notice notice-success is-dismissible">
<p><?php echo esc_html( $notice ); ?></p>
</div>
<?php
}
}
}

new FXW_Dashboard();
