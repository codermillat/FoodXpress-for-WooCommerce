# TestSprite Quick Start Guide

## ✅ What's Ready

1. ✅ **Static Tests** - All 86 tests passing (no WordPress needed)
2. ✅ **Docker Configuration** - `docker-compose.yml` ready
3. ✅ **TestSprite Code Summary** - Generated and ready
4. ✅ **Test Configuration** - `tests/testsprite-config.json` prepared

## 🚀 Next Steps

### Step 1: Install Docker Desktop

If you don't have Docker installed:

1. Download Docker Desktop: https://www.docker.com/products/docker-desktop
2. Install and start Docker Desktop
3. Verify installation:
   ```bash
   docker --version
   ```

### Step 2: Start WordPress

**Option A: Use the quick start script**
```bash
./start-wordpress.sh
```

**Option B: Manual start**
```bash
docker-compose up -d
```

### Step 3: Set Up WordPress

1. Open http://localhost:8080 in your browser
2. Complete WordPress installation:
   - Choose language
   - Set site title, username, password
   - **Remember your password** - you'll need it for TestSprite!
3. Install WooCommerce:
   - Go to Plugins → Add New
   - Search "WooCommerce" → Install → Activate
4. Activate FoodXpress plugin:
   - Go to Plugins → Installed Plugins
   - Find "FoodXpress for WooCommerce" → Activate

### Step 4: Configure TestSprite

Edit `tests/testsprite-config.json`:

```json
{
  "wordpress": {
    "test_url": "http://localhost:8080",
    "admin_credentials": {
      "username": "admin",
      "password": "your_password_here"
    }
  }
}
```

Replace:
- `admin` with your WordPress username
- `your_password_here` with your WordPress password

### Step 5: Run TestSprite Tests

In Cursor chat, type:
```
Run TestSprite tests for FoodXpress plugin
```

Or be more specific:
```
Test plugin activation with TestSprite
Test order management workflow
Generate and execute all TestSprite tests
```

## 📚 Documentation

- **Docker Setup**: `DOCKER_SETUP.md` - Detailed Docker instructions
- **WordPress Setup**: `tests/WORDPRESS_SETUP_GUIDE.md` - Alternative setup methods
- **TestSprite Guide**: `tests/README-TESTSPRITE.md` - Advanced TestSprite usage
- **Quick Start**: `tests/QUICK_START.md` - 5-minute TestSprite setup

## 🛠️ Useful Commands

```bash
# Start WordPress
docker-compose up -d

# Stop WordPress
docker-compose down

# View logs
docker-compose logs -f wordpress

# Restart WordPress
docker-compose restart wordpress

# Reset everything (⚠️ deletes all data)
docker-compose down -v
```

## ✅ Current Status

- ✅ Static code tests: **86/86 passing**
- ✅ Docker config: **Ready**
- ✅ TestSprite MCP: **Configured**
- ⏳ WordPress: **Needs setup** (Step 2 above)
- ⏳ TestSprite config: **Needs credentials** (Step 4 above)

## 🆘 Troubleshooting

**Docker not found?**
- Install Docker Desktop
- Make sure it's running (check system tray)

**Port 8080 in use?**
- Edit `docker-compose.yml`
- Change `8080:80` to another port like `8082:80`

**Plugin not showing?**
- Check volume mount in `docker-compose.yml`
- Restart: `docker-compose restart`

**Need help?**
- See `DOCKER_SETUP.md` for detailed troubleshooting
- Check `tests/README-TESTSPRITE.md` for TestSprite help

