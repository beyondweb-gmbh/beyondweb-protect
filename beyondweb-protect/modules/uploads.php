<?php
/**
 * Beyondweb Protect, Modul «Uploads»
 *
 * Groessenlimit fuer Uploads in die Mediathek, fuer alle Rollen:
 * Bilder 2 MB, alle anderen Dateien 10 MB. Zu grosse Dateien werden mit einer
 * verstaendlichen Meldung abgelehnt.
 *
 * Gilt nur fuer die Mediathek (Upload-Fenster, «Datei hinzufuegen», Block-Editor,
 * REST-Schnittstelle, XML-RPC).
 * Plugin- und Theme-Uploads, Importe und Formulare anderer Plugins bleiben unberuehrt.
 *
 * Optionen (per WP-CLI), Wert in MB, 0 = kein Limit:
 *   bw_protect_upload_max_image  Standard 2
 *   bw_protect_upload_max_file   Standard 10
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_handle_upload_prefilter', bw_protect_guard( 'bw_protect_uploads_check' ), 20 );
add_filter( 'wp_handle_sideload_prefilter', bw_protect_guard( 'bw_protect_uploads_check' ), 20 ); // REST-Upload als Rohdaten.
add_filter( 'wp_upload_bits', bw_protect_guard( 'bw_protect_uploads_check_bits' ), 20 );          // XML-RPC.
add_action( 'pre-upload-ui', bw_protect_guard( 'bw_protect_uploads_hint_start' ) );
add_action( 'post-upload-ui', bw_protect_guard( 'bw_protect_uploads_hint_end' ) );

/**
 * Limit in Bytes fuer Bilder ('image') oder andere Dateien ('file'). 0 = kein Limit.
 */
function bw_protect_uploads_limit( $kind ) {
	$default = 'image' === $kind ? 2 : 10;
	$value   = get_option( 'image' === $kind ? 'bw_protect_upload_max_image' : 'bw_protect_upload_max_file', $default );
	$mb      = is_numeric( $value ) ? (float) $value : (float) $default;
	if ( $mb < 0 || ! is_finite( $mb ) ) {
		$mb = (float) $default;
	}
	if ( 0.0 === $mb ) {
		return 0; // Ausdruecklich kein Limit.
	}
	// Mindestens 1 KB, hoechstens 1 TB: so wird aus einem Tippfehler nie «kein Limit».
	return (int) min( max( round( $mb * MB_IN_BYTES ), KB_IN_BYTES ), 1024 * GB_IN_BYTES );
}

/**
 * Laeuft gerade ein Upload in die Mediathek?
 */
function bw_protect_uploads_is_media_upload() {
	$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
	if ( in_array( $pagenow, array( 'async-upload.php', 'media-new.php', 'media-upload.php' ), true ) ) {
		return true;
	}
	// Derselbe Upload ueber admin-ajax.php. Nur lesender Vergleich, die Pruefung des Nonce macht WordPress.
	if ( 'admin-ajax.php' === $pagenow && isset( $_REQUEST['action'] ) && 'upload-attachment' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		return true;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return (bool) preg_match( '#^/wp/v2/media(/|$)#i', (string) $GLOBALS['wp']->query_vars['rest_route'] );
	}
	return false;
}

/**
 * Ist die Datei ein Bild? Massgebend sind Dateiendung und der gemeldete Typ:
 * Trifft eines von beiden zu, gilt das strengere Bild-Limit.
 */
function bw_protect_uploads_is_image( $file ) {
	$name  = isset( $file['name'] ) ? (string) $file['name'] : '';
	$check = wp_check_filetype( $name );
	if ( ! empty( $check['type'] ) && 0 === strpos( $check['type'], 'image/' ) ) {
		return true;
	}
	return isset( $file['type'] ) && is_string( $file['type'] ) && 0 === strpos( $file['type'], 'image/' );
}

function bw_protect_uploads_check( $file ) {
	if ( ! is_array( $file ) || ! empty( $file['error'] ) || ! bw_protect_uploads_is_media_upload() ) {
		return $file;
	}

	$size = 0;
	if ( ! empty( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) && is_file( $file['tmp_name'] ) ) {
		$size = (int) filesize( $file['tmp_name'] ); // Echte Groesse auf dem Server, nicht die gemeldete.
	} elseif ( isset( $file['size'] ) ) {
		$size = (int) $file['size'];
	}

	$image = bw_protect_uploads_is_image( $file );
	$limit = bw_protect_uploads_limit( $image ? 'image' : 'file' );

	if ( $limit > 0 && $size > $limit ) {
		$t             = bw_protect_texts( bw_protect_lang( get_user_locale() ) );
		$file['error'] = sprintf( $t[ $image ? 'upload_image' : 'upload_file' ], size_format( $size, 1 ), size_format( $limit ) );
	}

	return $file;
}

/**
 * Uploads ueber XML-RPC: Dort liegt die Datei als Zeichenkette vor.
 * Eine Zeichenkette als Rueckgabe ist fuer WordPress die Fehlermeldung.
 */
function bw_protect_uploads_check_bits( $upload ) {
	if ( ! is_array( $upload ) || ! isset( $upload['name'], $upload['bits'] ) || ! is_string( $upload['name'] ) || ! is_string( $upload['bits'] ) ) {
		return $upload;
	}
	if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
		return $upload;
	}

	$size  = strlen( $upload['bits'] );
	// Bild nach Dateiname oder nach Inhalt: Ein Bild mit fremder Endung bleibt ein Bild.
	// Nur gaengige Web-Formate zaehlen, sonst haelt PHP auch manche Textdatei fuer ein Bild.
	$image = bw_protect_uploads_is_image( array( 'name' => $upload['name'] ) );
	if ( ! $image ) {
		$info  = @getimagesizefromstring( $upload['bits'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- meldet bei Nicht-Bildern eine Warnung.
		$types = array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP, IMAGETYPE_WEBP );
		if ( defined( 'IMAGETYPE_AVIF' ) ) {
			$types[] = IMAGETYPE_AVIF;
		}
		$image = is_array( $info ) && isset( $info[2] ) && in_array( $info[2], $types, true );
	}
	$limit = bw_protect_uploads_limit( $image ? 'image' : 'file' );

	if ( $limit > 0 && $size > $limit ) {
		$t = bw_protect_texts( bw_protect_lang( get_user_locale() ) );
		return sprintf( $t[ $image ? 'upload_image' : 'upload_file' ], size_format( $size, 1 ), size_format( $limit ) );
	}

	return $upload;
}

/**
 * Hinweis im Upload-Feld der Mediathek. WordPress zeigt dort die technische Obergrenze
 * des Servers («Maximale Dateigroesse fuer Uploads»). Solange das Upload-Feld ausgegeben
 * wird, steht an dieser Stelle der Hinweis mit den eigenen Limits.
 */
function bw_protect_uploads_hint_text() {
	$image = bw_protect_uploads_limit( 'image' );
	$other = bw_protect_uploads_limit( 'file' );
	if ( $image <= 0 || $other <= 0 ) {
		return ''; // Ohne beide Limits bleibt die Angabe von WordPress.
	}
	// Liegt die Obergrenze des Servers tiefer, gilt sie: Der Hinweis verspricht nie mehr, als hochgeladen werden kann.
	$server = (int) wp_max_upload_size();
	if ( $server > 0 ) {
		$image = min( $image, $server );
		$other = min( $other, $server );
	}
	$t = bw_protect_texts( bw_protect_lang( get_user_locale() ) );
	return sprintf( $t['upload_hint'], size_format( $image ), size_format( $other ) );
}

function bw_protect_uploads_hint_start() {
	if ( '' === bw_protect_uploads_hint_text() ) {
		return;
	}
	$GLOBALS['bw_protect_upload_ui'] = true;
	static $added = false;
	if ( ! $added ) {
		$added = true;
		add_filter( 'gettext', bw_protect_guard( 'bw_protect_uploads_hint_gettext' ), 20, 3 );
	}
}

function bw_protect_uploads_hint_end() {
	$GLOBALS['bw_protect_upload_ui'] = false;
}

function bw_protect_uploads_hint_gettext( $translation, $text, $domain ) {
	if ( empty( $GLOBALS['bw_protect_upload_ui'] ) || 'default' !== $domain || 'Maximum upload file size: %s.' !== $text ) {
		return $translation;
	}
	$hint = bw_protect_uploads_hint_text();
	// WordPress setzt den Text noch in printf ein: Prozentzeichen maskieren.
	return '' !== $hint ? str_replace( '%', '%%', $hint ) : $translation;
}
