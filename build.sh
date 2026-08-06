#!/bin/bash
# Build the noarch package used by unraid-firewall.plg.

set -euo pipefail

VERSION="$(tr -d '\r\n' < VERSION)"
PKG_FILE="unraid-firewall-${VERSION}-noarch-1.txz"

rm -rf build
mkdir -p build

echo "Creating ${PKG_FILE}..."
tar --xz -cf "${PKG_FILE}" -C src .
sha256sum "${PKG_FILE}" > "${PKG_FILE}.sha256"

echo "Package: ${PKG_FILE}"
echo "SHA256: $(awk '{print $1}' "${PKG_FILE}.sha256")"
echo "Update the SHA256 in plugin/unraid-firewall.plg before publishing."
