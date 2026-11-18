<?php
/**
 * Beyondweb Protect: Sprache und eigene Texte fuer alle Module.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sprachcode einer Locale: de_CH -> de, pt_BR -> pt.
 */
function bw_protect_code( $locale ) {
	$parts = explode( '_', (string) $locale );
	$code  = strtolower( $parts[0] );
	return preg_match( '/^[a-z]{2,3}$/', $code ) ? $code : '';
}

/**
 * Sprache der eigenen Texte: de, fr, it oder en.
 * Ohne Angabe gilt die Sprache, die WordPress fuer die aktuelle Seite bestimmt
 * (Sprachwahl auf der Login-Seite, sonst Sprache der Website).
 * Fuer alle anderen Sprachen gelten die englischen Texte.
 */
function bw_protect_lang( $locale = '' ) {
	$code = bw_protect_code( '' !== $locale ? $locale : determine_locale() );
	return in_array( $code, array( 'de', 'fr', 'it' ), true ) ? $code : 'en';
}

/**
 * Eigene Texte in der Sprache der Login-Seite.
 * *_hl = Breite der gelben Markierung unter dem letzten Wort des Titels in Prozent.
 */
function bw_protect_texts( $lang = '' ) {
	static $cache = array();
	if ( ! in_array( $lang, array( 'de', 'fr', 'it', 'en' ), true ) ) {
		$lang = bw_protect_lang();
	}
	if ( isset( $cache[ $lang ] ) ) {
		return $cache[ $lang ];
	}

	$all = array(
		'de' => array(
			'title'           => 'Willkommen zurück',
			'title_hl'        => 34,
			'code'            => 'Code eingeben',
			'code_hl'         => 63,
			'backup'          => 'Backup-Code eingeben',
			'backup_hl'       => 41,
			'intro'           => 'Melde dich mit deinen Zugangsdaten an.',
			'intro_email'     => 'Melde dich mit deiner E-Mail-Adresse an.',
			'intro_2fa'       => 'Melde dich an. Danach schicken wir dir einen Code per Mail.',
			'intro_email_2fa' => 'Melde dich mit deiner E-Mail-Adresse an. Danach schicken wir dir einen Code per Mail.',
			'micro'           => 'Geschützt mit Zwei-Faktor-Anmeldung',
			'footer'          => 'Technische Betreuung durch Beyondweb GmbH',
			'nav'             => 'Sprache',
			'email_label'     => 'E-Mail-Adresse',
			'back'            => 'Zurück zur Website',
			'prompt'          => 'Wir haben dir einen Code an deine E-Mail-Adresse geschickt.',
			'prompt_ttl'      => ' Dieser ist %d Minuten gültig.',
			'code_label'      => 'Code aus der E-Mail',
			'verify'          => 'Bestätigen',
			'resend'          => 'Code erneut senden',
			'problems'        => 'Probleme?',
			'use_backup'      => 'Backup-Code verwenden',
			'send_code'       => 'Code per E-Mail senden',
			'backup_prompt'   => 'Gib einen deiner Backup-Codes ein. Jeder Code funktioniert nur einmal.',
			'backup_label'    => 'Backup-Code',
			'error'           => '<strong>Fehler:</strong> E-Mail-Adresse oder Passwort ist nicht korrekt.',
			'mail_code_subject' => 'Dein Anmeldecode für %s',
			'mail_code_intro'   => 'Dein Anmeldecode für %s:',
			'mail_code_valid'   => 'Der Code ist %d Minuten gültig. Gib ihn auf der Anmeldeseite ein.',
			'mail_code_enter'   => 'Gib den Code auf der Anmeldeseite ein.',
			'mail_code_q'       => 'Du hast dich nicht angemeldet?',
			'mail_code_a'       => 'Dann kennt jemand dein Passwort. Ändere es bitte sofort.',
			'mail_auto'         => 'Automatische Nachricht von %s',
			'mail_reset_subject' => 'Passwort zurücksetzen für %s',
			'mail_reset_intro'  => 'Für dein Konto bei %s wurde ein neues Passwort angefordert.',
			'mail_reset_cta'    => 'Neues Passwort festlegen',
			'mail_link_hint'    => 'Falls der Link nicht funktioniert, kopiere diese Adresse in deinen Browser:',
			'mail_reset_q'      => 'Du hast kein neues Passwort angefordert?',
			'mail_reset_a'      => 'Dann kannst du diese Nachricht ignorieren. Dein Passwort bleibt unverändert.',
			'mail_new_subject'  => 'Dein Konto bei %s',
			'mail_new_intro'    => 'Für dich wurde ein Konto bei %s eingerichtet.',
			'mail_new_email'    => 'Du meldest dich mit dieser E-Mail-Adresse an: %s',
			'mail_new_user'     => 'Dein Benutzername: %s',
			'mail_new_cta'      => 'Passwort festlegen',
			'upload_image'      => 'Dieses Bild ist %1$s gross. Erlaubt sind %2$s. Bitte verkleinere es und lade es erneut hoch.',
			'upload_file'       => 'Diese Datei ist %1$s gross. Erlaubt sind %2$s.',
			'upload_hint'       => 'Bilder bis %1$s, andere Dateien bis %2$s.',
		),
		'fr' => array(
			'title'           => 'Bon retour',
			'title_hl'        => 58,
			'code'            => 'Saisir le code',
			'code_hl'         => 38,
			'backup'          => 'Saisir le code de secours',
			'backup_hl'       => 33,
			'intro'           => 'Connectez-vous avec vos identifiants.',
			'intro_email'     => 'Connectez-vous avec votre adresse e-mail.',
			'intro_2fa'       => 'Connectez-vous. Nous vous enverrons ensuite un code par e-mail.',
			'intro_email_2fa' => 'Connectez-vous avec votre adresse e-mail. Nous vous enverrons ensuite un code par e-mail.',
			'micro'           => 'Protégé par l’authentification à deux facteurs',
			'footer'          => 'Support technique assuré par Beyondweb GmbH',
			'nav'             => 'Langue',
			'email_label'     => 'Adresse e-mail',
			'back'            => 'Retour au site',
			'prompt'          => 'Nous vous avons envoyé un code à votre adresse e-mail.',
			'prompt_ttl'      => ' Il est valable %d minutes.',
			'code_label'      => 'Code reçu par e-mail',
			'verify'          => 'Valider',
			'resend'          => 'Renvoyer le code',
			'problems'        => 'Un problème ?',
			'use_backup'      => 'Utiliser un code de secours',
			'send_code'       => 'Envoyer un code par e-mail',
			'backup_prompt'   => 'Saisissez l’un de vos codes de secours. Chaque code ne fonctionne qu’une seule fois.',
			'backup_label'    => 'Code de secours',
			'error'           => '<strong>Erreur&nbsp;:</strong> l’adresse e-mail ou le mot de passe est incorrect.',
			'mail_code_subject' => 'Votre code de connexion pour %s',
			'mail_code_intro'   => 'Votre code de connexion pour %s :',
			'mail_code_valid'   => 'Ce code est valable %d minutes. Saisissez-le sur la page de connexion.',
			'mail_code_enter'   => 'Saisissez ce code sur la page de connexion.',
			'mail_code_q'       => 'Vous n’avez pas essayé de vous connecter ?',
			'mail_code_a'       => 'Quelqu’un connaît alors votre mot de passe. Veuillez le modifier immédiatement.',
			'mail_auto'         => 'Message automatique de %s',
			'mail_reset_subject' => 'Réinitialisation du mot de passe pour %s',
			'mail_reset_intro'  => 'Un nouveau mot de passe a été demandé pour votre compte sur %s.',
			'mail_reset_cta'    => 'Définir un nouveau mot de passe',
			'mail_link_hint'    => 'Si le lien ne fonctionne pas, copiez cette adresse dans votre navigateur :',
			'mail_reset_q'      => 'Vous n’avez pas demandé de nouveau mot de passe ?',
			'mail_reset_a'      => 'Vous pouvez ignorer ce message. Votre mot de passe reste inchangé.',
			'mail_new_subject'  => 'Votre compte sur %s',
			'mail_new_intro'    => 'Un compte a été créé pour vous sur %s.',
			'mail_new_email'    => 'Vous vous connectez avec cette adresse e-mail : %s',
			'mail_new_user'     => 'Votre identifiant : %s',
			'mail_new_cta'      => 'Définir le mot de passe',
			'upload_image'      => 'Cette image pèse %1$s. La limite est de %2$s. Veuillez la réduire et la téléverser à nouveau.',
			'upload_file'       => 'Ce fichier pèse %1$s. La limite est de %2$s.',
			'upload_hint'       => 'Images jusqu’à %1$s, autres fichiers jusqu’à %2$s.',
		),
		'it' => array(
			'title'           => 'Ciao di nuovo',
			'title_hl'        => 47,
			'code'            => 'Inserisci il codice',
			'code_hl'         => 39,
			'backup'          => 'Inserisci il codice di backup',
			'backup_hl'       => 28,
			'intro'           => 'Accedi con le tue credenziali.',
			'intro_email'     => 'Accedi con il tuo indirizzo e-mail.',
			'intro_2fa'       => 'Accedi. Poi ti inviamo un codice via e-mail.',
			'intro_email_2fa' => 'Accedi con il tuo indirizzo e-mail. Poi ti inviamo un codice via e-mail.',
			'micro'           => 'Protetto con l’autenticazione a due fattori',
			'footer'          => 'Assistenza tecnica di Beyondweb GmbH',
			'nav'             => 'Lingua',
			'email_label'     => 'Indirizzo e-mail',
			'back'            => 'Torna al sito',
			'prompt'          => 'Ti abbiamo inviato un codice al tuo indirizzo e-mail.',
			'prompt_ttl'      => ' È valido per %d minuti.',
			'code_label'      => 'Codice ricevuto via e-mail',
			'verify'          => 'Conferma',
			'resend'          => 'Invia di nuovo il codice',
			'problems'        => 'Problemi?',
			'use_backup'      => 'Usa un codice di backup',
			'send_code'       => 'Invia un codice via e-mail',
			'backup_prompt'   => 'Inserisci uno dei tuoi codici di backup. Ogni codice funziona una sola volta.',
			'backup_label'    => 'Codice di backup',
			'error'           => '<strong>Errore:</strong> indirizzo e-mail o password non corretti.',
			'mail_code_subject' => 'Il tuo codice di accesso per %s',
			'mail_code_intro'   => 'Il tuo codice di accesso per %s:',
			'mail_code_valid'   => 'Il codice è valido per %d minuti. Inseriscilo nella pagina di accesso.',
			'mail_code_enter'   => 'Inserisci il codice nella pagina di accesso.',
			'mail_code_q'       => 'Non hai provato ad accedere?',
			'mail_code_a'       => 'Allora qualcuno conosce la tua password. Cambiala subito.',
			'mail_auto'         => 'Messaggio automatico da %s',
			'mail_reset_subject' => 'Reimposta la password per %s',
			'mail_reset_intro'  => 'È stata richiesta una nuova password per il tuo account su %s.',
			'mail_reset_cta'    => 'Imposta una nuova password',
			'mail_link_hint'    => 'Se il link non funziona, copia questo indirizzo nel browser:',
			'mail_reset_q'      => 'Non hai richiesto una nuova password?',
			'mail_reset_a'      => 'Puoi ignorare questo messaggio. La tua password resta invariata.',
			'mail_new_subject'  => 'Il tuo account su %s',
			'mail_new_intro'    => 'È stato creato un account per te su %s.',
			'mail_new_email'    => 'Accedi con questo indirizzo e-mail: %s',
			'mail_new_user'     => 'Il tuo nome utente: %s',
			'mail_new_cta'      => 'Imposta la password',
			'upload_image'      => 'Questa immagine pesa %1$s. Il limite è %2$s. Riducila e caricala di nuovo.',
			'upload_file'       => 'Questo file pesa %1$s. Il limite è %2$s.',
			'upload_hint'       => 'Immagini fino a %1$s, altri file fino a %2$s.',
		),
		'en' => array(
			'title'           => 'Welcome back',
			'title_hl'        => 33,
			'code'            => 'Enter code',
			'code_hl'         => 46,
			'backup'          => 'Enter backup code',
			'backup_hl'       => 27,
			'intro'           => 'Sign in with your login details.',
			'intro_email'     => 'Sign in with your email address.',
			'intro_2fa'       => 'Sign in. We’ll then send you a code by email.',
			'intro_email_2fa' => 'Sign in with your email address. We’ll then send you a code by email.',
			'micro'           => 'Protected by two-factor authentication',
			'footer'          => 'Technical support by Beyondweb GmbH',
			'nav'             => 'Language',
			'email_label'     => 'Email address',
			'back'            => 'Back to the website',
			'prompt'          => 'We’ve sent a code to your email address.',
			'prompt_ttl'      => ' It is valid for %d minutes.',
			'code_label'      => 'Code from the email',
			'verify'          => 'Confirm',
			'resend'          => 'Resend code',
			'problems'        => 'Problems?',
			'use_backup'      => 'Use a backup code',
			'send_code'       => 'Send a code by email',
			'backup_prompt'   => 'Enter one of your backup codes. Each code works only once.',
			'backup_label'    => 'Backup code',
			'error'           => '<strong>Error:</strong> Email address or password is incorrect.',
			'mail_code_subject' => 'Your login code for %s',
			'mail_code_intro'   => 'Your login code for %s:',
			'mail_code_valid'   => 'The code is valid for %d minutes. Enter it on the login page.',
			'mail_code_enter'   => 'Enter the code on the login page.',
			'mail_code_q'       => 'Didn’t try to log in?',
			'mail_code_a'       => 'Then someone knows your password. Please change it right away.',
			'mail_auto'         => 'Automated message from %s',
			'mail_reset_subject' => 'Reset your password for %s',
			'mail_reset_intro'  => 'A new password was requested for your account at %s.',
			'mail_reset_cta'    => 'Set a new password',
			'mail_link_hint'    => 'If the link doesn’t work, copy this address into your browser:',
			'mail_reset_q'      => 'Didn’t request a new password?',
			'mail_reset_a'      => 'You can ignore this message. Your password stays the same.',
			'mail_new_subject'  => 'Your account at %s',
			'mail_new_intro'    => 'An account has been created for you at %s.',
			'mail_new_email'    => 'You sign in with this email address: %s',
			'mail_new_user'     => 'Your username: %s',
			'mail_new_cta'      => 'Set your password',
			'upload_image'      => 'This image is %1$s. The limit is %2$s. Please reduce it and upload it again.',
			'upload_file'       => 'This file is %1$s. The limit is %2$s.',
			'upload_hint'       => 'Images up to %1$s, other files up to %2$s.',
		),
	);

	$cache[ $lang ] = $all[ $lang ];
	return $cache[ $lang ];
}
