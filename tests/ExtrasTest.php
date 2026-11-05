<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExtrasTest extends TestCase {
	public static function cells(): array {
		return [
			'plain'    => ['Anna', 'Anna'],
			'equals'   => ['=SUM(A1)', "'=SUM(A1)"],
			'plus'     => ['+1', "'+1"],
			'minus'    => ['-2', "'-2"],
			'at'       => ['@cmd', "'@cmd"],
			'number'   => [12.5, '12.5'],
		];
	}

	#[DataProvider('cells')]
	public function test_csv_cell_defuses_formulas(mixed $in, string $out): void {
		$this->assertSame($out, Adminova_Export::cell($in));
	}

	public function test_filter_params_keep_only_whitelisted_keys(): void {
		$this->assertSame(['status' => 'wc-processing', 's' => 'anna'], Adminova_Filters::clean_params(['status' => 'wc-processing', 's' => 'anna', 'action' => 'delete', 'evil' => 'x']));
	}

	public function test_filter_params_drop_empty_and_nested_values(): void {
		$this->assertSame([], Adminova_Filters::clean_params(['status' => '', 's' => ['a']]));
		$this->assertSame([], Adminova_Filters::clean_params('nope'));
	}

	public function test_filter_name_is_trimmed_stripped_and_capped(): void {
		$this->assertSame('Ready', Adminova_Filters::clean_name('  <b>Ready</b> '));
		$this->assertSame(40, mb_strlen(Adminova_Filters::clean_name(str_repeat('я', 100))));
		$this->assertSame('', Adminova_Filters::clean_name(['x']));
	}

	public function test_note_is_trimmed_and_capped(): void {
		$this->assertSame('hi', Adminova_Notes::clean_note("  hi \n"));
		$this->assertSame(Adminova_Notes::MAX_LEN, mb_strlen(Adminova_Notes::clean_note(str_repeat('a', 5000))));
	}

	public function test_restock_quantity_is_clamped(): void {
		$this->assertSame(1, Adminova_Restock::clamp(0));
		$this->assertSame(1, Adminova_Restock::clamp('x'));
		$this->assertSame(25, Adminova_Restock::clamp('25'));
		$this->assertSame(Adminova_Restock::MAX_QTY, Adminova_Restock::clamp(999999));
	}

	public function test_profit_is_null_without_a_cost_price(): void {
		$this->assertNull(Adminova_Profit::line(100.0, null, 2));
	}

	public function test_profit_per_line(): void {
		$this->assertSame(40.0, Adminova_Profit::line(100.0, 30.0, 2));
		$this->assertSame(-5.0, Adminova_Profit::line(10.0, 7.5, 2));
	}

	public function test_customer_summary(): void {
		$s = Adminova_Customers::summary([['total' => 10.0, 'ts' => 100], ['total' => 5.5, 'ts' => 300]]);
		$this->assertSame(['count' => 2, 'spent' => 15.5, 'last' => 300], $s);
	}

	public function test_customer_summary_of_a_new_customer(): void {
		$this->assertSame(['count' => 0, 'spent' => 0, 'last' => 0], Adminova_Customers::summary([]));
	}

	public function test_telegram_token_and_chat_validation(): void {
		$this->assertTrue(Adminova_Notify::valid_token('123456789:AAEhBOweik6ad9r_QXMENQjcrOTcBdgpsAB'));
		$this->assertFalse(Adminova_Notify::valid_token('123456789:abc/../../evil'));
		$this->assertFalse(Adminova_Notify::valid_token('x@evil.com/'));
		$this->assertTrue(Adminova_Notify::valid_chat('-1001234567890'));
		$this->assertFalse(Adminova_Notify::valid_chat('12; DROP'));
	}

	public function test_notification_message(): void {
		$this->assertSame("New order #7 from Anna\nTotal: 12.00 лв.\nhttp://x/o/7", Adminova_Notify::message('7', 'Anna', '12.00 лв.', 'http://x/o/7'));
	}

	public function test_undo_log_keeps_newest_first_and_is_capped(): void {
		$log = [];
		foreach (range(1, 60) as $i) {
			$log = Adminova_Undo::push($log, ['id' => $i]);
		}
		$this->assertCount(Adminova_Undo::MAX, $log);
		$this->assertSame(60, $log[0]['id']);
	}

	public function test_undo_only_when_order_is_still_in_the_changed_to_status(): void {
		$e = ['from' => 'processing', 'to' => 'completed'];
		$this->assertTrue(Adminova_Undo::can_undo($e, 'completed'));
		$this->assertFalse(Adminova_Undo::can_undo($e, 'refunded'), 'changed again since');
		$this->assertFalse(Adminova_Undo::can_undo(['from' => '', 'to' => 'completed'], 'completed'));
		$this->assertFalse(Adminova_Undo::can_undo(['from' => 'completed', 'to' => 'completed'], 'completed'));
	}
}
