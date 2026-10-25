.PHONY: help setup test test-coverage lint fix static architecture dependencies collisions refactor refactor-fix mutation validate audit check qa consumer-bundle consumer-faker consumer-personas artifacts release-preview release-preview-test release-verify release-publish graphify-check graphify-install-ollama graphify-code-only graphify-extract graphify-update graphify-query graphify-path graphify-explain serena-check serena-init serena-setup-codex serena-index serena-upgrade serena-activate

GRAPHIFY_BACKEND ?= ollama
GRAPHIFY_MODEL ?= qwen3:8b
GRAPHIFY_MAX_CONCURRENCY ?= 1

help:
	@printf '%s\n' 'Use Composer as the canonical developer interface:' '  composer setup qa test test:coverage lint fix static architecture dependencies collisions refactor:check refactor:fix mutation consumer:bundle validate audit release:preview release:verify' 'Graphify:' '  make graphify-check' '  make graphify-install-ollama' '  make graphify-code-only' '  make graphify-extract [GRAPHIFY_BACKEND=ollama GRAPHIFY_MODEL=qwen3:8b]' '  make graphify-update' '  make graphify-query QUESTION="..."' '  make graphify-path FROM="..." TO="..."' '  make graphify-explain NODE="..."' 'Serena:' '  make serena-check' '  make serena-init                  # one-time machine setup' '  make serena-setup-codex           # writes ~/.codex/config.toml' '  make serena-index                 # index this repository' '  make serena-upgrade' '  make serena-activate              # prompt to use in Codex'

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

refactor-fix:
	composer rector:fix

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

release-publish:
	composer release:publish -- --dry-run

graphify-check:
	@command -v graphify >/dev/null || { echo 'graphify is not installed or not on PATH' >&2; exit 1; }
	@graphify --version

graphify-install-ollama:
	@command -v uv >/dev/null || { echo 'uv is required to install the Graphify Ollama extra' >&2; exit 1; }
	uv tool install "graphifyy[ollama]" --force

graphify-code-only: graphify-check
	graphify extract . --code-only

graphify-extract: graphify-check
	graphify extract . --backend "$(GRAPHIFY_BACKEND)" --model "$(GRAPHIFY_MODEL)" --max-concurrency "$(GRAPHIFY_MAX_CONCURRENCY)"

graphify-update: graphify-check
	graphify update .

graphify-query: graphify-check
	@test -n "$(QUESTION)" || { echo 'Usage: make graphify-query QUESTION="your question"' >&2; exit 2; }
	graphify query "$(QUESTION)"

graphify-path: graphify-check
	@test -n "$(FROM)" -a -n "$(TO)" || { echo 'Usage: make graphify-path FROM="Source" TO="Target"' >&2; exit 2; }
	graphify path "$(FROM)" "$(TO)"

graphify-explain: graphify-check
	@test -n "$(NODE)" || { echo 'Usage: make graphify-explain NODE="Symbol"' >&2; exit 2; }
	graphify explain "$(NODE)"

serena-check:
	@command -v serena >/dev/null || { echo 'serena is not installed or not on PATH; install it with: uv tool install -p 3.13 serena-agent' >&2; exit 1; }
	@serena --version

serena-init: serena-check
	serena init

serena-setup-codex: serena-check
	@echo 'This updates the user-level Codex configuration (~/.codex/config.toml).'
	serena setup codex

serena-index: serena-check
	serena project index .

serena-upgrade:
	@command -v uv >/dev/null || { echo 'uv is required to upgrade Serena' >&2; exit 1; }
	uv tool upgrade serena-agent

serena-activate:
	@printf '%s\n' 'In a new Codex session, say:' '  Activate the current dir as project using Serena and read its initial instructions.'
