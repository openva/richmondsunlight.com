<?php
/*
* Version 2.0.3, Twitter Feed for Developers by Storm Consultancy (Liam Gladdy)
* The base class for the storm twitter feed for developers.
*/

// based on StormTwitter; significantly modified

require_once( 'wpt_twitter_oauth.php' );

class WPT_TwitterFeed {

	private $defaults = array(
		'directory'    => '',
		'key'          => '',
		'secret'       => '',
		'token'        => '',
		'token_secret' => '',
		'screenname'   => false,
		'cache_expire' => 1800
	);

	public $st_last_error = false;

	function __construct( $args = array() ) {
		$this->defaults = array_merge( $this->defaults, $args );
	}

	function __toString() {
		return print_r( $this->defaults, true );
	}

	//I'd prefer to put username before count, but for backwards compatibility it's not really viable. :(
	function getTweets( $count = 20, $screenname = false, $options = false ) {
		if ( $count > 20 ) {
			/**
			 * Filters the max feed count. Default is 20, but you can change it.
			 *
			 * @param integer 20 - Default value
			 * @param integer $count - Widget variable
			 * @return integer
			 */
			$count = apply_filters( 'wpt_feed_max_count', 20, $count );
		}
		if ( $count < 1 ) {
			$count = 1;
		}

		$default_options = array( 'trim_user' => true, 'exclude_replies' => true, 'include_rts' => false );

		if ( $options === false || ! is_array( $options ) ) {
			$options = $default_options;
		} else {
			$options = array_merge( $default_options, $options );
		}

		if ( $screenname == false ) {
			$screenname = get_option( 'wtt_twitter_username' );
		}

		$result = $this->checkValidCache( $screenname, $options );
		if ( $result !== false ) {
			return $this->cropTweets( $result, $count );
		}

		//If we're here, we need to load.
		$result = $this->oauthGetTweets( $screenname, $options );

		if ( is_object( $result ) && isset( $result->error ) ) {
			$last_error = $result->error;

			return array( 'error' => 'Twitter said: ' . $last_error );
		} else {
			return $this->cropTweets( $result, $count );
		}

	}

	private function cropTweets( $result, $count ) {
		if ( is_array( $result ) ) {
			return array_slice( $result, 0, $count );
		} else {
			return array();
		}
	}

	private function getCacheLocation() {
		return $this->defaults['directory'] . '.tweetcache';
	}

	private function getOptionsHash( $options ) {
		$hash = md5( serialize( $options ) );

		return $hash;
	}

	private function save_cache( $file, $cache ) {
		$is_writable = wpt_is_writable( $file );
		if ( $is_writable ) {
			file_put_contents( $file, $cache );
		} else {
			set_transient( 'wpt_cache', $cache, $this->defaults['cache_expire'] );
		}
	}

	private function delete_cache( $file ) {
		$is_writable = wpt_is_writable( $file );
		if ( $is_writable ) {
			unlink( $file );
		} else {
			delete_transient( 'wpt_cache' );
		}
	}	
	
	private function checkValidCache( $screenname, $options ) {
		$delete_cache = get_option( 'wpt_delete_cache' );
		$file = $this->getCacheLocation();
		
		if ( $delete_cache == 'true' ) {
			update_option( 'wpt_delete_cache', 'false' );
			$this->delete_cache( $file );
		}
		
		if ( is_file( $file ) ) {
			$cache = file_get_contents( $file );
			$cache = @json_decode( $cache, true );
			if ( ! isset( $cache ) ) {
				unlink( $file );

				return false;
			}
		} else {
			$cache = get_transient( 'wpt_cache' );
			$cache = @json_decode( $cache, true );
			if ( ! isset( $cache ) ) {
				return false;
			}
		}
		$cachename = $screenname . "-" . $this->getOptionsHash( $options );

		//Check if we have a cache for the user.
		if ( ! isset( $cache[ $cachename ] ) ) {
			return false;
		}

		if ( ! isset( $cache[ $cachename ]['time'] ) || ! isset( $cache[ $cachename ]['tweets'] ) ) {
			unset( $cache[ $cachename ] );
			$this->save_cache( $file, json_encode( $cache ) );

			return false;
		}

		if ( $cache[ $cachename ]['time'] < ( time() - $this->defaults['cache_expire'] ) ) {
			$result = $this->oauthGetTweets( $screenname, $options );
			if ( ! isset( $result->error ) ) {
				return $result;
			}
		}

		return $cache[ $cachename ]['tweets'];
	}

	private function oauthGetTweets( $screenname, $options ) {
		$key          = $this->defaults['key'];
		$secret       = $this->defaults['secret'];
		$token        = $this->defaults['token'];
		$token_secret = $this->defaults['token_secret'];
		$cachename    = $screenname . "-" . $this->getOptionsHash( $options );
		$options      = array_merge( $options, array( 'screen_name' => $screenname, 'count' => 20 ) );

		if ( empty( $key ) ) {
			return array( 'error' => 'Missing Consumer Key - Check Settings' );
		}
		if ( empty( $secret ) ) {
			return array( 'error' => 'Missing Consumer Secret - Check Settings' );
		}
		if ( empty( $token ) ) {
			return array( 'error' => 'Missing Access Token - Check Settings' );
		}
		if ( empty( $token_secret ) ) {
			return array( 'error' => 'Missing Access Token Secret - Check Settings' );
		}
		if ( empty( $screenname ) ) {
			return array( 'error' => __( 'Missing Twitter Feed Screen Name - Check Settings', 'wp-to-twitter' ) );
		}

		$connection = new wpt_TwitterOAuth( $key, $secret, $token, $token_secret );

		if ( isset( $options['search'] ) ) {
			$args = array( 'q'           => urlencode( $options['search'] ),
			               'result_type' => urlencode( $options['result_type'] )
			);
			if ( $options['geocode'] != '' ) {
				$args['geocode'] = urlencode( $options['geocode'] );
			}
			$url    = add_query_arg( $args, 'https://api.twitter.com/1.1/search/tweets.json' );
			$result = $connection->get( $url, $options );
		} else {
			$result = $connection->get( 'https://api.twitter.com/1.1/statuses/user_timeline.json', $options );
		}
		$result = json_decode( $result );
		if ( isset( $options['search'] ) ) {
			if ( !method_exists( $result, 'errors' ) ) {
				$result = $result->statuses;
			} else {
				$errors = $result->errors;
				$return = '';
				foreach ( $errors as $error ) {
					$return .= "<li>$error->message</li>";
				}
				echo "<ul>" . $return . "</ul>"; return;
			}
		}
		if ( is_file( $this->getCacheLocation() ) ) {
			$cache = json_decode( file_get_contents( $this->getCacheLocation() ), true );
		}

		if ( ! isset( $result->error ) ) {
			$cache[ $cachename ]['time']   = time();
			$cache[ $cachename ]['tweets'] = $result;
			$file                          = $this->getCacheLocation();
			$this->save_cache( $file, json_encode( $cache ) );
		} else {
			if ( is_array( $result ) && isset( $result['errors'][0] ) && isset( $result['errors'][0]['message'] ) ) {
				$last_error          = '[' . date( 'r' ) . '] ' . sprintf( __( 'Twitter error: %s', 'wp-to-twitter' ), $result['errors'][0]['message'] );
				$this->st_last_error = $last_error;
			} else {
				$last_error          = '[' . date( 'r' ) . ']' . __( 'Twitter returned an invalid response. It is probably down.', 'wp-to-twitter' );
				$this->st_last_error = $last_error;
			}
		}
		// Run an action on the results output from the Twitter widget query
		do_action( 'wpt_process_tweets', $result, $screenname, $options );

		return $result;
	}
}