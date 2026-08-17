# Changelog

All notable changes to FoodXpress for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.2.9] - 2026-08-17

### Added
- **Block-based Checkout support** (`FXW_Blocks_Checkout`, built entirely on documented extension points, verified against current WooCommerce docs):
  - The three delivery fields register via the **Additional Checkout Fields API** (WC 8.9+; older stores keep full classic-checkout support): required *House / Flat / Building No.* (`foodxpress/address-details`, address section), optional *Landmark* (address), optional *Delivery Instructions* (order section)
  - The **map picker renders above the Checkout block** — same Step-1 markup as classic checkout (`FXW_Checkout::render_location_picker()`), same `checkout.js`, same REST zone validation
  - **Block orders persist identical data to classic orders** through the shared `FXW_Checkout_Handler::apply_delivery_data_to_order()` via `woocommerce_store_api_checkout_update_order_from_request` — same `_fxw_*` meta, composed address, address second line, coordinates, distance, country defaults, and delivery-profile auto-save
  - Zone enforcement on blocks: the required field is validated through `woocommerce_validate_additional_field`, running the same store-open / pin-present / radius check as classic checkout (`get_zone_error()`)
  - `cart_checkout_blocks` compatibility now declared **true**; the v1.2.1 blocks warning is removed (real support replaced it)
  - Known v1 limitation: after dropping a pin, the blocks shipping total refreshes on the next checkout interaction rather than instantly (no build step → no React slotFill); order-time enforcement is unaffected

### Changed
- **`FXW_Dashboard` split into three files** (593 → 137/365/278 LOC, all under the 500 cap, logic unchanged): orchestrator keeps `FXW_Dashboard`; rendering → `FXW_Dashboard_Render`; write handlers → `FXW_Dashboard_Actions`
- **Two real bugs fixed during the split**: admin *Print Receipt* buttons always failed their nonce check (dashboard created `fxw_nonce`, handler verifies `fxw_print_receipt`), and dashboard order links were empty under HPOS (`get_edit_post_link()` returns null — now falls back to the HPOS admin URL)
- New `docs/QA-CHECKLIST.md` — the full pre-production runtime pass (configuration + classic/blocks checkout + operations + privacy), with expected outcomes per step

## [1.2.8] - 2026-08-17

### Changed (author credits)
- **Author/developer credits standardized across the project**: plugin header `Author URI` now points to the developer's site ([millat.is-a.dev](https://millat.is-a.dev/)), the `@author` docblock in all 22 PHP files carries the same link, and the README author section lists both the website and GitHub ([@codermillat](https://github.com/codermillat))

## [1.2.7] - 2026-08-17

### Changed (general open-source release — no client/region specifics)
- **Region-neutral defaults everywhere.** The plugin is a general open-source release for any restaurant worldwide:
  - Map fallback center is no longer hard-coded to a specific city — the initial viewport now picks the saved pin (zoom 15) → restaurant zone (zoom 11) → neutral world view (zoom 2)
  - Place autocomplete no longer biases searches to a specific country or language — results follow the user's own language and query anywhere in the world
  - Currency fallback is no longer a specific currency symbol; it comes from WooCommerce store settings
  - Settings example coordinates replaced with a neutral example
- **Removed the client reference in AGENTS.md** — the project is documented as a general open-source release, not tied to any specific restaurant or client

## [1.2.6] - 2026-08-17

### Added (production-readiness)
- **Privacy integration (`FXW_Privacy`)** — FoodXpress personal data now flows through the official WordPress/WooCommerce privacy frameworks, using only documented hooks: the saved delivery profile (pin coordinates, exact address, landmark, instructions) is included in **Tools → Export/Erase Personal Data** via `wp_privacy_personal_data_exporters`/`_erasers`, and order meta (current + pre-1.2.2 legacy address fields, coordinates) joins WooCommerce's own order export via `woocommerce_privacy_export_order_personal_data` and is removed on order anonymization via `woocommerce_privacy_before_remove_order_personal_data`
- **"Delivery not configured" admin warning** — persistent notice for shop managers when the Google Maps API key or the restaurant location (coordinates or address) is missing, with a direct settings link; cheap checks only, no API calls on admin renders
- **Translation template** — `languages/foodxpress.pot` with all 271 translatable strings (verified against the WooCommerce privacy-framework documentation before implementation)

## [1.2.5] - 2026-08-17

### Removed (dead code — full-project audit, every item verified caller-free)
- **3 orphaned AJAX endpoints** (registered in PHP, called by no JS file, template, or inline script): `fxw_update_customer_location` (superseded by the REST `validate-location` route in v1.2.3), `fxw_get_restaurant_location` (restaurant center is now localized server-side), `fxw_debug_status` (debug-only, no caller)
- **6 localized params nothing reads** (`ajax_url`, `nonce`, `debug`, `prep_time`, `max_retries`, `retry_delay` in `fxw_checkout_params`) plus the never-read `store_closed` translation key; `FXW_Config::MAX_RETRIES` / `RETRY_DELAY` constants existed only to feed two of those params and are gone too
- Dead markup/CSS/JS: the never-written `#fxw-geolocation-error` div + its CSS rules, and the unused `checkoutForm` element cache in `checkout.js`

### Fixed (docs)
- Plugin header `Plugin URI` corrected to the real repo casing (`FoodXpress-for-WooCommerce`)
- AGENTS.md refreshed: current-version line (was stale at v1.2.1), class map no longer describes the removed endpoints, coding-standard rule numbering fixed (duplicate "3"), `DOCKER_SETUP.md` drops its stale TestSprite reference
- `FXW_Address_Validator` intentionally retained (documented for the Phase 7 multi-outlet editor)

## [1.2.4] - 2026-08-17

### Fixed (WooCommerce-compatibility hardening, verified against current docs)
- **Default the customer's billing/shipping country to the store base country via `WC_Customer` CRUD** when empty on the checkout page. Since v1.2.3 removed the country select, WooCommerce's own posted-data/tax/payment-gateway pipeline could otherwise run without a country; seeding the customer object feeds every downstream step through the official flow (the `woocommerce_checkout_create_order` fallback from v1.2.3 stays as a second belt)
- Compliance audit of the v1.2.3 changes against current WooCommerce documentation (field removal via `woocommerce_checkout_fields` unset, order data via `woocommerce_checkout_create_order` + `$order->set_*`, Settings API, REST controller): all patterns confirmed documented; zero direct database access anywhere in the plugin (no `$wpdb` usage at all — order data flows exclusively through HPOS CRUD)

## [1.2.3] - 2026-08-17

### Changed (coordinates-only delivery engine)
- **The fee and zone checks now run on coordinates only — restaurant lat/lng ↔ customer pin lat/lng.** New optional setting *Restaurant Coordinates (lat, lng)*; when unset, the restaurant address is geocoded once and cached (24 h). All three distance call sites (shipping method, checkout validation, REST) share the new `FXW_Mapping_Service::get_restaurant_location()` helper. **No customer-entered field can influence the fee.**
- **Google Maps supplies coordinates only.** `checkout.js` no longer reverse-geocodes into checkout fields and no longer pushes addresses to the WC Store API (`fillWCFields` / `pushToStoreAPI` removed). The reverse geocode feeds the read-only "Selected Location" caption under the map — it never fills an input.
- **WC billing/shipping address fields (address, city, state, postcode, country) are removed from checkout entirely** (unset, not hidden). Orders default their country to the store base country; the exact-address line remains on `address_2` for receipts/emails.
- **Delivery-radius circle** drawn on the map around the restaurant (subtle green overlay) so customers can see the selectable zone; pins outside it get the immediate out-of-zone error and Place Order stays blocked server-side. Map default center is now the restaurant (saved pin still wins).

### Changed (saved address = the default)
- **Returning customers no longer re-pin or re-type.** The delivery profile (pin lat/lng, exact address, landmark, instructions) is auto-saved for logged-in customers on every order — the opt-in checkbox is gone — and auto-fills the map, the fields, and the session on the next checkout. Everything stays editable.

### Removed
- Dead address-based fallback-geocode branches in `calculate_shipping()` and `validate_delivery_zone()` (unreachable once WC address fields are gone), `.fxw-hidden-field` CSS, and the `fetchRestaurantCenter()` stub

## [1.2.2] - 2026-08-17

### Changed (Zomato/Swiggy-style checkout address)
- **Step 2 collapsed from five structured fields to a single required "House / Flat / Building No." field** (`fxw_address_details`) + optional landmark + the existing optional delivery-instructions textarea — mirroring Swiggy's "Complete address*" + "Landmark (optional)" model next to the map pin. The fee engine is unchanged (it runs on the pin's coordinates only); `checkout.js` needed zero changes
- Order meta now stores `_fxw_address_details` + `_fxw_landmark`; `_fxw_delivery_address` is composed as `details (Landmark: …)` so the admin meta box and delivery dashboard keep working unmodified
- The exact-address line is also written to billing/shipping `address_2`, so receipts and emails using `get_formatted_shipping_address()` now show the flat/house info (previously checkout-entered details never reached the receipt)
- Validation: one required check on `fxw_address_details` (min 5 chars) replaces the two old required-field checks

### Fixed
- **"Save this address for future orders" now actually works.** The old save gate required POST keys that no form ever submitted (`fxw_location-search-input` had no `name` attribute; `fxw_delivery_address` was never rendered), so `_fxw_delivery_profile` was never written and the returning-customer pre-fill (map pin + fields) was dead. The profile is now saved whenever the checkbox is ticked, including `address_details` + `landmark` for pre-fill

### Removed
- ~290 lines of dead v1 CSS (`.fxw-address-*` feedback/suggestion/score styles, `#fxw_delivery_address` rules) with no remaining PHP/JS references; live map/hidden-field/geolocation-error/mobile styles preserved
- Checkout no longer writes the obsolete `_fxw_house_flat_no` / `_fxw_floor_no` / `_fxw_society_building` / `_fxw_block_tower_area` metas to new orders (existing orders keep theirs)

## [1.2.1] - 2026-08-17

### License
- **Relicensed from proprietary to GPL-3.0-or-later** (matching the WooCommerce ecosystem), full text in `LICENSE.md`; plugin header now carries `License: GPL-3.0-or-later` + License URI. The repository is public as of this release. All future contributions are accepted under the same license (see `CONTRIBUTING.md`).

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
