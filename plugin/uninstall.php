<?php
/**
 * Uninstall: removes every piece of data created by the plugin.
 *
 * Only executed when the plugin is deleted from the admin screen. The plugin
 * stores two options (the settings and the ids of the sample page and forms
 * of module 6) and one transient (the activation flag); no tables, cron
 * events or files. On multisite every site of the network is cleaned.
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Identity check of the sample posts (JGOR_FLCF7_Samples::is_sample_post()).
// The class file only declares the class, nothing runs on load.
require_once __DIR__ . '/includes/class-jgor-flcf7-samples.php';

/**
 * Removes the data of the current site.
 *
 * @return void
 */
function jgor_flcf7_uninstall_site() {
	// The sample page and forms (module 6): only the ids the plugin stored,
	// and only when the post really is a sample post (slug of the page, title
	// prefix and suffix of a form); an id reused by another post is skipped.
	$samples = get_option( 'jgor_flcf7_samples', array() );
	if ( is_array( $samples ) ) {
		$ids = isset( $samples['forms'] ) && is_array( $samples['forms'] ) ? $samples['forms'] : array();
		if ( isset( $samples['page'] ) ) {
			$ids[] = $samples['page'];
		}

		foreach ( $ids as $id ) {
			$id   = is_numeric( $id ) ? (int) $id : 0;
			$post = $id > 0 ? get_post( $id ) : null;
			if ( $post instanceof WP_Post && JGOR_FLCF7_Samples::is_sample_post( $post ) ) {
				wp_delete_post( $id, true );
			}
		}
	}

	delete_option( 'jgor_flcf7_samples' );
	delete_transient( 'jgor_flcf7_activated' );
	delete_option( 'jgor_flcf7_settings' );
}

if ( is_multisite() ) {
	// number => 0: every site, not only the first 100 get_sites() returns by default.
	$jgor_flcf7_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $jgor_flcf7_site_ids as $jgor_flcf7_site_id ) {
		switch_to_blog( (int) $jgor_flcf7_site_id );
		jgor_flcf7_uninstall_site();
		restore_current_blog();
	}
} else {
	jgor_flcf7_uninstall_site();
}
