# Integration tests

Real WordPress + WooCommerce, run inside `wp-env`'s `tests-cli` container.

    npm run env:start
    composer test:integration

## If `wp-env start` stops after the database

On one machine (Docker 29, wp-env 10) `wp-env start` printed nothing, exited 0 after starting only MariaDB, and skipped its remaining steps. The suite was still run by finishing those steps by hand:

1. `docker compose -f ~/.wp-env/<hash>/docker-compose.yml up -d`
2. Put the WordPress test library (`tests/phpunit` from `WordPress/wordpress-develop`, same tag as the WordPress `wp-env` downloaded) into `~/.wp-env/<hash>/tests-WordPress-PHPUnit/tests/phpunit`.
3. Add a `wp-tests-config.php` there (DB `tests-wordpress`, user `root`, password `password`, host `tests-mysql`, `ABSPATH` `/var/www/html/`, table prefix `wptests_`).
4. Unzip `~/.wp-env/<hash>/woocommerce.latest-stable.zip` into `~/.wp-env/<hash>/woocommerce.latest-stable/` (the zip's inner `woocommerce/` folder contents).

None of this is committed: it lives in `~/.wp-env`. The CI job uses plain `wp-env start` and has not been run yet.
