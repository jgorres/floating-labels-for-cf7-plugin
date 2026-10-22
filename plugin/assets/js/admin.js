/**
 * Floating Labels for CF7 – settings screen script.
 *
 * Turns the colour fields of the section "Design" into WordPress colour
 * pickers (wp-color-picker). Loaded on the settings screen only; fields that
 * are fixed in code (disabled) stay plain inputs.
 *
 * @package Floating_Labels_For_CF7
 */

( function ( $ ) {
	'use strict';

	$( function () {
		$( '.jgor-flcf7-color' ).not( ':disabled' ).wpColorPicker();
	} );
} )( window.jQuery );
