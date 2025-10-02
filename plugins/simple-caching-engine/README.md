# Simple Caching Engine

This plugin works simply by storeing the result of a WordPress post in a `.html` file on-disk. Until the post is modified or the cache is older than one day (the default), the cache on-disk is used.

This reduces what WordPress is doing dynamically on each page load, helping out TTFB, server load, and responsiveness from the server.

It's advised to only use this plugin if you know WordPress is doing a lot under the hood to generate the markup sent to the browser, e.g. using Elementor, etc.

## Where is the cache on-disk?

Cached posts are normally stored in `wp-content/uploads/aubreypwd/simple-cache-engine/cache/post-<ID>.html` files.

## How can by bypass the cache for a URL?

Just add any GET query parameter to the URL, caching is bypassed when `POST` or `GET` parameters are passed to the page.

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

## How do I extend the lifetime of the on-disk cache file?

Use the filter `aubreypwd/simple_caching_engine/cache_lifetime` to change the accepted lifetime of the on-disk `*.html` cache files:

```php
add_filter( 'aubreypwd/simple_caching_engine/cache_lifetime', function( $lifetime ) {
	return HOUR_IN_SECONDS;
} );
```

This will change the trusted lifetime of a cached `.html` version of a post from 1 day to an hour. If the lifetime is not trusted, the on-disk cache of the post will be re-generated.

You can also `define` the constant `AUBREYPWD_SIMPLE_CACHING_ENGINE_CACHE_FILE_LIFETIME` to set the lifetime as well.
