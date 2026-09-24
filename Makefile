# ------------------------------------------------------------------
# Developer entry points for the local Docker stack (spec section 27).
# Run from the project root on WSL2.
#
# Targets below are real and verified. Deployment-related targets
# (deploy, rollback, backup, restore-test) will be added on stages
# 11 and 13 once their scripts exist. Spec section 32 forbids adding
# Makefile targets without a working implementation behind them.
# ------------------------------------------------------------------

.DEFAULT_GOAL := help

SHELL := /bin/bash

# Docker Compose wrapper. Used by every lifecycle target.
DC := docker compose

# Container name of the PHP-FPM service, used for one-off commands.
APP := ffxi_laravel_app

# ------------------------------------------------------------------
# Help
# ------------------------------------------------------------------

.PHONY: help
help: ## Show available targets.
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| sort \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------
# Stack lifecycle
# ------------------------------------------------------------------

.PHONY: up
up: ## Build (if needed) and start the stack in the background.
	$(DC) up -d --build

.PHONY: down
down: ## Stop and remove the stack (named volumes are kept).
	$(DC) down

.PHONY: build
build: ## Build images without starting the stack.
	$(DC) build

.PHONY: restart
restart: ## Restart all running services.
	$(DC) restart

.PHONY: ps
ps: ## Show status of every service.
	$(DC) ps

.PHONY: logs
logs: ## Follow logs of every service.
	$(DC) logs -f

.PHONY: logs-app
logs-app: ## Follow logs of the app (PHP-FPM) service.
	$(DC) logs -f app

.PHONY: shell
shell: ## Open a bash shell in the app container.
	$(DC) exec $(APP) bash

# ------------------------------------------------------------------
# Application (runs inside the app container)
# ------------------------------------------------------------------

.PHONY: install
install: ## Run composer install inside the app container.
	$(DC) exec $(APP) composer install

.PHONY: migrate
migrate: ## Run database migrations.
	$(DC) exec $(APP) php artisan migrate

.PHONY: fresh
fresh: ## Drop all tables, re-run migrations, seed demo data.
	$(DC) exec $(APP) php artisan migrate:fresh --seed

.PHONY: key
key: ## Generate a new APP_KEY (overwrites the current one).
	$(DC) exec $(APP) php artisan key:generate

# ------------------------------------------------------------------
# Quality gates (match the CI pipeline, spec section 21)
# ------------------------------------------------------------------

.PHONY: test
test: ## Run the Pest test suite.
	$(DC) exec $(APP) ./vendor/bin/pest

.PHONY: lint
lint: ## Run Laravel Pint (fixing mode).
	$(DC) exec $(APP) ./vendor/bin/pint

.PHONY: lint-test
lint-test: ## Run Laravel Pint in test mode (no file changes).
	$(DC) exec $(APP) ./vendor/bin/pint --test

.PHONY: stan
stan: ## Run Larastan static analysis.
	$(DC) exec $(APP) ./vendor/bin/phpstan analyse

.PHONY: audit
audit: ## Run composer audit for security advisories.
	$(DC) exec $(APP) composer audit

.PHONY: validate
validate: ## Run composer validate --strict.
	$(DC) exec $(APP) composer validate --strict

# ------------------------------------------------------------------
# Not implemented yet — added on later stages with real scripts
# behind them (spec section 32 forbids placeholder targets).
#
#   deploy        stage 13 — rsync + production commands
#   rollback      stage 13 — previous release tag
#   backup        stage 11 — mysqldump + external copy
#   restore-test  stage 11 — restore into a separate dev environment
# ------------------------------------------------------------------
