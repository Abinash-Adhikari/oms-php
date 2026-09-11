# SB-Tech OMS — developer entry points.
# Docs: AGENTS.md, docs/RULES.md. `make help` lists targets.

COMPOSER ?= composer
DB_HOST  ?= 127.0.0.1
DB_PORT  ?= 3306
DB_USER  ?= admin
DB_PASS  ?= admin

.PHONY: help up down build shell test check lint stan fix fresh-db dump-schema schema-dump schema-check hooks help-compose

help:
	@echo "SB-Tech OMS — make targets:"
	@echo "  make up          Start docker compose stack (app on :8080)"
	@echo "  make down        Stop the stack"
	@echo "  make shell       Shell into the app container"
	@echo "  make test        Run PHPUnit tests"
	@echo "  make stan        Run PHPStan"
	@echo "  make lint        Run rules linter (docs/RULES.md)"
	@echo "  make schema-check Verify docs/Schema.md matches the live DB"
	@echo "  make check       test + stan + lint + schema-check"
	@echo "  make fix         Auto-fix code style (PHP-CS-Fixer)"
	@echo "  make fresh-db    Rebuild disposable test DB (sb_tech_test) from migrations"
	@echo "  make dump-schema Refresh database/schema.sql from the dev DB"
	@echo "  make hooks       Enable local git hooks (pre-commit runs lint + stan)"

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

shell:
	docker compose exec app bash

test:
	$(COMPOSER) test

stan:
	$(COMPOSER) stan

lint:
	$(COMPOSER) lint

fix:
	$(COMPOSER) fix

schema-check:
	$(COMPOSER) schema:check

check:
	$(COMPOSER) check

fresh-db:
	DB_HOST=$(DB_HOST) DB_PORT=$(DB_PORT) DB_USER=$(DB_USER) DB_PASS=$(DB_PASS) \
		bash scripts/fresh_test_db.sh

dump-schema:
	bash scripts/dump_schema.sh

schema-dump: dump-schema

hooks:
	git config core.hooksPath scripts/githooks
	@echo "Git hooks enabled: pre-commit runs the rules linter + PHPStan on staged PHP files."
