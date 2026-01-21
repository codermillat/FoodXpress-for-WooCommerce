# TestSprite MCP Setup Guide for FoodXpress Plugin

This guide will help you set up TestSprite MCP (Model Context Protocol) in Cursor to automate testing of your WordPress plugin.

## Prerequisites

1. **Node.js 22+**: Ensure you have Node.js version 22 or higher installed
   ```bash
   node --version  # Should be v22.0.0 or higher
   ```

2. **TestSprite Account**: Sign up for a free account at [TestSprite](https://testsprite.com) to get your API key

3. **Cursor IDE**: Make sure you're using the latest version of Cursor

## Installation Steps

### Step 1: Install TestSprite MCP Server in Cursor

1. Open Cursor IDE
2. Navigate to **Settings** → **Features** → **MCP**
3. Click **"+ Add New MCP Server"**
4. Configure the server with these details:
   - **Name**: `TestSprite MCP`
   - **Transport Type**: `stdio` (recommended)
   - **Command**: `npx @testsprite/mcp-server`
   - **Environment Variables**:
     - `TESTSPRITE_API_KEY`: Your TestSprite API key
     - `TESTSPRITE_PROJECT_ID`: (Optional) Your project ID if you have one

5. Save the configuration
6. Restart Cursor to apply changes

### Step 2: Verify Installation

After restarting Cursor, you can verify TestSprite MCP is working by:

1. Opening the Cursor chat/command palette
2. Asking: "List available MCP resources" or "What TestSprite tools are available?"
3. You should see TestSprite MCP tools available

## Using TestSprite MCP for Plugin Testing

### Basic Workflow

1. **Generate Test Plans**: Ask Cursor to generate test plans for your plugin features
   ```
   "Generate test plans for FoodXpress plugin using TestSprite MCP"
   ```

2. **Execute Tests**: Run the generated tests
   ```
   "Execute WordPress plugin tests for FoodXpress using TestSprite"
   ```

3. **Review Results**: TestSprite will provide detailed reports with pass/fail status

### Test Coverage Areas

TestSprite MCP can help test:

- ✅ **Plugin Activation/Deactivation**
- ✅ **WooCommerce Integration**
- ✅ **Custom Order Statuses** (fxw-in-kitchen, fxw-assigned, fxw-picked-up)
- ✅ **Delivery Dashboard Functionality**
- ✅ **Google Maps Integration**
- ✅ **AJAX Endpoints** (with nonce verification)
- ✅ **User Role Permissions** (Delivery Boy role)
- ✅ **Email Notifications**
- ✅ **Shortcodes** ([fxw_track_order], [fxw_reorder])
- ✅ **Shipping Method Configuration**
- ✅ **Settings Page**

## Example Test Scenarios

### 1. Plugin Activation Test
```yaml
Test: Plugin Activation
Steps:
  - Activate FoodXpress plugin
  - Verify WooCommerce dependency check
  - Verify custom order statuses registered
  - Verify delivery boy role created
  - Verify rewrite rules flushed
Expected: All components initialized correctly
```

### 2. Order Status Flow Test
```yaml
Test: Order Status Progression
Steps:
  - Create test order
  - Change status to "In Kitchen"
  - Assign delivery boy
  - Change status to "Assigned"
  - Change status to "Picked Up"
  - Change status to "Delivered"
Expected: Each status change triggers correct email and updates
```

### 3. Delivery Dashboard Test
```yaml
Test: Delivery Dashboard Access
Steps:
  - Login as delivery boy user
  - Navigate to /delivery-dashboard/
  - Verify only assigned orders visible
  - Test order status update functionality
Expected: Proper access control and functionality
```

## Integration with Existing Tests

Your existing `FXWTestRunner.php` tests static code analysis. TestSprite MCP complements this by:

- **Static Tests** (FXWTestRunner): Syntax, security patterns, file structure
- **Dynamic Tests** (TestSprite): Functional testing, user interactions, WordPress integration

Run both for comprehensive coverage:
```bash
# Static tests
php tests/FXWTestRunner.php

# Dynamic tests (via Cursor + TestSprite MCP)
# Use Cursor chat to execute TestSprite tests
```

## Troubleshooting

### MCP Server Not Found
- Ensure Node.js 22+ is installed
- Check that `npx @testsprite/mcp-server` works in terminal
- Verify API key is correct

### Tests Not Executing
- Check TestSprite API key is valid
- Ensure WordPress test environment is accessible
- Verify plugin is activated in test environment

### Permission Issues
- Ensure Cursor has necessary permissions
- Check file system permissions for test files

## Resources

- [TestSprite Documentation](https://docs.testsprite.com)
- [TestSprite MCP Installation Guide](https://docs.testsprite.com/mcp/installation)
- [MCP Protocol Documentation](https://modelcontextprotocol.io)

## Testing Without Local WordPress

**Don't have WordPress installed locally?** No problem! TestSprite MCP can test against any accessible WordPress site:

### Option 1: Use Remote/Staging Site (Recommended)
- Update `tests/testsprite-config.json` with your staging/production WordPress URL
- TestSprite MCP works with any accessible WordPress site
- See `tests/TESTING_WITHOUT_LOCAL_WP.md` for detailed guide

### Option 2: Quick Docker Setup
- See `tests/TESTING_WITHOUT_LOCAL_WP.md` for Docker WordPress setup
- Takes just a few minutes to get running

### Option 3: Static Tests Only
- Run `php tests/FXWTestRunner.php` for code analysis
- No WordPress needed for static tests

## Next Steps

1. ✅ **MCP Already Configured** - You're set!
2. Update `tests/testsprite-config.json` with your WordPress site URL (remote or local)
3. Ensure FoodXpress plugin is installed on that WordPress site
4. Start testing via Cursor chat: "Run TestSprite tests for FoodXpress plugin"
5. Gradually expand test coverage

---

**Note**: TestSprite MCP works with:
- Remote/staging WordPress sites (no local install needed!)
- Local WordPress installations
- Docker WordPress setups
- Any accessible WordPress URL

