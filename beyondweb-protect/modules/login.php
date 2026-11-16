<?php
/**
 * Beyondweb Protect, Modul «Login»
 *
 * Einheitliches Login-Design: Kundenlogo automatisch, deutsche Texte fuer die
 * 2FA-Anmeldung von ASE Pro, Fusszeile «Technische Betreuung durch Beyondweb GmbH».
 *
 * Optionen (per WP-CLI):
 *   bw_protect_logo        auto | name | none (Kopf ueber der Karte)
 *   bw_protect_logo_id     Attachment-ID, ueberschreibt die automatische Logo-Suche
 *   bw_protect_logo_light  'yes' / 'no' erzwingt das Einfaerben (hell -> dunkel)
 */

defined( 'ABSPATH' ) || exit;


/* -------------------------------------------------------------------------
 * Stylesheet und Logo
 * ---------------------------------------------------------------------- */

add_action( 'login_enqueue_scripts', 'bw_protect_login_enqueue', 20 );

function bw_protect_login_enqueue() {
	wp_enqueue_style(
		'beyondweb-protect-login',
		plugins_url( 'assets/login/login.css', BW_PROTECT_DIR . '/beyondweb-protect.php' ),
		array( 'login' ),
		BW_PROTECT_VERSION
	);

	$logo = 'auto' === bw_protect_login_logo_mode() ? bw_protect_login_get_logo() : null;
	if ( ! $logo ) {
		return;
	}

	$css  = 'body.login{';
	$css .= '--bw-logo-img:url("' . esc_url_raw( $logo['url'] ) . '");';
	$css .= '--bw-logo-w:' . (int) $logo['width'] . 'px;--bw-logo-h:' . (int) $logo['height'] . 'px;';
	if ( ! empty( $logo['light'] ) ) {
		$css .= '--bw-logo-filter:brightness(0);';
	}
	$css .= '}';

	wp_add_inline_style( 'beyondweb-protect-login', $css );
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
 * 1. Option bw_protect_login_logo_id (manuell, z. B. per WP-CLI)
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
	if ( $flatsome && ! is_numeric( $flatsome ) && wp_http_validate_url( $flatsome ) ) {
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
	if ( ! wp_http_validate_url( $url ) ) {
		return false;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	return $host && $home && strtolower( $host ) === strtolower( $home );
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
	$box_w = (int) round( $max_h * $ratio );
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
		return filesize( $file ) <= MB_IN_BYTES ? $file : '';
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
	imagedestroy( $img );
	imagedestroy( $src );

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
	preg_match_all( '/(?:fill|stroke|stop-color)\s*[:=]\s*["\']?\s*(#[0-9a-f]{3,6}\b|white|black|rgb\([^)]*\))/i', $svg, $m );
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
 * Body-Klassen, damit Einleitung und 2FA-Hinweis zur Site passen
 * ---------------------------------------------------------------------- */

add_filter( 'login_body_class', function ( $classes ) {
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
	return $classes;
} );

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
 * Deutsche Texte auf der Login-Seite (nur wp-login.php)
 * ---------------------------------------------------------------------- */

// Erst auf wp-login.php registrieren: auf allen anderen Seiten laeuft davon nichts.
add_action( 'login_init', function () {
	add_filter( 'gettext', 'bw_protect_login_gettext', 20, 3 );
	add_filter( 'gettext_with_context', 'bw_protect_login_gettext_context', 20, 4 );
} );

function bw_protect_login_on_login_page() {
	return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
}

function bw_protect_login_gettext( $translation, $text, $domain ) {
	if ( ! bw_protect_login_on_login_page() ) {
		return $translation;
	}

	if ( 'default' === $domain && 'Username or Email Address' === $text && bw_protect_login_email_only() ) {
		return 'E-Mail-Adresse';
	}

	if ( 'admin-site-enhancements' !== $domain ) {
		return $translation;
	}

	static $map = null;
	if ( null === $map ) {
		$map = array(
		'A verification code has been sent to the email address associated with your account.' => bw_protect_login_email_prompt(),
		'Verification Code:'                                => 'Code aus der E-Mail',
		'Verify'                                            => 'Bestätigen',
		'Resend Code'                                       => 'Code erneut senden',
		'Having problems?'                                  => 'Probleme?',
		'Use a recovery code'                               => 'Backup-Code verwenden',
		'Send a code to your email'                         => 'Code per E-Mail senden',
		'Enter a recovery code.'                            => 'Gib einen deiner Backup-Codes ein. Jeder Code funktioniert nur einmal.',
		'Recovery Code:'                                    => 'Backup-Code',
		'<strong>Error:</strong> Invalid email or incorrect password.' => '<strong>Fehler:</strong> E-Mail-Adresse oder Passwort ist nicht korrekt.',
		);
	}

	return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
}

function bw_protect_login_gettext_context( $translation, $text, $context, $domain ) {
	if ( bw_protect_login_on_login_page() && 'default' === $domain && 'site' === $context && '&larr; Go to %s' === $text ) {
		return 'Zurück zur Website';
	}
	return $translation;
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

/**
 * Hinweis zum E-Mail-Code inkl. Gueltigkeit aus den ASE-Einstellungen.
 */
function bw_protect_login_email_prompt() {
	$text    = 'Wir haben dir einen Code an deine E-Mail-Adresse geschickt.';
	$minutes = 0;
	$o       = get_option( 'admin_site_enhancements', array() );
	if ( is_array( $o ) && ! empty( $o['two_factor_email_token_ttl'] ) && is_numeric( $o['two_factor_email_token_ttl'] ) ) {
		$minutes = (int) round( (int) $o['two_factor_email_token_ttl'] / 60 );
	}
	if ( $minutes > 0 ) {
		$text .= sprintf( ' Dieser ist %d Minuten gültig.', $minutes );
	}
	return $text;
}
