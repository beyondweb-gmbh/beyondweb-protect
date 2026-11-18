<?php
/**
 * Beyondweb Protect, Modul «Path»
 *
 * Schuetzt den eigenen Login-Pfad von ASE Pro («Change Login URL») vor dem Auslesen.
 *
 * Hintergrund: Einige Adressen von WordPress bleiben ohne Kenntnis des Login-Pfads
 * erreichbar. WordPress gibt dort einen Link zur Login-Seite aus oder leitet auf
 * sie weiter, und beides enthaelt den Login-Pfad.
 *
 * Was das Modul macht:
 * 1. Auf wp-login.php bekommen nur Besucher die echte Login-Adresse, die den Pfad
 *    nachweislich schon kennen. Alle anderen bekommen die Startseite.
 * 2. Weiterleitungen von wp-admin und wp-login.php auf die Login-Seite gehen fuer
 *    alle anderen ebenfalls auf die Startseite.
 * 3. Links von der Login-Seite zur Website senden keine Herkunftsangabe (Referer),
 *    damit der Pfad nicht bei Statistik-Skripten der Website landet.
 *
 * 4. Ungewoehnliche Aufrufe von wp-login.php (REST-Zusatz in der Adresse, Anhaenge
 *    hinter dem Dateinamen) gehen fuer nicht angemeldete Besucher auf die Fehlerseite
 *    von ASE Pro. Das ist die einzige Stelle, an der das Modul eine Anfrage abweist.
 *
 * Ohne aktiven Login-Pfad in ASE Pro tut das Modul nichts.
 * Der Login-Pfad selbst steht nur in den ASE-Einstellungen der Website, nie im Plugin.
 */

defined( 'ABSPATH' ) || exit;

const BW_PROTECT_PATH_COOKIE = 'bw_protect_lp';

/**
 * Login-Pfad aus ASE Pro. Leer, wenn «Change Login URL» nicht aktiv ist.
 */
function bw_protect_path_slug() {
	static $slug = null;
	if ( null === $slug ) {
		$slug    = '';
		$options = get_option( 'admin_site_enhancements', array() );
		if ( is_array( $options ) && ! empty( $options['change_login_url'] ) && ! empty( $options['custom_login_slug'] ) && is_string( $options['custom_login_slug'] ) ) {
			$slug = trim( $options['custom_login_slug'], "/ \t\n\r" );
		}
	}
	return $slug;
}

/**
 * Kommt der Login-Pfad in dieser Adresse als eigener Teil vor
 * (als Ordner im Pfad oder als Schluessel in der Abfrage)?
 */
function bw_protect_path_in_url( $url ) {
	$slug = bw_protect_path_slug();
	if ( '' === $slug || ! is_string( $url ) || '' === $url ) {
		return false;
	}
	$url = rawurldecode( $url ); // Weiterleitungen kodieren Sonderzeichen.
	return (bool) preg_match( '~(?:^|[/?&])' . preg_quote( $slug, '~' ) . '(?:$|[/?&=#])~', $url );
}

/**
 * Wert des Cookies, das den Besuch der echten Login-Seite belegt.
 * Der Pfad fliesst in die Signatur ein: Aendert er sich, sind alte Cookies wertlos.
 */
function bw_protect_path_cookie_value( $expires ) {
	return (int) $expires . '.' . hash_hmac( 'sha256', 'bw-protect-path|' . (int) $expires . '|' . bw_protect_path_slug(), wp_salt( 'auth' ) );
}

function bw_protect_path_cookie_valid() {
	if ( empty( $_COOKIE[ BW_PROTECT_PATH_COOKIE ] ) || ! is_string( $_COOKIE[ BW_PROTECT_PATH_COOKIE ] ) ) {
		return false;
	}
	$value = sanitize_text_field( wp_unslash( $_COOKIE[ BW_PROTECT_PATH_COOKIE ] ) );
	if ( ! preg_match( '/^(\d{10})\.[a-f0-9]{64}$/', $value, $match ) ) {
		return false;
	}
	if ( (int) $match[1] < time() ) {
		return false;
	}
	return hash_equals( bw_protect_path_cookie_value( (int) $match[1] ), $value );
}

/**
 * Hat dieser Besucher einen gueltigen Link zum Setzen des Passworts geoeffnet?
 * WordPress legt den Schluessel dafuer in einem Cookie ab.
 */
function bw_protect_path_has_reset_key() {
	static $result = null;
	if ( null !== $result ) {
		return $result; // Hoechstens eine Pruefung pro Anfrage.
	}
	$result = false;
	$name   = 'wp-resetpass-' . COOKIEHASH;
	if ( empty( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) || strlen( $_COOKIE[ $name ] ) > 200 || false === strpos( $_COOKIE[ $name ], ':' ) ) {
		return $result;
	}
	list( $login, $key ) = explode( ':', wp_unslash( $_COOKIE[ $name ] ), 2 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird nur an die Pruefung von WordPress uebergeben.
	$result              = check_password_reset_key( $key, $login ) instanceof WP_User;
	return $result;
}

/**
 * Kennt der Besucher den Login-Pfad bereits? Dann darf er ihn sehen.
 */
function bw_protect_path_known() {
	if ( ! empty( $GLOBALS['bw_protect_path_proven'] ) || is_user_logged_in() ) {
		return true;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Vergleich.
	if ( bw_protect_path_in_url( $uri ) ) {
		return true;
	}

	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Vergleich.
	if ( '' !== $referer ) {
		$host = wp_parse_url( $referer, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host && $home && strtolower( $host ) === strtolower( $home ) && bw_protect_path_in_url( $referer ) ) {
			return true;
		}
	}

	return bw_protect_path_cookie_valid() || bw_protect_path_has_reset_key();
}

/**
 * Laeuft gerade wp-login.php? Bewusst nicht nur ueber $pagenow: WordPress leitet
 * den Wert aus der Adresse ab, und ein praeparierter Pfadanhang verfaelscht ihn.
 */
function bw_protect_path_on_login_page() {
	if ( did_action( 'login_init' ) ) {
		return true;
	}
	if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
		return true;
	}
	$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( (string) wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Vergleich.
	return 'wp-login.php' === $script;
}

/**
 * Ist das genau die Adresse der Login-Seite dieser Website? Streng geprueft, weil
 * diese Pruefung auch fuer Weiterleitungen gilt:
 * «/<pfad>/» direkt unter der Startseite oder wp-login.php mit dem Pfad als Schluessel.
 */
function bw_protect_path_is_login_url( $url ) {
	$slug = bw_protect_path_slug();
	if ( '' === $slug || ! is_string( $url ) || '' === $url ) {
		return false;
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return false;
	}
	if ( ! empty( $parts['host'] ) ) {
		$host = strtolower( $parts['host'] );
		if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== $host && strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ) !== $host ) {
			return false;
		}
	}
	$path  = isset( $parts['path'] ) ? rawurldecode( (string) $parts['path'] ) : '';
	$query = isset( $parts['query'] ) ? rawurldecode( (string) $parts['query'] ) : '';

	if ( 'wp-login.php' === basename( $path ) ) {
		return (bool) preg_match( '~(?:^|&)' . preg_quote( $slug, '~' ) . '(?:$|[&=])~', $query );
	}

	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return trim( $path, '/' ) === trim( $home_path . $slug, '/' );
}

/* -------------------------------------------------------------------------
 * 1. Login-Adresse nur fuer Besucher, die sie kennen
 * ---------------------------------------------------------------------- */

add_action( 'login_init', bw_protect_guard( 'bw_protect_path_block_rest_marker' ), 0 );
add_action( 'login_init', bw_protect_guard( 'bw_protect_path_login_init' ), 1 );

/**
 * Ist das ein gewoehnlicher Aufruf von wp-login.php? Ungewoehnlich sind die Abfrage
 * «rest_route» und jede Adresse, deren Pfad nicht genau der von wp-login.php ist
 * (Anhaenge hinter dem Dateinamen, Umwege ueber andere Ordner). ASE Pro haelt manche
 * dieser Anfragen fuer Aufrufe der REST-Schnittstelle und laesst sie ungeprueft durch.
 * Bewusst der genaue Vergleich statt einer Liste bekannter Schreibweisen.
 */
function bw_protect_path_plain_login_request() {
	if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Erkennung, kein Formular.
		return false;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Vergleich.
	if ( '' === $uri ) {
		return true; // Keine Web-Anfrage.
	}
	$path = (string) strtok( $uri, '?' );
	if ( preg_match( '~^https?://[^/]+(/.*)?$~i', $path, $match ) ) {
		$path = isset( $match[1] ) ? $match[1] : '/'; // Anfrage mit vollstaendiger Adresse.
	}
	$expected = wp_parse_url( site_url( 'wp-login.php', 'login' ), PHP_URL_PATH );
	if ( ! is_string( $expected ) || '' === $expected ) {
		return true;
	}
	return $path === $expected;
}

/**
 * Eine nicht angemeldete, ungewoehnliche Anfrage an wp-login.php bekommt, was ASE Pro
 * fuer wp-login.php ohne Login-Pfad vorsieht: die Weiterleitung auf seine Fehlerseite.
 */
function bw_protect_path_block_rest_marker() {
	$slug = bw_protect_path_slug();
	if ( '' === $slug || is_user_logged_in() || bw_protect_path_plain_login_request() ) {
		return;
	}
	$options = get_option( 'admin_site_enhancements', array() );
	$target  = 'not_found';
	if ( is_array( $options ) && ! empty( $options['default_login_redirect_slug'] ) && is_string( $options['default_login_redirect_slug'] ) ) {
		$target = trim( $options['default_login_redirect_slug'], "/ \t\n\r" );
	}
	// Nur ein einfacher Seitenname ist ein gueltiges Ziel, nie der Login-Pfad selbst.
	if ( ! preg_match( '~^[A-Za-z0-9_-]{1,100}$~', $target ) || 0 === strcasecmp( $target, $slug ) ) {
		$target = 'not_found';
	}
	nocache_headers();
	wp_safe_redirect( home_url( '/' . $target . '/' ), 302 );
	exit;
}

/**
 * Ganz am Anfang von wp-login.php: Nachweis festhalten und Cookie setzen.
 */
function bw_protect_path_login_init() {
	if ( '' === bw_protect_path_slug() ) {
		return;
	}

	// Wer zu Beginn angemeldet ist, bleibt fuer diese Anfrage berechtigt,
	// auch nach dem Abmelden (Weiterleitung zur Login-Seite).
	if ( is_user_logged_in() ) {
		$GLOBALS['bw_protect_path_proven'] = true;
		return;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Vergleich.
	if ( bw_protect_path_in_url( $uri ) && ! headers_sent() ) {
		$expires = time() + DAY_IN_SECONDS;
		$options = array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		setcookie( BW_PROTECT_PATH_COOKIE, bw_protect_path_cookie_value( $expires ), $options );
		if ( SITECOOKIEPATH !== COOKIEPATH ) {
			$options['path'] = SITECOOKIEPATH;
			setcookie( BW_PROTECT_PATH_COOKIE, bw_protect_path_cookie_value( $expires ), $options );
		}
	}
}

// Nach dem Setzen eines neuen Passworts ist der Schluessel verbraucht: Nachweis merken,
// damit der Link «Anmelden» auf der Bestaetigungsseite stimmt.
add_action(
	'after_password_reset',
	bw_protect_guard(
		function () {
			$GLOBALS['bw_protect_path_proven'] = true;
		}
	)
);

// Wer sich abmeldet, war angemeldet und darf zur Login-Seite zurueck, auch wenn das
// Abmelden nicht ueber wp-login.php laeuft. Eine anonyme Abmelde-Anfrage zaehlt nicht.
add_action(
	'wp_logout',
	bw_protect_guard(
		function ( $user_id = 0 ) {
			if ( (int) $user_id > 0 ) {
				$GLOBALS['bw_protect_path_proven'] = true;
			}
		}
	)
);

// Nach ASE Pro (Prioritaet 10) filtern.
add_filter( 'login_url', bw_protect_guard( 'bw_protect_path_hide' ), 9999 );
add_filter( 'lostpassword_url', bw_protect_guard( 'bw_protect_path_hide' ), 9999 );
add_filter( 'register_url', bw_protect_guard( 'bw_protect_path_hide' ), 9999 );

/**
 * Adresse mit Login-Pfad durch die Startseite ersetzen, wenn der Besucher
 * den Pfad nicht kennt. Gilt nur fuer Seiten von wp-login.php.
 */
function bw_protect_path_hide( $url ) {
	if ( ! is_string( $url ) || '' === bw_protect_path_slug() || ! bw_protect_path_on_login_page() ) {
		return $url;
	}
	if ( ! bw_protect_path_in_url( $url ) || bw_protect_path_known() ) {
		return $url;
	}
	return home_url( '/' );
}

/* -------------------------------------------------------------------------
 * 2. Weiterleitungen auf die Login-Seite nur fuer Besucher, die sie kennen
 * ---------------------------------------------------------------------- */

// Leitet WordPress einen Besucher ohne Kenntnis des Pfads auf die Login-Seite weiter,
// steht die Zieladresse im Antwortkopf. Der Schutz gilt nur fuer Anfragen an wp-admin
// und wp-login.php. Oeffentliche Seiten bleiben unberuehrt: Schickt eine Website ihre
// Besucher dort absichtlich zur Anmeldung (Mitgliederbereich, Login-Zwang), muss das
// weiter funktionieren.
add_filter( 'wp_redirect', bw_protect_guard( 'bw_protect_path_guard_redirect' ), 9999 );

function bw_protect_path_guard_redirect( $location ) {
	if ( ! is_string( $location ) || '' === bw_protect_path_slug() ) {
		return $location;
	}
	if ( ! is_admin() && ! bw_protect_path_on_login_page() ) {
		return $location;
	}
	if ( ! bw_protect_path_is_login_url( $location ) || bw_protect_path_known() ) {
		return $location;
	}
	return home_url( '/' );
}

/* -------------------------------------------------------------------------
 * 3. Links von der Login-Seite zur Website ohne Herkunftsangabe
 * ---------------------------------------------------------------------- */

// Die Login-Seite selbst muss ihre Herkunft weiter senden: ASE Pro prueft beim
// Anmelden, ob sie den Login-Pfad enthaelt. Deshalb pro Link statt fuer die ganze Seite.
add_filter( 'login_site_html_link', bw_protect_guard( 'bw_protect_path_noreferrer' ), 20 );
add_filter( 'the_privacy_policy_link', bw_protect_guard( 'bw_protect_path_noreferrer' ), 20 );

function bw_protect_path_noreferrer( $link ) {
	if ( ! is_string( $link ) || '' === bw_protect_path_slug() || ! bw_protect_path_on_login_page() ) {
		return $link;
	}
	if ( false !== stripos( $link, 'noreferrer' ) ) {
		return $link;
	}
	if ( preg_match( '/\srel=(["\'])/i', $link ) ) {
		return preg_replace( '/\srel=(["\'])/i', ' rel=$1noreferrer ', $link, 1 );
	}
	return preg_replace( '/<a\s/i', '<a rel="noreferrer" ', $link, 1 );
}

// Das Logo laesst sich nicht kennzeichnen: Es fuehrt deshalb nicht mehr zur Startseite.
// Der Weg zur Website bleibt der Link «Zurueck zur Website».
add_filter(
	'login_headerurl',
	bw_protect_guard(
		function ( $url ) {
			return '' !== bw_protect_path_slug() ? '#' : $url;
		}
	),
	30
);
