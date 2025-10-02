# Simple Caching Engine

This plugin works simply by storeing the result of a WordPress post in a `.html` file on-disk. Until the post is modified, e.g. `save_post`, the cache on-disk.

This reduces what WordPress is doing dynamically on each page load, helping out TTFB, server load, and responsiveness from the server.

It's advised to only use this plugin if you know WordPress is doing a lot under the hood to generate the markup sent to the browser, e.g. using Elementor, etc.

## Where is the cache on-disk?

Cached posts are normally stored in `wp-content/uploads/aubreypwd/simple-cache-engine/cache/post-<ID>.html` files.

## How can by bypass the cache for a URL?

Just add `?bypass_cache` to any URL.

## How can I manually regenerate the cache for a post?

Save the post in the admin, or add `?refresh_cache` to any posts' URL to regenerate the cache for it.

## How do I exclude posts from the cache?

For any post, simple add a filter for it by id:

```php
add_filter( 'aubreypwd\simple_caching_engine\exclude_posts', function( $excluded_posts ) {
	return array_merge( $excluded_posts, [ 12, 13 ] );
} );
```

This will exclude posts `12` and `13` from using the caching engine.

## How can I adjust the priority for the cache?

Cached items are both generated and served on the `template_redirect` hook. By default it hooks into `template_redirect` with a priority of `PHP_INT_MAX` to ensure that the cache is served and generated after any redirects/etc. But, depending on the circumstance, this priority can be changed via this filter, e.g.:

```php
add_filter( 'aubreypwd/simple_caching_engine/priority', function( $priority ) {
	return 10;
} );
```

You can also `define` the constant `AUBREYPWD_SIMPLE_CACHING_ENGINE_PRIORITY` to also set it in `wp-config.php`:

```php
define( 'AUBREYPWD_SIMPLE_CACHING_ENGINE_PRIORITY', 10 );
```

## How can I reset the entire cache for all posts?

The easiest way to do this is to deactivate the plugin (which will delete all cached `.html` files), and then re-activate.

## How can I disable caching dynamically?

Use the hook `aubreypwd/simple_caching_engine/disable_cache`:

```php
add_filter( 'aubreypwd/simple_caching_engine/disable_cache', '__return_true' );
```