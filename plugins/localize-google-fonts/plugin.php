<?php
/**
 * Plugin Name:       Localize Google Fonts
 * Plugin URI:        https://aubreypwd.com
 * Description:       This switches out all images on the frontend (on-the-fly) with their Imagekit counterparts transformed.
 * Version:           1.0.0-alpha
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood, 2025
 */

namespace aubreypwd\wp_extend\plugins\localize_google_fonts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Mess with the best, die like the rest.' );
}

if ( is_admin() ) {
	return;
}

if ( wp_doing_ajax() || wp_doing_cron() ) {
	return;
}

function replace_match_in_buffer( $buffer, $match, $link, $font_path ) {

	$localized_font_url = str_replace( wp_normalize_path( WP_CONTENT_DIR ), content_url(), wp_normalize_path( $font_path ) );

	$new_match = str_replace( $link, $localized_font_url, $match );

	return str_replace( $match, $new_match, $buffer );
}

// When all plugins have loaded...
add_action( 'plugins_loaded', function() {

	// Replace images/video links with imagekit ones.
	add_action( 'template_redirect', function() {

		// Buffer the entire content of the page.
		ob_start( function( $buffer ) {

			// Match all Google Font CSS...
			preg_match_all(
				'~(<link\b[^>]*\bhref\s*=\s*(["\'])(https://fonts\.googleapis\.com/css\?family=[^"\']+)\2[^>]*>)~i',
				$buffer,
				$matches
			);

			$fonts_dir = sprintf(
				'%s/aubreypwd/localize-google-font-css/fonts',
				untrailingslashit( wp_get_upload_dir()["basedir"] )
			);

			if ( ! wp_mkdir_p( $fonts_dir ) ) {
				return $buffer; // We can't store the fonts.
			}

			foreach ( $matches[1] ?? [] as $key => $match ) {

				$link = $matches[3][ $key ] ?? '';

				if ( empty( $link ) ) {
					continue; // No matching link, we can't replace it.
				}

				$font_file = sprintf( '%s.css', md5( $match ) );
				$font_path = sprintf( '%s/%s', untrailingslashit( $fonts_dir ), $font_file );

				if ( file_exists( $font_path ) ) {

					// Replace match with existing CSS...
					$buffer = replace_match_in_buffer( $buffer, $match, $link, $font_path );

					continue; // We already downloaded it, just replace the content.
				}

				$response = wp_remote_get( $link );

				if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
					continue; // No server response.
				}

				$css = wp_remote_retrieve_body( $response );

				if ( empty( $css ) ) {
					continue; // No CSS found.
				}

				// Download the CSS...
				@file_put_contents( $font_path, $css );

				if ( ! file_exists( $font_path ) ) {
					continue; // Downloading failed, can't replace.
				}

				// Replace the match with the localized one.
				$buffer = replace_match_in_buffer( $buffer, $match, $link, $font_path );
			}

			return $buffer;
		} );
	} );
} );


