<?php
// This file contains secondary functions supporting WP to Twitter

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// FUNCTION to see if checkboxes should be checked
function jd_checkCheckbox( $field, $sub1 = false, $sub2 = '' ) {
	if ( $sub1 ) {
		$setting = get_option( $field );
		if ( isset( $setting[ $sub1 ] ) ) {
			$value = ( $sub2 != '' ) ? $setting[ $sub1 ][ $sub2 ] : $setting[ $sub1 ];
		} else {
			$value = 0;
		}
		if ( $value == 1 ) {
			return 'checked="checked"';
		}
	}
	if ( get_option( $field ) == '1' ) {
		return 'checked="checked"';
	}
	return '';
}

function jd_checkSelect( $field, $value, $type = 'select' ) {
	if ( get_option( $field ) == $value ) {
		return ( $type == 'select' ) ? 'selected="selected"' : 'checked="checked"';
	}
	return '';
}

function wpt_set_log( $data, $id, $message ) {
	if ( $id == 'test' ) {
		update_option( $data, $message );
	} else {
		update_post_meta( $id, '_' . $data, $message );
	}
	update_option( $data . '_last', array( $id, $message ) );
}

function wpt_log( $data, $id ) {
	if ( $id == 'test' ) {
		$log = get_option( $data );
	} else if ( $id == 'last' ) {
		$log = get_option( $data . '_last' );
	} else {
		$log = get_post_meta( $id, '_' . $data, true );
	}

	return $log;
}

function wpt_check_functions() {
	$message = "<div class='update'><ul>";
	// grab or set necessary variables
	$testurl   = get_bloginfo( 'url' );
	$testpost  = false;
	$title     = urlencode( 'Your blog home' );
	$shrink    = apply_filters( 'wptt_shorten_link', $testurl, $title, false, true );
	if ( $shrink == false ) {
		$error = htmlentities( get_option( 'wpt_shortener_status' ) );
		$message .= __( "<li class=\"error\"><strong>WP to Twitter was unable to contact your selected URL shortening service.</strong></li>", 'wp-to-twitter' );
		if ( $error != '' ) {
			$message .= "<li><code>$error</code></li>";
		} else {
			$message .= "<li><code>" . __( 'No error message was returned.', 'wp-to-twitter' ) . "</code></li>";
		}
	} else {
		$message .= __( "<li><strong>WP to Twitter successfully contacted your URL shortening service.</strong>  This link should point to your site's homepage:", 'wp-to-twitter' );
		$message .= " <a href='$shrink'>$shrink</a></li>";
	}
	//check twitter credentials
	if ( wtt_oauth_test() ) {
		$rand     = rand( 1000000, 9999999 );
		$testpost = jd_doTwitterAPIPost( "This is a test of WP to Twitter. $shrink ($rand)" );
		if ( $testpost ) {
			$message .= __( "<li><strong>WP to Twitter successfully submitted a status update to Twitter.</strong></li>", 'wp-to-twitter' );
		} else {
			$error = wpt_log( 'wpt_status_message', 'test' );
			$message .= __( "<li class=\"error\"><strong>WP to Twitter failed to submit an update to Twitter.</strong></li>", 'wp-to-twitter' );
			$message .= "<li class=\"error\">$error</li>";
		}
	} else {
		$message .= "<strong>" . _e( 'You have not connected WordPress to Twitter.', 'wp-to-twitter' ) . "</strong> ";
	}
	// If everything's OK, there's  no reason to do this again.
	if ( $testpost == false && $shrink == false ) {
		$message .= __( "<li class=\"error\"><strong>Your server does not appear to support the required methods for WP to Twitter to function.</strong> You can try it anyway - these tests aren't perfect.</li>", 'wp-to-twitter' );
	} else {
	}
	if ( $testpost && $shrink ) {
		$message .= __( "<li><strong>Your server should run WP to Twitter successfully.</strong></li>", 'wp-to-twitter' );
	}
	$message .= "</ul>
	</div>";

	return $message;
}

function wpt_settings_tabs() {
	$output = '';
	$default = ( get_option( 'wtt_twitter_username' ) == '' ) ? 'connection' : 'basic';
	$current = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : $default;
	$pro_text = ( function_exists( 'wpt_pro_exists' ) ) ? __( 'Pro Settings', 'wp-to-twitter' ) : __( 'Get WP Tweets PRO', 'wp-to-twitter' );
	$pages = array( 
		'connection'=> __( 'Twitter Connection', 'wp-to-twitter' ), 
		'basic'=> __( 'Basic Settings', 'wp-to-twitter' ),
		'shortener'=> __( 'URL Shortener', 'wp-to-twitter' ),
		'advanced' => __( 'Advanced Settings', 'wp-to-twitter' ),
		'support' => __( 'Get Help', 'wp-to-twitter' ),
		'pro' => $pro_text
	);
	$pages = apply_filters( 'wpt_settings_tabs_pages', $pages, $current );
	$admin_url = admin_url( 'admin.php?page=wp-tweets-pro' );

	foreach ( $pages as $key => $value ) {
		$selected = ( $key == $current ) ? " nav-tab-active" : '';
		$url = esc_url( add_query_arg( 'tab', $key, $admin_url ) );
		if ( $key == 'pro' ) {
			$output .= "<a class='wpt-pro-tab nav-tab$selected' href='$url'>$value</a>";
		} else {
			$output .= "<a class='nav-tab$selected' href='$url'>$value</a>";
		}
	}
	echo $output;
}

function wpt_show_last_tweet() {
	if ( apply_filters( 'wpt_show_last_tweet', true ) ) {
		$log = wpt_log( 'wpt_status_message', 'last' );
		if ( ! empty( $log ) && is_array( $log ) ) {
			$post_ID = $log[0];
			$post    = get_post( $post_ID );
			if ( is_object( $post ) ) {
				$title = "<a href='" . get_edit_post_link( $post_ID ) . "'>$post->post_title</a>";
			} else {
				$title = __( 'No post associated with this Tweet', 'wp-to-twitter' );
			}
			$notice = $log[1];
			echo "<div class='updated'><p><strong>" . __( 'Last Tweet', 'wp-to-twitter' ) . "</strong>: $title &raquo; $notice</p></div>";
		}
	}
}


function wpt_handle_errors() {
	if ( isset( $_POST['submit-type'] ) && $_POST['submit-type'] == 'clear-error' ) {
		delete_option( 'wp_url_failure' );
	}
	if ( get_option( 'wp_url_failure' ) == '1' ) {
		$admin_url = admin_url( 'admin.php?page=wp-tweets-pro' );
		$nonce = wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false );
		$error = '<div class="error">' . 
			__( "<p>The query to the URL shortener API failed, and your URL was not shrunk. The full post URL was attached to your Tweet. Check with your URL shortening provider to see if there are any known issues.</p>", 'wp-to-twitter' ) .
			'<form method="post" action="' . $admin_url . '">
				<div>
					<input type="hidden" name="submit-type" value="clear-error"/>
					'. $nonce . '
				</div>
				<p>
					<input type="submit" name="submit" value="' . __( "Clear 'WP to Twitter' Error Messages", 'wp-to-twitter' ) . '" class="button-primary" />
				</p>
			</form>
		</div>';
		echo $error;
	}
}
// verify user capabilities
function wpt_check_caps( $role, $cap ) {
	$role = get_role( $role );
	if ( $role->has_cap( $cap ) ) {
		return " checked='checked'";
	}
	return '';
}

// output checkbox for user capabilities
function wpt_cap_checkbox( $role, $cap, $name ) {
	return "<li><input type='checkbox' id='wpt_caps_{$role}_$cap' name='wpt_caps[$role][$cap]' value='on'" . wpt_check_caps( $role, $cap ) . " /> <label for='wpt_caps_{$role}_$cap'>$name</label></li>";
}

function wpt_mail( $subject, $body, $override=false ) {
	if ( ( WPT_DEBUG && function_exists( 'wpt_pro_exists' ) ) || $override == true ) {
		$use_email = true;
		if ( $use_email ) {
			wp_mail( WPT_DEBUG_ADDRESS, $subject, $body, WPT_FROM );
		} else {
			$debug                          = get_option( 'wpt_debug' );
			$debug[ date( 'Y-m-d H:i:s' ) ] = array( $subject, $body );
			update_option( 'wpt_debug', $debug );
		}
	}
}

function wpt_show_debug() {
	// Nothing triggers this. If you want some debugging information, just add the parameter to the URL.
	if ( isset( $_GET['debug'] ) && $_GET['debug'] == 'true' ) {
		$debug = get_option( 'wpt_debug' );
		echo "<pre>";
		print_r( $debug );
		echo "</pre>";
	}
	if ( isset( $_GET['debug'] ) && $_GET['debug'] == 'delete' ) {
		delete_option( 'wpt_debug' );
	}	
}

function wpt_remote_json( $url, $array = true, $method = 'GET' ) {
	$input = wpt_fetch_url( $url, $method );
	$obj   = json_decode( $input, $array );
	wpt_mail( 'Remote JSON return value', print_r( $obj, 1 ) );
	if ( function_exists( 'json_last_error' ) ) { // > PHP 5.3
		try {
			if ( is_null( $obj ) ) {
				switch ( json_last_error() ) {
					case JSON_ERROR_DEPTH :
						$msg = ' - Maximum stack depth exceeded';
						break;
					case JSON_ERROR_STATE_MISMATCH :
						$msg = ' - Underflow or the modes mismatch';
						break;
					case JSON_ERROR_CTRL_CHAR :
						$msg = ' - Unexpected control character found';
						break;
					case JSON_ERROR_SYNTAX :
						$msg = ' - Syntax error, malformed JSON';
						break;
					case JSON_ERROR_UTF8 :
						$msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
						break;
					default :
						$msg = ' - Unknown error';
						break;
				}
				throw new Exception( $msg );
			}
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	return $obj;
}

function wpt_is_valid_url( $url ) {
	if ( is_string( $url ) ) {
		$url = urldecode( $url );

		return preg_match( '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url );
	} else {
		return false;
	}
}

// Fetch a remote page. Input url, return content
function wpt_fetch_url( $url, $method = 'GET', $body = '', $headers = '', $return = 'body' ) {
	$request = new WP_Http;
	$result  = $request->request( $url, array( 'method'     => $method,
	                                           'body'       => $body,
	                                           'headers'    => $headers,
	                                           'sslverify'  => false,
	                                           'user-agent' => 'WP to Twitter/http://www.joedolson.com/wp-to-twitter/'
		) );
	// Success?
	if ( ! is_wp_error( $result ) && isset( $result['body'] ) ) {
		if ( $result['response']['code'] == 200 ) {
			if ( $return == 'body' ) {
				return $result['body'];
			} else {
				return $result;
			}
		} else {
			return $result['response']['code'];
		}
		// Failure (server problem...)
	} else {
		return false;
	}
}

if ( ! function_exists( 'mb_strlen' ) ) {
	/**
	 * Fallback implementation of mb_strlen, hardcoded to UTF-8.
	 *
	 * @param string $str
	 *
	 * @return int
	 */
	function mb_strlen( $str ) {
		$counts = count_chars( $str );
		$total  = 0;

		// Count ASCII bytes
		for ( $i = 0; $i < 0x80; $i ++ ) {
			$total += $counts[ $i ];
		}

		// Count multibyte sequence heads
		for ( $i = 0xc0; $i < 0xff; $i ++ ) {
			$total += $counts[ $i ];
		}

		return $total;
	}
}

if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $str, $start, $count = 'end' ) {
		if ( $start != 0 ) {
			$split = mb_substr_split_unicode( $str, intval( $start ) );
			$str   = substr( $str, $split );
		}

		if ( $count !== 'end' ) {
			$split = mb_substr_split_unicode( $str, intval( $count ) );
			$str   = substr( $str, 0, $split );
		}

		return $str;
	}
}

if ( ! function_exists( 'mb_substr_split_unicode' ) ) {
	function mb_substr_split_unicode( $str, $splitPos ) {
		if ( $splitPos == 0 ) {
			return 0;
        }
        $byteLen = strlen( $str );

        if ( $splitPos > 0 ) {
            if ( $splitPos > 256 ) {
                // Optimize large string offsets by skipping ahead N bytes.
                // This will cut out most of our slow time on Latin-based text,
                // and 1/2 to 1/3 on East European and Asian scripts.
                $bytePos = $splitPos;
                while ( $bytePos < $byteLen && $str[$bytePos] >= "\x80" && $str[$bytePos] < "\xc0" ) {
                    ++$bytePos;
                }
                $charPos = mb_strlen( substr( $str, 0, $bytePos ) );
            } else {
                $charPos = 0;
                $bytePos = 0;
            }

            while ( $charPos++ < $splitPos ) {
                ++$bytePos;
                // Move past any tail bytes
                while ( $bytePos < $byteLen && $str[$bytePos] >= "\x80" && $str[$bytePos] < "\xc0" ) {
                    ++$bytePos;
                }
            }
        } else {
            $splitPosX = $splitPos + 1;
            $charPos = 0; // relative to end of string; we don't care about the actual char position here
            $bytePos = $byteLen;
            while ( $bytePos > 0 && $charPos-- >= $splitPosX ) {
                --$bytePos;
                // Move past any tail bytes
                while ( $bytePos > 0 && $str[$bytePos] >= "\x80" && $str[$bytePos] < "\xc0" ) {
                    --$bytePos;
                }
            }
        }

        return $bytePos;
    }	
}

// filter_var substitution for PHP <5.2
if ( ! function_exists( 'filter_var' ) ) {
	function filter_var( $url ) {
		// this does not emulate filter_var; merely the usage of filter_var in WP to Twitter.
		return ( stripos( $url, 'https:' ) !== false || stripos( $url, 'http:' ) !== false ) ? true : false;
	}
}

if ( ! function_exists( 'mb_strrpos' ) ) {
	/**
	 * Fallback implementation of mb_strrpos, hardcoded to UTF-8.
	 *
	 * @param $haystack String
	 * @param $needle String
	 * @param $offset integer: optional start position
	 *
	 * @return int
	 */
	function mb_strrpos( $haystack, $needle, $offset = 0 ) {
		$needle = preg_quote( $needle, '/' );

		$ar = array();
		preg_match_all( '/' . $needle . '/u', $haystack, $ar, PREG_OFFSET_CAPTURE, $offset );

		if ( isset( $ar[0] ) && count( $ar[0] ) > 0 &&
		     isset( $ar[0][ count( $ar[0] ) - 1 ][1] )
		) {
			return $ar[0][ count( $ar[0] ) - 1 ][1];
		} else {
			return false;
		}
	}
}

// str_ireplace substitution for PHP4
if ( ! function_exists( 'str_ireplace' ) ) {
	function str_ireplace( $needle, $str, $haystack ) {
		$needle = preg_quote( $needle, '/' );

		return preg_replace( "/$needle/i", $str, $haystack );
	}
}
// str_split substitution for PHP4
if ( ! function_exists( 'str_split' ) ) {
	function str_split( $string, $string_length = 1 ) {
		if ( strlen( $string ) > $string_length || ! $string_length ) {
			do {
				$parts[] = substr( $string, 0, $string_length );
				$string  = substr( $string, $string_length );
			} while ( $string !== false );
		} else {
			$parts = array( $string );
		}

		return $parts;
	}
}
// mb_substr_replace substition for PHP4
if ( ! function_exists( 'mb_substr_replace' ) ) {
	function mb_substr_replace( $string, $replacement, $start, $length = null, $encoding = null ) {
		if ( extension_loaded( 'mbstring' ) === true ) {
			$string_length = ( is_null( $encoding ) === true ) ? mb_strlen( $string ) : mb_strlen( $string, $encoding );
			if ( $start < 0 ) {
				$start = max( 0, $string_length + $start );
			} else if ( $start > $string_length ) {
				$start = $string_length;
			}
			if ( $length < 0 ) {
				$length = max( 0, $string_length - $start + $length );
			} else if ( ( is_null( $length ) === true ) || ( $length > $string_length ) ) {
				$length = $string_length;
			}
			if ( ( $start + $length ) > $string_length ) {
				$length = $string_length - $start;
			}
			if ( is_null( $encoding ) === true ) {
				return mb_substr( $string, 0, $start ) . $replacement . mb_substr( $string, $start + $length, $string_length - $start - $length );
			}

			return mb_substr( $string, 0, $start, $encoding ) . $replacement . mb_substr( $string, $start + $length, $string_length - $start - $length, $encoding );
		}

		return ( is_null( $length ) === true ) ? substr_replace( $string, $replacement, $start ) : substr_replace( $string, $replacement, $start, $length );
	}
}

/**
 * This function is obsolete; only exists for people using out of date versions of WP Tweets PRO.
 */
function wtt_option_selected( $field, $value, $type = 'checkbox' ) {
	switch ( $type ) {
		case 'radio':
		case 'checkbox':
			$result = ' checked="checked"';
			break;
		case 'option':
			$result = ' selected="selected"';
			break;
		default: $result = ' selected="selected"';
	}
	if ( $field == $value ) {
		$output = $result;
	} else {
		$output = '';
	}

	return $output;
}

/**
 * Compares two dates to identify which is earlier. Used to differentiate between post edits and original publication.
 * 
 * @param string $early
 * @param string $late
 * 
 * @return integer 1|0
 */ 
function wpt_date_compare( $early, $late ) {
	$modifier  = apply_filters( 'wpt_edit_sensitivity', 0 ); // alter time in seconds to modified date.
	$firstdate = strtotime( $early );
	$lastdate  = strtotime( $late ) + $modifier;
	if ( $firstdate <= $lastdate ) { // if post_modified is before or equal to post_date
		return 1;
	} else {
		return 0;
	}
}

/**
 * Gets the first attachment for the supplied post.
 *
 * @param integer $post_ID The post ID
 *
 * @return mixed boolean|integer Attachment ID.
 */
function wpt_post_attachment( $post_ID ) {
	$use_featured_image = apply_filters( 'wpt_use_featured_image', true, $post_ID );
	if ( has_post_thumbnail( $post_ID ) && $use_featured_image ) {
		$attachment = get_post_thumbnail_id( $post_ID );

		$return = $attachment;
	} else {
		$args        = array(
			'post_type'      => 'attachment',
			'numberposts'    => 1,
			'post_status'    => 'published',
			'post_parent'    => $post_ID,
			'post_mime_type' => 'image',
			'order'          => 'ASC'
		);
		$attachments = get_posts( $args );
		if ( $attachments ) {
			$return = $attachments[0]->ID; //Return the first attachment.
		} else {
			$return = false;
		}
	}
	return apply_filters( 'wpt_post_attachment', $return, $post_ID );
}

function wpt_get_support_form() {
	global $current_user, $wpt_version;
	$current_user = wp_get_current_user();
	$request = '';
	$response_email = '';
	// send fields for WP to Twitter
	$license = ( get_option( 'wpt_license_key' ) != '' ) ? get_option( 'wpt_license_key' ) : 'none';
	if ( $license != 'none' ) {
		$valid = ( ( get_option( 'wpt_license_valid' ) == 'true' ) || ( get_option( 'wpt_license_valid' ) == 'active' ) ) ? ' (active)' : ' (inactive)';
	} else {
		$valid = '';
	}
	$license = "License Key: " . $license . $valid;

	$version              = $wpt_version;
	$wtt_twitter_username = get_option( 'wtt_twitter_username' );
	// send fields for all plugins
	$wp_version = get_bloginfo( 'version' );
	$home_url   = home_url();
	$wp_url     = site_url();
	$language   = get_bloginfo( 'language' );
	$charset    = get_bloginfo( 'charset' );
	// server
	$php_version = phpversion();

	// theme data
	$theme         = wp_get_theme();
	$theme_name    = $theme->Name;
	$theme_uri     = $theme->ThemeURI;
	$theme_parent  = $theme->Template;
	$theme_version = $theme->Version;

	$admin_email = get_option( 'admin_email' );
	// plugin data
	$plugins        = get_plugins();
	$plugins_string = '';
	foreach ( array_keys( $plugins ) as $key ) {
		if ( is_plugin_active( $key ) ) {
			$plugin         =& $plugins[ $key ];
			$plugin_name    = $plugin['Name'];
			$plugin_uri     = $plugin['PluginURI'];
			$plugin_version = $plugin['Version'];
			$plugins_string .= "$plugin_name: $plugin_version; $plugin_uri\n";
		}
	}

	$data              = "
================ Installation Data ====================
==WP to Twitter==
Version: $version
Twitter username: http://twitter.com/$wtt_twitter_username
$license

==WordPress:==
Version: $wp_version
URL: $home_url
Install: $wp_url
Language: $language
Charset: $charset
User Email: $current_user->user_email
Admin Email: $admin_email

==Extra info:==
PHP Version: $php_version
Server Software: $_SERVER[SERVER_SOFTWARE]
User Agent: $_SERVER[HTTP_USER_AGENT]

==Theme:==
Name: $theme_name
URI: $theme_uri
Parent: $theme_parent
Version: $theme_version

==Active Plugins:==
$plugins_string
";
	if ( isset( $_POST['wpt_support'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'wp-to-twitter-nonce' ) ) {
			die( "Security check failed" );
		}
		$request      = ( ! empty( $_POST['support_request'] ) ) ? stripslashes( $_POST['support_request'] ) : false;
		$has_donated  = ( isset( $_POST['has_donated'] ) ) ? "Donor" : "No donation";
		$has_read_faq = ( isset( $_POST['has_read_faq'] ) ) ? "Read FAQ" : false;
		if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
			$pro = " PRO";
		} else {
			$pro = '';
		}
		$subject = "WP to Twitter$pro support request. $has_donated";
		$message = $request . "\n\n" . $data;
		// Get the site domain and get rid of www. from pluggable.php
		$sitename = strtolower( $_SERVER['SERVER_NAME'] );
		if ( substr( $sitename, 0, 4 ) == 'www.' ) {
			$sitename = substr( $sitename, 4 );
		}
		$response_email = ( isset( $_POST['response_email'] ) ) ? $_POST['response_email'] : false;
		$from_email = 'wordpress@' . $sitename;
		$from       = "From: \"$current_user->display_name\" <$response_email>\r\nReply-to: \"$current_user->display_name\" <$response_email>\r\n";

		if ( ! $has_read_faq ) {
			echo "<div class='notice error'><p>" . __( 'Please read the FAQ and other Help documents before making a support request.', 'wp-to-twitter' ) . "</p></div>";
		} else if ( ! $response_email ) {
			echo "<div class='notice error'><p>" . __( 'Please supply a valid email where you can receive support responses.', 'wp-to-twitter' ) . "</p></div>";			
		} else if ( ! $request ) {
			echo "<div class='notice error'><p>" . __( 'Please describe your problem. I\'m not psychic.', 'wp-to-twitter' ) . "</p></div>";
		} else {
			$sent = wp_mail( "plugins@joedolson.com", $subject, $message, $from );
			if ( $sent ) {
				if ( $has_donated == 'Donor' ) {
					echo "<div class='notice updated'><p>" . sprintf( __( 'Thank you for supporting WP to Twitter! I\'ll get back to you as soon as I can. Please make sure you can receive email at <code>%s</code>.', 'wp-to-twitter' ), $response_email ) . "</p></div>";
				} else {
					echo "<div class='notice updated'><p>" . sprintf( __( "Thanks for using WP to Twitter. Please ensure that you can receive email at <code>%s</code>.", 'wp-to-twitter' ), $response_email ) . "</p></div>";
				}
			} else {
				echo "<div class='notice error'><p>" . __( "Sorry! I couldn't send that message. Here's the text of your request:", 'my-calendar' ) . "</p><p>" . sprintf( __( '<a href="%s">Contact me here</a>, instead.', 'wp-to-twitter' ), 'https://www.joedolson.com/contact/' ) . "</p><pre>$request</pre></div>";
			}
		}
	}
	if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) {
		$checked = "checked='checked'";
	} else {
		$checked = '';
	}
	$admin_url = admin_url( 'admin.php?page=wp-tweets-pro' );
	$admin_url = add_query_arg( 'tab', 'support', $admin_url );
	
	echo "
	<form method='post' action='$admin_url'>
		<div><input type='hidden' name='_wpnonce' value='" . wp_create_nonce( 'wp-to-twitter-nonce' ) . "' /></div>
		<div>
		<p>" .
	     __( "If you're having trouble with WP to Twitter, please try to answer these questions in your message:", 'wp-to-twitter' )
	     . "</p>
		<ul>
			<li>" . __( 'What were you doing when the problem occurred?', 'wp-to-twitter' ) . "</li>
			<li>" . __( 'What did you expect to happen?', 'wp-to-twitter' ) . "</li>
			<li>" . __( 'What happened instead?', 'wp-to-twitter' ) . "</li>
		</ul>
		<p>
		<label for='response_email'>" . __( 'Your Email', 'wp-to-twitter' ) . "</label><br />
		<input type='email' name='response_email' id='response_email' value='$response_email' class='widefat' required='required' aria-required='true' />
		</p>
		<p>
		<input type='checkbox' name='has_read_faq' id='has_read_faq' value='on' required='required' aria-required='true' /> <label for='has_read_faq'>" . sprintf( __( 'I have read <a href="%1$s">the FAQ for this plug-in</a> <span>(required)</span>', 'wp-to-twitter' ), 'http://www.joedolson.com/wp-to-twitter/support-2/' ) . "
        </p>
        <p>
        <input type='checkbox' name='has_donated' id='has_donated' value='on' $checked /> <label for='has_donated'>" . __( 'I made a donation or purchase to help support this plug-in', 'wp-to-twitter' ) . "</label>
        </p>
        <p>
        <label for='support_request'>" . __( 'Support Request:', 'wp-to-twitter' ) . "</label><br /><textarea class='support-request' name='support_request' id='support_request' cols='80' rows='10' class='widefat'>" . stripslashes( esc_attr( $request ) ) . "</textarea>
		</p>
		<p>
		<input type='submit' value='" . __( 'Send Support Request', 'wp-to-twitter' ) . "' name='wpt_support' class='button-primary' />
		</p>
		<p>" .
	     __( 'The following additional information will be sent with your support request:', 'wp-to-twitter' )
	     . "</p>
		<div class='mc_support'>
		" . wpautop( $data ) . "
		</div>
		</div>
	</form>";
}

function wpt_is_writable( $file ) {
	if ( function_exists( 'wp_is_writable' ) ) {
		$is_writable = wp_is_writable( $file );
	} else {
		$is_writable = is_writeable( $file );
	}

	return $is_writable;
}

/**
 * Normalizer is a PHP fallback implementation of the Normalizer class provided by the intl extension.
 *
 * It has been validated with Unicode 6.1 Normalization Conformance Test.
 * See http://www.unicode.org/reports/tr15/ for detailed info about Unicode normalizations.
 */
class WPT_Normalizer
{
    const

    NONE = 1,
    FORM_D  = 2, NFD  = 2,
    FORM_KD = 3, NFKD = 3,
    FORM_C  = 4, NFC  = 4,
    FORM_KC = 5, NFKC = 5;


    protected static

    $C, $D, $KD, $cC,
    $ulen_mask = array("\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4),
    $ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";


    static function isNormalized($s, $form = self::NFC)
    {
        if (strspn($s, self::$ASCII) === strlen($s)) return true;
        if (self::NFC === $form && preg_match('//u', $s) && !preg_match('/[^\x00-\x{2FF}]/u', $s)) return true;
        return false; // Pretend false as quick checks implementented in PHP won't be so quick
    }

    static function normalize($s, $form = self::NFC)
    {
        if (!preg_match('//u', $s)) return false;

        switch ($form)
        {
        case self::NONE: return $s;
        case self::NFC:  $C = true;  $K = false; break;
        case self::NFD:  $C = false; $K = false; break;
        case self::NFKC: $C = true;  $K = true;  break;
        case self::NFKD: $C = false; $K = true;  break;
        default: return false;
        }

        if (!strlen($s)) return '';

        if ($K && empty(self::$KD)) self::$KD = self::getData('compatibilityDecomposition');

        if (empty(self::$D))
        {
            self::$D = self::getData('canonicalDecomposition');
            self::$cC = self::getData('combiningClass');
        }

        if ($C)
        {
            if (empty(self::$C)) self::$C = self::getData('canonicalComposition');
            return self::recompose(self::decompose($s, $K));
        }
        else return self::decompose($s, $K);
    }

    protected static function recompose($s)
    {
        $ASCII = self::$ASCII;
        $compMap = self::$C;
        $combClass = self::$cC;
        $ulen_mask = self::$ulen_mask;

        $result = $tail = '';

        $i = $s[0] < "\x80" ? 1 : $ulen_mask[$s[0] & "\xF0"];
        $len = strlen($s);

        $last_uchr = substr($s, 0, $i);
        $last_ucls = isset($combClass[$last_uchr]) ? 256 : 0;

        while ($i < $len)
        {
            if ($s[$i] < "\x80")
            {
                // ASCII chars

                if ($tail)
                {
                    $last_uchr .= $tail;
                    $tail = '';
                }

                if ($j = strspn($s, $ASCII, $i+1))
                {
                    $last_uchr .= substr($s, $i, $j);
                    $i += $j;
                }

                $result .= $last_uchr;
                $last_uchr = $s[$i];
                ++$i;
            }
            else
            {
                $ulen = $ulen_mask[$s[$i] & "\xF0"];
                $uchr = substr($s, $i, $ulen);

                if ($last_uchr < "\xE1\x84\x80" || "\xE1\x84\x92" < $last_uchr
                    ||   $uchr < "\xE1\x85\xA1" || "\xE1\x85\xB5" < $uchr
                    || $last_ucls)
                {
                    // Table lookup and combining chars composition

                    $ucls = isset($combClass[$uchr]) ? $combClass[$uchr] : 0;

                    if (isset($compMap[$last_uchr . $uchr]) && (!$last_ucls || $last_ucls < $ucls))
                    {
                        $last_uchr = $compMap[$last_uchr . $uchr];
                    }
                    else if ($last_ucls = $ucls) $tail .= $uchr;
                    else
                    {
                        if ($tail)
                        {
                            $last_uchr .= $tail;
                            $tail = '';
                        }

                        $result .= $last_uchr;
                        $last_uchr = $uchr;
                    }
                }
                else
                {
                    // Hangul chars

                    $L = ord($last_uchr[2]) - 0x80;
                    $V = ord($uchr[2]) - 0xA1;
                    $T = 0;

                    $uchr = substr($s, $i + $ulen, 3);

                    if ("\xE1\x86\xA7" <= $uchr && $uchr <= "\xE1\x87\x82")
                    {
                        $T = ord($uchr[2]) - 0xA7;
                        0 > $T && $T += 0x40;
                        $ulen += 3;
                    }

                    $L = 0xAC00 + ($L * 21 + $V) * 28 + $T;
                    $last_uchr = chr(0xE0 | $L>>12) . chr(0x80 | $L>>6 & 0x3F) . chr(0x80 | $L & 0x3F);
                }

                $i += $ulen;
            }
        }

        return $result . $last_uchr . $tail;
    }

    protected static function decompose($s, $c)
    {
        $result = '';

        $ASCII = self::$ASCII;
        $decompMap = self::$D;
        $combClass = self::$cC;
        $ulen_mask = self::$ulen_mask;
        if ($c) $compatMap = self::$KD;

        $c = array();
        $i = 0;
        $len = strlen($s);

        while ($i < $len) {
            if ($s[$i] < "\x80") {
                // ASCII chars

                if ($c) {
                    ksort($c);
                    $result .= implode('', $c);
                    $c = array();
                }

                $j = 1 + strspn($s, $ASCII, $i+1);
                $result .= substr($s, $i, $j);
                $i += $j;
            } else {
                $ulen = $ulen_mask[$s[$i] & "\xF0"];
                $uchr = substr($s, $i, $ulen);
                $i += $ulen;

                if (isset($combClass[$uchr])) {
                    // Combining chars, for sorting

                    isset($c[$combClass[$uchr]]) || $c[$combClass[$uchr]] = '';
                    $c[$combClass[$uchr]] .= isset($compatMap[$uchr]) ? $compatMap[$uchr] : (isset($decompMap[$uchr]) ? $decompMap[$uchr] : $uchr);
                } else {
                    if ($c) {
                        ksort($c);
                        $result .= implode('', $c);
                        $c = array();
                    }

                    if ($uchr < "\xEA\xB0\x80" || "\xED\x9E\xA3" < $uchr) {
                        // Table lookup

                        $j = isset($compatMap[$uchr]) ? $compatMap[$uchr] : (isset($decompMap[$uchr]) ? $decompMap[$uchr] : $uchr);

                        if ($uchr != $j) {
                            $uchr = $j;

                            $j = strlen($uchr);
                            $ulen = $uchr[0] < "\x80" ? 1 : $ulen_mask[$uchr[0] & "\xF0"];

                            if ($ulen != $j)
                            {
                                // Put trailing chars in $s

                                $j -= $ulen;
                                $i -= $j;

                                if (0 > $i)
                                {
                                    $s = str_repeat(' ', -$i) . $s;
                                    $len -= $i;
                                    $i = 0;
                                }

                                while ($j--) $s[$i+$j] = $uchr[$ulen+$j];

                                $uchr = substr($uchr, 0, $ulen);
                            }
                        }
                    } else {
                        // Hangul chars

                        $uchr = unpack('C*', $uchr);
                        $j = (($uchr[1]-224) << 12) + (($uchr[2]-128) << 6) + $uchr[3] - 0xAC80;

                        $uchr = "\xE1\x84" . chr(0x80 + (int)  ($j / 588))
                              . "\xE1\x85" . chr(0xA1 + (int) (($j % 588) / 28));

                        if ($j %= 28)
                        {
                            $uchr .= $j < 25
                                ? ("\xE1\x86" . chr(0xA7 + $j))
                                : ("\xE1\x87" . chr(0x67 + $j));
                        }
                    }

                    $result .= $uchr;
                }
            }
        }

        if ( $c ) {
            ksort($c);
            $result .= implode('', $c);
        }

        return $result;
    }

    protected static function getData($file) {
        $file = __DIR__ . '/unidata/' . $file . '.ser';
        if ( file_exists( $file ) ) { 
			return unserialize( file_get_contents( $file ) );
        } else {
			return false;
		}
    }
}

/**
 * Functions to provide fallbacks for changed function names in case any plug-ins or themes are calling WP to Twitter functions in custom code.
 */
function jd_fetch_url( $url, $method = 'GET', $body = '', $headers = '', $return = 'body' ) {
	return wpt_fetch_url( $url, $method, $body, $headers, $return );
}

function jd_remote_json( $url, $array = true ) {
	return wpt_remote_json( $url, $array );
}

function jd_twit_link( $link_ID ) {
	return wpt_twit_link( $link_ID );
}

function jd_post_info( $post_ID ) {
	return wpt_post_info( $post_ID );
}

function jd_twit( $post_ID, $type = 'instant' ) {
	return wpt_tweet( $post_ID, $type );
}

function jd_addTwitterAdminStyles() {
	return wpt_admin_style();
}