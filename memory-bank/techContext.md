# Tech Context: FoodXpress for WooCommerce

This file documents the technologies, dependencies, development setup, and technical constraints for the project.

---

## 1. Core Technologies

- **WordPress:** (Latest stable version) The underlying CMS.
- **WooCommerce:** (Latest stable version) The e-commerce platform we are extending.
- **PHP:** (Version 7.4+) We will use modern PHP features where appropriate, while maintaining broad server compatibility.
- **HTML5 & CSS3:** For structuring and styling all front-end and admin components. All custom CSS will be written with a mobile-first approach to ensure responsiveness.
- **JavaScript (Vanilla & jQuery):** We will use vanilla JavaScript for the checkout page geolocation feature to keep it lightweight. For other admin-side interactions, we can leverage the version of jQuery bundled with WordPress.
- **Browser Geolocation API:** A standard web API supported by all modern browsers for capturing user location (with their consent).
- **Mapping Service JavaScript API:** The chosen mapping service's own JavaScript library will be required to display the interactive map with the draggable pin on the checkout page.

## 2. Dependencies

- **Primary Dependencies:** WordPress, WooCommerce.
- **External Service Dependencies:** A mapping service provider (e.g., Google Maps Platform, Mapbox) is required for distance calculation and geocoding. The specific provider will be determined by the user, who must provide a valid API key.
- **Development Dependencies:** None. We are intentionally keeping the plugin self-contained without requiring Composer or other package managers to maintain simplicity.

## 3. Development Setup & Constraints

- **Environment:** A standard WordPress development environment (e.g., Local, XAMPP, MAMP) with `WP_DEBUG` enabled is required.
- **No Build Process:** The plugin will not require a build or compilation step (e.g., no Sass, no modern JavaScript bundling). All assets (CSS, JS) will be written directly. This simplifies the development and deployment process.
- **API Key Management:** The external mapping service API key is a critical secret and must be handled securely. It will be stored in the `wp_options` table and only displayed in the admin settings page. It should never be exposed on the front end.
- **Proprietary License:** The code is for private use and should not contain any update-checkers or be prepared for public distribution on the WordPress.org repository.

## 4. Tool Usage Patterns

- **File Naming:**
    - Plugin root directory: `foodxpress-for-woocommerce`
    - Main plugin file: `foodxpress-for-woocommerce.php`
    - Classes: `class-fxw-{feature-name}.php` (e.g., `class-fxw-roles.php`).
    - CSS/JS: `admin.css`, `frontend.js`, etc.
- **Function Naming:** All global functions will be prefixed with `fxw_` to prevent naming collisions.
- **Hooks:** Hooks will be registered in the main plugin class or within the constructor of the relevant feature class.
