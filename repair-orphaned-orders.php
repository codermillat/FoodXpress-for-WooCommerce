<?php
/**
 * One-time repair utility for orphaned "Assigned" orders
 * Run this by visiting: http://your-site.com/wp-content/plugins/FoodXpress%20for%20WooCommerce/repair-orphaned-orders.php
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access. Please login as administrator.' );
}

echo '<h1>FoodXpress - Repair Orphaned Orders</h1>';
echo '<p>This utility finds orders with "Assigned" status but missing delivery boy assignment.</p>';

// Check for auto-repair parameter
$auto_repair = isset( $_GET['repair'] ) && $_GET['repair'] === 'normalize';
if ( $auto_repair ) {
    echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">';
    echo '<h3>🔧 Auto-Repair Mode: Normalizing Meta Values</h3>';
}

// Find orphaned orders
$orphaned_orders = wc_get_orders( array(
    'limit'      => -1,
    'status'     => array( 'fxw-assigned' ),
    'meta_query' => array(
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

echo '<h2>Orphaned Orders Found: ' . count( $orphaned_orders ) . '</h2>';

if ( ! empty( $orphaned_orders ) ) {
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Status</th><th>Current Meta</th><th>All Meta Values</th><th>Action</th></tr>';
    
    $repaired_count = 0;
    
    foreach ( $orphaned_orders as $order ) {
        $order_id = $order->get_id();
        $order = wc_get_order( $order_id );
$meta_value = $order ? $order->get_meta( '_fxw_delivery_boy_id', true ) : '';
        $order = wc_get_order( $order_id );
$all_meta_values = $order ? $order->get_meta( '_fxw_delivery_boy_id', false ) : array();
        
        $meta_display = $meta_value ? $meta_value : 'NOT SET';
        $all_meta_display = implode( ', ', array_map( function($v) { return $v === '' ? 'EMPTY' : $v; }, $all_meta_values ) );
        
        // Auto-repair logic
        $action = 'Needs Assignment';
        if ( $auto_repair && count( $all_meta_values ) > 1 ) {
            // Find the best value (highest numeric > 0)
            $numeric_values = array_filter( $all_meta_values, function($v) { return is_numeric($v) && $v > 0; } );
            if ( ! empty( $numeric_values ) ) {
                $best_value = max( $numeric_values );
                
                // Delete all meta and set the best one
                delete_post_meta( $order_id, '_fxw_delivery_boy_id' );
                update_post_meta( $order_id, '_fxw_delivery_boy_id', $best_value );
                
                $action = "✅ REPAIRED - Set to $best_value";
                $meta_display = $best_value;
                $all_meta_display = $best_value;
                $repaired_count++;
            }
        }
        
        echo '<tr>';
        echo '<td><a href="' . esc_url( get_edit_post_link( $order_id ) ) . '" target="_blank">#' . esc_html( $order->get_order_number() ) . '</a></td>';
        echo '<td>' . esc_html( $order->get_formatted_billing_full_name() ) . '</td>';
        echo '<td>' . esc_html( $order->get_date_created()->format( 'Y-m-d H:i' ) ) . '</td>';
        echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
        echo '<td>' . esc_html( $meta_display ) . '</td>';
        echo '<td>' . esc_html( $all_meta_display ) . '</td>';
        echo '<td>' . $action . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    if ( $auto_repair ) {
        echo '</div>';
        echo '<p><strong>✅ Auto-repair completed! Repaired: ' . $repaired_count . ' orders</strong></p>';
        if ( $repaired_count > 0 ) {
            echo '<p><a href="' . strtok( $_SERVER['REQUEST_URI'], '?' ) . '">🔄 Refresh to see updated results</a></p>';
        }
    } else {
        echo '<p><a href="' . esc_url( add_query_arg( 'repair', 'normalize' ) ) . '" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px;">🔧 Auto-Repair Meta Duplicates</a></p>';
    }
    
    echo '<h3>How to Fix:</h3>';
    echo '<ol>';
    echo '<li>Go to Admin → <a href="' . esc_url( admin_url( 'admin.php?page=fxw-deliveries-dashboard' ) ) . '" target="_blank">Deliveries Dashboard</a></li>';
    echo '<li>These orders should now appear in the "Unassigned Orders" section</li>';
    echo '<li>Assign a delivery boy to each order using the dropdown and "Assign" button</li>';
    echo '<li>Once assigned, they will move to "Assigned Orders" and appear on the delivery boy\'s dashboard</li>';
    echo '</ol>';
    
    echo '<p><strong>Note:</strong> The dashboard query has been updated to include "fxw-assigned" orders without delivery boy assignment in the Unassigned section.</p>';
    
} else {
    echo '<p>✅ No orphaned orders found. All "Assigned" orders have proper delivery boy assignments.</p>';
}

echo '<hr>';
echo '<h2>Additional Verification</h2>';

// Check all orders with delivery boy assignments
$assigned_with_meta = wc_get_orders( array(
    'limit'      => -1,
    'status'     => array( 'fxw-assigned' ),
    'meta_query' => array(
        array(
            'key'     => '_fxw_delivery_boy_id',
            'value'   => 0,
            'type'    => 'NUMERIC',
            'compare' => '>',
        ),
    ),
) );

echo '<p>Orders with "Assigned" status AND delivery boy meta: ' . count( $assigned_with_meta ) . '</p>';

// Check delivery boys
$delivery_boys = get_users( array( 'role' => 'delivery_boy' ) );
echo '<p>Available delivery boys: ' . count( $delivery_boys ) . '</p>';

if ( ! empty( $delivery_boys ) ) {
    echo '<ul>';
    foreach ( $delivery_boys as $boy ) {
        echo '<li>' . esc_html( $boy->display_name ) . ' (ID: ' . $boy->ID . ')</li>';
    }
    echo '</ul>';
}

echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fxw-deliveries-dashboard' ) ) . '">← Back to Deliveries Dashboard</a></p>';
