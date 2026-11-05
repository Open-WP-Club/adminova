<?php
class StatsTest extends AdminovaTestCase {
	public function test_a_store_with_no_orders_shows_zeros_and_no_percentage(): void {
		$s = Adminova_Stats::get();
		$this->assertSame( 0, $s['today']['orders'] );
		$this->assertSame( 0.0, $s['today']['revenue'] );
		$this->assertNull( $s['delta_pct'] );
		$this->assertCount( 7, $s['week'] );
	}

	public function test_revenue_counts_paid_orders_only(): void {
		$this->makeOrder( 'processing', '50.00' );
		$this->makeOrder( 'completed', '25.50' );
		$this->makeOrder( 'cancelled', '999.00' );
		$this->makeOrder( 'pending', '999.00' );

		$s = Adminova_Stats::get();
		$this->assertSame( 2, $s['today']['orders'] );
		$this->assertEqualsWithDelta( 75.5, $s['today']['revenue'], 0.001 );
	}

	public function test_yesterdays_orders_are_counted_separately_and_give_a_percentage(): void {
		$this->makeOrder( 'completed', '100.00', 'a@example.com', time() - DAY_IN_SECONDS );
		$this->makeOrder( 'completed', '150.00' );

		$s = Adminova_Stats::get();
		$this->assertSame( 1, $s['yesterday']['orders'] );
		$this->assertEqualsWithDelta( 100.0, $s['yesterday']['revenue'], 0.001 );
		$this->assertSame( 50.0, $s['delta_pct'] );
	}

	public function test_a_new_order_clears_the_cache(): void {
		Adminova_Stats::get();
		$this->assertNotFalse( get_transient( Adminova_Stats::KEY ) );
		$this->makeOrder( 'processing', '10.00' );
		$this->assertFalse( get_transient( Adminova_Stats::KEY ), 'woocommerce_new_order flushes the cache' );
		$this->assertSame( 1, Adminova_Stats::get()['today']['orders'] );
	}

	public function test_status_counts_and_latest_orders(): void {
		$this->makeOrder( 'processing' );
		$this->makeOrder( 'on-hold' );
		$s = Adminova_Stats::get();
		$this->assertSame( 1, $s['by_status']['processing'] );
		$this->assertSame( 1, $s['by_status']['on-hold'] );
		$this->assertSame( 'Anna Kovacheva', $s['recent'][0]['name'] );
	}

	public function test_low_stock_lists_managed_products_at_or_below_the_threshold(): void {
		update_option( 'woocommerce_notify_low_stock_amount', 2 );
		$this->makeProduct( 'Blue Mug', 1 );
		$this->makeProduct( 'Plenty', 500 );

		$names = array_column( Adminova_Stats::get()['low_stock'], 'name' );
		$this->assertContains( 'Blue Mug', $names );
		$this->assertNotContains( 'Plenty', $names );
	}

	public function test_profit_uses_cost_prices_and_reports_items_without_one(): void {
		update_option( Adminova_Features::OPTION, array( 'profit' => true ) );
		$costed = $this->makeProduct( 'Costed', 10 );
		$costed->update_meta_data( Adminova_Profit::META, '3.00' );
		$costed->save();
		$bare = $this->makeProduct( 'No cost', 10 );

		$o = wc_create_order();
		$o->add_product( $costed, 2 ); // 20.00 revenue, 6.00 cost
		$o->add_product( $bare, 1 );
		$o->calculate_totals();
		$o->set_status( 'processing' );
		$o->save();

		$today = Adminova_Stats::get()['today'];
		$this->assertEqualsWithDelta( 14.0, $today['profit'], 0.001 );
		$this->assertSame( 1, $today['missing'] );
	}
}
