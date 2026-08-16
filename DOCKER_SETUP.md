# Docker WordPress Setup for FoodXpress Local Testing

This guide will help you set up a local WordPress environment using Docker for local testing.

## Prerequisites

- **Docker Desktop** installed and running
  - Download: https://www.docker.com/products/docker-desktop
  - Make sure Docker Desktop is running before proceeding

## Quick Start

### Step 1: Start WordPress

From the project root directory, run:

```bash
docker-compose up -d
```

This will:
- Start WordPress on http://localhost:8080
- Start MySQL database
- Start phpMyAdmin on http://localhost:8081
- Mount your plugin directory automatically

### Step 2: Complete WordPress Installation

1. Open your browser and go to: **http://localhost:8080**
2. You'll see the WordPress installation wizard
3. Select your language and click "Continue"
4. Fill in the site information:
   - **Site Title**: FoodXpress Test Site (or any name)
   - **Username**: `admin` (or your choice)
   - **Password**: Choose a strong password (you'll need this for TestSprite)
   - **Email**: Your email address
5. Click "Install WordPress"

### Step 3: Install WooCommerce

1. After WordPress installation, log in to the admin dashboard
2. Go to **Plugins → Add New**
3. Search for "WooCommerce"
4. Click **Install Now** and then **Activate**
5. Complete the WooCommerce setup wizard (you can skip most steps for testing)

### Step 4: Activate FoodXpress Plugin

1. Go to **Plugins → Installed Plugins**
2. Find "FoodXpress for WooCommerce"
3. Click **Activate**

The plugin should already be there because we mounted the directory!

### Step 5: Configure TestSprite

Update `tests/testsprite-config.json` with your WordPress credentials:

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

### Step 6: Run TestSprite Tests

In Cursor chat, type:
```
Run TestSprite tests for FoodXpress plugin
```

## Useful Commands

### Start WordPress
```bash
docker-compose up -d
```

### Stop WordPress
```bash
docker-compose down
```

### View Logs
```bash
docker-compose logs -f wordpress
```

### Restart WordPress
```bash
docker-compose restart wordpress
```

### Access WordPress Container
```bash
docker exec -it foodxpress-wordpress bash
```

### Access Database (phpMyAdmin)
- URL: http://localhost:8081
- Server: `db`
- Username: `wordpress`
- Password: `wordpress`

## Troubleshooting

### Port 8080 Already in Use
If port 8080 is already in use, edit `docker-compose.yml` and change:
```yaml
ports:
  - "8080:80"  # Change 8080 to another port like 8082
```

### Plugin Not Showing Up
1. Make sure Docker Desktop is running
2. Restart the containers: `docker-compose restart`
3. Check the volume mount in `docker-compose.yml`

### Can't Access WordPress
1. Wait a minute for containers to fully start
2. Check if containers are running: `docker-compose ps`
3. Check logs: `docker-compose logs wordpress`

### Reset Everything
```bash
docker-compose down -v
docker-compose up -d
```
⚠️ **Warning**: This will delete all WordPress data!

## Next Steps

Once WordPress is running:
1. ✅ Update `tests/testsprite-config.json` with credentials
2. ✅ Run TestSprite tests via Cursor
3. ✅ Test your plugin features

## Access URLs

- **WordPress Site**: http://localhost:8080
- **WordPress Admin**: http://localhost:8080/wp-admin
- **phpMyAdmin**: http://localhost:8081

## Notes

- Your plugin code changes are automatically reflected (no need to restart)
- Database persists between container restarts
- To completely reset, use `docker-compose down -v`

