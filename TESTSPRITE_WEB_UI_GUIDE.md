# Running Tests via TestSprite Web UI

Since the CLI encountered an issue, use the TestSprite web interface that's already open in your browser.

## Steps to Run Tests

### 1. Complete the Initialization Form

In the TestSprite web UI (localhost:52532):

**Testing Configuration:**
- ✅ Mode: **Backend** (already selected)
- ✅ Scope: **Codebase** (already selected)
- ✅ Authentication: **None** (already selected)

**Local Development Port:**
- ✅ Port: **8080** (already filled)
- ✅ Path: **/** (already filled)

**Product Specification Doc:**
- 📄 Upload: `PRODUCT_SPEC.md` from your project root
  - File location: `/Users/mdmillathosen/Desktop/FoodXpress for WooCommerce/PRODUCT_SPEC.md`

### 2. Click "Continue"

After uploading the product spec, click the green "Continue" button.

### 3. TestSprite Will:

1. Generate a standardized PRD from your product spec
2. Create a comprehensive test plan
3. Generate test code
4. Execute tests against your WordPress site at http://localhost:8080
5. Generate a test report

### 4. Review Test Results

TestSprite will show you:
- ✅ Passed tests
- ❌ Failed tests
- ⚠️ Warnings
- 📊 Test coverage

## What Will Be Tested

Based on your `PRODUCT_SPEC.md`, TestSprite will test:

1. **Plugin Activation**
   - Plugin activates with WooCommerce
   - Dependency checks
   - Custom order statuses
   - Delivery boy role creation

2. **Order Management**
   - Order status progression
   - Order assignment
   - Delivery workflow

3. **Delivery Dashboard**
   - Access control
   - Order visibility
   - Status updates

4. **Checkout & Shipping**
   - Shipping method availability
   - Delivery fee calculation
   - Address validation (if Google Maps configured)

5. **Shortcodes**
   - Order tracking
   - Reorder functionality

6. **Settings**
   - Settings page access
   - Configuration saving

7. **Email Notifications**
   - Email templates
   - Email sending

8. **Security**
   - Nonce verification
   - Capability checks
   - ABSPATH checks

## Notes

- **Google Maps API**: Tests that require Google Maps will be skipped if API key is not configured (this is expected)
- **WordPress Credentials**: Tests will use the credentials from `tests/testsprite-config.json` (username: millat, password: millat)
- **Test URL**: http://localhost:8080

## Troubleshooting

**If tests fail to connect:**
- Verify WordPress is running: `docker-compose ps`
- Check WordPress is accessible: Open http://localhost:8080 in browser
- Verify plugins are activated in WordPress admin

**If you see authentication errors:**
- Make sure you're logged into TestSprite web UI
- Check your TestSprite account has active subscription/credits

## Next Steps After Tests

Once tests complete:
1. Review the test report
2. Fix any failing tests
3. Re-run tests to verify fixes
4. Use test results to improve plugin quality

---

**Ready to proceed?** Upload `PRODUCT_SPEC.md` in the TestSprite web UI and click "Continue"!

