# Backend - ESDS Command Documentation

## Initial Setup

### 1. Composer Installation & Updates

```bash
# If composer.lock doesn't exist - Fresh Installation
docker compose run --rm composer install

# If composer.lock exists - Update Dependencies
docker compose run --rm composer update
```
### 2. Environment Setup
```bash
# 1. Copy .env.example to .env
cp .env.example .env

# 2. Configure your .env file with appropriate settings
# - Database credentials
# - App settings
# - Mail configuration
# - Other environment-specific variables
```

## Building and Configuration

### 2. Build Docker Containers
```bash
# Build and start containers in detached mode
docker compose up -d --build nginx --force-recreate

# Alternative command if experiencing build issues
# DOCKER_BUILDKIT=0 docker compose up --build nginx
```

### 3. Fix Folder Permissions
```bash
# Set proper ownership for project files
# docker compose exec -it -u root php chown -R www-data:www-data .
docker compose exec -it -u root php chown -R www-data:www-data storage storage/app storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chmod -R 777 storage storage/app storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

```


## Application Setup

### 5. Storage Configuration
```bash
# Create symbolic link for storage
docker compose run -u root --rm artisan storage:link
```

### 6. Database Setup
```bash
# Run database migrations
docker compose run --rm artisan migrate

# Seed the database with initial data
docker compose run --rm artisan db:seed

# If you have a SQL backup file, restore it using:
docker compose exec pgsql bash -c "cd /docker-entrypoint-initdb.d && ./restore.sh"
```

### 7. API Documentation
```bash
# Generate Swagger documentation (accessible at /swagger/documentation)
docker compose run --rm artisan l5-swagger:generate --all
```

### 8. Route List Commands
```bash
# List all registered routes
docker compose run --rm artisan route:list

# List routes in compact form
docker compose run --rm artisan route:list --compact

# List routes for specific method (GET, POST, etc.)
docker compose run --rm artisan route:list --method=GET

# Search for specific routes
docker compose run --rm artisan route:list --name=user

# Show routes with their middleware
docker compose run --rm artisan route:list --show=middleware

# Export routes to JSON format
docker compose run --rm artisan route:list --json

# Show only API routes
docker compose run --rm artisan route:list --path=api
```

## Maintenance Commands

### 9. Cache Management
```bash
# Clear application cache, config, and other cached data
docker compose run --rm artisan optimize:clear
```

### 10. Stop All Services
```bash
# Stop all running containers and remove networks
docker compose down
```

## Available Services

The project includes the following Docker services:

1. **nginx** - Web Server
   - Ports: 80, 443
   - Dependencies: php, pgsql, redis, pgadmin

2. **php** - PHP-FPM Service
   - Mounts project root
   - Uses environment from .env

3. **composer** - PHP Dependency Manager
   - Working directory: /var/www/html
   - For managing PHP packages

4. **artisan** - Laravel Command Line Tool
   - For running Laravel commands
   - Working directory: /var/www/html

5. **phpunit** - Testing Service
   - For running PHP unit tests
   - Working directory: /var/www/html

6. **pgsql** - PostgreSQL Database
   - Port: 5432 (configurable via DB_PORT)
   - Includes backup restoration capability
   - Uses custom Dockerfile with timezone configuration

7. **pgadmin** - PostgreSQL Admin Interface
   - Port: 5050
   - Web-based PostgreSQL management tool
   - Credentials from MAIL_USERNAME/MAIL_PASSWORD

8. **redis** - Redis Server
   - Port: 6379 (configurable via REDIS_PORT)
   - Password protected
   - Persistence enabled

## Troubleshooting

If you encounter any issues:

1. Ensure all containers are running:
```bash
docker compose ps
```

2. Check container logs:
```bash
# View logs for a specific service
docker compose logs [service_name]

# Available service names:
# - nginx
# - php
# - composer
# - artisan
# - phpunit
# - pgsql
# - pgadmin
# - redis
```

3. Common issues:
   - Permission problems: Run the folder permission command (Step 3)
   - Database connection issues: Verify .env database credentials
   - Cache-related problems: Run optimize:clear command

## Notes

- Always ensure your .env file is properly configured before running migrations
- Keep your composer dependencies up to date
- Regularly clear cache when making configuration changes
- Check logs if you encounter any issues
- Make sure all required ports are available on your system
- All services are connected through the 'esds' network



## Docker prune all data

```shell
docker system df
docker stop $(docker ps -aq)  # Bütün konteynerləri dayandır
docker volume rm -f $(docker volume ls -q)  # Bütün volume-ləri sil
docker rm -vf $(docker ps -aq)  # Bütün konteynerləri sil
docker rmi -f $(docker images -aq)  # Bütün image-ləri sil
docker network rm $(docker network ls -q)  # Bütün network-ləri sil
docker system prune -a --volumes -f
docker system df
```
