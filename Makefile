# ===================================================
# ATLTechCallCenterAPI Development Makefile
# ===================================================

.DEFAULT_GOAL := help
.PHONY: help build up down restart logs shell composer test clean

# Colors
BLUE=$(shell tput setaf 4)
GREEN=$(shell tput setaf 2)
YELLOW=$(shell tput setaf 3)
RED=$(shell tput setaf 1)
NC=$(shell tput sgr0)

## Display this help message
help:
	@echo "$(BLUE)ATLTechCallCenterAPI Available commands:$(NC)"
	@awk '/^[a-zA-Z0-9_-]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")-1); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "  $(GREEN)%-20s$(NC) %s\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST)


## Build Docker images
build:
	@echo "$(BLUE)Building Docker images...$(NC)"
	docker-compose build --no-cache

## Start development environment
up:
	@echo "$(BLUE)Starting development environment...$(NC)"
	docker-compose up  -d --build nginx  --force-recreate #-d nginx pgsql php redis
	@echo "$(GREEN)Environment started$(NC)"
	@echo "$(YELLOW)Application: http://localhost:8000$(NC)"
	@echo "$(YELLOW)PostgreSQL: localhost:6432$(NC)"
	@echo "$(YELLOW)Redis: localhost:8002$(NC)"

## Stop all containers
down:
	@echo "$(BLUE)Stopping containers...$(NC)"
	docker-compose down
	@echo "$(GREEN)Containers stopped$(NC)"

## Restart containers
restart: down up

## Show container logs
logs:
	docker-compose logs -f

## Show specific service logs
logs-php:
	docker-compose logs -f php

logs-nginx:
	docker-compose logs -f nginx

logs-pgsql:
	docker-compose logs -f pgsql

logs-redis:
	docker-compose logs -f redis

## Access application shell
shell:
	docker-compose exec php /bin/bash

## Access database shell
db-shell:
	docker-compose exec pgsql psql -U atltechcallcenterapi_user -d atltechcallcenterapi_db

## Run composer commands
composer:
	docker-compose run --rm composer $(filter-out $@,$(MAKECMDGOALS))

## Install composer dependencies
composer-install:
	docker-compose run --rm composer install

## Update composer dependencies
composer-update:
	docker-compose run --rm composer update

## Run PHP tests
test:
	docker-compose exec php ./vendor/bin/phpunit --configuration phpunit.xml

## Run Laravel Pint (PHP CS Fixer)
cs-fix:
	docker-compose exec php ./vendor/bin/pint

## Run PHPStan analysis
analyse:
	docker-compose exec php ./vendor/bin/phpstan analyse --memory-limit=2G

## Laravel specific commands
artisan:
	docker-compose run --rm artisan $(filter-out $@,$(MAKECMDGOALS))

## Clear Laravel caches
cache-clear:
	docker-compose run --rm artisan cache:clear
	docker-compose run --rm artisan config:clear
	docker-compose run --rm artisan route:clear
	docker-compose run --rm artisan view:clear

## Run Laravel migrations
migrate:
	docker-compose run --rm artisan migrate

## Seed database
seed:
	docker-compose run --rm artisan db:seed

## Fresh migration with seeding
fresh:
	docker-compose run --rm artisan migrate:fresh --seed

## Generate Swagger documentation
swagger:
	@echo "$(BLUE)Generating Swagger documentation from PHP attributes...$(NC)"
	docker-compose run --rm artisan swagger:generate-from-attributes --path="app"
	@echo "$(BLUE)Copying to L5-Swagger location...$(NC)"
	docker-compose exec php cp /var/www/html/be/storage/api-docs/swagger.json /var/www/html/be/storage/api-docs/api-docs.json
	@echo "$(GREEN)Swagger documentation generated successfully!$(NC)"
	@echo "$(YELLOW)OpenAPI JSON: http://localhost:8000/docs$(NC)"
	@echo "$(GREEN)Swagger UI: http://localhost:8000/api/documentation/list$(NC)"

## Run all quality checks
quality: cs-fix analyse test
	@echo "$(GREEN)All quality checks passed$(NC)"

## Setup development environment
setup:
	@echo "$(BLUE)Setting up development environment...$(NC)"
	make build
	make up
	make composer-install
	make migrate
	make seed
	@echo "$(GREEN)Development environment ready!$(NC)"

## Clean up Docker resources
clean:
	@echo "$(BLUE)Cleaning up Docker resources...$(NC)"
	docker-compose down -v --remove-orphans
	docker system prune -f
	@echo "$(GREEN)Cleanup completed$(NC)"

## Show container status
status:
	docker-compose ps

## Monitor container resources
monitor:
	docker stats

## Backup database
backup-db:
	@echo "$(BLUE)Creating database backup...$(NC)"
	docker-compose exec pgsql pg_dump -U atltechcallcenterapi_user atltechcallcenterapi_db > backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)Database backed up$(NC)"

## Restore database
restore-db:
	@echo "$(BLUE)Restoring database...$(NC)"
	@read -p "Enter backup file path: " backup_file; \
	docker-compose exec -T pgsql psql -U atltechcallcenterapi_user -d atltechcallcenterapi_db < $$backup_file
	@echo "$(GREEN)Database restored$(NC)"

# Catch-all target to handle unknown commands
%:
	@:
## Show API token
token:
	@echo "$(BLUE)Call Event API Token:$(NC)"
	@docker-compose exec php php -r "require '/var/www/html/be/vendor/autoload.php'; \$$app = require_once '/var/www/html/be/bootstrap/app.php'; \$$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config('call-event.api_token') . PHP_EOL;"
	@echo "$(GREEN)Use this token in Authorization header: Bearer <token>$(NC)"
