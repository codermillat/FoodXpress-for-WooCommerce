#!/bin/bash

# Quick start script for Docker WordPress setup
# Usage: ./start-wordpress.sh

echo "🚀 Starting WordPress with Docker..."
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running!"
    echo "Please start Docker Desktop and try again."
    exit 1
fi

# Check if docker-compose.yml exists
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ docker-compose.yml not found!"
    exit 1
fi

# Start containers
echo "📦 Starting containers..."
echo "   (This may take a few minutes on first run as images are downloaded)"
echo ""

# Try to start, if phpmyadmin fails, use simple version
if ! docker-compose up -d 2>&1 | grep -q "Error\|timeout"; then
    echo "✅ All containers started successfully!"
else
    echo "⚠️  phpMyAdmin had network issues, trying without it..."
    echo "📦 Starting WordPress and MySQL only..."
    docker-compose -f docker-compose.simple.yml up -d
fi

# Wait a bit for containers to start
echo "⏳ Waiting for containers to be ready..."
sleep 5

# Check container status (try both compose files)
if docker-compose ps 2>/dev/null | grep -q "Up" || docker-compose -f docker-compose.simple.yml ps 2>/dev/null | grep -q "Up"; then
    echo ""
    echo "✅ WordPress is starting up!"
    echo ""
    echo "📍 Access URLs:"
    echo "   WordPress: http://localhost:8080"
    echo "   Admin:     http://localhost:8080/wp-admin"
    if docker-compose ps 2>/dev/null | grep -q "phpmyadmin"; then
        echo "   phpMyAdmin: http://localhost:8081"
    else
        echo "   phpMyAdmin: Not running (skipped due to network issues)"
    fi
    echo ""
    echo "⏳ Please wait 30-60 seconds for WordPress to fully initialize"
    echo "   Then open http://localhost:8080 in your browser"
    echo ""
    echo "📖 See DOCKER_SETUP.md for complete setup instructions"
else
    echo "❌ Failed to start containers"
    echo "Check logs with: docker-compose logs"
    exit 1
fi

