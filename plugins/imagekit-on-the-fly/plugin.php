<?php
/**
 * Plugin Name:       Imagekit on-the-fly
 * Plugin URI:        https://aubreypwd.com
 * Description:       This switches out all images on the frontend with their Imagekit CDN counterparts.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 */

namespace aubreypwd\wp_extend\plugins\imagekit_on_the_fly;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Mess with the best, die like the rest.' );
}

// === define these in wp-config.php: ===

// Your imagekit username.
@define( 'AUBREYPWD_IMAGKIT_ON_THE_FLY_USERNAME', '' );

// Store a 1x1 pixel image in this location on your site, it will be used to test imagekit uptime.
@define( 'AUBREYPWD_IMAGKIT_ON_THE_FLY_PIXEL_URI', 'wp-content/pixel.gif' );

// Transformations to make to images: default = avif, 71% quality, and images are no bigger than 1024px.
@define( 'AUBREYPWD_IMAGKIT_ON_THE_FLY_IMAGE_TR', 'f-web,q-71,w-1024' ); // See https://imagekit.io/docs/image-transformation.

// The place where you store your images, usually it's wp-content/uploads.
@define( 'AUBREYPWD_IMAGKIT_ON_THE_FLY_URI', 'wp-content/uploads' ); // The source path you setup on imagekit.io.

if (
	empty( AUBREYPWD_IMAGKIT_ON_THE_FLY_USERNAME )
		|| empty( AUBREYPWD_IMAGKIT_ON_THE_FLY_PIXEL_URI )
		|| empty( AUBREYPWD_IMAGKIT_ON_THE_FLY_IMAGE_TR )
		|| empty( AUBREYPWD_IMAGKIT_ON_THE_FLY_URI )
) {
	return;
}

/**
 * Convert content to use imagekit.
 *
 * @param string $content The content.
 */
function convert_content( string $content ) {

	// Get the current uploads URI (including domain) so we can use it to switch out images/videos in the content...
	$uploads_uri = trim( untrailingslashit( str_replace( [ 'http://', 'https://' ], '', wp_get_upload_dir()['baseurl'] ) ), '/' );

	// Image replacement: add transformations defined above.
	$content = preg_replace(
		sprintf(
			'#https?://%s/([^\s"\']+?\.(jpe?g|png|bmp|webp))#i',
			$uploads_uri
		),
		sprintf(
			'https://ik.imagekit.io/%s/tr:%s/%s/$1',
			AUBREYPWD_IMAGKIT_ON_THE_FLY_USERNAME,

			/**
			 * Filter transformations.
			 *
			 * @param string $transformations See https://imagekit.io/docs/image-transformation.
			 */
			apply_filters( 'aubreypwd/imagekit_on_the_fly/tr', AUBREYPWD_IMAGKIT_ON_THE_FLY_IMAGE_TR ),
			trim( AUBREYPWD_IMAGKIT_ON_THE_FLY_URI, '/' )
		),
		$content
	);

	// Video replacement, no transformations, but hosted on imagekit.
	$content = preg_replace(
		sprintf(
			'#https?://%s/([^\s"\']+?\.(mp4|webm|mov))#i',
			$uploads_uri
		),
		sprintf(
			'https://ik.imagekit.io/%s/%s/$1',
			AUBREYPWD_IMAGKIT_ON_THE_FLY_USERNAME,
			trim( AUBREYPWD_IMAGKIT_ON_THE_FLY_URI, '/' )
		),
		$content
	);

	return $content;
}

// Convert images on the fly to avif and reduce image size with imagekit.io.
add_filter( 'the_content', function( $content ) {

	$transient = 'imagekit_on_the_fly/network_check';

	if ( isset( $_GET['reset_imagekit_check'] ) ) {
		delete_transient( $transient ); // Reset the transient to re-test.
	}

	$check = get_transient( $transient );

	if ( 'failed' === $check ) {

		// It recently failed, use our normal content until transient expires and we'll try again.
		return $content;

	} elseif ( 'succeeded' === $check ) {

		// Trust the converted content until the transient expires.
		return convert_content( $content );

	// We don't know if it failed or not, let's test.
	} else {

		// Check the pixel on the server, it should translate to a 200 OK if imagekit is up.
		$headers = @get_headers(
			sprintf(
				'https://ik.imagekit.io/%s/%s',
				AUBREYPWD_IMAGKIT_ON_THE_FLY_USERNAME,
				trim( AUBREYPWD_IMAGKIT_ON_THE_FLY_PIXEL_URI, '/' )
			)
		);

		// We got a 200 OK from imagekit.
		if ( strpos( ( $headers[0] ?? '' ), '200' ) ) {

			// Remember this success for an hour (we'll try again after an hour).
			set_transient( $transient, 'succeeded', HOUR_IN_SECONDS );

			// Use converted content since we can trust the source.
			return convert_content( $content );

		} else {

			// We can't trust the source, so don't trust it for 10 minutes.
			set_transient( $transient, 'failed', MINUTE_IN_SECONDS * 10 );
		}
	}

	return $content; // Default to our content.
} );