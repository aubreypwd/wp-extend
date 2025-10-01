<?php
/**
 * Plugin Name:       Pinterest Widget Fix
 * Plugin URI:        https://aubreypwd.com
 * Description:       This fixes the Solidad theme's Pinterest widget from making HTTP requests to pinterest.com on every pageload.
 * Version:           1.0.0
 * Author:            Aubrey Portwood
 * Author URI:        https://aubreypwd.com
 * Copyright:         (c) Aubrey Portwood 2025
 */

namespace aubreypwd\freelance\joli\literaryqsand_com\pinterest_widget_fix;

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

			$skip_transient_key = 'penci_pinterest_skip_request';

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