# TestSprite MCP Testing Guide

This directory contains configuration and documentation for automated testing using TestSprite MCP in Cursor.

## Quick Start

1. **Set up TestSprite MCP** (see `TESTSPRITE_SETUP.md` in project root)
2. **Configure test environment** in `testsprite-config.json`
3. **Run tests via Cursor chat**:
   ```
   "Run TestSprite tests for FoodXpress plugin"
   ```

## Configuration Files

- `testsprite-config.json` - Test suite definitions and configuration
- `FXWTestRunner.php` - Static code analysis tests (complementary)

## Test Suites

The configuration includes 8 test suites covering:

1. **Plugin Activation** - Installation and initialization
2. **Order Management** - Status flow and assignment
3. **Delivery Dashboard** - Access control and functionality
4. **Checkout & Shipping** - Payment and delivery selection
5. **Shortcodes** - Frontend display components
6. **Settings & Configuration** - Admin panel functionality
7. **Email Notifications** - Customer communication
8. **Security** - Nonces, capabilities, and access control

## Running Tests

### Via Cursor Chat

Simply ask Cursor to run tests:
```
"Execute all TestSprite tests for FoodXpress"
"Run order management test suite"
"Test delivery dashboard functionality"
```

### Manual Execution

If you have TestSprite CLI installed:
```bash
testsprite run --config tests/testsprite-config.json
```

## Test Environment Setup

Before running tests, ensure:

1. **WordPress Test Site** is running
   - Local: `http://localhost/wordpress`
   - Docker: Configure in `testsprite-config.json`
   - Staging: Update `test_url` in config

2. **Test Data** is available
   - Test products created
   - Test users (admin, delivery boy, customer)
   - WooCommerce configured

3. **Plugin Activated**
   - FoodXpress plugin installed and active
   - WooCommerce active
   - Settings configured

## Customizing Tests

Edit `testsprite-config.json` to:
- Add new test cases
- Modify test steps
- Update environment settings
- Configure reporting options

## Integration with CI/CD

TestSprite MCP can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run TestSprite Tests
  run: |
    cursor --execute "Run TestSprite tests for FoodXpress"
```

## Troubleshooting

### Tests Fail to Connect
- Verify WordPress URL in config is correct
- Check WordPress is accessible
- Verify admin credentials

### Tests Timeout
- Increase timeout in `environment.timeout`
- Check WordPress site performance
- Verify database connection

### MCP Not Available
- Restart Cursor after MCP configuration
- Verify Node.js 22+ installed
- Check TestSprite API key is valid

## Best Practices

1. **Run static tests first**: `php tests/FXWTestRunner.php`
2. **Then run dynamic tests**: Via TestSprite MCP
3. **Fix issues incrementally**: Address one test suite at a time
4. **Keep config updated**: Sync with plugin changes
5. **Document custom tests**: Add comments in config file

## Support

- TestSprite Docs: https://docs.testsprite.com
- Plugin Issues: GitHub Issues
- MCP Help: Cursor Documentation

