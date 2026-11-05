<?php
use Brain\Monkey\Functions;

final class DashboardRenderTest extends MonkeyTestCase {
	protected function setUp(): void {
		parent::setUp();
		$day = fn( $label, $rev ) => array(
			'label'   => $label,
			'orders'  => 2,
			'revenue' => $rev,
			'profit'  => 30.0,
			'missing' => 1,
		);
		$stats = array(
			'today'     => $day( 'Thu', 123.0 ),
			'yesterday' => $day( 'Wed', 100.0 ),
			'delta_pct' => 23.0,
			'week'      => array( $day( 'Mon', 10.0 ), $day( 'Tue', 20.0 ) ),
			'by_status' => array(
				'processing' => 3,
				'completed'  => 0,
			),
			'recent'    => array(
				array(
					'number'   => '55',
					'name'     => 'Anna K',
					'total'    => 77.0,
					'currency' => 'EUR',
					'status'   => 'processing',
					'url'      => 'http://x/o/55',
				),
			),
			'low_stock' => array(
				array(
					'id'   => 12,
					'name' => 'Blue Mug',
					'qty'  => 1,
				),
			),
		);
		Functions\when( 'get_transient' )->justReturn( $stats );
		Functions\when( 'wc_price' )->alias( fn( $v ) => 'PRICE(' . $v . ')' );
		Functions\when( 'wc_get_order_status_name' )->alias( fn( $s ) => ucfirst( $s ) );
		Functions\when( 'get_edit_post_link' )->justReturn( 'http://x/edit/12' );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
	}

	private function render(): string {
		ob_start();
		Adminova_Dashboard::render();
		return ob_get_clean();
	}

	public function test_order_editors_without_reports_do_not_see_money(): void {
		$this->can( 'edit_shop_orders' );
		$out = $this->render();
		$this->assertStringContainsString( 'Orders today', $out );
		$this->assertStringNotContainsString( 'Sales today', $out );
		$this->assertStringNotContainsString( 'PRICE(', $out, 'no amounts anywhere: tiles, chart or latest orders' );
		$this->assertStringNotContainsString( 'adminova-chart', $out );
		$this->assertStringNotContainsString( 'vs yesterday', $out );
	}

	public function test_report_viewers_see_sales_chart_and_order_totals(): void {
		$this->can( 'edit_shop_orders', 'view_woocommerce_reports' );
		$out = $this->render();
		$this->assertStringContainsString( 'Sales today', $out );
		$this->assertStringContainsString( 'PRICE(123)', $out );
		$this->assertStringContainsString( 'adminova-chart', $out );
		$this->assertStringContainsString( 'PRICE(77)', $out );
		$this->assertStringContainsString( '+23.0% vs yesterday', $out );
	}

	public function test_profit_tile_needs_the_feature_and_the_reports_capability(): void {
		$this->can( 'edit_shop_orders', 'view_woocommerce_reports' );
		$this->assertStringNotContainsString( 'Profit today', $this->render(), 'feature is off by default' );

		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => Adminova_Features::OPTION === $k ? array( 'profit' => true ) : $d );
		$out = $this->render();
		$this->assertStringContainsString( 'Profit today', $out );
		$this->assertStringContainsString( '1 items have no cost price', $out );

		$this->can( 'edit_shop_orders' );
		$this->assertStringNotContainsString( 'Profit today', $this->render() );
	}

	public function test_only_non_empty_statuses_are_listed(): void {
		$this->can( 'edit_shop_orders' );
		$out = $this->render();
		$this->assertStringContainsString( 'Processing', $out );
		$this->assertStringNotContainsString( 'Completed', $out );
	}

	public function test_low_stock_needs_the_products_capability(): void {
		$this->can( 'edit_shop_orders' );
		$this->assertStringNotContainsString( 'Blue Mug', $this->render() );

		$this->can( 'edit_shop_orders', 'edit_products' );
		$this->assertStringContainsString( 'Blue Mug', $this->render() );
	}

	public function test_restock_button_follows_the_feature_switch_and_the_product_capability(): void {
		$this->can( 'edit_shop_orders', 'edit_products', 'edit_product' );
		$this->assertStringContainsString( 'adminova_restock', $this->render() );

		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => Adminova_Features::OPTION === $k ? array( 'restock' => false ) : $d );
		$this->assertStringNotContainsString( 'adminova_restock', $this->render() );
	}

	public function test_customer_supplied_text_is_escaped(): void {
		$this->can( 'edit_shop_orders' );
		Functions\when( 'get_transient' )->alias(
			function () {
				$s                      = array(
					'today'     => array( 'orders' => 0, 'revenue' => 0.0, 'profit' => 0.0, 'missing' => 0 ),
					'yesterday' => array( 'orders' => 0, 'revenue' => 0.0, 'profit' => 0.0, 'missing' => 0 ),
					'delta_pct' => null,
					'week'      => array(),
					'by_status' => array(),
					'low_stock' => array(),
					'recent'    => array( array( 'number' => '1', 'name' => '<script>alert(1)</script>', 'total' => 1.0, 'currency' => 'EUR', 'status' => 'processing', 'url' => 'http://x' ) ),
				);
				return $s;
			}
		);
		$this->assertStringNotContainsString( '<script>alert(1)', $this->render() );
	}
}
