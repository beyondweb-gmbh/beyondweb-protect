<?php
/**
 * Beyondweb Protect, Modul «Mail»
 *
 * Schlichte, gut lesbare Mails ohne Farben und Bilder fuer fuenf Faelle:
 * Anmeldecode (2FA von ASE Pro), Passwort zuruecksetzen, neues Konto,
 * Passwort geaendert, E-Mail-Adresse geaendert.
 * Das Modul verschickt selbst nichts. Es ersetzt nur Betreff und Inhalt der Mails,
 * die ASE Pro und WordPress ohnehin versenden.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Gemeinsame Bausteine
 * ---------------------------------------------------------------------- */

/**
 * Locale einer Mail (z. B. fr_FR). Daraus ergibt sich die Sprache de, fr, it oder en.
 *
 * @param int  $user_id     Empfaenger.
 * @param bool $use_request Sprachwahl der Login-Seite beruecksichtigen. Nur sinnvoll,
 *                          wenn der Empfaenger die Mail selbst ausgeloest hat.
 */
function bw_protect_mail_locale( $user_id, $use_request ) {
	if ( $use_request ) {
		foreach ( array( $_GET, $_COOKIE ) as $source ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $source['wp_lang'] ) || ! is_string( $source['wp_lang'] ) ) {
				continue;
			}
			$locale = sanitize_locale_name( wp_unslash( $source['wp_lang'] ) );
			if ( '' !== $locale && ( 'en_US' === $locale || in_array( $locale, get_available_languages(), true ) ) ) {
				return $locale;
			}
		}
	}
	return get_user_locale( (int) $user_id );
}

function bw_protect_mail_lang( $user_id, $use_request ) {
	return bw_protect_lang( bw_protect_mail_locale( $user_id, $use_request ) );
}

/**
 * Name der Website als reiner Text.
 */
function bw_protect_mail_site_name() {
	$name = wp_strip_all_tags( wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) );
	return trim( (string) preg_replace( '/\s+/', ' ', $name ) ); // Keine Zeilenumbrueche im Betreff.
}

/**
 * Domain der Website fuer die Fusszeile.
 */
function bw_protect_mail_site_host() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return is_string( $host ) ? $host : '';
}

/**
 * Rahmen aller Mails. $rows sind fertige, bereits maskierte HTML-Bloecke.
 */
function bw_protect_mail_html( array $rows, $lang ) {
	$t    = bw_protect_texts( $lang );
	$font = "font-family:-apple-system,'Segoe UI',Arial,Helvetica,sans-serif;";

	$html  = '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="UTF-8">';
	$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
	$html .= '<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">';
	$html .= '</head><body style="margin:0;padding:0;background:#ffffff;">';
	$html .= '<div style="margin:0;padding:24px 20px;background:#ffffff;">';
	$html .= '<div style="max-width:460px;margin:0;' . $font . 'font-size:16px;line-height:1.5;color:#111111;">';
	$html .= implode( '', $rows );
	$html .= '<p style="margin:0;padding-top:14px;border-top:1px solid #dddddd;font-size:13px;line-height:1.5;color:#666666;">';
	$host  = bw_protect_mail_site_host();
	$html .= ( '' !== $host ? esc_html( sprintf( $t['mail_auto'], $host ) ) . '<br>' : '' ) . esc_html( $t['footer'] );
	$html .= '</p></div></div></body></html>';

	return $html;
}

function bw_protect_mail_p( $html, $margin = 20 ) {
	return '<p style="margin:0 0 ' . (int) $margin . 'px;">' . $html . '</p>';
}

/**
 * Hervorgehobener Kasten: fuer den Code und fuer Adressen zum Kopieren.
 */
function bw_protect_mail_box( $text, $big, $mask = false ) {
	$style = 'margin:0 0 20px;padding:14px 18px;border:1px solid #c8c8c8;border-radius:8px;background:#f4f4f4;color:#111111;'
		. "font-family:ui-monospace,'SF Mono',Menlo,Consolas,'Liberation Mono',monospace;";
	$style .= $big
		? 'font-size:34px;line-height:1.2;font-weight:700;letter-spacing:3px;'
		: 'font-size:13px;line-height:1.5;word-break:break-all;';
	return '<p style="' . $style . '">' . ( $mask ? bw_protect_mail_mask( esc_html( $text ) ) : esc_html( $text ) ) . '</p>';
}

/**
 * WordPress ersetzt in den Hinweisen «Passwort geaendert» und «E-Mail-Adresse geaendert»
 * nachtraeglich Platzhalter wie ###ADMIN_EMAIL###. Damit ein eingesetzter Wert (neue Adresse,
 * Name der Website) keinen solchen Platzhalter bilden kann, wird das Rautezeichen als
 * HTML-Zeichenreferenz ausgegeben. In der Mail sieht es gleich aus. Ausgenommen ist das
 * Rautezeichen direkt nach «&»: Es gehoert zu einer Zeichenreferenz, die esc_html() selbst
 * erzeugt hat (z. B. &#039; fuer den Apostroph). Drei Rautezeichen in Folge bleiben so nie stehen.
 */
function bw_protect_mail_mask( $html ) {
	$masked = preg_replace( '/(?<!&)#/', '&#35;', (string) $html );
	return null === $masked ? str_replace( '#', '&#35;', (string) $html ) : $masked;
}

/**
 * Link als schlichter Knopf ohne Farbe.
 */
function bw_protect_mail_button( $url, $label ) {
	return '<p style="margin:0 0 20px;"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 20px;border:1px solid #111111;border-radius:8px;'
		. 'background:#ffffff;color:#111111;font-weight:700;text-decoration:none;">' . esc_html( $label ) . '</a></p>';
}

/**
 * Mail als HTML kennzeichnen. Vorhandene Kopfzeilen bleiben erhalten.
 */
function bw_protect_mail_headers( $headers ) {
	$list = array();
	foreach ( (array) $headers as $header ) {
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $header ) as $line ) {
			if ( '' !== trim( $line ) && 0 !== stripos( trim( $line ), 'content-type:' ) ) {
				$list[] = trim( $line );
			}
		}
	}
	$list[] = 'Content-Type: text/html; charset=UTF-8';
	return $list;
}

/* -------------------------------------------------------------------------
 * Anmeldecode (2FA per E-Mail von ASE Pro)
 * ---------------------------------------------------------------------- */

add_filter( 'two_factor_token_email_subject', bw_protect_guard( 'bw_protect_mail_code_subject' ), 20, 2 );
add_filter( 'two_factor_token_email_message', bw_protect_guard( 'bw_protect_mail_code_message' ), 20, 3 );

function bw_protect_mail_code_subject( $subject, $user_id = 0 ) {
	$t = bw_protect_texts( bw_protect_mail_lang( $user_id, true ) );
	return sprintf( $t['mail_code_subject'], bw_protect_mail_site_name() );
}

function bw_protect_mail_code_message( $message, $token = '', $user_id = 0 ) {
	$token = preg_replace( '/[^0-9A-Za-z]/', '', (string) $token );
	if ( '' === $token ) {
		return $message; // Kein Code erkannt: Original von ASE unveraendert lassen.
	}

	$lang = bw_protect_mail_lang( $user_id, true );
	$t    = bw_protect_texts( $lang );

	$minutes = 0;
	$options = get_option( 'admin_site_enhancements', array() );
	if ( is_array( $options ) && ! empty( $options['two_factor_email_token_ttl'] ) && is_numeric( $options['two_factor_email_token_ttl'] ) ) {
		$minutes = (int) round( (int) $options['two_factor_email_token_ttl'] / 60 );
	}

	$rows = array(
		bw_protect_mail_p( esc_html( sprintf( $t['mail_code_intro'], bw_protect_mail_site_name() ) ), 6 ),
		bw_protect_mail_box( $token, true ),
		bw_protect_mail_p( esc_html( $minutes > 0 ? sprintf( $t['mail_code_valid'], $minutes ) : $t['mail_code_enter'] ) ),
		bw_protect_mail_p( '<strong>' . esc_html( $t['mail_code_q'] ) . '</strong> ' . esc_html( $t['mail_code_a'] ), 24 ),
	);

	$html = bw_protect_mail_html( $rows, $lang );

	// Merken, damit genau diese Mail beim Versand als HTML gekennzeichnet wird.
	$GLOBALS['bw_protect_mail_pending'] = md5( $html );

	return $html;
}

add_filter( 'wp_mail', bw_protect_guard( 'bw_protect_mail_mark_html' ), 20 );

/**
 * Kennzeichnet die Code-Mail als HTML, falls der Absender das nicht selbst tut.
 * Greift nur bei der Mail, die dieses Modul unmittelbar davor erzeugt hat.
 */
function bw_protect_mail_mark_html( $args ) {
	if ( empty( $GLOBALS['bw_protect_mail_pending'] ) || ! is_array( $args ) || ! isset( $args['message'] ) || ! is_string( $args['message'] ) ) {
		return $args;
	}
	if ( md5( $args['message'] ) === $GLOBALS['bw_protect_mail_pending'] ) {
		$args['headers'] = bw_protect_mail_headers( isset( $args['headers'] ) ? $args['headers'] : '' );
		unset( $GLOBALS['bw_protect_mail_pending'] );
	}
	return $args;
}

/* -------------------------------------------------------------------------
 * Passwort zuruecksetzen
 * ---------------------------------------------------------------------- */

add_filter( 'retrieve_password_notification_email', bw_protect_guard( 'bw_protect_mail_reset' ), 20, 4 );

function bw_protect_mail_reset( $email, $key = '', $user_login = '', $user = null ) {
	if ( ! is_array( $email ) || ! is_string( $key ) || '' === $key || ! ( $user instanceof WP_User ) ) {
		return $email;
	}

	// Sprachwahl der Login-Seite nur, wenn nicht jemand anderes angemeldet den Link ausloest.
	$own    = ! is_user_logged_in() || get_current_user_id() === (int) $user->ID;
	$locale = bw_protect_mail_locale( $user->ID, $own );
	$lang   = bw_protect_lang( $locale );
	$t      = bw_protect_texts( $lang );
	$name   = bw_protect_mail_site_name();
	$url    = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' )
		. '&wp_lang=' . rawurlencode( $locale );

	$rows = array(
		bw_protect_mail_p( esc_html( sprintf( $t['mail_reset_intro'], $name ) ) ),
		bw_protect_mail_button( $url, $t['mail_reset_cta'] ),
		bw_protect_mail_p( esc_html( $t['mail_link_hint'] ), 6 ),
		bw_protect_mail_box( $url, false ),
		bw_protect_mail_p( '<strong>' . esc_html( $t['mail_reset_q'] ) . '</strong> ' . esc_html( $t['mail_reset_a'] ), 24 ),
	);

	$email['subject'] = sprintf( $t['mail_reset_subject'], $name );
	$email['message'] = bw_protect_mail_html( $rows, $lang );
	$email['headers'] = bw_protect_mail_headers( isset( $email['headers'] ) ? $email['headers'] : '' );

	return $email;
}

/* -------------------------------------------------------------------------
 * Neues Konto
 * ---------------------------------------------------------------------- */

add_filter( 'wp_new_user_notification_email', bw_protect_guard( 'bw_protect_mail_new_user' ), 20, 2 );

function bw_protect_mail_new_user( $email, $user = null ) {
	if ( ! is_array( $email ) || empty( $email['message'] ) || ! ( $user instanceof WP_User ) ) {
		return $email;
	}

	// WordPress gibt den Schluessel nicht weiter: Link aus dem Originaltext uebernehmen.
	// Ohne erkennbaren Link bleibt die Mail unveraendert.
	if ( ! preg_match( '#https?://[^\s<>"]+action=rp[^\s<>"]*#', (string) $email['message'], $match ) ) {
		return $email;
	}
	$url = $match[0];
	if ( ! wp_http_validate_url( $url ) || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( network_site_url(), PHP_URL_HOST ) ) {
		return $email;
	}

	$locale = bw_protect_mail_locale( $user->ID, false );
	$lang   = bw_protect_lang( $locale );
	$t      = bw_protect_texts( $lang );
	$name   = bw_protect_mail_site_name();

	// Seite «Passwort festlegen» in der Sprache der Mail oeffnen.
	if ( false === strpos( $url, 'wp_lang=' ) ) {
		$url .= '&wp_lang=' . rawurlencode( $locale );
	}

	$login = function_exists( 'bw_protect_login_email_only' ) && bw_protect_login_email_only()
		? sprintf( $t['mail_new_email'], $user->user_email )
		: sprintf( $t['mail_new_user'], $user->user_login );

	$rows = array(
		bw_protect_mail_p( esc_html( sprintf( $t['mail_new_intro'], $name ) ) ),
		bw_protect_mail_p( esc_html( $login ) ),
		bw_protect_mail_button( $url, $t['mail_new_cta'] ),
		bw_protect_mail_p( esc_html( $t['mail_link_hint'] ), 6 ),
		bw_protect_mail_box( $url, false ),
	);

	// WordPress schickt den Betreff noch durch sprintf: Prozentzeichen maskieren.
	$email['subject'] = str_replace( '%', '%%', sprintf( $t['mail_new_subject'], $name ) );
	$email['message'] = bw_protect_mail_html( $rows, $lang );
	$email['headers'] = bw_protect_mail_headers( isset( $email['headers'] ) ? $email['headers'] : '' );

	return $email;
}

/* -------------------------------------------------------------------------
 * Hinweise an das Konto: Passwort geaendert, E-Mail-Adresse geaendert
 * ---------------------------------------------------------------------- */

add_filter( 'password_change_email', bw_protect_guard( 'bw_protect_mail_password_changed' ), 20, 2 );
add_filter( 'email_change_email', bw_protect_guard( 'bw_protect_mail_email_changed' ), 20, 3 );

/**
 * Gemeinsamer Aufbau beider Hinweise. $user ist der bisherige Datensatz des Kontos
 * als Array, so wie WordPress ihn an die Filter gibt.
 */
function bw_protect_mail_account_notice( $email, $user, $kind, $new_address = '' ) {
	if ( ! is_array( $email ) || ! is_array( $user ) || empty( $user['ID'] ) ) {
		return $email;
	}

	$lang  = bw_protect_mail_lang( (int) $user['ID'], false );
	$t     = bw_protect_texts( $lang );
	$name  = bw_protect_mail_site_name();
	$admin = sanitize_email( (string) get_option( 'admin_email' ) );
	$rows  = array( bw_protect_mail_p( bw_protect_mail_mask( esc_html( sprintf( $t[ 'mail_' . $kind . '_intro' ], $name ) ) ), '' !== $new_address ? 6 : 20 ) );

	if ( '' !== $new_address ) {
		$rows[] = bw_protect_mail_box( $new_address, false, true );
	}
	if ( '' !== $admin ) {
		$rows[] = bw_protect_mail_p( '<strong>' . esc_html( $t['mail_notme_q'] ) . '</strong> ' . esc_html( $t['mail_notme_a'] ), 6 );
		$rows[] = bw_protect_mail_box( $admin, false, true );
	}

	// WordPress schickt den Betreff noch durch sprintf: Prozentzeichen maskieren.
	$email['subject'] = str_replace( '%', '%%', sprintf( $t[ 'mail_' . $kind . '_subject' ], $name ) );
	$email['message'] = bw_protect_mail_html( $rows, $lang );
	$email['headers'] = bw_protect_mail_headers( isset( $email['headers'] ) ? $email['headers'] : '' );

	return $email;
}

function bw_protect_mail_password_changed( $email, $user = null ) {
	return bw_protect_mail_account_notice( $email, $user, 'pwc' );
}

function bw_protect_mail_email_changed( $email, $user = null, $userdata = null ) {
	$new = is_array( $userdata ) && isset( $userdata['user_email'] ) ? sanitize_email( (string) $userdata['user_email'] ) : '';
	if ( '' === $new ) {
		return $email; // Ohne neue Adresse bleibt die Mail von WordPress.
	}
	return bw_protect_mail_account_notice( $email, $user, 'emc', $new );
}
