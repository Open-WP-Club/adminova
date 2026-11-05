<?php
use Brain\Monkey\Functions;

final class PrintExportTest extends MonkeyTestCase {
	private array $orders = array();

	protected function setUp(): void {
		parent::setUp();
		$this->orders = array();
		Functions\when( 'wc_get_order' )->alias( fn( $id ) => $this->orders[ $id ] ?? false );
		Functions\when( 'wc_format_datetime' )->justReturn( '2026-11-05' );
		Functions\when( 'wc_get_order_status_name' )->alias( fn( $s ) => ucfirst( $s ) );
	}

	private function item( string $name, int $qty ) {
		$i = Mockery::mock( 'WC_Order_Item_Product' )->shouldIgnoreMissing();
		$i->shouldReceive( 'get_name' )->andReturn( $name );
		$i->shouldReceive( 'get_quantity' )->andReturn( $qty );
		return $i;
	}

	private function addOrder( int $id, string $name = 'Anna', string $type = 'shop_order' ): void {
		$o = $this->order(
			$id,
			$type,
			array(
				'get_order_number'          => (string) $id,
				'get_billing_first_name'    => $name,
				'get_billing_last_name'     => 'K',
				'get_billing_email'         => 'a@example.com',
				'get_billing_phone'         => '',
				'get_formatted_billing_address'  => '',
				'get_formatted_shipping_address' => '',
				'get_customer_note'         => '',
				'get_total'                 => '12.50',
				'get_currency'              => 'EUR',
				'get_status'                => 'processing',
				'get_items'                 => array( $this->item( 'Mug', 2 ) ),
				'get_order_item_totals'     => array(
					array(
						'label' => 'Total:',
						'value' => 'SECRET-TOTAL',
					),
				),
				'get_formatted_line_subtotal' => 'SECRET-LINE',
			)
		);
		$this->orders[ $id ] = $o;
	}

	private function slips( array $ids ): string {
		ob_start();
		Adminova_Print::output( $ids );
		return ob_get_clean();
	}

	public function test_slips_only_for_orders_the_user_may_edit(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 1 );
		$this->addOrder( 2 );
		Functions\when( 'current_user_can' )->alias( fn( $cap, $id = 0 ) => 'edit_shop_order' === $cap && 1 === $id );

		$out = $this->slips( array( 1, 2 ) );
		$this->assertStringContainsString( '#1', $out );
		$this->assertStringNotContainsString( '#2', $out );
	}

	public function test_slips_skip_refunds_and_missing_orders(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 3, 'Anna', 'shop_order_refund' );
		$this->assertSame( '', trim( $this->slips( array( 3, 99 ) ) ) );
	}

	public function test_slips_hide_prices_without_the_reports_capability(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 1 );
		$out = $this->slips( array( 1 ) );
		$this->assertStringContainsString( 'Mug', $out );
		$this->assertStringNotContainsString( 'SECRET', $out );
		$this->assertStringNotContainsString( 'Total', $out );
	}

	public function test_slips_show_prices_with_the_reports_capability(): void {
		$this->can( 'edit_shop_order', 'view_woocommerce_reports' );
		$this->addOrder( 1 );
		$out = $this->slips( array( 1 ) );
		$this->assertStringContainsString( 'SECRET-LINE', $out );
		$this->assertStringContainsString( 'SECRET-TOTAL', $out );
	}

	private function csv( array $ids, bool $reports ): array {
		$h = fopen( 'php://memory', 'w+' );
		Adminova_Export::write( $h, $ids, $reports );
		rewind( $h );
		$rows = array();
		while ( false !== ( $r = fgetcsv( $h, 0, ',', '"', '\\' ) ) ) {
			$rows[] = $r;
		}
		return $rows;
	}

	public function test_csv_has_one_row_per_allowed_order_plus_a_header(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 1 );
		$this->addOrder( 2 );
		Functions\when( 'current_user_can' )->alias( fn( $cap, $id = 0 ) => 2 === $id );

		$rows = $this->csv( array( 1, 2 ), false );
		$this->assertCount( 2, $rows );
		$this->assertSame( '2', $rows[1][0] );
		$this->assertSame( 'Mug x2', $rows[1][8] );
	}

	public function test_csv_has_no_money_columns_without_the_reports_capability(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 1 );
		$rows = $this->csv( array( 1 ), false );
		$this->assertNotContains( 'Total', $rows[0] );
		$this->assertNotContains( '12.50', $rows[1] );
	}

	public function test_csv_has_money_columns_with_the_reports_capability(): void {
		$this->can( 'edit_shop_order', 'view_woocommerce_reports' );
		$this->addOrder( 1 );
		$rows = $this->csv( array( 1 ), true );
		$this->assertSame( array( 'Total', 'Currency' ), array_slice( $rows[0], -2 ) );
		$this->assertSame( array( '12.50', 'EUR' ), array_slice( $rows[1], -2 ) );
	}

	public function test_csv_defuses_spreadsheet_formulas_in_customer_supplied_fields(): void {
		$this->can( 'edit_shop_order' );
		$this->addOrder( 1, '=HYPERLINK("http://evil")' );
		$rows = $this->csv( array( 1 ), false );
		$this->assertStringStartsWith( "'=", $rows[1][3] );
	}
}
