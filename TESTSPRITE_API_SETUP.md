# TestSprite API Key Setup

## Issue
TestSprite MCP requires a valid API key to generate test plans and execute tests.

## Solution

### Step 1: Get Your API Key

1. Visit: **https://www.testsprite.com/dashboard/settings/apikey**
2. Sign in to your TestSprite account (or create one if you don't have one)
3. Create a new API key
4. Copy the API key

### Step 2: Configure in Cursor

1. Open **Cursor Settings** → **Features** → **MCP**
2. Find the **TestSprite MCP** server configuration
3. Update the environment variable:
   - **Key**: `TESTSPRITE_API_KEY`
   - **Value**: Paste your API key here
4. Save and restart Cursor

### Step 3: Verify Setup

After restarting Cursor, you can verify the API key is working by asking in chat:
```
"List TestSprite MCP resources"
```

Or try generating a test plan again.

## Alternative: Use TestSprite Web UI

If you prefer, you can also use the TestSprite web interface directly:

1. The TestSprite UI is already open in your browser (localhost:52532)
2. Complete the initialization form:
   - Port: `8080`
   - Path: `/`
   - Upload `PRODUCT_SPEC.md`
3. Click "Continue" to proceed
4. TestSprite will generate tests through the web interface

## Next Steps

Once the API key is configured:
1. ✅ Restart Cursor
2. ✅ Run test plan generation
3. ✅ Execute tests

## Need Help?

- TestSprite Docs: https://docs.testsprite.com
- API Key Help: https://www.testsprite.com/dashboard/settings/apikey
- Support: Check TestSprite documentation

