<?php
class OrderScreenTest extends AdminovaTestCase {
	public function test_print_slips_list_each_order_and_hide_prices_from_order_editors(): void {
		$a = $this->makeOrder( 'processing', '10.00' );
		$b = $this->makeOrder( 'processing', '20.00' );
		$this->actingAsOrderEditor();
		$out = $this->render( function () use ( $a, $b ) {
			Adminova_Print::output( array( $a->get_id(), $b->get_id() ) );
		} );
		$this->assertStringContainsString( '#' . $a->get_order_number(), $out );
		$this->assertStringContainsString( '#' . $b->get_order_number(), $out );
		$this->assertStringNotContainsString( 'Total', $out );
	}

	public function test_print_slips_show_prices_to_shop_managers(): void {
		$a = $this->makeOrder( 'processing', '10.00' );
		$this->actingAs( 'shop_manager' );
		$out = $this->render( function () use ( $a ) {
			Adminova_Print::output( array( $a->get_id() ) );
		} );
		$this->assertStringContainsString( 'Total', $out );
	}

	public function test_csv_export_of_real_orders(): void {
		$a = $this->makeOrder( 'completed', '12.50', 'x@example.com' );
		$this->actingAs( 'shop_manager' );
		$h = fopen( 'php://memory', 'w+' );
		Adminova_Export::write( $h, array( $a->get_id() ), true );
		rewind( $h );
		$head = fgetcsv( $h, 0, ',', '"', '\\' );
		$row  = fgetcsv( $h, 0, ',', '"', '\\' );
		$this->assertSame( array( 'Total', 'Currency' ), array_slice( $head, -2 ) );
		$this->assertSame( 'x@example.com', $row[4] );
		$this->assertSame( '12.50', $row[ count( $row ) - 2 ] );
	}

	public function test_customer_history_counts_earlier_orders_by_email(): void {
		$this->makeOrder( 'completed', '30.00', 'repeat@example.com', time() - DAY_IN_SECONDS * 3 );
		$this->makeOrder( 'completed', '20.00', 'repeat@example.com', time() - DAY_IN_SECONDS );
		$current = $this->makeOrder( 'processing', '5.00', 'repeat@example.com' );
		$this->makeOrder( 'completed', '99.00', 'someone-else@example.com' );

		$this->actingAs( 'shop_manager' );
		$out = $this->render( function () use ( $current ) {
			Adminova_Customers::render( $current );
		} );
		$this->assertStringContainsString( '2 earlier orders', $out );
		$this->assertStringContainsString( '50.00', $out );
	}

	public function test_first_order_customer(): void {
		$o = $this->makeOrder( 'processing', '5.00', 'brand-new@example.com' );
		$this->actingAs( 'shop_manager' );
		$out = $this->render( function () use ( $o ) {
			Adminova_Customers::render( $o );
		} );
		$this->assertStringContainsString( 'First order', $out );
	}

	public function test_new_order_email_goes_out_once(): void {
		update_option( Adminova_Features::OPTION, array( 'notify' => true ) );
		Adminova::init();
		$sent = array();
		add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$sent ) {
			$sent[] = $atts;
			return true;
		}, 10, 2 );

		$o = $this->makeOrder( 'pending' );
		$o->update_status( 'processing' );
		$o->update_status( 'on-hold' );
		$o->update_status( 'processing' );

		$this->assertCount( 1, $sent );
		$this->assertStringContainsString( 'New order #' . $o->get_order_number(), $sent[0]['message'] );
	}

	public function test_cost_price_field_is_saved_with_the_product(): void {
		update_option( Adminova_Features::OPTION, array( 'profit' => true ) );
		$this->actingAs( 'shop_manager' );
		$p = $this->makeProduct();
		$_POST[ Adminova_Profit::META ] = '4,50';
		Adminova_Profit::save( $p->get_id() );
		$this->assertSame( '4.50', get_post_meta( $p->get_id(), Adminova_Profit::META, true ) );

		$_POST[ Adminova_Profit::META ] = '';
		Adminova_Profit::save( $p->get_id() );
		$this->assertSame( '', get_post_meta( $p->get_id(), Adminova_Profit::META, true ) );
	}
}
