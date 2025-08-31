---
task_progress: 68
unit: percent
last_updated: 2025-08-24T08:51:12Z
---

# FoodXpress Geolocation, Checkout, Shipping, Dashboards — Task Progress

Legend: - [ ] todo, - [x] done

## Setup & Configuration
- [x] FXW_Core loads on frontend and during AJAX (registers checkout handlers)
- [x] Shipping method deferred to `woocommerce_shipping_init` and guarded for `WC_Shipping_Method`
- [x] Mapping Service normalized (metric/driving) with consistent error handling
- [ ] Verify Google API key has Geocoding + Distance Matrix enabled
- [ ] Verify Restaurant Address setting is valid/non-empty

## Backend: Shipping + Validation
- [x] `calculate_shipping`: fallback geocode from customer shipping address when session coords missing; persist session
- [x] `calculate_shipping`: persist `fxw_distance_data`; add rate when within radius
- [x] `validate_delivery_zone`: fallback geocode and persist session, then validate
- [ ] Ensure FoodXpress method enabled in active Shipping Zone(s); test zone match (country/state/postcode)
- [x] Auto-select FoodXpress rate when present (verify `woocommerce_shipping_chosen_method` behavior)

## Checkout AJAX/UX
- [x] Checkout AJAX registered during admin-ajax (no more 0/400)
- [x] `fxw_update_customer_location`: sets billing + shipping on `WC()->customer`, saves, persists session
- [x] JS updates billing_* and shipping_* with `.change()`; debounced `update_checkout`
- [x] JS `tryPrefillFromShippingFields()` geocodes prefilled saved/account address on load
- [x] Prevent overwriting house/flat in Address_1 if user-edited
- [ ] Confirm no “country/state required” errors with Select2 sync
- [x] Flat/House/Unit field (fxw_address_unit) added, never geocoded, persists to order meta and delivery views
- [x] JS: lock coords after first geocode, prevent Address_1 edits from breaking rates, persist coords in hidden fields

## Fees / ETA
- [x] Persist `fxw_distance_data`; ETA display hooks present
- [x] Gate `add_delivery_fee()` to avoid double-charge when FoodXpress shipping rate is applied
- [ ] Confirm ETA renders on cart/checkout from session data

## Observability
- [x] `wc_get_logger()` instrumentation across critical paths (source: `foodxpress`)
- [ ] Review logs for failing paths and capture examples in `docs/checkout-troubleshooting-runbook.md`
- [ ] Optional: admin debug endpoint/link on checkout showing session, customer dest, zone, coords

## Edge Cases & Errors
- [ ] Handle missing/invalid API key with clear notice and safe fallback
- [ ] Graceful notices for Google quota/timeouts; avoid silent failures
- [ ] Out-of-zone UX: explicit message; no ambiguous “No method selected”

## Dashboards & Assignment
- [x] Admin order meta box saves `_fxw_delivery_boy_id`
- [x] On assignment, optionally auto-set status to `wc-fxw-assigned` (setting-gated)
- [x] Deliveries Dashboard: include expected statuses and an Unassigned queue
- [x] Delivery Agent Dashboard: verify listing by `_fxw_delivery_boy_id` with status transitions (Picked Up, Delivered)
- [x] Ensure `delivery_boy` role exists and has required access

## Testing Matrix
- [ ] Saved Address flow (in-zone)
- [ ] Autocomplete + draggable pin flow
- [ ] Out-of-zone flow
- [ ] Ship-to-different on/off
- [ ] Guest vs logged-in customer behavior
- [ ] Mobile Safari/Chrome geolocation permission paths
- [ ] Checkout reload/back-navigation scenarios

## Documentation
- [x] Memory Bank updated for implemented fixes
- [ ] Update `docs/checkout-troubleshooting-runbook.md` with log signatures and remedies
- [ ] Update README/CHANGELOG with new behaviors, settings, and fallbacks

Notes: Update `task_progress` above and check items as they complete.
