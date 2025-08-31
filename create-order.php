<?php
require_once( __DIR__ . '/../../../../wp-load.php' );

$product_id = 15;
$quantity = 2;
$customer_id = 1;

$order = wc_create_order(array('customer_id' => $customer_id));
$order->add_product(wc_get_product($product_id), $quantity);
$order->set_address(array(
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'company'    => '',
    'email'      => 'john.doe@example.com',
    'phone'      => '123456789',
    'address_1'  => '123 Main St',
    'address_2'  => '',
    'city'       => 'Anytown',
    'state'      => 'CA',
    'postcode'   => '90210',
    'country'    => 'US'
), 'billing');
$order->set_address(array(
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'company'    => '',
    'address_1'  => '123 Main St',
    'address_2'  => '',
    'city'       => 'Anytown',
    'state'      => 'CA',
    'postcode'   => '90210',
    'country'    => 'US'
), 'shipping');
$order->calculate_totals();
$order->update_status('processing');
