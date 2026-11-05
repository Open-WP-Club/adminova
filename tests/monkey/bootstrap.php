<?php
// Brain Monkey bootstrap: no hand-written WordPress stubs here, each test mocks what it needs.
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ADMINOVA_URL', 'http://x/wp-content/plugins/adminova/' );
define( 'ADMINOVA_VERSION', 'test' );

foreach ( array( 'features', 'adminova', 'stats', 'bulk', 'settings', 'shortcuts', 'dashboard', 'alerts', 'print', 'export', 'filters', 'notes', 'restock', 'profit', 'customers', 'notify', 'undo' ) as $adminova_file ) {
	require dirname( __DIR__, 2 ) . "/includes/class-$adminova_file.php";
}
require __DIR__ . '/MonkeyTestCase.php';
