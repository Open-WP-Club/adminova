<?php
use PHPUnit\Framework\TestCase;

final class AlertsTest extends TestCase {
	public function test_recent_value_is_kept(): void {
		$this->assertSame(1000, Adminova_Alerts::since(1000, 1500));
	}

	public function test_stale_value_is_clamped_to_now(): void {
		$this->assertSame(200000, Adminova_Alerts::since(5, 200000));
	}

	public function test_garbage_falls_back_to_now(): void {
		$this->assertSame(200000, Adminova_Alerts::since('x', 200000));
		$this->assertSame(200000, Adminova_Alerts::since(null, 200000));
	}
}
