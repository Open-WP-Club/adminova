<?php
defined( 'ABSPATH' ) || exit;

// Which extras are switched on. Anything switched off never has its hooks registered.
class Adminova_Features {
	const OPTION   = 'adminova_features';
	const DEFAULTS = array(
		'skin'      => true,
		'palette'   => true,
		'dashboard' => true,
		'shortcuts' => true,
		'alerts'    => true,
		'print'     => true,
		'export'    => true,
		'filters'   => true,
		'notes'     => true,
		'restock'   => true,
		'profit'    => false, // needs cost prices entered first
		'customers' => true,
		'notify'    => false, // needs a destination configured
		'undo'      => true,
	);
	// features that are a class with a static hooks()
	const HOOKED = array(
		'dashboard' => 'Adminova_Dashboard',
		'shortcuts' => 'Adminova_Shortcuts',
		'alerts'    => 'Adminova_Alerts',
		'print'     => 'Adminova_Print',
		'export'    => 'Adminova_Export',
		'filters'   => 'Adminova_Filters',
		'notes'     => 'Adminova_Notes',
		'restock'   => 'Adminova_Restock',
		'profit'    => 'Adminova_Profit',
		'customers' => 'Adminova_Customers',
		'notify'    => 'Adminova_Notify',
		'undo'      => 'Adminova_Undo',
	);

	public static function resolve( $saved, string $slug ): bool {
		if ( is_array( $saved ) && isset( $saved[ $slug ] ) ) {
			return (bool) $saved[ $slug ];
		}
		return self::DEFAULTS[ $slug ] ?? false;
	}

	public static function enabled( string $slug ): bool {
		return self::resolve( get_option( self::OPTION, array() ), $slug );
	}

	// Unchecked checkboxes are absent from the form post, so absent means off.
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::DEFAULTS;
		}
		$out = array();
		foreach ( array_keys( self::DEFAULTS ) as $slug ) {
			$out[ $slug ] = isset( $input[ $slug ] );
		}
		return $out;
	}
}
