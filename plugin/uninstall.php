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
