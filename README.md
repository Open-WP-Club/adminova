# Adminova

Modern look and a one-screen overview (sales, orders by status, low stock) for the WordPress / WooCommerce admin. Skin only: no core changes, no database changes (just one per-user setting for dark mode).

## Install
Back up the site, then Plugins > Add New > Upload `adminova.zip` (build it with `bin/build-zip.sh`) and activate.

## If something looks wrong
- Deactivate the plugin: the stock admin comes back.
- Can't reach the admin? Add `define('ADMINOVA_DISABLE', true);` to `wp-config.php`.

## Dev
`php tests/run.php` runs the unit checks (no WordPress needed). Requires PHP 7.4+, WooCommerce 8+ (optional).
