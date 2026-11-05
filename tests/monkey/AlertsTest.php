<?php
use Brain\Monkey\Functions;

final class AlertsTest extends MonkeyTestCase {
	private array $queries = array();

	private function newOrder( int $ts ) {
		$when = Mockery::mock()->shouldIgnoreMissing();
		$when->shouldReceive( 'getTimestamp' )->andReturn( $ts );
		return $this->order(
			1,
			'shop_order',
			array(
				'get_date_created'       => $when,
				'get_order_number'       => '1',
				'get_billing_first_name' => 'Anna',
				'get_billing_last_name'  => 'K',
				'get_total'              => '9.00',
				'get_currency'           => 'EUR',
				'get_edit_order_url'     => 'http://x/o/1',
			)
		);
	}

	private function stubOrders( array $orders ): void {
		Functions\when( 'wc_get_orders' )->alias(
			function ( $args ) use ( $orders ) {
				$this->queries[] = $args;
				return $orders;
			}
		);
		Functions\when( 'wc_price' )->alias( fn( $v ) => '<span>' . $v . ' EUR</span>' );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->queries = array();
	}

	public function test_ignores_requests_that_do_not_carry_the_marker(): void {
		$this->stubOrders( array() );
		$this->can( 'edit_shop_orders' );
		$this->assertSame( array( 'x' => 1 ), Adminova_Alerts::received( array( 'x' => 1 ), array() ) );
		$this->assertSame( array(), $this->queries );
	}

	public function test_ignores_users_who_cannot_edit_orders(): void {
		$this->stubOrders( array() );
		$this->assertSame( array(), Adminova_Alerts::received( array(), array( 'adminova_since' => time() ) ) );
		$this->assertSame( array(), $this->queries, 'no order query at all' );
	}

	public function test_returns_new_orders_and_advances_the_cursor(): void {
		$since = time() - 100;
		$this->stubOrders( array( $this->newOrder( $since + 40 ) ) );
		$this->can( 'edit_shop_orders' );

		$r = Adminova_Alerts::received( array(), array( 'adminova_since' => $since ) );
		$this->assertSame( $since + 40, $r['adminova_new']['since'] );
		$this->assertSame( '1', $r['adminova_new']['orders'][0]['number'] );
		$this->assertSame( 'Anna K', $r['adminova_new']['orders'][0]['name'] );
		$this->assertSame( '>' . $since, $this->queries[0]['date_created'] );
		$this->assertSame( array( 'processing', 'on-hold' ), $this->queries[0]['status'] );
	}

	public function test_amount_only_goes_to_report_viewers(): void {
		$since = time() - 100;
		$this->stubOrders( array( $this->newOrder( $since + 1 ) ) );

		$this->can( 'edit_shop_orders' );
		$r = Adminova_Alerts::received( array(), array( 'adminova_since' => $since ) );
		$this->assertSame( '', $r['adminova_new']['orders'][0]['total'] );

		$this->can( 'edit_shop_orders', 'view_woocommerce_reports' );
		$r = Adminova_Alerts::received( array(), array( 'adminova_since' => $since ) );
		$this->assertSame( '9.00 EUR', $r['adminova_new']['orders'][0]['total'] );
	}

	public function test_a_stale_cursor_cannot_make_it_scan_old_history(): void {
		$this->stubOrders( array() );
		$this->can( 'edit_shop_orders' );
		Adminova_Alerts::received( array(), array( 'adminova_since' => 5 ) );
		$queried = (int) ltrim( $this->queries[0]['date_created'], '>' );
		$this->assertGreaterThan( time() - 10, $queried );
	}
}
