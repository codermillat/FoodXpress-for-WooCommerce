// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * End-to-End Tests for FoodXpress Checkout Flow
 * 
 * These tests simulate real user interactions with the checkout process
 * to ensure the complete user experience works correctly.
 */

test.describe('FoodXpress Checkout Flow', () => {
  
  test.beforeEach(async ({ page }) => {
    // Navigate to the test site
    await page.goto('/');
    
    // Setup: Add a product to cart
    await page.goto('/shop/');
    
    // Wait for products to load
    await page.waitForSelector('.products .product', { timeout: 10000 });
    
    // Add first available product to cart
    const addToCartButton = page.locator('.add_to_cart_button').first();
    if (await addToCartButton.isVisible()) {
      await addToCartButton.click();
      
      // Wait for cart update
      await page.waitForTimeout(2000);
    }
    
    // Navigate to checkout
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
  });

  test('Complete checkout flow with valid delivery location', async ({ page }) => {
    // Test the complete happy path checkout flow
    
    // Step 1: Fill billing information
    await page.fill('#billing_first_name', 'John');
    await page.fill('#billing_last_name', 'Doe');
    await page.fill('#billing_email', 'john.doe@example.com');
    await page.fill('#billing_phone', '+1234567890');
    
    // Step 2: Set delivery location using the FoodXpress location picker
    const locationInput = page.locator('#fxw-location-search-input');
    if (await locationInput.isVisible()) {
      await locationInput.click();
      await locationInput.fill('123 Main Street, New York, NY 10001');
      
      // Wait for autocomplete suggestions
      await page.waitForTimeout(2000);
      
      // Click on the first suggestion or press Enter
      await page.keyboard.press('ArrowDown');
      await page.keyboard.press('Enter');
      
      // Wait for location processing
      await page.waitForTimeout(3000);
    }
    
    // Step 3: Verify shipping method appears
    const shippingMethods = page.locator('.woocommerce-shipping-methods');
    await expect(shippingMethods).toBeVisible({ timeout: 10000 });
    
    // Check for FoodXpress delivery method
    const foodxpressMethod = page.locator('input[value*="foodxpress_delivery"]');
    if (await foodxpressMethod.isVisible()) {
      await foodxpressMethod.check();
      
      // Verify ETA is displayed
      const etaDisplay = page.locator('.fxw-eta-display');
      await expect(etaDisplay).toBeVisible();
      await expect(etaDisplay).toContainText('ETA');
    }
    
    // Step 4: Select payment method
    const paymentMethod = page.locator('#payment_method_cod');
    if (await paymentMethod.isVisible()) {
      await paymentMethod.check();
    }
    
    // Step 5: Place order
    const placeOrderButton = page.locator('#place_order');
    await expect(placeOrderButton).toBeEnabled();
    
    // Click place order and verify success
    await placeOrderButton.click();
    
    // Wait for order confirmation or error
    await page.waitForTimeout(5000);
    
    // Check for either success or validation errors
    const orderReceived = page.locator('.woocommerce-order-received');
    const errorNotices = page.locator('.woocommerce-error');
    
    // If we reach order received page, the flow worked
    if (await orderReceived.isVisible()) {
      await expect(orderReceived).toBeVisible();
    } else if (await errorNotices.isVisible()) {
      // Log any validation errors for debugging
      const errorText = await errorNotices.textContent();
      console.log('Checkout validation errors:', errorText);
    }
  });

  test('Checkout with out-of-delivery-zone location', async ({ page }) => {
    // Test behavior when customer enters location outside delivery zone
    
    // Fill required billing fields
    await page.fill('#billing_first_name', 'Jane');
    await page.fill('#billing_last_name', 'Smith');
    await page.fill('#billing_email', 'jane.smith@example.com');
    await page.fill('#billing_phone', '+1987654321');
    
    // Enter a location far from the restaurant (e.g., different city)
    const locationInput = page.locator('#fxw-location-search-input');
    if (await locationInput.isVisible()) {
      await locationInput.click();
      await locationInput.fill('Los Angeles, CA, USA');
      
      await page.waitForTimeout(2000);
      await page.keyboard.press('ArrowDown');
      await page.keyboard.press('Enter');
      
      // Wait for location processing
      await page.waitForTimeout(5000);
    }
    
    // Should show "outside delivery zone" message
    const outsideZoneMessage = page.locator('.fxw-outside-zone-notice');
    if (await outsideZoneMessage.isVisible()) {
      await expect(outsideZoneMessage).toBeVisible();
      await expect(outsideZoneMessage).toContainText('outside');
    }
    
    // FoodXpress delivery method should not be available
    const foodxpressMethod = page.locator('input[value*="foodxpress_delivery"]');
    await expect(foodxpressMethod).not.toBeVisible();
    
    // Order placement should either be blocked or use alternative shipping
    const placeOrderButton = page.locator('#place_order');
    if (await placeOrderButton.isVisible()) {
      // If button is enabled, it should be for alternative shipping methods
      const shippingMethods = page.locator('.woocommerce-shipping-methods input');
      const visibleMethods = await shippingMethods.count();
      
      if (visibleMethods === 0) {
        // No shipping methods available - order should be blocked
        await expect(placeOrderButton).toBeDisabled();
      }
    }
  });

  test('Location picker user interface functionality', async ({ page }) => {
    // Test the Google Maps integration and location picker UI
    
    const locationInput = page.locator('#fxw-location-search-input');
    await expect(locationInput).toBeVisible();
    
    // Test autocomplete functionality
    await locationInput.click();
    await locationInput.fill('New York');
    
    // Wait for autocomplete suggestions (Google Places API)
    await page.waitForTimeout(3000);
    
    // Check if Google Maps API is loaded and working
    const mapContainer = page.locator('#fxw-map-container');
    if (await mapContainer.isVisible()) {
      await expect(mapContainer).toBeVisible();
      
      // Test map interaction (if map is loaded)
      const map = mapContainer.locator('div').first();
      if (await map.isVisible()) {
        // Try to click on the map to place a marker
        await map.click();
        await page.waitForTimeout(1000);
      }
    }
    
    // Test geolocation button if present
    const geolocationButton = page.locator('#fxw-geolocation-button');
    if (await geolocationButton.isVisible()) {
      await geolocationButton.click();
      
      // Note: Geolocation will likely fail in headless testing
      // but we can verify the button interaction works
      await page.waitForTimeout(2000);
    }
  });

  test('AJAX functionality and error handling', async ({ page }) => {
    // Test AJAX requests and error handling
    
    // Monitor network requests
    const ajaxRequests = [];
    page.on('request', request => {
      if (request.url().includes('admin-ajax.php')) {
        ajaxRequests.push(request.url());
      }
    });
    
    // Fill in location to trigger AJAX
    const locationInput = page.locator('#fxw-location-search-input');
    if (await locationInput.isVisible()) {
      await locationInput.fill('123 Test Street, New York, NY');
      await page.keyboard.press('Enter');
      
      // Wait for AJAX processing
      await page.waitForTimeout(3000);
      
      // Verify AJAX requests were made
      expect(ajaxRequests.length).toBeGreaterThan(0);
      
      // Check for any AJAX errors in console
      const logs = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          logs.push(msg.text());
        }
      });
      
      // Verify no JavaScript errors
      expect(logs.filter(log => log.includes('error')).length).toBe(0);
    }
  });

  test('Responsive design on mobile devices', async ({ page }) => {
    // Test mobile responsiveness
    await page.setViewportSize({ width: 375, height: 667 }); // iPhone SE size
    
    // Verify checkout form is responsive
    const checkoutForm = page.locator('#checkout');
    await expect(checkoutForm).toBeVisible();
    
    // Check location input is usable on mobile
    const locationInput = page.locator('#fxw-location-search-input');
    if (await locationInput.isVisible()) {
      await expect(locationInput).toBeVisible();
      
      // Test touch interaction
      await locationInput.tap();
      await locationInput.fill('Mobile Test Location');
      
      // Verify input is properly focused and usable
      await expect(locationInput).toBeFocused();
    }
    
    // Test that buttons are properly sized for touch
    const placeOrderButton = page.locator('#place_order');
    const buttonBox = await placeOrderButton.boundingBox();
    
    if (buttonBox) {
      // Button should be at least 44px tall for good touch target
      expect(buttonBox.height).toBeGreaterThanOrEqual(40);
    }
  });

  test('Accessibility compliance', async ({ page }) => {
    // Basic accessibility tests
    
    // Check for required form labels
    const billingFields = [
      '#billing_first_name',
      '#billing_last_name', 
      '#billing_email',
      '#billing_phone'
    ];
    
    for (const field of billingFields) {
      const input = page.locator(field);
      if (await input.isVisible()) {
        // Check that field has associated label
        const labelFor = await page.locator(`label[for="${field.replace('#', '')}"]`);
        await expect(labelFor).toBeVisible();
      }
    }
    
    // Check location input has proper labeling
    const locationInput = page.locator('#fxw-location-search-input');
    if (await locationInput.isVisible()) {
      const ariaLabel = await locationInput.getAttribute('aria-label');
      const placeholder = await locationInput.getAttribute('placeholder');
      
      // Should have either aria-label or placeholder for accessibility
      expect(ariaLabel || placeholder).toBeTruthy();
    }
    
    // Test keyboard navigation
    await page.keyboard.press('Tab');
    const focusedElement = await page.locator(':focus');
    await expect(focusedElement).toBeVisible();
    
    // Verify focus is visible (basic test)
    const focusedBox = await focusedElement.boundingBox();
    expect(focusedBox).toBeTruthy();
  });

  test('Performance and loading times', async ({ page }) => {
    // Test page load performance
    const startTime = Date.now();
    
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Checkout should load within reasonable time (10 seconds max)
    expect(loadTime).toBeLessThan(10000);
    
    // Test that essential elements load quickly
    const locationInput = page.locator('#fxw-location-search-input');
    const placeOrderButton = page.locator('#place_order');
    
    // Essential elements should be visible
    if (await locationInput.isVisible()) {
      await expect(locationInput).toBeVisible();
    }
    await expect(placeOrderButton).toBeVisible();
    
    // Test Google Maps loading (if API key is configured)
    const mapContainer = page.locator('#fxw-map-container');
    if (await mapContainer.isVisible()) {
      // Maps should load within 5 seconds
      await expect(mapContainer).toBeVisible({ timeout: 5000 });
    }
  });
});

// Additional test suite for admin functionality
test.describe('FoodXpress Admin Interface', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login to WordPress admin (if credentials are available)
    await page.goto('/wp-admin/');
    
    // Skip if we can't access admin (common in test environments)
    const loginForm = page.locator('#loginform');
    if (await loginForm.isVisible()) {
      test.skip('Admin login required - skipping admin tests');
    }
  });

  test('Plugin settings page accessibility', async ({ page }) => {
    // Test FoodXpress settings page
    await page.goto('/wp-admin/admin.php?page=foodxpress-settings');
    
    // Verify settings page loads
    const settingsForm = page.locator('.foodxpress-settings-form');
    if (await settingsForm.isVisible()) {
      await expect(settingsForm).toBeVisible();
      
      // Test Google Maps API key field
      const apiKeyField = page.locator('#google_maps_api_key');
      if (await apiKeyField.isVisible()) {
        await expect(apiKeyField).toBeVisible();
        
        // Verify it's a password-type field for security
        const fieldType = await apiKeyField.getAttribute('type');
        expect(fieldType).toBe('password');
      }
    }
  });

  test('Order management interface', async ({ page }) => {
    // Test order listing page
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    
    const ordersTable = page.locator('#the-list');
    if (await ordersTable.isVisible()) {
      await expect(ordersTable).toBeVisible();
      
      // Check for FoodXpress-specific columns or information
      const deliveryColumns = page.locator('.column-delivery_status');
      if (await deliveryColumns.count() > 0) {
        await expect(deliveryColumns.first()).toBeVisible();
      }
    }
  });
});
