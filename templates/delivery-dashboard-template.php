<?php
/**
 * Delivery Dashboard Template
 *
 * @package FoodXpress
 */

if (!defined('ABSPATH')) {
    exit;
}

// Auth check: must be logged in with delivery access
if (!is_user_logged_in() || !current_user_can('fxw_delivery_access')) {
    wp_redirect(home_url());
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#E85D04">
    <meta name="mobile-web-app-capable" content="yes">
    <?php wp_head(); ?>
</head>

<body <?php body_class('fxw-delivery-dashboard-page'); ?>>
    <?php
    $delivery_boy_id = get_current_user_id();
    $new_orders = wc_get_orders(array(
        'limit' => 50,
        'meta_key' => '_fxw_delivery_boy_id',
        'meta_value' => $delivery_boy_id,
        'status' => 'fxw-assigned',
    ));
    $picked_up_orders = wc_get_orders(array(
        'limit' => 50,
        'meta_key' => '_fxw_delivery_boy_id',
        'meta_value' => $delivery_boy_id,
        'status' => 'fxw-picked-up',
    ));
    ?>
    <div class="fxw-app-dashboard">
        <header class="fxw-app-header">
            <h1><?php esc_html_e('My Deliveries', 'foodxpress'); ?></h1>
        </header>

        <div class="fxw-tabs" role="tablist" aria-label="<?php esc_attr_e('Delivery status filters', 'foodxpress'); ?>">
            <button type="button" id="tab-new-orders" class="fxw-tab-link active" role="tab" aria-selected="true"
                aria-controls="new-orders" data-fxw-tab="new-orders"><?php esc_html_e('New', 'foodxpress'); ?>
                (<?php echo count($new_orders); ?>)</button>
            <button type="button" id="tab-in-progress" class="fxw-tab-link" role="tab" aria-selected="false"
                aria-controls="in-progress" data-fxw-tab="in-progress"><?php esc_html_e('In Progress', 'foodxpress'); ?>
                (<?php echo count($picked_up_orders); ?>)</button>
        </div>

        <main class="fxw-app-main">
            <div id="new-orders" class="fxw-tab-content active" role="tabpanel" aria-labelledby="tab-new-orders">
                <?php if ($new_orders): ?>
                    <?php foreach ($new_orders as $order): ?>
                        <?php fxw_render_order_card($order); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="fxw-no-orders">
                        <p><?php esc_html_e('No new deliveries assigned.', 'foodxpress'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="in-progress" class="fxw-tab-content" role="tabpanel" aria-labelledby="tab-in-progress">
                <?php if ($picked_up_orders): ?>
                    <?php foreach ($picked_up_orders as $order): ?>
                        <?php fxw_render_order_card($order); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="fxw-no-orders">
                        <p><?php esc_html_e('No deliveries in progress.', 'foodxpress'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
    if (!function_exists('fxw_render_order_card')) {
        function fxw_render_order_card($order)
        {
            $shipping_address = $order->get_formatted_shipping_address();

            // Get new delivery details
            $delivery_address = $order->get_meta('_fxw_delivery_address', true);
            $delivery_lat = $order->get_meta('_fxw_delivery_lat', true);
            $delivery_lng = $order->get_meta('_fxw_delivery_lng', true);
            $delivery_distance = $order->get_meta('_fxw_delivery_distance', true);

            // Fallback to old unit field for backward compatibility
            $unit = $order->get_meta('_fxw_address_unit', true);
            $status = $order->get_status();
            ?>
            <div class="fxw-order-card">
                <div class="fxw-card-header">
                    <h3><?php printf(esc_html__('Order #%s', 'foodxpress'), esc_html($order->get_order_number())); ?></h3>
                    <span
                        class="fxw-status-badge status-<?php echo esc_attr($status); ?>"><?php echo esc_html(wc_get_order_status_name($status)); ?></span>
                </div>
                <div class="fxw-card-body">
                    <div class="fxw-card-section">
                        <p><span
                                class="fxw-icon">&#128100;</span><strong><?php esc_html_e('Customer:', 'foodxpress'); ?></strong>
                            <?php echo esc_html($order->get_formatted_billing_full_name()); ?></p>
                        <p><span class="fxw-icon">&#128222;</span><strong><?php esc_html_e('Phone:', 'foodxpress'); ?></strong>
                            <a
                                href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>"><?php echo esc_html($order->get_billing_phone()); ?></a>
                        </p>

                        <?php if ($delivery_address): ?>
                            <p><span
                                    class="fxw-icon">&#127968;</span><strong><?php esc_html_e('Delivery Address:', 'foodxpress'); ?></strong>
                                <?php echo esc_html($delivery_address); ?></p>
                            <?php if ($delivery_distance): ?>
                                <p><span
                                        class="fxw-icon">&#128207;</span><strong><?php esc_html_e('Distance:', 'foodxpress'); ?></strong>
                                    <?php echo esc_html($delivery_distance); ?> km</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><span
                                    class="fxw-icon">&#127968;</span><strong><?php esc_html_e('Address:', 'foodxpress'); ?></strong>
                                <?php echo wp_kses_post($shipping_address); ?></p>
                            <?php if ($unit): ?>
                                <p><span class="fxw-icon">&#128190;</span><strong><?php esc_html_e('Unit:', 'foodxpress'); ?></strong>
                                    <?php echo esc_html($unit); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="fxw-card-section">
                        <p><span
                                class="fxw-icon">&#128179;</span><strong><?php esc_html_e('Payment:', 'foodxpress'); ?></strong>
                            <?php echo esc_html($order->get_payment_method_title()); ?></p>
                        <?php if ('cod' === $order->get_payment_method()): ?>
                            <p class="fxw-collect-amount"><span
                                    class="fxw-icon">&#128181;</span><strong><?php esc_html_e('Collect:', 'foodxpress'); ?></strong>
                                <?php echo wp_kses_post($order->get_formatted_order_total()); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fxw-card-footer">
                    <?php
                    // Create precise map link using coordinates if available
                    if ($delivery_lat && $delivery_lng && is_numeric($delivery_lat) && is_numeric($delivery_lng)) {
                        // Use precise coordinate-based map link for exact pinpoint location
                        $map_url = "https://www.google.com/maps?q=" . urlencode(trim($delivery_lat) . ',' . trim($delivery_lng));
                        $map_label = __('Open Exact Location', 'foodxpress');
                        $map_class = 'fxw-button-map-precise';
                    } elseif ($delivery_address) {
                        // Use delivery address if coordinates are missing but delivery address exists
                        $map_url = "https://www.google.com/maps/search/?api=1&query=" . urlencode(trim(strip_tags($delivery_address)));
                        $map_label = __('Search Delivery Address', 'foodxpress');
                        $map_class = 'fxw-button-map-address';
                    } else {
                        // Final fallback to shipping address
                        $map_url = "https://www.google.com/maps/search/?api=1&query=" . urlencode(trim(strip_tags($shipping_address)));
                        $map_label = __('Search Location', 'foodxpress');
                        $map_class = 'fxw-button-map-fallback';
                    }
                    ?>
                    <a href="<?php echo esc_url($map_url); ?>" target="_blank"
                        class="fxw-button fxw-button-map <?php echo esc_attr($map_class); ?>"><span
                            class="fxw-icon">&#128205;</span><?php echo esc_html($map_label); ?></a>
                    <?php if ('fxw-assigned' === $status): ?>
                        <button type="button" class="fxw-button fxw-button-pickup fxw-action-btn"
                            data-action="fxw_update_delivery_status" data-status="fxw-picked-up"
                            data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('fxw_delivery_action')); ?>">
                            <span class="fxw-icon">&#128230;</span><?php esc_html_e('Mark Picked Up', 'foodxpress'); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ('fxw-picked-up' === $status): ?>
                        <button type="button" class="fxw-button fxw-button-deliver fxw-action-btn"
                            data-action="fxw_update_delivery_status" data-status="completed"
                            data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('fxw_delivery_action')); ?>">
                            <span class="fxw-icon">&#127937;</span><?php esc_html_e('Mark Delivered', 'foodxpress'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php }
    } // end function_exists guard ?>

    <?php wp_footer(); ?>
</body>

</html>