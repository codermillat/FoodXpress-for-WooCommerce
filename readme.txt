=== FoodXpress for WooCommerce ===
Contributors: codermillat
Tags: woocommerce, delivery, food delivery, restaurant, local delivery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Map-based delivery management for single-restaurant WooCommerce stores: location picker at checkout, distance fees, custom order statuses, and a mobile delivery-agent app.

== Description ==

FoodXpress turns a single-restaurant WooCommerce store into a full delivery operation. Customers drop a pin on a map at checkout; the store owner manages orders through a dedicated deliveries dashboard; riders work from an installable mobile web app.

= Checkout =

* Interactive map location picker (Google Maps with an API key, or a free OpenStreetMap + Leaflet mode with no key required)
* Distance-based validation — orders outside your delivery radius are blocked before payment
* Distance-based delivery fee: flat rate, per-km rate, or distance tiers, with an optional free-delivery threshold and minimum order amount
* Live fee update in the Order summary as the customer moves the pin
* Store opening hours: per-weekday schedule, "open all day" / "closed all day" toggles, and a special-occasion override
* Estimated arrival time shown to the customer (kitchen prep time + travel time)

= Store management =

* Deliveries dashboard listing unassigned, assigned, and out-for-delivery orders, including the customer's kitchen note
* Custom order statuses: In Kitchen → Assigned → Picked Up → Completed, each with its own WooCommerce email notification
* One-click assignment of delivery riders, with automatic reassignment handling
* Admin-bar toggle to open/close ordering instantly

= Delivery rider app =

* Installable PWA (Add to Home Screen) that looks and feels like a native app
* Live order list with New / In Progress / Delivered tabs and automatic refresh when new orders arrive or statuses change
* Per-order actions: call the customer, open the exact delivery location in maps, mark picked up / delivered
* Cash-on-delivery awareness: shows the amount to collect per order and a running "cash in hand" total
* Rider-initiated cash settlement requests that a manager or admin accepts or rejects, with a full audit ledger
* Riders see delivery instructions only — kitchen notes stay private to the store

= Customer features =

* Order tracking page showing live status and, once assigned, the rider's name and phone number
* Reorder shortcut and an upgraded My Account dashboard with order stats and saved delivery address
* Thermal-printer-friendly receipt template

= Privacy & external services =

The plugin is self-contained except for the map provider you choose:

* **Google Maps mode:** the checkout page loads JavaScript from `maps.googleapis.com`, and geocoding/routing requests are sent server-side to `maps.googleapis.com`. The customer's chosen coordinates (latitude/longitude) and your store address are sent to Google. Your Google Maps API key is stored in your own database and never sent anywhere else.
* **OpenStreetMap (Leaflet) mode:** the browser loads map tiles from `tile.openstreetmap.org` and the plugin sends coordinate lookups to `nominatim.openstreetmap.org`. The customer's chosen coordinates and the store address are sent to those services.
* No other data leaves your site. The plugin contains no telemetry, analytics, or advertising.

== Installation ==

1. Install and activate WooCommerce first.
2. Upload the plugin files to `/wp-content/plugins/foodxpress-for-woocommerce`, or install through the WordPress plugins screen.
3. Go to WooCommerce → Settings → FoodXpress and pick a map provider. Google Maps needs an API key (Maps + Geocoding + Distance Matrix enabled); the OpenStreetMap option works with no key.
4. Set your restaurant location on the map, delivery radius, and delivery fee settings.
5. Enable the FoodXpress Delivery method in your shipping zone(s), or let the plugin add it automatically.
6. Create users with the "Delivery Boy" role — their mobile app lives at the Delivery Dashboard link on their profile menu.

== Frequently Asked Questions ==

= Do I need a Google Maps API key? =

No. Pick the OpenStreetMap provider and the plugin uses bundled Leaflet plus free OSM tiles and Nominatim geocoding. Use Google Maps if you prefer its data quality and already have a key.

= How is the delivery fee calculated? =

From the straight-line (haversine) distance between your restaurant coordinates and the customer's dropped pin. You choose flat, per-km, or tiered pricing, and can set a minimum order amount and a free-delivery threshold.

= Does it work with HPOS (high-performance order storage)? =

Yes. FoodXpress declares full HPOS compatibility and reads/writes order data only through WooCommerce CRUD APIs.

= Which payment methods work? =

All of them. FoodXpress handles fulfilment, not payment. For cash on delivery it additionally tracks how much each rider has collected and pending hand-over.

= Can customers see the rider's contact details? =

Only after you assign a rider to their order, and only the rider's name and phone number, on the order-tracking page.

= Where do riders get the app? =

It is not a separate download. A rider logs into your site, opens the Delivery Dashboard page, and uses "Add to Home Screen" in their browser to install it as a PWA.

== Screenshots ==

1. Map location picker on the checkout with live delivery fee.
2. Deliveries dashboard for store managers.
3. Mobile delivery-rider app with cash-on-delivery totals.
4. FoodXpress settings inside WooCommerce.

== Changelog ==

= 1.4.0 =
* Customer My Account dashboard overhaul.
* Delivery-rider PWA: installable app, live auto-refreshing order list, COD collection totals and settlement workflow with manager approval.
* Settings redesigned for non-technical admins: fields appear based on the selected map provider and fee mode; masked API-key input; clearer opening-hours controls ("open all day", special-occasion override).
* Kitchen notes now visible only to managers/admins; riders see delivery instructions only.
* Fixed: admin-bar open/close toggle, weekly-hours saving, receipt printing, unassign crash, and more — see CHANGELOG.md.

= 1.3.9 =
* Address deduplication on receipts and order screens; one-time migration normalises existing orders; free-delivery rates labelled clearly.

Earlier releases: see CHANGELOG.md in the repository.

== Upgrade Notice ==

= 1.4.0 =
Adds the rider PWA and settlement workflow, and redesigns settings. Update from any 1.2/1.3 version safely — no manual steps required.
