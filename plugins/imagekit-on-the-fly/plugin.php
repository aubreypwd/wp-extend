<?php
/**
 * Plugin Name:       Imagekit on-the-fly
 * Plugin URI:        https://aubreypwd.com
 * Description:       This switches out all images on the frontend with their Imagekit CDN counterparts.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 *
 * See https://imagekit.io/docs/
 */

namespace aubreypwd\wp_extend\plugins\imagekit_on_the_fly;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Mess with the best, die like the rest.' );
}

if ( is_admin() ) {
	return;
}

if ( wp_doing_ajax() || wp_doing_cron() ) {
	return;
}

@define( 'AUBREYPWD_IMAGEKIT_ON_THE_FLY_USERNAME', '' );
@define( 'AUBREYPWD_IMAGEKIT_ON_THE_FLY_IMAGE_TR', 'f-webp,q-71,md-false,cp-false' ); // See https://imagekit.io/docs/image-transformation.
@define( 'AUBREYPWD_IMAGEKIT_ON_THE_FLY_UPTIME_IMAGE', plugins_url( 'pixel.gif', __FILE__ ) ); // Override if you want to use a pixel somewhere else.

if (
	empty( AUBREYPWD_IMAGEKIT_ON_THE_FLY_USERNAME )
		|| empty( AUBREYPWD_IMAGEKIT_ON_THE_FLY_UPTIME_IMAGE )
		|| empty( AUBREYPWD_IMAGEKIT_ON_THE_FLY_IMAGE_TR )
) {
	return;
}

// Whether or not do do images and/or video, define these or use filter below.
@define( 'AUBREYPWD_IMAGEKIT_ON_THE_FLY_DO_IMAGES', true );
@define( 'AUBREYPWD_IMAGEKIT_ON_THE_FLY_DO_VIDEO', true );

/**
 * Convert content to use imagekit.
 *
 * @param string $content The content.
 */
function convert_content( string $content ) {

	// Get the current uploads URI (including domain) so we can use it to switch out images/videos in the content...
	$uploads_uri = trim( str_replace( [ 'http://', 'https://' ], '', wp_get_upload_dir()['baseurl'] ), '/' );

	if (
		apply_filters( 'aubreypwd/imagekit_on_the_fly/do_images', AUBREYPWD_IMAGEKIT_ON_THE_FLY_DO_IMAGES )
	) {

	}

	// Image replacement: add transformations defined above.
	$content = preg_replace(

		/**
		 * Filter preg_replace pattern (images).
		 *
		 * @param string $pattern The pattern.
		 */
		apply_filters(
			'aubreypwd/imagekit_on_the_fly/preg_replace/images/pattern',
			sprintf(
				'#https?://%s/([^\s"\']+?\.(jpe?g|png|bmp|webp))#i',
				$uploads_uri
			)
		),

		/**
		 * Filter preg_replace replacement pattern (images).
		 *
		 * @param string $replacement_pattern The replacement pattern.
		 */
		apply_filters(
			'aubreypwd/imagekit_on_the_fly/preg_replace/images/replacement_pattern',
			sprintf(
				'https://ik.imagekit.io/%s/tr:%s/$1',
				AUBREYPWD_IMAGEKIT_ON_THE_FLY_USERNAME,

				/**
				 * Filter transformations.
				 *
				 * @param string $transformations See https://imagekit.io/docs/image-transformation.
				 */
				apply_filters( 'aubreypwd/imagekit_on_the_fly/tr', AUBREYPWD_IMAGEKIT_ON_THE_FLY_IMAGE_TR )
			)
		),
		$content
	);

	// Video replacement, no transformations, but hosted on imagekit.
	$content = preg_replace(

		/**
		 * Filter preg_replace pattern (video).
		 *
		 * @param string $pattern The pattern.
		 */
		apply_filters(
			'aubreypwd/imagekit_on_the_fly/preg_replace/video/pattern',
			sprintf(
				'#https?://%s/([^\s"\']+?\.(mp4|webm|mov))#i',
				$uploads_uri
			)
		),

		/**
		 * Filter preg_replace replacement pattern (video).
		 *
		 * @param string $replacement_pattern The replacement pattern.
		 */
		apply_filters(
			'aubreypwd/imagekit_on_the_fly/preg_replace/video/replacement_pattern',
			sprintf(
				'https://ik.imagekit.io/%s/$1',
				AUBREYPWD_IMAGEKIT_ON_THE_FLY_USERNAME
			)
		),
		$content
	);

	return $content;
}

// When all plugins have loaded...
add_action( 'plugins_loaded', function() {

	// Replace images/video links with imagekit ones.
	add_action(  'template_redirect', function() {

		// Buffer the entire content of the page.
		ob_start( function( $buffer ) {

			$transient = 'aubreypwd/imagekit_on_the_fly/network_check';

			if ( isset( $_GET['reset_imagekit_check'] ) ) {
				delete_transient( $transient ); // Reset the transient to re-test.
			}

			$check = get_transient( $transient );

			if ( 'failed' === $check ) {

				// It recently failed, use our normal content until transient expires and we'll try again.
				return $buffer;

			} elseif ( 'succeeded' === $check ) {

				// Trust the converted content until the transient expires.
				return convert_content( $buffer );

			// We don't know if it failed or not, let's test.
			} else {

				// Check the pixel on the server, it should translate to a 200 OK if imagekit is up.
				$headers = @get_headers(

					/**
					 * Filter imagekit uptime image URL.
					 *
					 * @param string $uri The URI where your pixel should be on imagekit.
					 */
					apply_filters(
						'aubreypwd/imagekit_on_the_fly/uptime_image',
						AUBREYPWD_IMAGEKIT_ON_THE_FLY_UPTIME_IMAGE
					)
				);

				// We got a 200 OK from imagekit.
				if ( strpos( ( $headers[0] ?? '' ), '200' ) ) {

					// Remember this success for an hour (we'll try again after an hour).
					set_transient( $transient, 'succeeded', HOUR_IN_SECONDS );

					// Use converted content since we can trust the source.
					return convert_content( $buffer );

				} else {

					// We can't trust the source, so don't trust it for 10 minutes.
					set_transient( $transient, 'failed', MINUTE_IN_SECONDS * 10 );
				}
			}

			return $buffer; // Default to our content.
		} );
	} );
} );