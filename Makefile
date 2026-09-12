.DEFAULT_GOAL := help
COMPOSE := docker compose

.PHONY: help env install remove up down build logs migrate lint

help:
	@echo make install - configure, build, migrate, seed and start
	@echo make remove  - remove the local environment and generated files
	@echo make up      - start services
	@echo make down    - stop services, keep data
	@echo make build   - build PHP image and Vue assets
	@echo make logs    - follow logs
	@echo make migrate - apply migrations
	@echo make lint    - check PHP and frontend style

env:
	docker run --rm -v "$(CURDIR):/workspace" -w /workspace composer:2.10.2 php docker/init.php

install: build
	$(COMPOSE) run --rm --no-deps app composer install --no-interaction --prefer-dist
	$(COMPOSE) up -d --wait postgres
	$(COMPOSE) run --rm app php artisan migrate --force
	$(COMPOSE) run --rm app php artisan db:seed --force
	$(COMPOSE) run --rm --no-deps app chown -R www-data:www-data storage bootstrap/cache
	$(COMPOSE) up -d --wait
	@echo Ready. Default URL: http://localhost:8080

remove: env
	$(COMPOSE) down --volumes --remove-orphans --rmi local
	docker run --rm -v "$(CURDIR):/workspace" -w /workspace composer:2.10.2 php docker/remove.php
	@echo Removed. The working tree is ready for a clean installation.

build: env
	$(COMPOSE) build app
	$(COMPOSE) run --rm --no-deps frontend sh -c "npm ci && npm run build"

up:
	$(COMPOSE) up -d --wait

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f --tail=100

migrate:
	$(COMPOSE) exec -T app php artisan migrate --force

lint:
	$(COMPOSE) exec -T app vendor/bin/pint --test
	$(COMPOSE) run --rm --no-deps frontend npm run lint
