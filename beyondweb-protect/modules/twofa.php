<?php
/**
 * Beyondweb Protect, Modul «TwoFA»
 *
 * ASE Pro schaltet den Anmeldecode per E-Mail fuer ein Konto erst ein, wenn dessen Profil im
 * Backend geladen wird. Bis dahin meldet sich die Person ohne Code an und landet je nach Frist
 * auf ihrem Profil, mit englischen Hinweisen. Dieses Modul zieht den Schritt vor:
 *
 * 1. Bei der Anmeldung auf wp-login.php, sobald das Passwort stimmt. ASE Pro verlangt den Code
 *    damit schon bei dieser Anmeldung.
 * 2. Bei einer offenen Sitzung im Backend, bevor ASE Pro aufs Profil umleitet.
 *
 * Das Modul entscheidet nichts selbst. Ob die Pflicht fuer ein Konto gilt, bestimmt ASE Pro
 * (2FA aktiv, E-Mail als Methode, Option «Enable the email method by default», Rolle in der Pflichtliste),
 * und eingeschaltet wird ueber die Funktion von ASE Pro. Es kommt nur ein Faktor dazu, entfernt
 * oder umgestellt wird nichts. Fehlt ASE Pro oder aendert es seine Funktionen, bleibt das Modul
 * wirkungslos und es gilt wieder der Ablauf von ASE Pro.
 */

defined( 'ABSPATH' ) || exit;

// Direkt nach der Passwortpruefung von WordPress (Prioritaet 20) und vor der Stelle, an der die
// 2FA die Anmelde-Cookies zurueckhaelt: ASE Pro 9.3.3 bei PHP_INT_MAX, das zugrunde liegende
// Plugin «Two Factor» in neueren Versionen bei 31. So gilt der Code in beiden Faellen schon
// fuer diese Anmeldung.
add_filter( 'authenticate', bw_protect_guard( 'bw_protect_twofa_on_login' ), 25 );

// Vor der Umleitung aufs Profil (ASE Pro, admin_init mit Prioritaet 0).
add_action( 'admin_init', bw_protect_guard( 'bw_protect_twofa_on_admin' ), -10 );

function bw_protect_twofa_on_login( $user ) {
	// Nur die Anmeldung im Browser ueber wp-login.php. Anfragen ueber REST, XML-RPC und
	// Application Passwords laufen nicht ueber diese Seite und bleiben unberuehrt.
	if ( $user instanceof WP_User && did_action( 'login_init' ) ) {
		bw_protect_twofa_enable( $user );
	}

	return $user;
}

function bw_protect_twofa_on_admin() {
	if ( wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}

	bw_protect_twofa_enable( wp_get_current_user() );
}

/**
 * Schaltet den E-Mail-Code fuer ein Konto ein, wenn ASE Pro ihn fuer dieses Konto verlangt
 * und noch keine Methode aktiv ist. Rueckgabe: true, wenn eingeschaltet wurde.
 */
function bw_protect_twofa_enable( $user ) {
	if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
		return false;
	}

	if ( ! class_exists( 'Two_Factor_Core', false )
		|| ! is_callable( array( 'Two_Factor_Core', 'is_user_using_two_factor' ) )
		|| ! function_exists( 'asenha_two_factor_is_forced_email_enrollment_active_for_user__premium_only' )
		|| ! function_exists( 'asenha_two_factor_apply_forced_email_enrollment__premium_only' )
	) {
		return false;
	}

	// Wer schon eine Methode nutzt (E-Mail oder App), bleibt, wie er ist.
	if ( Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
		return false;
	}

	if ( ! asenha_two_factor_is_forced_email_enrollment_active_for_user__premium_only( $user ) ) {
		return false;
	}

	asenha_two_factor_apply_forced_email_enrollment__premium_only( $user->ID );

	return (bool) Two_Factor_Core::is_user_using_two_factor( $user->ID );
}
