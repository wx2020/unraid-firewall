# Makefile for the Unraid Firewall plugin.

VERSION := $(shell tr -d '\r\n' < VERSION)
PKG_NAME := unraid-firewall-$(VERSION)-noarch-1
PKG_FILE := $(PKG_NAME).txz
BUILD_DIR := build

.PHONY: all package clean

all: package

package:
	@echo "Building $(PKG_FILE)..."
	@rm -rf "$(BUILD_DIR)"
	@mkdir -p "$(BUILD_DIR)"
	@tar --xz -cf "$(PKG_FILE)" -C src .
	@sha256sum "$(PKG_FILE)" > "$(PKG_FILE).sha256"
	@echo "Package: $(PKG_FILE)"
	@echo "SHA256: $$(awk '{print $$1}' "$(PKG_FILE).sha256")"

clean:
	@rm -rf "$(BUILD_DIR)"
	@rm -f "$(PKG_FILE)" "$(PKG_FILE).sha256"
