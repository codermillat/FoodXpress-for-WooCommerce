# FoodXpress for WooCommerce - Quick Reference Guide

## Plugin Entry Point
- **Main File:** `foodxpress-for-woocommerce.php`
- **Core Class:** `FXW_Core` in `includes/class-fxw-core.php`
- **Initialization:** Hooks into `plugins_loaded` action

## Key Classes and Their Responsibilities

### Core Classes
- **FXW_Core**: Main orchestrator, loads dependencies, manages hooks
- **FXW_Config**: Configuration constants (prep time, radius, retries)
- **FXW_Roles**: User role management (delivery_boy role)
- **FXW_Order_Statuses**: Custom order statuses registration

### Checkout & Shipping
- **FXW_Checkout**: Checkout enhancements, location handling, zone validation
- **FXW_Shipping_Method**: Distance-based shipping calculation
- **FXW_Mapping_Service**: Google Maps API integration (geocoding, distance)

### Admin & Dashboard
- **FXW_Dashboard**: Admin delivery dashboard, order assignment
- **FXW_Order_Admin**: Order admin functionality, receipt printing
- **FXW_Settings**: Settings page management
- **FXW_Admin_Bar**: Admin bar integration (delivery status toggle)
- **FXW_Reporting**: Daily reports on dashboard

### Frontend
- **FXW_Shortcodes**: Frontend shortcodes (track order, re-order)
- **FXW_Delivery_Boy_View**: Delivery personnel view

### Services
- **FXW_Mapping_Service**: Google Maps API wrapper
- **FXW_Rate_Limiter**: Rate limiting for API requests
- **FXW_Notifications**: Email notifications on status changes

## Key Hooks

### Actions
- `plugins_loaded`: Plugin initialization
- `admin_menu`: Admin menu registration
- `wp_enqueue_scripts`: Script/style enqueuing
- `woocommerce_checkout_fields`: Checkout field customization
- `woocommerce_after_checkout_validation`: Delivery zone validation
- `woocommerce_cart_calculate_fees`: Delivery fee calculation
- `woocommerce_checkout_create_order`: Save delivery details
- `woocommerce_order_status_changed`: Status change notifications

### Filters
- `woocommerce_shipping_methods`: Add custom shipping method
- `woocommerce_shipping_chosen_method`: Prefer FoodXpress shipping
- `wc_order_statuses`: Add custom order statuses
- `template_include`: Custom delivery dashboard template
- `query_vars`: Add custom query variables

## AJAX Endpoints

### Public Endpoints (with nonce)
- `fxw_get_restaurant_location`: Get restaurant coordinates
- `fxw_update_customer_location`: Update customer location in session
- `fxw_print_receipt`: Print order receipt (with auth check)
- `fxw_debug_status`: Debug endpoint

### Admin Endpoints (with nonce + capability check)
- `fxw_toggle_delivery_status`: Toggle delivery open/closed

## Order Meta Fields

- `_fxw_delivery_boy_id`: Assigned delivery personnel ID
- `_fxw_delivery_address`: Full delivery address
- `_fxw_delivery_lat`: Delivery latitude
- `_fxw_delivery_lng`: Delivery longitude
- `_fxw_delivery_distance`: Distance in kilometers
- `_fxw_delivery_duration`: Estimated duration in minutes
- `_fxw_address_unit`: Unit/apartment number

## Session Data

- `customer_lat`: Customer latitude
- `customer_lng`: Customer longitude
- `customer_address`: Customer address

## Settings Options

Stored in `fxw_settings` option:
- `fxw_google_maps_api_key`: Google Maps API key
- `fxw_restaurant_address`: Restaurant address
- `fxw_preparation_time`: Default preparation time (minutes)
- `fxw_delivery_fee_base`: Base delivery fee
- `fxw_delivery_fee_per_km`: Fee per kilometer
- `fxw_delivery_zone_radius`: Delivery zone radius (km)
- `fxw_is_open`: Delivery status (open/closed)

## Custom Order Statuses

- `fxw-in-kitchen`: Order is being prepared
- `fxw-assigned`: Delivery personnel assigned
- `fxw-picked-up`: Order picked up, out for delivery
- `completed`: Standard WooCommerce status (delivered)

## User Roles & Capabilities

### Delivery Boy Role
- Capabilities: `read`, `edit_posts`, `fxw_delivery_access`
- Access: Delivery dashboard only
- Restrictions: No admin bar, limited order access

### Custom Capability
- `fxw_delivery_access`: Required for delivery dashboard access
- Granted to: `delivery_boy` role, `administrator` role

## Templates

### Frontend Templates
- `templates/delivery-dashboard-template.php`: Delivery personnel dashboard
- `templates/delivery-boy-view.php`: Alternative delivery view
- `templates/receipt-template.php`: Order receipt template

## Shortcodes

- `[fxw_track_order]`: Order tracking form and status display

## Routes

- `/delivery-dashboard`: Delivery personnel dashboard (rewrite rule)

## Security Measures

1. **Nonce Verification**: All AJAX endpoints and forms
2. **Capability Checks**: Admin functions require proper capabilities
3. **Input Sanitization**: All user inputs sanitized
4. **Output Escaping**: All outputs escaped
5. **Rate Limiting**: IP-based rate limiting for API requests
6. **Authorization**: Order ownership checks for receipts

## Common Functions

### Get Restaurant Location
```php
$options = get_option( 'fxw_settings' );
$restaurant_address = $options['fxw_restaurant_address'];
$mapping = new FXW_Mapping_Service();
$coords = $mapping->get_coords( $restaurant_address );
```

### Get Order Delivery Boy
```php
$order = wc_get_order( $order_id );
$delivery_boy_id = $order->get_meta( '_fxw_delivery_boy_id' );
```

### Get Delivery Details
```php
$order = wc_get_order( $order_id );
$address = $order->get_meta( '_fxw_delivery_address' );
$lat = $order->get_meta( '_fxw_delivery_lat' );
$lng = $order->get_meta( '_fxw_delivery_lng' );
$distance = $order->get_meta( '_fxw_delivery_distance' );
$duration = $order->get_meta( '_fxw_delivery_duration' );
```

### Calculate Shipping
```php
$mapping = new FXW_Mapping_Service();
$distance_data = $mapping->get_distance( $origin, $destination );
// Returns: array( 'distance' => float, 'duration' => int )
```

### Rate Limiting
```php
$rate_limit_check = FXW_Rate_Limiter::check_rate_limit( 'action_name', 10 );
if ( is_wp_error( $rate_limit_check ) ) {
    // Handle rate limit error
}
```

## Testing

### Run PHPUnit Tests
```bash
vendor/bin/phpunit
```

### Run E2E Tests (Playwright)
```bash
cd tests/e2e
npm install
npm test
```

### Test Coverage
- Unit tests: `tests/unit/`
- Integration tests: `tests/integration/`
- E2E tests: `tests/e2e/`
- Security tests: `tests/security/`

## Debugging

### Enable Debug Logging
```php
// Already enabled if WooCommerce logger is available
wc_get_logger()->debug( 'Message', array( 'source' => 'foodxpress' ) );
```

### Check Rate Limiting
```php
$remaining = FXW_Rate_Limiter::get_remaining_quota( 'action_name', 10 );
```

### Debug Status Endpoint
- AJAX endpoint: `fxw_debug_status`
- Returns current session and configuration status

## Common Issues & Solutions

### Issue: Google Maps not loading
- **Solution**: Check API key in settings, verify API key has correct permissions

### Issue: Shipping not calculating
- **Solution**: Check session data, verify restaurant address is set, check API key

### Issue: Delivery dashboard not accessible
- **Solution**: Verify user has `fxw_delivery_access` capability, check rewrite rules

### Issue: Orders not showing in dashboard
- **Solution**: Check order status, verify delivery boy assignment, check meta queries

### Issue: Rate limiting errors
- **Solution**: Check rate limit settings, verify IP detection, check transient storage

## File Locations

### Core Files
- Main plugin: `foodxpress-for-woocommerce.php`
- Core class: `includes/class-fxw-core.php`
- Config: `includes/class-fxw-config.php`

### Checkout Files
- Checkout: `includes/class-fxw-checkout.php`
- Shipping: `includes/class-fxw-shipping-method.php`
- Mapping: `includes/services/class-fxw-mapping-service.php`

### Admin Files
- Dashboard: `includes/class-fxw-dashboard.php`
- Order Admin: `includes/class-fxw-order-admin.php`
- Settings: `includes/class-fxw-settings.php`

### Frontend Files
- Shortcodes: `includes/class-fxw-shortcodes.php`
- Delivery View: `includes/class-fxw-delivery-boy-view.php`

### Assets
- Admin JS: `assets/js/admin.js`
- Checkout JS: `assets/js/checkout.js`
- Dashboard JS: `assets/js/delivery-dashboard.js`
- Frontend CSS: `assets/css/frontend.css`

## Constants

- `FXW_VERSION`: Plugin version (1.0.1)
- `FXW_PLUGIN_DIR`: Plugin directory path
- `FXW_PLUGIN_URL`: Plugin URL

## Dependencies

- **WordPress**: 5.0+
- **WooCommerce**: Required
- **Google Maps API**: Required for maps and distance calculation
- **PHP**: 7.4+ (recommended 8.0+)

## Version History

- **1.0.1**: Current version
- **1.0.0**: Initial release

---

**Last Updated:** January 2025

