# FoodXpress for WooCommerce - Copilot Instructions

## Project Overview
FoodXpress is a WordPress/WooCommerce plugin for food delivery. It adds delivery zones, delivery fee calculation, delivery agent management, order tracking, and a mobile-friendly delivery dashboard.

## Tech Stack
- **PHP 7.4+** with WordPress 6.0+ and WooCommerce 7.0+
- **JavaScript** (jQuery + vanilla ES6) for frontend
- **Google Maps API** (Places, Geocoding, Distance Matrix) for location features
- **CSS** with mobile-first responsive design

## Code Standards
- Follow **WordPress Coding Standards** for PHP, JS, and CSS
- All PHP files must have `defined('ABSPATH') || exit;` guard
- Verify nonces for ALL AJAX and form submissions
- Sanitize ALL input: `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.
- Escape ALL output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Use `wp_unslash()` before sanitizing `$_POST`/`$_GET`/`$_REQUEST` data
- Use strict type comparisons (`===` / `!==`), never loose (`==` / `!=`)
- Always null-check `WC()->session` and `WC()->customer` before accessing

## Architecture
- **OOP Pattern**: Each feature is a class in `includes/`
- **Entry Point**: `foodxpress-for-woocommerce.php` loads `class-fxw-core.php`
- **Templates**: `templates/` for delivery views and receipts
- **REST API**: `includes/api/` for checkout validation endpoints
- **Services**: `includes/services/` for mapping and rate limiting

## Key Classes
- `FXW_Core` - Plugin bootstrap, hooks, scripts, rewrite rules
- `FXW_Checkout` - Google Maps integration, delivery fee calculation, zone validation
- `FXW_Dashboard` - Admin order management dashboard
- `FXW_Shortcodes` - Order tracking, receipt printing
- `FXW_Order_Admin` - WooCommerce order meta boxes
- `FXW_Delivery_Boy_View` - Delivery agent mobile dashboard

## HPOS Compatibility
This plugin supports WooCommerce High-Performance Order Storage (HPOS). Use `wc_get_order()` instead of direct post queries. Use `$order->get_meta()` / `$order->update_meta_data()` for order meta.

## Security Checklist
- [ ] Nonce verification on all forms/AJAX
- [ ] Capability checks (`current_user_can()`) before sensitive operations
- [ ] Input sanitization before processing
- [ ] Output escaping before rendering
- [ ] `WC()->session` null checks before `->get()` / `->set()`
- [ ] `WC()->customer` null checks before `->set_*()` methods
- [ ] Strict comparisons for authorization checks
