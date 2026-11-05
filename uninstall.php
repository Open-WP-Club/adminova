<?php
// Removes the per-user settings and the cached stats. Orders, products and everything else are untouched.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_metadata( 'user', 0, 'adminova_theme', '', true );
delete_metadata( 'user', 0, 'adminova_accent', '', true );
delete_transient( 'adminova_stats' );
