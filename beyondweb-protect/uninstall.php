<?php
/**
 * Beyondweb Protect: Aufraeumen beim Loeschen des Plugins.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'bw_protect_logo', 'bw_protect_logo_id', 'bw_protect_logo_light', 'bw_protect_lang_switcher', 'bw_protect_upload_max_image', 'bw_protect_upload_max_file', 'bw_protect_disabled_modules' ) as $bw_protect_option ) {
	delete_option( $bw_protect_option );
}

global $wpdb;
$bw_protect_like = $wpdb->esc_like( '_transient_bw_protect_login_light_' ) . '%';
$bw_protect_time = $wpdb->esc_like( '_transient_timeout_bw_protect_login_light_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $bw_protect_like, $bw_protect_time ) );
