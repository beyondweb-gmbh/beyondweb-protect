<?php
/**
 * Plugin Name:       Beyondweb Protect
 * Plugin URI:        https://www.beyondweb.ch
 * Description:       Standards fuer Websites, die Beyondweb technisch betreut. Module: Login, Path, Mail, Uploads, REST, TwoFA.
 * Version:           1.0.7
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

define( 'BW_PROTECT_VERSION', '1.0.7' );
define( 'BW_PROTECT_LOGO_DEFAULT', 'auto' );
define( 'BW_PROTECT_DIR', __DIR__ );

/**
 * Schutzhuelle fuer alle Hooks des Plugins: Wirft eine Funktion einen Fehler, wird er
 * protokolliert und der unveraenderte Eingangswert zurueckgegeben. So kann ein Fehler
 * im Plugin weder die Login-Seite noch die REST-Schnittstelle oder einen Upload lahmlegen.
 */
function bw_protect_guard( $callback ) {
	return static function () use ( $callback ) {
		$args = func_get_args();
		try {
			return call_user_func_array( $callback, $args );
		} catch ( \Throwable $error ) {
			bw_protect_log( $error->getMessage() . ' in ' . basename( $error->getFile() ) . ':' . $error->getLine() );
			return isset( $args[0] ) ? $args[0] : null;
		}
	};
}

/**
 * Fehler protokollieren, jede Meldung pro Anfrage nur einmal.
 */
function bw_protect_log( $message ) {
	static $seen = array();
	if ( isset( $seen[ $message ] ) ) {
		return;
	}
	$seen[ $message ] = true;
	error_log( 'Beyondweb Protect: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
}

/**
 * Module laden. Einzelne Module lassen sich pro Site abschalten:
 * wp option update bw_protect_disabled_modules '["login"]' --format=json
 *
 * login    Login-Seite: Design, Logo, Sprachwahl, eigene Texte
 * mail     Mails zu Anmeldecode, Passwort zuruecksetzen, neuem Konto, geaendertem Passwort und geaenderter Adresse
 * uploads  Groessenlimit fuer Uploads in die Mediathek
 * rest     Benutzerliste der REST-Schnittstelle nur fuer angemeldete Benutzer
 * path     Login-Pfad von ASE Pro vor dem Auslesen schuetzen
 * twofa    Anmeldecode per E-Mail (ASE Pro) schon bei der ersten Anmeldung einschalten
 *
 * Ein Fehler in einem Modul darf die Website nicht blockieren:
 * Das Modul wird uebersprungen und der Fehler ins Log geschrieben.
 */
$bw_protect_modules  = array( 'texts', 'login', 'path', 'mail', 'uploads', 'rest', 'twofa' ); // texts = gemeinsame Texte, immer zuerst.
$bw_protect_disabled = (array) get_option( 'bw_protect_disabled_modules', array() );

foreach ( $bw_protect_modules as $bw_protect_module ) {
	if ( 'texts' !== $bw_protect_module && in_array( $bw_protect_module, $bw_protect_disabled, true ) ) {
		continue;
	}
	$bw_protect_path = 'texts' === $bw_protect_module
		? BW_PROTECT_DIR . '/inc/texts.php'
		: BW_PROTECT_DIR . '/modules/' . $bw_protect_module . '.php';
	if ( ! is_readable( $bw_protect_path ) ) {
		continue;
	}
	try {
		require_once $bw_protect_path;
	} catch ( \Throwable $bw_protect_error ) {
		bw_protect_log( 'Modul ' . $bw_protect_module . ' nicht geladen: ' . $bw_protect_error->getMessage() );
		if ( 'texts' === $bw_protect_module ) {
			break; // Ohne Texte laeuft kein Modul: Plugin bleibt wirkungslos, die Website laeuft weiter.
		}
	}
}

unset( $bw_protect_modules, $bw_protect_disabled, $bw_protect_module, $bw_protect_path, $bw_protect_error );
