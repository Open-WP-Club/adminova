<?php
use PHPUnit\Framework\TestCase;

final class BulkTest extends TestCase {
	public function test_ids_are_parsed_deduped_and_zeros_dropped(): void {
		$this->assertSame([3, 7], Adminova_Bulk::parse_ids('3, 3,x,0,7'));
	}

	public function test_ids_from_an_array(): void {
		$this->assertSame([5, 2], Adminova_Bulk::parse_ids(['5', '-2']));
	}

	public function test_ids_are_capped(): void {
		$this->assertCount(Adminova_Bulk::MAX, Adminova_Bulk::parse_ids(range(1, 500)));
	}

	public function test_nonce_is_bound_to_the_id_list(): void {
		$this->assertNotSame(Adminova_Bulk::nonce_action('adminova_print', '1,2'), Adminova_Bulk::nonce_action('adminova_print', '1,3'));
	}

	public function test_nonce_is_bound_to_the_action(): void {
		$this->assertNotSame(Adminova_Bulk::nonce_action('adminova_print', '1,2'), Adminova_Bulk::nonce_action('adminova_export', '1,2'));
	}
}
