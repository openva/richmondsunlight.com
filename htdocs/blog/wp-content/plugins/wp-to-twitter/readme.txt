=== WP to Twitter ===
Contributors: joedolson
Donate link: http://www.joedolson.com/donate/
Tags: twitter, microblogging, su.pr, bitly, yourls, redirect, shortener, post, links, social, sharing, media, tweet
Requires at least: 4.1
Tested up to: 4.4.2
License: GPLv2 or later
Text Domain: wp-to-twitter
Stable tag: 3.2.5

Posts a Twitter update when you update your WordPress blog or add a link, with your chosen URL shortening service.

== Description ==

= Post Tweets from WordPress to Twitter. =

Yep. That's the basic functionality. But it's not the only thing you can do:

* Display your Recent Tweets: Widget for your recent Tweets. Fetch Tweets from your own or any other account.
* Display Tweets based on a search: Display the Tweets resulting from a search and limit by Geolocation.
* Shorten URLs in your Tweets with popular URL shorteners, or let Twitter to do it with [t.co](http://t.co). 

[Upgrade to WP Tweets Pro](http://www.joedolson.com/wp-tweets-pro/) and schedule Tweets, set up automatic reposts, upload images and more!

[youtube https://www.youtube.com/watch?v=3YIia5dQBSk]

WP to Twitter uses a customizable Tweet template for Tweets sent when updating or editing posts and pages or custom post types. You can customize your Tweet for each post, using custom template tags to generate the Tweet. 

= Free Features =

* Use post tags as Twitter hashtags
* Use alternate URLs in place of post permalinks
* Support for Google Analytics
* Support for XMLRPC remote clients
* Select from YOURLS, Goo.gl, Bit.ly, jotURL, or Su.pr as external URL shorteners.
* Rate limiting: make sure you don't exceed Twitter's API rate limits. 

= Premium Features =

Upgrade to [WP Tweets Pro](http://www.joedolson.com/wp-tweets-pro/) for extra features, including:

* Authors can set up their own Twitter accounts in their profiles
* Time delayed Tweeting
* Scheduled Tweet management
* Simultaneously Tweet to site and author Twitter accounts
* Preview and Tweet comments
* Filter Tweets by taxonomy (categories, tags, or custom taxonomies)
* Upload images to Twitter
* Integrated Twitter Card support
* Automatically schedule Tweets of old posts
* [Check out WP Tweets PRO!](http://www.joedolson.com/wp-tweets-pro/)

Want to stay up to date on WP to Twitter? [Follow me on Twitter!](https://twitter.com/joedolson)

= Translations =

Visit the [WP to Twitter translation site](https://translate.wordpress.org/projects/wp-plugins/wp-to-twitter/stable) to see how complete the current translations are.

Translating my plug-ins is always appreciated. Work on WP to Twitter translations at <a href="https://translate.wordpress.org/projects/wp-plugins/wp-to-twitter">the WordPress translation site</a>! You'll need a WordPress.org account to contribute!

== Changelog ==

= Future =

* Use apply_filters( 'wpt_tweet_sentence', $tweet, $post_ID ) to pass custom taxonomy Tweet formats - Pending WordPress support for taxonomy meta.
* Add regex filter to detect URLs typed into Tweet fields for counting/shortening purposes. [todo]
* 4.2 added compat function for mb_substr; drop mine when I drop support for 4.1
* WP to Twitter timing bug with images?

= 3.2.6 =

* Bug fix: wrap Twitter follow button in div to prevent obscure Blink rendering bug.
* Bug fix: obscure bug saving incorrect short URL when saving draft

= 3.2.5 =

* Bug fix: added prefix to is_valid_url (function used by some other plug-ins)
* Bug fix: undismissable promotion for WP Tweets PRO
* Minor style changes

= 3.2.4 =

* Bug fix: functionalized uninstall, but placed in file only imported while WPT active.

= 3.2.3 =

* Remove Freemius integration due to excessive API load.

= 3.2.2 =

* Only call Freemius integration in admin.

= 3.2.1 =

* Bug fix: uninstall issue with Freemius
* Bug fix: extraneous function call with Freemius
* More style streamlining

= 3.2.0 =

* Bug fix: if user without permissions to edit WP to Twitter meta updated profiles, Twitter profile data was deleted.
* Bug fix: PHP notices (2) in Twitter search widget
* Bug fix: no notice to update settings when setting new URL shortener.
* Bug fix: permissions tabs non functional if custom role name had a space
* Bug fix: remove notice thrown when rate limiting is run on a Tweet not associated with a post
* Bug fix: remove notice thrown when no error defined by custom shortener.
* Design update in metabox panel
* Misc. design & text updates
* Ability to add new URL shorteners via filters ('wpt_shorten_link', 'wpt_shortener_settings', 'wpt_choose_shortener')
* Remove ability to set YOURLS as a local resource in new installs
* Added filter to disable storing URLs in post meta
* Deprecate more old jd_ prefixed functions
* Change admin page URL to match Pro version.
* Remove dependency on is_plugin_active()
* Added opt-in usage tracking via Freemius.com

= 3.1.9 =

* CSS update in Twitter feed for new iframe generated follow button
* Include target URL in information deleted when a post's Tweet History cleared
* Minor design changes
* Updated manual
* Updated text

= 3.1.8 =

* Bug fix: Add support for calendar picker in WP Tweets Pro
* New filter on random delay value

= 3.1.7 =

* Bug fix: mismatched argument count in replacements caused & to be replaced with null
* Bug fix: PHP notice on Advanced Settings screen
* Bug fix: append/prepend fields accidentally eliminated from Tweet output in truncation rewrite

= 3.1.6 =

* Rewrite: Rewrite Tweet truncation code.
* Bug fix: Make charcount aware of #longurl#
* Open up possibility of reposting more than 3 times in WP Tweets PRO through filters.
* Bug fix: issue with character counting on Scheduled Tweets screen.
* Add textdomain to plug-in header

= 3.1.5 =

* New filter allows disabling storing short URLs `wpt_store_urls`; return false.
* Disable migration routine as DB-wide function; handle only on post edit.
* Eliminate some unused variables.
* Change primary settings headings to H1 on WP 4.3 and above.
* Removed collapsible panels in settings. These are irrelevant with tabbed interface.
* Minor design changes.

= 3.1.4 =

* CSS fix for 4.3 compatibility. 
* Avoid error if administrator role is missing.
* Prevent setting rate limiting to 0.

= 3.1.3 =

* Bug fix: Fix a fallback function for mb_substr
* Bug fix: Missing Urlencoding on YOURLS post titles caused return as XML instead of JSON
* Bug fix: one rate limiting setting not deleted on uninstall
* Update Language: Australian English 

= 3.1.2 =

* Misnamed variable in 3.1.1.
* Minor update to Dutch translation
* Added partial Australian English translation

= 3.1.1 =

* Add post title to Yourls shortener query. Thanks to <a href="https://wordpress.org/support/topic/missing-post-title-on-remote-yourls-call-fix-included?replies=1">the.mnbvcx</a>.
* Bug fix: Overlooked warning if categories not defined.
* Updated wp-to-twitter.pot

= 3.1.0 = 

* Moved changelog entries older than 3.0.0 into changelog.txt
* Update PHP 4 class constructors to PHP 5.
* Added template tags for all categories and all category descriptions.
* Better loading of text domain.
* Improve preview character counting when featured images are being uploaded. (WP Tweets PRO)
* Require users to add an email to send a support request.
* Added check for constant WPT_STAGING_MODE; disables posting to Twitter on staging servers.
* New feature: Rate limiting. Enable rate limiting to restrict the number of posts per category per hour can be sent to your Twitter account.

= 3.0.7 =

* Bug fix: Twitter Feed search broken.
* Bug fix: Display issue with support form textarea.
* Address issue with input sources that have double encoded entities.
* Improved: Error messages with Twitter Feed issues.
* Add option to hide header on Twitter feed widget.
* Language Update: Portuguese (Brazil)

= 3.0.6 =

* Bug fix: missing styles from Twitter feed
* Bug fix: test whether Tweet is possibly sensitive always returned true
* New feature: display uploaded images in Twitter feed instead of link to image.
* New template tag: #longurl# - use to Tweet the unshortened URL for a post.

= 3.0.5 =

* Bug fix: Typo in fix for settings update screwed things up.

= 3.0.4 =

* Bug fix: Error with YOURLS url handler. Two reversed variable definitions.
* Bug fix: Bad URL for testing time check when WP Tweets PRO active.
* Bug fix: Update could reset some settings to defaults.
* Grammar fix to one text string. 
* Minor updates to Spanish & Portuguese translations

= 3.0.3 =

* Update Japanese translation
* Bug fix: accidentally left one debug message in override.

= 3.0.2 =

* Bug fix: obscure duplicating Tweets issue related to co-Tweeting and media uploads
* Bug fix: notice thrown if using Yourls and access to Yourls directory blocked at server.
* Revamped settings page. 
* Updated user's guide.

= 3.0.1 =

* Changed priority of wpt_twit function on save_post action so that The Events Calendar can send Tweets.
* Bug fix: ensure that arguments passed to URL shorteners for analytics are URL encoded.
* Bug fix: Clear widget cache when widget is updated.
* Bug fix: invalid argument with obsolete category filters.
* Bug fix: inconsistent labeling of API key/consumer key. 
* Bug fix: Errors in data migration for 3.0.0 fixed.
* Only show 'Tweet to' tab if individual authors options are enabled.
* Minor updates to application setup instructions.

= 3.0.0 =

* Handles case where post type identification could throw PHP warning if no post types were chosen to be Tweeted.
* Eliminated outdated compatibility function. 
* Eliminated old update notices.
* General code cleanup.
* Code documentation.
* Updated media uploading to use Uploads endpoint, replacing deprecated update_with_media endpoint. [WP Tweets PRO]
* Simplifed short URL storage
* Decreased widget cache life from 1 hour to 30 minutes.
* Added fallback Normalizer class for cases when extension is not installed.
* Added notes for the 100 HTTP code return error.
* Moved Twitter server time check out of basic set-up & set up to only run on demand.
* Minor design changes.

== Installation ==

1. Upload the `wp-to-twitter` folder to your `/wp-content/plugins/` directory
2. Activate the plugin using the `Plugins` menu in WordPress
3. Go to Settings > WP to Twitter
4. Adjust the WP to Twitter Options as you prefer them. 
5. Create a Twitter application at Twitter and Configure your OAuth keys

== Frequently Asked Questions ==

= Where are your Frequently Asked Questions? Why aren't they here? =

Right here: [WP to Twitter FAQ](http://www.joedolson.com/wp-to-twitter/support-2/). I don't maintain them here because I would prefer to only maintain one copy. This is better for everybody, since the responses are much more likely to be up to date!

= How can I help you make WP to Twitter a better plug-in? =

Writing and maintaining a plug-in is a lot of work. You can help me by providing detailed support requests (which saves me time), or by providing financial support, either via my [plug-in donations page](https://www.joedolson.com/donate/) or by [upgrading to WP Tweets Pro](http://www.wptweetspro.com/wp-tweets-pro). Believe me, your donation really makes a difference!

== Screenshots ==

1. WP to Twitter OAuth settings.
2. WP to Twitter post meta box settings.
3. WP to Twitter post meta box with WP Tweets PRO.
4. WP Tweets PRO settings.
5. Twitter Feed 
6. Settings

== Upgrade Notice ==

* 3.2.5 - Bug fix; undismissable admin notice