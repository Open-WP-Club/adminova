<?php
use Brain\Monkey\Functions;

final class UndoTest extends MonkeyTestCase {
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();
		$this->log = array();
		Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => Adminova_Undo::OPTION === $k ? $this->log : $d );
		Functions\when( 'update_option' )->alias(
			function ( $k, $v, $autoload = null ) {
				$this->log = $v;
				return true;
			}
		);
	}

	private function entry( string $from = 'processing', string $to = 'completed' ): array {
		return array(
			'key'  => 'k1',
			'id'   => 9,
			'from' => $from,
			'to'   => $to,
			'user' => 7,
			'time' => time(),
		);
	}

	public function test_logs_changes_made_in_the_admin(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Adminova_Undo::log( 9, 'processing', 'completed' );

		$this->assertCount( 1, $this->log );
		$this->assertSame( array( 9, 'processing', 'completed', 7 ), array( $this->log[0]['id'], $this->log[0]['from'], $this->log[0]['to'], $this->log[0]['user'] ) );
	}

	public function test_does_not_log_front_end_changes(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Adminova_Undo::log( 9, 'pending', 'processing' );
		$this->assertSame( array(), $this->log );
	}

	public function test_undo_restores_the_old_status_and_drops_the_entry(): void {
		$this->can( 'edit_shop_orders', 'edit_shop_order' );
		$this->log = array( $this->entry() );
		$_POST     = array( 'key' => 'k1' );
		$order     = $this->order( 9 );
		$order->shouldReceive( 'get_status' )->andReturn( 'completed' );
		$order->shouldReceive( 'update_status' )->once()->with( 'processing', Mockery::type( 'string' ) );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertRedirectsWith( 'undo', array( Adminova_Undo::class, 'undo' ) );
		$this->assertSame( array(), $this->log );
		$this->assertSame( array( 'adminova_undo_k1' ), $this->nonces );
	}

	public function test_undo_refuses_when_the_order_was_changed_again_since(): void {
		$this->can( 'edit_shop_orders', 'edit_shop_order' );
		$this->log = array( $this->entry() );
		$_POST     = array( 'key' => 'k1' );
		$order     = $this->order( 9 );
		$order->shouldReceive( 'get_status' )->andReturn( 'refunded' );
		$order->shouldReceive( 'update_status' )->never();
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertRedirectsWith( 'failed', array( Adminova_Undo::class, 'undo' ) );
		$this->assertCount( 1, $this->log, 'entry stays' );
	}

	public function test_undo_refuses_without_permission_on_that_order(): void {
		$this->can( 'edit_shop_orders' ); // can use the screen, but not edit this order
		$this->log = array( $this->entry() );
		$_POST     = array( 'key' => 'k1' );
		$order     = $this->order( 9 );
		$order->shouldReceive( 'get_status' )->andReturn( 'completed' );
		$order->shouldReceive( 'update_status' )->never();
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertRedirectsWith( 'failed', array( Adminova_Undo::class, 'undo' ) );
	}

	public function test_undo_with_an_unknown_key_changes_nothing(): void {
		$this->can( 'edit_shop_orders', 'edit_shop_order' );
		$this->log = array( $this->entry() );
		$_POST     = array( 'key' => 'nope' );
		Functions\when( 'wc_get_order' )->justReturn( $this->order( 9 ) );

		$this->assertRedirectsWith( 'failed', array( Adminova_Undo::class, 'undo' ) );
		$this->assertCount( 1, $this->log );
	}

	public function test_undo_dies_without_the_orders_permission(): void {
		$_POST = array( 'key' => 'k1' );
		$this->expectException( DieException::class );
		Adminova_Undo::undo();
	}

	public function test_undoing_does_not_create_a_new_log_entry(): void {
		$this->can( 'edit_shop_orders', 'edit_shop_order' );
		$this->log = array( $this->entry() );
		$_POST     = array( 'key' => 'k1' );
		Functions\when( 'is_admin' )->justReturn( true );
		$order = $this->order( 9 );
		$order->shouldReceive( 'get_status' )->andReturn( 'completed' );
		// a real update_status() fires woocommerce_order_status_changed, which calls log()
		$order->shouldReceive( 'update_status' )->andReturnUsing(
			function () {
				Adminova_Undo::log( 9, 'completed', 'processing' );
			}
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->redirectOf( array( Adminova_Undo::class, 'undo' ) );
		$this->assertSame( array(), $this->log, 'entry removed and the undo itself not logged' );
	}
}
