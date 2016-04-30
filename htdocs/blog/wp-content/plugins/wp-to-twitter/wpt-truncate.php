<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function jd_truncate_tweet( $tweet, $post, $post_ID, $retweet = false, $ref = false ) {
	// media file occupies 22 characters, need to account for in shortening.
	$maxlength    = apply_filters( 'wpt_max_length', array( 'with_media' => 116, 'without_media' => 139 ) );
	$length       = ( wpt_post_with_media( $post_ID, $post ) ) ? $maxlength['with_media'] : $maxlength['without_media'];
	$tweet        = apply_filters( 'wpt_tweet_sentence', $tweet, $post_ID );
	$tweet        = trim( wpt_custom_shortcodes( $tweet, $post_ID ) );
	$encoding     = ( get_option( 'blog_charset' ) != 'UTF-8' && get_option( 'blog_charset' ) != '' ) ? get_option( 'blog_charset' ) : 'UTF-8';
	$diff         = 0;

	// Add custom append/prepend fields to Tweet text
	if ( get_option( 'jd_twit_prepend' ) != "" && $tweet != '' ) {
		$tweet = stripslashes( get_option( 'jd_twit_prepend' ) ) . " " . $tweet;
	}
	if ( get_option( 'jd_twit_append' ) != "" && $tweet != '' ) {
		$tweet = $tweet . " " . stripslashes( get_option( 'jd_twit_append' ) );
	}	
	
	// there are no tags in this Tweet. Truncate and return.
	if ( !wpt_has_tags( $tweet ) ) {
		$post_tweet = mb_substr( $tweet, 0, $length, $encoding );
		return apply_filters( 'wpt_custom_truncate', $post_tweet, $tweet, $post_ID, $retweet, 1 );
	}
	
	// create full unconditional post tweet - prior to truncation
	// order matters; arrays have to be ordered the same way.
	$tags      = array_map( 'wpt_make_tag', wpt_tags() );
	$values    = wpt_create_values( $post, $post_ID, $ref );
	
	$post_tweet = str_ireplace( $tags, $values, $tweet );
	// check total length 
	$str_length = mb_strlen( urldecode( wpt_normalize( $post_tweet ) ), $encoding );
	
	/**
	 * Check whether completed replacement is still within allowed length.
	 *
	 * If so, post as is.
	 */
	if ( $str_length < $length + 1 ) {
		if ( mb_strlen( wpt_normalize( $post_tweet ) ) > $length + 1 ) {
			$post_tweet = mb_substr( $post_tweet, 0, $length, $encoding );
		}

		return apply_filters( 'wpt_custom_truncate', $post_tweet, $tweet, $post_ID, $retweet, 2 ); // return early if all is well without replacements.
	} else {
		$has_excerpt_tag = wpt_has( $tweet, '#post#' );
		$has_title_tag   = wpt_has( $tweet, '#title#' );
		$has_short_url   = wpt_has( $tweet, '#url#' );
		$has_long_url    = wpt_has( $tweet, '#longurl#' );
		
		$url_strlen = mb_strlen( urldecode( wpt_normalize( $values['url'] ) ), $encoding );		
		$longurl_strlen = mb_strlen( urldecode( wpt_normalize( $values['longurl'] ) ), $encoding );		
		/**
		 * Tweet is too long, so we'll have to truncate that sucker.
		 */
		$length_array = wpt_length_array( $values, $encoding );

		// Twitter's t.co shortener is mandatory. All URLS are max-character value set by Twitter.			
		$tco   = ( wpt_is_ssl( $values['url'] ) ) ? 23 : 22;
		$order = get_option( 'wpt_truncation_order' );
		if ( is_array( $order ) ) {
			asort( $order );
			$preferred = array();
			foreach ( $order as $k => $v ) {
				if ( $k == 'excerpt' ) {
					$k = 'post';
					$value = $length_array[ 'post' ];
				} else if ( $k == 'blogname' ) {
					$k = 'blog';
					$value = $length_array[ 'blog' ];
				} else {
					$value = $length_array[ $k ];
				}
	
				$preferred[ $k ] = $value;
			}
		} else {
			$preferred = $length_array;
		}
		if ( $has_short_url ) {
			$diff = ( ( $url_strlen - $tco ) > 0 ) ? $url_strlen - $tco : 0;
		} else if ( $has_long_url ) {
			$diff = ( ( $longurl_strlen - $tco ) > 0 ) ? $longurl_strlen - $tco : 0;			
		}
		if ( $str_length > ( $length + 1 + $diff ) ) {
			foreach ( $preferred AS $key => $value ) {
				// don't truncate content of post excerpt or title if those tags not in use
				if ( ! ( $key == 'excerpt' && ! $has_excerpt_tag ) && ! ( $key == 'title' && ! $has_title_tag ) ) {
					$str_length = mb_strlen( urldecode( wpt_normalize( trim( $post_tweet ) ) ), $encoding );
					if ( $str_length > ( $length + 1 + $diff ) ) {
						$trim      = $str_length - ( $length + 1 + $diff );
						$old_value = $values[$key];
						// prevent URL from being modified
						$post_tweet = str_ireplace( array( $values['url'], $values['longurl'] ), array( '#url#', '#longurl#' ), $post_tweet );

						/**
						 * These tag fields should be removed completely, rather than truncated. 
						 */
						if ( wpt_remove_tag( $key ) ) {
							$new_value = '';
						/**
						 * These tag fields should have stray characters removed on word boundaries
						 */
						} else if ( $key == 'tags' ) {
							// remove any stray hash characters due to string truncation
							if ( mb_strlen( $old_value ) - $trim <= 2 ) {
								$new_value = '';
							} else {
								$new_value = $old_value;
								while ( ( mb_strlen( $old_value ) - $trim ) < mb_strlen( $new_value ) ) {
									$new_value = trim( mb_substr( $new_value, 0, mb_strrpos( $new_value, '#', $encoding ) - 1 ) );
								}
							}
						/**
						 * Just flat out truncate everything else cold. 
						 */
						} else {
							// trim letters
							$new_value = mb_substr( $old_value, 0, - ( $trim ), $encoding );
							// trim rest of last word
							$last_space = strrpos( $new_value, ' ' );
							$new_value = mb_substr( $new_value, 0, $last_space, $encoding );							
							/**
							 * If you want to add something like an ellipsis after truncation, use this filter.
							 */
							$new_value  = apply_filters( 'wpt_filter_truncated_value', $new_value, $key, $old_value );
						}
						$post_tweet = str_ireplace( $old_value, $new_value, $post_tweet );
						// put URL back before checking length
						$post_tweet = str_ireplace( array( '#url#', '#longurl#' ), array( $values['url'], $values['longurl'] ), $post_tweet );						
					} else {
						if ( mb_strlen( wpt_normalize( $post_tweet ), $encoding ) > ( $length + 1 + $diff ) ) {
							$post_tweet = mb_substr( $post_tweet, 0, ( $length + $diff ), $encoding );
						}
					}
				}
			}
		}
		
		// this is needed in case a tweet needs to be truncated outright and the truncation values aren't in the above.
		// 1) removes URL 2) checks length of remainder 3) Replaces URL
		if ( mb_strlen( wpt_normalize( $post_tweet ) ) > $length + 1 ) {
			$tweet = false;
			if ( $has_short_url ) {
				$url = $values['url'];
				$tag = '#url#';
			} else if ( $has_long_url ) {
				$url = $values['longurl'];
				$tag = '#longurl#';
			} else {
				$post_tweet = mb_substr( $post_tweet, 0, ( $length + $diff ), $encoding );
				$tweet      = true;
			}
			
			if ( !$tweet ) {
				$temp = str_ireplace( $url, $tag, $post_tweet );
				if ( mb_strlen( wpt_normalize( $temp ) ) > ( ( $length + 1 ) - ( $tco - strlen( $tag ) ) ) && $temp != $post_tweet ) {
					if ( stripos( $temp, '#url#' ) === false && stripos( $temp, '#longurl#' ) === false ) {
						$post_tweet   = trim( mb_substr( $temp, 0, $length, $encoding ) );
					} else {
						$post_tweet   = trim( mb_substr( $temp, 0, ( $length - $tco - 1 ), $encoding ) );					
					}
					// it's possible to trim off the #url# part in this process. If that happens, put it back.
					$sub_sentence = ( !wpt_has( $post_tweet, $tag ) && ( $has_short_url || $has_long_url ) ) ? $post_tweet . ' ' . $tag : $post_tweet;
					$post_tweet   = str_ireplace( $tag, $url, $sub_sentence );
				}
			}
		}
	}

	return apply_filters( 'wpt_custom_truncate', $post_tweet, $tweet, $post_ID, $retweet, 3 );
}

function wpt_has( $string, $tag ) {
	if ( strpos( $string, $tag ) === false ) {
		return false;
	}
	
	return true;
}

function wpt_has_tags( $string ) {
	$tags = wpt_tags();
	foreach ( $tags as $tag ) {
		if ( wpt_has( $string, "#$tag#" ) ) {
			return true;
		}
	}

	return false;
}

function wpt_remove_tag( $key ) {
	switch( $key ) {
		case 'account':
		case 'author':
		case 'category':
		case 'date':
		case 'modified':
		case 'reference':
		case '@': $return = true; break;
		default: $return = false;
	}
	
	return $return;
	//$key == 'account' || $key == 'author' || $key == 'category' || $key == 'date' || $key == 'modified' || $key == 'reference' || $key == '@'
}

function wpt_tags() {
	return apply_filters( 'wpt_tags', array( 'url', 'title', 'blog', 'post', 'category', 'date', 'author', 'displayname', 'tags', 'modified', 'reference', 'account', '@', 'cat_desc', 'longurl' ) );
}

function wpt_make_tag( $value ) {
	return '#' . $value . '#';
}

function wpt_create_values( $post, $post_ID, $ref ) {
	$shrink       = ( $post['shortUrl'] != '' ) ? $post['shortUrl'] : apply_filters( 'wptt_shorten_link', $post['postLink'], $post['postTitle'], $post_ID, false );
	// generate template variable values
	$auth         = $post['authId'];	
	$title        = trim( apply_filters( 'wpt_status', $post['postTitle'], $post_ID, 'title' ) );
	$blogname     = trim( $post['blogTitle'] );
	$excerpt      = trim( apply_filters( 'wpt_status', $post['postExcerpt'], $post_ID, 'post' ) );
	$thisposturl  = trim( $shrink );
	$category     = trim( $post['category'] );
	$cat_desc     = trim( $post['cat_desc'] );
	$user_account = get_user_meta( $auth, 'wtt_twitter_username', true );
	$tags         = wpt_generate_hash_tags( $post_ID );
	$account      = get_option( 'wtt_twitter_username' );
	$date         = trim( $post['postDate'] );
	$modified     = trim( $post['postModified'] );
	if ( get_option( 'jd_individual_twitter_users' ) == 1 ) {
		if ( $user_account == '' ) {
			if ( get_user_meta( $auth, 'wp-to-twitter-enable-user', true ) == 'mainAtTwitter' ) {
				$account = $user_account = stripcslashes( get_user_meta( $auth, 'wp-to-twitter-user-username', true ) );
			} else if ( get_user_meta( $auth, 'wp-to-twitter-enable-user', true ) == 'mainAtTwitterPlus' ) {
				$account = $user_account = stripcslashes( get_user_meta( $auth, 'wp-to-twitter-user-username', true ) . ' @' . get_option( 'wtt_twitter_username' ) );
			}
		} else {
			$account = "$user_account";
		}
	}
	$display_name = get_the_author_meta( 'display_name', $auth );
	$author       = ( $user_account != '' ) ? "@$user_account" : $display_name; 	// value of #author#
	$account      = ( $account != '' ) ? "@$account" : ''; // value of #account# 
	$uaccount     = ( $user_account != '' ) ? "@$user_account" : "$account"; 	// value of #@# 
	// clean up data if extra @ included in user data //
	$account      = str_ireplace( '@@', '@', $account );
	$uaccount     = str_ireplace( '@@', '@', $uaccount );
	$author       = str_ireplace( '@@', '@', $author );

	if ( get_user_meta( $auth, 'wpt-remove', true ) == 'on' ) {
		$account = '';
	}
		
	if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
		$reference = ( $ref ) ? $account : '@' . get_option( 'wtt_twitter_username' );
	} else {
		$reference = '';
	}	
	
	return array( 
		'url' => $thisposturl,
		'title' => $title,
		'blog' => $blogname,
		'post' => $excerpt,
		'category' => $category,
		'date' => $date,
		'author' => $author,
		'displayname' => $display_name,
		'tags' => $tags,
		'modified' => $modified,
		'reference' => $reference,	
		'account' => $account,
		'@' => $uaccount,
		'cat_desc' => $cat_desc,
		'longurl' => $post['postLink']
	);
}

function wpt_length_array( $values, $encoding ) {
	foreach ( $values as $key => $value ) {
		$array[$key] = mb_strlen( wpt_normalize( $value ), $encoding );
	}

	return $array;
}