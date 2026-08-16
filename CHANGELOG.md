# Changelog

All notable changes to FoodXpress for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.2.1] - 2026-08-17

### Added
- **Blocks-checkout admin warning** (`FXW_Core::warn_blocks_checkout`) — when the WooCommerce Checkout *block* is active on the checkout page, shop managers now see a persistent notice explaining that the FoodXpress map picker, zone validation and distance fee only run on the classic `[woocommerce_checkout]` shortcode, with a direct edit link. The `cart_checkout_blocks` compatibility declaration is informational only and never forced the classic checkout, so previously a blocks store silently lost all delivery logic.

### Changed (performance)
- **Google Maps API responses are now cached in transients** (`FXW_Mapping_Service`):
  - `get_distance()` results cached 30 minutes, keyed on origin/destination with coordinates rounded to 4 decimals (~11 m)
  - `get_coords()` geocode results cached 24 hours, keyed on the address
  - `calculate_shipping()` runs on every cart/checkout render — previously each run made a live, uncached Distance Matrix call (plus a fallback Geocode call), burning API quota and adding latency on every address change; only successful results are cached, errors still hit the API on retry

### Fixed
- `wp_redirect()` → `wp_safe_redirect()` in `FXW_Core::handle_delivery_dashboard_access` (AGENTS.md §5 rule 10 violation)
- `in_array()` role checks in `FXW_Core::disable_admin_bar` now use strict comparison (AGENTS.md §5 rule 6)

### Hygiene
- `class-fxw-shipping-method.php` `calculate_shipping()` reindented to consistent tabs (the fallback-geocode block was pasted in with mixed spaces); one redundant duplicate `get_option('fxw_settings')` call removed — behavior identical
- Header bumps: `Tested up to: 7.0`, `WC tested up to: 11.0` (static analysis + code review against current docs; runtime verification against these versions lands with the Phase 8 CI matrix)
- `.gitignore` now excludes `.mimosa/` (security-scan tool output)

## [1.2.0] - 2026-08-17

### Changed (refactor & hygiene)
- **Split `class-fxw-checkout.php` (1,046 LOC) into 3 single-purpose files**
  - `class-fxw-checkout.php` (233 LOC) — orchestrator: renders the checkout form, hides default WC fields, pre-fills saved address
  - `class-fxw-checkout-maps.php` (299 LOC) — frontend map assets + `get_restaurant_location` AJAX + admin debug status
  - `class-fxw-checkout-handler.php` (469 LOC) — server-side: location update, zone validation, address save, order meta
- **Extracted two reusable services** to keep all classes under 500 LOC:
  - `includes/services/class-fxw-delivery-fee.php` — cart-fee + shipping-label ETA hooks (`add_delivery_fee`, `append_eta_to_label`)
  - `includes/services/class-fxw-address-validator.php` — stateless `validate_address_completeness` heuristic (previously dead private code; preserved for Phase 7 multi-outlet editor)
- **Comprehensive `.gitignore`** replacing the 4-line `.DS_Store`-only file: OS, editors/IDE, AI tool configs (`.agent/`, `.claude/`, `.cursorrules`, etc.), PHP/Composer/Psalm/PHPStan, logs, build artifacts, TestSprite/Playwright outputs, env/secrets
- **Added `LICENSE.md`** — proprietary license text matching the plugin header, with third-party-component carve-out for WordPress/WooCommerce (GPL-2.0+)
- **Test runner updated** to verify the new files exist and that all four checkout files have proper nonce verification

### Removed (bloat cleanup)
- 9 AI-tool config folders (`.agent/`, `.agents/`, `.claude/`, `.cline/`, `.codex/`, `.cursor/`, `.gemini/`, `.kiro/`, `.vscode/`)
- AI-tool dev docs at the repo root (`.cursorrules`, `CLAUDE.md`, `AGENTS.md`, `.github/copilot-instructions.md`)
- Vim swap file (`.CLAUDE.md.swp`)

### Impact
- The largest class in the codebase is now `FXW_Dashboard` (593 LOC) — every class stays under the 500-LOC ceiling except this single legacy file, which is queued for the same split treatment in a later refactor pass
- GitHub language detection switches from "Python" (driven by `.agent/skills/*/scripts/*.py` files) to "PHP" — the repo is now accurately classified
- No runtime behavior change. All hooks, AJAX endpoints, and class names are preserved
- The orchestrator file (`class-fxw-checkout.php`) keeps its name and call site, so external code referencing it continues to work without modification

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
