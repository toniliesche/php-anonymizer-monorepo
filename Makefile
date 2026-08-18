.PHONY: help setup test test-coverage lint fix static architecture dependencies collisions refactor mutation validate audit check qa consumer-bundle consumer-faker consumer-personas artifacts release-preview release-preview-test release-verify

help:
	@printf '%s\n' 'Use Composer as the canonical developer interface:' '  composer setup qa test test:coverage lint fix static architecture dependencies collisions refactor:check rector:fix mutation consumer:bundle validate audit release:preview release:verify'

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

collisions:
	composer collisions

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

consumer-bundle:
	composer consumer:bundle

consumer-faker:
	composer consumer:faker

consumer-personas:
	composer consumer:personas

artifacts:
	composer artifacts

release-preview:
	composer release:preview

release-preview-test:
	composer release:preview:test

release-verify:
	composer release:verify
