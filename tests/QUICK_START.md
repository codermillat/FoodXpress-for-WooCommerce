# TestSprite MCP Quick Start

## 5-Minute Setup

### 1. Get TestSprite API Key
- Visit https://testsprite.com
- Sign up for free account
- Copy your API key

### 2. Configure in Cursor
1. Open Cursor Settings → Features → MCP
2. Click "+ Add New MCP Server"
3. Enter:
   - Name: `TestSprite MCP`
   - Command: `npx @testsprite/mcp-server`
   - Env: `TESTSPRITE_API_KEY=your_key_here`
4. Save and restart Cursor

### 3. Update Test Config
Edit `tests/testsprite-config.json` with your WordPress site URL:
```json
{
  "wordpress": {
    "test_url": "https://your-staging-site.com",
    "admin_credentials": {
      "username": "your_admin_username",
      "password": "your_admin_password"
    }
  }
}
```

**Note**: You can use a remote/staging WordPress site - no local installation needed!
See `TESTING_WITHOUT_LOCAL_WP.md` for details.

### 4. Run Your First Test
In Cursor chat, type:
```
Run TestSprite tests for FoodXpress plugin
```

That's it! 🎉

## Common Commands

```
"Run all TestSprite tests"
"Test plugin activation"
"Test order management workflow"
"Test delivery dashboard"
"Generate test plan for checkout feature"
"Show test results"
```

## Troubleshooting

**MCP not found?**
- Check Node.js version: `node --version` (needs 22+)
- Restart Cursor after configuration

**Tests fail to connect?**
- Verify WordPress URL is correct
- Check WordPress is running
- Verify admin credentials

**Need help?**
- See `TESTSPRITE_SETUP.md` for detailed guide
- Check `tests/README-TESTSPRITE.md` for advanced usage

