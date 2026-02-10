#!/bin/bash

# Docker Test Runner for league-router-attributes
# Runs tests, PHPStan, and PHP CS Fixer in a Docker container

set -e

echo "=========================================="
echo "Running Tests in Docker Container"
echo "=========================================="
echo ""

# Use PHP 8.0 CLI image (minimum required version)
PHP_IMAGE="php:8.0-cli"

# Run Docker container with project mounted as volume
docker run --rm \
    -v "$(pwd):/app" \
    -w /app \
    "$PHP_IMAGE" \
    bash -c '
        echo "Installing system dependencies..."
        apt-get update -qq && apt-get install -y -qq git unzip zip > /dev/null
        
        echo "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
        
        echo ""
        echo "Installing dependencies..."
        composer install --no-interaction --prefer-dist --optimize-autoloader
        
        echo ""
        echo "=========================================="
        echo "Running Pest Tests"
        echo "=========================================="
        ./vendor/bin/pest --colors=always || EXIT_CODE=$?
        
        echo ""
        echo "=========================================="
        echo "Running PHPStan (Level 6)"
        echo "=========================================="
        ./vendor/bin/phpstan analyse --no-progress --no-ansi || EXIT_CODE=$?
        
        echo ""
        echo "=========================================="
        echo "Running PHP CS Fixer (Dry Run)"
        echo "=========================================="
        ./vendor/bin/php-cs-fixer fix --dry-run --diff --verbose || EXIT_CODE=$?
        
        echo ""
        echo "=========================================="
        echo "All checks completed!"
        echo "=========================================="
        
        exit ${EXIT_CODE:-0}
    '

echo ""
echo "Docker container cleaned up (--rm flag)"
