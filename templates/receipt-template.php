<?php
/**
 * Restaurant Bill Style Receipt Template for FoodXpress Orders
 *
 * @package FoodXpress
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get order data - must be set by print_receipt_template() after nonce/capability check.
// Do not fall back to $_GET; this template is only included after verification.
global $order;

// If still no order, exit with error
if (!$order || !is_a($order, 'WC_Order')) {
    wp_die(__('Invalid order.', 'foodxpress'));
}

// Get order data
$order_id = $order->get_id();
$order_number = $order->get_order_number();
$order_date = $order->get_date_created();
$billing_address = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$payment_method = $order->get_payment_method_title();
$order_total = $order->get_total();
$currency_symbol = get_woocommerce_currency_symbol($order->get_currency());

// Get delivery boy info
$delivery_boy_id = $order->get_meta('_fxw_delivery_boy_id');
$delivery_boy = $delivery_boy_id ? get_user_by('id', $delivery_boy_id) : null;

// Get FoodXpress receipt branding settings
$fxw_options = get_option('fxw_settings', array());

// Receipt Logo
$receipt_logo = isset($fxw_options['fxw_receipt_logo']) ? $fxw_options['fxw_receipt_logo'] : '';

// Restaurant Name - prioritize FXW setting, fallback to WooCommerce, then site name
$store_name = isset($fxw_options['fxw_receipt_restaurant_name']) && !empty($fxw_options['fxw_receipt_restaurant_name'])
    ? $fxw_options['fxw_receipt_restaurant_name']
    : get_bloginfo('name');

// Restaurant Address - prioritize FXW setting, fallback to WooCommerce store address
$receipt_address = isset($fxw_options['fxw_receipt_address']) && !empty($fxw_options['fxw_receipt_address'])
    ? $fxw_options['fxw_receipt_address']
    : '';

// If no FXW address, build from WooCommerce settings
if (empty($receipt_address)) {
    $store_address = get_option('woocommerce_store_address');
    $store_city = get_option('woocommerce_store_city');
    $store_postcode = get_option('woocommerce_store_postcode');
    $receipt_address = implode(', ', array_filter(array($store_address, $store_city, $store_postcode)));
}

// Restaurant Phone - prioritize FXW setting
$store_phone = isset($fxw_options['fxw_receipt_phone']) && !empty($fxw_options['fxw_receipt_phone'])
    ? $fxw_options['fxw_receipt_phone']
    : get_option('woocommerce_store_phone');

// Tagline (optional)
$receipt_tagline = isset($fxw_options['fxw_receipt_tagline']) ? $fxw_options['fxw_receipt_tagline'] : '';

// Footer Message
$receipt_footer = isset($fxw_options['fxw_receipt_footer_message']) && !empty($fxw_options['fxw_receipt_footer_message'])
    ? $fxw_options['fxw_receipt_footer_message']
    : __('Thank You! Have a great day!', 'foodxpress');

// Get unit/flat info
$unit = $order->get_meta('_fxw_address_unit');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php printf(esc_html__('Bill - Order #%s', 'foodxpress'), $order_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #fff;
            color: #000;
            font-size: 14px;
            line-height: 1.4;
        }

        .bill-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }

        .bill-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .restaurant-name {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .restaurant-address {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .bill-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .bill-info {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
        }

        .bill-row.bold {
            font-weight: bold;
        }

        .customer-info {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .customer-details {
            font-size: 12px;
            line-height: 1.3;
        }

        .items-section {
            margin-bottom: 15px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            align-items: flex-start;
        }

        .item-name {
            flex: 1;
            font-weight: bold;
            margin-right: 10px;
        }

        .item-meta {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        .item-qty-price {
            text-align: right;
            min-width: 80px;
            font-size: 12px;
        }

        .item-total {
            text-align: right;
            min-width: 60px;
            font-weight: bold;
        }

        .bill-totals {
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-bottom: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .total-row.final {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 8px 0;
            margin-top: 10px;
            font-size: 16px;
            font-weight: bold;
        }

        .payment-section {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px dashed #000;
        }

        .payment-method {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .amount-to-collect {
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }

        .delivery-section {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #000;
        }

        .bill-footer {
            text-align: center;
            font-size: 11px;
            border-top: 1px dashed #000;
            padding-top: 15px;
        }

        .thank-you {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .separator {
            text-align: center;
            margin: 15px 0;
            font-size: 16px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .bill-container {
                margin: 0;
                padding: 10px;
                max-width: none;
            }
        }
    </style>
    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</head>

<body>
    <div class="bill-container">
        <!-- Restaurant Header -->
        <div class="bill-header">
            <?php if ($receipt_logo): ?>
                <img src="<?php echo esc_url($receipt_logo); ?>" alt="<?php echo esc_attr($store_name); ?>"
                    class="receipt-logo" style="max-width: 180px; max-height: 60px; margin-bottom: 10px;">
            <?php endif; ?>
            <div class="restaurant-name"><?php echo esc_html($store_name); ?></div>
            <?php if ($receipt_tagline): ?>
                <div class="restaurant-tagline" style="font-size: 11px; font-style: italic; margin-bottom: 5px;">
                    <?php echo esc_html($receipt_tagline); ?></div>
            <?php endif; ?>
            <?php if ($receipt_address): ?>
                <div class="restaurant-address"><?php echo esc_html($receipt_address); ?></div>
            <?php endif; ?>
            <?php if ($store_phone): ?>
                <div class="restaurant-address">
                    <?php printf(esc_html__('Phone: %s', 'foodxpress'), esc_html($store_phone)); ?></div>
            <?php endif; ?>
            <div class="bill-title"><?php esc_html_e('Delivery Bill', 'foodxpress'); ?></div>
        </div>

        <!-- Bill Information -->
        <div class="bill-info">
            <div class="bill-row">
                <span><?php esc_html_e('Order No:', 'foodxpress'); ?></span>
                <span><?php echo esc_html($order_number); ?></span>
            </div>
            <div class="bill-row">
                <span><?php esc_html_e('Date:', 'foodxpress'); ?></span>
                <span><?php echo $order_date ? esc_html($order_date->format('j M Y g:i A')) : esc_html__('N/A', 'foodxpress'); ?></span>
            </div>
            <div class="bill-row">
                <span><?php esc_html_e('Status:', 'foodxpress'); ?></span>
                <span><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
            </div>
            <?php if ($delivery_boy): ?>
                <div class="bill-row">
                    <span><?php esc_html_e('Delivery By:', 'foodxpress'); ?></span>
                    <span><?php echo esc_html($delivery_boy->display_name); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer Information -->
        <div class="customer-info">
            <div class="section-title"><?php esc_html_e('Customer Details', 'foodxpress'); ?></div>
            <div class="customer-details">
                <div><strong><?php echo esc_html($order->get_formatted_billing_full_name()); ?></strong></div>
                <?php if ($order->get_billing_phone()): ?>
                    <div><?php echo esc_html($order->get_billing_phone()); ?></div>
                <?php endif; ?>
                <br>
                <div><strong><?php esc_html_e('Delivery Address:', 'foodxpress'); ?></strong></div>
                <div><?php echo esc_html(str_replace(array('<br>', '<br/>', '<br />'), ', ', wp_strip_all_tags($shipping_address))); ?></div>
                <?php if ($unit): ?>
                    <div><?php printf(esc_html__('Unit/Flat: %s', 'foodxpress'), esc_html($unit)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <div class="items-section">
            <div class="section-title"><?php esc_html_e('Items Ordered', 'foodxpress'); ?></div>
            <div class="separator">━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</div>

            <?php foreach ($order->get_items() as $item_id => $item): ?>
                <?php
                $product = $item->get_product();
                $item_total = $order->get_formatted_line_total($item);
                $quantity = $item->get_quantity();
                $unit_price = $quantity > 0 ? $item->get_total() / $quantity : 0;
                ?>
                <div class="item-row">
                    <div style="flex: 1;">
                        <div class="item-name"><?php echo esc_html($item->get_name()); ?></div>
                        <?php
                        // Show item meta (variations, add-ons, etc.)
                        $item_meta = wc_display_item_meta($item, array(
                            'before' => '',
                            'after' => '',
                            'separator' => ', ',
                            'echo' => false,
                        ));
                        if ($item_meta) {
                            echo '<div class="item-meta">' . esc_html(wp_strip_all_tags($item_meta)) . '</div>';
                        }
                        ?>
                        <div class="item-qty-price">
                            <?php printf(
                                esc_html__('%d × %s', 'foodxpress'),
                                $quantity,
                                wp_kses_post(wc_price($unit_price, array('currency' => $order->get_currency())))
                            ); ?>
                        </div>
                    </div>
                    <div class="item-total"><?php echo wp_kses_post($item_total); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Bill Totals -->
        <div class="bill-totals">
            <div class="total-row">
                <span><?php esc_html_e('Subtotal:', 'foodxpress'); ?></span>
                <span><?php echo wp_kses_post($order->get_subtotal_to_display()); ?></span>
            </div>

            <?php foreach ($order->get_order_item_totals() as $key => $total): ?>
                <?php if ('order_total' === $key)
                    continue; // Skip total, we'll show it separately ?>
                <div class="total-row">
                    <span><?php echo esc_html($total['label']); ?></span>
                    <span><?php echo wp_kses_post($total['value']); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="total-row final">
                <span><?php esc_html_e('TOTAL AMOUNT:', 'foodxpress'); ?></span>
                <span><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
            </div>
        </div>

        <!-- Payment Information -->
        <?php if ('cod' === $order->get_payment_method()): ?>
            <div class="payment-section">
                <div class="payment-method"><?php esc_html_e('Cash on Delivery', 'foodxpress'); ?></div>
                <div class="amount-to-collect">
                    <?php printf(esc_html__('COLLECT: %s', 'foodxpress'), wp_kses_post($order->get_formatted_order_total())); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="payment-section">
                <div class="payment-method"><?php echo esc_html(strtoupper($payment_method)); ?></div>
                <div><?php esc_html_e('PAYMENT COMPLETED', 'foodxpress'); ?></div>
            </div>
        <?php endif; ?>

        <?php
        // Show customer note if any
        $customer_note = $order->get_customer_note();
        if ($customer_note):
            ?>
            <div class="delivery-section">
                <div class="section-title"><?php esc_html_e('Special Instructions', 'foodxpress'); ?></div>
                <div style="font-size: 12px;"><?php echo esc_html($customer_note); ?></div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="bill-footer">
            <div class="thank-you"><?php echo esc_html($receipt_footer); ?></div>
            <br>
            <div><?php printf(esc_html__('Printed: %s', 'foodxpress'), current_time('j M Y g:i A')); ?></div>
        </div>
    </div>
</body>

</html>