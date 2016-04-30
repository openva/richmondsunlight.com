=== APC Object Cache Backend ===
Contributors: markjaquith
Donate link: http://txfx.net/wordpress-plugins/donate
Tags: APC, object cache, backend, cache, performance, speed, batcache
Requires at least: 3.3
Tested up to: 3.5
Stable tag: 2.0.6

APC Object Cache provides a persistent memory-based backend for the WordPress object cache. APC must be available on your PHP install.

== Description ==

APC Object Cache provides a persistent memory-based backend for the WordPress object cache. APC must be available on your PHP install.

An object cache is a place for WordPress and WordPress extensions to store the results of complex operations. On subsequent loads, 
this data can be fetched from the cache, which will be must faster than dynamically generating it on every page load.

The APC Object Cache backend is also compatible with [Batcache][1], the powerful full page caching engine that runs on WordPress.com

Be sure to read the installation instructions, as this is **not** a traditional plugin, and needs to be installed in a specific location.

[1]: http://wordpress.org/extend/plugins/batcache/

== Installation ==

1. Verify that you have PHP 5.2.4+ and a compatible APC version installed.
2. Copy `object-cache.php` to your WordPress content directory (`wp-content/` by default).
3. Done!

== Frequently Asked Questions ==

= Does this work as a backend for Batcache? =

Yes! APC 3.1.1+ supports incrementers and handles its own cleanup of expired objects, so it works just fine for Batcache. Lower versions of APC will work, but the hits trigger will be disabled.

= Does this support versions of WordPress earlier than 3.3? =

Maybe, but I'm not going to support them, and you shouldn't still be running them!

= I share `wp-config.php` among multiple WordPress installs. How can I guarantee key uniqueness? =

Define `WP_APC_KEY_SALT` to something that is unique for each install (like an md5 of the MySQL host, database, and table prefix).

== Changelog ==
= 2.0.6 =
* Fixed a PHP notice

= 2.0.5 =
* Implements `wp_cache_switch_to_blog()`
* Degrades to the built-in PHP-memory cache when APC is not available (now plays with WP-CLI)
* Clone objects before storing them to the local cache, so changes to them don't corrupt the cache
* Clear the local PHP memory cache when the APC cache is cleared

= 2.0.4 =
* `die()` when people mistakenly try to activate this as a plugin, and provide a helpful message for where they should move the file

= 2.0.3 =
* Parity with the Memcache backend, as much as was possible
* Object cloning
* Requires WP 3.1+
* Fix double-equals vs triple-equals bug with boolean true values

= 2.0.2 =
* Perform the `md5( ABSPATH )` calculation once per load (props jdub)
* Allow users of complex `wp-config.php` setups to define `WP_APC_KEY_SALT` to guarantee key uniqueness (props jdub)
* Lose the `preg_replace()` call in `::key()` (props jdub)
* Rename the `incr` method to `incr2` and then conditionally add `incr` via class extension (so that Batcache can properly detect incrementor support)
* Convert arrays to ArrayObject objects (APC does not cache multi-level arrays or arrays of objects, so this works around that)
* Require PHP 5.2+

= 2.0.1 =
* Fixed bugs in wp_cache_delete()

= 2.0 =
* First version in SVN
* Updated to support increment/decrement and feature parity with the Memcached backend (except for multiget support)

== Upgrade Notice ==
= 2.0.5 =
Upgrade for better WordPress Multisite support and WP-CLI support.

= 2.0.4 =
More helpful error message for people who try to activate this as a plugin.

= 2.0.3 =
Object cloning and a fix for the boolean true value bug. Parity with Memcache backend, as much as was possible.

= 2.0.2 =
Support for lower versions of APC (Batcache, especially). Adds support for more esoteric `wp-config.php` setups, and adds minor performance tweaks.

= 2.0.1 =
Fixed bugs regarding wp_cache_delete()

= 2.0 =
First update in four years! This should last you a while.
