<?php
defined( 'ABSPATH' ) || exit;

// Bulk action "Print order slips": one printable page with a slip per selected order.
class Adminova_Print {
	public static function hooks(): void {
		Adminova_Bulk::register( 'adminova_print', __( 'Print order slips', 'adminova' ) );
		add_action( 'admin_post_adminova_print', array( self::class, 'page' ) );
	}

	public static function page(): void {
		$ids = Adminova_Bulk::request_ids( 'adminova_print' );
		echo '<!doctype html><meta charset="utf-8"><title>' . esc_html__( 'Order slips', 'adminova' ) . '</title>';
		echo '<style>body{font:14px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;color:#111}section{page-break-after:always;max-width:720px;margin:0 auto 40px}'
			. 'table{width:100%;border-collapse:collapse;margin:12px 0}th,td{text-align:left;padding:6px 8px;border-bottom:1px solid #ddd}.cols{display:flex;gap:40px}</style>';
		$reports = current_user_can( 'view_woocommerce_reports' );
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && 'shop_order' === $o->get_type() && current_user_can( 'edit_shop_order', $id ) ) {
				self::slip( $o, $reports );
			}
		}
		echo '<script>window.onload=function(){window.print()}</script>';
		exit;
	}

	private static function slip( $o, bool $reports ): void {
		printf(
			'<section><h2>%s #%s</h2><p>%s</p><div class="cols"><div><b>%s</b><br>%s<br>%s<br>%s</div><div><b>%s</b><br>%s</div></div>',
			esc_html__( 'Order', 'adminova' ),
			esc_html( $o->get_order_number() ),
			esc_html( wc_format_datetime( $o->get_date_created() ) ),
			esc_html__( 'Billing', 'adminova' ),
			wp_kses_post( $o->get_formatted_billing_address() ),
			esc_html( $o->get_billing_email() ),
			esc_html( $o->get_billing_phone() ),
			esc_html__( 'Shipping', 'adminova' ),
			wp_kses_post( $o->get_formatted_shipping_address() ?: '-' )
		);
		// prices are financial data: packing info only unless the user may see reports
		echo '<table><tr><th>' . esc_html__( 'Item', 'adminova' ) . '</th><th>' . esc_html__( 'Qty', 'adminova' ) . '</th>'
			. ( $reports ? '<th>' . esc_html__( 'Total', 'adminova' ) . '</th>' : '' ) . '</tr>';
		foreach ( $o->get_items() as $item ) {
			printf(
				'<tr><td>%s</td><td>%d</td>%s</tr>',
				esc_html( $item->get_name() ),
				(int) $item->get_quantity(),
				$reports ? '<td>' . wp_kses_post( $o->get_formatted_line_subtotal( $item ) ) . '</td>' : ''
			);
		}
		echo '</table>';
		if ( $reports ) {
			echo '<table>';
			foreach ( $o->get_order_item_totals() as $row ) {
				printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( wp_strip_all_tags( $row['label'] ) ), wp_kses_post( $row['value'] ) );
			}
			echo '</table>';
		}
		if ( $o->get_customer_note() ) {
			printf( '<p><b>%s</b> %s</p>', esc_html__( 'Note:', 'adminova' ), esc_html( $o->get_customer_note() ) );
		}
		echo '</section>';
	}
}
