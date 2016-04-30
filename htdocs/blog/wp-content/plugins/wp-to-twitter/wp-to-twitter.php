<?php
/*
Plugin Name: WP to Twitter
Plugin URI: http://www.joedolson.com/wp-to-twitter/
Description: Posts a Tweet when you update your WordPress blog or post a link, using your URL shortening service. Rich in features for customizing and promoting your Tweets.
Version: 3.2.6
Author: Joseph Dolson
Text Domain: wp-to-twitter
Domain Path: /lang
Author URI: http://www.joedolson.com/
*/
/*  Copyright 2008-2016  Joseph C Dolson  (email : plugins@joedolson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

define( 'WPT_DEBUG', false ); // Debugging only works with WP Tweets PRO. 
define( 'WPT_DEBUG_ADDRESS', get_option( 'admin_email' ) );
define( 'WPT_FROM', "From: \"" . get_option( 'blogname' ) . "\" <" . get_option( 'admin_email' ) . ">" );
// define( 'WPT_DEBUG_ADDRESS', 'debug@joedolson.com, yourname@youraddress.com' ); // for multiple recipients.

require_once( plugin_dir_path( __FILE__ ) . '/wpt-functions.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wp-to-twitter-oauth.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wp-to-twitter-shorteners.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wp-to-twitter-manager.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wpt-truncate.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wpt-feed.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wpt-widget.php' );
require_once( plugin_dir_path( __FILE__ ) . '/wpt-rate-limiting.php' );

global $wpt_version;
$wpt_version = "3.2.6";

add_action( 'plugins_loaded', 'wpt_load_textdomain' );
function wpt_load_textdomain() {
	load_plugin_textdomain( 'wp-to-twitter', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}

// check for OAuth configuration
function wpt_check_oauth( $auth = false ) {
	if ( ! function_exists( 'wtt_oauth_test' ) ) {
		$oauth = false;
	} else {
		$oauth = wtt_oauth_test( $auth );
	}

	return $oauth;
}

function wpt_check_version() {
	global $wpt_version;
	$prev_version = ( get_option( 'wp_to_twitter_version' ) != '' ) ? get_option( 'wp_to_twitter_version' ) : '1.0.0';
	if ( version_compare( $prev_version, $wpt_version, "<" ) ) {
		wptotwitter_activate();
	}
}

function wptotwitter_activate() {
	// If this has never run before, do the initial setup.
	$new_install = ( get_option( 'wpt_twitter_setup' ) == 1 || get_option( 'twitterInitialised' ) == 1 ) ? false : true;
	if ( $new_install ) {
		$initial_settings = array(
			'post' => array(
				'post-published-update' => 1,
				'post-published-text'   => 'New post: #title# #url#',
				'post-edited-update'    => 0,
				'post-edited-text'      => 'Post Edited: #title# #url#'
			),
			'page' => array(
				'post-published-update' => 0,
				'post-published-text'   => 'New page: #title# #url#',
				'post-edited-update'    => 0,
				'post-edited-text'      => 'Page edited: #title# #url#'
			)
		);
		update_option( 'wpt_post_types', $initial_settings );
		update_option( 'jd_twit_blogroll', '1' );
		update_option( 'newlink-published-text', 'New link: #title# #url#' );
		update_option( 'jd_shortener', '1' );
		update_option( 'jd_strip_nonan', '0' );
		update_option( 'jd_max_tags', 3 );
		update_option( 'jd_max_characters', 15 );
		update_option( 'jd_replace_character', '' );
		$administrator = get_role( 'administrator' );
		if ( is_object( $administrator ) ) {
			$administrator->add_cap( 'wpt_twitter_oauth' ); // wpt_twitter_oauth is the general permission for editing user accounts
			$administrator->add_cap( 'wpt_twitter_custom' );
			$administrator->add_cap( 'wpt_twitter_switch' );
			$administrator->add_cap( 'wpt_can_tweet' );
			$administrator->add_cap( 'wpt_tweet_now' );
		}
		$editor = get_role( 'editor' );
		if ( is_object( $editor ) ) {
			$editor->add_cap( 'wpt_can_tweet' );
		}
		$author = get_role( 'author' );
		if ( is_object( $author ) ) {
			$author->add_cap( 'wpt_can_tweet' );
		}
		$contributor = get_role( 'contributor' );
		if ( is_object( $contributor ) ) {
			$contributor->add_cap( 'wpt_can_tweet' );
		}

		update_option( 'jd_twit_remote', '0' );
		update_option( 'jd_post_excerpt', 30 );
		// Use Google Analytics with Twitter
		update_option( 'twitter-analytics-campaign', 'twitter' );
		update_option( 'use-twitter-analytics', '0' );
		update_option( 'jd_dynamic_analytics', '0' );
		update_option( 'no-analytics', 1 );
		update_option( 'use_dynamic_analytics', 'category' );
		// Use custom external URLs to point elsewhere. 
		update_option( 'jd_twit_custom_url', 'external_link' );
		// Error checking
		update_option( 'wp_url_failure', '0' );
		// Default publishing options.
		update_option( 'jd_tweet_default', '0' );
		update_option( 'jd_tweet_default_edit', '0' );
		update_option( 'wpt_inline_edits', '0' );
		// Note that default options are set.
		update_option( 'wpt_twitter_setup', '1' );
		//YOURLS API
		update_option( 'jd_keyword_format', '0' );	
	}
	
	global $wpt_version;
	$prev_version = get_option( 'wp_to_twitter_version' );
	// this is a switch to plan for future versions
	$administrator = get_role( 'administrator' );
	// version 2.4.0: 05/20/2012
	$upgrade       = version_compare( $prev_version, "2.4.0", "<" );
	if ( $upgrade ) {
		$perms = get_option( 'wtt_user_permissions' );
		switch ( $perms ) {
			case 'read':
				$update = 'subscriber';
				break;
			case 'edit_posts':
				$update = 'contributor';
				break;
			case 'publish_posts':
				$update = 'author';
				break;
			case 'moderate_comments':
				$update = 'editor';
				break;
			case 'manage_options':
				$update = 'administrator';
				break;
			default:
				$update = 'administrator';
		}
		update_option( 'wtt_user_permissions', $update );
	}
	$upgrade = version_compare( $prev_version, "2.4.1", "<" );
	if ( $upgrade ) {
		$subscriber  = get_role( 'subscriber' );
		$contributor = get_role( 'contributor' );
		$author      = get_role( 'author' );
		$editor      = get_role( 'editor' );
		$administrator->add_cap( 'wpt_twitter_oauth' );
		$administrator->add_cap( 'wpt_twitter_custom' );
		$administrator->add_cap( 'wpt_twitter_switch' ); // can toggle tweet/don't tweet
		switch ( get_option( 'wtt_user_permissions' ) ) { // users that can add twitter information
			case 'subscriber':
				$subscriber->add_cap( 'wpt_twitter_oauth' );
				$contributor->add_cap( 'wpt_twitter_oauth' );
				$author->add_cap( 'wpt_twitter_oauth' );
				$editor->add_cap( 'wpt_twitter_oauth' );
				break;
			case 'contributor':
				$contributor->add_cap( 'wpt_twitter_oauth' );
				$author->add_cap( 'wpt_twitter_oauth' );
				$editor->add_cap( 'wpt_twitter_oauth' );
				break;
			case 'author':
				$author->add_cap( 'wpt_twitter_oauth' );
				$editor->add_cap( 'wpt_twitter_oauth' );
				break;
			case 'editor':
				$editor->add_cap( 'wpt_twitter_oauth' );
				break;
			case 'administrator':
				break;
			default:
				$role = get_role( get_option( 'wtt_user_permissions' ) );
				if ( is_object( $role ) ) {
					$role->add_cap( 'wpt_twitter_oauth' );
				}
				break;
		}
		switch ( get_option( 'wtt_show_custom_tweet' ) ) { // users that can compose a custom tweet
			case 'subscriber':
				$subscriber->add_cap( 'wpt_twitter_custom' );
				$contributor->add_cap( 'wpt_twitter_custom' );
				$author->add_cap( 'wpt_twitter_custom' );
				$editor->add_cap( 'wpt_twitter_custom' );
				break;
			case 'contributor':
				$contributor->add_cap( 'wpt_twitter_custom' );
				$author->add_cap( 'wpt_twitter_custom' );
				$editor->add_cap( 'wpt_twitter_custom' );
				break;
			case 'author':
				$author->add_cap( 'wpt_twitter_custom' );
				$editor->add_cap( 'wpt_twitter_custom' );
				break;
			case 'editor':
				$editor->add_cap( 'wpt_twitter_custom' );
				break;
			case 'administrator':
				break;
			default:
				$role = get_role( get_option( 'wtt_show_custom_tweet' ) );
				if ( is_object( $role ) ) {
					$role->add_cap( 'wpt_twitter_custom' );
				}
				break;
		}
	}
	// version 2.4.13: 10/12/2012
	$upgrade = version_compare( $prev_version, "2.4.13", "<" );
	if ( $upgrade ) {
		$administrator->add_cap( 'wpt_can_tweet' );
		$editor = get_role( 'editor' );
		if ( is_object( $editor ) ) {
			$editor->add_cap( 'wpt_can_tweet' );
		}
		$author = get_role( 'author' );
		if ( is_object( $author ) ) {
			$author->add_cap( 'wpt_can_tweet' );
		}
		$contributor = get_role( 'contributor' );
		if ( is_object( $contributor ) ) {
			$contributor->add_cap( 'wpt_can_tweet' );
		}
		update_option( 'wpt_can_tweet', 'contributor' );
	}
	$upgrade = version_compare( $prev_version, "2.9.0", "<" );
	if ( $upgrade ) {
		$administrator->add_cap( 'wpt_tweet_now' );
	}
		
	update_option( 'wp_to_twitter_version', $wpt_version );
}

/**
 *  Migrates post meta to new format when post is called in editor.
 */
add_action( 'load-post.php', 'wpt_migrate_url_meta' );
function wpt_migrate_url_meta() {
	$post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : false;
	if ( !$post_id ) { 
		// if this is a new post screen, no migration
		return;
	}
	$post = get_post( $post_id );
	if ( strtotime( $post->post_date ) > 1449764285 ) {
		// if this post was added after the migration function was added, it will not need to be migrated. Guaranteed.
		return;
	}
	
	$short = get_post_meta( $post_id, '_wpt_short_url', true );
	if ( $short != '' ) { 
		return; 
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_goo', true );
		delete_post_meta( $post_id, '_wp_jd_goo' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_supr', true );
		delete_post_meta( $post_id, '_wp_jd_supr' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_wp', true );
		delete_post_meta( $post_id, '_wp_jd_wp' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_ind', true );
		delete_post_meta( $post_id, '_wp_jd_ind' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_yourls', true );
		delete_post_meta( $post_id, '_wp_jd_yourls' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_url', true );
		delete_post_meta( $post_id, '_wp_jd_url' );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_joturl', true );
		delete_post_meta( $post_id, '_wp_jd_joturl' );
	}
	if ( $short == "" ) {
		// don't delete target link
		$short = get_post_meta( $post_id, '_wp_jd_target', true );
	}
	if ( $short == "" ) {
		$short = get_post_meta( $post_id, '_wp_jd_clig', true );
		delete_post_meta( $post_id, '_wp_jd_clig' );
	}
	
	if ( $short == '' ) {
		$short = get_permalink( $post_id );
	}
	
	update_post_meta( $post_id, '_wpt_short_url', $short );
}

// Function checks for an alternate URL to be Tweeted. Contribution by Bill Berry.	
function wpt_link( $post_ID ) {
	$ex_link       = false;
	$external_link = get_option( 'jd_twit_custom_url' );
	$permalink     = get_permalink( $post_ID );
	if ( $external_link != '' ) {
		$ex_link = get_post_meta( $post_ID, $external_link, true );
	}

	return ( $ex_link ) ? $ex_link : $permalink;
}

function wpt_saves_error( $id, $auth, $twit, $error, $http_code, $ts ) {
	$http_code = (int) $http_code;
	if ( $http_code != 200 ) {
		add_post_meta( $id, '_wpt_failed', array(
				'author'    => $auth,
				'sentence'  => $twit,
				'error'     => $error,
				'code'      => $http_code,
				'timestamp' => $ts
			) );
	} else {
		if ( get_option( 'wpt_rate_limiting' ) == 1 ) {
			wpt_log_success( $auth, $ts, $id );
		}
	}
}


/*
 * Checks whether WP to Twitter has sent a tweet on this post to this author within the last 30 seconds and blocks it if so. Prevents double posting.
 *
 * uses filter wpt_recent_tweet_threshold
 */
function wpt_check_recent_tweet( $id, $auth ) {
	if ( ! $id ) {
		return false;
	} else {
		if ( $auth == false ) {
			$transient = get_transient( "_wpt_most_recent_tweet_$id" );
		} else {
			$transient = get_transient( "_wpt_" . $auth . "_most_recent_tweet_$id" );
		}
		if ( $transient ) {
			return true;
		} else {
			$expire = apply_filters( 'wpt_recent_tweet_threshold', 30 );
			// if expiration is 0, don't set the transient. We don't want permanent transients.
			if ( $expire !== 0 ) {
				wpt_mail( "Tweet transient being set: $id", "$expire / $auth / $id" );
				if ( $auth == false ) {
					set_transient( "_wpt_most_recent_tweet_$id", true, $expire );
				} else {
					set_transient( "_wpt_" . $auth . "_most_recent_tweet_$id", true, $expire );
				}
			}
		}
	}

	return false;
}

/**
 * Performs the API post to Twitter
 * 
 * @param string $twit Text of Tweet to be sent to Twitter
 * @param integer $auth Author ID
 * @param integer $id Post ID
 * @param boolean $media Whether to upload media attached to the post specified in $id
 * 
 * @return boolean Success of query. 
 */
function jd_doTwitterAPIPost( $twit, $auth = false, $id = false, $media = false ) {
	$recent     = wpt_check_recent_tweet( $id, $auth );
	
	if ( get_option( 'wpt_rate_limiting' ) == 1 ) {
		// check whether this post needs to be rate limited.
		$continue = wpt_test_rate_limit( $id, $auth );
		if ( !$continue ) {
			return false;
		}
	}

	$http_code = 0;
	if ( $recent ) {
		return false;
	}
	
	if ( ! wpt_check_oauth( $auth ) ) {
		$error = __( 'This account is not authorized to post to Twitter.', 'wp-to-twitter' );
		wpt_saves_error( $id, $auth, $twit, $error, '401', time() );
		wpt_set_log( 'wpt_status_message', $id, $error );

		return false;
	} // exit silently if not authorized
	$check = ( ! $auth ) ? get_option( 'jd_last_tweet' ) : get_user_meta( $auth, 'wpt_last_tweet', true ); // get user's last tweet
	// prevent duplicate Tweets
	if ( $check == $twit ) {
		wpt_mail( "Matched: tweet identical: #$id", "This Tweet: $twit; Check Tweet: $check; $auth, $id, $media" ); // DEBUG
		$error = __( 'This tweet is identical to another Tweet recently sent to this account.', 'wp-to-twitter' ) . ' ' . __( 'Twitter requires all Tweets to be unique.', 'wp-to-twitter' );
		wpt_saves_error( $id, $auth, $twit, $error, '403-1', time() );
		wpt_set_log( 'wpt_status_message', $id, $error );

		return false;
	} else if ( $twit == '' || ! $twit ) {
		wpt_mail( "Tweet check: empty sentence: #$id", "$twit, $auth, $id, $media" ); // DEBUG
		$error = __( 'This tweet was blank and could not be sent to Twitter.', 'wp-tweets-pro' );
		wpt_saves_error( $id, $auth, $twit, $error, '403-2', time() );
		wpt_set_log( 'wpt_status_message', $id, $error );

		return false;
	} else {
		$media_id = false;
		// must be designated as media and have a valid attachment
		$attachment = ( $media ) ? wpt_post_attachment( $id ) : false;
		if ( $attachment ) {
			wpt_mail( 'Has Upload', "$auth, $attachment" );
			$meta = wp_get_attachment_metadata( $attachment );
			if ( ! isset( $meta['width'], $meta['height'] ) ) {
				wpt_mail( "Image Data Does not Exist for Attachment #$attachment", print_r( $meta, 1 ) );
				$attachment = false;
			}
		}
		$api = "https://api.twitter.com/1.1/statuses/update.json";
		$upload_api = 'https://upload.twitter.com/1.1/media/upload.json';
		$status = array(
					'status'           => $twit,
					'source'           => 'wp-to-twitter',
					'include_entities' => 'true'
				);
		// support for HTTP deprecated as of 1/14/2014 -- https://dev.twitter.com/discussions/24239
		if ( wtt_oauth_test( $auth ) && ( $connection = wtt_oauth_connection( $auth ) ) ) {
			if ( $media && $attachment && !$media_id ) {
				$media_id = $connection->media( $upload_api, array( 'auth'=>$auth, 'media'=>$attachment ) );
				wpt_mail( 'Media Uploaded', "$auth, $media_id, $attachment" );
				if ( $media_id ) {
					$status['media_ids'] = $media_id;
				}
			}
		}
		if ( empty( $connection ) ) {
			$connection = array( 'connection' => 'undefined' );
		} else {
			$staging_mode = apply_filters( 'wpt_staging_mode', false, $auth, $id );
			if ( ( defined( 'WPT_STAGING_MODE' ) && WPT_STAGING_MODE == true ) || $staging_mode ) {
				// if in staging mode, we'll behave as if the Tweet succeeded, but not send it.
				$connection = true;
				$http_code = 200;
				$notice = __( 'In Staging Mode:', 'wp-to-twitter' ) . ' ';
			} else {
				$connection->post( $api, $status );
				$http_code = ( $connection ) ? $connection->http_code : 'failed';
				$notice = '';
			}			
		}
		wpt_mail( 'Twitter Connection', print_r( $connection, 1 ) . " - $twit, $auth, $id, $media" );
		if ( $connection ) {
			if ( isset( $connection->http_header['x-access-level'] ) && $connection->http_header['x-access-level'] == 'read' ) {
				$supplement = sprintf( __( 'Your Twitter application does not have read and write permissions. Go to <a href="%s">your Twitter apps</a> to modify these settings.', 'wp-to-twitter' ), 'https://dev.twitter.com/apps/' );
			} else {
				$supplement = '';
			}
			$return = false;
			switch ( $http_code ) {
				case '100':
					$error = __( "100 Continue: Twitter received the header of your submission, but your server did not follow through by sending the body of the data.", 'wp-to-twitter' );
					break;				
				case '200':
					$return = true;
					$error  = __( "200 OK: Success!", 'wp-to-twitter' );
					update_option( 'wpt_authentication_missing', false );
					break;
				case '304':
					$error = __( "304 Not Modified: There was no new data to return", 'wp-to-twitter' );
					break;
				case '400':
					$error = __( "400 Bad Request: The request was invalid. This is the status code returned during rate limiting.", 'wp-to-twitter' );
					break;
				case '401':
					$error = __( "401 Unauthorized: Authentication credentials were missing or incorrect.", 'wp-to-twitter' );
					update_option( 'wpt_authentication_missing', "$auth" );
					break;
				case '403':
					$error = __( "403 Forbidden: The request is understood, but has been refused by Twitter. Possible reasons: too many Tweets, same Tweet submitted twice, Tweet longer than 140 characters.", 'wp-to-twitter' );
					break;
				case '404':
					$error = __( "404 Not Found: The URI requested is invalid or the resource requested does not exist.", 'wp-to-twitter' );
					break;
				case '406':
					$error = __( "406 Not Acceptable: Invalid Format Specified.", 'wp-to-twitter' );
					break;
				case '422':
					$error = __( "422 Unprocessable Entity: The image uploaded could not be processed..", 'wp-to-twitter' );
					break;
				case '429':
					$error = __( "429 Too Many Requests: You have exceeded your rate limits.", 'wp-to-twitter' );
					break;
				case '500':
					$error = __( "500 Internal Server Error: Something is broken at Twitter.", 'wp-to-twitter' );
					break;
				case '502':
					$error = __( "502 Bad Gateway: Twitter is down or being upgraded.", 'wp-to-twitter' );
					break;
				case '503':
					$error = __( "503 Service Unavailable: The Twitter servers are up, but overloaded with requests - Please try again later.", 'wp-to-twitter' );
					break;
				case '504':
					$error = __( "504 Gateway Timeout: The Twitter servers are up, but the request couldn't be serviced due to some failure within our stack. Try again later.", 'wp-to-twitter' );
					break;
				default:
					$error = __( "<strong>Code $http_code</strong>: Twitter did not return a recognized response code.", 'wp-to-twitter' );
					break;
			}
			$error .= ( $supplement != '' ) ? " $supplement" : '';
			// debugging
			wpt_mail( "Twitter Response: $http_code, #$id", "$http_code, $error" ); // DEBUG
			// end debugging
			if ( ! $auth ) {
				update_option( 'jd_last_tweet', $twit );
			} else {
				update_user_meta( $auth, 'wpt_last_tweet', $twit );
			}
			wpt_saves_error( $id, $auth, $twit, $error, $http_code, time() );
			if ( $http_code == '200' ) {
				$jwt = get_post_meta( $id, '_jd_wp_twitter', true );
				if ( ! is_array( $jwt ) ) {
					$jwt = array();
				}
				$jwt[] = urldecode( $twit );
				if ( empty( $_POST ) ) {
					$_POST = array();
				}
				$_POST['_jd_wp_twitter'] = $jwt;
				update_post_meta( $id, '_jd_wp_twitter', $jwt );
				if ( ! function_exists( 'wpt_pro_exists' ) ) {
					// schedule a one-time promotional box for 4 weeks after first successful Tweet.
					if ( get_option( 'wpt_promotion_scheduled' ) == false ) {
						wp_schedule_single_event( time() + ( 60 * 60 * 24 * 7 * 4 ), 'wpt_schedule_promotion_action' );
						update_option( 'wpt_promotion_scheduled', 1 );
					}
				}
			}
			if ( ! $return ) {
				wpt_set_log( 'wpt_status_message', $id, $error );
			} else {
				wpt_set_log( 'wpt_status_message', $id, $notice . __( 'Tweet sent successfully.', 'wp-to-twitter' ) );
			}

			return $return;
		} else {
			wpt_set_log( 'wpt_status_message', $id, __( 'No Twitter OAuth connection found.', 'wp-to-twitter' ) );

			return false;
		}
	}
}


/**
 * For servers without PEAR normalize installed, approximates normalization. With normalizer, executes normalization on string.
 * 
 * @param string Text to normalize
 * 
 * @return string Normalized text.
 */
function wpt_normalize( $string ) {
	if ( version_compare( PHP_VERSION, '5.0.0', '>=' ) && function_exists( 'normalizer_normalize' ) ) {
		if ( normalizer_is_normalized( $string ) ) {
			return $string;
		}

		return normalizer_normalize( $string );
	} else {
		$normalizer = new WPT_Normalizer();
		if ( $normalizer->isNormalized( $string ) ) {
				return $string;
		}
		
		return $normalizer->normalize( $string );
	}
}

/**
 * Test URL to see if is pointing to https location.
 * 
 * @param string $url
 *
 * @return boolean
 */
function wpt_is_ssl( $url ) {
	if ( stripos( $url, 'https' ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Deprecated; still used if configured. Uses old option 'tweet_categories' and verifies whether category of current post allows it to be Tweeted.
 */
function wpt_in_allowed_category( $array ) {
	$allowed_categories = get_option( 'tweet_categories' );
	if ( is_array( $array ) && is_array( $allowed_categories ) ) {
		$common = @array_intersect( $array, $allowed_categories );
		if ( count( $common ) >= 1 ) {
			return true;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

/**
 * Builds array of post info for use in Tweet functions.
 * 
 * @param integer $post_ID Post ID.
 *
 * @return array Post data used in Tweet functions. 
 */
function wpt_post_info( $post_ID ) {
	$encoding = get_option( 'blog_charset' );
	if ( $encoding == '' ) {
		$encoding = 'UTF-8';
	}
	$post         = get_post( $post_ID );
	$category_ids = false;
	$values       = array();
	$values['id'] = $post_ID;
	// get post author
	$values['postinfo']      = $post;
	$values['postContent']   = $post->post_content;
	$values['authId']        = $post->post_author;
	$postdate                = $post->post_date;
	$altformat               = "Y-m-d H:i:s";
	$dateformat              = ( get_option( 'jd_date_format' ) == '' ) ? get_option( 'date_format' ) : get_option( 'jd_date_format' );
	$thisdate                = mysql2date( $dateformat, $postdate );
	$altdate                 = mysql2date( $altformat, $postdate );
	$values['_postDate']     = $altdate;
	$values['postDate']      = $thisdate;
	$moddate                 = $post->post_modified;
	$values['_postModified'] = mysql2date( $altformat, $moddate );
	$values['postModified']  = mysql2date( $dateformat, $moddate );
	// get first category
	$category   = $cat_desc = null;
	$categories = get_the_category( $post_ID );
	$cats = $cat_descs = array();
	if ( is_array( $categories ) ) {
		if ( count( $categories ) > 0 ) {
			$category = $categories[0]->cat_name;
			$cat_desc = $categories[0]->description;
		}
		foreach ( $categories AS $cat ) {
			$category_ids[] = $cat->term_id;
			$cats[] = $cat->cat_name;
			$cat_descs[] = $cat->description;
		}
		$cat_names = implode( ' ', apply_filters( 'wpt_twitter_category_names', $cats ) );
		$cat_descs  = implode( ' ', apply_filters( 'wpt_twitter_category_descs', $cat_descs ) );
	} else {
		$category     = '';
		$cat_desc     = '';
		$category_ids = array();
	}
	$values['cats']        = $cat_names;
	$values['cat_descs']   = $cat_descs;
	$values['categoryIds'] = $category_ids;
	$values['category']    = html_entity_decode( $category, ENT_COMPAT, $encoding );
	$values['cat_desc']    = html_entity_decode( $cat_desc, ENT_COMPAT, $encoding );
	$excerpt_length        = get_option( 'jd_post_excerpt' );
	$post_excerpt          = ( trim( $post->post_excerpt ) == "" ) ? @mb_substr( strip_tags( strip_shortcodes( $post->post_content ) ), 0, $excerpt_length ) : @mb_substr( strip_tags( strip_shortcodes( $post->post_excerpt ) ), 0, $excerpt_length );
	$values['postExcerpt'] = html_entity_decode( $post_excerpt, ENT_COMPAT, $encoding );
	$thisposttitle         = $post->post_title;
	if ( $thisposttitle == "" && isset( $_POST['title'] ) ) {
		$thisposttitle = $_POST['title'];
	}
	$thisposttitle = strip_tags( apply_filters( 'the_title', stripcslashes( $thisposttitle ) ) );
	// These are common sequences that may not be fixed by html_entity_decode due to double encoding
	$search = array( '&apos;', '&#039;', '&quot;', '&#034;', '&amp;', '&#038;' );
	$replace = array( "'", "'", '"', '"', '&', '&' );
	$thisposttitle = str_replace( $search, $replace, $thisposttitle );	
	$values['postTitle']  = html_entity_decode( $thisposttitle, ENT_QUOTES, $encoding );
	$values['postLink']   = wpt_link( $post_ID );
	$values['blogTitle']  = get_bloginfo( 'name' );
	$values['shortUrl']   = wpt_short_url( $post_ID );
	$values['postStatus'] = $post->post_status;
	$values['postType']   = $post->post_type;
	/**
	 * Filters post array to insert custom data that can be used in Tweet process.
	 * 
	 * @param array $values
	 * @param integer $post_ID
	 * @return array $values
	 */
	$values               = apply_filters( 'wpt_post_info', $values, $post_ID );

	return $values;
}

/**
 * Retrieve stored short URL.
 * @param $post_id
 *
 * @return mixed
 */
function wpt_short_url( $post_id ) {
	global $post_ID;
	if ( ! $post_id ) {
		$post_id = $post_ID;
	}
	$short = get_post_meta( $post_id, '_wpt_short_url', true );
	
	return $short;
}

/**
 * Identify whether a post should be uploading media. Test settings and verify whether post has images that can be uploaded.
 *
 * @param integer $post_ID
 * @param array $post_info 
 * @return boolean
 */ 
function wpt_post_with_media( $post_ID, $post_info = array() ) {
	$return = false;
	if ( isset( $post_info['wpt_image'] ) && $post_info['wpt_image'] == 1 ) {
		return $return;
	}

	if ( ! function_exists( 'wpt_pro_exists' ) || get_option( 'wpt_media' ) != '1' ) {
		$return = false;
	} else {
		if ( has_post_thumbnail( $post_ID ) || wpt_post_attachment( $post_ID ) ) {
			$return = true;
		}
	}

	return apply_filters( 'wpt_upload_media', $return, $post_ID );
}

/**
 * @deprecated
 */
function wpt_category_limit( $post_type, $post_info, $post_ID ) {
	$post_type_cats = get_object_taxonomies( $post_type );
	$continue       = true;
	if ( in_array( 'category', $post_type_cats ) ) {
		// 'category' is assigned to this post type, so apply filters.
		if ( get_option( 'jd_twit_cats' ) == '1' ) {
			$continue = ( ! wpt_in_allowed_category( $post_info['categoryIds'] ) ) ? true : false;
		} else {
			$continue = ( wpt_in_allowed_category( $post_info['categoryIds'] ) ) ? true : false;
		}
	}

	$continue = ( get_option( 'limit_categories' ) == '0' ) ? true : $continue;
	$args     = array( 'type' => $post_type, 'info' => $post_info, 'id' => $post_ID );

	return apply_filters( 'wpt_filter_terms', $continue, $args );
}

/**
 * Set up a Tweet to be sent. 
 * 
 * @param integer $post_ID Post ID.
 * @param string $type Publishing context (publishing, scheduled, xmlrpc, etc.)
 * 
 * @return integer $post_ID
 */
function wpt_tweet( $post_ID, $type = 'instant' ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_ID ) ) {
		return $post_ID;
	}
	
	wpt_check_version();
	$jd_tweet_this = get_post_meta( $post_ID, '_jd_tweet_this', true );
	$newpost       = $oldpost = $is_inline_edit = false;
	$sentence      = $template = $nptext = '';
	if ( get_option( 'wpt_inline_edits' ) != 1 ) {
		if ( isset( $_POST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) ) {
			return false;
		}
	} else {
		if ( isset( $_POST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) ) {
			$is_inline_edit = true;
		}
	}
	if ( get_option( 'jd_tweet_default' ) == 0 ) {
		$default = ( $jd_tweet_this != 'no' ) ? true : false;
	} else {
		$default = ( $jd_tweet_this == 'yes' ) ? true : false;
	}
	wpt_mail( "1: JD Tweet This Value: #$post_ID", "Tweet this: $jd_tweet_this /" . get_option( 'jd_tweet_default' ) . " / $type" ); // DEBUG
	if ( $default ) { // default switch: depend on default settings.
		$post_info = wpt_post_info( $post_ID );
		$media     = wpt_post_with_media( $post_ID, $post_info );
		if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
			$auth = ( get_option( 'wpt_cotweet_lock' ) == 'false' || ! get_option( 'wpt_cotweet_lock' ) ) ? $post_info['authId'] : get_option( 'wpt_cotweet_lock' );
		} else {
			$auth = $post_info['authId'];
		}
		/* debug data */
		wpt_mail( "2: POST Debug Data #$post_ID", "Post_Info: " . print_r( $post_info, 1 ) . "\n\nPOST: " . print_r( $_POST, 1 ) . " / $type" );
		if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true && function_exists( 'wpt_filter_post_info' ) ) {
			$filter = wpt_filter_post_info( $post_info );
			if ( $filter == true ) {
				wpt_mail( "3a: Post filtered: #$post_ID", print_r( $post_info, 1 ) . " / $type" );

				return false;
			}
		}
		/* Filter Tweet based on POST data -- allows custom filtering of unknown plug-ins, etc. */
		$filter = apply_filters( 'wpt_filter_post_data', false, $_POST );
		if ( $filter ) {
			return false;
		}
		$post_type = $post_info['postType'];
		if ( $type == 'future' || get_post_meta( $post_ID, 'wpt_publishing' ) == 'future' ) {
			$new = 1; // if this is a future action, then it should be published regardless of relationship
			wpt_mail( "4: Future post: #$post_ID", print_r( $post_info, 1 ) . " / $type" );
			delete_post_meta( $post_ID, 'wpt_publishing' );
		} else {
			// if the post modified date and the post date are the same, this is new.
			// true if first date before or equal to last date
			$new = wpt_date_compare( $post_info['_postModified'], $post_info['_postDate'] );
		}
		// post is not previously published but has been backdated: 
		// (post date is edited, but save option is 'publish')
		if ( $new == 0 && ( isset( $_POST['edit_date'] ) && $_POST['edit_date'] == 1 && ! isset( $_POST['save'] ) ) ) {
			$new = 1;
		}
		// can't catch posts that were set to a past date as a draft, then published. 
		$post_type_settings = get_option( 'wpt_post_types' );
		$post_types         = array_keys( $post_type_settings );
		if ( in_array( $post_type, $post_types ) ) {
			// identify whether limited by category/taxonomy
			$continue = wpt_category_limit( $post_type, $post_info, $post_ID );
			if ( $continue == false ) {
				return false;
			}
			// create Tweet and ID whether current action is edit or new
			$cT = get_post_meta( $post_ID, '_jd_twitter', true );
			if ( isset( $_POST['_jd_twitter'] ) && $_POST['_jd_twitter'] != '' ) {
				$cT = $_POST['_jd_twitter'];
			}
			$customTweet = ( $cT != '' ) ? stripcslashes( trim( $cT ) ) : '';
			// if ops is set and equals 'publish', this is being edited. Otherwise, it's a new post.
			if ( $new == 0 || $is_inline_edit == true ) {
				// if this is an old post and editing updates are enabled				
				if ( get_option( 'jd_tweet_default_edit' ) == 1 ) {
					$jd_tweet_this = apply_filters( 'wpt_tweet_this_edit', $jd_tweet_this, $_POST );
					if ( $jd_tweet_this != 'yes' ) {
						return false;
					}
				}
				wpt_mail( "4a: Edited post #$post_ID", "Tweet this: " . print_r( $post_info, 1 ) . " / $type" ); // DEBUG
				if ( $post_type_settings[ $post_type ]['post-edited-update'] == '1' ) {
					$nptext  = stripcslashes( $post_type_settings[ $post_type ]['post-edited-text'] );
					$oldpost = true;
				}
			} else {
				wpt_mail( "4b: New Post #$post_ID", "Tweet this: " . print_r( $post_info, 1 ) . " / $type" ); // DEBUG
				if ( $post_type_settings[ $post_type ]['post-published-update'] == '1' ) {
					$nptext  = stripcslashes( $post_type_settings[ $post_type ]['post-published-text'] );
					$newpost = true;
				}
			}
			if ( $newpost || $oldpost ) {
				$template = ( $customTweet != "" ) ? $customTweet : $nptext;
				$sentence = jd_truncate_tweet( $template, $post_info, $post_ID );
				wpt_mail( "5: Tweet Truncated #$post_ID", "Truncated Tweet: $sentence / $template / $type" ); // DEBUG
				if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
					$sentence2 = jd_truncate_tweet( $template, $post_info, $post_ID, false, $auth );
				}
			}
			if ( $sentence != '' ) {
				// WPT PRO //
				if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
					$wpt_selected_users = $post_info['wpt_authorized_users'];
					/* set up basic author/main account values */
					$auth_verified = wtt_oauth_test( $auth, 'verify' );
					if ( empty( $wpt_selected_users ) && get_option( 'jd_individual_twitter_users' ) == 1 ) {
						$wpt_selected_users = ( $auth_verified ) ? array( $auth ) : array( false );
					}
					if ( $post_info['wpt_cotweet'] == 1 || get_option( 'jd_individual_twitter_users' ) != 1 || in_array( 'main', $wpt_selected_users ) ) {
						$wpt_selected_users['main'] = false;
					}
					// filter selected users before using
					$wpt_selected_users = apply_filters( 'wpt_filter_users', $wpt_selected_users, $post_info );
					if ( $post_info['wpt_delay_tweet'] == 0 || $post_info['wpt_delay_tweet'] == '' || $post_info['wpt_no_delay'] == 'on' ) {
						foreach ( $wpt_selected_users as $acct ) {
							if ( wtt_oauth_test( $acct, 'verify' ) ) {
								jd_doTwitterAPIPost( $sentence2, $acct, $post_ID, $media );
							}
						}
					} else {
						foreach ( $wpt_selected_users as $acct ) {
							$acct = ( $acct == 'main' ) ? false : $acct;
							$offset = ( $auth != $acct ) ? apply_filters( 'wpt_random_delay', rand( 60, 480 ) ) : 0;
							if ( wtt_oauth_test( $acct, 'verify' ) ) {
								$time      = apply_filters( 'wpt_schedule_delay', ( (int) $post_info['wpt_delay_tweet'] ) * 60, $acct );
								wp_schedule_single_event( time() + $time + $offset, 'wpt_schedule_tweet_action', array(
										'id'       => $acct,
										'sentence' => $sentence,
										'rt'       => 0,
										'post_id'  => $post_ID
									) );
								if ( WPT_DEBUG && function_exists( 'wpt_pro_exists' ) ) {
									$author_id = ( $acct ) ? "#$acct" : 'Main';
									wpt_mail( "7a: Tweet Scheduled for Auth ID $author_id #$post_ID", print_r( array(
												'id'                  => $acct,
												'sentence'            => $sentence,
												'rt'                  => 0,
												'post_id'             => $post_ID,
												'timestamp'           => time() + $time + $offset,
												'current_time'        => time(),
												'timezone'            => get_option( 'gmt_offset' ),
												'timestamp_string'    => date( 'Y-m-d H:i:s', time() + $time + $offset ),
												'current_time_string' => date( 'Y-m-d H:i:s', time() ),
											), 1 ) ); // DEBUG
								}
							}
						}
					}
					/* This cycle handles scheduling the automatic retweets */
					if ( $post_info['wpt_retweet_after'] != 0 && $post_info['wpt_no_repost'] != 'on' ) {
						$repeat = $post_info['wpt_retweet_repeat'];
						$first  = true;
						foreach ( $wpt_selected_users as $acct ) {
							if ( wtt_oauth_test( $acct, 'verify' ) ) {
								for ( $i = 1; $i <= $repeat; $i ++ ) {
									$continue = apply_filters( 'wpt_allow_reposts', true, $i, $post_ID, $acct );
									if ( $continue ) {
										$retweet = apply_filters( 'wpt_set_retweet_text', $template, $i );
										$retweet = jd_truncate_tweet( $retweet, $post_info, $post_ID, true, $acct );
										// add original delay to schedule
										$delay = ( isset( $post_info['wpt_delay_tweet'] ) ) ? ( (int) $post_info['wpt_delay_tweet'] ) * 60 : 0;
										/* Don't delay the first Tweet of the group */
										$offset    = ( $first == true ) ? 0 : rand( 60, 240 ); // delay each co-tweet by 1-4 minutes
										$time      = apply_filters( 'wpt_schedule_retweet', ( $post_info['wpt_retweet_after'] ) * ( 60 * 60 ) * $i, $acct, $i, $post_info );
										wp_schedule_single_event( time() + $time + $offset + $delay, 'wpt_schedule_tweet_action', array(
												'id'       => $acct,
												'sentence' => $retweet,
												'rt'       => $i,
												'post_id'  => $post_ID
											) );
										if ( WPT_DEBUG && function_exists( 'wpt_pro_exists' ) ) {
											if ( $acct ) {
												$author_id = "#$acct";
											} else {
												$author_id = 'Main';
											}
											wpt_mail( "7b: Retweet Scheduled for Auth ID $author_id #$post_ID", print_r( array(
														'id'                  => $acct,
														'sentence'            => $retweet,
														'rt'                  => $i,
														'post_id'             => $post_ID,
														'timestamp'           => time() + $time + $offset + $delay,
														'current_time'        => time(),
														'timezone'            => get_option( 'gmt_offset' ),
														'timestamp_string'    => date( 'Y-m-d H:i:s', time() + $time + $offset + $delay ),
														'current_time_string' => date( 'Y-m-d H:i:s', time() ),
													), 1 ) ); // DEBUG
										}
										$tweet_limit = apply_filters( 'wpt_tweet_repeat_limit', 4, $post_ID );
										if ( $i == $tweet_limit ) {
											break;
										}
									}
								}
							}
							$first = false;
						}
					}
				} else {
					jd_doTwitterAPIPost( $sentence, false, $post_ID, $media );
				}
				// END WPT PRO //
			}
		} else {
			if ( WPT_DEBUG && function_exists( 'wpt_pro_exists' ) ) {
				wpt_mail( "3c: Not a Tweeted post type #$post_ID", "Post_Info: " . print_r( $post_info, 1 ) . " / $type" );
			}

			return $post_ID;
		}
	}

	return $post_ID;
}

/**
 *  Send Tweets on links in link manager. Only active if Link plug-in is installed.
 * 
 * @param integer $link_ID Database ID for link.
 * 
 * @return mixed boolean/integer link ID if successful, false if failure.
 */
function wpt_twit_link( $link_ID ) {
	wpt_check_version();
	$thislinkprivate = $_POST['link_visible'];
	if ( $thislinkprivate != 'N' ) {
		$thislinkname        = stripcslashes( $_POST['link_name'] );
		$thispostlink        = $_POST['link_url'];
		$thislinkdescription = stripcslashes( $_POST['link_description'] );
		$sentence            = stripcslashes( get_option( 'newlink-published-text' ) );
		$sentence            = str_ireplace( "#title#", $thislinkname, $sentence );
		$sentence            = str_ireplace( "#description#", $thislinkdescription, $sentence );

		if ( mb_strlen( $sentence ) > 118 ) {
			$sentence = mb_substr( $sentence, 0, 114 ) . '...';
		}
		$shrink = apply_filters( 'wptt_shorten_link', $thispostlink, $thislinkname, false, 'link' );
		if ( stripos( $sentence, "#url#" ) === false ) {
			$sentence = $sentence . " " . $shrink;
		} else {
			$sentence = str_ireplace( "#url#", $shrink, $sentence );
		}
		
		if ( stripos( $sentence, "#longurl#" ) === false ) {
			$sentence = $sentence . " " . $thispostlink;
		} else {
			$sentence = str_ireplace( "#longurl#", $thispostlink, $sentence );
		}
		
		if ( $sentence != '' ) {
			jd_doTwitterAPIPost( $sentence, false, $link_ID );
		}

		return $link_ID;
	} else {
		return false;
	}
}

/** 
 * Generate hash tags from tags set on post.
 * 
 * @param integer $post_ID Post ID.
 *
 * @return string $hashtags Hashtags in format needed for Tweet. 
 */
function wpt_generate_hash_tags( $post_ID ) {
	$hashtags       = '';
	$term_meta      = $t_id = false;
	$max_tags       = get_option( 'jd_max_tags' );
	$max_characters = get_option( 'jd_max_characters' );
	$max_characters = ( $max_characters == 0 || $max_characters == "" ) ? 100 : $max_characters + 1;
	if ( $max_tags == 0 || $max_tags == "" ) {
		$max_tags = 100;
	}
	$tags = get_the_tags( $post_ID );
	if ( $tags > 0 ) {
		$i = 1;
		foreach ( $tags as $value ) {
			if ( function_exists( 'wpt_pro_exists' ) ) {
				$t_id      = $value->term_id;
				$term_meta = get_option( "wpt_taxonomy_$t_id" );
			}
			if ( get_option( 'wpt_tag_source' ) == 'slug' ) {
				$tag = $value->slug;
			} else {
				$tag = $value->name;
			}
			$strip   = get_option( 'jd_strip_nonan' );
			$search  = "/[^\p{L}\p{N}\s]/u";
			$replace = get_option( 'jd_replace_character' );
			$replace = ( $replace == "[ ]" || $replace == "" ) ? "" : $replace;
			$tag     = str_ireplace( " ", $replace, trim( $tag ) );
			$tag     = preg_replace( '/[\/]/', $replace, $tag ); // remove forward slashes.
			$tag = ( $strip == '1' ) ? preg_replace( $search, $replace, $tag ) : $tag;
			
			switch ( $term_meta ) {
				case 1 : $newtag = "#$tag";	break;
				case 2 : $newtag = "$$tag";	break;
				case 3 : $newtag = ''; break;
				case 4 : $newtag = $tag; break;
				default: $newtag = apply_filters( 'wpt_tag_default', "#", $t_id ) . $tag;
			}
			if ( mb_strlen( $newtag ) > 2 && ( mb_strlen( $newtag ) <= $max_characters ) && ( $i <= $max_tags ) ) {
				$hashtags .= "$newtag ";
				$i ++;
			}
		}
	}
	$hashtags = trim( $hashtags );
	if ( mb_strlen( $hashtags ) <= 1 ) {
		$hashtags = "";
	}

	return $hashtags;
}

add_action( 'admin_menu', 'wpt_add_twitter_outer_box' );
/** 
 * Set up post meta box.
 */
function wpt_add_twitter_outer_box() {
	wpt_check_version();
	// add Twitter panel to post types where it's enabled.
	$wpt_post_types = get_option( 'wpt_post_types' );
	if ( function_exists( 'add_meta_box' ) ) {
		if ( is_array( $wpt_post_types ) ) {
			foreach ( $wpt_post_types as $key => $value ) {
				if ( $value['post-published-update'] == 1 || $value['post-edited-update'] == 1 ) {
					add_meta_box( 'wp2t', 'WP to Twitter', 'wpt_add_twitter_inner_box', $key, 'side' );
				}
			}
		}
	}
}

/**
 * Print post meta box
 */
function wpt_add_twitter_inner_box( $post ) {
	if ( current_user_can( 'wpt_can_tweet' ) ) {
		$is_pro = ( function_exists( 'wpt_pro_exists' ) ) ? 'pro' : 'free'; ?>
		<div class='wp-to-twitter <?php echo $is_pro; ?>'>
			<?php
		$tweet_status = '';
		$options      = get_option( 'wpt_post_types' );
		$type    = $post->post_type;
		$status  = $post->post_status;
		$post_id = $post->ID;

		$jd_tweet_this = get_post_meta( $post_id, '_jd_tweet_this', true );
		if ( ! $jd_tweet_this ) {
			$jd_tweet_this = ( get_option( 'jd_tweet_default' ) == '1' ) ? 'no' : 'yes';
		}
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && get_option( 'jd_tweet_default_edit' ) == '1' && $status == 'publish' ) {
			$jd_tweet_this = 'no';
		}
		if ( isset( $_REQUEST['message'] ) && $_REQUEST['message'] != 10 ) { // don't display when draft is updated or if no message
			if ( ! ( ( $_REQUEST['message'] == 1 ) && ( $status == 'publish' && $options[ $type ]['post-edited-update'] != 1 ) ) && $jd_tweet_this != 'no' ) {
				$log   = wpt_log( 'wpt_status_message', $post_id );
				$class = ( $log != __( 'Tweet sent successfully.', 'wp-to-twitter' ) ) ? 'error' : 'updated';
				if ( $log != '' ) {
					echo "<div class='$class'><p>$log</p></div>";
				}
			}
		}
		$previous_tweets = get_post_meta( $post_id, '_jd_wp_twitter', true );
		$failed_tweets   = get_post_meta( $post_id, '_wpt_failed' );
		$tweet           = esc_attr( stripcslashes( get_post_meta( $post_id, '_jd_twitter', true ) ) );
		$tweet           = apply_filters( 'wpt_user_text', $tweet, $status );
		$jd_template     = ( $status == 'publish' ) ? $options[ $type ]['post-edited-text'] : $options[ $type ]['post-published-text'];

		if ( $status == 'publish' && $options[ $type ]['post-edited-update'] != 1 ) {
			$tweet_status = sprintf( __( '%s will not be Tweeted on update.', 'wp-to-twitter' ), ucfirst( $type ) );
		}

		if ( $status == 'publish' && ( current_user_can( 'wpt_tweet_now' ) || current_user_can( 'manage_options' ) ) ) {
			?>
			<div class='tweet-buttons'>
				<button class='tweet button-primary'
				        data-action='tweet'><?php _e( 'Tweet Now', 'wp-to-twitter' ); ?></button>
				<?php if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() ) { ?>
					<button class='tweet schedule button-secondary' data-action='schedule'
					        disabled><?php _e( 'Schedule', 'wp-to-twitter' ); ?></button>
					<button class='time button-secondary'>
						<span class="dashicons dashicons-clock" aria-hidden="true"><span
								class="screen-reader-text"><?php _e( 'Set Date/Time', 'wp-to-twitter' ); ?></span></span>
					</button>
					<div id="jts">
						<label for='wpt_date'><?php _e( 'Date', 'wp-to-twitter' ); ?></label> 
						<input type='date'
							value=''
							class='date'
							name='wpt_datetime'
							id='wpt_date'
							data-value='<?php echo date( 'Y-m-d', current_time( 'timestamp' ) ); ?>' /><br/>
						<label for='wpt_time'><?php _e( 'Time', 'wp-to-twitter' ); ?></label> 
						<input type='text'
							value='<?php echo date_i18n( 'h:s a', current_time( 'timestamp' ) + 3600 ); ?>'
							class='time'
							name='wpt_datetime'
							id='wpt_time'/>
					</div>
				<?php } ?>
				<div class='wpt_log' aria-live='assertive'></div>
			</div>
		<?php
		}
		if ( current_user_can( 'wpt_twitter_custom' ) || current_user_can( 'manage_options' ) ) {
			?>
			<p class='jtw'>
				<label
					for="jtw"><?php _e( "Custom Twitter Post", 'wp-to-twitter', 'wp-to-twitter' ) ?></label><br/><textarea class="wpt_tweet_box" name="_jd_twitter" id="jtw" rows="2"
					cols="60"><?php echo esc_attr( $tweet ); ?></textarea>
			</p>
			<?php
			$jd_expanded = $jd_template;
			if ( get_option( 'jd_twit_prepend' ) != "" ) {
				$jd_expanded = "<span title='" . __( 'Your prepended Tweet text; not part of your template.', 'wp-to-twitter' ) . "'>" . stripslashes( get_option( 'jd_twit_prepend' ) ) . "</span> " . $jd_expanded;
			}
			if ( get_option( 'jd_twit_append' ) != "" ) {
				$jd_expanded = $jd_expanded . " <span title='" . __( 'Your appended Tweet text; not part of your template.', 'wp-to-twitter' ) . "'>" . stripslashes( get_option( 'jd_twit_append' ) ) . "</span>";
			}
			?>
			<p class='template'>
				<?php _e( 'Template:', 'wp-to-twitter' ); ?><br />
				<code><?php echo stripcslashes( $jd_expanded ); ?></code>
			</p>
			<?php
			echo apply_filters( 'wpt_custom_retweet_fields', '', $post_id );
			if ( get_option( 'jd_keyword_format' ) == 2 ) {
				$custom_keyword = get_post_meta( $post_id, '_yourls_keyword', true );
				echo "<label for='yourls_keyword'>" . __( 'YOURLS Custom Keyword', 'wp-to-twitter' ) . "</label> <input type='text' name='_yourls_keyword' id='yourls_keyword' value='$custom_keyword' />";
			}
		} else {
			?>
			<input type="hidden" name='_jd_twitter' value='<?php esc_attr_e( $tweet ); ?>'/>
		<?php
		}
		?>
		<div class='wpt-options'>
			<?php
			if ( $is_pro == 'pro' ) {
				$pro_active  = " class='active'";
				$free_active = '';
			} else {
				$free_active = " class='active'";
				$pro_active  = '';
			}
			?>
			<ul class='tabs' role="tablist">
				<?php if ( get_option( 'jd_individual_twitter_users' ) == 1 ) { ?>
					<li><a href='#authors'<?php echo $pro_active; ?> aria-controls="authors" role="tab" id="tab_authors"><?php _e( 'Tweet to', 'wp-to-twitter' ); ?></a></li>
				<?php } ?>
				<li><a href='#custom' aria-controls="custom" role="tab" id="tab_custom"><?php _e( 'Options', 'wp-to-twitter' ); ?></a></li>
				<li><a href='#notes'<?php echo $free_active; ?> aria-controls="notes" role="tab" id="tab_notes"><?php _e( 'Notes', 'wp-to-twitter' ); ?></a></li>
			</ul>
			<?php
			/* WPT PRO OPTIONS */
			if ( current_user_can( 'edit_others_posts' ) ) {
				if ( get_option( 'jd_individual_twitter_users' ) == 1 ) {
					echo "<div class='wptab' id='authors' aria-labelledby='tab_authors' role='tabpanel'>";
					$selected = ( get_post_meta( $post_id, '_wpt_authorized_users', true ) ) ? get_post_meta( $post_id, '_wpt_authorized_users', true ) : array();
					if ( function_exists( 'wpt_authorized_users' ) ) {
						echo wpt_authorized_users( $selected );
						do_action( 'wpt_authors_tab', $post_id, $selected );
					} else {
						echo "<p>";
						if ( function_exists( 'wpt_pro_exists' ) ) {
							printf( __( 'WP Tweets PRO allows you to select Twitter accounts. <a href="%s">Log in and download now!</a>', 'wp-to-twitter' ), 'http://www.joedolson.com/account/' );
						} else {
							printf( __( 'Upgrade to WP Tweets PRO to select Twitter accounts! <a href="%s">Upgrade now!</a>', 'wp-to-twitter' ), 'http://www.joedolson.com/wp-tweets-pro/' );
						}
						echo "</p>";
					}
					echo "</div>";
				}				
			}
			?>
				<div class='wptab' id='custom' aria-labelledby='tab_custom' role='tabpanel'><?php
				if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true && ( current_user_can( 'wpt_twitter_custom' ) || current_user_can( 'manage_options' ) ) ) {
					wpt_schedule_values( $post_id );
					do_action( 'wpt_custom_tab', $post_id, 'visible' );
				} else {
					if ( ! function_exists( 'wpt_pro_exists' ) ) {
						echo "<p>" .sprintf( __( 'Upgrade to WP Tweets PRO to configure options! <a href="%s">Upgrade now!</a>', 'wp-to-twitter' ), 'http://www.joedolson.com/wp-tweets-pro/' ) . "</p>";
					}
				}
				?>
				</div>
			<?php
			/* WPT PRO */
			if ( ! current_user_can( 'wpt_twitter_custom' ) && ! current_user_can( 'manage_options' ) ) {
				?>
				<div class='wptab' id='custom' aria-labelledby='tab_custom' role='tabpanel'>
					<p><?php _e( 'Access to customizing WP to Twitter values is not allowed for your user role.', 'wp-to-twitter' ); ?></p>
					<?php
					if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
						wpt_schedule_values( $post_id, 'hidden' );
						do_action( 'wpt_custom_tab', $post_id, 'hidden' );
					} ?>
				</div>
			<?php
			}
			if ( current_user_can( 'wpt_twitter_custom' ) || current_user_can( 'manage_options' ) ) {
				?>
				<div class='wptab' id='notes' aria-labelledby='tab_notes' role='tabpanel'>
					<p>
						<?php _e( "Tweets must be less than 140 characters; Twitter counts URLs as 22 or 23 characters. Template Tags: <code>#url#</code>, <code>#title#</code>, <code>#post#</code>, <code>#category#</code>, <code>#date#</code>, <code>#modified#</code>, <code>#author#</code>, <code>#account#</code>, <code>#tags#</code>, or <code>#blog#</code>, <code>#longurl#</code>.", 'wp-to-twitter' );
						do_action( 'wpt_notes_tab', $post_id );
						?>
					</p>
				</div>
			<?php
			} ?>
		</div>
		<?php
		if ( current_user_can( 'wpt_twitter_switch' ) || current_user_can( 'manage_options' ) ) {
			// "no" means 'Don't Tweet' (is checked)
			$nochecked  = ( $jd_tweet_this == 'no' ) ? ' checked="checked"' : '';
			$yeschecked = ( $jd_tweet_this == 'yes' ) ? ' checked="checked"' : '';
			?>
			<p class='toggle-btn-group'>
				<input type="radio" name="_jd_tweet_this" value="no" id="jtn"<?php echo $nochecked; ?> /><label for="jtn"><?php _e( "Don't Tweet post", 'wp-to-twitter' ); ?></label> 
				<input type="radio" name="_jd_tweet_this" value="yes" id="jty"<?php echo $yeschecked; ?> />
				<label for="jty"><?php _e( "Tweet post", 'wp-to-twitter' ); ?></label>
			</p>
		<?php
		} else {
			?>
			<input type='hidden' name='_jd_tweet_this' value='<?php echo $jd_tweet_this; ?>'/>
		<?php
		}
		wpt_show_tweets( $previous_tweets, $failed_tweets ); 
		?>
		<p class="wpt-support">
			<?php if ( ! function_exists( 'wpt_pro_exists' ) ) { 
			/* These aren't actually usages that require esc_url. But using it will give people the illusion that it does something. */
			?>
				<strong><a href="http://www.wptweetspro.com/wp-tweets-pro"><?php _e( 'Go Premium', 'wp-to-twitter', 'wp-to-twitter' ) ?></a></strong> &raquo;
			<?php } else { ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'support', admin_url( 'admin.php?page=wp-tweets-pro' ) ) ); ?>#get-support"><?php _e( 'Get Support', 'wp-to-twitter', 'wp-to-twitter' ); ?></a> &raquo;
			<?php } ?>
		</p>		
		<?php 
		if ( $tweet_status != '' ) {
			echo "<p class='disabled'>$tweet_status</p>";
		}
		?>
		</div>
	<?php
	} else { // permissions: this user isn't allowed to Tweet;
		_e( 'Your role does not have the ability to Post Tweets from this site.', 'wp-to-twitter' ); ?> <input
			type='hidden' name='_jd_tweet_this' value='no'/> <?php
	}
}

/**
 * Format history of Tweets attempted on current post.
 * 
 * @param array $previous_tweets Array of successfully sent Tweets on this post.
 * @param array $failed_tweets Array of failed Tweets sent on this post.
 * 
 * @return string Formatted list of Tweets.
 */
function wpt_show_tweets( $previous_tweets, $failed_tweets ) {
	if ( ! is_array( $previous_tweets ) && $previous_tweets != '' ) {
		$previous_tweets = array( 0 => $previous_tweets );
	}
	if ( ! empty( $previous_tweets ) || ! empty( $failed_tweets ) ) {
		?>
		<hr>
		<p class='panel-toggle'>
			<a href='#wpt_tweet_history' class='history-toggle'><span class='dashicons dashicons-plus' aria-hidden="true"></span><?php _e( 'View Tweet History', 'wp-to-twitter' ); ?></a>
		</p>
		<div class='history'>
		<p class='error'><em><?php _e( 'Previous Tweets', 'wp-to-twitter' ); ?>:</em></p>
		<ul>
			<?php
			$has_history   = false;
			$hidden_fields = '';
			if ( is_array( $previous_tweets ) ) {
				foreach ( $previous_tweets as $previous_tweet ) {
					if ( $previous_tweet != '' ) {
						$has_history = true;
						$hidden_fields .= "<input type='hidden' name='_jd_wp_twitter[]' value='" . esc_attr( $previous_tweet ) . "' />";
						echo "<li>$previous_tweet <a href='http://twitter.com/intent/tweet?text=" . urlencode( $previous_tweet ) . "'>Retweet this</a></li>";
					}
				}
			}
			?>
		</ul>
		<?php
		$list       = false;
		$error_list = '';
		if ( is_array( $failed_tweets ) ) {
			foreach ( $failed_tweets as $failed_tweet ) {
				if ( ! empty( $failed_tweet ) ) {
					$ft     = $failed_tweet['sentence'];
					$reason = $failed_tweet['code'];
					$error  = $failed_tweet['error'];
					$list   = true;
					$error_list .= "<li> <code>Error: $reason</code> $ft <a href='http://twitter.com/intent/tweet?text=" . urlencode( $ft ) . "'>Tweet this</a><br /><em>$error</em></li>";
				}
			}
			if ( $list == true ) {
				echo "<p class='error'><em>" . __( 'Failed Tweets', 'wp-to-twitter' ) . ":</em></p>
				<ul>$error_list</ul>";
			}
		}
		echo "<div>" . $hidden_fields . "</div>";
		if ( $has_history || $list ) {
			echo "<p><input type='checkbox' name='wpt_clear_history' id='wptch' value='clear' /> <label for='wptch'>" . __( 'Delete Tweet History', 'wp-to-twitter' ) . "</label></p>";
		}
		echo "</div>";
	}
}

add_action( 'admin_enqueue_scripts', 'wpt_admin_scripts', 10, 1 );
/**
 * Enqueue admin scripts for WP to Twitter and WP Tweets PRO.
 */
function wpt_admin_scripts() {
	global $current_screen, $wpt_version;
	if ( $current_screen->base == 'post' || $current_screen->id == 'wp-tweets-pro_page_wp-to-twitter-schedule' ) {
		wp_enqueue_script( 'charCount', plugins_url( 'wp-to-twitter/js/jquery.charcount.js' ), array( 'jquery' ), $wpt_version );
	}
	if ( $current_screen->base == 'post' && isset( $_GET['post'] ) && ( current_user_can( 'wpt_tweet_now' ) || current_user_can( 'manage_options' ) ) ) {
		wp_enqueue_script( 'wpt.ajax', plugins_url( 'js/ajax.js', __FILE__ ), array( 'jquery' ), $wpt_version );
		wp_localize_script( 'wpt.ajax', 'wpt_data', array(
			'post_ID'  => (int) $_GET['post'],
			'action'   => 'wpt_tweet',
			'security' => wp_create_nonce( 'wpt-tweet-nonce' )
		) );
	}
	if ( $current_screen->id == 'settings_page_wp-to-twitter/wp-to-twitter' || $current_screen->id == 'toplevel_page_wp-tweets-pro' ) {
		wp_enqueue_script( 'wpt.tabs', plugins_url( 'js/tabs.js', __FILE__ ), array( 'jquery' ), $wpt_version );
		wp_localize_script( 'wpt.tabs', 'firstItem', 'wpt_post' );
		wp_localize_script( 'wpt.tabs', 'firstPerm', 'wpt_editor' );
		wp_enqueue_script( 'dashboard' );
	}
}

add_action( 'wp_ajax_wpt_tweet', 'wpt_ajax_tweet' );
/**
 * Handle Tweets sent via Ajax Tweet Now/Schedule Tweet buttons.
 *
 * @return string Confirmation message indicating success or failure of Tweeting.
 */
function wpt_ajax_tweet() {
	if ( ! check_ajax_referer( 'wpt-tweet-nonce', 'security', false ) ) {
		echo "Invalid Security Check";
		die;
	}
	$action       = ( $_REQUEST['tweet_action'] == 'tweet' ) ? 'tweet' : 'schedule';
	// This isn't used right now, because of time. 
	$authors      = ( isset( $_REQUEST['tweet_auth'] ) && $_REQUEST['tweet_auth'] != null ) ? $_REQUEST['tweet_auth'] : false;
	$current_user = wp_get_current_user();
	if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() ) {
		if ( wtt_oauth_test( $current_user->ID, 'verify' ) ) {
			$auth = $user_ID = $current_user->ID;
		} else {
			$auth    = false;
			$user_ID = $current_user->ID;
		}
	} else {
		$auth    = false;
		$user_ID = $current_user->ID;
	}
	if ( current_user_can( 'wpt_can_tweet' ) ) {
		$options        = get_option( 'wpt_post_types' );
		$post_ID        = intval( $_REQUEST['tweet_post_id'] );
		$type           = get_post_type( $post_ID );
		$default        = ( isset( $options[ $type ]['post-edited-text'] ) ) ? $options[ $type ]['post-edited-text'] : '';
		$sentence       = ( isset( $_REQUEST['tweet_text'] ) && trim( $_REQUEST['tweet_text'] ) != '' ) ? $_REQUEST['tweet_text'] : $default;
		$sentence       = stripcslashes( trim( $sentence ) );
		$post_info      = wpt_post_info( $post_ID );		
		$sentence       = jd_truncate_tweet( $sentence, $post_info, $post_ID, false, $user_ID );
		$schedule       = ( isset( $_REQUEST['tweet_schedule'] ) ) ? strtotime( $_REQUEST['tweet_schedule'] ) : rand( 60, 240 );
		$print_schedule = date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $schedule );
		$offset         = ( 60 * 60 * get_option( 'gmt_offset' ) );
		$schedule       = $schedule - $offset;
		$media          = wpt_post_with_media( $post_ID, $post_info );
		switch ( $action ) {
			case 'tweet' :
				jd_doTwitterAPIPost( $sentence, $auth, $post_ID, $media );
				break;
			case 'schedule' :
				wp_schedule_single_event( $schedule, 'wpt_schedule_tweet_action', array(
						'id'       => $auth,
						'sentence' => $sentence,
						'rt'       => 0,
						'post_id'  => $post_ID
					) );
				break;
		}
		$return = ( $action == 'tweet' ) ? wpt_log( 'wpt_status_message', $post_ID ) : "Tweet scheduled: '$sentence' for $print_schedule";
		echo $return;
	} else {
		echo __( 'You are not authorized to perform this action', 'wp-to-twitter' );
	}
	die;
}

add_action( 'admin_head', 'wpt_admin_script' );
/**
 * Print scripts to WP Tweets PRO pages. 
 */
function wpt_admin_script() {
	global $current_screen;
	if ( $current_screen->base == 'post' || $current_screen->id == 'wp-tweets-pro_page_wp-to-twitter-schedule' ) {
		wp_register_style( 'wpt-post-styles', plugins_url( 'css/post-styles.css', __FILE__ ) );
		wp_enqueue_style( 'wpt-post-styles' );
		if ( $current_screen->base == 'post' ) {
			$allowed = 140 - mb_strlen( get_option( 'jd_twit_prepend' ) . get_option( 'jd_twit_append' ) );
		} else {
			$allowed = ( wpt_is_ssl( home_url() ) ) ? 137 : 138;
		}
		if ( function_exists( 'wpt_pro_exists' ) && get_option( 'jd_individual_twitter_users' ) == 1 ) {
			$first = '#authors';
		} else if ( function_exists( 'wpt_pro_exists' ) ) {
			$first = '#custom';
		} else {
			$first = '#notes';
		}
		wp_register_script( 'wpt-base-js', plugins_url( 'js/base.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'wpt-base-js' );
		wp_localize_script( 'wpt-base-js', 'wptSettings', array(
				'allowed' => $allowed,
				'first'   => $first,
				'text'    => __( 'Characters left: ', 'wp-to-twitter' )
			) );
		echo "
<style type='text/css'>
#wp2t h3 span { padding-left: 30px; background: url(" . plugins_url( 'wp-to-twitter/images/twitter-bird-light-bgs.png' ) . ") left 50% no-repeat; }
</style>";
	}
}

/**
 * Post the Custom Tweet & custom Tweet data into the post meta table
 *
 * @param integer $id Post ID.
 *
 */
function wpt_save_post( $id ) {
	if ( empty( $_POST ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || isset( $_POST['_inline_edit'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! wpt_in_post_type( $id ) ) {
		return;
	}
	if ( isset( $_POST['_yourls_keyword'] ) ) {
		$yourls = $_POST['_yourls_keyword'];
		$update = update_post_meta( $id, '_yourls_keyword', $yourls );
	}
	if ( isset( $_POST['_jd_twitter'] ) && $_POST['_jd_twitter'] != '' ) {
		$jd_twitter = $_POST['_jd_twitter'];
		$update     = update_post_meta( $id, '_jd_twitter', $jd_twitter );
	} else if ( isset( $_POST['_jd_twitter'] ) && $_POST['_jd_twitter'] == '' ) {
		delete_post_meta( $id, '_jd_twitter' );
	}
	if ( isset( $_POST['_jd_wp_twitter'] ) && $_POST['_jd_wp_twitter'] != '' ) {
		$jd_wp_twitter = $_POST['_jd_wp_twitter'];
		$update        = update_post_meta( $id, '_jd_wp_twitter', $jd_wp_twitter );
	}
	if ( isset( $_POST['_jd_tweet_this'] ) ) {
		$jd_tweet_this = ( $_POST['_jd_tweet_this'] == 'no' ) ? 'no' : 'yes';
		$update        = update_post_meta( $id, '_jd_tweet_this', $jd_tweet_this );
	} else {
		if ( isset( $_POST['_wpnonce'] ) ) {
			$jd_tweet_default = ( get_option( 'jd_tweet_default' ) == 1 ) ? 'no' : 'yes';
			$update    = update_post_meta( $id, '_jd_tweet_this', $jd_tweet_default );
		}
	}
	if ( isset( $_POST['wpt_clear_history'] ) && $_POST['wpt_clear_history'] == 'clear' ) {
		delete_post_meta( $id, '_wpt_failed' );
		delete_post_meta( $id, '_jd_wp_twitter' );
		delete_post_meta( $id, '_wpt_short_url' );
		delete_post_meta( $id, '_wp_jd_twitter' );
	}
	// WPT PRO //
	$update = apply_filters( 'wpt_insert_post', $_POST, $id );
	// WPT PRO //	
	// only send debug data if post meta is updated. 
	if ( $update == true || is_int( $update ) ) {
		wpt_mail( "Post Meta Inserted: #$id", print_r( $_POST, 1 ) ); // DEBUG
	}
}

/**
 * Parse custom shortcodes 
 *
 * @param string $sentence Tweet template
 * @param integer $post_ID Post ID.
 *
 * @return string $sentence with any custom shortcodes replaced with their appropriate content.
 */
function wpt_custom_shortcodes( $sentence, $post_ID ) {
	$pattern = '/([([\[\]?)([A-Za-z0-9-_])*(\]\]]?)+/';
	$params  = array( 0 => "[[", 1 => "]]" );
	preg_match_all( $pattern, $sentence, $matches );
	if ( $matches && is_array( $matches[0] ) ) {
		foreach ( $matches[0] as $value ) {
			$shortcode = "$value";
			$field     = str_replace( $params, "", $shortcode );
			$custom    = apply_filters( 'wpt_custom_shortcode', strip_tags( get_post_meta( $post_ID, $field, true ) ), $post_ID, $field );
			$sentence  = str_replace( $shortcode, $custom, $sentence );
		}

		return $sentence;
	} else {
		return $sentence;
	}
}

/**
 * Show user profile data on Edit User pages.
 * 
 * return @string Configuration forms for WP to Twitter user-specific settings.
 */
function wpt_twitter_profile() {
	global $user_ID;
	$current_user = wp_get_current_user();
	$user_edit = ( isset( $_GET['user_id'] ) ) ? (int) $_GET['user_id'] : $user_ID;

	$is_enabled       = get_user_meta( $user_edit, 'wp-to-twitter-enable-user', true );
	$twitter_username = get_user_meta( $user_edit, 'wp-to-twitter-user-username', true );
	$wpt_remove       = get_user_meta( $user_edit, 'wpt-remove', true );
	if ( current_user_can( 'wpt_twitter_oauth' ) || current_user_can( 'manage_options' ) ) {		
		?>
		<h3><?php _e( 'WP Tweets User Settings', 'wp-to-twitter' ); ?></h3>
		<?php if ( function_exists( 'wpt_connect_oauth_message' ) ) {
			wpt_connect_oauth_message( $user_edit );
		} ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e( "Use My Twitter Username", 'wp-to-twitter' ); ?></th>
				<td>
					<input type="radio" name="wp-to-twitter-enable-user" id="wp-to-twitter-enable-user-3" value="mainAtTwitter"<?php checked( $is_enabled, 'mainAtTwitter' ); ?> /> <label for="wp-to-twitter-enable-user-3"><?php _e( "Tweet my posts with an @ reference to my username.", 'wp-to-twitter' ); ?></label><br/>
					<input type="radio" name="wp-to-twitter-enable-user" id="wp-to-twitter-enable-user-4" value="mainAtTwitterPlus"<?php checked( $is_enabled, 'mainAtTwitterPlus' ); ?> /> <label for="wp-to-twitter-enable-user-4"><?php _e( "Tweet my posts with an @ reference to both my username and to the main site username.", 'wp-to-twitter' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label
						for="wp-to-twitter-user-username"><?php _e( "Your Twitter Username", 'wp-to-twitter' ); ?></label>
				</th>
				<td><input type="text" name="wp-to-twitter-user-username" id="wp-to-twitter-user-username"
				           value="<?php esc_attr_e( $twitter_username ); ?>"/> <?php _e( 'Enter your own Twitter username.', 'wp-to-twitter' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label
						for="wpt-remove"><?php _e( "Hide account name in Tweets", 'wp-to-twitter' ); ?></label></th>
				<td><input type="checkbox" name="wpt-remove" id="wpt-remove"
				           value="on"<?php if ( $wpt_remove == 'on' ) {
						echo ' checked="checked"';
					} ?> /> <?php _e( 'Do not display my account in the #account# template tag.', 'wp-to-twitter' ); ?>
				</td>
			</tr>
			<?php if ( ! function_exists( 'wpt_pro_exists' ) ) {
				add_filter( 'wpt_twitter_user_fields', create_function( '', 'return;' ) );
			} ?>
			<?php echo apply_filters( 'wpt_twitter_user_fields', $user_edit ); ?>
		</table>
		<?php
		if ( function_exists( 'wpt_schedule_tweet' ) ) {
			if ( function_exists( 'wtt_connect_oauth' ) ) {
				wtt_connect_oauth( $user_edit );
			}
		}
	} else {
		// hidden fields. If function is enabled, but this user does not have privileges to edit.
		?>
		<input type="hidden" name="wp-to-twitter-enable-user" value="<?php esc_attr_e( $is_enabled ); ?>" />
		<input type="hidden" name="wp-to-twitter-user-username" value="<?php esc_attr_e( $twitter_username ); ?>" />
		<input type="hidden" name="wpt-remove" value="<?php esc_attr_e( $wpt_remove ); ?>" />
		<?php
	}
}

/** 
 * Save user profile data 
 */
function wpt_twitter_save_profile() {	
	global $user_ID;
	$current_user = wp_get_current_user();
	if ( isset( $_POST['user_id'] ) ) {
		$edit_id = (int) $_POST['user_id'];
	} else {
		$edit_id = $user_ID;
	}
	if ( current_user_can( 'wpt_twitter_oauth' ) || current_user_can( 'manage_options' ) ) {		
		$enable     = ( isset( $_POST['wp-to-twitter-enable-user'] ) ) ? $_POST['wp-to-twitter-enable-user'] : '';
		$username   = ( isset( $_POST['wp-to-twitter-user-username'] ) ) ? $_POST['wp-to-twitter-user-username'] : '';
		$wpt_remove = ( isset( $_POST['wpt-remove'] ) ) ? 'on' : '';
		update_user_meta( $edit_id, 'wp-to-twitter-enable-user', $enable );
		update_user_meta( $edit_id, 'wp-to-twitter-user-username', $username );
		update_user_meta( $edit_id, 'wpt-remove', $wpt_remove );
	}
	//WPT PRO
	apply_filters( 'wpt_save_user', $edit_id, $_POST );
}

/**
 * Send links to old admin to new admin page
 */
add_action( 'init', 'wpt_old_admin_redirect' );
function wpt_old_admin_redirect() {
	if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'wp-to-twitter/wp-to-twitter.php' ) {
		wp_safe_redirect( admin_url( 'admin.php?page=wp-tweets-pro' ) );
		exit;
	}
}

add_action( 'admin_menu', 'wpt_admin_page' );
/**
 * Add the administrative settings to the "Settings" menu.
 */
function wpt_admin_page() {
	if ( function_exists( 'add_menu_page' ) && ! function_exists( 'wpt_pro_functions' ) ) {
		$icon_path = plugins_url( 'images/icon.png', __FILE__ );
		$page = add_menu_page(__('WP to Twitter','wp-to-twitter'), __('WP to Twitter','wp-to-twitter'), 'manage_options', 'wp-tweets-pro', 'wpt_update_settings',$icon_path );
	}
}

add_action( 'admin_head', 'wpt_admin_style' );
/** 
 * Add stylesheets to WP to Twitter pages.
 */
function wpt_admin_style() {
	if ( isset( $_GET['page'] ) && ( $_GET['page'] == "wp-to-twitter" || $_GET['page'] == "wp-tweets-pro" || $_GET['page'] == "wp-to-twitter-schedule" || $_GET['page'] == "wp-to-twitter-tweets" || $_GET['page'] == "wp-to-twitter-errors" ) ) {
		wp_enqueue_style( 'wpt-styles', plugins_url( 'css/styles.css', __FILE__ ) );
	}
}

/** 
 * Add WP to Twitter links to plug-in information.
 */
function wpt_plugin_action( $links, $file ) {
	if ( $file == plugin_basename( dirname( __FILE__ ) . '/wp-to-twitter.php' ) ) {
		$admin_url = admin_url( 'admin.php?page=wp-tweets-pro' );
		$links[]   = "<a href='$admin_url'>" . __( 'WP to Twitter Settings', 'wp-to-twitter', 'wp-to-twitter' ) . "</a>";
		if ( ! function_exists( 'wpt_pro_exists' ) ) {
			$links[] = "<a href='http://www.wptweetspro.com/wp-tweets-pro'>" . __( 'Go Premium', 'wp-to-twitter', 'wp-to-twitter' ) . "</a>";
		}
	}

	return $links;
}

//Add Plugin Actions to WordPress
add_filter( 'plugin_action_links', 'wpt_plugin_action', - 10, 2 );

if ( get_option( 'jd_individual_twitter_users' ) == '1' ) {
	add_action( 'show_user_profile', 'wpt_twitter_profile' );
	add_action( 'edit_user_profile', 'wpt_twitter_profile' );
	add_action( 'profile_update', 'wpt_twitter_save_profile' );
}

add_action( 'in_plugin_update_message-wp-to-twitter/wp-to-twitter.php', 'wpt_plugin_update_message' );
/**
 * Parse plugin update info to display in update list.
 *
 * @return string Notice from new version's readme.txt
 */
function wpt_plugin_update_message() {
	global $wpt_version;
	$note = '';
	define( 'WPT_PLUGIN_README_URL', 'http://svn.wp-plugins.org/wp-to-twitter/trunk/readme.txt' );
	$response = wp_remote_get( WPT_PLUGIN_README_URL, array( 'user-agent' => 'WordPress/WP to Twitter' . $wpt_version . '; ' . get_bloginfo( 'url' ) ) );
	if ( ! is_wp_error( $response ) || is_array( $response ) ) {
		$data = $response['body'];
		$bits = explode( '== Upgrade Notice ==', $data );
		$note = '<div id="wpt-upgrade"><p><strong style="color:#c22;">Upgrade Notes:</strong> ' . nl2br( trim( $bits[1] ) ) . '</p></div>';
	}
	echo $note;
}

if ( get_option( 'jd_twit_blogroll' ) == '1' ) {
	add_action( 'add_link', 'wpt_twit_link' );
}

add_action( 'save_post', 'wpt_twit', 15 );
add_action( 'save_post', 'wpt_save_post', 10 );

/**
 * Check whether a given post is in an allowed post type and has an update template configured.
 * 
 * @param integer $id Post ID.
 *
 * @return boolean True if post is allowed, false otherwise.
 */
function wpt_in_post_type( $id ) {
	$post_type_settings = get_option( 'wpt_post_types' );
	if ( is_array( $post_type_settings ) && !empty( $post_type_settings ) ) {
		$post_types         = array_keys( $post_type_settings );
		$type               = get_post_type( $id );
		if ( in_array( $type, $post_types ) ) {
			if ( $post_type_settings[ $type ]['post-edited-update'] == '1' || $post_type_settings[ $type ]['post-published-update'] == '1' ) {
				return true;
			}
		}
	}
	return false;
}

add_action( 'future_to_publish', 'wpt_future_to_publish', 16 );
/**
 * Handle Tweeting posts scheduled for the future.
 */
function wpt_future_to_publish( $post ) {
	$id = $post->ID;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $id ) || ! wpt_in_post_type( $id ) ) {
		return;
	}
	wpt_twit_future( $id );
}

/**
 * Handle Tweeting posts published directly.
 */
function wpt_twit( $id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $id ) || ! wpt_in_post_type( $id ) ) {
		return;
	}
	$post = get_post( $id );	
	if ( $post->post_status != 'publish' ) {
		return;
	} // is there any reason to accept any other status?
	wpt_twit_instant( $id );
}

add_action( 'xmlrpc_publish_post', 'wpt_twit_xmlrpc' );
add_action( 'publish_phone', 'wpt_twit_xmlrpc' );

/**
 * For future posts, check transients to see whether this post has already been published. Prevents duplicate Tweet attempts in older versions of WP.
 *
 * @param integer $id Post ID.
 */
function wpt_twit_future( $id ) {
	set_transient( '_wpt_twit_future', $id, 10 );
	// instant action has already run for this post. // prevent running actions twice (need both for older WP)
	if ( get_transient( '_wpt_twit_instant' ) && get_transient( '_wpt_twit_instant' ) == $id ) {
		delete_transient( '_wpt_twit_instant' );

		return;
	}
	wpt_tweet( $id, 'future' );
}

/**
 * For immediate posts, check transients to see whether this post has already been published. Prevents duplicate Tweet attempts in older versions of WP or cases where a future action is being run after the initial action.
 *
 * @param integer $id Post ID.
 */
function wpt_twit_instant( $id ) {
	set_transient( '_wpt_twit_instant', $id, 10 );
	// future action has already run for this post.
	if ( get_transient( '_wpt_twit_future' ) && get_transient( '_wpt_twit_future' ) == $id ) {
		delete_transient( '_wpt_twit_future' );

		return;
	}
	// xmlrpc action has already run for this post.
	if ( get_transient( '_wpt_twit_xmlrpc' ) && get_transient( '_wpt_twit_xmlrpc' ) == $id ) {
		delete_transient( '_wpt_twit_xmlrpc' );

		return;
	}
	wpt_tweet( $id, 'instant' );
}

/** 
 * Tweet XMLRPC posts.
 */
function wpt_twit_xmlrpc( $id ) {
	set_transient( '_wpt_twit_xmlrpc', $id, 10 );
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $id ) || ! wpt_in_post_type( $id ) ) {
		return $id;
	}
	wpt_tweet( $id, 'xmlrpc' );
	return $id;
}

add_action( 'wpt_schedule_promotion_action', 'wpt_schedule_promotion' );
/**
 * Set an option indicating that a job has been scheduled for promoting WP Tweets PRO.
 */
function wpt_schedule_promotion() {
	if ( ! function_exists( 'wpt_pro_exists' ) && get_option( 'wpt_promotion_scheduled' ) == 1 ) {
		update_option( 'wpt_promotion_scheduled', 2 );
	}
}

/**
 * Dismiss promotion notice.
 */
function wpt_dismiss_promotion() {
	if ( isset( $_GET['dismiss'] ) && $_GET['dismiss'] == 'promotion' ) {
		update_option( 'wpt_promotion_scheduled', 3 );
	}
}

add_action( 'admin_notices', 'wpt_dismiss_promotion', 5 );
add_action( 'admin_notices', 'wpt_promotion_notice', 10 );
/**
 * Display promotion notice to admin users who have not donated or purchased WP Tweets PRO.
 */
function wpt_promotion_notice() {
	if ( current_user_can( 'activate_plugins' ) && get_option( 'wpt_promotion_scheduled' ) == 2 && get_option( 'jd_donations' ) != 1 ) {
		$upgrade = "http://www.wptweetspro.com/wp-tweets-pro/";
		$dismiss = admin_url( 'admin.php?page=wp-tweets-pro&dismiss=promotion' );
		echo "<div class='notice'><p>" . sprintf( __( "I hope you've enjoyed <strong>WP to Twitter</strong>! Take a look at <a href='%s'>upgrading to WP Tweets PRO</a> for advanced Tweeting with WordPress! <a href='%s'>Dismiss</a>", 'wp-to-twitter' ), $upgrade, $dismiss ) . "</p></div>";
	}
}

add_action( 'wp_enqueue_scripts', 'wpt_stylesheet' );
/**
 * Enqueue front-end styles for Twitter Feed widget if enabled.
 */
function wpt_stylesheet() {
	$apply = apply_filters( 'wpt_enqueue_feed_styles', true );
	if ( $apply ) {
		$file = apply_filters( 'wpt_feed_stylesheet', plugins_url( 'css/twitter-feed.css', __FILE__ ) );
		wp_register_style( 'wpt-twitter-feed', $file );
		wp_enqueue_style( 'wpt-twitter-feed' );
	}
}

add_filter( 'wpt_enqueue_feed_styles', 'wpt_permit_feed_styles' );
/**
 * Check whether Twitter Feed styles are enabled.
 *
 * @param boolean $value true if permitted.
 * 
 * @return boolean $value False if settings disable styles.
 */
function wpt_permit_feed_styles( $value ) {
	if ( get_option( 'wpt_permit_feed_styles' ) == 1 ) {
		$value = false;
	}

	return $value;
}

/*
// Add notes about Tweet status to posts admin 
function wpt_column($cols) {
	$cols['wpt'] = __('Tweet Status','wp-to-twitter');
	return $cols;
}

// Echo the ID for the new column
function wpt_value($column_name, $id) {
	if ($column_name == 'wpt') {
		$marked = ucfirst( ( get_post_meta($id,'_jd_tweet_this',true) ) );
		echo $marked;
	}
}

function wpt_return_value($value, $column_name, $id) {
	if ( $column_name == 'wpt' ) {
		$value = $id;
	}
	return $value;
}
*/
// Output CSS for width of new column
add_action( 'admin_head', 'wpt_css' );
function wpt_css() {
	?>
	<style type="text/css">
		th#wpt {
			width: 60px;
		}

		.wpt_twitter .authorized {
			padding: 1px 3px;
			border-radius: 3px;
			background: #070;
			color: #fff;
		}
	</style>
<?php
}
/*
// Actions/Filters for various tables and the css output
add_action('admin_init', 'wpt_add');
function wpt_add() {
	$post_type_settings = get_option('wpt_post_types');
	$post_types = array_keys($post_type_settings);
	
	add_action('admin_head', 'wpt_css');
	if ( !$post_types || in_array( 'post', $post_types ) ) {
		add_filter('manage_posts_columns', 'wpt_column');
		add_action('manage_posts_custom_column', 'wpt_value', 10, 2);
	}
	if ( !$post_types || in_array( 'page', $post_types ) ) {
		add_filter('manage_pages_columns', 'wpt_column');
		add_action('manage_pages_custom_column', 'wpt_value', 10, 2);
	}	
	foreach ( $post_types as $types ) {
		add_action("manage_${types}_columns", 'wpt_column');			
		add_filter("manage_${types}_custom_column", 'wpt_value', 10, 2);
	}
} */