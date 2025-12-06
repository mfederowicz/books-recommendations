# Default target
.DEFAULT_GOAL := help

.PHONY: help install lint fix rector rector-dry test quality assets assets-watch assets-prod

help: ## Show this help
	@echo ""
	@echo "Available make commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

install: ## Install composer dependencies
	./bin/run.sh composer install

lint: ## Run style check without modifying files
	./bin/run.sh vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Automatically fix style issues
	./bin/run.sh vendor/bin/php-cs-fixer fix

rector: ## Run Rector with changes applied
	./bin/run.sh vendor/bin/rector process --ansi

rector-dry: ## Dry-run Rector without modifying code
	./bin/run.sh vendor/bin/rector process --dry-run --ansi

test: ## Run PHPUnit tests
	./bin/run.sh vendor/bin/phpunit --stderr --colors=never

quality: ## Run full quality suite: style + tests
	make lint
	make test

migrations-diff: ## Generate new Doctrine migration based on entity changes
	@echo "Checking for schema changes..."
	@if ./bin/run.sh php bin/console doctrine:migrations:diff --allow-empty-diff 2>&1 | grep -q "Generated new migration"; then \
		echo "Migration generated successfully."; \
	else \
		echo "No changes detected in mapping information."; \
	fi

migrations-migrate: ## Execute pending Doctrine migrations
	./bin/run.sh bin/console doctrine:migrations:migrate

assets: ## Build frontend assets for development
	npm run build

assets-prod: ## Build and minify frontend assets for production
	npm run build-prod

assets-watch: ## Watch and rebuild frontend assets during development
	npm run dev

