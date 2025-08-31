# Active Context: FoodXpress for WooCommerce

This file tracks the current work focus, recent changes, and next steps. Updated after implementing critical security and standards compliance fixes.

---

## 1. Current Work Focus

- **CRITICAL FATAL ERROR RESOLVED: Duplicate enqueue_scripts Method**
- Fixed "Cannot redeclare FXW_Checkout::enqueue_scripts()" fatal error
- Removed duplicate method and standardized nonce usage
- Plugin should now load without fatal errors
- Ready for testing and deployment

## 2. Critical Fixes Completed

### Google Maps Callback Function Fix (JUST COMPLETED)
- **Issue**: Console error "Uncaught (in promise) InvalidValueError: fxwInitMap is not a function" preventing map initialization
- **Root Cause**: Callback function was defined inside jQuery wrapper, making it unavailable when Google Maps script loaded
- **Solution**: Restructured JavaScript to define callback function immediately when script loads
- **Changes Made**:
  - **Script Loading Order (`includes/class-fxw-checkout.php`)**: 
    - Fixed dependency chain: checkout.js loads first, then Google Maps script depends on it
    - Ensures callback function is available before Google Maps tries to call it
  - **JavaScript Structure (`assets/js/checkout.js`)**:
    - Moved global variables outside jQuery wrapper for proper scope
    - Created `window.fxwInitMap` callback function outside jQuery wrapper 
    - Added `window.fxwInitializeMap` as intermediate function with retry logic
    - Added `window.fxwInternalInit` to bridge global callback to jQuery-wrapped functions
    - Added robust error handling and retry mechanisms for timing issues
- **Status**: Map should now initialize reliably without callback errors

### Previous Google Maps Loading Fix (PREVIOUSLY IMPLEMENTED)
- **Issue**: Interactive map on checkout page showing "This page can't load Google Maps correctly" error despite valid API key
- **Root Cause**: Race condition where JavaScript tries to initialize map before Google Maps script is fully loaded
- **Solution**: Implemented proper callback pattern for Google Maps API loading
- **Changes Made**:
  - **PHP (`includes/class-fxw-checkout.php`)**: Added `&callback=fxwInitMap` parameter to Google Maps script URL
  - **JavaScript (`assets/js/checkout.js`)**: Created global `window.fxwInitMap` function that Google Maps calls when ready
  - **Fallback**: Maintained immediate initialization if Google Maps is already loaded
- **Status**: SUPERSEDED by callback function fix above

### Plugin Load Order Fix (PREVIOUSLY IMPLEMENTED)
- **Issue**: Plugin showed "FoodXpress for WooCommerce requires WooCommerce to be installed and active" despite WooCommerce being properly installed
- **Root Cause**: Plugin was checking `class_exists('WooCommerce')` immediately on file load, before WooCommerce was loaded by WordPress
- **Solution**: Wrapped entire plugin initialization in `fxw_init_plugin()` function hooked to `plugins_loaded` action
- **Status**: Plugin now properly recognizes active WooCommerce installation and loads correctly

## 3. Critical Security & Standards Fixes (PREVIOUSLY IMPLEMENTED)

### Security Vulnerabilities Fixed:
- **XSS Vulnerability**: Fixed unescaped output in `FXW_Checkout::display_eta()` - added `esc_html()` around `$eta` variable
- **API Key Exposure**: Added proper validation to prevent Google Maps script loading with empty API key

### WordPress/WooCommerce Standards Compliance:
- **WooCommerce Dependency Check**: Replaced fragile `in_array()` check with robust `class_exists('WooCommerce')`
- **Text Domain Loading**: Added proper `load_plugin_textdomain()` call on `plugins_loaded` hook
- **Function Guards**: Added `function_exists()` guards around all function definitions
- **Rewrite Rules Lifecycle**: Moved rewrite rules from activation-only to `init` hook + added deactivation cleanup

### Reliability Improvements:
- **Google Maps API Error Handling**: 
  - Added 15-second timeouts to all `wp_remote_get()` calls
  - Sanitized API errors before displaying to users
  - Added proper HTTP status code checking
  - User-friendly error messages instead of raw API responses
- **Asset Loading**: Conditional Google Maps script loading based on API key availability
- **Logging**: Enhanced error logging via `wc_get_logger()` for debugging

## 3. Previous Changes (Shipping/Geolocation Features)

- Core loader/AJAX hooks:
  - `includes/class-fxw-core.php` now loads `FXW_Checkout` on frontend and during AJAX (`wp_doing_ajax()`), ensuring `wp_ajax_*` hooks register for admin-ajax requests. This resolves 400/"0" responses for `fxw_update_customer_location`, `fxw_get_restaurant_location`, and `fxw_debug_status`.

- Shipping class hardening:
  - Added early guard in `includes/class-fxw-shipping-method.php` to return if `WC_Shipping_Method` is not loaded.
  - Shipping method still loaded via `woocommerce_shipping_init` and registered via `woocommerce_shipping_methods` (correct lifecycle).

- Mapping service normalization:
  - `includes/services/class-fxw-mapping-service.php` now accepts `{lat,lng}` arrays, `"lat,lng"` strings, or full address strings via `normalize_location()`.
  - `get_distance()` adds `units=metric`, `mode=driving`, consistent error handling, and logs errors via `wc_get_logger()`.

- Checkout JS sync and stability:
  - `assets/js/checkout.js` now fills both `billing_*` and `shipping_*` fields when not shipping to a different address.
  - Country/State selects trigger `.change()` to update Woo validation/select2.
  - Introduced debounced `update_checkout` to avoid race conditions.
  - Continues to POST lat/lng + parsed address to backend, which stores coords in `WC()->session`.
  - Sets `autocomplete="off"` on `#fxw-location-search-input` to prevent native browser autocomplete from interfering with Places Autocomplete UX.
  - Coordinates lock on frontend ensures subsequent `address_1` or unit changes do not recalc distance or rates.

- Distance data reuse + user feedback:
  - Shipping method stores `fxw_distance_data` (distance + duration) in session upon successful distance lookup.
  - On API error, logs details and surfaces a concise checkout notice to the customer.

- ETA display:
  - `FXW_Checkout::display_eta()` now hooked on: `woocommerce_review_order_after_shipping` and `woocommerce_cart_totals_after_shipping` (shows ETA near shipping calculator on checkout/cart).

- NEW: ETA label on shipping method
  - Implemented `FXW_Checkout::append_eta_to_label()` and registered it via `woocommerce_cart_shipping_method_full_label`.
  - For the `foodxpress_delivery` method, appends `ETA ~ N mins` using `fxw_distance_data.duration + prep_time`.

- NEW: Server-side coords lock + debug visibility
  - `FXW_Checkout::update_customer_location()` now sets `WC()->session->set( 'fxw_coords_locked', true )`.
  - `FXW_Checkout::debug_status()` now includes `session.fxw_coords_locked` in its JSON response for observability.
  - Complements frontend coordinate locking so edits to flat/house/unit don’t break shipping rates.

## 3. Next Steps

- Validate in browser:
  - Happy path: in-zone location → shipping rate appears with no validation errors.
  - Out-of-zone: clear “outside delivery zone” message with no rates.
  - Missing location: “Please select your location on the map.”
  - “Ship to a different address” checked: ensure shipping_* fields drive destination and rates.
  - Verify ETA appears both:
    - Next to shipping calculator blocks (cart/checkout).
    - Appended to the `foodxpress_delivery` shipping method label.

- Select2 and country/state synchronization:
  - Ensure Select2 stays in sync after programmatic updates (country first, then state with delay as needed).

- Error hardening:
  - Handle Google API failures (missing key/quota/denied) with single user-facing notice and logs.

- Double charging review:
  - Confirm whether extra delivery fee should coexist with shipping rate. If shipping rate already represents delivery cost, keep `add_delivery_fee()` gated to avoid double charge.

- Documentation:
  - Keep `memory-bank/progress.md` and `docs/checkout-troubleshooting-runbook.md` aligned with the latest flows and flags (`fxw_coords_locked`, ETA label).

## 4. Active Decisions & Preferences

- Use session `customer_lat`/`customer_lng` as the single source of truth for delivery location.
- Centralize all distance/ETA computations in `FXW_Mapping_Service` (DRY).
- Maintain shipping logic in `WC_Shipping_Method` rather than fees, for compatibility with zones and other methods.
- Keep UX responsive: Places Autocomplete, draggable marker, geolocation, and a debounced checkout update.

## 5. Important Patterns & Insights

- Guard patterns:
  - Plugin bootstrap checks WooCommerce active before proceeding.
  - Shipping class defends against early load to prevent admin fatal errors.

- Data flow:
  - Frontend selects location → AJAX → backend sets session (+ `fxw_coords_locked`) + customer shipping data → totals recalculated → shipping method reads session → rate computed → ETA shown near shipping calculator and in shipping label.

- Observability:
  - Use `wc_get_logger()` for API failures and unexpected statuses to aid debugging without exposing internals to users.
  - `fxw_debug_status` exposes key session fields: `customer_lat`, `customer_lng`, `chosen_shipping_methods`, and `fxw_coords_locked`.
