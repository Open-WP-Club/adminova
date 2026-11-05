# Adminova

Modern look and a one-screen overview (sales, orders by status, low stock) for the WordPress / WooCommerce admin. Skin only: no core changes, no database changes (just one per-user setting for dark mode).

## Features
Every extra can be switched on/off in Settings > Adminova; a switched-off extra registers no hooks at all.

- Modern look with dark mode (default) and per-user accent colour; mobile-friendly layout
- Dashboard card: sales vs yesterday, 7-day chart, latest orders, status links, low stock, quick actions
- Ctrl/Cmd+K palette: jump to admin pages, search orders and products
- New-order pop-up with a beep (checks every 30s while an admin page is open)
- Orders list: bulk "Print order slips" and "Export to CSV", saved filters
- Dashboard: quick order note, "Restock" button on low-stock products
- Profit (off by default): cost price field on products, Profit tile on the dashboard
- Customer history box on the order screen
- New-order email / Telegram message (off by default)
- Log of recent order status changes with Undo (Settings > Adminova)

## Install
Back up the site, then Plugins > Add New > Upload `adminova.zip` (build it with `bin/build-zip.sh`) and activate.

## If something looks wrong
- Deactivate the plugin: the stock admin comes back.
- Can't reach the admin? Add `define('ADMINOVA_DISABLE', true);` to `wp-config.php`.

## Dev
Three layers of tests, from fastest to most realistic:

| Command | What it runs | Needs |
|---|---|---|
| `composer test:unit` | pure logic (parsing, sanitising, maths) with a few hand-written stubs | PHP 8.3+ |
| `composer test:monkey` | handlers, permissions and rendering with WordPress/WooCommerce mocked (Brain Monkey + Mockery) | PHP 8.3+ |
| `composer test:integration` | the same flows against real WordPress + WooCommerce in `wp-env` | Docker, Node |

`composer test` runs the first two. Also `composer lint` (PHPCS, WordPress standards) and `composer analyse` (PHPStan).
First time: `composer install && npm install`, then `npm run env:start` before `composer test:integration`.

The plugin itself needs PHP 7.4+; the dev tools need PHP 8.3+. WooCommerce 8+ is optional.

Layout: `adminova.php` (bootstrap), `includes/` (one class per file), `assets/`, `tests/{unit,monkey,integration}`, `languages/`, `uninstall.php`.
