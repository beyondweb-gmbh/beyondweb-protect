<?php
/**
 * Beyondweb Protect, Modul «REST»
 *
 * Die Benutzerliste der REST-Schnittstelle (/wp/v2/users) gibt es nur fuer
 * angemeldete Benutzer. Alle anderen Routen bleiben unveraendert erreichbar.
 * Andere Wege zu Benutzernamen (Autorenarchive, Feeds, oEmbed) deckt das Modul nicht ab.
 * Angemeldete Zugriffe (Cookie mit Nonce, Application Passwords) sind nicht betroffen.
 */

defined( 'ABSPATH' ) || exit;

// Zweimal eingehaengt: frueh, damit normalerweise gar nichts weiter laeuft, und ganz am
// Schluss, damit ein fremder Filter (etwa ein REST-Cache) die Sperre nicht ueberschreibt.
add_filter( 'rest_pre_dispatch', bw_protect_guard( 'bw_protect_rest_users' ), 5, 3 );
add_filter( 'rest_pre_dispatch', bw_protect_guard( 'bw_protect_rest_users' ), PHP_INT_MAX, 3 );

function bw_protect_rest_users( $result, $server, $request ) {
	if ( ! ( $request instanceof WP_REST_Request ) || is_user_logged_in() ) {
		return $result;
	}

	// OPTIONS liefert nur die Beschreibung der Route, keine Benutzer. Browser fragen so
	// vorab an (CORS), bevor sie sich anmelden koennen.
	if ( 'OPTIONS' === $request->get_method() ) {
		return $result;
	}

	// WordPress vergleicht Routen ohne Ruecksicht auf Gross- und Kleinschreibung.
	$route = strtolower( '/' . trim( (string) $request->get_route(), '/' ) );
	if ( ! preg_match( '#^/wp/v2/users(/|$)#', $route ) ) {
		return $result;
	}

	// Als fertige Antwort zurueckgeben: Ein rohes WP_Error vertraegt der Batch-Endpunkt von WordPress nicht.
	return rest_convert_error_to_response(
		new WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.' ), // Text und Uebersetzung von WordPress.
			array( 'status' => 401 )
		)
	);
}
