# Simple Caching Engine

This plugin works simply by storeing the result of a WordPress post in a `.html` file on-disk. Until the post is modified, e.g. `save_post`, the cache on-disk.

This reduces what WordPress is doing dynamically on each page load, helping out TTFB, server load, and responsiveness from the server.

It's advised to only use this plugin if you know WordPress is doing a lot under the hood to generate the markup sent to the browser, e.g. using Elementor, etc.

## Where is the cache on-disk?

Cached posts are normally stored in `wp-content/uploads/aubreypwd/simple-cache-engine/cache/post-<ID>.html` files.

## How can by bypass the cache per-post?

Just add `?bypass_cache` to any URL.

## How can I manually regenerate the cache of the post?

Save the post in the admin, or add `?refresh_cache` to any URL to regenerate the cache for a post.

## How do I exclude posts from the cache?

For any post, simple add a filter for it by id:

```php
add_filter( 'aubreypwd\simple_caching_engine\exclude_posts', function( $excluded_posts ) {
	return array_merge( $excluded_posts, [ 12, 13 ] );
} );
```

This will exclude posts `12` and `13` from using the caching engine.
