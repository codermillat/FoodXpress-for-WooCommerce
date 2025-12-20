<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Manages the admin-specific functionality for orders.
 *
 * @since      1.0.0
 * @package    FoodXpress
 * @author     MD MILLAT HOSEN <https://github.com/codermillat>
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

			if (!wp_verify_nonce($_GET['_wpnonce'], 'fxw_order_action')) {
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
					$order->delete_meta_data('_fxw_delivery_boy_id');
					$order->save();
					$order->add_order_note(__('Delivery boy has been unassigned.', 'foodxpress'));
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
		// Only load on order edit pages
		if ('post.php' !== $hook && 'post-new.php' !== $hook) {
			return;
		}

		global $post;
		if (!$post || 'shop_order' !== $post->post_type) {
			return;
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

		// Set the global order variable for the template
		global $order;
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
		add_meta_box(
			'fxw_delivery_meta_box',
			__('FoodXpress Delivery', 'foodxpress'),
			array($this, 'render_delivery_meta_box'),
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
	public function render_delivery_meta_box($post)
	{
		wp_nonce_field('fxw_save_delivery_meta_box_data', 'fxw_meta_box_nonce');

		$order = wc_get_order($post->ID);
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

		// Fallback to old unit field for backward compatibility
		$unit = $order->get_meta('_fxw_address_unit', true);
		?>
		<?php if ($delivery_address): ?>
			<div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
				<p><strong><?php _e('Delivery Address:', 'foodxpress'); ?></strong><br>
					<?php echo esc_html($delivery_address); ?></p>

				<?php if ($delivery_distance): ?>
					<p><strong><?php _e('Delivery Distance:', 'foodxpress'); ?></strong>
						<?php echo esc_html($delivery_distance); ?> km</p>
				<?php endif; ?>

				<?php if ($delivery_lat && $delivery_lng): ?>
					<p><strong><?php _e('Coordinates:', 'foodxpress'); ?></strong>
						<?php echo esc_html($delivery_lat); ?>, <?php echo esc_html($delivery_lng); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ($unit): ?>
			<p><strong><?php _e('Flat/House/Unit:', 'foodxpress'); ?></strong> <?php echo esc_html($unit); ?></p>
		<?php endif; ?>
		<p>
			<strong><?php _e('Payment Method:', 'foodxpress'); ?></strong><br>
			<?php echo esc_html($payment_method); ?>
			<?php if ('cod' === $order->get_payment_method()): ?>
				<br><strong><?php _e('Amount to Collect:', 'foodxpress'); ?></strong>
				<?php echo wp_kses_post($order->get_formatted_order_total()); ?>
			<?php endif; ?>
		</p>
		<p>
			<label for="fxw_delivery_boy_id"><?php _e('Assign Delivery Boy', 'foodxpress'); ?></label>
			<?php if (!empty($delivery_boys)): ?>
				<select name="fxw_delivery_boy_id" id="fxw_delivery_boy_id" style="width:100%;">
					<option value=""><?php _e('Select a Delivery Boy', 'foodxpress'); ?></option>
					<?php foreach ($delivery_boys as $boy): ?>
						<option value="<?php echo esc_attr($boy->ID); ?>" <?php selected($delivery_boy_id, $boy->ID); ?>>
							<?php echo esc_html($boy->display_name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else: ?>
			<p><?php _e('No delivery boys found. Please create a user with the "Delivery Boy" role.', 'foodxpress'); ?></p>
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
			<a href="<?php echo esc_url($map_url); ?>" target="_blank" class="button"><?php echo esc_html($map_label); ?></a>
			<a href="#" class="button"
				onclick="window.open('<?php echo esc_url(add_query_arg('fxw_print_receipt', $post->ID)); ?>', '_blank'); return false;"><?php _e('Print Receipt', 'foodxpress'); ?></a>
		</p>
		<p>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('fxw_action', 'reject', get_edit_post_link($post->ID)), 'fxw_order_action')); ?>"
				class="button button-danger"><?php _e('Reject Order', 'foodxpress'); ?></a>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('fxw_action', 'reassign', get_edit_post_link($post->ID)), 'fxw_order_action')); ?>"
				class="button"><?php _e('Re-assign', 'foodxpress'); ?></a>
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

		if (!wp_verify_nonce($_POST['fxw_meta_box_nonce'], 'fxw_save_delivery_meta_box_data')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		// Save fxw_address_unit from checkout/order meta
		if (isset($_POST['fxw_address_unit'])) {
			update_post_meta($post_id, '_fxw_address_unit', sanitize_text_field($_POST['fxw_address_unit']));
		}

		if (isset($_POST['fxw_delivery_boy_id'])) {
			$new_id = sanitize_text_field($_POST['fxw_delivery_boy_id']);
			$order = wc_get_order($post_id);
			$prev_id = $order ? $order->get_meta('_fxw_delivery_boy_id', true) : '';

			if ($new_id) {
				update_post_meta($post_id, '_fxw_delivery_boy_id', $new_id);
			} else {
				delete_post_meta($post_id, '_fxw_delivery_boy_id');
			}

			if ($new_id && $new_id !== $prev_id) {
				if ($order) {
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
			}
		}
	}
}

new FXW_Order_Admin();
