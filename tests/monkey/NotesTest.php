<?php
use Brain\Monkey\Functions;

final class NotesTest extends MonkeyTestCase {
	protected function setUp(): void {
		parent::setUp();
		$_POST = array(
			'order' => '5',
			'note'  => '  Call the customer  ',
		);
	}

	public function test_saves_a_private_note_and_checks_the_nonce(): void {
		$this->can( 'edit_shop_order' );
		$order = $this->order( 5 );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Call the customer', 0, true );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertRedirectsWith( 'note', array( Adminova_Notes::class, 'save' ) );
		$this->assertSame( array( 'adminova_note' ), $this->nonces );
		$this->assertContains( array( 'edit_shop_order', 5 ), $this->capChecks, 'checks the cap for that exact order' );
	}

	public function test_refuses_without_permission_on_that_order(): void {
		$order = $this->order( 5 );
		$order->shouldReceive( 'add_order_note' )->never();
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->assertRedirectsWith( 'failed', array( Adminova_Notes::class, 'save' ) );
	}

	public function test_refuses_things_that_are_not_shop_orders(): void {
		$this->can( 'edit_shop_order' );
		$refund = $this->order( 5, 'shop_order_refund' );
		$refund->shouldReceive( 'add_order_note' )->never();
		Functions\when( 'wc_get_order' )->justReturn( $refund );

		$this->assertRedirectsWith( 'failed', array( Adminova_Notes::class, 'save' ) );
	}

	public function test_refuses_an_empty_note_and_a_missing_order(): void {
		$this->can( 'edit_shop_order' );
		Functions\when( 'wc_get_order' )->justReturn( false );
		$this->assertRedirectsWith( 'failed', array( Adminova_Notes::class, 'save' ) );

		$_POST['note'] = '   ';
		Functions\when( 'wc_get_order' )->justReturn( $this->order( 5 ) );
		$this->assertRedirectsWith( 'failed', array( Adminova_Notes::class, 'save' ) );
	}

	public function test_form_is_hidden_from_people_who_cannot_edit_orders(): void {
		ob_start();
		Adminova_Notes::form();
		$this->assertSame( '', ob_get_clean() );
	}
}
