# CLAUDE.md - FoodXpress for WooCommerce

## Project Summary
FoodXpress is a WordPress/WooCommerce plugin that adds food delivery functionality: delivery zones with Google Maps, dynamic delivery fee calculation, delivery agent management, order tracking, and mobile-optimized dashboards.

## Quick Start
- PHP 7.4+, WordPress 6.0+, WooCommerce 7.0+
- Google Maps API key required (Places, Geocoding, Distance Matrix)
- Entry point: `foodxpress-for-woocommerce.php`

## Common Commands

### Static Code Analysis (No WordPress Required)
```bash
php tests/FXWTestRunner.php
```

### Docker Development Environment
```bash
# Start WordPress
docker-compose up -d

# Or use quick start script
./start-wordpress.sh

# Stop containers
docker-compose down
```

- WordPress Admin: http://localhost:8080/wp-admin
- Site URL: http://localhost:8080

## File Structure
```
├── foodxpress-for-woocommerce.php   # Main plugin file
├── uninstall.php                     # Cleanup on delete
├── includes/
│   ├── class-fxw-core.php           # Bootstrap, hooks, scripts
│   ├── class-fxw-checkout.php       # Maps, fees, zone validation
│   ├── class-fxw-dashboard.php      # Admin order dashboard
│   ├── class-fxw-shortcodes.php     # Tracking, receipts
│   ├── class-fxw-order-admin.php    # Order meta boxes
│   ├── class-fxw-delivery-boy-view.php # Agent dashboard
│   ├── class-fxw-admin-bar.php      # Delivery toggle
│   ├── class-fxw-roles.php          # delivery_boy role
│   ├── class-fxw-order-statuses.php # Custom order statuses
│   ├── class-fxw-notifications.php  # Email notifications
│   ├── class-fxw-reporting.php      # Delivery analytics
│   ├── class-fxw-config.php         # Plugin configuration
│   ├── api/
│   │   └── class-fxw-rest-checkout-controller.php # REST endpoints
│   └── services/
│       ├── class-fxw-mapping-service.php   # Google Maps wrapper
│       └── class-fxw-rate-limiter.php      # API rate limiting
├── templates/
│   ├── delivery-dashboard-template.php
│   ├── delivery-boy-view.php
│   └── receipt-template.php
├── assets/
│   ├── css/ (frontend.css, delivery-dashboard.css, my-account.css)
│   └── js/ (checkout.js, delivery-dashboard.js, admin.js, admin-dashboard.js)
```

## Coding Standards (MUST follow)
1. WordPress Coding Standards for PHP/JS/CSS
2. `defined('ABSPATH') || exit;` in every PHP file
3. Nonce verification on ALL AJAX/forms
4. Sanitize input, escape output - NO exceptions
5. `wp_unslash()` before sanitizing superglobals
6. Strict comparisons only (`===`/`!==`)
7. Null-check `WC()->session` and `WC()->customer` before use
8. HPOS compatible: use `wc_get_order()`, `$order->get_meta()`

## Key Patterns
- **Distance data**: Always check `isset($distance_data['distance']) && is_object($distance_data['distance'])` before accessing `->value`
- **Session access**: `WC()->session ? WC()->session->get('key') : null`
- **Customer access**: Guard with `if (!WC()->customer) { return; }`
- **Redirects**: Always provide fallback: `wp_safe_redirect($referer ? $referer : admin_url())`
- **HPOS links**: Fallback when `get_edit_post_link()` returns null: `admin_url('admin.php?page=wc-orders&action=edit&id=' . $id)`

## Custom Order Statuses
- `fxw-assigned` - Order assigned to delivery agent
- `fxw-picked-up` - Agent picked up the order

## Order Meta Keys
- `_fxw_delivery_boy_id` - Assigned delivery agent user ID
- `_fxw_delivery_boy_name` - Agent display name
- `_fxw_delivery_status` - Delivery status

## User Meta Keys
- `_fxw_delivery_profile` - Saved delivery address profile

## Plugin Options
- `fxw_settings` - All plugin settings (single option array)

## MCP Servers Available
- **Context7** - Library documentation lookup
- **Playwright** - Browser testing automation
- **GitHub** - Repository management
