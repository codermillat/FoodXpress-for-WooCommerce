#!/bin/bash
# FoodXpress for WooCommerce - Test Environment Setup Script
# This script sets up a comprehensive testing environment with Docker, PHPUnit, and Playwright

set -e

echo "🚀 Setting up FoodXpress testing environment..."

# Create test directories
mkdir -p tests/{unit,integration,e2e,fixtures,docker}
mkdir -p tests/unit/{security,core,checkout,mapping}
mkdir -p tests/integration/{woocommerce,api,shipping}
mkdir -p tests/e2e/{checkout-flow,admin-panel,delivery-tracking}

echo "📁 Created test directory structure"

# Create PHPUnit configuration
cat > tests/phpunit.xml << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false"
    testSuiteLoaderClass="PHPUnit\Runner\StandardTestSuiteLoader"
    testSuiteLoaderFile=""
    verbose="true">

    <testsuites>
        <testsuite name="Security Tests">
            <directory>unit/security</directory>
        </testsuite>
        <testsuite name="Core Tests">
            <directory>unit/core</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>integration</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory suffix=".php">../includes</directory>
            <exclude>
                <file>../includes/class-fxw-admin-bar.php</file>
            </exclude>
        </whitelist>
    </filter>

    <logging>
        <log type="coverage-html" target="coverage-report"/>
        <log type="coverage-clover" target="coverage.xml"/>
    </logging>
</phpunit>
EOF

echo "⚙️ Created PHPUnit configuration"

# Create Docker Compose for WordPress test environment
cat > tests/docker/docker-compose.yml << 'EOF'
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
      WORDPRESS_DEBUG: 1
    volumes:
      - wordpress_data:/var/www/html
      - ../../:/var/www/html/wp-content/plugins/foodxpress
    depends_on:
      - db

  woocommerce:
    image: wordpress:latest
    ports:
      - "8081:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: woocommerce
      WORDPRESS_DB_PASSWORD: woocommerce
      WORDPRESS_DB_NAME: woocommerce
      WORDPRESS_DEBUG: 1
    volumes:
      - woocommerce_data:/var/www/html
      - ../../:/var/www/html/wp-content/plugins/foodxpress
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    volumes:
      - db_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password

  db_woo:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: woocommerce
      MYSQL_USER: woocommerce
      MYSQL_PASSWORD: woocommerce
    volumes:
      - db_woo_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password

  selenium:
    image: selenium/standalone-chrome:latest
    ports:
      - "4444:4444"
    volumes:
      - /dev/shm:/dev/shm

volumes:
  wordpress_data:
  woocommerce_data:
  db_data:
  db_woo_data:
EOF

echo "🐳 Created Docker test environment"

# Create Playwright configuration
cat > tests/e2e/playwright.config.js << 'EOF'
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['html'],
    ['json', { outputFile: 'test-results.json' }],
    ['junit', { outputFile: 'test-results.xml' }]
  ],
  use: {
    baseURL: 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] },
    }
  ],

  webServer: {
    command: 'cd ../docker && docker-compose up',
    port: 8080,
    reuseExistingServer: !process.env.CI,
  },
});
EOF

echo "🎭 Created Playwright configuration"

# Create GitHub Actions workflow
mkdir -p .github/workflows
cat > .github/workflows/test.yml << 'EOF'
name: FoodXpress Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  security-tests:
    runs-on: ubuntu-latest
    name: Security & Standards Tests
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, intl, gd, xml, dom, json, fileinfo, curl, zip, iconv
        
    - name: Install Composer dependencies
      run: composer install --prefer-dist --no-interaction
      
    - name: Run PHP CodeSniffer
      run: vendor/bin/phpcs --standard=WordPress includes/
      
    - name: Run PHPStan
      run: vendor/bin/phpstan analyse includes/ --level=5
      
    - name: Security vulnerability scan
      run: composer audit

  unit-tests:
    runs-on: ubuntu-latest
    name: PHPUnit Tests
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, intl, gd, xml, dom, json, fileinfo, curl, zip, iconv
        coverage: xdebug
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-interaction
      
    - name: Run PHPUnit tests
      run: cd tests && php ../vendor/bin/phpunit --configuration phpunit.xml --coverage-clover coverage.xml
      
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: tests/coverage.xml

  e2e-tests:
    runs-on: ubuntu-latest
    name: End-to-End Tests
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install Playwright
      run: |
        cd tests/e2e
        npm init -y
        npm install @playwright/test
        npx playwright install --with-deps
        
    - name: Start test environment
      run: |
        cd tests/docker
        docker-compose up -d
        sleep 30
        
    - name: Run Playwright tests
      run: |
        cd tests/e2e
        npx playwright test
        
    - name: Upload test results
      uses: actions/upload-artifact@v3
      if: always()
      with:
        name: playwright-report
        path: tests/e2e/playwright-report/
EOF

echo "🔄 Created CI/CD pipeline"

# Create test bootstrap file
cat > tests/bootstrap.php << 'EOF'
<?php
/**
 * PHPUnit bootstrap file for FoodXpress for WooCommerce tests
 */

// Define test environment
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'test@example.com' );
define( 'WP_TESTS_TITLE', 'FoodXpress Test Site' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_TABLE_PREFIX', 'fxwtest_' );

// Define WordPress test constants
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Load WordPress test environment if available
if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
    require_once '/tmp/wordpress-tests-lib/includes/bootstrap.php';
} else {
    // Fallback for local development
    echo "WordPress test environment not found. Setting up minimal test environment...\n";
    
    // Mock WordPress functions for isolated testing
    if ( ! function_exists( 'add_action' ) ) {
        function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
            // Mock implementation
            return true;
        }
    }
    
    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
            // Mock implementation
            return true;
        }
    }
    
    if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = 'default' ) {
            return $text;
        }
    }
    
    if ( ! function_exists( '_e' ) ) {
        function _e( $text, $domain = 'default' ) {
            echo $text;
        }
    }
    
    if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) {
            return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
        }
    }
    
    if ( ! function_exists( 'wp_remote_get' ) ) {
        function wp_remote_get( $url, $args = array() ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body' => '{"status":"OK"}'
            );
        }
    }
    
    if ( ! class_exists( 'WP_Error' ) ) {
        class WP_Error {
            public function __construct( $code = '', $message = '', $data = '' ) {
                $this->errors = array( $code => array( $message ) );
            }
            
            public function get_error_message() {
                return 'Test error';
            }
        }
    }
}

// Load plugin files for testing
define( 'FXW_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'FXW_VERSION', '1.0.0' );

echo "FoodXpress test environment loaded successfully.\n";
EOF

echo "🧪 Created test bootstrap"

# Create package.json for Playwright
cat > tests/e2e/package.json << 'EOF'
{
  "name": "foodxpress-e2e-tests",
  "version": "1.0.0",
  "description": "End-to-end tests for FoodXpress for WooCommerce",
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "playwright test --debug",
    "report": "playwright show-report"
  },
  "devDependencies": {
    "@playwright/test": "^1.40.0"
  }
}
EOF

echo "📦 Created Playwright package configuration"

# Create test execution script
cat > run-tests.sh << 'EOF'
#!/bin/bash
# FoodXpress Test Execution Script

echo "🧪 Running FoodXpress for WooCommerce Test Suite"
echo "================================================"

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check dependencies
echo "Checking dependencies..."

if ! command_exists php; then
    echo "❌ PHP is required but not installed"
    exit 1
fi

if ! command_exists docker; then
    echo "❌ Docker is required but not installed"
    exit 1
fi

if ! command_exists docker-compose; then
    echo "❌ Docker Compose is required but not installed"
    exit 1
fi

echo "✅ All dependencies found"

# Run syntax checks
echo "🔍 Running syntax checks..."
find . -name "*.php" -not -path "./tests/*" -not -path "./vendor/*" -exec php -l {} \; > /tmp/syntax_check.log 2>&1

if [ $? -eq 0 ]; then
    echo "✅ All PHP files pass syntax check"
else
    echo "❌ Syntax errors found:"
    cat /tmp/syntax_check.log
    exit 1
fi

# Start Docker environment
echo "🐳 Starting Docker test environment..."
cd tests/docker
docker-compose up -d

echo "⏳ Waiting for services to be ready..."
sleep 30

# Wait for WordPress to be ready
echo "🔄 Waiting for WordPress to be ready..."
timeout 120 bash -c 'until curl -s http://localhost:8080 > /dev/null; do sleep 5; done'

if [ $? -eq 0 ]; then
    echo "✅ WordPress is ready"
else
    echo "❌ WordPress failed to start"
    docker-compose logs
    exit 1
fi

# Run PHPUnit tests if available
if [ -f "vendor/bin/phpunit" ]; then
    echo "🧪 Running PHPUnit tests..."
    cd ../
    php vendor/bin/phpunit --configuration tests/phpunit.xml
else
    echo "⚠️ PHPUnit not found, skipping unit tests"
fi

# Run Playwright tests if available
if [ -d "tests/e2e/node_modules" ]; then
    echo "🎭 Running Playwright tests..."
    cd tests/e2e
    npx playwright test
else
    echo "⚠️ Playwright not installed, skipping E2E tests"
fi

echo "🏁 Test execution completed"
echo "📊 Check test results in:"
echo "   - PHPUnit: tests/coverage-report/"
echo "   - Playwright: tests/e2e/playwright-report/"

# Cleanup
echo "🧹 Cleaning up..."
cd ../docker
docker-compose down

echo "✅ Test suite execution finished"
EOF

chmod +x run-tests.sh
chmod +x tests/setup-test-environment.sh

echo "✅ Test environment setup completed!"
echo ""
echo "📋 Next steps:"
echo "1. Run './tests/setup-test-environment.sh' to initialize"
echo "2. Install dependencies: 'composer install' and 'cd tests/e2e && npm install'"
echo "3. Execute tests: './run-tests.sh'"
echo ""
echo "🔧 Manual testing available at:"
echo "   - WordPress: http://localhost:8080"
echo "   - WooCommerce: http://localhost:8081"
echo ""
echo "📁 Test structure created:"
echo "   - tests/unit/ - PHPUnit unit tests"
echo "   - tests/integration/ - Integration tests"
echo "   - tests/e2e/ - Playwright E2E tests"
echo "   - tests/docker/ - Docker test environment"
