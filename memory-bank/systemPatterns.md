# System Patterns: FoodXpress for WooCommerce

This file documents the system architecture, key technical decisions, and design patterns for the project.

---

## 1. System Architecture

FoodXpress is a standard WordPress plugin that extends WooCommerce. Its architecture is designed to be lightweight and modular, hooking into WordPress and WooCommerce at specific points to add its functionality without altering core files.

- **Plugin Core:** A main plugin file (`foodxpress-for-woocommerce.php`) acts as the entry point. It handles initialization, loads dependencies, and instantiates a main controller class.
- **Class-Based Modularity:** Functionality is broken down into distinct PHP classes, each handling a specific domain (e.g., `FXW_Roles`, `FXW_Order_Admin`, `FXW_Shortcodes`). This follows an object-oriented approach that promotes separation of concerns.
- **Mobile-First Responsive Design:** All custom admin interfaces (dashboard, meta boxes) and front-end components (tracking page) will be built with responsive CSS to ensure they are usable and look professional on all screen sizes, from mobile phones to desktops.
- **Hook-Based Integration:** The plugin relies entirely on the WordPress/WooCommerce hook system (actions and filters) to integrate its features. This is the standard, recommended pattern for WordPress development, ensuring compatibility and preventing conflicts.
- **Data Layer:** The plugin will not create custom database tables for its MVP. All data will be stored in the `wp_postmeta` table (for order-specific data like assigned driver and customer coordinates) and the `wp_options` table (for global settings like API keys and fee rules).
- **External Service Integration:** A dedicated class will act as a service container to handle all communication with the external mapping API (e.g., Google Maps). This centralizes API calls, making them easier to manage and debug.

## 2. Key Design Patterns

- **Singleton Pattern (for the main class):** The main plugin class will be instantiated only once to prevent duplicate hooks and ensure a single point of control for the plugin's lifecycle.
- **Dependency Injection (simple form):** While not using a full container, the main class will instantiate other controller classes, effectively "injecting" them into the application's runtime.
- **Observer Pattern (via Hooks):** The WordPress hook system is a classic implementation of the Observer pattern. Our plugin "observes" core events (like `save_post`) and attaches its own functionality to them.

## 3. Key Dependencies & APIs

This section provides direct links and examples for our critical external dependencies.

*   **Google Maps Platform:**
    *   **Purpose:** Used for all geolocation, geocoding, distance/time matrix calculations, and interactive map displays.
    *   **Official Documentation:** [Google Maps Platform Web Services](https://developers.google.com/maps/documentation/webservices)
    *   **JS API Documentation:** [Google Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/overview)
    *   **PHP Authentication Snippet (Example):**
        ```php
        $api_key = get_option('fxw_google_maps_api_key');
        $base_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $request_url = add_query_arg([
            'origins'      => 'Restaurant_Lat,Restaurant_Lng',
            'destinations' => 'Customer_Lat,Customer_Lng',
            'key'          => $api_key,
        ], $base_url);

        $response = wp_remote_get($request_url);
        // Process response...
        ```

## 4. Component Relationships

```
[Main Plugin File]
      |
      |-- Instantiates --> [FXW_Core] (Main Controller)
                              |
                              |-- Loads & Initializes --> [FXW_Roles]
                              |-- Loads & Initializes --> [FXW_Order_Admin]
                              |-- Loads & Initializes --> [FXW_Dashboard]
                              |-- Loads & Initializes --> [FXW_Shortcodes]
                              |-- Loads & Initializes --> [FXW_Notifications]
                              |-- Loads & Initializes --> [FXW_Rest_Api]
```

- `FXW_Order_Admin` interacts with the WooCommerce Order CPT (Custom Post Type).
- `FXW_Dashboard` reads data from multiple Order CPTs.
- `FXW_Shortcodes` provides the UI for the end-user (customer).
- `FXW_Settings` will manage the admin settings page for API keys, fee rules, prep times, and delivery zone definitions.
- `FXW_Checkout` will handle the frontend logic on the checkout page, including the live location capture, delivery notes, and dynamic fee/ETA updates.
- `FXW_Mapping_Service` will be the dedicated handler for all external API calls.
- `FXW_Delivery_Boy_View` will render the simple, mobile-optimized page for delivery boys.
- `FXW_Reporting` will handle the logic for generating the end-of-day summary.
- `FXW_Shipping_Method` will define the custom shipping method and its calculation logic.

## 5. Critical Implementation Paths

- **Shipping Method Registration:** `add_filter('woocommerce_shipping_methods')` -> Register the `FXW_Shipping_Method` class. The class itself will be defined directly, not inside a hook, to ensure it's available when WooCommerce builds its method list.
- **Delivery Zone Validation:** `FXW_Shipping_Method::calculate_shipping()` -> Get customer address -> Use Mapping Service to geocode address -> Check if coordinates fall within the admin-defined delivery zone polygon/radius. If not, do not return a shipping rate.
- **Open/Closed Toggle:** `add_action('admin_bar_menu')` -> Add toggle link -> Link triggers a custom admin POST handler -> Handler updates a `wp_option` value -> `FXW_Shipping_Method::calculate_shipping()` -> Check this option value; if closed, do not return a shipping rate.
- **Address Input & Geocoding:** `woocommerce_checkout_fields` filter -> Add "Get My Location" button, interactive map div, and notes field -> Enqueue custom JS for the mapping API and our custom script -> JS calls a new `fxw_get_restaurant_location` AJAX endpoint to get coordinates for map center -> JS initializes the map with a draggable pin and a delivery zone circle -> On pin drop, use reverse geocoding to get address and fill fields, then trigger `update_checkout` -> On address field keyup (debounced), geocode the address and move the pin -> On "Get My Location" click, use Browser Geolocation API to get coordinates, then reverse geocode and fill fields, then trigger `update_checkout`.
- **Distance, Fee & ETA Calculation:** `FXW_Shipping_Method::calculate_shipping()` -> Get customer address from package -> Use Mapping Service to get distance & travel time -> Calculate fee based on saved rules -> Use `add_rate()` to add the final fee to the cart. The ETA is displayed separately on the checkout page.
- **Dashboard & Tracking Fixes:** The `wc_get_orders` query for the dashboard will be updated to correctly filter by status. The shortcode logic will be fixed to correctly calculate the `is_completed` status for the visual tracker.
- **Delivery Boy Redirect:** `add_filter('woocommerce_login_redirect')` -> Check for 'delivery_boy' role -> If match, redirect to the `/delivery-dashboard/` page.
- **Rewrite Rule Flushing:** `register_activation_hook` -> Call `flush_rewrite_rules()` to ensure the `/delivery-dashboard/` endpoint is recognized.
- **Order Assignment & Map Link:** `add_meta_boxes` hook -> Render HTML for meta box, including a Google Maps link, payment method display, and a "Print Receipt" button -> `save_post_shop_order` hook -> Sanitize and save data.
- **Receipt Printing:** The "Print Receipt" button will have a `data-order-id` attribute. An enqueued JS file will listen for clicks on this button -> On click, it opens a new window with a custom URL (e.g., `?fxw_print_receipt=ORDER_ID`) -> A hook on `template_redirect` will check for this query parameter -> If present, it will load a clean HTML receipt template, populate it with order data (including payment method and a COD highlight), and execute `window.print()`.
- **Status Tracking & Rejection:** `init` hook -> `register_post_status()` for delivery statuses and a "Rejected" status -> `wc_order_statuses` filter -> Display on order screen. Add "Reject" button to meta box that changes status.
- **Delivery Boy View:** `template_redirect` hook -> Check if the current user has the 'delivery_boy' role and is on the dedicated page slug -> If so, load a custom template that queries for their assigned orders and displays them in a simple list.
- **Customer View:** `add_shortcode()` -> Shortcode handler function -> Get order data via `wc_get_order()` -> Check `_fxw_delivery_status` meta field -> Render status display.
- **Re-order:** `woocommerce_my_account_my_orders_actions` filter -> Add a "Re-order" button for completed orders -> Button links to a custom URL with the order ID -> A function hooked to `template_redirect` will get the old order, add items to the current user's cart, and redirect to the checkout page.
