# FoodXpress for WooCommerce — Landing Page Content & Instructions

> **Purpose:** This document contains all copy, feature descriptions, section layouts, FAQs, pricing, and technical specifications needed to build the premium landing page for FoodXpress for WooCommerce. Hand this to any designer or developer to produce the final page.

---

## Table of Contents

1. [Hero Section](#1-hero-section)
2. [Trust Bar](#2-trust-bar)
3. [Problem / Solution](#3-problem--solution)
4. [Feature Showcase](#4-feature-showcase)
5. [How It Works](#5-how-it-works)
6. [Live Demo Section](#6-live-demo-section)
7. [Feature Deep Dives](#7-feature-deep-dives)
8. [Comparison Table](#8-comparison-table)
9. [Technical Specifications](#9-technical-specifications)
10. [Pricing](#10-pricing)
11. [FAQ](#11-faq)
12. [Testimonials / Social Proof](#12-testimonials--social-proof)
13. [Final CTA](#13-final-cta)
14. [Footer Notes](#14-footer-notes)

---

## 1. Hero Section

### Headline
**Turn Your WooCommerce Store Into a Full-Featured Food Delivery Platform**

### Subheadline
Google Maps-powered delivery zones, real-time order tracking, a mobile delivery agent dashboard, and dynamic distance-based pricing — all inside WooCommerce. No monthly fees. No third-party dependencies.

### Primary CTA
**Get FoodXpress — $99 Lifetime**

### Secondary CTA
View Live Demo

### Hero Visual
Screenshot or animated GIF showing: the checkout with Google Maps pin, the delivery agent mobile dashboard, and the customer order tracking stepper — side by side in a mockup frame.

---

## 2. Trust Bar

### Compatibility Badges (display as icon + text row)
- WordPress 6.0 – 6.7+
- WooCommerce 7.0 – 9.4+
- PHP 7.4+
- HPOS Compatible
- Google Maps API
- Translation Ready

### Key Stats (display as 3-4 large number callouts)
- **27+** Premium Features
- **3** Custom Order Statuses
- **3** Automated Email Notifications
- **6** Structured Delivery Address Fields
- **8** AJAX-Powered Endpoints
- **100%** WooCommerce Native

---

## 3. Problem / Solution

### The Problem
Running a food delivery business on WooCommerce means duct-taping together five or six plugins: one for delivery zones, another for maps, a separate app for delivery agents, a custom solution for order tracking, and still no proper receipt printing. Each plugin adds bloat, conflicts, and monthly costs. Your customers get a clunky checkout, your delivery riders fumble with paper notes, and you have no real-time visibility into active deliveries.

### The Solution
**FoodXpress replaces all of that with a single, purpose-built plugin.** It adds a Google Maps-powered checkout with pin-drop accuracy, calculates delivery fees based on actual driving distance, gives your delivery agents a mobile-first dashboard they can use on their phones, shows your customers a beautiful 4-step order tracker with rider contact info, and lets you print branded thermal receipts — all without leaving WooCommerce.

---

## 4. Feature Showcase

> **Layout:** 3-column grid with icon, title, and 1-2 sentence description per card. Group into categories.

### Checkout & Maps

| Icon | Feature | Description |
|------|---------|-------------|
| Map Pin | **Interactive Google Maps Checkout** | Customers set their exact delivery location by searching, using GPS, or dragging the map pin. Three ways to pick a location — zero confusion. |
| Route | **Distance-Based Delivery Fees** | Fees are calculated using real Google Distance Matrix driving distance — not inaccurate straight-line calculations. Base fee + per-km pricing you control. |
| Target | **Delivery Zone Validation** | Orders outside your delivery radius are blocked at checkout with a clear message. No more wasted food on undeliverable orders. |
| Clock | **ETA on Checkout** | Estimated delivery time (driving time + your prep time) shown alongside shipping — customers know exactly when to expect their food. |
| Bookmark | **Saved Delivery Addresses** | Logged-in customers see their last address pre-loaded — faster repeat checkouts, higher conversion. |
| Form | **6-Field Structured Address** | House/flat, floor, building, block/tower, landmark, and delivery instructions — everything a rider needs to find the door, not just the building. |

### Store Management

| Icon | Feature | Description |
|------|---------|-------------|
| Dashboard | **Deliveries Dashboard** | A dedicated admin page showing unassigned, assigned, and in-transit orders. Assign riders, update statuses, and print receipts — all in one screen. |
| Toggle | **Open/Closed Toggle** | One click in the admin bar opens or closes your store for deliveries. Perfect for kitchen breaks, prayer times, or closing early. |
| Bell | **3 Automated Emails** | Customers get notified when their order is in the kitchen, when a rider is assigned, and when it's out for delivery — all customizable in WooCommerce email settings. |
| Receipt | **Branded Receipt Printing** | Print thermal receipts with your logo, restaurant name, tagline, itemized bill, and COD collection amount. Works with any receipt printer. |
| Status | **3 Custom Order Statuses** | "In the Kitchen", "Assigned", and "Picked Up" statuses map the real delivery lifecycle that WooCommerce doesn't provide out of the box. |
| Analytics | **Daily Delivery Report** | See today's total completed deliveries and delivery fee revenue right on the dashboard. |

### Delivery Agent Experience

| Icon | Feature | Description |
|------|---------|-------------|
| Mobile | **Mobile-First Agent Dashboard** | A standalone page at `/delivery-dashboard/` designed for phones — no WordPress admin clutter. Agents see their assigned orders, customer details, and action buttons. |
| GPS | **One-Tap Navigation** | Each order card links directly to Google Maps with GPS coordinates. Riders tap once and start driving — no address typing. |
| Phone | **Click-to-Call Customer** | Customer phone numbers are tap-to-call links. No copying, no dialing errors. |
| Battery | **Battery Optimized** | CSS containment, minimal animations, and no background polling — the dashboard won't drain riders' phones during a shift. |
| Touch | **Touch-Friendly UI** | 44px+ tap targets, swipe-friendly tabs, and no tiny buttons. Designed for gloved hands and bright sunlight. |
| Shield | **Secure Access** | Custom `delivery_boy` role with restricted capabilities. Agents can only see their own assigned orders — nothing else. |

### Customer Experience

| Icon | Feature | Description |
|------|---------|-------------|
| Tracker | **4-Step Order Tracker** | A beautiful visual stepper shows: In Kitchen > Assigned > Picked Up > Delivered. Animated, color-coded, and mobile-responsive. |
| Contact | **Rider Contact Card** | Once a rider is assigned, customers see their name and a prominent call button. Builds trust and reduces support tickets. |
| Search | **Track Any Order** | The `[fxw_track_order]` shortcode adds a public tracking page — enter order ID and email to see delivery status. |
| Refresh | **One-Click Re-Order** | A "Re-order" button on completed orders adds all items back to cart. Perfect for regular lunch orders. |
| Paint | **Styled My Account** | The WooCommerce My Account page gets a food-delivery-app makeover with card navigation, warm orange accents, and mobile-first layout. |

---

## 5. How It Works

> **Layout:** Horizontal 4-step timeline with icon, number, title, and description.

### Step 1: Install & Configure
Upload the plugin, enter your Google Maps API key, set your restaurant address, delivery radius, and pricing. Takes 5 minutes.

### Step 2: Customers Order
Shoppers see an interactive map on checkout, pin their exact location, fill in structured delivery details, and see real-time fees and ETA.

### Step 3: You Manage
Assign delivery agents from your Deliveries dashboard. Print receipts. Toggle delivery open/closed from the admin bar. Customers get automatic email updates.

### Step 4: Agents Deliver
Riders open their mobile dashboard, see assigned orders, tap for GPS navigation, and mark orders as picked up and delivered — all from their phone.

---

## 6. Live Demo Section

### Headline
**See It In Action**

### Description
Explore the full checkout experience, delivery dashboard, and order tracking on our live demo store.

### CTA Buttons
- **Try the Checkout** (link to demo checkout page)
- **View Agent Dashboard** (link to demo delivery dashboard)
- **Track a Sample Order** (link to demo tracking page)

### Demo Credentials (if applicable)
- Demo Store Manager: `demo@example.com` / `demo123`
- Demo Delivery Agent: `rider@example.com` / `rider123`

---

## 7. Feature Deep Dives

> **Layout:** Alternating left-right sections with screenshot on one side, text on the other. Each section focuses on one major feature.

### 7.1 Google Maps Checkout Experience

**Headline:** Pin-Drop Accuracy, Not "Somewhere Near That Street"

**Body:**
Generic address fields fail for food delivery. "123 Main Street" doesn't tell a rider which building, which floor, or which gate to use. FoodXpress replaces the default checkout with an interactive Google Maps widget that gives customers three ways to set their location:

- **Type-ahead search** — Start typing an address and Google Places suggests the rest
- **Use My Location** — One tap uses the phone's GPS for instant, meter-level accuracy
- **Drag the pin** — Fine-tune the marker to the exact building entrance

Once the location is set, the plugin auto-fills WooCommerce's shipping fields and calculates the delivery fee in real-time using actual driving distance from the Google Distance Matrix API.

**Tech Highlights:**
- Uses Google Maps `AdvancedMarkerElement` (modern API, not deprecated `google.maps.Marker`)
- `PlaceAutocompleteElement` web component with automatic session token management (saves you money on API billing)
- Real-time zone validation via REST API — no page reload needed
- Skeleton loading animation while calculating
- Saved address profiles for logged-in customers

**Screenshot:** The checkout page showing map with draggable pin, autocomplete search bar, "Use My Location" button, and structured address fields below.

---

### 7.2 Dynamic Distance-Based Pricing

**Headline:** Charge Fair Fees Based on Actual Driving Distance

**Body:**
Flat-rate delivery fees punish nearby customers and subsidize distant ones. FoodXpress calculates fees using the real driving route, not a straight line on a map.

**Your formula:**
```
Delivery Fee = Base Fee + (Driving Distance in KM x Per-KM Rate)
```

You set the base fee and per-km rate. The plugin does the rest using Google's Distance Matrix API — the same technology Uber and Grab use for ride pricing.

**Example:**
- Base fee: $3.00
- Per-km rate: $0.80
- Customer is 4.2 km away by road
- **Delivery fee: $3.00 + (4.2 x $0.80) = $6.36**

The fee appears as a WooCommerce shipping method, fully integrated with shipping zones, tax calculations, and cart totals.

**Screenshot:** The checkout totals showing "FoodXpress Delivery (ETA ~ 25 mins): $6.36"

---

### 7.3 Delivery Agent Mobile Dashboard

**Headline:** A Dashboard Your Riders Will Actually Use

**Body:**
Delivery agents don't sit at desks. They're on motorcycles, in the rain, with one hand free. FoodXpress gives them a mobile-first dashboard at `/delivery-dashboard/` that loads fast, works offline-capable, and won't drain their battery.

**What riders see:**
- **New Orders tab** — Assigned orders waiting for pickup, with customer name, phone (tap-to-call), address, distance, and COD amount
- **In Progress tab** — Picked-up orders being delivered
- **One-tap Google Maps** — GPS coordinates open directly in Google Maps for turn-by-turn navigation
- **Mark Picked Up / Mark Delivered** — Single button taps with confirmation, no page reloads

**Built for mobile:**
- PWA-capable (`mobile-web-app-capable`, `theme-color`)
- Safe area support for notched phones (iPhone 14+, Pixel 8)
- CSS containment and `content-visibility: auto` for battery efficiency
- 44px minimum touch targets — usable with gloves
- Reduced motion support for accessibility

**Screenshot:** iPhone mockup showing the delivery dashboard with two order cards, "New (2)" and "In Progress (1)" tabs.

---

### 7.4 Customer Order Tracking

**Headline:** Your Customers Know Exactly Where Their Food Is

**Body:**
After placing an order, customers see a visual 4-step tracker on their order page:

1. **In the Kitchen** — Order is being prepared
2. **Assigned** — A delivery agent has been assigned
3. **Picked Up** — Rider has the food and is on the way
4. **Delivered** — Order complete

Each step lights up with animated indicators as the order progresses. Once a rider is assigned, a contact card appears with the rider's name and a prominent **Call** button.

The tracker appears automatically on the WooCommerce My Account order view — no shortcode needed. For public tracking (order confirmation emails, etc.), use the `[fxw_track_order]` shortcode.

**Screenshot:** Order tracking stepper showing 3 of 4 steps completed, with delivery rider contact card below.

---

### 7.5 Branded Receipt Printing

**Headline:** Professional Receipts, Not Handwritten Notes

**Body:**
Print restaurant-style receipts from any screen in the plugin — the admin dashboard, the order edit page, or the delivery agent dashboard. Receipts include:

- Your restaurant logo and tagline
- Restaurant name, address, and phone
- Order number, date, and time
- Customer name, phone, and full delivery address
- Itemized product list with quantities, prices, and variation details
- Subtotal, delivery fee, discounts, taxes, and grand total
- **COD collection amount** prominently displayed for cash orders
- Special delivery instructions
- Custom footer message ("Thank You! Have a great day!")

Receipts are optimized for 80mm thermal printers but work with any printer. Auto-prints when the receipt window opens.

**Screenshot:** Receipt template showing logo, order details, itemized bill, and COD amount.

---

### 7.6 WooCommerce-Native Architecture

**Headline:** Works With WooCommerce, Not Against It

**Body:**
FoodXpress is not a bolt-on hack. It extends WooCommerce the right way:

- **Custom Shipping Method** — Integrates with WooCommerce Shipping Zones, tax, and cart totals
- **Custom Order Statuses** — Appear in the WooCommerce status dropdown, filter by them, bulk-update them
- **Custom Emails** — Managed in WooCommerce > Settings > Emails, with HTML and plain text templates
- **Order Meta** — All delivery data stored as proper WooCommerce order meta, visible in exports and reports
- **HPOS Compatible** — Works with WooCommerce's High-Performance Order Storage (the new default)
- **Custom User Role** — `delivery_boy` role with minimal capabilities, managed via WooCommerce's user system
- **Clean Uninstall** — Removes all options, roles, and user meta. Order data is preserved (your historical records are safe)

---

## 8. Comparison Table

> **Layout:** Feature comparison table — FoodXpress vs. building the same functionality with multiple plugins.

| Feature | FoodXpress (1 Plugin) | DIY Stack (4-6 Plugins) |
|---------|:---------------------:|:----------------------:|
| Google Maps on Checkout | Included | Plugin #1 ($49-99/yr) |
| Distance-Based Delivery Fees | Included | Plugin #2 ($39-79/yr) |
| Delivery Zone Validation | Included | Plugin #2 or Custom Code |
| Delivery Agent Dashboard | Included | Plugin #3 ($99-199/yr) or Custom App |
| Order Tracking Stepper | Included | Plugin #4 ($29-59/yr) |
| Custom Order Statuses | Included | Plugin #5 ($0-29/yr) |
| Automated Delivery Emails | Included | Manual or Plugin #6 ($29/yr) |
| Receipt Printing | Included | Plugin #7 ($39-79/yr) |
| One-Click Re-Order | Included | Plugin #8 ($0-29/yr) |
| My Account Styling | Included | Custom CSS or Theme Purchase |
| **Total Year 1 Cost** | **$99 (lifetime)** | **$284-594/year** |
| **Total Year 3 Cost** | **$99 (lifetime)** | **$852-1,782** |
| Plugin Conflicts | None (one codebase) | Likely (6+ codebases) |
| Support Points of Contact | 1 | 4-8 |
| Performance Impact | Minimal (one plugin) | Heavy (6+ plugins loading) |

---

## 9. Technical Specifications

### System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress | 6.0 | 6.5+ |
| WooCommerce | 7.0 | 9.0+ |
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| Google Maps API | Places + Geocoding + Distance Matrix + Maps JavaScript | Same |

### Google Maps API Keys Needed
You need ONE Google Cloud project with these APIs enabled:
1. **Maps JavaScript API** — For the interactive checkout map
2. **Places API (New)** — For the address autocomplete
3. **Geocoding API** — For address-to-coordinate conversion
4. **Distance Matrix API** — For driving distance and ETA calculations

> Google offers a $200/month free credit — enough for ~10,000-40,000 API calls, depending on usage.

### Performance
- **Page load impact:** ~50KB CSS + ~20KB JS (minified) on checkout only
- **Admin load impact:** Scripts load only on plugin pages
- **Database:** Uses existing WooCommerce tables (order meta, user meta, options). No custom tables created.
- **Caching friendly:** All transient-based caching, compatible with object cache (Redis, Memcached)
- **Rate limiting:** Built-in per-IP rate limiting on all public endpoints (configurable)

### Security
- Nonce verification on every AJAX handler and form
- Capability checks on all admin operations
- Input sanitization and output escaping on every data point
- Rate limiting on all public REST and AJAX endpoints
- Coordinate bounds validation (lat: -90 to 90, lng: -180 to 180)
- DOM injection prevention in JavaScript
- Google Maps API key masking in debug output
- Custom role with minimal capabilities

### Compatibility
- WooCommerce HPOS (High-Performance Order Storage): Full support
- WooCommerce Shipping Zones: Full integration
- Multisite: Compatible (per-site activation)
- Translation: Full i18n support with `foodxpress` text domain
- RTL: CSS supports RTL layouts
- Accessibility: WCAG 2.1 AA — ARIA labels, focus indicators, reduced motion support, 44px touch targets

---

## 10. Pricing

> **Layout:** Single-plan pricing card, centered, with feature list.

---

### FoodXpress for WooCommerce

## $99

**Lifetime Access — Per Website**

Everything you need to run food delivery on WooCommerce. One purchase, one site, forever.

### What's Included:

- All 27+ features listed above
- Google Maps checkout with pin-drop delivery
- Distance-based dynamic delivery fees
- Delivery zone validation
- 3 custom order statuses + 3 automated emails
- Mobile delivery agent dashboard
- Customer order tracking with rider contact
- Branded thermal receipt printing
- One-click re-order
- Admin open/closed toggle
- Deliveries management dashboard
- Styled My Account page
- REST API for custom integrations
- **1 year of updates and new features**
- **1 year of priority email support**
- **Lifetime usage rights** (use forever, even after support expires)

### After Year 1 (Optional Renewal):
- **$39/year** — Continued updates + priority support
- No renewal = no updates, but the plugin keeps working forever

### Multi-Site Packages:

| Sites | Price | Savings |
|-------|-------|---------|
| 1 Site | $99 | — |
| 3 Sites | $199 | Save $98 (33%) |
| 10 Sites | $399 | Save $591 (60%) |
| Unlimited Sites | $599 | Best value for agencies |

### 14-Day Money-Back Guarantee
Try FoodXpress risk-free. If it's not the right fit for your restaurant, get a full refund within 14 days — no questions asked.

### CTA Button
**Get FoodXpress for $99 — Lifetime Access**

---

## 11. FAQ

### General

**Q: What is FoodXpress for WooCommerce?**
A: FoodXpress is a premium WordPress plugin that turns any WooCommerce store into a complete food delivery platform. It adds Google Maps-powered delivery zones, dynamic distance-based pricing, a mobile delivery agent dashboard, customer order tracking, automated emails, receipt printing, and more — all in a single plugin.

**Q: Is this a SaaS or a plugin?**
A: It's a self-hosted WordPress plugin. You install it on your own server. No monthly fees, no per-order charges, no third-party platform to depend on. You own everything.

**Q: Do I need any other plugins?**
A: Just WooCommerce (free). FoodXpress handles everything else — maps, delivery zones, agent management, tracking, emails, and receipts. No additional plugins needed.

**Q: Does it work with my theme?**
A: Yes. FoodXpress works with any WooCommerce-compatible theme. The delivery agent dashboard is a standalone page that doesn't use your theme at all, so there are zero conflicts. The checkout and tracking components are styled to blend with any theme.

### Setup & Requirements

**Q: What Google Maps APIs do I need?**
A: You need one Google Cloud project with four APIs enabled: Maps JavaScript API, Places API (New), Geocoding API, and Distance Matrix API. We include a step-by-step setup guide. Google gives you $200/month in free credit — typically enough for small to medium restaurants.

**Q: How much does Google Maps cost?**
A: Google provides $200/month free credit. For a typical restaurant doing 50-200 orders/day, API costs are usually $0-15/month. Each checkout triggers about 2-4 API calls. High-volume stores (500+ orders/day) might see $30-50/month in API costs.

**Q: How long does setup take?**
A: Most restaurant owners complete setup in 10-15 minutes. Enter your Google Maps API key, set your restaurant address, configure your delivery radius and pricing, and you're live.

**Q: Does it work with WooCommerce HPOS?**
A: Yes, fully compatible. FoodXpress uses WooCommerce's modern CRUD methods and declares HPOS compatibility. It works with both legacy post-based storage and the new High-Performance Order Storage.

### Features

**Q: Can I set a maximum delivery distance?**
A: Yes. Set your delivery radius in kilometers. Orders outside that radius are automatically blocked at checkout with a friendly error message.

**Q: How is the delivery fee calculated?**
A: You set a base fee (e.g., $3.00) and a per-kilometer rate (e.g., $0.80). The plugin calculates the actual driving distance using Google's Distance Matrix API and applies the formula: Base + (Distance x Rate). Example: a 5km order costs $3.00 + (5 x $0.80) = $7.00.

**Q: Can my delivery agents use their phones?**
A: Absolutely. The delivery agent dashboard at `/delivery-dashboard/` is designed mobile-first — optimized for small screens, touch-friendly, battery-efficient, and works on any modern phone browser. No app download required.

**Q: Can customers track their order?**
A: Yes. A 4-step visual tracker (Kitchen > Assigned > Picked Up > Delivered) appears on every order page. Once a rider is assigned, customers also see the rider's name and a call button. A public tracking page is also available via the `[fxw_track_order]` shortcode.

**Q: Does it support Cash on Delivery?**
A: Yes. COD orders are highlighted throughout the system — on the admin dashboard, delivery agent dashboard, and printed receipts — with the exact collection amount displayed prominently.

**Q: Can I customize the receipt?**
A: Yes. Upload your logo, set your restaurant name, address, phone, tagline, and a custom footer message. All configured in the plugin settings — no code needed.

**Q: Does it send emails when order status changes?**
A: Yes. Three automated customer emails are included: "In the Kitchen," "Driver Assigned," and "Out for Delivery." Each is fully customizable via WooCommerce > Settings > Emails.

**Q: Can I open and close my store for deliveries?**
A: Yes. A toggle in the WordPress admin bar lets you open or close delivery acceptance with one click. When closed, the checkout blocks new delivery orders and customers see a "not accepting orders" message.

### Pricing & Licensing

**Q: Is this a one-time payment?**
A: Yes. $99 gets you lifetime usage rights for one website. The plugin is yours forever. After the first year, you can optionally renew at $39/year for continued updates and support.

**Q: What happens after my support expires?**
A: The plugin continues working indefinitely. You just won't receive new feature updates or priority support until you renew. All existing features remain fully functional.

**Q: Can I use it on multiple websites?**
A: Each license is per-website. We offer multi-site packages: 3 sites for $199, 10 sites for $399, or unlimited sites for $599.

**Q: Is there a refund policy?**
A: Yes — 14-day money-back guarantee, no questions asked.

---

## 12. Testimonials / Social Proof

> **Instructions for the landing page builder:** Add 3-6 testimonials from early customers. If none exist yet, use this section structure as a template and fill in real testimonials later.

### Testimonial Template

```
"[Quote about how the plugin solved a specific problem]"

— [Name], [Restaurant Name], [City/Country]
[Optional: small headshot photo]
```

### Suggested Testimonial Angles to Collect:
1. **Setup speed** — "We were live in 15 minutes"
2. **Replacing multiple plugins** — "We replaced 4 plugins with FoodXpress"
3. **Delivery agent experience** — "Our riders love the mobile dashboard"
4. **Customer satisfaction** — "Customers appreciate the live tracking"
5. **Cost savings** — "Saved us $400/year in plugin subscriptions"
6. **Reliability** — "Zero issues in 6 months of daily use"

### Trust Indicators to Display:
- Number of active installations (update as you grow)
- Star rating (from WordPress.org if listed, or from your own review system)
- "Trusted by X+ restaurants" counter
- Compatible with popular themes: Astra, Flavflavor flavour flavor flavor, flavor flavor, flavor flavor flavor flavor, flavor flavorOceanWP, Flavor flavor flavor flavor GeneratePress, Flavor flavor flavor flavor Flavor Flavor FlavorKadence, flavor Flavor Flavor Flavor FlavorStorefront, flavor flavor Flavor flavorFlatsome

> **Note:** Replace placeholder testimonials with real customer quotes before launch.

---

## 13. Final CTA

### Headline
**Ready to Launch Your Food Delivery Business?**

### Subheadline
Join restaurants worldwide who chose FoodXpress over expensive monthly SaaS platforms and fragile multi-plugin stacks.

### CTA Button
**Get FoodXpress for $99 — Lifetime Access**

### Below CTA
- 14-day money-back guarantee
- One-time payment — no recurring fees
- 1 year of updates and support included
- Works with any WooCommerce theme

---

## 14. Footer Notes

### Legal
- FoodXpress for WooCommerce is a product of [Your Company Name].
- WooCommerce is a registered trademark of Automattic, Inc.
- Google Maps is a trademark of Google LLC.
- WordPress is a registered trademark of the WordPress Foundation.

### Support Channels
- Email: support@[yourdomain].com
- Documentation: docs.[yourdomain].com
- Response time: Within 24 hours (business days)

### Changelog Link
Link to a changelog page showing version history and recent updates.

### Refund Policy Link
Full refund within 14 days of purchase. See refund policy for details.

---

## Appendix A: Complete Feature List (For Feature Page or Documentation)

### Checkout & Delivery Zones (10 Features)
1. Interactive Google Maps on WooCommerce checkout
2. Google Places autocomplete search (PlaceAutocompleteElement with session tokens)
3. HTML5 Geolocation ("Use My Location" button)
4. Draggable map pin for manual location adjustment
5. Real-time delivery zone validation via REST API
6. Dynamic delivery fee calculation (base + per-km rate using driving distance)
7. Estimated delivery time (driving duration + preparation time)
8. 6-field structured delivery address (house, floor, building, block, landmark, instructions)
9. Saved address profiles for logged-in customers
10. Auto-fill WooCommerce shipping fields from map selection

### Order Management (8 Features)
11. Dedicated "Deliveries" admin dashboard with 3-section layout
12. One-click delivery agent assignment with dropdown
13. AJAX-powered order status updates (no page reloads)
14. 3 custom order statuses: In Kitchen, Assigned, Picked Up
15. FoodXpress Delivery meta box on order edit screen
16. Open exact delivery location in Google Maps from admin
17. Auto-assign status when delivery agent is selected on order edit
18. Daily delivery report (completed deliveries + fee revenue)

### Delivery Agent System (7 Features)
19. Custom `delivery_boy` user role with restricted capabilities
20. Mobile-first standalone delivery dashboard (`/delivery-dashboard/`)
21. PWA-capable agent interface (theme-color, mobile-web-app-capable)
22. One-tap Google Maps navigation from order cards
23. AJAX pick-up and delivery confirmation
24. Click-to-call customer phone
25. Auto-redirect to dashboard on agent login

### Customer Experience (6 Features)
26. 4-step visual order tracking stepper (animated)
27. Delivery rider name and click-to-call contact card
28. Public order tracking page via `[fxw_track_order]` shortcode
29. One-click re-order from completed orders
30. Styled My Account page (food delivery app aesthetic)
31. 3 automated email notifications (In Kitchen, Assigned, Picked Up)

### Receipts & Communication (4 Features)
32. Branded thermal receipt printing with custom logo
33. Itemized receipt with product details, variations, and totals
34. COD collection amount prominently displayed
35. Custom receipt footer message

### Technical & Infrastructure (8 Features)
36. WooCommerce HPOS fully compatible
37. Custom WooCommerce shipping method (integrates with Shipping Zones)
38. REST API endpoints for headless/custom integrations
39. Per-IP rate limiting on all public endpoints
40. WooCommerce logger integration for debugging
41. Fallback geocoding when session data is lost
42. Clean uninstall (removes all data, preserves order history)
43. Full i18n / translation support

### Performance & Accessibility (6 Features)
44. CSS `content-visibility: auto` and `contain` for battery efficiency
45. `prefers-reduced-motion` media query support
46. WCAG 2.1 AA compliant (ARIA, focus indicators, color contrast)
47. 44px+ minimum touch targets
48. Skeleton loading animations
49. Scripts loaded only where needed (checkout, dashboard, admin)

---

## Appendix B: Pricing Rationale & Positioning

### Why Lifetime + Per-Website

**Rationale:**
- Restaurants are cost-conscious. Monthly SaaS fees feel like a tax.
- "Lifetime" is the #1 driver for WordPress plugin purchases (CodeCanyon data).
- Per-website licensing prevents unlimited redistribution while keeping the price accessible.
- Renewal for updates creates sustainable recurring revenue without making the customer feel trapped.

### Price Justification ($99)
- The plugin replaces $284-594/year in plugin subscriptions
- ROI is achieved in the first 2-4 months of use
- Comparable plugins on CodeCanyon: $39-89 (with less functionality)
- Comparable SaaS platforms: $49-199/month (Otter, Flavor, etc.)
- FoodXpress: $99 one-time = less than 2 months of the cheapest SaaS alternative

### Renewal Pricing ($39/year)
- ~40% of initial purchase price — industry standard
- Covers ongoing development, WooCommerce compatibility updates, and support costs
- Low enough that most customers will renew to stay current

### Multi-Site Discounts
- 3 sites: $66/site (33% off) — small chain / 2-3 location restaurant
- 10 sites: $40/site (60% off) — agency managing multiple restaurants
- Unlimited: $599 flat — web agencies, franchises

---

## Appendix C: SEO Metadata for Landing Page

### Page Title
FoodXpress for WooCommerce — Food Delivery Plugin with Google Maps, Order Tracking & Agent Dashboard

### Meta Description
Turn your WooCommerce store into a food delivery platform. Google Maps checkout, distance-based fees, delivery agent dashboard, order tracking, and receipt printing. $99 lifetime. No monthly fees.

### Target Keywords
- WooCommerce food delivery plugin
- WordPress restaurant delivery
- WooCommerce delivery zone plugin
- Google Maps WooCommerce checkout
- WooCommerce delivery management
- WooCommerce order tracking plugin
- Food delivery plugin WordPress
- WooCommerce delivery boy plugin
- Restaurant delivery WordPress plugin
- WooCommerce distance based delivery fee

### Open Graph Tags
```html
<meta property="og:title" content="FoodXpress for WooCommerce — Complete Food Delivery Plugin" />
<meta property="og:description" content="Google Maps checkout, delivery zones, agent dashboard, order tracking, receipt printing. $99 lifetime." />
<meta property="og:type" content="product" />
<meta property="og:image" content="[URL to hero image showing checkout + dashboard + tracking]" />
```

### Structured Data (Product Schema)
```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "FoodXpress for WooCommerce",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "WordPress",
  "offers": {
    "@type": "Offer",
    "price": "99.00",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock"
  },
  "softwareRequirements": "WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+",
  "description": "Complete food delivery management plugin for WooCommerce with Google Maps, delivery zones, agent dashboard, order tracking, and receipt printing."
}
```
