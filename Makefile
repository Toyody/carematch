DOCKER_COMPOSE := docker compose

.PHONY: setup up down logs ps shell-backend shell-frontend psql migrate demo-seed test test-backend test-frontend test-e2e test-production-images lint analyse format build audit openapi-lint check

setup:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@test -f frontend/.env.local || cp frontend/.env.example frontend/.env.local
	$(DOCKER_COMPOSE) build
	$(DOCKER_COMPOSE) run --rm backend composer install --no-interaction --prefer-dist
	$(DOCKER_COMPOSE) run --rm backend php artisan key:generate --force
	$(DOCKER_COMPOSE) run --rm frontend npm ci
	$(DOCKER_COMPOSE) up -d postgres
	$(DOCKER_COMPOSE) run --rm backend php artisan migrate --force

up:
	$(DOCKER_COMPOSE) up -d --wait

down:
	$(DOCKER_COMPOSE) down

logs:
	$(DOCKER_COMPOSE) logs -f

ps:
	$(DOCKER_COMPOSE) ps

shell-backend:
	$(DOCKER_COMPOSE) exec backend sh

shell-frontend:
	$(DOCKER_COMPOSE) exec frontend sh

psql:
	$(DOCKER_COMPOSE) exec postgres psql -U carematch -d carematch

migrate:
	$(DOCKER_COMPOSE) run --rm backend php artisan migrate

demo-seed:
	@test -n "$$CARE_MATCH_DEMO_PASSWORD" || (echo "CARE_MATCH_DEMO_PASSWORD is required." >&2; exit 1)
	@$(DOCKER_COMPOSE) run --rm \
		-e CARE_MATCH_DEMO_DATA=true \
		-e CARE_MATCH_DEMO_EMAIL="$${CARE_MATCH_DEMO_EMAIL:-demo.admin@example.test}" \
		-e CARE_MATCH_DEMO_PASSWORD \
		backend php artisan db:seed --class=PortfolioDemoSeeder --force

test: test-backend test-frontend

test-backend:
	$(DOCKER_COMPOSE) run --rm backend php artisan test

test-frontend:
	$(DOCKER_COMPOSE) run --rm frontend npm test

test-e2e:
	./scripts/run-e2e.sh

test-production-images:
	./scripts/validate-production-images.sh

lint:
	$(DOCKER_COMPOSE) run --rm backend composer lint
	$(DOCKER_COMPOSE) run --rm frontend npm run lint
	$(DOCKER_COMPOSE) run --rm frontend npm run format:check

analyse:
	$(DOCKER_COMPOSE) run --rm backend composer analyse
	$(DOCKER_COMPOSE) run --rm frontend npm run typecheck

format:
	$(DOCKER_COMPOSE) run --rm backend composer format
	$(DOCKER_COMPOSE) run --rm frontend npm run format

build:
	$(DOCKER_COMPOSE) run --rm frontend npm run build

audit:
	$(DOCKER_COMPOSE) run --rm backend composer audit
	$(DOCKER_COMPOSE) run --rm frontend npm audit --omit=dev --audit-level=high
	$(DOCKER_COMPOSE) run --rm frontend npm audit --audit-level=critical

openapi-lint:
	docker run --rm -v "$(CURDIR)/openapi:/spec" redocly/cli:2.47.0 lint --config /spec/redocly.yaml /spec/openapi.yaml

check: lint analyse test build audit openapi-lint
