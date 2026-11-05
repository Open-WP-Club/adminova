<?php
use Brain\Monkey\Functions;

final class CustomersTest extends MonkeyTestCase {
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wc_price' )->alias( fn( $v ) => "PRICE($v)" );
		Functions\when( 'wp_date' )->justReturn( '5 Nov 2026' );
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => 'date_format' === $k ? 'j M Y' : $d );
		Functions\when( '_n' )->alias( fn( $one, $many, $n ) => 1 === $n ? $one : $many );
	}

	private function previous( float $total, int $ts ) {
		$when = Mockery::mock()->shouldIgnoreMissing();
		$when->shouldReceive( 'getTimestamp' )->andReturn( $ts );
		return $this->order( 1, 'shop_order', array( 'get_total' => (string) $total, 'get_date_created' => $when ) );
	}

	private function render( $order ): string {
		ob_start();
		Adminova_Customers::render( $order );
		return ob_get_clean();
	}

	public function test_orders_without_an_email_say_so(): void {
		$o = $this->order( 1, 'shop_order', array( 'get_billing_email' => '' ) );
		$this->assertStringContainsString( 'No email on this order.', $this->render( $o ) );
	}

	public function test_first_time_customer(): void {
		Functions\when( 'wc_get_orders' )->justReturn( array() );
		$o = $this->order( 1, 'shop_order', array( 'get_billing_email' => 'a@example.com' ) );
		$this->assertStringContainsString( 'First order from this customer.', $this->render( $o ) );
	}

	public function test_returning_customer_amounts_need_the_reports_capability(): void {
		Functions\when( 'wc_get_orders' )->justReturn( array( $this->previous( 10, 100 ), $this->previous( 5.5, 300 ) ) );
		$o = $this->order( 9, 'shop_order', array( 'get_billing_email' => 'a@example.com' ) );

		$out = $this->render( $o );
		$this->assertStringContainsString( '2 earlier orders', $out );
		$this->assertStringContainsString( 'Last order: 5 Nov 2026', $out );
		$this->assertStringNotContainsString( 'PRICE(', $out );

		$this->can( 'view_woocommerce_reports' );
		$this->assertStringContainsString( 'PRICE(15.5)', $this->render( $o ) );
	}

	public function test_excludes_the_order_being_viewed_from_the_history(): void {
		$seen = null;
		Functions\when( 'wc_get_orders' )->alias(
			function ( $args ) use ( &$seen ) {
				$seen = $args;
				return array();
			}
		);
		$this->render( $this->order( 9, 'shop_order', array( 'get_billing_email' => 'a@example.com' ) ) );
		$this->assertSame( array( 9 ), $seen['exclude'] );
		$this->assertSame( 'a@example.com', $seen['billing_email'] );
		$this->assertSame( Adminova_Customers::LIMIT, $seen['limit'] );
	}
}
