# Quick WordPress Setup Guide for TestSprite

Since you don't have a WordPress site yet, here are your options:

## ✅ Option 1: Quick Docker Setup (Recommended - 5 minutes)

This is the fastest way to get a local WordPress site running:

### Prerequisites
- Docker Desktop installed (https://www.docker.com/products/docker-desktop)

### Setup Steps

1. **Create a docker-compose.yml file** in a new directory:
```bash
mkdir wordpress-test && cd wordpress-test
```

2. **Create docker-compose.yml**:
```yaml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - wordpress_data:/var/www/html
      - ../:/var/www/html/wp-content/plugins/foodxpress-for-woocommerce
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db_data:/var/lib/mysql

volumes:
  wordpress_data:
  db_data:
```

3. **Start WordPress**:
```bash
docker-compose up -d
```

4. **Access WordPress**:
- URL: http://localhost:8080
- Complete WordPress installation wizard
- Install WooCommerce plugin
- Activate FoodXpress plugin

5. **Update TestSprite config**:
```json
{
  "wordpress": {
    "test_url": "http://localhost:8080",
    "admin_credentials": {
      "username": "admin",
      "password": "your_password"
    }
  }
}
```

## ✅ Option 2: Use LocalWP (Easiest - No Docker needed)

1. Download LocalWP: https://localwp.com/
2. Create a new site
3. Install WooCommerce
4. Copy your plugin to `wp-content/plugins/`
5. Activate FoodXpress plugin
6. Update TestSprite config with your LocalWP URL (usually `http://foodxpress.local`)

## ✅ Option 3: Use Remote/Staging Site

If you have access to a staging or development WordPress site:
1. Upload FoodXpress plugin to that site
2. Activate it
3. Update `tests/testsprite-config.json` with the site URL and credentials

## ✅ Option 4: Continue with Static Tests Only

You can continue development using only static tests:
```bash
php tests/FXWTestRunner.php
```

This tests:
- ✅ PHP syntax
- ✅ Security patterns
- ✅ File structure
- ✅ Code quality
- ✅ Plugin headers
- ✅ WordPress hooks

**Note**: Static tests don't test functionality, only code structure.

## Next Steps

Once you have WordPress set up:
1. Update `tests/testsprite-config.json` with your WordPress URL
2. Run TestSprite tests via Cursor chat:
   ```
   "Run TestSprite tests for FoodXpress plugin"
   ```

## Need Help?

- Docker setup issues? Check Docker Desktop is running
- LocalWP issues? See LocalWP documentation
- TestSprite config? See `tests/README-TESTSPRITE.md`

