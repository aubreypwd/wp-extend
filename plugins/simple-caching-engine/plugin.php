<?php
/**
 * Plugin Name:       Simple Caching Engine
 * Plugin URI:        https://aubreypwd.com
 * Description:       This creates a on-disk cache of every post to increase server-side performance.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 */

namespace aubreypwd\wp_extend\plugins\simple_caching_engine;

if ( ! defined( 'AUBREYPWD_SIMPLE_CACHING_ENGINE_PRIORITY' ) ) {
	define( 'AUBREYPWD_SIMPLE_CACHING_ENGINE_PRIORITY', PHP_INT_MAX );
}

/**
 * Get the caching directory path.
 *
 * @return string
 */
function get_cache_dir() {
	return sprintf(
		'%s/%s',
		untrailingslashit( wp_get_upload_dir()['basedir'] ),
		'aubreypwd/simple-caching-engine/cache'
	);
}

// Delete the cache when the plugin is deactivated.
register_deactivation_hook( __FILE__, function() {
	global $wp_filesystem;
		$wp_filesystem->delete( get_cache_dir(), true, 'd' );
} );

/**
 * Get the path to the cache file for a post.
 *
 * @param int $post_id The Post ID.
 * @return string
 */
function get_post_cache_file( $post_id ) {
	return sprintf( '%s/post-id-%d.html', get_cache_dir(), absint( $post_id ) );
}

// When we save a post...
add_action( 'save_post', function( $post_id ) {
	@unlink( get_post_cache_file( $post_id ) ); // Delete the posts' cached file if there is one.
} );

if ( is_admin() ) {
	return; // No need to do anything else from the admin.
}

if ( wp_doing_ajax() || wp_doing_cron() ) {
	return; // Also no need to do anything in these cases.
}

if ( isset( $_GET['bypass_cache'] ) ) {
	return; // Use ?bypass_cache to bypass caching.
}

// When we load the frontend...
add_action(
	'template_redirect',
	function() {

		global $post;

		if ( ! is_a( $post, '\WP_Post' ) ) {
			return; // Not a post, do not use cache.
		}

		if ( in_array(
			$post->ID,
			array_map(
				function( int $post_id ) {
					return absint( $post_id );
				},

				/**
				 * Exclude posts from cache.
				 *
				 * @param array $exclude_posts A list of ID's of posts to exclude.
				 */
				apply_filters( 'aubreypwd\simple_caching_engine\exclude_posts', [] )
			),
			true
		) ) {
			return; // Don't cache this post.
		}

		@wp_mkdir_p( get_cache_dir() ); // Create the cache directory.

		$cache_file = get_post_cache_file( $post->ID );

		// Check for cached contents for the post...
		$contents = ( file_exists( $cache_file ) )
			? file_get_contents( $cache_file ) // Use the contents of the file.
			: ''; // No cache.

		if ( ! empty( $contents ) && ! isset( $_GET['refresh_cache'] ) ) {

			// Use cached contents for the post instead of letting WordPress build it dynamically.
			echo $contents;
			exit;
		}

		// Since there isn't a valid cache (or you are refreshing it), create one by caching what WordPress does.
		ob_start( function( $buffer ) use ( $cache_file ) {

				// Store the result on-disk.
				@file_put_contents( $cache_file, $buffer );

				// Output the page as WordPress sees it.
				return $buffer;
		} );
	},
	intval(

		/**
		 * Priority for serving cached content.
		 *
		 * Based on other plugins, adjust this to ensure that cached content is
		 * both served and generated at the right time.
		 *
		 * @param int $priority Priority.
		 */
		apply_filters( 'aubreypwd/simple_caching_engine/priority', AUBREYPWD_SIMPLE_CACHING_ENGINE_PRIORITY )
	)
);
