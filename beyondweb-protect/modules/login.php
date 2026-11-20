<?php
/**
 * Beyondweb Protect, Modul «Login»
 *
 * Einheitliches Login-Design: Kundenlogo automatisch, eigene Texte in Deutsch,
 * Franzoesisch, Italienisch und Englisch (auch fuer die 2FA-Anmeldung von ASE Pro),
 * Sprachwahl auf mehrsprachigen Websites, Fusszeile mit Hinweis auf Beyondweb.
 *
 * Optionen (per WP-CLI):
 *   bw_protect_logo           auto | name | none (Kopf ueber der Karte)
 *   bw_protect_logo_id        Attachment-ID, ueberschreibt die automatische Logo-Suche
 *   bw_protect_logo_light     'yes' / 'no' erzwingt das Einfaerben (hell -> dunkel)
 *   bw_protect_lang_switcher  auto | on | off (Sprachwahl auf der Login-Seite)
 */

defined( 'ABSPATH' ) || exit;


/* -------------------------------------------------------------------------
 * Stylesheet und Logo
 * ---------------------------------------------------------------------- */

add_action( 'login_enqueue_scripts', bw_protect_guard( 'bw_protect_login_enqueue' ), 20 );

function bw_protect_login_enqueue() {
	wp_enqueue_style(
		'beyondweb-protect-login',
		plugins_url( 'assets/login/login.css', BW_PROTECT_DIR . '/beyondweb-protect.php' ),
		array( 'login' ),
		BW_PROTECT_VERSION
	);

	$t = bw_protect_texts();

	if ( bw_protect_login_email_only() && bw_protect_login_email_2fa() ) {
		$intro = $t['intro_email_2fa'];
	} elseif ( bw_protect_login_email_only() ) {
		$intro = $t['intro_email'];
	} elseif ( bw_protect_login_email_2fa() ) {
		$intro = $t['intro_2fa'];
	} else {
		$intro = $t['intro'];
	}

	// Texte, die das Stylesheet einsetzt, in der Sprache der Login-Seite.
	$css  = 'body.login{';
	$css .= '--bw-t-title:' . bw_protect_login_css_string( $t['title'] ) . ';--bw-t-title-hl:' . (int) $t['title_hl'] . '%;';
	$css .= '--bw-t-code:' . bw_protect_login_css_string( $t['code'] ) . ';--bw-t-code-hl:' . (int) $t['code_hl'] . '%;';
	$css .= '--bw-t-backup:' . bw_protect_login_css_string( $t['backup'] ) . ';--bw-t-backup-hl:' . (int) $t['backup_hl'] . '%;';
	$css .= '--bw-t-intro:' . bw_protect_login_css_string( $intro ) . ';';
	$css .= '--bw-t-micro:' . bw_protect_login_css_string( $t['micro'] ) . ';';
	$css .= '--bw-t-footer:' . bw_protect_login_css_string( $t['footer'] ) . ';';

	$logo = 'auto' === bw_protect_login_logo_mode() ? bw_protect_login_get_logo() : null;
	if ( $logo ) {
		$css .= '--bw-logo-img:url("' . esc_url_raw( $logo['url'] ) . '");';
		$css .= '--bw-logo-w:' . (int) $logo['width'] . 'px;--bw-logo-h:' . (int) $logo['height'] . 'px;';
		if ( ! empty( $logo['light'] ) ) {
			$css .= '--bw-logo-filter:brightness(0);';
		}
	}
	$css .= '}';

	wp_add_inline_style( 'beyondweb-protect-login', $css );
}

/**
 * Text als CSS-Zeichenkette (fuer content: var(--x)).
 */
function bw_protect_login_css_string( $text ) {
	// «E-Mail» nicht am Bindestrich umbrechen (unsichtbares Verbindungszeichen U+2060).
	$text = preg_replace( '/(e-)(mail)/i', '$1' . "\u{2060}" . '$2', (string) $text );
	$text = str_replace( array( '\\', '"', "\r", "\n", '<', '>' ), array( '\\\\', '\\"', ' ', ' ', '', '' ), (string) $text );
	return '"' . $text . '"';
}

/**
 * Kopf ueber der Karte, pro Site per WP-CLI umschaltbar:
 *   wp option update bw_protect_logo name
 * auto = Logo automatisch (Standard), name = Site-Name als Text, none = nichts.
 */
function bw_protect_login_logo_mode() {
	$mode = get_option( 'bw_protect_logo', BW_PROTECT_LOGO_DEFAULT );
	return in_array( $mode, array( 'auto', 'name', 'none' ), true ) ? $mode : BW_PROTECT_LOGO_DEFAULT;
}

/**
 * Logo suchen. Reihenfolge:
 * 1. Option bw_protect_logo_id (manuell, z. B. per WP-CLI)
 * 2. Theme-Logo (custom_logo)
 * 3. Block-Theme-Logo (site_logo)
 * 4. Impreza, Flatsome
 * 5. Site Icon
 * Keins gefunden: WordPress-Logo bleibt.
 *
 * @return array|null url, width, height, light
 */
function bw_protect_login_get_logo() {
	static $cached = false;
	if ( false === $cached ) {
		$cached = bw_protect_login_find_logo();
	}
	return $cached;
}

function bw_protect_login_find_logo() {
	$candidates = array();

	$candidates[] = (int) get_option( 'bw_protect_logo_id', 0 );
	$candidates[] = (int) get_theme_mod( 'custom_logo', 0 );
	$candidates[] = (int) get_option( 'site_logo', 0 );

	if ( function_exists( 'us_get_option' ) ) { // Impreza.
		$candidates[] = (int) us_get_option( 'logo_image' );
	}

	$flatsome = get_theme_mod( 'site_logo' ); // Flatsome: ID oder URL.
	if ( $flatsome && is_string( $flatsome ) && ! is_numeric( $flatsome ) && bw_protect_login_is_own_url( $flatsome ) ) {
		$id = attachment_url_to_postid( $flatsome );
		$candidates[] = $id ? $id : $flatsome;
	} elseif ( $flatsome ) {
		$candidates[] = (int) $flatsome;
	}

	$candidates[] = (int) get_option( 'site_icon', 0 );

	$candidates = apply_filters( 'bw_protect_login_logo_candidates', $candidates );

	foreach ( $candidates as $candidate ) {
		if ( is_string( $candidate ) && ! is_numeric( $candidate ) ) {
			if ( ! bw_protect_login_is_own_url( $candidate ) ) {
				continue; // Keine Bilder von fremden Servern auf der Login-Seite.
			}
			return array(
				'url'    => $candidate,
				'width'  => 240,
				'height' => 72,
				'light'  => false,
			);
		}
		$id = (int) $candidate;
		if ( $id <= 0 || ( ! wp_attachment_is_image( $id ) && 'image/svg+xml' !== get_post_mime_type( $id ) ) ) {
			continue;
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $url ) {
			continue;
		}
		$meta = wp_get_attachment_metadata( $id );
		$w    = ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;

		list( $box_w, $box_h ) = bw_protect_login_fit_box( $w, $h );

		return array(
			'url'    => $url,
			'width'  => $box_w,
			'height' => $box_h,
			'light'  => bw_protect_login_is_light( $id ),
		);
	}

	return null;
}

/**
 * URL gehoert zur eigenen Website (gleicher Host wie die Startseite)?
 */
function bw_protect_login_is_own_url( $url ) {
	if ( ! is_string( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
		return false;
	}
	// Zuerst den Host vergleichen: So wird fuer fremde Adressen nie ein Name aufgeloest.
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host || ! $home || strtolower( $host ) !== strtolower( $home ) ) {
		return false;
	}
	return (bool) wp_http_validate_url( $url );
}

/**
 * Logo in eine Box von max. 240 x 72 px einpassen.
 */
function bw_protect_login_fit_box( $w, $h ) {
	$max_w = 240;
	$max_h = 72;
	if ( $w <= 0 || $h <= 0 ) {
		return array( $max_w, $max_h );
	}
	$ratio = $w / $h;
	$box_h = $max_h;
	$box_w = (int) max( 24, round( $max_h * $ratio ) );
	if ( $box_w > $max_w ) {
		$box_w = $max_w;
		$box_h = (int) max( 32, round( $max_w / $ratio ) );
	}
	return array( $box_w, $box_h );
}

/**
 * Erkennt helle Logos (z. B. weiss auf transparent fuer dunkle Header).
 * Nur Logos mit transparentem Hintergrund werden als hell eingestuft,
 * damit ein Logo auf weissem Rechteck nicht schwarz eingefaerbt wird.
 * Ergebnis wird 30 Tage zwischengespeichert.
 */
function bw_protect_login_is_light( $id ) {
	$forced = get_option( 'bw_protect_logo_light', '' ); // 'yes' / 'no' erzwingt.
	if ( 'yes' === $forced ) {
		return true;
	}
	if ( 'no' === $forced ) {
		return false;
	}

	$file = bw_protect_login_analysis_file( $id );
	if ( '' === $file ) {
		return false; // Kein Bild oder zu gross: nicht anfassen.
	}

	$key    = 'bw_protect_login_light_' . $id . '_' . filemtime( $file );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return 'y' === $cached;
	}

	$light = false;
	$ext   = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

	if ( 'svg' === $ext ) {
		$light = bw_protect_login_svg_is_light( $file );
	} elseif ( function_exists( 'imagecreatefromstring' ) ) {
		$light = bw_protect_login_raster_is_light( $file );
	}

	set_transient( $key, $light ? 'y' : 'n', 30 * DAY_IN_SECONDS );
	return $light;
}

/**
 * Datei fuer die Helligkeitspruefung. Schutz gegen uebergrosse Bilder:
 * hoechstens 2 Megapixel und 4 MB, sonst die mittlere Bildgroesse von
 * WordPress, sonst gar keine Pruefung.
 */
function bw_protect_login_analysis_file( $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! is_readable( $file ) ) {
		return '';
	}
	if ( 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
		return filesize( $file ) <= 200 * KB_IN_BYTES ? $file : ''; // Groessere SVG werden nicht untersucht.
	}
	if ( bw_protect_login_image_is_small( $file ) ) {
		return $file;
	}
	foreach ( array( 'medium', 'thumbnail' ) as $size ) {
		$sub = image_get_intermediate_size( $id, $size );
		if ( $sub && ! empty( $sub['file'] ) ) {
			$path = trailingslashit( dirname( $file ) ) . basename( $sub['file'] );
			if ( is_readable( $path ) && bw_protect_login_image_is_small( $path ) ) {
				return $path;
			}
		}
	}
	return '';
}

function bw_protect_login_image_is_small( $file ) {
	if ( filesize( $file ) > 4 * MB_IN_BYTES ) {
		return false;
	}
	$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return $size && $size[0] > 0 && $size[1] > 0 && ( $size[0] * $size[1] ) <= 2000000;
}

function bw_protect_login_raster_is_light( $file ) {
	$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$src  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $src ) {
		return false;
	}
	$size = 48;
	$img  = imagecreatetruecolor( $size, $size );
	imagealphablending( $img, false );
	imagesavealpha( $img, true );
	imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
	imagecopyresampled( $img, $src, 0, 0, 0, 0, $size, $size, imagesx( $src ), imagesy( $src ) );

	$sum         = 0.0;
	$opaque      = 0;
	$transparent = 0;
	for ( $y = 0; $y < $size; $y++ ) {
		for ( $x = 0; $x < $size; $x++ ) {
			$rgba  = imagecolorat( $img, $x, $y );
			$alpha = ( $rgba >> 24 ) & 0x7F;
			if ( $alpha > 100 ) {
				$transparent++;
				continue;
			}
			$r    = ( $rgba >> 16 ) & 0xFF;
			$g    = ( $rgba >> 8 ) & 0xFF;
			$b    = $rgba & 0xFF;
			$sum += ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
			$opaque++;
		}
	}

	$total = $size * $size;
	if ( 0 === $opaque || $transparent / $total < 0.1 ) {
		return false; // Kein transparenter Hintergrund: nicht anfassen.
	}
	return ( $sum / $opaque ) > 0.85;
}

function bw_protect_login_svg_is_light( $file ) {
	$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $svg ) {
		return false;
	}
	// Possessive Quantoren (*+, ?+): kein Zurueckspringen, auch bei praeparierten Dateien schnell.
	preg_match_all( '/(?:fill|stroke|stop-color)\s*+[:=]\s*+["\']?+\s*+(#[0-9a-f]{3,6}\b|white|black|rgb\([^)]{0,40}\))/i', $svg, $m );
	if ( empty( $m[1] ) ) {
		return false;
	}
	$lums = array();
	foreach ( $m[1] as $color ) {
		$lum = bw_protect_login_color_luminance( $color );
		if ( null !== $lum ) {
			$lums[] = $lum;
		}
	}
	if ( ! $lums ) {
		return false;
	}
	return min( $lums ) > 0.85;
}

function bw_protect_login_color_luminance( $color ) {
	$color = strtolower( trim( $color ) );
	if ( 'white' === $color ) {
		return 1.0;
	}
	if ( 'black' === $color ) {
		return 0.0;
	}
	if ( preg_match( '/^#([0-9a-f]{3})$/', $color, $m ) ) {
		$color = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $color, $m ) ) {
		$r = hexdec( $m[1] );
		$g = hexdec( $m[2] );
		$b = hexdec( $m[3] );
	} elseif ( preg_match( '/rgb\(\s*(\d+)\D+(\d+)\D+(\d+)/', $color, $m ) ) {
		list( , $r, $g, $b ) = $m;
	} else {
		return null;
	}
	return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
}

/* -------------------------------------------------------------------------
 * Body-Klassen, damit Kopf, Einleitung und 2FA-Hinweis zur Site passen
 * ---------------------------------------------------------------------- */

add_filter( 'login_body_class', bw_protect_guard( function ( $classes ) {
	$classes[] = 'bw-protect';
	$mode      = bw_protect_login_logo_mode();
	if ( 'name' === $mode ) {
		$classes[] = 'bw-name';
	} elseif ( 'none' === $mode ) {
		$classes[] = 'bw-nologo';
	} elseif ( bw_protect_login_get_logo() ) {
		$classes[] = 'bw-logo';
	}
	if ( bw_protect_login_email_only() ) {
		$classes[] = 'bw-email-only';
	}
	if ( bw_protect_login_email_2fa() ) {
		$classes[] = 'bw-2fa-email';
	}
	if ( count( bw_protect_login_languages() ) > 1 ) {
		$classes[] = 'bw-has-lang';
	}
	return $classes;
} ) );

/**
 * 2FA per E-Mail-Code in ASE aktiv?
 */
function bw_protect_login_email_2fa() {
	$o = get_option( 'admin_site_enhancements', array() );
	return is_array( $o )
		&& ! empty( $o['two_factor_authentication'] )
		&& ! empty( $o['two_factor_available_providers'] )
		&& in_array( 'email', (array) $o['two_factor_available_providers'], true );
}

/**
 * Login nur per E-Mail (ASE «Login ID Type»)?
 */
function bw_protect_login_email_only() {
	$o = get_option( 'admin_site_enhancements', array() );
	return is_array( $o )
		&& ! empty( $o['login_id_type_restriction'] )
		&& isset( $o['login_id_type'] ) && 'email' === $o['login_id_type'];
}

/* -------------------------------------------------------------------------
 * Logo verlinkt auf die Website statt auf wordpress.org
 * ---------------------------------------------------------------------- */

add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );

add_filter( 'login_headertext', function () {
	return get_bloginfo( 'name', 'display' );
} );

/* -------------------------------------------------------------------------
 * Sprachwahl (nur auf mehrsprachigen Websites)
 * ---------------------------------------------------------------------- */

// Das Auswahlfeld von WordPress faellt weg: Es listet Varianten derselben Sprache
// einzeln auf und verliert beim Wechsel die Adresszusaetze eines eigenen Login-Pfads.
add_filter( 'login_display_language_dropdown', '__return_false', 99 );

/**
 * Sprachen fuer die Sprachwahl: Sprachcode => Locale, je Sprache ein Eintrag.
 *
 * Schalter bw_protect_lang_switcher:
 *   auto  Sprache der Website, installierte Sprachpakete und Englisch, wenn die Website
 *         englisch ist oder ein Mehrsprachigkeits-Plugin Englisch fuehrt. Kommen so
 *         zwei Sprachen zusammen, erscheint die Sprachwahl, immer mit Englisch.
 *   on    Sprachwahl immer, auch auf einsprachigen Websites (Sprache der Website und Englisch).
 *   off   keine Sprachwahl.
 */
function bw_protect_login_languages() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$cached = array();

	$mode = get_option( 'bw_protect_lang_switcher', 'auto' );
	if ( 'off' === $mode ) {
		return $cached;
	}

	$installed = get_available_languages(); // Installierte Sprachpakete.
	$site      = get_locale();
	$current   = determine_locale();

	// Reihenfolge = Vorrang innerhalb einer Sprache: gewaehlte Variante, Website, Rest.
	$locales = array_merge( array( $current, $site ), $installed, bw_protect_login_plugin_locales() );
	if ( 'on' === $mode ) {
		$locales[] = 'en_US';
	}
	$locales = (array) apply_filters( 'bw_protect_login_locales', $locales );

	$found = array();
	foreach ( $locales as $locale ) {
		if ( ! is_string( $locale ) || ! preg_match( '/^[A-Za-z_]{2,20}$/', $locale ) ) {
			continue;
		}
		$code = bw_protect_code( $locale );
		if ( '' === $code || isset( $found[ $code ] ) ) {
			continue;
		}
		// Nur Sprachen, die WordPress auch anzeigen kann: Englisch oder installiertes Paket.
		if ( 'en' !== $code && ! in_array( $locale, $installed, true ) ) {
			continue;
		}
		if ( 'en' === $code && ! in_array( $locale, $installed, true ) ) {
			$locale = 'en_US';
		}
		$found[ $code ] = $locale;
	}

	// Englisch ist immer dabei, sobald es eine Sprachwahl gibt (zwei Sprachen oder mehr).
	if ( count( $found ) >= 2 && ! isset( $found['en'] ) ) {
		$found['en'] = 'en_US';
	}

	// Feste Reihenfolge wie in der Schweiz ueblich, der Rest alphabetisch.
	$order = array( 'de', 'fr', 'it', 'rm', 'en' );
	foreach ( $order as $code ) {
		if ( isset( $found[ $code ] ) ) {
			$cached[ $code ] = $found[ $code ];
			unset( $found[ $code ] );
		}
	}
	ksort( $found );
	$cached = array_merge( $cached, $found );

	if ( count( $cached ) < 2 ) {
		$cached = array();
	}
	return $cached;
}

/**
 * Sprachen aus WPML, Polylang und TranslatePress. Gebraucht wird das fuer Englisch:
 * Dafuer gibt es kein Sprachpaket, an dem man die Sprache erkennen koennte.
 */
function bw_protect_login_plugin_locales() {
	$out = array();

	$wpml = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	if ( is_array( $wpml ) ) {
		foreach ( $wpml as $language ) {
			if ( is_array( $language ) && ! empty( $language['default_locale'] ) ) {
				$out[] = $language['default_locale'];
			}
		}
	}

	if ( function_exists( 'pll_languages_list' ) ) {
		$out = array_merge( $out, (array) pll_languages_list( array( 'fields' => 'locale' ) ) );
	}

	$trp = get_option( 'trp_settings' );
	if ( is_array( $trp ) && ! empty( $trp['translation-languages'] ) ) {
		$out = array_merge( $out, (array) $trp['translation-languages'] );
	}

	return array_filter( $out, 'is_string' );
}

/**
 * Name einer Sprache in dieser Sprache (fuer Screenreader und Tooltip).
 */
function bw_protect_login_language_name( $code ) {
	$names = array(
		'de' => 'Deutsch',
		'fr' => 'Français',
		'it' => 'Italiano',
		'rm' => 'Rumantsch',
		'en' => 'English',
		'es' => 'Español',
		'pt' => 'Português',
		'nl' => 'Nederlands',
	);
	return isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code );
}

add_action( 'login_footer', bw_protect_guard( 'bw_protect_login_language_nav' ) );

/**
 * Abfrage der aktuellen Adresse ohne Sprachwahl, unveraendert uebernommen.
 * Sie wird bewusst nicht zerlegt und neu gebaut: So bleiben auch ungewoehnliche
 * Schluessel (etwa ein Login-Pfad mit Punkt) genau so, wie sie sind.
 */
function bw_protect_login_query_without_lang() {
	$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird mit esc_attr ausgegeben.
	if ( '' === $query || strlen( $query ) > 2000 ) {
		return ''; // Ueberlange Abfragen nicht weitertragen.
	}
	$query = (string) preg_replace( '/[\x00-\x20\x7f]+/', '', $query ); // Steuerzeichen und Leerraum haben in einer Abfrage nichts zu suchen.

	$keep = array();
	foreach ( explode( '&', $query ) as $part ) {
		if ( '' === $part ) {
			continue;
		}
		$pieces = explode( '=', $part, 2 );
		$key    = rawurldecode( $pieces[0] );
		if ( 'wp_lang' === $key || 0 === strpos( $key, 'wp_lang[' ) ) {
			continue;
		}
		$keep[] = $part;
	}
	return implode( '&', $keep );
}

/**
 * Sprachwahl ausgeben: ein Link je Sprache, die aktuelle Sprache hervorgehoben.
 * Die Links behalten alle Adresszusaetze der aktuellen Seite (eigener Login-Pfad,
 * Weiterleitungsziel) und wechseln die Sprache ueber den WordPress-Standard wp_lang.
 * Sie sind relativ («?...») und zeigen deshalb nie auf einen anderen Server.
 */
function bw_protect_login_language_nav() {
	global $interim_login;
	if ( ! empty( $interim_login ) ) {
		return; // Anmeldefenster bei abgelaufener Sitzung.
	}

	$languages = bw_protect_login_languages();
	if ( count( $languages ) < 2 ) {
		return;
	}

	$t       = bw_protect_texts();
	$current = bw_protect_code( determine_locale() );

	// Die Links bestehen nur aus der Abfrage («?...») und bleiben damit immer auf der aktuellen
	// Seite. Der Pfad der Anfrage wird bewusst nicht uebernommen: Ein praeparierter Pfad wie
	// «//fremde-domain/...» wuerde sonst zu einem Link auf einen fremden Server.
	$base = bw_protect_login_query_without_lang();

	echo '<nav class="bw-lang" aria-label="' . esc_attr( $t['nav'] ) . '"><ul>';
	foreach ( $languages as $code => $locale ) {
		$name = bw_protect_login_language_name( $code );
		if ( $code === $current ) {
			printf(
				'<li><span class="bw-lang-item is-current" aria-current="true">%1$s<span class="screen-reader-text" lang="%2$s"> %3$s</span></span></li>',
				esc_html( strtoupper( $code ) ),
				esc_attr( $code ),
				esc_html( $name )
			);
		} else {
			printf(
				'<li><a class="bw-lang-item" href="%1$s" hreflang="%2$s" title="%3$s">%4$s<span class="screen-reader-text" lang="%2$s"> %5$s</span></a></li>',
				esc_attr( '?' . $base . ( '' !== $base ? '&' : '' ) . 'wp_lang=' . rawurlencode( $locale ) ),
				esc_attr( $code ),
				esc_attr( $name ),
				esc_html( strtoupper( $code ) ),
				esc_html( $name )
			);
		}
	}
	echo '</ul></nav>';
}

/* -------------------------------------------------------------------------
 * Anmeldefenster bei abgelaufener Sitzung: Code-Schritt der 2FA
 * ---------------------------------------------------------------------- */

// Nach einem falschen Code oder «Code erneut senden» gibt die 2FA die Code-Seite aus, bevor
// WordPress den Fenster-Modus setzt (wp-login.php setzt $interim_login erst nach der Aktion
// login_form_...). Ohne diesen Schritt fehlt der Seite die Klasse «interim-login» und im
// Fenster erscheint die ganze Login-Seite. Gesetzt wird derselbe Wert, den WordPress
// danach selbst setzen wuerde.
// Registriert wird erst auf login_init und nur, wenn die 2FA die Aktion selbst kennt:
// WordPress prueft vorher mit has_filter(), ob eine Aktion gueltig ist, und diese Pruefung
// soll durch das Plugin nicht anders ausfallen.
add_action(
	'login_init',
	bw_protect_guard(
		function () {
			if ( false === has_action( 'login_form_validate_2fa' ) ) {
				return;
			}
			add_action(
				'login_form_validate_2fa',
				bw_protect_guard(
					function () {
						if ( isset( $_REQUEST['interim-login'] ) && empty( $GLOBALS['interim_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							$GLOBALS['interim_login'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						}
					}
				),
				0
			);
		}
	)
);

/* -------------------------------------------------------------------------
 * Eigene Texte auf der Login-Seite (nur wp-login.php)
 * ---------------------------------------------------------------------- */

// Erst auf wp-login.php registrieren: auf allen anderen Seiten laeuft davon nichts.
add_action( 'login_init', function () {
	add_filter( 'gettext', bw_protect_guard( 'bw_protect_login_gettext' ), 20, 3 );
	add_filter( 'gettext_with_context', bw_protect_guard( 'bw_protect_login_gettext_context' ), 20, 4 );
	add_filter( 'ngettext', bw_protect_guard( 'bw_protect_login_ngettext' ), 20, 5 );

	// Hinweis zu den Passwortregeln auf der Seite «Neues Passwort». ASE Pro baut ihn aus
	// seinen Regeln. Die Regeln werden hier nur mitgelesen, der Hinweis wird danach in der
	// Sprache der Login-Seite neu formuliert.
	add_filter(
		'asenha_password_policy_hint_parts',
		bw_protect_guard(
			function ( $parts, $policy = array() ) {
				if ( is_array( $policy ) ) {
					$GLOBALS['bw_protect_pw_policy'] = $policy;
				}
				return $parts;
			}
		),
		9999,
		2
	);
	add_filter( 'password_hint', bw_protect_guard( 'bw_protect_login_password_hint' ), 9999 );
} );

function bw_protect_login_on_login_page() {
	return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
}

function bw_protect_login_gettext( $translation, $text, $domain ) {
	if ( ! bw_protect_login_on_login_page() ) {
		return $translation;
	}

	if ( 'default' === $domain ) {
		if ( 'Username or Email Address' === $text && bw_protect_login_email_only() ) {
			$t = bw_protect_texts();
			return $t['email_label'];
		}
		// Meldungen von WordPress in der du-Form, passend zu den eigenen Texten (nur Deutsch).
		static $core = null;
		if ( null === $core ) {
			$core = bw_protect_core_du();
			if ( bw_protect_login_email_only() ) {
				$key          = 'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.';
				$core[ $key ] = str_replace( 'deinen Benutzernamen oder deine E-Mail-Adresse', 'deine E-Mail-Adresse', $core[ $key ] );
				$key          = 'Unknown email address. Check again or try your username.';
				$core[ $key ] = str_replace( ' oder versuche es mit deinem Benutzernamen', '', $core[ $key ] );
			}
		}
		// Erst der guenstige Abgleich, dann die Sprache: der Filter laeuft bei jedem Text der Seite.
		if ( isset( $core[ $text ] ) && 'de' === bw_protect_lang() ) {
			return $core[ $text ];
		}
		return $translation;
	}

	if ( 'admin-site-enhancements' !== $domain ) {
		return $translation;
	}

	// Sperre nach zu vielen Fehlversuchen: ASE Pro liefert die Restdauer nur auf Englisch.
	// Steht sie als Zahl bereit, wird sie in der Sprache der Seite eingesetzt.
	if ( '<b>WARNING:</b> You\'ve been locked out. You can login again in %s.' === $text ) {
		$t    = bw_protect_texts();
		$left = isset( $GLOBALS['asenha_limit_login']['lockout_period_remaining'] ) ? (int) $GLOBALS['asenha_limit_login']['lockout_period_remaining'] : 0;
		if ( $left > 0 ) {
			$now = time();
			return str_replace( '%', '%%', sprintf( $t['lockout'], human_time_diff( $now, $now + $left ) ) );
		}
		return $t['lockout_later']; // Ohne Zahl lieber keine Dauer als eine englische.
	}

	static $maps = array();
	$lang = bw_protect_lang();
	if ( ! isset( $maps[ $lang ] ) ) {
		$t             = bw_protect_texts();
		$needs         = $t['pw_intro'] . ' %s.';
		$maps[ $lang ] = array(
			'A verification code has been sent to the email address associated with your account.' => bw_protect_login_email_prompt(),
			'Verification Code:'        => $t['code_label'],
			'Verify'                    => $t['verify'],
			'Resend Code'               => $t['resend'],
			'Having problems?'          => $t['problems'],
			'Use a recovery code'       => $t['use_backup'],
			'Send a code to your email' => $t['send_code'],
			'Enter a recovery code.'    => $t['backup_prompt'],
			'Recovery Code:'            => $t['backup_label'],
			'<strong>Error:</strong> Invalid email or incorrect password.' => $t['error'],
			'Error:'                    => $t['error_label'],
			'Invalid username/email or incorrect password.' => bw_protect_login_email_only() ? $t['error_login'] : $t['error_login_user'],
			'ERROR: Invalid code.'      => $t['code_invalid'],
			'ERROR: Too many invalid codes, you can try again in %s. This limit protects your account against automated attacks.' => $t['code_locked'],
			'There have been too many failed two-factor authentication attempts, which often indicates that the password has been compromised. The password has been reset in order to protect the account.' => $t['forced_reset'],
			'If you are the owner of this account, please check your email for instructions on regaining access.' => $t['forced_reset_hint'],
			'The password must include an uppercase letter.' => sprintf( $needs, $t['pw_upper'] ),
			'The password must include a lowercase letter.' => sprintf( $needs, $t['pw_lower'] ),
			'The password must include a digit.' => sprintf( $needs, $t['pw_digit'] ),
			'The password must include a special character.' => sprintf( $needs, $t['pw_special'] ),
		);
	}

	return isset( $maps[ $lang ][ $text ] ) ? $maps[ $lang ][ $text ] : $translation;
}

/**
 * Meldungen von ASE Pro mit Einzahl und Mehrzahl.
 */
function bw_protect_login_ngettext( $translation, $single, $plural, $number, $domain ) {
	if ( 'admin-site-enhancements' !== $domain || ! bw_protect_login_on_login_page() ) {
		return $translation;
	}
	$t = bw_protect_texts();
	if ( 0 === strpos( $single, 'WARNING: Your account has attempted to login %1$s time without providing a valid two factor token.' ) ) {
		return 1 === (int) $number ? $t['warn_one'] : $t['warn_many'];
	}
	switch ( $single ) {
		case 'The password must be at least %d character long.':
			return $t['pw_intro'] . ' ' . $t[ 1 === (int) $number ? 'pw_len_one' : 'pw_len' ] . '.';
		case 'The password must contain at least %d unique character.':
			return $t['pw_intro'] . ' ' . $t[ 1 === (int) $number ? 'pw_unique_one' : 'pw_unique' ] . '.';
		case 'The password must not be longer than %d character.':
			return $t['pw_max'];
	}
	return $translation;
}

/* -------------------------------------------------------------------------
 * Hinweis zu den Passwortregeln auf der Seite «Neues Passwort»
 * ---------------------------------------------------------------------- */

function bw_protect_login_password_hint( $hint ) {
	if ( empty( $GLOBALS['bw_protect_pw_policy'] ) || ! is_array( $GLOBALS['bw_protect_pw_policy'] ) ) {
		return $hint;
	}
	$policy = $GLOBALS['bw_protect_pw_policy'];
	$t      = bw_protect_texts();
	$items  = array();

	$min = isset( $policy['min_length'] ) ? absint( $policy['min_length'] ) : 0;
	if ( $min > 0 ) {
		$items[] = sprintf( $t[ 1 === $min ? 'pw_len_one' : 'pw_len' ], $min );
	}
	foreach ( array( 'require_uppercase' => 'pw_upper', 'require_lowercase' => 'pw_lower', 'require_digit' => 'pw_digit', 'require_special' => 'pw_special' ) as $rule => $key ) {
		if ( ! empty( $policy[ $rule ] ) ) {
			$items[] = $t[ $key ];
		}
	}
	$unique = isset( $policy['min_unique_chars'] ) ? absint( $policy['min_unique_chars'] ) : 0;
	if ( $unique > 1 ) { // Ein einziges verschiedenes Zeichen ist keine Regel.
		$items[] = sprintf( $t['pw_unique'], $unique );
	}
	if ( ! $items ) {
		return $hint;
	}

	$last = array_pop( $items );
	$list = $items ? implode( ', ', $items ) . ' ' . $t['pw_and'] . ' ' . $last : $last;

	return esc_html( $t['pw_intro'] . ' ' . $list . '.' );
}

function bw_protect_login_gettext_context( $translation, $text, $context, $domain ) {
	if ( bw_protect_login_on_login_page() && 'default' === $domain && 'site' === $context && '&larr; Go to %s' === $text ) {
		$t = bw_protect_texts();
		return $t['back'];
	}
	return $translation;
}

/**
 * Hinweis zum E-Mail-Code inkl. Gueltigkeit aus den ASE-Einstellungen.
 */
function bw_protect_login_email_prompt() {
	$t       = bw_protect_texts();
	$text    = $t['prompt'];
	$minutes = 0;
	$o       = get_option( 'admin_site_enhancements', array() );
	if ( is_array( $o ) && ! empty( $o['two_factor_email_token_ttl'] ) && is_numeric( $o['two_factor_email_token_ttl'] ) ) {
		$minutes = (int) round( (int) $o['two_factor_email_token_ttl'] / 60 );
	}
	if ( $minutes > 0 ) {
		$text .= sprintf( $t['prompt_ttl'], $minutes );
	}
	return $text;
}
