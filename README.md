# FoodXpress for WooCommerce

A complete delivery management system for single-restaurant WooCommerce stores.

![Version](https://img.shields.io/badge/Version-1.4.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![HPOS](https://img.shields.io/badge/HPOS-Compatible-green)
![License](https://img.shields.io/badge/License-GPL--3.0--or--later-green)

> **Latest release:** v1.4.0 — customer My Account overhaul, delivery-rider PWA (installable app, live order list, COD collection + settlement workflow), settings redesigned for non-technical admins, kitchen-note privacy. See [`CHANGELOG.md`](./CHANGELOG.md) for full details.

## Features

### 🗺️ Location-based checkout

- **Two map providers**: Google Maps (with your API key) or a free OpenStreetMap + Leaflet mode with no key required
- **Interactive pin drop** with visible delivery-radius circle; orders outside the radius are blocked before payment
- **Distance fees**: flat, per-km, or admin-defined distance tiers, plus free-delivery threshold and minimum order amount — all configurable
- **Live Order summary**: fee updates as the customer moves the pin
- **Opening hours**: per-weekday schedule, "open all day" / "closed all day" toggles, special-occasion override
- **ETA for customers**: kitchen prep time + travel time

### 🚴 Delivery management

- **Custom order statuses**: In Kitchen → Assigned → Picked Up → Completed
- **Deliveries dashboard**: unassigned / assigned / out-for-delivery queues with the customer's kitchen note visible to managers only
- **Delivery rider role** with a restricted mobile app
- **Admin-bar toggle** to open/close ordering instantly
- **WooCommerce email notifications** for each custom status

### 📱 Delivery-rider PWA

- **Installable app** (Add to Home Screen) that looks and behaves like a native app
- **Live order list** with New / In Progress / Delivered tabs that auto-refreshes on new orders and status changes
- **Per-order actions**: call the customer, open the exact delivery location, mark picked up / delivered
- **Cash-on-delivery tracking**: amount to collect per order, running cash-in-hand total
- **Settlement workflow**: riders request a cash hand-over; managers/admins accept or reject with a full audit ledger
- **Privacy by design**: riders see delivery instructions only — kitchen notes stay with the store

### 🛍️ Customer experience

- **Order tracking** showing live status and, once assigned, the rider's name and phone number
- **My Account dashboard**: welcome banner, order stats, recent orders, saved delivery address
- **Reorder shortcut**
- **Thermal-printer-friendly receipts**

## Privacy & external services

The plugin is self-contained except for the map provider you choose:

- **Google Maps mode** loads JavaScript from `maps.googleapis.com` and sends geocoding/routing requests server-side there. The customer's chosen coordinates and your store address are sent to Google. Your API key stays in your own database.
- **OpenStreetMap (Leaflet) mode** loads map tiles from `tile.openstreetmap.org` and sends coordinate lookups to `nominatim.openstreetmap.org`.
- No other data leaves your site. No telemetry, analytics, or advertising.

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ |
| PHP | 7.4+ |

## Installation

1. Install and activate WooCommerce first.
2. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or install through the WordPress plugins screen.
3. Go to **WooCommerce → Settings → FoodXpress**, pick a map provider (Google Maps needs an API key; OpenStreetMap needs nothing), and set your restaurant location, delivery radius, and delivery fees.
4. Enable the FoodXpress Delivery method in your shipping zone(s), or let the plugin add it automatically.
5. Create users with the "Delivery Boy" role — their app lives at their Delivery Dashboard page.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[fxw_track_order]` | Live order tracking for customers |
| `[fxw_reorder]` | Quick reorder previous orders |

## Testing

Run the built-in test suite:

```bash
php tests/FXWTestRunner.php
```

The runner validates PHP syntax, security patterns (ABSPATH checks, nonce verification), file structure, plugin headers, HPOS declaration, bundled-vendor integrity (SHA-256), and custom order statuses.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run `php tests/FXWTestRunner.php` before opening a pull request.

## License

[GPL-3.0-or-later](LICENSE.md) — same license family as WooCommerce itself.

## Author / Developer

**MD MILLAT HOSEN**
Website: [millat.is-a.dev](https://millat.is-a.dev/)
GitHub: [@codermillat](https://github.com/codermillat)
