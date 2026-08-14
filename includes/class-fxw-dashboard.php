<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the admin dashboard functionality.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
 */
class FXW_Dashboard
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_dashboard_menu'));
		add_action('admin_post_fxw_assign_delivery', array($this, 'assign_delivery'));
		add_action('admin_post_fxw_update_order_status', array($this, 'update_order_status'));
		add_action('admin_notices', array($this, 'show_admin_notices'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		// AJAX handlers for modern dashboard experience
		add_action('wp_ajax_fxw_ajax_assign_delivery', array($this, 'ajax_assign_delivery'));
		add_action('wp_ajax_fxw_ajax_update_status', array($this, 'ajax_update_status'));
	}

	/**
	 * Adds the dashboard menu page.
	 *
	 * @since    1.0.0
	 */
	public function add_dashboard_menu()
	{
		add_menu_page(
			__('Deliveries Dashboard', 'foodxpress'),
			__('Deliveries', 'foodxpress'),
			'manage_woocommerce',
			'fxw-deliveries-dashboard',
			array($this, 'render_dashboard_page'),
			'dashicons-cart',
			25
		);
	}

	/**
	 * Renders the content of the dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function render_dashboard_page()
	{
		// Unassigned orders: exclude fxw-assigned status to prevent overlap
		$unassigned_orders = wc_get_orders(array(
			'limit' => 100,
			'status' => array('pending', 'processing', 'on-hold', 'fxw-in-kitchen'),
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_fxw_delivery_boy_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => '_fxw_delivery_boy_id',
					'value' => array('', '0', 0),
					'compare' => 'IN',
				),
			),
		));

		// Add orphaned orders (fxw-assigned status but no delivery boy)
		$orphaned_orders = wc_get_orders(array(
			'limit' => 100,
			'status' => array('fxw-assigned'),
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_fxw_delivery_boy_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => '_fxw_delivery_boy_id',
					'value' => array('', '0', 0),
					'compare' => 'IN',
				),
			),
		));

		// Combine unassigned and orphaned orders
		$unassigned_orders = array_merge($unassigned_orders, $orphaned_orders);

		$assigned_orders = wc_get_orders(array(
			'limit' => 100,
			'status' => array('fxw-assigned'),
			'meta_query' => array(
				array(
					'key' => '_fxw_delivery_boy_id',
					'value' => 0,
					'type' => 'NUMERIC',
					'compare' => '>',
				),
			),
		));

		$out_for_delivery = wc_get_orders(array(
			'limit' => 100,
			'status' => array('fxw-picked-up'),
			'meta_query' => array(
				array(
					'key' => '_fxw_delivery_boy_id',
					'value' => 0,
					'type' => 'NUMERIC',
					'compare' => '>',
				),
			),
		));
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<?php do_action('fxw_dashboard_content'); ?>

			<h2><?php esc_html_e('Unassigned Orders', 'foodxpress'); ?></h2>
			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th class="manage-column"><?php esc_html_e('Order', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Customer', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Status', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Payment', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Actions', 'foodxpress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($unassigned_orders)): ?>
						<?php $delivery_boys = get_users(array('role' => 'delivery_boy')); ?>
						<?php foreach ($unassigned_orders as $order): ?>
							<tr>
								<td><a
										href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">#<?php echo esc_html($order->get_order_number()); ?></a>
								</td>
								<td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
								<td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
								<td>
									<?php echo esc_html($order->get_payment_method_title()); ?>
									<?php if ('cod' === $order->get_payment_method()): ?>
										<br><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
									<?php endif; ?>
								</td>
								<td>
									<div class="fxw-action-buttons">
										<button type="button" class="button button-small fxw-print-receipt"
											data-order-id="<?php echo esc_attr($order->get_id()); ?>"
											title="<?php esc_attr_e('Print Receipt', 'foodxpress'); ?>">
											<span class="dashicons dashicons-media-text"></span>
											<span class="button-text"><?php esc_html_e('Print Receipt', 'foodxpress'); ?></span>
										</button>
										<?php if (!empty($delivery_boys)): ?>
											<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
												style="display:inline-block;">
												<?php wp_nonce_field('fxw_assign_delivery'); ?>
												<input type="hidden" name="action" value="fxw_assign_delivery" />
												<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
												<select name="delivery_boy_id" style="width:140px; margin-right:5px;"
													onchange="this.form.submit();">
													<option value=""><?php esc_html_e('Select Delivery Boy...', 'foodxpress'); ?></option>
													<?php foreach ($delivery_boys as $boy): ?>
														<option value="<?php echo esc_attr($boy->ID); ?>">
															<?php echo esc_html($boy->display_name); ?> (ID:
															<?php echo esc_html($boy->ID); ?>)
														</option>
													<?php endforeach; ?>
												</select>
												<noscript>
													<button type="submit"
														class="button button-small"><?php esc_html_e('Assign', 'foodxpress'); ?></button>
												</noscript>
											</form>
										<?php else: ?>
											<span style="color:#999;"><?php esc_html_e('No delivery boys available', 'foodxpress'); ?></span>
										<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="5"><?php esc_html_e('No unassigned orders.', 'foodxpress'); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:24px;"><?php esc_html_e('Assigned Orders', 'foodxpress'); ?></h2>
			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th class="manage-column"><?php esc_html_e('Order', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Customer', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Delivery Boy', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Status', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Payment', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Actions', 'foodxpress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($assigned_orders)): ?>
						<?php foreach ($assigned_orders as $order): ?>
							<?php
							$delivery_boy_id = $order->get_meta('_fxw_delivery_boy_id', true);
							$delivery_boy = $delivery_boy_id ? get_user_by('id', $delivery_boy_id) : null;
							?>
							<tr>
								<td><a
										href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">#<?php echo esc_html($order->get_order_number()); ?></a>
								</td>
								<td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
								<td><?php echo esc_html($delivery_boy ? $delivery_boy->display_name : '-'); ?></td>
								<td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
								<td>
									<?php echo esc_html($order->get_payment_method_title()); ?>
									<?php if ('cod' === $order->get_payment_method()): ?>
										<br><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
									<?php endif; ?>
								</td>
								<td>
									<div class="fxw-action-buttons">
										<button type="button" class="button button-small fxw-print-receipt"
											data-order-id="<?php echo esc_attr($order->get_id()); ?>"
											title="<?php esc_attr_e('Print Receipt', 'foodxpress'); ?>">
											<span class="dashicons dashicons-media-text"></span>
											<span class="button-text"><?php esc_html_e('Print Receipt', 'foodxpress'); ?></span>
										</button>
										<?php if ('fxw-assigned' === $order->get_status()): ?>
											<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
												style="display:inline-block; margin-right:5px;">
												<?php wp_nonce_field('fxw_update_status'); ?>
												<input type="hidden" name="action" value="fxw_update_order_status" />
												<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
												<input type="hidden" name="new_status" value="fxw-picked-up" />
												<button type="submit"
													class="button button-small"><?php esc_html_e('Picked Up', 'foodxpress'); ?></button>
											</form>
										<?php endif; ?>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
											style="display:inline-block;">
											<?php wp_nonce_field('fxw_assign_delivery'); ?>
											<input type="hidden" name="action" value="fxw_assign_delivery" />
											<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
											<input type="hidden" name="delivery_boy_id" value="" />
											<button type="submit"
												class="button button-small button-link-delete"><?php esc_html_e('Reassign', 'foodxpress'); ?></button>
										</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="6"><?php esc_html_e('No assigned orders.', 'foodxpress'); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:24px;"><?php esc_html_e('Out for Delivery', 'foodxpress'); ?></h2>
			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th class="manage-column"><?php esc_html_e('Order', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Customer', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Delivery Boy', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Status', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Payment', 'foodxpress'); ?></th>
						<th class="manage-column"><?php esc_html_e('Actions', 'foodxpress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($out_for_delivery)): ?>
						<?php foreach ($out_for_delivery as $order): ?>
							<?php
							$delivery_boy_id = $order->get_meta('_fxw_delivery_boy_id', true);
							$delivery_boy = $delivery_boy_id ? get_user_by('id', $delivery_boy_id) : null;
							?>
							<tr>
								<td><a
										href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">#<?php echo esc_html($order->get_order_number()); ?></a>
								</td>
								<td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
								<td><?php echo esc_html($delivery_boy ? $delivery_boy->display_name : '-'); ?></td>
								<td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
								<td>
									<?php echo esc_html($order->get_payment_method_title()); ?>
									<?php if ('cod' === $order->get_payment_method()): ?>
										<br><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
									<?php endif; ?>
								</td>
								<td>
									<div class="fxw-action-buttons">
										<button type="button" class="button button-small fxw-print-receipt"
											data-order-id="<?php echo esc_attr($order->get_id()); ?>"
											title="<?php esc_attr_e('Print Receipt', 'foodxpress'); ?>">
											<span class="dashicons dashicons-media-text"></span>
											<span class="button-text"><?php esc_html_e('Print Receipt', 'foodxpress'); ?></span>
										</button>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
											style="display:inline-block;">
											<?php wp_nonce_field('fxw_update_status'); ?>
											<input type="hidden" name="action" value="fxw_update_order_status" />
											<input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
											<input type="hidden" name="new_status" value="completed" />
											<button type="submit"
												class="button button-small button-primary"><?php esc_html_e('Delivered', 'foodxpress'); ?></button>
										</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="6"><?php esc_html_e('No orders out for delivery.', 'foodxpress'); ?></td>
						</tr>
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
	public function assign_delivery()
	{
		if (!current_user_can('edit_shop_orders')) {
			wp_die(__('Unauthorized.', 'foodxpress'));
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw_assign_delivery')) {
			wp_die(__('Invalid nonce.', 'foodxpress'));
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$delivery_boy_id = isset($_POST['delivery_boy_id']) ? absint($_POST['delivery_boy_id']) : 0;

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_die(__('Invalid order.', 'foodxpress'));
		}

		// Log before assignment
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->debug(sprintf('assign_delivery: Order #%d, delivery_boy_id=%d, current_status=%s', $order_id, $delivery_boy_id, $order->get_status()), array('source' => 'foodxpress'));
		}

		if ($delivery_boy_id) {
			$delivery_boy = get_user_by('id', $delivery_boy_id);
			$delivery_boy_name = $delivery_boy ? $delivery_boy->display_name : "ID: {$delivery_boy_id}";

			$order->update_meta_data('_fxw_delivery_boy_id', $delivery_boy_id);
			$order->update_status('fxw-assigned', sprintf(__('Order assigned to delivery boy: %s', 'foodxpress'), $delivery_boy_name));
			$order->save();

			if (function_exists('wc_get_logger')) {
				wc_get_logger()->debug(sprintf('assign_delivery: Order #%d saved, new_status=%s, delivery_boy_id=%d', $order_id, $order->get_status(), $delivery_boy_id), array('source' => 'foodxpress'));
			}

			set_transient('fxw_admin_notice', sprintf(__('Order #%s successfully assigned to %s', 'foodxpress'), $order->get_order_number(), $delivery_boy_name), 30);
		} else {
			$order->delete_meta_data('_fxw_delivery_boy_id');
			$order->add_order_note(__('Delivery boy unassigned.', 'foodxpress'));
			$order->save();

			set_transient('fxw_admin_notice', sprintf(__('Order #%s unassigned from delivery boy', 'foodxpress'), $order->get_order_number()), 30);
		}

		// Clear WooCommerce caches for this order
		if (function_exists('wc_delete_shop_order_transients')) {
			wc_delete_shop_order_transients($order_id);
		}

		$referer = wp_get_referer();
		wp_safe_redirect($referer ? $referer : admin_url());
		exit;
	}

	/**
	 * Handle order status updates from dashboard.
	 *
	 * @since 1.0.0
	 */
	public function update_order_status()
	{
		if (!current_user_can('edit_shop_orders')) {
			wp_die(__('Unauthorized.', 'foodxpress'));
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw_update_status')) {
			wp_die(__('Invalid nonce.', 'foodxpress'));
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_die(__('Invalid order.', 'foodxpress'));
		}

		$valid_statuses = array('fxw-assigned', 'fxw-picked-up', 'completed', 'cancelled');
		if (in_array($new_status, $valid_statuses, true)) {
			$order->update_status($new_status, __('Status updated from dashboard.', 'foodxpress'));
		}

		$referer = wp_get_referer();
		wp_safe_redirect($referer ? $referer : admin_url());
		exit;
	}

	/**
	 * Enqueue admin scripts for the dashboard.
	 *
	 * @since 1.0.1
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Only load on our deliveries dashboard page
		if ('toplevel_page_fxw-deliveries-dashboard' !== $hook) {
			return;
		}

		// Enqueue jQuery
		wp_enqueue_script('jquery');

		// Enqueue our print receipt JavaScript
		wp_enqueue_script(
			'fxw-admin-dashboard',
			FXW_PLUGIN_URL . 'assets/js/delivery-dashboard.js',
			array('jquery'),
			FXW_VERSION,
			true
		);

		// Enqueue AJAX dashboard functionality
		wp_enqueue_script(
			'fxw-ajax-dashboard',
			FXW_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array('jquery'),
			FXW_VERSION,
			true
		);

		// Localize script for AJAX dashboard
		wp_localize_script('fxw-ajax-dashboard', 'fxwDashboard', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fxw_dashboard_nonce'),
		));

		// Localize script for legacy
		wp_localize_script('fxw-admin-dashboard', 'fxw_checkout_params', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fxw_nonce'),
		));
	}

	/**
	 * Show admin notices for delivery actions.
	 *
	 * @since 1.0.0
	 */
	public function show_admin_notices()
	{
		$notice = get_transient('fxw_admin_notice');
		if ($notice) {
			delete_transient('fxw_admin_notice');
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html($notice); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler for delivery assignment.
	 *
	 * @since 1.1.0
	 */
	public function ajax_assign_delivery()
	{
		// Security checks
		if (!current_user_can('edit_shop_orders')) {
			wp_send_json_error(array('message' => __('Unauthorized.', 'foodxpress')), 403);
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw_dashboard_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'foodxpress')), 403);
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$delivery_boy_id = isset($_POST['delivery_boy_id']) ? absint($_POST['delivery_boy_id']) : 0;

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(array('message' => __('Invalid order.', 'foodxpress')), 400);
		}

		if ($delivery_boy_id) {
			$delivery_boy = get_user_by('id', $delivery_boy_id);
			$delivery_boy_name = $delivery_boy ? $delivery_boy->display_name : "ID: {$delivery_boy_id}";

			$order->update_meta_data('_fxw_delivery_boy_id', $delivery_boy_id);
			$order->update_status('fxw-assigned', sprintf(__('Order assigned to delivery boy: %s', 'foodxpress'), $delivery_boy_name));
			$order->save();

			if (function_exists('wc_delete_shop_order_transients')) {
				wc_delete_shop_order_transients($order_id);
			}

			wp_send_json_success(array(
				'message' => sprintf(__('Order #%s assigned to %s', 'foodxpress'), $order->get_order_number(), $delivery_boy_name),
				'order_id' => $order_id,
				'delivery_boy_id' => $delivery_boy_id,
				'delivery_boy_name' => $delivery_boy_name,
				'new_status' => 'fxw-assigned',
				'status_label' => wc_get_order_status_name('fxw-assigned'),
			));
		} else {
			$order->delete_meta_data('_fxw_delivery_boy_id');
			$order->add_order_note(__('Delivery boy unassigned.', 'foodxpress'));
			$order->save();

			if (function_exists('wc_delete_shop_order_transients')) {
				wc_delete_shop_order_transients($order_id);
			}

			wp_send_json_success(array(
				'message' => sprintf(__('Order #%s unassigned', 'foodxpress'), $order->get_order_number()),
				'order_id' => $order_id,
				'delivery_boy_id' => 0,
				'delivery_boy_name' => '',
			));
		}
	}

	/**
	 * AJAX handler for order status updates.
	 *
	 * @since 1.1.0
	 */
	public function ajax_update_status()
	{
		// Security checks
		if (!current_user_can('edit_shop_orders')) {
			wp_send_json_error(array('message' => __('Unauthorized.', 'foodxpress')), 403);
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'fxw_dashboard_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'foodxpress')), 403);
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		$new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(array('message' => __('Invalid order.', 'foodxpress')), 400);
		}

		$valid_statuses = array('fxw-assigned', 'fxw-picked-up', 'completed', 'cancelled');
		if (!in_array($new_status, $valid_statuses, true)) {
			wp_send_json_error(array('message' => __('Invalid status.', 'foodxpress')), 400);
		}

		$order->update_status($new_status, __('Status updated from dashboard.', 'foodxpress'));

		wp_send_json_success(array(
			'message' => sprintf(__('Order #%s updated to %s', 'foodxpress'), $order->get_order_number(), wc_get_order_status_name($new_status)),
			'order_id' => $order_id,
			'new_status' => $new_status,
			'status_label' => wc_get_order_status_name($new_status),
		));
	}
}

new FXW_Dashboard();
