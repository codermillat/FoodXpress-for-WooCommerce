# Changelog

All notable changes to FoodXpress for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.3.0] - 2026-08-18

### Fixed (checkout UX — four regressions caught in browser walkthrough)
- **Double-charging on the checkout is gone.** The "Delivery Fee ₹X" line and the "Free shipping FREE" line used to render simultaneously because the cost was added both as a fee (`woocommerce_cart_calculate_fees`) AND via the FoodXpress shipping method, with the two figures computed at different moments. The fee-side path has been removed entirely from `FXW_Delivery_Fee`; the charge is now added exactly once, through the documented `WC_Shipping_Method::calculate_shipping() + add_rate()`. `wc_price()` formatting for the toast comes from the REST controller so currency, decimals and symbol position are store-driven.
- **Country, state, city and postcode are no longer shown on the checkout.** A new `FXW_Checkout_Address` class hooks `woocommerce_get_country_locale` to mark those fields hidden + non-required for every WC locale, and hooks `woocommerce_default_address_fields` to relabel WooCommerce's own `address_1` field to **"Flat / Floor / Block / Society / Tower"** with an example placeholder. This works on both classic AND block checkouts because the locale filter runs on every request. The `city`/`state`/`country`/`postcode` slots are also populated from the store's configured base for both shipping and billing so the address payload sent to order processing still has complete country data even though the fields are not on the form. `woocommerce_customer_default_location` returns the documented `"COUNTRY:STATE"` string format that `wc_get_customer_default_location()` expects (the previous array form crashed `wc_format_country_state_string()` → `strstr()` on PHP 8 and broke every checkout request).
- **Address field count cut to one.** The legacy `foodxpress/address-details` Additional Checkout Field is gone from the blocks checkout; the blocks flow now reuses the relabelled WC `address_1` field, and `FXW_Blocks_Checkout::apply_delivery_data()` reads the detail from `$order->get_shipping_address_1()`. Only Email address, Country/Region, the relabelled Address and an optional Nearest Landmark remain visible.
- **Pin drag → Order summary now updates simultaneously.** The picker (both `checkout.js` and `checkout-leaflet.js`) detects whether it is on the block checkout by sniffing `wc.blocksCheckout.extensionCartUpdate`, and on the classic checkout falls back to `$(document.body).trigger('update_checkout')`. The new `POST /foodxpress/v1/checkout/cart-update` REST route persists `customer_lat` / `customer_lng` to the session, bootstrapping `WC()->session` and the customer-session cookie the same way WC's own Store API does for custom REST routes, then re-runs `WC()->cart->calculate_shipping()` + `calculate_totals()` so the shipping method re-reads the freshly-stored coordinates. On the block checkout `extensionCartUpdate({ namespace: 'foodxpress', data: { lat, lng } })` returns the result inline; on the classic checkout the jQuery event rebuilds the order-review fragment.
- **Landmark label no longer reads "(optional) (optional)".** Block checkout's Additional Checkout Field wrapper appends its own "(optional)" suffix for non-required fields; the previous label `__('Nearby Landmark (optional)', ...)` therefore rendered the suffix twice. The label now reads simply `__('Nearby Landmark', ...)` and the field marker renders correctly.
- **Settings page no longer asks the customer to enable the deleted extra-delivery-fee toggle.** The `fxw_enable_extra_delivery_fee` settings field and its sanitizer branch are removed (it was the guard that was supposed to prevent the double-charge). Existing stored values are cleared on the next settings save.

### Internal
- New `includes/class-fxw-checkout-address.php` (259 LOC) — address simplification extracted behind the same single-responsibility split pattern as the v1.2.0 checkout split; `FXW_Core::requires` it once on every request because the locale filter must run globally, not just on the frontend.
- Test runner now asserts the new file exists. Suite is at 136/136.

## [1.2.19] - 2026-08-18

### Fixed (browser QA — map picker on block themes + store-state source of truth)
- **The map location picker now renders on the block checkout in block themes.** `FXW_Blocks_Checkout` prepended the Step-1 picker via a `the_content` filter guarded by `in_the_loop()`. Block themes render page content through the core `post-content` block, which applies `the_content` outside the main loop, so the guard rejected every request and the picker silently never appeared — customers saw WooCommerce's stock address fields with no way to pin their location. Moved the injection to `render_block` targeting `woocommerce/checkout`, which fires wherever the block renders regardless of theme type. Verified on a block theme (Kiosko): `#fxw-map`, the search input, the "Use My Location" button and the hidden `fxw_lat`/`fxw_lng` inputs are all present in the checkout HTML, and the cart page is unaffected.
- **Store open/closed state is now consistent between the admin bar and the storefront.** `is_store_open()` lived on `FXW_Checkout`, but `FXW_Core` only loads the checkout classes on frontend and AJAX requests — so in `wp-admin` the class-existence check failed and the admin bar fell back to "Deliveries: Open" while the cart and checkout correctly showed the closed notice. The canonical implementation moved to `FXW_Store_Hours::is_store_open()` (loaded on every request); `FXW_Checkout::is_store_open()` now delegates to it, so both names remain valid for existing callers. The admin bar and the shipping method read the new location.

## [1.2.18] - 2026-08-18

### Fixed (browser QA — blocks checkout fields + admin-bar state)
- **Blocks-checkout FXW fields now register on WooCommerce 11.x.** The Additional Checkout Fields API (used by `FXW_Blocks_Checkout::register_fields`) was hooked on `woocommerce_init` since v1.2.9. WooCommerce 11.0+ changed the function so it requires `woocommerce_blocks_loaded` to have already fired — when called earlier, the function silently defers the registration to that later hook, which can miss the page render entirely. Switched the hook to `woocommerce_blocks_loaded` and verified the three fields (`foodxpress/address-details`, `foodxpress/landmark`, `foodxpress/delivery-instructions`) land in WC's `CheckoutFields::$additional_fields` store on WC 11.0.1. Caught during browser walkthrough of the blocks-checkout page; pre-fix the three fields were registered (function returned no exception) but never appeared on the page.
- **Admin-bar Deliveries status now matches the rest of the UI.** `FXW_Admin_Bar` previously read the raw `fxw_is_open` option, so the bar could say "Deliveries: Open" while the schedule-aware `FXW_Checkout::is_store_open()` said closed (and vice versa). The bar now shows the effective `is_store_open()` value, and when closed it appends the next-reopen hint from `FXW_Store_Hours` so the admin sees *why* (e.g. "Deliveries: Closed (We reopen Sunday at 09:00.)"). The AJAX toggle response label is also schedule-aware.

## [1.2.17] - 2026-08-18

### Fixed (runtime QA on Local — WP 7.0.4, WC 11.0.1, PHP 8.2.29)
- **REST validate-location no longer 500s on every page load.** The `lat` and `lng` `sanitize_callback` was set to the bare string `'floatval'`, which WP 6.x+ now resolves as a method-style callback and invokes with `($value, $request, $key)` — PHP 8 rejects the extra args and the WP REST `sanitize_params()` dispatcher throws `ArgumentCountError`, taking down every page that hits the REST layer (cart, checkout, my-account, all admin pages). Replaced both with explicit `function ($param) { return (float) $param; }` closures. Caught by probing `POST /wp-json/foodxpress/v1/checkout/validate-location` from a curl session; pre-fix the call returned a JSON-wrapped fatal; post-fix it returns the expected `configuration_error` (no Maps key yet) and the full page render is no longer blocked.

## [1.2.16] - 2026-08-18

### Fixed (full-flow audit cleanup pass — 16 minors, no blockers)
- **REST schedule parity.** `validate_location` and the GET `/checkout/settings` endpoint now read store-open state through `FXW_Checkout::is_store_open()`, so the manual Open/Closed toggle AND `FXW_Store_Hours` are honored uniformly across classic checkout, blocks checkout, and the REST estimate
- **No-API-key customer message.** When `fxw_google_maps_api_key` is empty, the shared zone-error returns a distinct "online ordering currently unavailable" message instead of telling customers to pin a location on a map that isn't rendered
- **Unassign reverts status.** Both the dashboard form/AJAX unassign path and the order-edit "Re-assign" action now revert the order to `fxw-in-kitchen` (falling back to `processing` if the custom status isn't registered) so unassigned orders don't sit in `fxw-assigned` with no rider
- **Agent transition enforcement.** Delivery agents can no longer jump straight from `fxw-assigned` to `completed` — both the AJAX handler and the form-POST `mark_delivered` require `fxw-picked-up` first
- **wp-login.php redirect for riders.** Added a 3-param `login_redirect` filter (the 2-param binding is deprecated in WP 6.2+) so delivery boys who log in via wp-login land on their dashboard
- **Track-order rate limit.** The public order-tracking form now rate-limits at 10 requests/minute per IP via `FXW_Rate_Limiter`, closing the brute-force window opened by sequential order IDs + email guessing
- **Reorder feedback.** Re-order now surfaces per-item failures (e.g. variation since deleted) and a generic "items added" notice via `wc_add_notice` instead of failing silently
- **Settings: explicit cap check + fractional radius + latlng range + uninstall opt-in.** Save handler now guards `manage_woocommerce` per-handler; delivery radius accepts fractional km (2.5, 3.7, etc.) via the new `step=0.1` input and a float sanitize (was `absint`); restaurant-coordinates save now validates ±90/±180 and surfaces a transient admin notice on bad input instead of silently keeping an out-of-range value; new `fxw_remove_on_uninstall` checkbox controls whether uninstall.php wipes the option, role, and saved delivery profiles
- **Cart-block closed/minimum notices.** Block-based cart (and checkout) pages now show the same store-closed and minimum-order notices as the classic pages — rendered via a `render_block` filter on the `woocommerce/store-notices` block, bounded to `is_cart()`/`is_checkout()`
- **Reporting site-timezone day boundary.** The dashboard "Today's Report" now uses a `>=... <...` date range anchored on the site's `wp_date` day, so non-UTC stores no longer get a report that drifts by ±1 day near midnight
- **Dashboard `fxw-in-kitchen` whitelist.** Both dashboard status-update handlers (form + AJAX) now accept `fxw-in-kitchen` so admins can move an order into the kitchen state from the dashboard (previously only possible from the order-edit dropdown, never the deliveries dashboard)
- **Dead code removed.** `admin-dashboard.js` (bound selectors that don't exist in the markup) replaced with a no-op stub that documents why; the orphan `fxw_address_unit` save branch and read in the order meta box deleted (the input was never rendered; legacy meta is preserved in WC's meta store)

## [1.2.15] - 2026-08-17

### Fixed (full-flow audit — 3 blockers, 3 majors, cleanup pass)
- **Checkout can now complete on default installs.** WooCommerce never initializes the session for custom REST routes (`is_request('frontend')` excludes REST requests), so the pinned coordinates from `validate-location` were written into a `null` session and lost — order placement always failed with "Please select your exact location on the map." The handler now bootstraps the session exactly the way WooCommerce's own Store API does (`wc_load_cart()` — session + customer + cart, restores any existing session from the cookie, saves on shutdown) and explicitly sets the session cookie for brand-new guest sessions. With the cart loaded, the REST fee estimate also sees the real subtotal, so **free-delivery-threshold pricing now applies to the toast**, not just the shipping method
- **Pricing Rules and Opening Hours sections now render on the FoodXpress settings tab.** The `fxw_settings_register_extra_fields` action fired inside a field renderer mid-render — `do_settings_sections()` had already snapshotted the section list, so the sections were never output and every settings save silently reset pricing/hours to defaults. The action now fires at registration time (`admin_init`). The two sanitize callbacks additionally preserve stored values defensively when their section's fields fail to post
- **Receipt printing works again on every path.** (1) The `fxw_print_receipt` AJAX handler lives in `FXW_Shortcodes`, which only loaded when `is_admin()` was false — but `admin-ajax.php` reports `is_admin() === true`, so the handler never registered and the buttons printed "0". The class now loads on AJAX requests too. (2) The meta-box "Print Receipt" button opens a frontend link, but its `template_redirect` handler lived in `FXW_Order_Admin`, which only loaded in the admin — the link opened the homepage. That class now loads on all requests (its admin hooks are no-ops on the frontend)
- **Saved-address prefill now actually runs.** `load_saved_address()` was hooked to `wp_loaded`, where `is_checkout()` is always false (the main query is parsed later, at `wp`) — the body never executed. Moved to `wp`
- **Honest feature-scope declaration:** the blocks integration relies on the Additional Checkout Fields API (WooCommerce 8.9+); `cart_checkout_blocks` compatibility is now declared version-aware instead of unconditionally true, so stores on WC 7.0–8.8 no longer get a false compatibility claim
- **Delivery agents are now notified when an order is assigned to them.** All three assignment paths (dashboard form, dashboard AJAX, order-edit meta box) transition the order to `fxw-assigned`, so a single hook on that status sends the rider a `wp_mail` with the order number, delivery address, total, COD collection hint, and dashboard link; the send result is logged as an order note either way. Previously the three email classes were all customer-facing and assignments happened silently
- **Re-order no longer drops variations** — variation items are re-added with their variation ID and attributes instead of the parent product only
- **Track-order email check is now case-insensitive** (emails are case-insensitive by definition; WC stores them lowercased but input may arrive in any case)

### Changed
- New `delivery_boy` installs no longer grant `edit_posts` — `read` alone grants basic wp-admin access, and the capability was never needed (the incorrect "required to access the admin area" comment is corrected); existing installs are unaffected
- Success toast after pinning a location uses dedicated labels ("Delivery fee: …" / "Free delivery") instead of reusing the "Calculating…" string with the ellipsis stripped

## [1.2.14] - 2026-08-17

### Fixed (currency is 100% WooCommerce-driven)
- **The map's fee toast now uses store-formatted prices**: the REST endpoint returns `fee_formatted` from `wc_price()` — correct currency, decimals, thousand separators, and symbol position (prefix or suffix) for whatever the store uses, with no currency hardcoded anywhere. Previously the toast built `symbol + raw number` in JS, which ignored store formatting (e.g. `$6.4` instead of `$6.40`, wrong position for suffix currencies); the JS fallback also derives from `get_woocommerce_currency_symbol()`, never a literal
- **LANDING-PAGE.md** currency examples made store-currency-neutral, and the stale "$99 Lifetime" pitch corrected to the actual free/open-source (GPL-3.0-or-later) positioning; verified zero hardcoded currency symbols or codes (USD/BDT/INR/৳/₹/$) anywhere in runtime code — every amount renders through `wc_price()` or `get_woocommerce_currency_symbol()`

## [1.2.13] - 2026-08-17

### Added
- **Admin-configurable pricing rules** (`FXW_Pricing`, new *Pricing Rules* section on the FoodXpress settings tab — nothing hardcoded):
  - **Fee structure**: choose *base + per-km* (classic) or **distance tiers** — up to five "up to X km → flat fee" rows, matched by the calculated distance; a tier with `0` km means "and above"; distances beyond every tier get no rate
  - **Free delivery threshold**: order subtotal at or above the configured amount gets delivery free (0 disables)
  - **Minimum order amount**: below the configured subtotal no delivery orders are accepted (0 disables) — no shipping rate, a cart-page notice telling the customer how much more to add, and a checkout validation error on both classic and blocks checkouts
  - Applied consistently at every decision point: the shipping method, classic and blocks validation, the REST fee estimate, and the legacy opt-in cart-fee path all resolve fees through `FXW_Pricing`

## [1.2.12] - 2026-08-17

### Fixed (enterprise review — critical items)
- **Map ID + classic-marker fallback** — `AdvancedMarkerElement` requires a real Cloud Console Map ID, so the hardcoded placeholder could break the draggable pin on production keys. The Map ID is now an optional setting (`fxw_google_maps_map_id`); when empty, the map uses the classic `google.maps.Marker`, which works with any API key — no cloud configuration required
- **Dual Google Maps keys** — new optional *Server Key* setting (`fxw_google_maps_server_key`) for Geocoding/Distance Matrix, so merchants can restrict the browser key by HTTP referrer and the server key by IP (previously one key had to serve both, making proper key restriction impossible). Falls back to the shared key when empty

### Added
- **Scheduled opening hours** (`FXW_Store_Hours`) — an optional per-day schedule (time inputs + all-day-closed checkboxes, overnight spans like 18:00–02:00 supported) on the FoodXpress settings tab. The admin-bar toggle remains the manual master close; the schedule additionally pauses ordering outside hours, and closed notices tell customers when you reopen ("We reopen Sunday at 09:00.")

### Changed (hardening)
- Google Maps server calls now retry once (300 ms backoff) on transient failures — network errors or 5xx — smoothing brief API hiccups at checkout

## [1.2.11] - 2026-08-17

### Changed
- **The "Deliveries: Open/Closed" admin-bar switch now visibly controls order placement end to end.** Previously, closing removed the delivery rate and blocked at validation — customers just hit a generic "no shipping method selected" with no explanation. Now:
  - A clear error notice on the **cart and classic checkout** pages ("We are currently closed for deliveries. You can browse and fill your cart — ordering will be available as soon as we reopen.") via `wc_print_notice` on the documented `woocommerce_before_cart` / `woocommerce_before_checkout_form` hooks
  - The same notice renders on **block-based checkout** pages above the Checkout block
  - Server-side placement stays hard-blocked in both checkouts, with the closed check now running **first** in validation so its message dominates; the shipping method offers no rate; the REST `validate-location` endpoint reports closed on every pin drop
  - New single source of truth `FXW_Checkout::is_store_open()` replaces the four scattered `fxw_is_open` normalizations (shipping method, zone validation, blocks check, notices)

## [1.2.10] - 2026-08-17

### Fixed (shop-manager capability audit)
- **Settings now live as a native tab under WooCommerce → Settings** (`FXW_Settings`, via the documented `woocommerce_settings_tabs_array` / `woocommerce_settings_{tab}` / `woocommerce_update_options_{tab}` hooks — names verified against WooCommerce core source). Previously a standalone WordPress-Settings page gated by `manage_options`, which (a) blocked shop managers who can configure every other WooCommerce shipping/fee setting, (b) contradicted the plugin's own "delivery is not configured" warning (shown to `manage_woocommerce` users, linking to a page they couldn't open), and (c) didn't match the documented "WooCommerce → Settings → FoodXpress" location. Saving is gated consistently (`manage_woocommerce` on `register_setting` + the WC settings nonce)
- **Admin-bar "Deliveries: Open/Closed" toggle now works for shop managers** — it was displayed to admins *or* shop managers but the AJAX action accepted admins only, so shop managers saw the toggle and got "Unauthorized access" on click. The action now matches the display capability
- The configuration warning's settings link now points to the new tab URL

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
