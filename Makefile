.PHONY: help setup test test-coverage lint fix static architecture dependencies refactor mutation validate audit check qa artifacts release-preview release-preview-test release-verify

help:
	@printf '%s\n' 'Use Composer as the canonical developer interface:' '  composer setup qa test test:coverage lint fix static architecture dependencies refactor:check rector:fix mutation validate audit release:preview release:verify'

setup:
	composer install

test:
	composer test

test-coverage:
	composer test:coverage

lint:
	composer lint

fix:
	composer fix

static:
	composer static

architecture:
	composer architecture

dependencies:
	composer dependencies

refactor:
	composer refactor:check

mutation:
	composer mutation

validate:
	composer validate:metadata

audit:
	composer audit

check:
	composer check

qa:
	composer qa

artifacts:
	composer artifacts

release-preview:
	composer release:preview

release-preview-test:
	composer release:preview:test

release-verify:
	composer release:verify
