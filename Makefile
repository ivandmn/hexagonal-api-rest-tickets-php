## ———— Ticket API ————

# Let docker compose interpolate ${VARS} from .env.local too (overrides .env)
ifneq ($(wildcard .env.local),)
export COMPOSE_ENV_FILES := .env,.env.local
endif

# PHP targets run in the app container; from inside it (`make shell`) they run directly.
EXEC := $(if $(wildcard /.dockerenv),,docker compose exec app-tickets)
CONSOLE := $(EXEC) bin/console

.DEFAULT_GOAL := help

.PHONY: help install update fix-perms purge \
	up down build restart logs shell \
	cache-clear db-diff db-migrate \
	cs cs-fix phpstan deptrac \
	test test-unit test-functional test-db test-coverage \
	code-check ci

help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^## ————)' $(firstword $(MAKEFILE_LIST)) \
		| awk 'BEGIN {FS = ":.*?## "}{if (NR == 1) printf "\n\033[1;33m%s\033[0m\n", $$0; else if ($$1 ~ /^##/) {name = $$0; gsub(/^## *(—)* *| *(—)* *$$/, "", name); printf "\n \033[36m▸ %s\033[0m\n", name} else printf "  \033[32m%-35s\033[0m %s\n", $$1, $$2}'

install: ## Install vendors according to the current composer.lock file
	$(EXEC) composer install

update: ## Update vendors according to the current composer.json file
	$(EXEC) composer update

fix-perms: ## Give your user and group write access to var/ (cache, logs)
	$(EXEC) chmod -R ug+rwX var

purge: ## Purge cache and logs
	$(EXEC) sh -c 'rm -rf var/log/*.log && find var -mindepth 1 -maxdepth 1 ! -name log -exec rm -rf {} +'

## ———— Docker ————

up: ## Start the stack docker containers
	docker compose up -d --wait

down: ## Down the stack docker containers
	docker compose down

build: ## Rebuild the docker images
	docker compose build --no-cache

restart: ## Restart the app container (FrankenPHP workers reload the code)
	docker compose restart app-tickets

logs: ## Show logs of container
	docker compose logs -f app-tickets

shell: ## Access app container shell
	docker compose exec -it app-tickets bash

## ———— Symfony ————

cache-clear: ## Clear Symfony cache
	$(CONSOLE) cache:clear

db-diff: ## Generate database migration diff
	$(CONSOLE) doctrine:migrations:diff

db-migrate: ## Run database migrations
	@# The migrations table lives in the "tickets" schema, so it must exist before migrating.
	$(CONSOLE) dbal:run-sql -q 'CREATE SCHEMA IF NOT EXISTS tickets'
	$(CONSOLE) doctrine:migrations:migrate

## ———— Tests ————

test: ## Run every test suite (needs `make test-db` once per `make up`)
	$(EXEC) bin/phpunit --testdox

test-unit: ## Run unit tests (no database needed)
	$(EXEC) bin/phpunit --testsuite unit --testdox

test-functional: ## Run functional tests: HTTP + Doctrine (needs `make test-db`)
	$(EXEC) bin/phpunit --testsuite functional --testdox

test-db: ## Create the schema of the test database (TEST_DATABASE_URL in .env.test)
	$(CONSOLE) --env=test dbal:run-sql -q 'CREATE SCHEMA IF NOT EXISTS tickets'
	$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration

test-coverage: ## Generate test coverage (HTML + XML + text in var/phpunit/coverage)
	$(EXEC) env XDEBUG_MODE=coverage bin/phpunit --testdox \
		--coverage-html var/phpunit/coverage/html-coverage \
		--coverage-xml var/phpunit/coverage/xml-coverage \
		--coverage-text=var/phpunit/coverage/coverage.txt --only-summary-for-coverage-text

## ———— Quality ————

cs: ## Check code style (dry-run)
	$(EXEC) bin/php-cs-fixer fix --no-interaction --dry-run --diff -v

cs-fix: ## Apply code style fixes
	$(EXEC) bin/php-cs-fixer fix

phpstan: ## Run PHPStan static analysis (against the test container)
	$(CONSOLE) cache:warmup --env=test --quiet
	$(EXEC) bin/phpstan analyse --memory-limit=512M

deptrac: ## Check architectural dependencies
	$(EXEC) bin/deptrac analyze --fail-on-uncovered --report-uncovered

code-check: cs deptrac phpstan test-unit ## Fast checks, no database: style, architecture, types, unit tests

ci: cs deptrac phpstan test-db test ## Everything CI runs, against the test database
