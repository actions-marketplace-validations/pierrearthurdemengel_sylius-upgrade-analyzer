install:
	git config core.hooksPath hooks
	chmod +x hooks/prepare-commit-msg hooks/commit-msg hooks/pre-push
	composer install

test:
	vendor/bin/phpunit

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix

analyse:
	vendor/bin/phpstan analyse src --level=8

ci: analyse test lint

.PHONY: install test lint fix analyse ci
