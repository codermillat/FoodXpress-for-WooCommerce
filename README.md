# FoodXpress for WooCommerce

A complete delivery management system for single-restaurant WooCommerce stores.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/License-Proprietary-red)

## Features

### 🚴 Delivery Management
- **Custom Order Statuses**: In Kitchen → Assigned → Picked Up → Delivered
- **Delivery Boy Role**: Dedicated user role with restricted dashboard access
- **Order Assignment**: Assign delivery personnel from admin dashboard
- **Real-time Tracking**: Customers can track their orders via shortcode

### 🗺️ Location-Based Delivery
- **Google Maps Integration**: Interactive map for delivery location selection
- **Distance Calculation**: Automatic delivery radius validation
- **Dynamic Fees**: Distance-based delivery fee calculation
- **Address Geocoding**: Convert addresses to coordinates automatically

### 📧 Customer Notifications
- **WooCommerce Emails**: Customizable email templates for each status
- **Admin Customization**: Edit subject, heading, and content via WooCommerce Settings

### 🖨️ Order Management
- **Receipt Printing**: Professional thermal receipt templates
- **Custom Branding**: Logo, restaurant name, and tagline on receipts
- **Admin Dashboard**: Centralized view of all delivery orders

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ |
| PHP | 7.4+ |

## Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Go to **WooCommerce → Settings → FoodXpress** to configure

## Configuration

### Google Maps API Key
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Enable **Maps JavaScript API**, **Geocoding API**, and **Distance Matrix API**
3. Create an API key and add it to plugin settings

### Delivery Settings
- **Restaurant Address**: Your pickup location
- **Delivery Radius**: Maximum delivery distance (km)
- **Base Delivery Fee**: Starting fee for all orders
- **Per KM Fee**: Additional charge per kilometer

### Email Customization
1. Go to **WooCommerce → Settings → Emails**
2. Find "Order In Kitchen", "Driver Assigned", "Order Picked Up"
3. Click **Manage** to customize content

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[fxw_track_order]` | Order tracking for customers |
| `[fxw_reorder]` | Quick reorder previous orders |

## User Roles

### Delivery Boy
- Access to delivery dashboard at `/delivery-dashboard/`
- Can view assigned orders only
- Update order status (Picked Up / Delivered)

## File Structure

```
foodxpress-for-woocommerce/
├── assets/
│   ├── css/
│   └── js/
├── includes/
│   ├── emails/              # WC_Email classes
│   ├── services/            # API services
│   └── class-fxw-*.php      # Core classes
├── templates/
│   ├── emails/              # Email templates
│   └── *.php                # Frontend templates
└── tests/
    ├── FXWTestRunner.php    # Static code analysis tests
    └── testsprite-config.json  # TestSprite MCP configuration
```

## Testing

### Static Code Analysis

Run the built-in test suite for syntax, security, and code quality checks:

```bash
php tests/FXWTestRunner.php
```

This validates:
- PHP syntax errors
- Security patterns (ABSPATH checks, nonce verification)
- File structure completeness
- Code quality (unlimited queries, proper hooks)
- Plugin headers
- WordPress hooks and filters

### Automated Testing with TestSprite MCP

For comprehensive functional testing, use TestSprite MCP in Cursor:

1. **Setup**: See [TESTSPRITE_SETUP.md](TESTSPRITE_SETUP.md) for installation
2. **Configuration**: Edit `tests/testsprite-config.json` for your environment
3. **Run Tests**: Use Cursor chat to execute tests:
   ```
   "Run TestSprite tests for FoodXpress plugin"
   ```

TestSprite MCP covers:
- Plugin activation/deactivation
- Order management workflows
- Delivery dashboard functionality
- Checkout and shipping integration
- Shortcode functionality
- Email notifications
- Security and access control

For detailed testing documentation, see [tests/README-TESTSPRITE.md](tests/README-TESTSPRITE.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

Proprietary License - All rights reserved.

## Author

**MD MILLAT HOSEN**  
GitHub: [@codermillat](https://github.com/codermillat)
