# Changelog

All notable changes to FoodXpress for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.1.0] - 2024-12-20

### Added
- **WooCommerce Email Classes**: Customizable email templates via WooCommerce Settings
  - Order In Kitchen email
  - Driver Assigned email
  - Order Picked Up email
- **AJAX Admin Dashboard**: No page reloads for order management
  - AJAX delivery assignment
  - AJAX status updates
  - Loading states and toast notifications
- **Unit Test Suite**: 86 automated tests for code quality

### Changed
- Replaced `wp_mail()` with proper `WC_Email` classes
- Improved rate limiter with IP address hashing for privacy
- Enhanced security with strict IP validation (REMOTE_ADDR only)

### Fixed
- Removed duplicate `wc_get_order()` calls in order admin
- Removed console.log statements from production JavaScript
- Fixed orphaned orders appearing in wrong dashboard sections

### Security
- All AJAX endpoints now verify nonces and capabilities
- Input sanitization and output escaping verified across all files
- HPOS (High-Performance Order Storage) fully compatible

## [1.0.1] - 2024-12-15

### Added
- Receipt branding settings (logo, restaurant name, tagline)
- Custom footer message for receipts
- Delivery radius validation on checkout

### Fixed
- Google Maps callback race condition
- Session data persistence for coordinates
- Delivery fee calculation edge cases

## [1.0.0] - 2024-12-01

### Added
- Initial release
- Custom order statuses for delivery workflow
- Delivery boy user role
- Google Maps integration for location selection
- Distance-based delivery fee calculation
- Order tracking shortcode
- Reorder functionality
- Receipt printing with thermal printer support
- Admin dashboard for delivery management
