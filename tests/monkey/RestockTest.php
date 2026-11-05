<?php
use Brain\Monkey\Functions;

final class RestockTest extends MonkeyTestCase {
	private array $updates = array();

	protected function setUp(): void {
		parent::setUp();
		$this->updates = array();
		$_POST         = array(
			'product' => '5',
			'qty'     => '25',
		);
		Functions\when( 'wc_update_product_stock' )->alias(
			function ( $product, $qty, $op ) {
				$this->updates[] = array( $qty, $op );
			}
		);
	}

	private function product( bool $managing ) {
		$p = Mockery::mock( 'WC_Product' )->shouldIgnoreMissing();
		$p->shouldReceive( 'managing_stock' )->andReturn( $managing );
		Functions\when( 'wc_get_product' )->justReturn( $p );
	}

	public function test_increases_stock_by_the_requested_amount(): void {
		$this->can( 'edit_product' );
		$this->product( true );

		$this->assertRedirectsWith( 'restock', array( Adminova_Restock::class, 'handle' ) );
		$this->assertSame( array( array( 25, 'increase' ) ), $this->updates );
		$this->assertSame( array( 'adminova_restock_5' ), $this->nonces, 'nonce is bound to the product id' );
	}

	public function test_quantity_is_clamped(): void {
		$this->can( 'edit_product' );
		$this->product( true );
		$_POST['qty'] = '99999999';

		$this->redirectOf( array( Adminova_Restock::class, 'handle' ) );
		$this->assertSame( array( array( Adminova_Restock::MAX_QTY, 'increase' ) ), $this->updates );
	}

	public function test_dies_with_403_without_permission(): void {
		$this->product( true );
		try {
			Adminova_Restock::handle();
			$this->fail( 'expected wp_die' );
		} catch ( DieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}
		$this->assertSame( array(), $this->updates );
	}

	public function test_does_nothing_for_products_that_do_not_track_stock(): void {
		$this->can( 'edit_product' );
		$this->product( false );

		$this->assertRedirectsWith( 'failed', array( Adminova_Restock::class, 'handle' ) );
		$this->assertSame( array(), $this->updates );
	}

	public function test_form_is_not_shown_without_permission(): void {
		ob_start();
		Adminova_Restock::form( 5 );
		$this->assertSame( '', ob_get_clean() );
	}
}
