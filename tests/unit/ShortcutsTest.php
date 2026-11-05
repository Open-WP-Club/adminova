<?php
use PHPUnit\Framework\TestCase;

final class ShortcutsTest extends TestCase {
	public function test_orders_url_with_hpos(): void {
		$this->assertSame('http://x/wp-admin/admin.php?page=wc-orders&status=wc-processing', Adminova_Shortcuts::orders_url('processing', true));
	}

	public function test_orders_url_legacy(): void {
		$this->assertSame('http://x/wp-admin/edit.php?post_type=shop_order&post_status=wc-processing', Adminova_Shortcuts::orders_url('processing', false));
	}

	public function test_orders_url_without_status_is_the_plain_list(): void {
		$this->assertSame('http://x/wp-admin/admin.php?page=wc-orders', Adminova_Shortcuts::orders_url('', true));
	}

	public function test_new_order_url_hpos_and_legacy(): void {
		$this->assertSame('http://x/wp-admin/admin.php?page=wc-orders&action=new', Adminova_Shortcuts::new_order_url(true));
		$this->assertSame('http://x/wp-admin/post-new.php?post_type=shop_order', Adminova_Shortcuts::new_order_url(false));
	}
}
