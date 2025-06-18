.PHONY: help install test lint fix phpstan coverage clean all

# Default target
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	composer install

test: ## Run tests
	composer test

test-coverage: ## Run tests with coverage
	composer test -- --coverage-html=coverage

lint: ## Check code style
	PHP_CS_FIXER_IGNORE_ENV=1 composer cs-fix -- --dry-run --diff

fix: ## Fix code style issues
	PHP_CS_FIXER_IGNORE_ENV=1 composer cs-fix

phpstan: ## Run static analysis
	composer phpstan

security: ## Run security audit
	composer audit

validate: ## Validate composer.json
	composer validate --strict

all: install validate lint phpstan test ## Run all checks

clean: ## Clean generated files
	rm -rf coverage/
	rm -rf .phpunit.cache/
	rm -f .php-cs-fixer.cache

ci: validate lint phpstan test ## Run CI pipeline locally

hooks-install: ## Install git hooks
	./bin/php-commitlint install

hooks-uninstall: ## Uninstall git hooks
	./bin/php-commitlint uninstall

demo: ## Show demo of the tool
	@echo "Demo: Valid commit message"
	@./bin/php-commitlint validate "feat: add new validation feature"
	@echo ""
	@echo "Demo: Invalid commit message"
	@./bin/php-commitlint validate "bad commit message" || true 