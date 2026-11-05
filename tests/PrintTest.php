<?php
use PHPUnit\Framework\TestCase;

final class PrintTest extends TestCase {
	public function test_ids_are_parsed_deduped_and_zeros_dropped(): void {
		$this->assertSame([3, 7], Adminova_Print::parse_ids('3, 3,x,0,7'));
	}

	public function test_ids_from_an_array(): void {
		$this->assertSame([5, 2], Adminova_Print::parse_ids(['5', '-2']));
	}

	public function test_ids_are_capped(): void {
		$this->assertCount(Adminova_Print::MAX, Adminova_Print::parse_ids(range(1, 500)));
	}

	public function test_nonce_is_bound_to_the_id_list(): void {
		$this->assertNotSame(Adminova_Print::nonce_action('1,2'), Adminova_Print::nonce_action('1,3'));
	}
}
