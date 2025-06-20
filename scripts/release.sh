#!/bin/bash

# PHP CommitLint Release Script
# This script helps prepare releases by running all checks and updating version info

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if version is provided
if [ -z "$1" ]; then
    print_error "Usage: $0 <version>"
    print_error "Example: $0 1.0.0"
    exit 1
fi

VERSION=$1

print_status "Preparing release for PHP CommitLint v$VERSION"

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    print_error "composer.json not found. Please run this script from the project root."
    exit 1
fi

# Check if git is clean
if [ -n "$(git status --porcelain)" ]; then
    print_error "Git working directory is not clean. Please commit or stash changes first."
    exit 1
fi

# Run all quality checks
print_status "Running quality checks..."

# Validate composer.json
print_status "Validating composer.json..."
composer validate --strict || {
    print_error "Composer validation failed"
    exit 1
}

# Install dependencies
print_status "Installing dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader || {
    print_error "Composer install failed"
    exit 1
}

# Reinstall dev dependencies for tests
composer install || {
    print_error "Composer install with dev dependencies failed"
    exit 1
}

# Run code style check
print_status "Checking code style..."
composer run cs:check || {
    print_error "Code style check failed"
    exit 1
}

# Run static analysis
print_status "Running static analysis..."
composer run analyse || {
    print_error "Static analysis failed"
    exit 1
}

# Run tests
print_status "Running tests..."
composer run test || {
    print_error "Tests failed"
    exit 1
}

# Run security audit
print_status "Running security audit..."
composer audit || {
    print_warning "Security audit found issues. Please review."
}

# Update version in composer.json (if needed)
# This would require jq or similar JSON processor
# For now, manual update is expected

print_success "All quality checks passed!"

# Create git tag
print_status "Creating git tag v$VERSION..."
git tag -a "v$VERSION" -m "Release version $VERSION"

print_success "Release v$VERSION prepared successfully!"
print_status "Next steps:"
echo "  1. Push the tag: git push origin v$VERSION"
echo "  2. Create a GitHub release with the tag"
echo "  3. Update CHANGELOG.md with release notes"
echo "  4. Consider updating documentation if needed"

print_warning "Remember to update the version in Application.php if needed!" 