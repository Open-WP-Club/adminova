<?php
defined( 'ABSPATH' ) || exit;

// Bulk action "Export to CSV" for the selected orders.
class Adminova_Export {
	public static function hooks(): void {
		Adminova_Bulk::register( 'adminova_export', __( 'Export to CSV', 'adminova' ) );
		add_action( 'admin_post_adminova_export', array( self::class, 'download' ) );
	}

	// Spreadsheet apps run cells starting with = + - @ as formulas; a leading quote defuses them.
	public static function cell( $v ): string {
		$v = (string) $v;
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	}

	public static function download(): void {
		$ids     = Adminova_Bulk::request_ids( 'adminova_export' );
		$reports = current_user_can( 'view_woocommerce_reports' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="orders-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel reads UTF-8
		$head = array( 'Order', 'Date', 'Status', 'Name', 'Email', 'Phone', 'Country', 'Payment', 'Items' );
		if ( $reports ) {
			array_push( $head, 'Total', 'Currency' );
		}
		fputcsv( $out, $head, ',', '"', '\\' );
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o || 'shop_order' !== $o->get_type() || ! current_user_can( 'edit_shop_order', $id ) ) {
				continue;
			}
			$items = array();
			foreach ( $o->get_items() as $item ) {
				$items[] = $item->get_name() . ' x' . (int) $item->get_quantity();
			}
			$row = array(
				$o->get_order_number(),
				$o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '',
				wc_get_order_status_name( $o->get_status() ),
				trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
				$o->get_billing_email(),
				$o->get_billing_phone(),
				$o->get_billing_country(),
				$o->get_payment_method_title(),
				implode( '; ', $items ),
			);
			if ( $reports ) {
				array_push( $row, $o->get_total(), $o->get_currency() );
			}
			fputcsv( $out, array_map( array( self::class, 'cell' ), $row ), ',', '"', '\\' );
		}
		fclose( $out );
		exit;
	}
}
