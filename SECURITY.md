# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.1.x   | :white_check_mark: |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** open a public GitHub issue
2. Email: [Contact the author directly via GitHub]
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact

You can expect an initial response within 48 hours.

## Security Measures

This plugin implements the following security practices:

### Input Validation
- All user inputs are sanitized using WordPress functions
- `sanitize_text_field()`, `absint()`, `sanitize_email()`

### Output Escaping
- All output is escaped to prevent XSS
- `esc_html()`, `esc_attr()`, `wp_kses_post()`

### CSRF Protection
- All forms use nonce verification
- All AJAX endpoints verify nonces

### Authorization
- Capability checks before sensitive operations
- `current_user_can('edit_shop_orders')`

### Database Security
- Uses WooCommerce CRUD methods
- No direct SQL queries with user input

### Rate Limiting
- API calls are rate-limited to prevent abuse
- IP addresses are hashed for privacy
