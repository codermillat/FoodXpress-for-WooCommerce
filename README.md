# FoodXpress for WooCommerce

A complete delivery management system for single-restaurant WooCommerce stores.

![Version](https://img.shields.io/badge/Version-1.2.13-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![HPOS](https://img.shields.io/badge/HPOS-Compatible-green)
![License](https://img.shields.io/badge/License-GPL--3.0--or--later-green)

> **Latest release:** [v1.2.10 — Manager capability fixes](https://github.com/codermillat/FoodXpress-for-WooCommerce/releases/tag/v1.2.10) (2026-08-17)
> Coordinates-only delivery engine, Zomato/Swiggy-style checkout with auto-saved address defaults, GDPR privacy integration, translation template, configuration health warnings — with region-neutral defaults, usable by any restaurant anywhere. See [`CHANGELOG.md`](./CHANGELOG.md) for full details.
> **Next:** [Phase 1 — Data layer + 8 new `fxw_*` tables](./docs/ROADMAP.md).

## Features

### 🚴 Delivery Management
- **Custom Order Statuses**: In Kitchen → Assigned → Picked Up → Delivered
- **Delivery Boy Role**: Dedicated user role with restricted dashboard access
- **Order Assignment**: Assign delivery personnel from admin dashboard
- **Real-time Tracking**: Customers can track their orders via shortcode

### 🗺️ Location-Based Delivery
- **Google Maps Integration**: Interactive map with draggable pin, place search, "use my location", and a visible delivery-radius circle
- **Zomato/Swiggy-style address**: pinned location + one "House / Flat / Building No." field + optional landmark
- **Distance Calculation**: Automatic delivery radius validation from coordinates only
- **Dynamic Fees**: Distance-based fee — base + per-km or admin-defined distance tiers, free-delivery threshold, and minimum order amount — all configurable from the settings tab
- **Saved defaults**: returning customers get their pin and address auto-filled

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
├── foodxpress-for-woocommerce.php   # Slim bootstrap
├── uninstall.php                   # Cleanup on delete
├── LICENSE.md                      # GPL-3.0-or-later
├── CHANGELOG.md                    # Version history
├── CONTRIBUTING.md                 # How to contribute
├── AGENTS.md                       # Context for AI coding agents
├── docs/
│   ├── ANALYSIS.md                 # 3-repo comparison
│   ├── ROADMAP.md                  # 8-phase backport plan (Phase 0 ✅ done)
│   └── archive/                    # Historical docs
├── includes/
│   ├── class-fxw-*.php             # Core + feature classes
│   ├── api/                        # REST controllers
│   ├── services/                   # Stateless services
│   └── emails/                     # WC_Email classes
├── templates/                      # Frontend templates
├── assets/
│   ├── css/
│   └── js/
├── tests/
│   └── FXWTestRunner.php           # Static analysis + security checks (118 tests)
└── skills/                         # Developer reference (Claude skills content)
```

> **Class size rule:** every class stays under 500 LOC. `FXW_Dashboard` (593) is queued for the same split treatment as the v1.2.0 checkout split.

## Testing

### Static Code Analysis

Run the built-in test suite for syntax, security, and code quality checks:

```bash
php tests/FXWTestRunner.php
```

Expected output: `Passed: 118, Failed: 0`.

The runner validates:
- PHP syntax errors
- Security patterns (ABSPATH checks, nonce verification on every AJAX file)
- File structure completeness (including the 3 checkout split files)
- Code quality (no unlimited queries, proper hooks registered)
- Plugin headers (WordPress + WooCommerce compatibility)
- HPOS compatibility declaration
- Custom order statuses registered with WC

**Run this after every change. It must report 103/103 before any commit.**

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run `php tests/FXWTestRunner.php` and make sure it reports 103/103 before opening a pull request.

## License

[GPL-3.0-or-later](LICENSE.md) — same license family as WooCommerce itself.

## Author / Developer

**MD MILLAT HOSEN**
Website: [millat.is-a.dev](https://millat.is-a.dev/)
GitHub: [@codermillat](https://github.com/codermillat)
