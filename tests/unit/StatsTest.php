<?php
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase {
	public function test_delta_is_null_when_yesterday_had_no_sales(): void {
		$this->assertNull(Adminova_Stats::delta_pct(50, 0));
	}

	public function test_delta_is_null_when_both_days_are_empty(): void {
		$this->assertNull(Adminova_Stats::delta_pct(0, 0));
	}

	public function test_growth_is_positive(): void {
		$this->assertSame(50.0, Adminova_Stats::delta_pct(150, 100));
	}

	public function test_drop_is_negative(): void {
		$this->assertSame(-50.0, Adminova_Stats::delta_pct(50, 100));
	}

	public function test_delta_rounds_to_one_decimal(): void {
		$this->assertSame(33.3, Adminova_Stats::delta_pct(4, 3));
	}
}
