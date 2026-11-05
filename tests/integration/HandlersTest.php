<?php
class HandlersTest extends AdminovaTestCase {
	public function test_quick_note_is_added_as_a_private_note(): void {
		$uid   = $this->actingAs( 'shop_manager' );
		$order = $this->makeOrder();
		$this->nonce( 'adminova_note' );
		$_POST += array( 'order' => (string) $order->get_id(), 'note' => 'Call the customer' );

		$url = $this->redirectOf( array( 'Adminova_Notes', 'save' ) );
		$this->assertStringContainsString( 'adminova_done=note', $url );
		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
		$this->assertSame( 'Call the customer', $notes[0]->content );
		$this->assertFalse( $notes[0]->customer_note );
	}

	public function test_quick_note_is_refused_without_the_nonce(): void {
		$this->actingAs( 'shop_manager' );
		$order = $this->makeOrder();
		$_POST = array( 'order' => (string) $order->get_id(), 'note' => 'x' );
		$this->expectException( WPDieException::class );
		Adminova_Notes::save();
	}

	public function test_quick_note_is_refused_for_a_user_who_cannot_edit_orders(): void {
		$this->actingAs( 'subscriber' );
		$order = $this->makeOrder();
		$this->nonce( 'adminova_note' );
		$_POST += array( 'order' => (string) $order->get_id(), 'note' => 'x' );

		$this->assertStringContainsString( 'adminova_done=failed', $this->redirectOf( array( 'Adminova_Notes', 'save' ) ) );
		$this->assertSame( array(), wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) );
	}

	public function test_restock_adds_to_the_stock_and_the_cache_refreshes(): void {
		$this->actingAs( 'shop_manager' );
		$p = $this->makeProduct( 'Blue Mug', 1 );
		$this->nonce( 'adminova_restock_' . $p->get_id() );
		$_POST += array( 'product' => (string) $p->get_id(), 'qty' => '25' );

		$this->assertStringContainsString( 'adminova_done=restock', $this->redirectOf( array( 'Adminova_Restock', 'handle' ) ) );
		$this->assertSame( 26, wc_get_product( $p->get_id() )->get_stock_quantity() );
	}

	public function test_restock_is_refused_for_a_user_without_product_rights(): void {
		$this->actingAs( 'subscriber' );
		$p = $this->makeProduct( 'Blue Mug', 1 );
		$this->nonce( 'adminova_restock_' . $p->get_id() );
		$_POST += array( 'product' => (string) $p->get_id(), 'qty' => '25' );

		$this->expectException( WPDieException::class );
		try {
			Adminova_Restock::handle();
		} finally {
			$this->assertSame( 1, wc_get_product( $p->get_id() )->get_stock_quantity() );
		}
	}

	public function test_saved_filters_round_trip_per_user(): void {
		$uid = $this->actingAs( 'shop_manager' );
		$this->nonce( 'adminova_filter' );
		$_POST += array( 'name' => 'To ship', 'q' => array( 'status' => 'wc-processing', 'evil' => 'x' ) );
		$this->redirectOf( array( 'Adminova_Filters', 'save' ) );

		$this->assertSame( array( 'To ship' => array( 'status' => 'wc-processing' ) ), get_user_meta( $uid, 'adminova_filters', true ) );

		$other = $this->actingAs( 'shop_manager' );
		$this->assertSame( '', get_user_meta( $other, 'adminova_filters', true ), 'another user does not see it' );

		wp_set_current_user( $uid );
		$_POST = array( 'name' => 'To ship' );
		$this->nonce( 'adminova_filter' );
		$this->redirectOf( array( 'Adminova_Filters', 'delete' ) );
		$this->assertSame( array(), get_user_meta( $uid, 'adminova_filters', true ) );
	}

	public function test_status_change_is_logged_and_can_be_undone(): void {
		$this->actingAs( 'shop_manager' );
		$order = $this->makeOrder( 'processing' );
		$order->update_status( 'completed' );

		$log = get_option( Adminova_Undo::OPTION );
		$this->assertSame( array( 'processing', 'completed' ), array( $log[0]['from'], $log[0]['to'] ) );

		$key = $log[0]['key'];
		$this->nonce( 'adminova_undo_' . $key );
		$_POST += array( 'key' => $key );
		$this->assertStringContainsString( 'adminova_done=undo', $this->redirectOf( array( 'Adminova_Undo', 'undo' ) ) );
		$this->assertSame( 'processing', wc_get_order( $order->get_id() )->get_status() );
		$this->assertCount( 0, get_option( Adminova_Undo::OPTION ), 'the undo is not itself logged' );
	}

	public function test_undo_is_refused_when_the_order_moved_on_since(): void {
		$this->actingAs( 'shop_manager' );
		$order = $this->makeOrder( 'processing' );
		$order->update_status( 'completed' );
		$key = get_option( Adminova_Undo::OPTION )[0]['key'];
		$order->update_status( 'refunded' );

		$this->nonce( 'adminova_undo_' . $key );
		$_POST += array( 'key' => $key );
		$this->assertStringContainsString( 'adminova_done=failed', $this->redirectOf( array( 'Adminova_Undo', 'undo' ) ) );
		$this->assertSame( 'refunded', wc_get_order( $order->get_id() )->get_status() );
	}

	public function test_front_end_status_changes_are_not_logged(): void {
		set_current_screen( 'front' );
		$this->makeOrder( 'pending' )->update_status( 'processing' );
		$this->assertEmpty( get_option( Adminova_Undo::OPTION ) );
	}
}
