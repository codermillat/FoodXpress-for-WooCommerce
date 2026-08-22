<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the admin-specific functionality for orders.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://millat.is-a.dev/>
 */
class FXW_Order_Admin
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'add_delivery_meta_box'));
		add_action('woocommerce_process_shop_order_meta', array($this, 'save_delivery_meta_box_data'));
		add_action('template_redirect', array($this, 'print_receipt_template'));
		add_action('admin_init', array($this, 'handle_order_actions'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
	}

	/**
	 * Handle order actions.
	 *
	 * @since    1.0.0
	 */
	public function handle_order_actions()
	{
		if (isset($_GET['fxw_action']) && isset($_GET['order_id']) && isset($_GET['_wpnonce'])) {
			if (!current_user_can('edit_shop_orders')) {
				wp_die(__('Unauthorized.', 'foodxpress'));
			}

			$nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
			if (!wp_verify_nonce($nonce, 'fxw_order_action')) {
				wp_die(__('Invalid nonce.', 'foodxpress'));
			}

			$order_id = absint($_GET['order_id']);
			$order = wc_get_order($order_id);

			if (!$order) {
				wp_die(__('Invalid order.', 'foodxpress'));
			}

			switch (sanitize_text_field(wp_unslash($_GET['fxw_action']))) {
				case 'reject':
					$order->update_status('cancelled', __('Order rejected by admin.', 'foodxpress'));
					break;
case 'reassign':
						// Reassign also drops the rider meta AND reverts
						// the order status so it doesn't sit in fxw-assigned
						// with no rider (1.2.16).
						$order->delete_meta_data('_fxw_delivery_boy_id');
						$revert_to = get_post_status_object('wc-fxw-in-kitchen') ? 'fxw-in-kitchen' : 'processing';
						$order->update_status($revert_to, __('Delivery boy has been unassigned — order returned to kitchen.', 'foodxpress'));
						$order->save();
						break;
			}

			wp_safe_redirect(remove_query_arg(array('fxw_action', '_wpnonce')));
			exit;
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Load on both legacy (post.php) and HPOS (woocommerce_page_wc-orders) edit pages
		$is_legacy_order = in_array($hook, array('post.php', 'post-new.php'), true);
		$is_hpos_order = 'woocommerce_page_wc-orders' === $hook;

		if (!$is_legacy_order && !$is_hpos_order) {
			return;
		}

		if ($is_legacy_order) {
			global $post;
			if (!$post || 'shop_order' !== $post->post_type) {
				return;
			}
		}

		// Enqueue delivery dashboard script for order admin pages
		wp_enqueue_script(
			'fxw-delivery-dashboard',
			plugin_dir_url(__FILE__) . '../assets/js/delivery-dashboard.js',
			array('jquery'),
			FXW_VERSION,
			true
		);

		// Localize script with AJAX parameters
		wp_localize_script('fxw-delivery-dashboard', 'fxw_checkout_params', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fxw_print_receipt'),
		));
	}

	/**
	 * Load the receipt template.
	 *
	 * @since    1.0.0
	 */
	public function print_receipt_template()
	{
		if (!isset($_GET['fxw_print_receipt'])) {
			return;
		}

		// Security: Verify nonce to prevent CSRF
		if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fxw_print_receipt')) {
			wp_die(__('Security verification failed. Please try again.', 'foodxpress'));
		}

		if (!current_user_can('edit_shop_orders')) {
			wp_die(__('Unauthorized.', 'foodxpress'));
		}

		$order_id = absint($_GET['fxw_print_receipt']);
		$order = wc_get_order($order_id);

		if (!$order) {
			wp_die(__('Invalid order.', 'foodxpress'));
		}

		// Set the global order variable for the template.
		$GLOBALS['order'] = $order;

		include_once FXW_PLUGIN_DIR . 'templates/receipt-template.php';
		exit;
	}

	/**
	 * Adds the meta box to the order edit screen.
	 *
	 * @since    1.0.0
	 */
	public function add_delivery_meta_box()
	{
		// Register on both legacy post type and HPOS screen
		$screens = array('shop_order');
		if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
			$screens[] = 'woocommerce_page_wc-orders';
		}

		foreach ($screens as $screen) {
			add_meta_box(
				'fxw_delivery_meta_box',
				__('FoodXpress Delivery', 'foodxpress'),
				array($this, 'render_delivery_meta_box'),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Renders the content of the meta box.
	 *
	 * @param   WP_Post   $post    The post object.
	 * @since   1.0.0
	 */
	public function render_delivery_meta_box($post_or_order)
	{
		wp_nonce_field('fxw_save_delivery_meta_box_data', 'fxw_meta_box_nonce');

		// HPOS compatibility: accept WC_Order or WP_Post
		if ($post_or_order instanceof WC_Order) {
			$order = $post_or_order;
			$order_id = $order->get_id();
		} else {
			$order = wc_get_order($post_or_order->ID);
			$order_id = $post_or_order->ID;
		}

		if (!$order) {
			return;
		}
		$delivery_boy_id = $order->get_meta('_fxw_delivery_boy_id', true);
		$delivery_boys = get_users(array('role' => 'delivery_boy'));
		$payment_method = $order->get_payment_method_title();
		$shipping_address = $order->get_formatted_shipping_address();

		// Get new delivery details
		$delivery_address = $order->get_meta('_fxw_delivery_address', true);
		$delivery_lat = $order->get_meta('_fxw_delivery_lat', true);
		$delivery_lng = $order->get_meta('_fxw_delivery_lng', true);
		$delivery_distance = $order->get_meta('_fxw_delivery_distance', true);

		// (Legacy _fxw_address_unit read removed in 1.2.16 — the field was
		// never rendered, and the live _fxw_address_details + _fxw_landmark
		// fields cover the same need.)
		?>
		<?php if ($delivery_address): ?>
			<div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
				<p><strong><?php esc_html_e('Delivery Address:', 'foodxpress'); ?></strong><br>
					<?php echo esc_html($delivery_address); ?></p>

				<?php if ($delivery_distance): ?>
					<p><strong><?php esc_html_e('Delivery Distance:', 'foodxpress'); ?></strong>
						<?php echo esc_html($delivery_distance); ?> km</p>
				<?php endif; ?>

				<?php if ($delivery_lat && $delivery_lng): ?>
					<p><strong><?php esc_html_e('Coordinates:', 'foodxpress'); ?></strong>
						<?php echo esc_html($delivery_lat); ?>, <?php echo esc_html($delivery_lng); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ($unit): ?>
			<p><strong><?php esc_html_e('Flat/House/Unit:', 'foodxpress'); ?></strong> <?php echo esc_html($unit); ?></p>
		<?php endif; ?>
		<p>
			<strong><?php esc_html_e('Payment Method:', 'foodxpress'); ?></strong><br>
			<?php echo esc_html($payment_method); ?>
			<?php if ('cod' === $order->get_payment_method()): ?>
				<br><strong><?php esc_html_e('Amount to Collect:', 'foodxpress'); ?></strong>
				<?php echo wp_kses_post($order->get_formatted_order_total()); ?>
			<?php endif; ?>
		</p>
		<p>
			<label for="fxw_delivery_boy_id"><?php esc_html_e('Assign Delivery Boy', 'foodxpress'); ?></label>
			<?php if (!empty($delivery_boys)): ?>
				<select name="fxw_delivery_boy_id" id="fxw_delivery_boy_id" style="width:100%;">
					<option value=""><?php esc_html_e('Select a Delivery Boy', 'foodxpress'); ?></option>
					<?php foreach ($delivery_boys as $boy): ?>
						<option value="<?php echo esc_attr($boy->ID); ?>" <?php selected($delivery_boy_id, $boy->ID); ?>>
							<?php echo esc_html($boy->display_name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else: ?>
			<p><?php esc_html_e('No delivery boys found. Please create a user with the "Delivery Boy" role.', 'foodxpress'); ?></p>
		<?php endif; ?>
		</p>
		<p>
			<?php
			// Create precise map link using coordinates if available
			if ($delivery_lat && $delivery_lng) {
				$map_url = "https://www.google.com/maps?q=" . urlencode($delivery_lat . ',' . $delivery_lng);
				$map_label = __('Open Exact Location', 'foodxpress');
			} else {
				// Fallback to address-based search
				$map_url = "https://www.google.com/maps/search/?api=1&query=" . urlencode($shipping_address);
				$map_label = __('Search Location', 'foodxpress');
			}
			?>
			<a href="<?php echo esc_url($map_url); ?>" target="_blank" rel="noopener noreferrer" class="button"><?php echo esc_html($map_label); ?></a>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('fxw_print_receipt', $order_id, site_url()), 'fxw_print_receipt')); ?>"
				target="_blank" class="button"><?php esc_html_e('Print Receipt', 'foodxpress'); ?></a>
		</p>
		<p>
			<?php
			$edit_link = get_edit_post_link($order_id, 'raw');
		if (!$edit_link) {
			$edit_link = admin_url('admin.php?page=wc-orders&action=edit&id=' . absint($order_id));
		}
			?>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('fxw_action', 'reject', $edit_link), 'fxw_order_action')); ?>"
				class="button button-danger"><?php esc_html_e('Reject Order', 'foodxpress'); ?></a>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('fxw_action', 'reassign', $edit_link), 'fxw_order_action')); ?>"
				class="button"><?php esc_html_e('Re-assign', 'foodxpress'); ?></a>
		</p>
		<?php
	}

	/**
	 * Saves the data from the meta box.
	 *
	 * @param   int   $post_id    The post ID.
	 * @since   1.0.0
	 */
	public function save_delivery_meta_box_data($post_id)
	{
		if (!isset($_POST['fxw_meta_box_nonce'])) {
			return;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST['fxw_meta_box_nonce']));
		if (!wp_verify_nonce($nonce, 'fxw_save_delivery_meta_box_data')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_shop_orders')) {
			return;
		}

		$order = wc_get_order($post_id);
		if (!$order) {
			return;
		}

		// (fxw_address_unit save branch removed in 1.2.16 — the input was never
// rendered in the meta box; legacy meta is preserved in WC meta store.)

		if (isset($_POST['fxw_delivery_boy_id'])) {
			$new_id = absint($_POST['fxw_delivery_boy_id']);
			$prev_id = (int) $order->get_meta('_fxw_delivery_boy_id', true);

			if ($new_id) {
				$order->update_meta_data('_fxw_delivery_boy_id', $new_id);
			} else {
				$order->delete_meta_data('_fxw_delivery_boy_id');
			}

			$order->save();

			if ($new_id && $new_id !== $prev_id) {
				$options = get_option('fxw_settings');
				$auto_assign = true;
				if (is_array($options) && isset($options['fxw_auto_set_assigned_status'])) {
					$auto_assign = in_array($options['fxw_auto_set_assigned_status'], array('yes', 'true', 1, '1'), true);
				}
				if ($auto_assign) {
					$order->update_status('fxw-assigned', __('Order assigned to delivery boy.', 'foodxpress'));
				} else {
					$order->add_order_note(__('Delivery boy assigned (status unchanged).', 'foodxpress'));
				}
			}
		} else {
			$order->save();
		}
	}
}

new FXW_Order_Admin();
