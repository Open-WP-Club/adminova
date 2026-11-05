<?php
// Removes the per-user settings and the cached stats. Orders, products and everything else are untouched.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_metadata( 'user', 0, 'adminova_theme', '', true );
delete_metadata( 'user', 0, 'adminova_accent', '', true );
delete_metadata( 'user', 0, 'adminova_filters', '', true );
delete_option( 'adminova_features' );
delete_option( 'adminova_notify' );
delete_option( 'adminova_status_log' );
delete_transient( 'adminova_stats' );
// Product cost prices (_adminova_cost) are your data and are left in place.
