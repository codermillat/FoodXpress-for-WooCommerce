## Brief overview
This document outlines the guidelines and best practices for WordPress and WooCommerce development to ensure concise, technical, and modular code.

## Key Principles
- Write concise, technical code with accurate PHP examples.
- Follow WordPress and WooCommerce coding standards and best practices.
- Use object-oriented programming when appropriate, focusing on modularity.
- Prefer iteration and modularization over duplication.
- Use descriptive function, variable, and file names.
- Use lowercase with hyphens for directories (e.g., `wp-content/themes/my-theme`, `wp-content/plugins/my-plugin`).
- Favor hooks (actions and filters) for extending functionality.

## PHP/WordPress/WooCommerce Standards
- Use PHP 7.4+ features where appropriate (e.g., typed properties, arrow functions).
- Follow WordPress PHP Coding Standards.
- Use strict typing when possible: `declare(strict_types=1);`
- Utilize WordPress core functions and APIs when available.
- Follow WordPress theme and plugin directory structures and naming conventions.
- Implement proper error handling and logging using WordPress debug logging features, custom error handlers, and try-catch blocks.
- Use WordPress's built-in functions for data validation and sanitization.
- Implement proper nonce verification for form submissions.
- Utilize WordPress's database abstraction layer (`wpdb`) for database interactions, using `prepare()` statements for security.
- Implement database schema changes using the `dbDelta()` function.

## Dependencies
- WordPress (latest stable version)
- WooCommerce (latest stable version)
- Composer for dependency management in advanced plugins or themes.

## WordPress and WooCommerce Best Practices
- Use WordPress hooks (actions and filters) instead of modifying core files.
- Implement theme functions in `functions.php`.
- Use WordPress's built-in user roles and capabilities system.
- Utilize WordPress's transients API for caching.
- Use `wp_cron()` for background processing.
- Use `WP_UnitTestCase` for unit tests.
- Implement internationalization and localization using WordPress i18n functions.
- Implement security measures like nonces, data escaping, and input sanitization.
- Use `wp_enqueue_script()` and `wp_enqueue_style()` for asset management.
- Implement custom post types and taxonomies when appropriate.
- Use the WordPress options API for storing configuration data.
- Use `paginate_links()` for pagination.
- Leverage WooCommerce action and filter hooks for extensibility (e.g., `add_action('woocommerce_before_add_to_cart_form', 'your_function');`).
- Adhere to WooCommerce's coding standards and naming conventions.
- Use built-in WooCommerce functions like `wc_get_product()`.
- Use the WooCommerce Settings API for plugin configuration pages.
- Override WooCommerce templates by placing them in `your-plugin/woocommerce/`.
- Use WooCommerce's CRUD classes and data stores for managing custom data.
- Use WooCommerce session handling for temporary data (e.g., `WC()->session->set('your_key', 'your_value');`).
- Follow WooCommerce's API structure when extending the REST API.
- Use WooCommerce's notice system for user-facing messages (e.g., `wc_add_notice('Your message', 'error');`).
- Extend WooCommerce's email system using the `WC_Email` class.
- Check for WooCommerce activation and version compatibility.
- Use WooCommerce's translation functions and support RTL languages.
- Utilize WooCommerce's logging system for debugging (e.g., `wc_get_logger()->debug('Your debug message', array('source' => 'your-plugin'));`).

## Key Conventions
1. Follow WordPress's plugin API for extending functionality.
2. Use WordPress's template hierarchy for theme development.
3. Implement proper data sanitization and validation using WordPress functions.
4. Use WordPress's template tags and conditional tags in themes.
5. Implement proper database queries using `$wpdb` or `WP_Query`.
6. Use WordPress's authentication and authorization functions.
7. Implement proper AJAX handling using `admin-ajax.php` or the REST API.
8. Use WordPress's hook system for modular and extensible code.
9. Implement proper database operations using WordPress transactional functions.
10. Use WordPress's `WP_Cron` API for scheduling tasks.
