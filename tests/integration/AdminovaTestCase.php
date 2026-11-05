<?php
class AdminovaRedirect extends RuntimeException {}

// Real WordPress + WooCommerce. Handlers end in wp_safe_redirect(); a filter turns that into an exception.
abstract class AdminovaTestCase extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		$_POST = array();
		$_GET  = array();
		delete_transient( 'adminova_stats' );
		delete_option( Adminova_Features::OPTION );
		delete_option( Adminova_Undo::OPTION );
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new AdminovaRedirect( $location );
			}
		);
		set_current_screen( 'dashboard' ); // is_admin() is true, like a real admin request
	}

	protected function actingAs( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	/** A user who may edit orders but has no access to reports or products. */
	protected function actingAsOrderEditor(): int {
		$id = $this->actingAs( 'subscriber' );
		$u  = new WP_User( $id );
		foreach ( array( 'edit_shop_orders', 'edit_others_shop_orders', 'read_shop_order', 'edit_shop_order', 'read' ) as $cap ) {
			$u->add_cap( $cap );
		}
		return $id;
	}

	protected function nonce( string $action ): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( $action );
		$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	}

	protected function makeOrder( string $status = 'processing', string $total = '50.00', string $email = 'anna@example.com', ?int $ts = null ): WC_Order {
		$o = wc_create_order();
		$o->set_billing_first_name( 'Anna' );
		$o->set_billing_last_name( 'Kovacheva' );
		$o->set_billing_email( $email );
		$o->set_total( $total );
		if ( $ts ) {
			$o->set_date_created( $ts );
		}
		$o->set_status( $status );
		$o->save();
		return $o;
	}

	protected function makeProduct( string $name = 'Blue Mug', int $stock = 1 ): WC_Product_Simple {
		$p = new WC_Product_Simple();
		$p->set_name( $name );
		$p->set_regular_price( '10' );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $stock );
		$p->save();
		return $p;
	}

	/** Runs a handler that ends in a redirect and returns the redirect URL. */
	protected function redirectOf( callable $fn ): string {
		try {
			$fn();
		} catch ( AdminovaRedirect $e ) {
			return $e->getMessage();
		}
		$this->fail( 'expected the handler to redirect' );
	}

	protected function render( callable $fn ): string {
		ob_start();
		$fn();
		return ob_get_clean();
	}
}
