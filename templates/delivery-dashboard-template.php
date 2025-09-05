<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php echo esc_url( FXW_PLUGIN_URL . 'assets/css/frontend.css' ); ?>" type="text/css" media="all" />
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php
    $delivery_boy_id = get_current_user_id();
    $new_orders = wc_get_orders( array(
        'limit' => -1,
        'meta_key' => '_fxw_delivery_boy_id',
        'meta_value' => $delivery_boy_id,
        'status' => 'fxw-assigned',
    ) );
    $picked_up_orders = wc_get_orders( array(
        'limit' => -1,
        'meta_key' => '_fxw_delivery_boy_id',
        'meta_value' => $delivery_boy_id,
        'status' => 'fxw-picked-up',
    ) );
    ?>
    <div class="fxw-app-dashboard">
        <header class="fxw-app-header">
            <h1><?php _e( 'My Deliveries', 'foodxpress' ); ?></h1>
        </header>

        <div class="fxw-tabs">
            <button class="fxw-tab-link active" onclick="openTab(event, 'new-orders')"><?php _e( 'New', 'foodxpress' ); ?> (<?php echo count( $new_orders ); ?>)</button>
            <button class="fxw-tab-link" onclick="openTab(event, 'in-progress')"><?php _e( 'In Progress', 'foodxpress' ); ?> (<?php echo count( $picked_up_orders ); ?>)</button>
        </div>

        <main class="fxw-app-main">
            <div id="new-orders" class="fxw-tab-content active">
                <?php if ( $new_orders ) : ?>
                    <?php foreach ( $new_orders as $order ) : ?>
                        <?php fxw_render_order_card( $order ); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="fxw-no-orders">
                        <p><?php _e( 'No new deliveries assigned.', 'foodxpress' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="in-progress" class="fxw-tab-content">
                <?php if ( $picked_up_orders ) : ?>
                    <?php foreach ( $picked_up_orders as $order ) : ?>
                        <?php fxw_render_order_card( $order ); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="fxw-no-orders">
                        <p><?php _e( 'No deliveries in progress.', 'foodxpress' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
    function fxw_render_order_card( $order ) {
        $shipping_address = $order->get_formatted_shipping_address();
        
        // Get new delivery details
        $delivery_address = $order->get_meta( '_fxw_delivery_address', true );
        $delivery_lat = $order->get_meta( '_fxw_delivery_lat', true );
        $delivery_lng = $order->get_meta( '_fxw_delivery_lng', true );
        $delivery_distance = $order->get_meta( '_fxw_delivery_distance', true );
        
        // Fallback to old unit field for backward compatibility
        $unit = $order->get_meta( '_fxw_address_unit', true );
        $status = $order->get_status();
        ?>
        <div class="fxw-order-card">
            <div class="fxw-card-header">
                <h3><?php printf( __( 'Order #%s', 'foodxpress' ), $order->get_order_number() ); ?></h3>
                <span class="fxw-status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
            </div>
            <div class="fxw-card-body">
                <div class="fxw-card-section">
                    <p><span class="fxw-icon">&#128100;</span><strong><?php _e( 'Customer:', 'foodxpress' ); ?></strong> <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></p>
                    <p><span class="fxw-icon">&#128222;</span><strong><?php _e( 'Phone:', 'foodxpress' ); ?></strong> <a href="tel:<?php echo esc_attr( $order->get_billing_phone() ); ?>"><?php echo esc_html( $order->get_billing_phone() ); ?></a></p>
                    
                    <?php if ( $delivery_address ) : ?>
                        <p><span class="fxw-icon">&#127968;</span><strong><?php _e( 'Delivery Address:', 'foodxpress' ); ?></strong> <?php echo esc_html( $delivery_address ); ?></p>
                        <?php if ( $delivery_distance ) : ?>
                            <p><span class="fxw-icon">&#128207;</span><strong><?php _e( 'Distance:', 'foodxpress' ); ?></strong> <?php echo esc_html( $delivery_distance ); ?> km</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><span class="fxw-icon">&#127968;</span><strong><?php _e( 'Address:', 'foodxpress' ); ?></strong> <?php echo wp_kses_post( $shipping_address ); ?></p>
                        <?php if ( $unit ) : ?>
                            <p><span class="fxw-icon">&#128190;</span><strong><?php _e( 'Unit:', 'foodxpress' ); ?></strong> <?php echo esc_html( $unit ); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="fxw-card-section">
                    <p><span class="fxw-icon">&#128179;</span><strong><?php _e( 'Payment:', 'foodxpress' ); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></p>
                    <?php if ( 'cod' === $order->get_payment_method() ) : ?>
                        <p class="fxw-collect-amount"><span class="fxw-icon">&#128181;</span><strong><?php _e( 'Collect:', 'foodxpress' ); ?></strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="fxw-card-footer">
                <?php
                // Create precise map link using coordinates if available
                if ( $delivery_lat && $delivery_lng && is_numeric( $delivery_lat ) && is_numeric( $delivery_lng ) ) {
                    // Use precise coordinate-based map link for exact pinpoint location
                    $map_url = "https://www.google.com/maps?q=" . urlencode( trim( $delivery_lat ) . ',' . trim( $delivery_lng ) );
                    $map_label = __( 'Open Exact Location', 'foodxpress' );
                    $map_class = 'fxw-button-map-precise';
                } elseif ( $delivery_address ) {
                    // Use delivery address if coordinates are missing but delivery address exists
                    $map_url = "https://www.google.com/maps/search/?api=1&query=" . urlencode( trim( strip_tags( $delivery_address ) ) );
                    $map_label = __( 'Search Delivery Address', 'foodxpress' );
                    $map_class = 'fxw-button-map-address';
                } else {
                    // Final fallback to shipping address
                    $map_url = "https://www.google.com/maps/search/?api=1&query=" . urlencode( trim( strip_tags( $shipping_address ) ) );
                    $map_label = __( 'Search Location', 'foodxpress' );
                    $map_class = 'fxw-button-map-fallback';
                }
                ?>
                <a href="<?php echo esc_url( $map_url ); ?>" target="_blank" class="fxw-button fxw-button-map <?php echo esc_attr( $map_class ); ?>"><span class="fxw-icon">&#128205;</span><?php echo esc_html( $map_label ); ?></a>
                <?php if ( 'fxw-assigned' === $status ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fxw-action-form">
                        <?php wp_nonce_field( 'fxw_delivery_action' ); ?>
                        <input type="hidden" name="action" value="fxw_mark_picked_up" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
                        <button type="submit" class="fxw-button fxw-button-pickup"><span class="fxw-icon">&#128230;</span><?php _e( 'Mark Picked Up', 'foodxpress' ); ?></button>
                    </form>
                <?php endif; ?>
                <?php if ( 'fxw-picked-up' === $status ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fxw-action-form">
                        <?php wp_nonce_field( 'fxw_delivery_action' ); ?>
                        <input type="hidden" name="action" value="fxw_mark_delivered" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
                        <button type="submit" class="fxw-button fxw-button-deliver"><span class="fxw-icon">&#127937;</span><?php _e( 'Mark Delivered', 'foodxpress' ); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php } ?>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("fxw-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("fxw-tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        // Get the element with id="defaultOpen" and click on it
        document.querySelector(".fxw-tab-link.active").click();
    </script>
    <?php wp_footer(); ?>
</body>
</html>
