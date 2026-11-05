.DEFAULT_GOAL := help
.DELETE_ON_ERROR:
# Targets share generated output; serialize aggregate invocations even with -j.
.NOTPARALLEL:
ifeq ($(OS),Windows_NT)
PYTHON ?= python
NPM ?= npm.cmd
COMPOSER ?= composer.cmd
else
PYTHON ?= python3
NPM ?= npm
COMPOSER ?= composer
endif
# COMPOSER names an executable here; Composer reserves its environment value for a manifest.
unexport COMPOSER
NODE ?= node
PHP ?= php
DOTNET ?= dotnet

WP ?= wp
WP_PATH ?= .build/runtime/wp-site
PORT ?= 8080
BUILD_DIR ?= build
PLUGIN_FILE := smart-hybrid-cache/smart-hybrid-cache.php
VERSION ?= $(shell "$(PYTHON)" tools/make_support.py version "$(PLUGIN_FILE)")
ZIP = $(BUILD_DIR)/smart-hybrid-cache.zip
VERSIONED_ZIP = $(BUILD_DIR)/smart-hybrid-cache-$(VERSION).zip

.PHONY: help all install dev check-deps version lint lint-php test validate set-version package build build-versioned release ci clean tree security

help: ## Show commands; defaults never install, build, bump or deploy
	@echo "Lolisoft-Smart-Hybryd-Cache-Redis-and-memcached - local development commands"
	@echo "  all                     Compatibility alias for a validated build"
	@echo "  install                 Verify PHP runtime (plugin has no Composer runtime dependencies)"
	@echo "  dev                     Serve an existing local WordPress site on loopback; set WP_PATH"
	@echo "  check-deps              Validate PHP runtime and required plugin files"
	@echo "  version                 Print current plugin version"
	@echo "  lint                    Check plugin/tests/tool PHP syntax"
	@echo "  lint-php                Compatibility alias for portable PHP syntax checks"
	@echo "  test                    Run release/admin regressions and memory/Redis/Memcached contracts"
	@echo "  validate                Validate package structure without external zip tools"
	@echo "  set-version             Explicitly set VERSION=X.Y.Z (never part of build)"
	@echo "  package                 Build the canonical WordPress ZIP and manifest in release/"
	@echo "  build                   Build and validate compatible build/smart-hybrid-cache.zip"
	@echo "  build-versioned         Also create the versioned build ZIP"
	@echo "  release                 Prepare both local archives only; no publishing"
	@echo "  ci                      Run complete tests then build local archives"
	@echo "  clean                   Remove only untracked build artifacts"
	@echo "  tree                    List tracked plugin source paths"
	@echo "  security                Run portable syntax and existing cache regressions"
	@echo "Override executable paths with PYTHON, NODE, NPM, PHP, COMPOSER or DOTNET. Run from the repository root."

all: build ## Compatibility alias for a validated build

install: ## Verify PHP runtime (plugin has no Composer runtime dependencies)
	"$(PHP)" --version

dev: ## Serve an existing local WordPress site on loopback; set WP_PATH
	"$(WP)" --path="$(WP_PATH)" server --host=127.0.0.1 --port="$(PORT)"

check-deps: ## Validate PHP runtime and required plugin files
	"$(PHP)" --version
	"$(PYTHON)" tools/make_support.py require "$(PLUGIN_FILE)" "smart-hybrid-cache/readme.txt"

version: ## Print current plugin version
	"$(PYTHON)" tools/make_support.py version "$(PLUGIN_FILE)"

lint: ## Check plugin/tests/tool PHP syntax
	"$(PHP)" tools/php-lint.php smart-hybrid-cache tests tools

lint-php: lint ## Compatibility alias for portable PHP syntax checks

test: ## Run release/admin regressions and memory/Redis/Memcached contracts
	"$(PHP)" tests/release-regressions.php
	"$(PHP)" tests/admin-regressions.php
	"$(PHP)" tests/run.php
	"$(PHP)" -r "putenv('SHC_TEST_ENGINE=redis'); require 'tests/run.php';"
	"$(PHP)" -r "putenv('SHC_TEST_ENGINE=memcached'); require 'tests/run.php';"

validate: ## Validate package structure without external zip tools
	"$(PHP)" tools/validate-package.php

set-version: ## Explicitly set VERSION=X.Y.Z (never part of build)
	"$(PHP)" tools/bump-plugin-version.php "$(VERSION)"

package: lint validate ## Build the canonical WordPress ZIP and manifest in release/
	"$(PYTHON)" tools/package-wordpress.py

build: package ## Build and validate compatible build/smart-hybrid-cache.zip
	"$(PYTHON)" tools/make_support.py copy-package "$(ZIP)"
	"$(PHP)" tools/validate-package.php "$(ZIP)"

build-versioned: build ## Also create the versioned build ZIP
	"$(PYTHON)" tools/make_support.py copy-package "$(VERSIONED_ZIP)"

release: build-versioned ## Prepare both local archives only; no publishing

ci: ## Run complete tests then build local archives
	"$(MAKE)" lint test validate
	"$(MAKE)" build

clean: ## Remove only untracked build artifacts
	"$(PYTHON)" tools/make_support.py clean "$(BUILD_DIR)"

tree: ## List tracked plugin source paths
	git ls-files smart-hybrid-cache

security: ## Run portable syntax and existing cache regressions
	"$(MAKE)" lint test
