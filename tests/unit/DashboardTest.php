<?php
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase {
	public function test_bars_scale_to_the_tallest(): void {
		$this->assertSame([0, 40, 80], Adminova_Dashboard::bar_heights([0, 50, 100], 80));
	}

	public function test_all_zero_days_give_flat_bars_not_a_division_error(): void {
		$this->assertSame([0, 0], Adminova_Dashboard::bar_heights([0, 0], 80));
	}

	public function test_no_data_gives_no_bars(): void {
		$this->assertSame([], Adminova_Dashboard::bar_heights([], 80));
	}
}
