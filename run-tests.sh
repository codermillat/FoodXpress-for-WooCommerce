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
