# Adminova Admin Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A standalone WordPress plugin that gives the stock WP/WooCommerce admin a modern look and a one-screen dashboard of orders and stats, without changing core behavior.

**Architecture:** Skin-only plugin. It adds a body class, enqueues one CSS file that overrides the stock admin via CSS variables, replaces the dashboard widgets with a single "Overview" widget fed by WooCommerce APIs (cached), and adds quick-link shortcuts. No core files, no DB schema, no template overrides, so deactivating the plugin restores the original admin.

**Tech Stack:** PHP 7.4+, WordPress 6.x, WooCommerce 8+ (HPOS compatible), plain CSS and ~1 small JS file, no external libraries or fonts. Dev-only: `@wordpress/env` (Docker) for a local test site, PHPUnit via wp-env for tests.

**Spec:** No separate spec; requirements come from the brainstorming conversation (2026-11-05):
restyle existing WP admin (not a new app), modern/optimized look, quick access to orders and stats on one screen, extras welcome, must not break the system, user will test afterwards, no staging site exists.

## Global Constraints

- Plugin lives at repo root: main file `adminova.php`, text domain `adminova`, prefix `adminova_`.
- Never modify WP/WooCommerce core or other plugins; only hooks and CSS.
- Kill switch: defining `ADMINOVA_DISABLE` as true in `wp-config.php` makes the plugin load nothing.
- Works if WooCommerce is inactive (skin still applies; Woo widgets are skipped).
- Declare HPOS compatibility; read orders only via `wc_get_orders()` / `wc_get_order()`, never `wp_posts` queries.
- No external requests (fonts, CDNs); system font stack only.
- Assets load only in wp-admin, and only for users with `edit_posts` or higher.
- Cached stats use transients with a 5-minute TTL.
- UI strings are translatable and the Bulgarian (`bg_BG`) locale must render without overflow.

## Review Focus

- Store with zero orders: stats show 0 and the empty state, no PHP warnings or division by zero in "vs yesterday".
- WooCommerce deactivated: no fatals, Woo widget not registered.
- Shop manager (not admin) role: sees the dashboard and quick links, no permission errors.
- Store with tens of thousands of orders: dashboard stays fast because of aggregate queries plus the cache, not loading all orders.
- Dark mode choice persists per user and does not flash light on page load.

---

### Task 1: Plugin skeleton, kill switch, local test site

**Files:**
- Create: `adminova.php`, `includes/class-adminova.php`, `.wp-env.json`, `package.json`, `tests/test-bootstrap.php`
- Test: `tests/test-bootstrap.php`

**Interfaces:**
- Produces: `Adminova::init(): void` (hooks everything; no-op when `ADMINOVA_DISABLE`), constant `ADMINOVA_VERSION`, `ADMINOVA_PATH`, `ADMINOVA_URL`.

- [ ] **Step 1:** Write test `test_init_registers_nothing_when_disabled` (defines `ADMINOVA_DISABLE` true, asserts `has_action('admin_enqueue_scripts', ...)` is false) and `test_init_registers_assets_hook_by_default`.
- [ ] **Step 2:** Run `npx wp-env run tests-cli --env-cwd=wp-content/plugins/adminova vendor/bin/phpunit` and confirm FAIL (class missing).
- [ ] **Step 3:** Implement `adminova.php` (header, constants, HPOS `before_woocommerce_init` compat declaration) and `Adminova::init()`.
- [ ] **Step 4:** Rerun tests, expect PASS; run `npx wp-env start` and confirm the plugin activates with no notices.
- [ ] **Step 5:** Commit `feat: plugin skeleton with kill switch`.

### Task 2: Design tokens and admin restyle

**Files:**
- Create: `assets/admin.css`
- Modify: `includes/class-adminova.php` (add `enqueue_assets()` and `admin_body_class()` filter)

**Interfaces:**
- Consumes: `Adminova::init()`.
- Produces: body class `adminova`; CSS variables `--adminova-bg`, `--adminova-surface`, `--adminova-text`, `--adminova-accent`, `--adminova-radius`; dark variant under `body.adminova-dark`.

- [ ] **Step 1:** Test `test_body_class_added_for_admin_users` and `test_assets_not_enqueued_on_frontend`.
- [ ] **Step 2:** Run, confirm FAIL.
- [ ] **Step 3:** Implement hooks. In `admin.css` scope every rule under `body.adminova`; restyle sidebar menu, top bar, buttons, inputs, list tables (spacing, zebra off, hover row), notices, and the Woo orders list status pills. System font stack, no images.
- [ ] **Step 4:** Rerun tests, expect PASS. Manually check Orders, Products, Settings, Plugins pages in wp-env for no broken layouts (screenshots into `docs/screens/`).
- [ ] **Step 5:** Commit `feat: modern admin skin`.

### Task 3: Overview dashboard widget

**Files:**
- Create: `includes/class-stats.php`, `includes/class-dashboard.php`
- Test: `tests/test-stats.php`

**Interfaces:**
- Produces: `Adminova_Stats::get(): array` returning `['today' => ['orders' => int, 'revenue' => float], 'yesterday' => [...], 'delta_pct' => ?float (null when yesterday revenue is 0), 'by_status' => array<string,int>, 'low_stock' => array<int,array{id:int,name:string,qty:int}>]`; `Adminova_Stats::flush(): void`; `Adminova_Dashboard::register(): void` (removes default widgets, adds one).

- [ ] **Step 1:** Tests: `test_zero_orders_returns_zeros_and_null_delta`, `test_counts_today_and_yesterday_separately`, `test_result_is_cached_for_300_seconds`, `test_cache_flushed_on_new_order` (hook `woocommerce_new_order`, `woocommerce_order_status_changed`).
- [ ] **Step 2:** Run, confirm FAIL.
- [ ] **Step 3:** Implement `get()` using `wc_get_orders` with date range and `return => 'ids'` / counts; revenue from the totals of paid statuses only (`processing`, `completed`, `on-hold` excluded from revenue). Low stock threshold from the Woo option `woocommerce_notify_low_stock_amount`.
- [ ] **Step 4:** Rerun tests, expect PASS. Render in wp-env with seeded orders (WP-CLI `wc` generate script in `tests/seed.sh`).
- [ ] **Step 5:** Commit `feat: overview dashboard widget`.

### Task 4: Quick access links

**Files:**
- Create: `includes/class-shortcuts.php`

**Interfaces:**
- Consumes: `Adminova_Stats::get()['by_status']`.
- Produces: `Adminova_Shortcuts::register(): void` adding an admin-bar group "Orders" with counts for Processing / On hold / Pending; each link opens the filtered orders list, respecting HPOS vs legacy URL (`wc_get_page_screen_id` / `OrderUtil::custom_orders_table_usage_is_enabled`).

- [ ] **Step 1:** Test `test_links_use_hpos_url_when_enabled` and `test_group_hidden_without_manage_woocommerce_cap`.
- [ ] **Step 2:** Run, confirm FAIL.
- [ ] **Step 3:** Implement on `admin_bar_menu`.
- [ ] **Step 4:** Rerun, expect PASS.
- [ ] **Step 5:** Commit `feat: orders quick links in admin bar`.

### Task 5: Dark mode toggle

**Files:**
- Create: `assets/admin.js`
- Modify: `includes/class-adminova.php` (admin-bar toggle button, `wp_ajax_adminova_set_theme`, body class from user meta server-side)

**Interfaces:**
- Produces: user meta `adminova_theme` in `{light, dark}`; AJAX action `adminova_set_theme` with nonce `adminova_theme`.

- [ ] **Step 1:** Tests `test_theme_saved_to_user_meta`, `test_invalid_theme_value_rejected`, `test_ajax_requires_valid_nonce`, `test_dark_class_rendered_server_side` (no flash on load).
- [ ] **Step 2:** Run, confirm FAIL.
- [ ] **Step 3:** Implement handler (sanitize to the two values, check nonce and `is_user_logged_in`) and JS that toggles the class then posts the choice.
- [ ] **Step 4:** Rerun, expect PASS.
- [ ] **Step 5:** Commit `feat: per-user dark mode`.

### Task 6: Safety and performance pass, release zip

**Files:**
- Create: `README.md` (install, kill switch, rollback), `bin/build-zip.sh`

- [ ] **Step 1:** Run with `WP_DEBUG` on across all admin screens listed in Task 2; zero PHP notices expected.
- [ ] **Step 2:** Check Query Monitor on the dashboard: Adminova adds no more than 3 queries on a cached load; CSS under 40 KB, JS under 3 KB.
- [ ] **Step 3:** Test with Woo deactivated and with a shop-manager user (Review Focus items 2 and 3).
- [ ] **Step 4:** `bin/build-zip.sh` produces `adminova.zip` without dev files.
- [ ] **Step 5:** Commit `chore: docs and release build`.

### Your testing (after delivery)

1. Back up the live site (files + DB) first.
2. Upload the zip via Plugins, Add New, activate. If anything looks off, deactivate or set `ADMINOVA_DISABLE` in `wp-config.php`.
3. Check: Orders, Products, one order edit, Settings, and any plugin you rely on.
