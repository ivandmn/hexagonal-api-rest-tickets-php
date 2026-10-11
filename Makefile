## ———— Ticket API ————

# Let docker compose interpolate ${VARS} from .env.local too (overrides .env)
ifneq ($(wildcard .env.local),)
export COMPOSE_ENV_FILES := .env,.env.local
endif

help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^## ————)' $(firstword $(MAKEFILE_LIST)) \
		| awk 'BEGIN {FS = ":.*?## "}{if (NR == 1) printf "\n\033[1;33m%s\033[0m\n", $$0; else if ($$1 ~ /^##/) {name = $$0; gsub(/^## *(—)* *| *(—)* *$$/, "", name); printf "\n \033[36m▸ %s\033[0m\n", name} else printf "  \033[32m%-35s\033[0m %s\n", $$1, $$2}'

install: ## Install vendors according to the current composer.lock file
	composer install

update: ## Update vendors according to the current composer.json file
	composer update

fix-perms: ## Fix permissions of all var files
	chmod -R 777 var/*

purge: ## Purge cache and logs
	rm -rf var/log/*.log
	find var -mindepth 1 -maxdepth 1 ! -name log -exec rm -rf {} +

## ———— Docker ————

up: ## Start the stack docker containers
	docker compose up -d

down: ## Down the stack docker containers
	docker compose down

build: ## Rebuild the docker images
	docker compose build --no-cache

restart: ## Restart docker services
	docker compose --profile supervisor restart

logs: ## Show logs of container
	docker compose logs -f app-tickets

shell: ## Access app container shell
	docker compose exec -it app-tickets bash

## ———— Symfony ————

cache-clear: ## Clear Symfony cache
	bin/console cache:clear

db-diff: ## Generate database migration diff
	bin/console doctrine:migrations:diff

db-migrate: ## Run database migrations
	bin/console doctrine:migrations:migrate

## ———— Quality ————

cs: ## Check code style (dry-run)
	bin/php-cs-fixer --no-interaction --dry-run --diff -v fix

cs-fix: ## Apply code style fixes
	bin/php-cs-fixer fix

phpstan: ## Run PHPStan static analysis
	bin/console cache:warmup --env=local --quiet
	bin/phpstan analyse --memory-limit=512M

test-unit: ## Run unit tests
	bin/phpunit --testsuite Unit --stop-on-failure --testdox

test-coverage: ## Generate test coverage
	XDEBUG_MODE=coverage bin/phpunit --stop-on-failure --testdox --coverage-html var/coverage

deptrac: ## Check architectural dependencies
	bin/deptrac analyze --fail-on-uncovered --report-uncovered

code-check: cs deptrac phpstan test ## Run all code quality checks

