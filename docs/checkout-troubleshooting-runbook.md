# FoodXpress Checkout Troubleshooting Runbook

Use this guide to diagnose “No shipping method has been selected”, “Please select your location on the map.”, geolocation errors, and missing/incorrect delivery fees.

## What’s implemented (server + client)

- Server
  - Distance-based shipping via `FXW_Shipping_Method` with logs and graceful errors.
  - Default selection of FoodXpress rate when available (`woocommerce_shipping_chosen_method`).
  - Validation: closed-store message, out-of-zone message, and API error propagation.
  - `fxw_debug_status` AJAX: masked settings, shipping zones/methods, session/customer snapshot.
  - Extra delivery fee gated by `fxw_enable_extra_delivery_fee` (disabled by default) and double-charge prevention when FoodXpress shipping is chosen.
- Client
  - Google Maps + Places Autocomplete, draggable marker, “Use My Location”.
  - Syncs billing + shipping fields; triggers `.change()` and debounced `update_checkout`.
  - Debug button (visible when `WP_DEBUG` is true) to call diagnostics endpoint.

---

## Quick validation checklist (5 minutes)

1) WooCommerce shipping zone
- Ensure a zone applies to your store’s region.
- Add method “FoodXpress Delivery” inside that zone and enable it.

2) FoodXpress settings (Woo Admin → FoodXpress → Settings)
- Restaurant address present.
- Google API key present with Geocoding + Distance Matrix enabled.
- “Open for deliveries” is ON.
- Delivery radius reasonable (e.g., 10–15 km).

3) Checkout browser diagnostics
- Open the checkout page with DevTools (Network + Console).
- Enter address via autocomplete or use the draggable pin.
- Confirm:
  - `fxw_get_restaurant_location` returns `{ success: true, data: { lat, lng } }`.
  - `fxw_update_customer_location` payload includes `lat`, `lng`, and address fields; response `{ success: true }`.
  - A Woo session cookie exists for the checkout origin (Application → Cookies → `wp_woocommerce_session_*`).
- If `WP_DEBUG` is true, click “FXW Debug” button:
  - Session shows `customer_lat`, `customer_lng`.
  - Zones list shows a FoodXpress method with `enabled: true`.
  - Settings show masked API key, `fxw_is_open: true`, and configured radius.

---

## What to capture from the browser

- `fxw_get_restaurant_location` (Network → Preview)
  - Expected: `success: true`, valid `lat`/`lng`.
- `fxw_update_customer_location` (Network → Request Payload + Preview)
  - Request payload: lat/lng + address fields.
  - Response: `success: true`.
- “FXW Debug” (Network → `fxw_debug_status`)
  - Look for:
    - `session.customer_lat`/`customer_lng`.
    - `zones[].methods[]` includes `{ id: 'foodxpress_delivery', enabled: true }`.
    - `fxw_settings`: `fxw_is_open: true`, reasonable `fxw_delivery_zone_radius`.

---

## WooCommerce logs (WooCommerce → Status → Logs)

Select the latest `foodxpress-*.log` and search for entries:

- Missing session coordinates
  - `calculate_shipping: missing session lat/lng`
  - Likely cause: session cookie not set on the same origin, or AJAX update not called. Confirm `fxw_update_customer_location` success and cookie exists.
- Store closed
  - `store closed (fxw_is_open=false)` → turn the setting on.
- Distance API problems
  - `Distance API request failed` / `Distance API status not OK` / `element status` warnings → verify API key + enable Geocoding & Distance Matrix.
- Out of zone
  - `out of zone (distance=..., radius=...)` → increase radius or reject gracefully.
- Rate added + selection
  - `adding rate cost=...` (rate computed)
  - `prefer_fxw_shipping: selecting foodxpress_delivery...` (rate auto-selected)

---

## Expected checkout behavior

- In-zone:
  - Rate “FoodXpress Delivery” appears and is auto-selected.
  - ETA visible (Cart + Checkout) if distance was computed.
  - No “No shipping method…” or “Select your location…” errors.
- Out-of-zone:
  - Checkout shows “Sorry, we do not deliver to your location.”
- Closed store:
  - Checkout shows “We are currently closed for deliveries. Please try again later.”
- API issues:
  - Checkout shows “We could not calculate delivery distance. Please verify your address and try again.”

---

## Common causes and fixes

- Session not set
  - Symptom: `missing session lat/lng` in logs.
  - Fix: Ensure `fxw_update_customer_location` returns success; confirm `wp_woocommerce_session_*` cookie exists. Avoid cross-domain/proxy mismatches on admin-ajax calls.
- Zone doesn’t include method
  - Symptom: No FoodXpress rate despite valid coords.
  - Fix: Enable FoodXpress method in matching shipping zone; check Zone “Locations” covers the customer destination.
- Google APIs not fully enabled
  - Symptom: Geocoding/Distance Matrix errors or no coords.
  - Fix: Enable both Geocoding and Distance Matrix for the key; verify billing restrictions and domain/IP constraints.
- Double charging
  - Prevented by default. Extra fee is off unless `fxw_enable_extra_delivery_fee` is explicitly set.

---

## Useful snippets

- Toggle extra delivery fee (off by default)
```php
// php
update_option('fxw_enable_extra_delivery_fee', 'yes'); // or 'no'
```

- Trigger status debug in console (when WP_DEBUG is true)
```javascript
// javascript
window.fxwDebugStatus && window.fxwDebugStatus();
```

- Verify settings via AJAX response example
```json
{
  "success": true,
  "data": {
    "fxw_settings": {
      "fxw_restaurant_address": "123 Main St, City",
      "fxw_google_maps_api_key": "ABCD************WXYZ",
      "fxw_is_open": true,
      "fxw_delivery_zone_radius": "12"
    },
    "zones": [
      {
        "zone_id": 1,
        "zone_name": "Local",
        "methods": [
          { "id": "foodxpress_delivery", "instance_id": 3, "enabled": true, "title": "FoodXpress Delivery" }
        ]
      }
    ],
    "session": {
      "customer_lat": 12.34,
      "customer_lng": 56.78,
      "chosen_shipping_methods": ["foodxpress_delivery:3"]
    },
    "customer": {
      "shipping_country": "IN",
      "shipping_state": "WB",
      "shipping_postcode": "700001",
      "shipping_city": "Kolkata"
    }
  }
}
```

---

## Test matrix

- In-zone happy path: Autocomplete → success AJAX → rate auto-selected → ETA shown → place order.
- Out-of-zone: Select far address → error notice.
- Closed store: Toggle `fxw_is_open = false` → error notice.
- Ship to different address: Toggle checkbox → autocomplete on shipping fields → success.
- Guest vs logged-in: Confirm behavior identical; session cookie must exist for guests.
