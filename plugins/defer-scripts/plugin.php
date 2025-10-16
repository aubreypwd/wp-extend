<?php
/**
 * Plugin Name:       Defer Scripts
 * Plugin URI:        https://aubreypwd.com
 * Description:       This defers select scripts enqueued via <code>wp_enqueue_script()</code> for faster preformance.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 */

namespace aubreypwd\wp_extend\plugins\defer_scripts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Mess with the best, die like the rest.' );
}

if ( is_admin() ) {
	return;
}

if ( wp_doing_ajax() || wp_doing_cron() ) {
	return;
}

@define( 'AUBREYPWD_DEFER_SCRIPTS_NEEDLES', '' );

if ( empty( AUBREYPWD_DEFER_SCRIPTS_NEEDLES ) ) {
	return;
}

function get_needles() {
	return explode( ',', AUBREYPWD_DEFER_SCRIPTS_NEEDLES );
}

// Filter any script tags that we might want to defer...
add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {

	if ( stristr( $tag, 'defer' ) ) {
		return $tag; // Already deferred.
	}

	foreach ( get_needles() as $needle ) {

		if ( ! stristr( $tag, $needle ) ) {
			continue; // Tag does not have the needle in it, skip.
		}

		if ( ! stristr( $tag, '<script' ) ) {
			continue; // Not JS, we only do JS.
		}

		if (
			false ===

				/**
				 * Set to false to skip deferring this script.
				 *
				 * This is your chance to do custom logic to decide when
				 * to and when not to defer this script e.g. to not
				 * defer it on specific pages, etc.
				 *
				 * @param string $tag The tag being changed to defer.
				 * @param string $handle The handle of the script.
				 * @param string $src The src= value of the script.
				 * @param string $needle The needle determining the script to be deferred.
				 *
				 * @return bool
				 */
				apply_filters( 'aubreypwd/defer_scripts/defer', true, $tag, $handle, $src, $needle )
		) {
			continue; // Skip if the filter says so.
		}

		// Defer the script.
		$tag = str_replace( '<script ', '<script defer ', $tag );
	}

	return $tag;

}, 10, 3 );
