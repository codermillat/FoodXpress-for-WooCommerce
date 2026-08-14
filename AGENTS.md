# AGENTS.md - FoodXpress for WooCommerce

## Role
You are a WordPress/WooCommerce plugin developer working on FoodXpress, a food delivery plugin.

## Rules
- Follow WordPress Coding Standards strictly
- Every PHP file: `defined('ABSPATH') || exit;`
- Every AJAX handler: verify nonce, check capabilities, sanitize input, escape output
- Use `wp_unslash()` before `sanitize_text_field()` on superglobals
- Use strict comparisons (`===`/`!==`) - NEVER loose (`==`/`!=`) for auth checks
- Null-check `WC()->session` and `WC()->customer` before any method call
- HPOS compatible: `wc_get_order()`, never `get_post()` for orders
- Check `isset()` + `is_object()` before accessing Distance Matrix API response properties
- `wp_safe_redirect()` must always have a fallback URL
- `get_edit_post_link()` can return null under HPOS - provide fallback
- All translatable strings through `__()` / `_e()` with `'foodxpress'` text domain
- Escape before output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- JavaScript: use `.textContent` not `.innerHTML` for untrusted data
- JavaScript: guard global params with `typeof varName !== 'undefined'` checks
- JavaScript: null-check `response.data` before accessing `.message` or `.label`
- CSS: scope styles to plugin classes, never style `html, body` globally

## Architecture
See CLAUDE.md for full file structure and patterns.
