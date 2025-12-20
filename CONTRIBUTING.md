# Contributing to FoodXpress for WooCommerce

Thank you for your interest in contributing! This document provides guidelines for contributing to this project.

## Code of Conduct

Be respectful and professional in all interactions.

## Development Setup

### Prerequisites
- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Local development environment (Local, MAMP, Docker, etc.)

### Getting Started

1. Clone the repository:
   ```bash
   git clone https://github.com/codermillat/FoodXpress-for-WooCommerce.git
   ```

2. Copy to your WordPress plugins directory:
   ```bash
   cp -r FoodXpress-for-WooCommerce /path/to/wordpress/wp-content/plugins/
   ```

3. Activate the plugin in WordPress admin

## Coding Standards

### PHP
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use tabs for indentation
- Include ABSPATH check in all PHP files:
  ```php
  if (!defined('ABSPATH')) {
      exit;
  }
  ```

### JavaScript
- Use strict mode: `'use strict';`
- Wrap in IIFE with jQuery: `(function($) { ... })(jQuery);`

### Security
- Always verify nonces for form submissions and AJAX
- Check user capabilities before actions
- Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- Escape all output: `esc_html()`, `esc_attr()`, `wp_kses_post()`

## Testing

Run the test suite before submitting changes:

```bash
cd wp-content/plugins/foodxpress-for-woocommerce
php tests/FXWTestRunner.php
```

All 86 tests must pass.

## Pull Request Process

1. Create a feature branch from `main`
2. Make your changes with clear commit messages
3. Ensure all tests pass
4. Update CHANGELOG.md if applicable
5. Submit a pull request with a clear description

## Questions?

Open an issue on GitHub for any questions or discussions.
