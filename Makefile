.PHONY: install migrate seed test analyse lint mutation check up down relay

install:
	composer install
	git submodule update --init

migrate:
	php artisan migrate --force

seed:
	php artisan db:seed --force

test:
	php artisan test

analyse:
	vendor/bin/phpstan analyse

lint:
	vendor/bin/pint --test

mutation:
	vendor/bin/infection --min-msi=80 --threads=4

# The full local gate, mirroring php-service-ci.yml.
check: lint analyse test

up:
	docker compose up -d --build

down:
	docker compose down -v

relay:
	php artisan outbox:relay
