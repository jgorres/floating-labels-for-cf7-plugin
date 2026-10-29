<?php
/**
 * Uninstall: removes every piece of data created by the plugin.
 *
 * Only executed when the plugin is deleted from the admin screen. The plugin
 * stores a single option and nothing else (no tables, transients, cron
 * events or files). On multisite every site of the network is cleaned.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the data of the current site.
 *
 * @return void
 */
function jgor_flcf7_uninstall_site() {
	// The sample page and forms (module 6): only the ids the plugin stored,
	// and only pages and contact forms.
	$samples = get_option( 'jgor_flcf7_samples', array() );
	if ( is_array( $samples ) ) {
		$ids = isset( $samples['forms'] ) && is_array( $samples['forms'] ) ? $samples['forms'] : array();
		if ( isset( $samples['page'] ) ) {
			$ids[] = $samples['page'];
		}

		foreach ( $ids as $id ) {
			$id = is_numeric( $id ) ? (int) $id : 0;
			if ( $id > 0 && in_array( get_post_type( $id ), array( 'page', 'wpcf7_contact_form' ), true ) ) {
				wp_delete_post( $id, true );
			}
		}
	}

	delete_option( 'jgor_flcf7_samples' );
	delete_transient( 'jgor_flcf7_activated' );
	delete_option( 'jgor_flcf7_settings' );
}

if ( is_multisite() ) {
	$jgor_flcf7_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $jgor_flcf7_site_ids as $jgor_flcf7_site_id ) {
		switch_to_blog( (int) $jgor_flcf7_site_id );
		jgor_flcf7_uninstall_site();
		restore_current_blog();
	}
} else {
	jgor_flcf7_uninstall_site();
}
