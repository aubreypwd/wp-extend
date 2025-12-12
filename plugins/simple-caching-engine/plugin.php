<?php // phpcs:disable
/**
 * Plugin Name:       Simple Server-side Caching Engine
 * Plugin URI:        https://aubreypwd.com
 * Description:       This creates a on-disk cache of every post to increase server-side performance.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 */

namespace aubreypwd\wp_extend\plugins\simple_server_side_caching_engine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Mess with the best, die like the rest.' );
}

// Load with other plugins...
add_action( 'plugins_loaded', function() {

	if ( ! defined( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_PRIORITY' ) )

		// Why - 1000, that way other plugins CAN beat us out if they want to e.g. - 900 - 800.
		define( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_PRIORITY', PHP_INT_MAX - 1000 );

	if ( ! defined( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_DISABLED' ) )
			define( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_DISABLED', false );

	if ( ! defined( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_CACHE_FILE_LIFETIME' ) )
			define( 'AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_CACHE_FILE_LIFETIME', DAY_IN_SECONDS );

	if (
		/**
		 * Disable caching with a filter.
		 *
		 * @param $disable Set to true to disable.
		 */
		apply_filters( 'aubreypwd/simple_server_side_caching_engine/disable_cache', AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_DISABLED )
	) {
		return; // The filter told us to not.
	}

	/**
	 * Get the caching directory path.
	 *
	 * @return string
	 */
	function get_cache_dir() {
		return sprintf(
			'%s/cache/%s',
			untrailingslashit( WP_CONTENT_DIR ),

			// Yes, this has underscores so I can easily rename it later if I want to.
			str_replace( '_', '-', 'aubreypwd/simple_server_side_caching_engine' )
		);
	}

	/**
	 * Delete the entire cache.
	 */
	function delete_cache() {

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			require_once ABSPATH . 'wp-admin/includes/file.php';

			WP_Filesystem();
		}

		$wp_filesystem->delete( get_cache_dir(), true, 'd' );
	}

	// Reset the cache when we deactivate the plugin.
	register_deactivation_hook( __FILE__, function() {
		delete_cache();
	} );

	if ( is_admin() ) {

		// Reset the cache anytime any options change in the admin.
		foreach ( [
			'update_option',
			'add_option',
			'delete_option',
			'update_site_option',
			'updated_site_option',
			'add_site_option',
			'delete_site_option',
		] as $filter ) {
			add_action( $filter, function() {
				delete_cache();
			} );
		}
	}

	/**
	 * Get the path to the cache file for a post.
	 *
	 * @param int $post_id The Post ID.
	 * @return string
	 */
	function get_post_cache_file( $post_id ) {
		return sprintf(
			'%s/post-id-%d.html',
			get_cache_dir(),
			absint( $post_id )
		);
	}

	// When we save a post...
	add_action( 'save_post', function( $post_id ) {

		$cache_file = get_post_cache_file( $post_id );

		@unlink( $cache_file ); // Delete the posts' on-disk cache file, if there is one.

		/**
		 * Fires after we delete a posts' on-disk cache file.
		 *
		 * @param string $cache_file The file we deleted.
		 * @param int    $post_id    The post associated with the cache.
		 */
		do_action( 'aubreypwd/simple_server_side_caching_engine/delete_cache', $cache_file, $post_id );
	} );

	if ( is_admin() ) {
		return; // No need to do anything else from the admin.
	}

	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return; // Also no need to do anything in these cases.
	}

	// When we load the frontend...
	add_action(
		'template_redirect',
		function() {

			if ( isset( $_GET['__purge'] ) ) {

				// Purge the entire cache and don't cache the current page.
				delete_cache();
				return;
			}

			if ( is_user_logged_in() ) {
				return; // No caching for logged in users.
			}

			if ( ! empty( $_POST ) || ! empty( $_GET ) ) {
				return; // Don't use cached contents when POST or GET have content (forms/etc).
			}

			global $post;

			if ( ! is_a( $post, '\WP_Post' ) && is_a( get_queried_object(), '\WP_Post' ) ) {
				return; // Not a post, do not use cache.
			}

			if ( is_robots() ) {
				return;
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
					apply_filters( 'aubreypwd/simple_server_side_caching_engine/exclude_posts', [] )
				),
				true
			) ) {
				return; // Don't cache this post.
			}

			@wp_mkdir_p( get_cache_dir() ); // Create the cache directory.

			$cache_file = get_post_cache_file( $post->ID );

			// Check for cached contents for the post...
			$cache = (

				// We have a on-disk cache...
				file_exists( $cache_file )

				// The cache (file) is not older than X days...
				&& (

					// The lifetime of the file on-disk...
					( time() - filemtime( $cache_file ) )

					// Is less than the acceptable lifetime...
					< (

						/**
						 * Set the acceptable lifetime of on-disk cache files.
						 *
						 * @param $seconds Lifetime in seconds.
						 */
						apply_filters(
							'aubreypwd/simple_server_side_caching_engine/cache_lifetime',
							AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_CACHE_FILE_LIFETIME
						)
					)
				)
			)
				? file_get_contents( $cache_file ) // Use the on-disk cache of the post.
				: false; // Don't use the on-disk cache (or there is none).

			if ( false !== $cache && is_string( $cache ) && ! empty( $cache ) ) {
				exit( $cache ); // Output our cache instead of letting WordPress generate the markup.
			}

			// Since there isn't a valid cache, (re-)create one by caching what WordPress does.
			ob_start( function( $buffer ) use ( $cache_file, $post ) {

				if ( empty( $buffer ) ) {
					return $buffer;
				}

				// Store the result on-disk.
				@file_put_contents( $cache_file, $buffer );

				/**
				 * Right after we write the cache for a file.
				 *
				 * @param string   $cache_file The file that stores the on-disk cache for the post.
				 * @param \WP_Post $post       The post object.
				 * @param string   $buffer     The HTML we wrote to the cache.
				 */
				do_action( 'aubreypwd/simple_server_side_caching_engine/cache_generated', $cache_file, $post, $buffer );

				// Output the page as WordPress did it.
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
			apply_filters( 'aubreypwd/simple_server_side_caching_engine/priority', AUBREYPWD_SIMPLE_SERVER_SIDE_CACHING_ENGINE_PRIORITY )
		)
	);
} );
