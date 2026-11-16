<?php
/**
 * Plugin Name:       Beyondweb Protect
 * Plugin URI:        https://www.beyondweb.ch
 * Description:       Standards fuer Websites, die Beyondweb technisch betreut. Module: Login.
 * Version:           1.0.4
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Beyondweb GmbH
 * Author URI:        https://www.beyondweb.ch
 * License:           GPL-2.0-or-later
 * Update URI:        https://www.beyondweb.ch/beyondweb-protect
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BW_PROTECT_VERSION' ) ) {
	return; // Schon geladen.
}

define( 'BW_PROTECT_VERSION', '1.0.4' );
define( 'BW_PROTECT_LOGO_DEFAULT', 'auto' );
define( 'BW_PROTECT_DIR', __DIR__ );

/**
 * Module laden. Einzelne Module lassen sich pro Site abschalten:
 * wp option update bw_protect_disabled_modules '["login"]' --format=json
 *
 * Ein Fehler in einem Modul darf die Website nicht blockieren:
 * Das Modul wird uebersprungen und der Fehler ins Log geschrieben.
 */
$bw_protect_modules  = array( 'login' );
$bw_protect_disabled = (array) get_option( 'bw_protect_disabled_modules', array() );

foreach ( $bw_protect_modules as $bw_protect_module ) {
	if ( in_array( $bw_protect_module, $bw_protect_disabled, true ) ) {
		continue;
	}
	$bw_protect_path = BW_PROTECT_DIR . '/modules/' . $bw_protect_module . '.php';
	if ( ! is_readable( $bw_protect_path ) ) {
		continue;
	}
	try {
		require_once $bw_protect_path;
	} catch ( \Throwable $bw_protect_error ) {
		error_log( 'Beyondweb Protect: Modul ' . $bw_protect_module . ' nicht geladen: ' . $bw_protect_error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}

unset( $bw_protect_modules, $bw_protect_disabled, $bw_protect_module, $bw_protect_path, $bw_protect_error );
