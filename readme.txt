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
3. Go to WooCommerce → Settings → FoodXpress and pick a map provider (see Configuration below for getting API keys).
4. Set your restaurant location on the map, delivery radius, and delivery fee settings.
5. Enable the FoodXpress Delivery method in your shipping zone(s), or let the plugin add it automatically.
6. Create users with the "Delivery Boy" role — their mobile app lives at the Delivery Dashboard link on their profile menu.

== Configuration ==

= Map provider: which one? =

* **OpenStreetMap (default, no key)** — free, works instantly. Best for small/low-volume stores; address lookups are throttled by OSM's usage policy.
* **Google Maps** — best data quality and road distances; needs a Google Cloud account with billing enabled.
* **MapTiler** — free key, 100k tile loads + geocodes/month; no road routing.
* **Geoapify** — free key, 3,000 requests/day covering tiles, geocoding and road routing.

You can switch providers at any time — orders already placed are not affected.

= Getting a Google Maps key =

1. Go to [Google Cloud Console](https://console.cloud.google.com/) and create (or pick) a project.
2. Enable **Maps JavaScript API**, **Geocoding API**, and **Distance Matrix API**.
3. Create an API key under APIs & Services → Credentials.
4. Paste it into WooCommerce → Settings → FoodXpress → Map Provider → "Google Maps JavaScript API Key".
5. **Recommended:** also paste a *second* key into the "Server-side API Key" field and restrict each one:
   * Browser key → restrict to your domain under Application restrictions → HTTP referrers (`https://*.yourdomain.com/*`).
   * Server key → restrict to your server's IP address. The plugin automatically uses this one for behind-the-scenes distance/geocoding calls.

= Getting a MapTiler or Geoapify key =

MapTiler: sign up at [cloud.maptiler.com](https://cloud.maptiler.com/account/keys/) and copy a key.
Geoapify: sign up at [myprojects.geoapify.com](https://myprojects.geoapify.com/) and create a project key.
Paste either into the "Provider API Key" field that appears when you select that provider. Restrict the key to your domain in the provider's dashboard if it offers referrer restrictions.

= Delivery fees =

Choose flat rate, per-kilometre, or distance tiers under Delivery Fee Settings — only the fields relevant to your choice are shown. Optional free-delivery threshold and minimum order amount are on the same screen.

= Opening hours =

Set per-weekday times, tick "Open all day" for 24-hour days, or use the special-occasion override to stay open despite a closed schedule. The admin-bar "Deliveries: Open/Closed" toggle is always the master switch.

== Security Recommendations ==

This plugin handles real orders and real money (including cash on delivery). A few settings matter beyond the plugin itself:

* **Use HTTPS.** Checkout coordinates, rider sessions, and admin logins must travel encrypted. Most hosts offer free Let's Encrypt certificates.
* **Restrict your map keys.** Browser-exposed keys (Google JS, MapTiler, Geoapify tiles) should be locked to your domain in the provider's dashboard; the Google server-side key should be locked to your server IP. Keys without restrictions can be abused if extracted from your page source.
* **Limit who sees what.** Managers/admins need `edit_shop_orders` or `manage_woocommerce`; riders only need the "Delivery Boy" role — they see only their own assigned orders and never other agents' data.
* **Cash settlement is trust-based by design.** The plugin records exactly how much cash each rider collected and what was handed over (with who approved what, when), but physical cash counts must still be reconciled by a human.
* **Back up before big changes.** Standard WordPress database backups cover all FoodXpress data (orders stay in WooCommerce's own tables).

== Frequently Asked Questions ==

= Do I need a Google Maps API key? =

No. Pick the OpenStreetMap provider and the plugin uses bundled Leaflet plus free OSM tiles and Nominatim geocoding. Use Google Maps if you prefer its data quality and already have a key.

= How is the delivery fee calculated? =

From the straight-line (haversine) distance between your restaurant coordinates and the customer's dropped pin. You choose flat, per-km, or tiered pricing, and can set a minimum order amount and a free-delivery threshold.

= Does it work with HPOS (high-performance order storage)? =

Yes. FoodXpress declares full HPOS compatibility and reads/writes order data only through WooCommerce CRUD APIs.

= Which payment methods work? =

All of them. FoodXpress handles fulfilment, not payment. For cash on delivery it additionally tracks how much each rider has collected and pending hand-over.

= Are my API keys safe? =

Keys are stored in your own WordPress database, never sent anywhere except the map provider they belong to, and never printed in frontend JavaScript data (only in the map-provider URLs that require them). Restricting keys to your domain/server IP as described under Security Recommendations closes the remaining abuse vector.

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
