<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// function to test credentials
function wtt_oauth_test( $auth = false, $context = '' ) {
	if ( ! $auth ) {
		return ( wtt_oauth_credentials_to_hash() == get_option( 'wtt_oauth_hash' ) );
	} else {
		$return = ( wtt_oauth_credentials_to_hash( $auth ) == wpt_get_user_verification( $auth ) );
		if ( ! $return && $context != 'verify' ) {
			return ( wtt_oauth_credentials_to_hash() == get_option( 'wtt_oauth_hash' ) );
		} else {
			return $return;
		}
	}
}

function wpt_get_user_verification( $auth ) {
	if ( get_option( 'jd_individual_twitter_users' ) != '1' ) {
		return false;
	} else {
		$auth = get_user_meta( $auth, 'wtt_oauth_hash', true );

		return $auth;
	}
}

// function to make connection
function wtt_oauth_connection( $auth = false ) {
	if ( ! $auth ) {
		$ack = get_option( 'app_consumer_key' );
		$acs = get_option( 'app_consumer_secret' );
		$ot  = get_option( 'oauth_token' );
		$ots = get_option( 'oauth_token_secret' );
	} else {
		$ack = get_user_meta( $auth, 'app_consumer_key', true );
		$acs = get_user_meta( $auth, 'app_consumer_secret', true );
		$ot  = get_user_meta( $auth, 'oauth_token', true );
		$ots = get_user_meta( $auth, 'oauth_token_secret', true );
	}
	if ( ! empty( $ack ) && ! empty( $acs ) && ! empty( $ot ) && ! empty( $ots ) ) {
		require_once( plugin_dir_path( __FILE__ ) . 'wpt_twitter_oauth.php' );
		$connection            = new wpt_TwitterOAuth( $ack, $acs, $ot, $ots );
		$connection->useragent = get_option( 'blogname' ) . ' ' . home_url();

		return $connection;
	} else {
		return false;
	}
}

// convert credentials to md5 hash
function wtt_oauth_credentials_to_hash( $auth = false ) {
	if ( ! $auth ) {
		$hash = md5( get_option( 'app_consumer_key' ) . get_option( 'app_consumer_secret' ) . get_option( 'oauth_token' ) . get_option( 'oauth_token_secret' ) );
	} else {
		$hash = md5( get_user_meta( $auth, 'app_consumer_key', true ) . get_user_meta( $auth, 'app_consumer_secret', true ) . get_user_meta( $auth, 'oauth_token', true ) . get_user_meta( $auth, 'oauth_token_secret', true ) );
	}

	return $hash;
}

/**
 * Back compat
 */
function jd_update_oauth_settings( $auth = false, $post = false ) {
	return wpt_update_oauth_settings( $auth, $post );
}

// response to settings updates
function wpt_update_oauth_settings( $auth = false, $post = false ) {
	if ( isset( $post['oauth_settings'] ) ) {
		switch ( $post['oauth_settings'] ) {
			case 'wtt_oauth_test':
				if ( ! wp_verify_nonce( $post['_wpnonce'], 'wp-to-twitter-nonce' ) && ! $auth ) {
					wp_die( 'Oops, please try again.' );
				}
				if ( ! empty( $post['wtt_app_consumer_key'] )
				     && ! empty( $post['wtt_app_consumer_secret'] )
				     && ! empty( $post['wtt_oauth_token'] )
				     && ! empty( $post['wtt_oauth_token_secret'] )
				) {
					$ack = trim( $post['wtt_app_consumer_key'] );
					$acs = trim( $post['wtt_app_consumer_secret'] );
					$ot  = trim( $post['wtt_oauth_token'] );
					$ots = trim( $post['wtt_oauth_token_secret'] );
					if ( ! $auth ) {
						update_option( 'app_consumer_key', $ack );
						update_option( 'app_consumer_secret', $acs );
						update_option( 'oauth_token', $ot );
						update_option( 'oauth_token_secret', $ots );
					} else {
						update_user_meta( $auth, 'app_consumer_key', $ack );
						update_user_meta( $auth, 'app_consumer_secret', $acs );
						update_user_meta( $auth, 'oauth_token', $ot );
						update_user_meta( $auth, 'oauth_token_secret', $ots );
					}
					$message = 'failed';
					if ( $connection = wtt_oauth_connection( $auth ) ) {
						$data = $connection->get( 'https://api.twitter.com/1.1/account/verify_credentials.json' );
						if ( $connection->http_code != '200' ) {
							$data  = json_decode( $data );
							$code  = "<a href='https://dev.twitter.com/docs/error-codes-responses'>" . $data->errors[0]->code . "</a>";
							$error = $data->errors[0]->message;
							update_option( 'wpt_error', "$code: $error" );
						} else {
							delete_option( 'wpt_error' );
						}
						if ( $connection->http_code == '200' ) {
							$error_information = '';
							$decode            = json_decode( $data );
							if ( ! $auth ) {
								update_option( 'wtt_twitter_username', stripslashes( $decode->screen_name ) );
							} else {
								update_user_meta( $auth, 'wtt_twitter_username', stripslashes( $decode->screen_name ) );
							}
							$oauth_hash = wtt_oauth_credentials_to_hash( $auth );
							if ( ! $auth ) {
								update_option( 'wtt_oauth_hash', $oauth_hash );
							} else {
								update_user_meta( $auth, 'wtt_oauth_hash', $oauth_hash );
							}
							$message = 'success';
							delete_option( 'wpt_curl_error' );
						} else if ( $connection->http_code == 0 ) {
							$error_information = __( "WP to Twitter was unable to establish a connection to Twitter.", 'wp-to-twitter' );
							update_option( 'wpt_curl_error', "$error_information" );
						} else {
							$status = ( isset( $connection->http_header['status'] ) ) ? $connection->http_header['status'] : '404';
							$error_information = array(
								"http_code" => $connection->http_code,
								"status"    => $status
							);
							$error_code        = __( "Twitter response: http_code $error_information[http_code] - $error_information[status]", 'wp-to-twitter' );
							update_option( 'wpt_curl_error', $error_code );
						}
						if ( get_option( 'wp_debug_oauth' ) == '1' ) {
							echo "<pre><strong>Summary Connection Response:</strong><br />";
							print_r( $error_information );
							echo "<br /><strong>Account Verification Data:</strong><br />";
							print_r( $data );
							echo "<br /><strong>Full Connection Response:</strong><br />";
							print_r( $connection );
							echo "</pre>";
						}
					}
				} else {
					$message = "nodata";
				}
				if ( $message == 'failed' && ( time() < strtotime( $connection->http_header['date'] ) - 300 || time() > strtotime( $connection->http_header['date'] ) + 300 ) ) {
					$message = 'nosync';
				}

				return $message;
				break;
			case 'wtt_twitter_disconnect':
				if ( ! wp_verify_nonce( $post['_wpnonce'], 'wp-to-twitter-nonce' ) && ! $auth ) {
					wp_die( 'Oops, please try again.' );
				}
				if ( ! $auth ) {
					update_option( 'app_consumer_key', '' );
					update_option( 'app_consumer_secret', '' );
					update_option( 'oauth_token', '' );
					update_option( 'oauth_token_secret', '' );
					update_option( 'wtt_twitter_username', '' );
				} else {
					delete_user_meta( $auth, 'app_consumer_key' );
					delete_user_meta( $auth, 'app_consumer_secret' );
					delete_user_meta( $auth, 'oauth_token' );
					delete_user_meta( $auth, 'oauth_token_secret' );
					delete_user_meta( $auth, 'wtt_twitter_username' );
				}
				$message = "cleared";

				return $message;
				break;
		}
	}

	return '';
}

// connect or disconnect form
function wtt_connect_oauth( $auth = false ) {
	if ( ! $auth ) {
		echo '<div class="ui-sortable meta-box-sortables">';
		echo '<div class="postbox">';
	}

	$class = ( $auth ) ? 'wpt-profile' : 'wpt-settings';
	$form  = ( ! $auth ) ? '<form action="" method="post">' : '';
	$nonce = ( ! $auth ) ? wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false ) . '</form>' : '';

	if ( ! wtt_oauth_test( $auth, 'verify' ) ) {

		// show notification to authenticate with OAuth. No longer global; settings only.
		if ( ! wpt_check_oauth() ) {
			$admin_url = admin_url( 'admin.php?page=wp-tweets-pro' );
			$message   = sprintf( __( "Twitter requires authentication by OAuth. You will need to <a href='%s'>update your settings</a> to complete installation of WP to Twitter.", 'wp-to-twitter' ), $admin_url );
			echo "<div class='error'><p>$message</p></div>";
		}

		$ack = ( ! $auth ) ? get_option( 'app_consumer_key' ) : get_user_meta( $auth, 'app_consumer_key', true );
		$acs = ( ! $auth ) ? get_option( 'app_consumer_secret' ) : get_user_meta( $auth, 'app_consumer_secret', true );
		$ot  = ( ! $auth ) ? get_option( 'oauth_token' ) : get_user_meta( $auth, 'oauth_token', true );
		$ots = ( ! $auth ) ? get_option( 'oauth_token_secret' ) : get_user_meta( $auth, 'oauth_token_secret', true );

		$submit = ( ! $auth ) ? '<p class="submit"><input type="submit" name="submit" class="button-primary" value="' . __( 'Connect to Twitter', 'wp-to-twitter' ) . '" /></p>' : '';
		print( '
			<h3><span>' . __( 'Connect to Twitter', 'wp-to-twitter' ) . '</span></h3>
			<div class="inside ' . $class . '">
			<div class="notes">
			<h4>' . __( 'WP to Twitter Set-up', 'wp-to-twitter' ) . '</h4>
			</div>
					<h4>' . __( '1. Register this site as an application on ', 'wp-to-twitter' ) . '<a href="https://apps.twitter.com/app/new/">' . __( 'Twitter\'s application registration page', 'wp-to-twitter' ) . '</a></h4>
						<ul>
						<li>' . __( 'If you\'re not currently logged in to Twitter, log-in to the account you want associated with this site', 'wp-to-twitter' ) . '</li>
						<li>' . __( 'Your application name cannot include the word "Twitter."', 'wp-to-twitter' ) . '</li>
						<li>' . __( 'Your Application Description can be anything.', 'wp-to-twitter' ) . '</li>
						<li>' . __( 'The WebSite and Callback URL should be ', 'wp-to-twitter' ) . '<strong>' . esc_url( home_url() ) . '</strong></li>
						</ul>
					<p><em>' . __( 'Agree to the Twitter Developer Agreement and continue.', 'wp-to-twitter' ) . '</em></p>
					<h4>' . __( '2. Switch to the "Permissions" tab in Twitter apps', 'wp-to-twitter' ) . '</h4>
						<ul>
						<li>' . __( 'Select "Read and Write" for the Application Type', 'wp-to-twitter' ) . '</li>
						<li>' . __( 'Update the application settings', 'wp-to-twitter' ) . '</li>
						</ul>
					<h4>' . __( '3. Switch to the Keys and Access Tokens tab and regenerate your consumer key and secret, then create your access token.', 'wp-to-twitter' ) . '</h4>
						<ul>
						<li>' . __( 'Copy your API key and API secret from the "Application Settings" section.', 'wp-to-twitter' ) . '</li>
						<li>' . __( 'Copy your Access token and Access token secret from the "Your Access Token" section.', 'wp-to-twitter' ) . '</li>
						</ul>
			' . $form . '
				<fieldset class="options">						
					<div class="tokens">
					<p>
						<label for="wtt_app_consumer_key">' . __( 'API Key', 'wp-to-twitter' ) . '</label>
						<input type="text" size="45" name="wtt_app_consumer_key" id="wtt_app_consumer_key" value="' . esc_attr( $ack ) . '" />
					</p>
					<p>
						<label for="wtt_app_consumer_secret">' . __( 'API Secret', 'wp-to-twitter' ) . '</label>
						<input type="text" size="45" name="wtt_app_consumer_secret" id="wtt_app_consumer_secret" value="' . esc_attr( $acs ) . '" />
					</p>
					</div>
					<h4>' . __( '4. Copy and paste your Access Token and Access Token Secret into the fields below', 'wp-to-twitter' ) . '</h4>
					<p>' . __( 'If the Access Level for your Access Token is not "<em>Read and write</em>", you must return to step 2 and generate a new Access Token.', 'wp-to-twitter' ) . '</p>
					<div class="tokens">
					<p>
						<label for="wtt_oauth_token">' . __( 'Access Token', 'wp-to-twitter' ) . '</label>
						<input type="text" size="45" name="wtt_oauth_token" id="wtt_oauth_token" value="' . esc_attr( $ot ) . '" />
					</p>
					<p>
						<label for="wtt_oauth_token_secret">' . __( 'Access Token Secret', 'wp-to-twitter' ) . '</label>
						<input type="text" size="45" name="wtt_oauth_token_secret" id="wtt_oauth_token_secret" value="' . esc_attr( $ots ) . '" />
					</p>
					</div>
				</fieldset>
				' . $submit . '
				<input type="hidden" name="oauth_settings" value="wtt_oauth_test" class="hidden" style="display: none;" />
				' . $nonce . '
			</div>	
				' );
	} else if ( wtt_oauth_test( $auth ) ) {
		$ack   = ( ! $auth ) ? esc_attr( get_option( 'app_consumer_key' ) ) : esc_attr( get_user_meta( $auth, 'app_consumer_key', true ) );
		$acs   = ( ! $auth ) ? esc_attr( get_option( 'app_consumer_secret' ) ) : esc_attr( get_user_meta( $auth, 'app_consumer_secret', true ) );
		$ot    = ( ! $auth ) ? esc_attr( get_option( 'oauth_token' ) ) : esc_attr( get_user_meta( $auth, 'oauth_token', true ) );
		$ots   = ( ! $auth ) ? esc_attr( get_option( 'oauth_token_secret' ) ) : esc_attr( get_user_meta( $auth, 'oauth_token_secret', true ) );
		$uname = ( ! $auth ) ? esc_attr( get_option( 'wtt_twitter_username' ) ) : esc_attr( get_user_meta( $auth, 'wtt_twitter_username', true ) );
		$nonce = ( ! $auth ) ? wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false ) . '</form>' : '';
		if ( ! $auth ) {
			$submit = '
					<input type="submit" name="submit" class="button-primary" value="' . __( 'Disconnect your WordPress and Twitter Account', 'wp-to-twitter' ) . '" />
					<input type="hidden" name="oauth_settings" value="wtt_twitter_disconnect" class="hidden" />
				';
		} else {
			$submit = '<input type="checkbox" name="oauth_settings" value="wtt_twitter_disconnect" id="disconnect" /> <label for="disconnect">' . __( 'Disconnect your WordPress and Twitter Account', 'wp-to-twitter' ) . '</label>';
		}

		print( '
			<h3><span>' . __( 'Disconnect from Twitter', 'wp-to-twitter' ) . '</span></h3>
			<div class="inside ' . $class . '">
			' . $form . '
				<div id="wtt_authentication_display">
					<fieldset class="options">
					<ul>
						<li><strong class="auth_label">' . __( 'Twitter Username ', 'wp-to-twitter' ) . '</strong> <code class="auth_code">' . $uname . '</code></li>
						<li><strong class="auth_label">' . __( 'API Key ', 'wp-to-twitter' ) . '</strong> <code class="auth_code">' . $ack . '</code></li>
						<li><strong class="auth_label">' . __( 'API Secret ', 'wp-to-twitter' ) . '</strong> <code class="auth_code">' . $acs . '</code></li>
						<li><strong class="auth_label">' . __( 'Access Token ', 'wp-to-twitter' ) . '</strong> <code class="auth_code">' . $ot . '</code></li>
						<li><strong class="auth_label">' . __( 'Access Token Secret ', 'wp-to-twitter' ) . '</strong> <code class="auth_code">' . $ots . '</code></li>
					</ul>
					</fieldset>
					<div>
					' . $submit . '
					</div>
				</div>		
				' . $nonce . '
			</div>' );

	}
	if ( ! $auth ) {
		echo "</div>";
		echo "</div>";
	}
}