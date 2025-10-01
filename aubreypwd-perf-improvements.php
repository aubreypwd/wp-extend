<?php
/**
 * Plugin Name:       Aubrey Portwood's Performance Improvements
 * Plugin URI:        https://aubreypwd.com
 * Description:       Performance improvements for literaryquicksand.com
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood 2025
 */

namespace aubreypwd\perf_improvements;

// Set these to `off` to disable individual features.
define( 'AUBREYPWD_PERF_IMPROVEMENTS_GET_BOARD_NAME_PINS', defined( 'AUBREYPWD_PERF_IMPROVEMENTS_GET_BOARD_NAME_PINS' ) ? AUBREYPWD_PERF_IMPROVEMENTS_GET_BOARD_NAME_PINS : 'on' );
define( 'AUBREYPWD_PERF_IMPROVEMENTS_CACHE', defined( 'AUBREYPWD_PERF_IMPROVEMENTS_CACHE' ) ? AUBREYPWD_PERF_IMPROVEMENTS_CACHE : 'on' );

function get_plugin_prefix( string $replace = '_' ) {
	return str_replace(
		[ '_', '-' ],
		$replace,
		sanitize_title_with_dashes( str_replace( '\\', $replace, __NAMESPACE__ ) ),
	);
}

// Simple caching engine.
if ( 'on' === AUBREYPWD_PERF_IMPROVEMENTS_CACHE ) {

	function get_cache_dir() {
		return sprintf( '%s/%s-cache', untrailingslashit( wp_get_upload_dir()['basedir'] ), get_plugin_prefix( '-' ) );
	}

	function get_post_cache_file( $post_id ) {
		return sprintf( '%s/post-%s.html', get_cache_dir(), absint( $post_id ) );
	}

	// When we save a post...
	add_action( 'save_post', function( $post_id ) {
		@unlink( get_post_cache_file( $post_id ) ); // Delete the posts' cached file if there is one.
	} );

	// When we load the frontend...
	add_action( 'template_redirect', function() {

		global $post;

		if ( ! is_a( $post, '\WP_Post' ) ) {
			return; // Not a post, do not cache.
		}

		$cache_dir = get_cache_dir();

		@wp_mkdir_p( $cache_dir );

		$cache_file = get_post_cache_file( $post->ID );

		// Check for cached contents...
		$contents = ( file_exists( $cache_file ) )
			? file_get_contents( $cache_file ) // Use the contents of the file.
			: ''; // No cache.

		if ( ! empty( $contents ) ) {

			// Use cached contents for the post...
			echo $contents;
			exit;
		}

		// Since there isn't a valid cache, create one...
		ob_start( function( $buffer ) use ( $cache_dir, $cache_file ) {

				@file_put_contents(
					$cache_file,
					$buffer
				);

				// Output the page as WordPress sees it.
				return $buffer;
		} );
	} );
}

// Fix pinterest board pins repeat requests.
if ( 'on' === AUBREYPWD_PERF_IMPROVEMENTS_GET_BOARD_NAME_PINS ) {

	// Filter any HTTP request (we'll only affect those going to www.pinterest.com/literaryqsand/best-book-lists/)...
	add_filter(
		'pre_http_request',

			/**
			 * Disable repeat wp_remote_get requests for get_board_name_pins().
			 *
			 * This disables repeat wp_remote_get requests due to the caching of []
			 * in a transient being seen as falsy in the original code, resulting
			 * in a repeat request each pageload even though [] was cached.
			 *
			 * @param mixed $default This should be false, true, or a response.
			 * @param mixed $params Array a parameters for the request.
			 * @param mixed $url The URL we are making the request to.
			 *
			 * @return mixed We will make the request ourselves and cache the response properly,
			 *               we will return `\WP_Error` and skip any repeat requests, or we will return
			 *               `$default` when the request isn't our pinterest request, or we will
			 *               return a response if we think the request is legit.
			 */
			function( $default, $params, $url ) {

				if ( ! strstr( $url, 'www.pinterest.com/literaryqsand/best-book-lists/' ) ) {
					return $default; // Not our wp_remote_get call, allow others to pass.
				}

				$skip_transient_key = sprintf( '%s_penci_pinterest_skip_request', get_plugin_prefix() );

				if ( 'skip' === get_transient( $skip_transient_key ) ) {
					return new \WP_Error(); // Skip request until our time expires.
				}

				if ( true === isset( $params['_get_board_name_pins'] ) ) {
					return $default; // Allow the below attempt to complete (recursive).
				}

				// Make the request...
				$response = wp_remote_get(
					$url,
					array_merge(
						$params,
						[

							// Tells this function not to make another request (above).
							'_get_board_name_pins' => true,
						]
					)
				);

				// Try and find what wp-content/themes/soledad/inc/widgets/pinterest_widget.php:get_board_name_pins() is looking for...
				preg_match_all(
					'/jsInit1\'>(.*)<\/script>/',
					wp_remote_retrieve_body(
						$response
					),
					$matches
				);

				// Nothing found...
				if ( empty( $matches[1] ) ) {

					// Skip the next attempt for X seconds, nothing was found (don't repeat request).
					set_transient( $skip_transient_key, 'skip', DAY_IN_SECONDS );

					// Nothing was found, skip this time and sub-sequent requests for X seconds...
					return new \WP_Error();
				}

				// Something was found pass back the request for pinterest_widget.php to cache the data.
				return $response;
			},
		10,
		3
	);
}
