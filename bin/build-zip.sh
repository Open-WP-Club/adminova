#!/bin/sh
# Builds adminova.zip (folder name "adminova/" inside) for Plugins > Add New > Upload.
set -e
cd "$(dirname "$0")/.."
rm -rf /tmp/adminova-build adminova.zip
mkdir -p /tmp/adminova-build/adminova
cp -R adminova.php uninstall.php includes assets languages README.md CHANGELOG.md /tmp/adminova-build/adminova/
(cd /tmp/adminova-build && zip -qr "$OLDPWD/adminova.zip" adminova)
rm -rf /tmp/adminova-build
echo "Built adminova.zip"
