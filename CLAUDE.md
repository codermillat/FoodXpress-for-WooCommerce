# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FoodXpress for WooCommerce is a WordPress plugin that provides a complete delivery management system for single-restaurant WooCommerce stores. It adds custom order statuses, delivery boy role, Google Maps integration for distance-based delivery fees, and custom email notifications.

- **Type**: WordPress/WooCommerce Plugin
- **Requirements**: WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+
- **Version**: 1.1.0

## Common Commands

### Static Code Analysis (No WordPress Required)
```bash
php tests/FXWTestRunner.php
```
Validates PHP syntax, security patterns (ABSPATH checks, nonce verification), file structure, code quality, and plugin headers.

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

### TestSprite MCP Testing
In Cursor, use commands like:
- "Run TestSprite tests for FoodXpress plugin"
- "Test [specific feature] using TestSprite"

Configuration: `tests/testsprite-config.json`

## Architecture

### Core Classes (`includes/class-fxw-*.php`)
- **class-fxw-core.php**: Main plugin initialization, hooks, and filters
- **class-fxw-checkout.php**: Checkout process with delivery fee calculation
- **class-fxw-dashboard.php**: Admin order management dashboard
- **class-fxw-settings.php**: Plugin settings page
- **class-fxw-roles.php**: Custom "Delivery Boy" user role
- **class-fxw-order-statuses.php**: Custom statuses (In Kitchen, Assigned, Picked Up, Delivered)
- **class-fxw-notifications.php**: Email notification triggers
- **class-fxw-shipping-method.php**: WooCommerce shipping method integration
- **class-fxw-shortcodes.php**: `[fxw_track_order]`, `[fxw_reorder]`

### Email System (`includes/emails/`)
Custom WooCommerce email classes extending `WC_Email`:
- `class-fxw-email-in-kitchen.php`
- `class-fxw-email-assigned.php`
- `class-fxw-email-picked-up.php`

### Services (`includes/services/`)
- **class-fxw-mapping-service.php**: Google Maps API integration for distance calculation
- **class-fxw-rate-limiter.php**: API rate limiting

### Templates (`templates/`)
- Frontend templates for delivery dashboard, receipt printing, and email layouts

## Code Standards

- Follow WordPress Coding Standards
- Include `if ( ! defined( 'ABSPATH' ) ) exit;` in all PHP files
- Verify nonces for all AJAX requests
- Sanitize input, escape output
- Test with WooCommerce 7.0+ and WordPress 6.0+

## Key Features

- Custom order statuses: In Kitchen → Assigned → Picked Up → Delivered
- Delivery Boy role with restricted dashboard access at `/delivery-dashboard/`
- Google Maps integration for delivery location and distance-based fees
- Custom WooCommerce email templates
- Receipt printing templates
- HPOS (High-Performance Order Storage) compatible
