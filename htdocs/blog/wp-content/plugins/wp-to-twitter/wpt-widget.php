<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * wpt Latest Tweets widget class.
 */

function wpt_get_user( $twitter_ID = false ) {
	if ( ! $twitter_ID ) {
		return;
	}
	$options      = array( 'screen_name' => $twitter_ID );
	$key          = get_option( 'app_consumer_key' );
	$secret       = get_option( 'app_consumer_secret' );
	$token        = get_option( 'oauth_token' );
	$token_secret = get_option( 'oauth_token_secret' );
	if ( $key && $secret && $token && $token_secret ) {
		$connection   = new wpt_TwitterOAuth( $key, $secret, $token, $token_secret );
		$result       = $connection->get( "https://api.twitter.com/1.1/users/show.json?screen_name=$twitter_ID", $options );

		return json_decode( $result );
	} else {
		return array();
	}
}

add_shortcode( 'get_tweets', 'wpt_get_twitter_feed' );
function wpt_get_twitter_feed( $atts, $content ) {
	extract( shortcode_atts( array(
		'id'       => false,
		'num'      => 10,
		'duration' => 1800,
		'replies'  => 0,
		'rts'      => 1,
		'links'    => 1,
		'mentions' => 1,
		'hashtags' => 0,
		'intents'  => 1,
		'source'   => 0,
		'show_images' => 1,
		'hide_header' => 0
	), $atts, 'get_tweets' ) );
	$instance = array(
		'twitter_id'           => $id,
		'twitter_num'          => $num,
		'twitter_duration'     => $duration,
		'twitter_hide_replies' => $replies,
		'twitter_include_rts'  => $rts,
		'link_links'           => $links,
		'link_mentions'        => $mentions,
		'link_hashtags'        => $hashtags,
		'intents'              => $intents,
		'source'               => $source,
		'show_images'          => $show_images,
		'hide_header'          => $hide_header
	);

	return wpt_twitter_feed( $instance );
}

function wpt_twitter_feed( $instance ) {
	$header = '';
	if ( ! isset( $instance['search'] ) ) {
		$twitter_ID = ( isset( $instance['twitter_id'] ) && $instance['twitter_id'] != '' ) ? $instance['twitter_id'] : get_option( 'wtt_twitter_username' );
		$user = wpt_get_user( $twitter_ID );
		if ( empty( $user ) ) {
			return __( 'Error: You are not connected to Twitter.', 'wp-to-twitter' );
		}
		if ( isset( $user->errors ) && $user->errors[0]->message ) {
			return __( "Error: ", 'wp-to-twitter' ) . $user->errors[0]->message;
		}
		$avatar           = $user->profile_image_url_https;
		$name             = $user->name;
		$verified         = sanitize_title( $user->verified );
		$img_alignment    = ( is_rtl() ) ? 'wpt-right' : 'wpt-left';
		$follow_alignment = ( is_rtl() ) ? 'wpt-left' : 'wpt-right';
		$follow_url       = esc_url( 'https://twitter.com/' . $twitter_ID );
		$follow_button    = apply_filters( 'wpt_follow_button', "<a href='$follow_url' class='twitter-follow-button $follow_alignment' data-width='30px' data-show-screen-name='false' data-size='large' data-show-count='false' data-lang='en'>Follow @" .  esc_html( $twitter_ID ) . "</a>" );
		$header .= '<div class="wpt-header">';
		$header .= "<div class='wpt-follow-button'>$follow_button</div>
		</p>
		<img src='$avatar' alt='' class='wpt-twitter-avatar $img_alignment $verified' />
		<span class='wpt-twitter-name'>$name</span><br />
		<span class='wpt-twitter-id'><a href='$follow_url'>@" .  esc_html( $twitter_ID ) . "</a></span>
		</p>";
		$header .= '</div>';
	} else {
		$twitter_ID = false;
	}
	
	$hide_header = ( isset( $instance['hide_header'] ) && $instance['hide_header'] == 1 ) ? true : false;
	
	if ( ! isset( $instance['search'] ) ) {
		$options['exclude_replies'] = ( isset( $instance['twitter_hide_replies'] ) ) ? $instance['twitter_hide_replies'] : false;
		$options['include_rts']     = $instance['twitter_include_rts'];
	} else {
		$options['search']      = $instance['search'];
		$options['geocode']     = $instance['geocode'];
		$options['result_type'] = $instance['result_type'];
	}
	
	if ( $hide_header ) {
		$header = ''; 
	}
	
	$return = $header . '<ul>' . "\n";	
	
	$opts['links']       = $instance['link_links'];
	$opts['mentions']    = $instance['link_mentions'];
	$opts['hashtags']    = $instance['link_hashtags'];
	$opts['show_images'] = isset( $instance['show_images'] ) ? $instance['show_images'] : false;
	$rawtweets           = WPT_getTweets( $instance['twitter_num'], $twitter_ID, $options );

	if ( isset( $rawtweets['error'] ) ) {
		$return .= "<li>" . $rawtweets['error'] . "</li>";
	} else {
		/** Build the tweets array */
		$tweets = array();
		foreach ( $rawtweets as $tweet ) {

			if ( is_object( $tweet ) ) {
				$tweet = json_decode( json_encode( $tweet ), true );
			}
			if ( $instance['source'] ) {
				$source    = $tweet['source'];
				$timetweet = sprintf( __( '<a href="%3$s">about %1$s ago</a> via %2$s', 'wp-to-twitter' ), human_time_diff( strtotime( $tweet['created_at'] ) ), $source, "http://twitter.com/" . $twitter_ID . "/status/$tweet[id_str]" );
			} else {
				$timetweet = sprintf( __( '<a href="%2$s">about %1$s ago</a>', 'wp-to-twitter' ), human_time_diff( strtotime( $tweet['created_at'] ) ), "http://twitter.com/$twitter_ID/status/$tweet[id_str]" );
			}
			$tweet_classes = wpt_generate_classes( $tweet );

			$intents = ( $instance['intents'] ) ? "<div class='wpt-intents-border'></div><div class='wpt-intents'><a class='wpt-reply' href='https://twitter.com/intent/tweet?in_reply_to=$tweet[id_str]'><span></span><span class='intent-text reply-text'>Reply</span></a> <a class='wpt-retweet' href='https://twitter.com/intent/retweet?tweet_id=$tweet[id_str]'><span></span><span class='intent-text retweet-text'>Retweet</span></a> <a class='wpt-favorite' href='https://twitter.com/intent/favorite?tweet_id=$tweet[id_str]'><span></span><span class='intent-text favorite-text'>Favorite</span></a></div>" : '';
			/** Add tweet to array */
			$before_tweet = apply_filters( 'wpt_before_tweet', '', $tweet );
			$after_tweet  = apply_filters( 'wpt_after_tweet', '', $tweet );
			$tweets[]     = '<li class="' . $tweet_classes . '">' . $before_tweet . wpt_tweet_linkify( $tweet['text'], $opts, $tweet ) . "<br /><span class='wpt-tweet-time'>$timetweet</span> $intents " . $after_tweet . "</li>\n";
		}
	}
	if ( is_array( $tweets ) ) {
		foreach ( $tweets as $tweet ) {
			$return .= $tweet;
		}
	}
	$return .= '</ul>' . "\n";

	return $return;
}


class WPT_Latest_Tweets_Widget extends WP_Widget {

	/**
	 * Holds widget settings defaults, populated in constructor.
	 *
	 * @var array
	 */
	protected $defaults;

	/**
	 * Constructor. Set the default widget options and create widget.
	 *
	 * @since 0.1.8
	 */
	function __construct() {

		$this->defaults = array(
			'title'                => '',
			'twitter_id'           => '',
			'twitter_num'          => '',
			'twitter_duration'     => '',
			'twitter_hide_replies' => 0,
			'twitter_include_rts'  => 0,
			'link_links'           => '',
			'link_mentions'        => '',
			'link_hashtags'        => '',
			'intents'              => '',
			'source'               => '',
			'show_images'          => '',
			'hide_header'          => 0
		);

		$widget_ops = array(
			'classname'   => 'wpt-latest-tweets',
			'description' => __( 'Display a list of your latest tweets.', 'wp-to-twitter' ),
		);

		$control_ops = array(
			'id_base' => 'wpt-latest-tweets',
			'width'   => 200,
			'height'  => 250,
		);
		parent::__construct( 'wpt-latest-tweets', __( 'WP to Twitter - Latest Tweets', 'wp-to-twitter' ), $widget_ops, $control_ops );
	}

	/**
	 * Echo the widget content.
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */

	function widget( $args, $instance ) {
		extract( $args );
		wp_enqueue_script( 'twitter-platform', "https://platform.twitter.com/widgets.js" );
		/** Merge with defaults */
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		echo $before_widget;
		if ( $instance['title'] ) {
			echo $before_title . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $after_title;
		}
		echo wpt_twitter_feed( $instance );
		echo $after_widget;
	}

	/**
	 * Update a particular instance.
	 *
	 * This function should check that $new_instance is set correctly.
	 * The newly calculated value of $instance should be returned.
	 * If "false" is returned, the instance won't be saved/updated.
	 *
	 * @since 0.1
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Settings to save or bool false to cancel saving
	 */
	function update( $new_instance, $old_instance ) {
		/** Force the cache to refresh */
		update_option( 'wpt_delete_cache', 'true' );
		$new_instance['title'] = strip_tags( $new_instance['title'] );

		return $new_instance;
	}

	/**
	 * Echo the settings update form.
	 *
	 * @param array $instance Current settings
	 *
	 * @return string
	 */
	function form( $instance ) {

		/** Merge with defaults */
		$instance = wp_parse_args( (array) $instance, $this->defaults );
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'wp-to-twitter' ); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat"/>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'twitter_id' ); ?>"><?php _e( 'Twitter Username', 'wp-to-twitter' ); ?>
				:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'twitter_id' ); ?>"
			       name="<?php echo $this->get_field_name( 'twitter_id' ); ?>"
			       value="<?php echo esc_attr( $instance['twitter_id'] ); ?>" class="widefat"/>
		</p>
		
		<p>
			<input id="<?php echo $this->get_field_id( 'hide_header' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'hide_header' ); ?>"
			       value="1" <?php checked( $instance['hide_header'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'hide_header' ); ?>"><?php _e( 'Hide Widget Header', 'wp-to-twitter' ); ?></label>
		</p>
		
		<p>
			<label
				for="<?php echo $this->get_field_id( 'twitter_num' ); ?>"><?php _e( 'Number of Tweets to Show', 'wp-to-twitter' ); ?>
				:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'twitter_num' ); ?>"
			       name="<?php echo $this->get_field_name( 'twitter_num' ); ?>"
			       value="<?php echo esc_attr( $instance['twitter_num'] ); ?>" size="3"/>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'twitter_hide_replies' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'twitter_hide_replies' ); ?>"
			       value="1" <?php checked( $instance['twitter_hide_replies'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'twitter_hide_replies' ); ?>"><?php _e( 'Hide @ Replies', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'twitter_include_rts' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'twitter_include_rts' ); ?>"
			       value="1" <?php checked( $instance['twitter_include_rts'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'twitter_include_rts' ); ?>"><?php _e( 'Include Retweets', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'link_links' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_links' ); ?>"
			       value="1" <?php checked( $instance['link_links'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_links' ); ?>"><?php _e( 'Parse links', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'link_mentions' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_mentions' ); ?>"
			       value="1" <?php checked( $instance['link_mentions'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_mentions' ); ?>"><?php _e( 'Parse @mentions', 'wp-to-twitter' ); ?></label>
		</p>
		
		<p>
			<input id="<?php echo $this->get_field_id( 'show_images' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'show_images' ); ?>"
			       value="1" <?php checked( $instance['show_images'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'show_images' ); ?>"><?php _e( 'Show Images', 'wp-to-twitter' ); ?></label>
		</p>		

		<p>
			<input id="<?php echo $this->get_field_id( 'link_hashtags' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_hashtags' ); ?>"
			       value="1" <?php checked( $instance['link_hashtags'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_hashtags' ); ?>"><?php _e( 'Parse #hashtags', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'intents' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'intents' ); ?>"
			       value="1" <?php checked( $instance['intents'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'intents' ); ?>"><?php _e( 'Include Reply/Retweet/Favorite Links', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'source' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'source' ); ?>"
			       value="1" <?php checked( $instance['source'], 1 ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'source' ); ?>"><?php _e( 'Include Tweet source', 'wp-to-twitter' ); ?></label>
		</p>
	<?php
	}
}

add_action( 'widgets_init', create_function( '', "register_widget('WPT_Latest_Tweets_Widget');" ) );


class WPT_Search_Tweets_Widget extends WP_Widget {

	/**
	 * Holds widget settings defaults, populated in constructor.
	 *
	 * @var array
	 */
	protected $defaults;

	/**
	 * Constructor. Set the default widget options and create widget.
	 *
	 * @since 0.1.8
	 */
	function __construct() {

		$this->defaults = array(
			'title'         => '',
			'twitter_num'   => '',
			'search'        => '',
			'result_type'   => 'recent', // mixed, recent, popular
			'geocode'       => '', // 37.777,-127.98,2km
			'link_links'    => '',
			'link_mentions' => '',
			'link_hashtags' => '',
			'intents'       => '',
			'source'        => ''
		);

		$widget_ops = array(
			'classname'   => 'wpt-search-tweets',
			'description' => __( 'Display a list of tweets returned by a search.', 'wp-to-twitter' ),
		);

		$control_ops = array(
			'id_base' => 'wpt-search-tweets',
			'width'   => 200,
			'height'  => 250,
		);
		parent::__construct( 'wpt-search-tweets', __( 'WP to Twitter - Searched Tweets', 'wp-to-twitter' ), $widget_ops, $control_ops );
	}

	/**
	 * Echo the widget content.
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */

	function widget( $args, $instance ) {
		extract( $args );
		wp_enqueue_script( 'twitter-platform', "https://platform.twitter.com/widgets.js" );
		/** Merge with defaults */
		$instance = wp_parse_args( (array) $instance, $this->defaults );
		echo $before_widget;
		if ( $instance['title'] ) {
			echo $before_title . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $after_title;
		}
		echo wpt_twitter_feed( $instance );
		echo $after_widget;
	}

	/**
	 * Update a particular instance.
	 *
	 * This function should check that $new_instance is set correctly.
	 * The newly calculated value of $instance should be returned.
	 * If "false" is returned, the instance won't be saved/updated.
	 *
	 * @since 0.1
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Settings to save or bool false to cancel saving
	 */
	function update( $new_instance, $old_instance ) {
		/** Force the cache to refresh */
		update_option( 'wpt_delete_cache', 'true' );		
		$new_instance['title'] = strip_tags( $new_instance['title'] );

		return $new_instance;
	}

	/**
	 * Echo the settings update form.
	 *
	 * @param array $instance Current settings
	 */
	function form( $instance ) {

		/** Merge with defaults */
		$instance = wp_parse_args( (array) $instance, $this->defaults );

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'wp-to-twitter' ); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       value="<?php echo esc_attr( $instance['title'] ); ?>" class="widefat"/>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'search' ); ?>"><?php _e( 'Search String', 'wp-to-twitter' ); ?>
				:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'search' ); ?>"
			       name="<?php echo $this->get_field_name( 'search' ); ?>"
			       value="<?php echo esc_attr( $instance['search'] ); ?>" class="widefat"/>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'twitter_num' ); ?>"><?php _e( 'Number of Tweets to Show', 'wp-to-twitter' ); ?>
				:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'twitter_num' ); ?>"
			       name="<?php echo $this->get_field_name( 'twitter_num' ); ?>"
			       value="<?php echo esc_attr( $instance['twitter_num'] ); ?>" size="3"/>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'result_type' ); ?>"><?php _e( 'Type of Results', 'wp-to-twitter' ); ?></label>
			<select name="<?php echo $this->get_field_name( 'result_type' ); ?>"
			        id="<?php echo $this->get_field_id( 'result_type' ); ?>">
				<option
					value='recent'<?php echo ( $instance['result_type'] == 'recent' ) ? ' selected="selected"' : ''; ?>><?php _e( 'Recent Tweets', 'wp-to-twitter' ); ?></option>
				<option
					value='popular'<?php echo ( $instance['result_type'] == 'popular' ) ? ' selected="selected"' : ''; ?>><?php _e( 'Popular Tweets', 'wp-to-twitter' ); ?></option>
				<option
					value='mixed'<?php echo ( $instance['result_type'] == 'mixed' ) ? ' selected="selected"' : ''; ?>><?php _e( 'Mixed', 'wp-to-twitter' ); ?></option>
			</select>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'geocode' ); ?>"><?php _e( 'Geocode (Latitude,Longitude,Radius)', 'wp-to-twitter' ); ?>
				:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'geocode' ); ?>"
			       name="<?php echo $this->get_field_name( 'geocode' ); ?>"
			       value="<?php echo esc_attr( $instance['geocode'] ); ?>" size="32"
			       placeholder="37.781157,-122.398720,2km"/>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'link_links' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_links' ); ?>"
			       value="1" <?php checked( $instance['link_links'] ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_links' ); ?>"><?php _e( 'Parse links', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'link_mentions' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_mentions' ); ?>"
			       value="1" <?php checked( $instance['link_mentions'] ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_mentions' ); ?>"><?php _e( 'Parse @mentions', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'link_hashtags' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'link_hashtags' ); ?>"
			       value="1" <?php checked( $instance['link_hashtags'] ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'link_hashtags' ); ?>"><?php _e( 'Parse #hashtags', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'intents' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'intents' ); ?>"
			       value="1" <?php checked( $instance['intents'] ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'intents' ); ?>"><?php _e( 'Include Reply/Retweet/Favorite Links', 'wp-to-twitter' ); ?></label>
		</p>

		<p>
			<input id="<?php echo $this->get_field_id( 'source' ); ?>" type="checkbox"
			       name="<?php echo $this->get_field_name( 'source' ); ?>"
			       value="1" <?php checked( $instance['source'] ); ?>/>
			<label
				for="<?php echo $this->get_field_id( 'source' ); ?>"><?php _e( 'Include Tweet source', 'wp-to-twitter' ); ?></label>
		</p>
	<?php
	}
}

add_action( 'widgets_init', create_function( '', "register_widget('WPT_Search_Tweets_Widget');" ) );

/**
 * Adds links to the contents of a tweet.
 * Forked from genesis_tweet_linkify, removed target = _blank
 *
 * Takes the content of a tweet, detects @replies, #hashtags, and
 * http:// links, and links them appropriately.
 *
 * @since 0.1
 *
 * @link http://www.snipe.net/2009/09/php-twitter-clickable-links/
 *
 * @param string $text A string representing the content of a tweet
 *
 * @return string Linkified tweet content
 */
function wpt_tweet_linkify( $text, $opts, $tweet ) {
	if ( $opts['show_images'] == true ) {
		$media = $tweet['entities']['media'];
		$media_urls = array();
		if ( !empty( $media ) ) {
			foreach ( $media as $image ) {
				$media_urls[] = $image['url'];
				// alt attributes are not available on Twitter.
				$text .= "<img src='$image[media_url_https]' alt='' class='wpt-twitter-image' />";
			}
		}
		if ( !empty( $media_urls ) ) {
			foreach ( $media_urls as $media_url ) {
				$text = str_replace( "$media_url", '', $text );
			}
		}
	}
	$text = ( $opts['links'] == true ) ? preg_replace( "#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" rel="nofollow">\\2</a>', $text ) : $text;
	$text = ( $opts['links'] == true ) ? preg_replace( "#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" rel="nofollow">\\2</a>', $text ) : $text;
	$text = ( $opts['mentions'] == true ) ? preg_replace( '/@(\w+)/', '<a href="https://www.twitter.com/\\1" rel="nofollow">@\\1</a>', $text ) : $text;
	$text = ( $opts['hashtags'] == true ) ? preg_replace( '/#(\w+)/', '<a href="https://twitter.com/search?q=%23\\1" rel="nofollow">#\\1</a>', $text ) : $text;
	$urls = $tweet['entities']['urls'];
	if ( is_array( $urls ) ) {
		foreach ( $urls as $url ) {

			$text = str_replace( ">$url[url]<", ">$url[display_url]<", $text );
		}
	}

	return $text;
}

/* implement getTweets */
function WPT_getTweets( $count = 20, $username = false, $options = false ) {

	$config['key']          = get_option( 'app_consumer_key' );
	$config['secret']       = get_option( 'app_consumer_secret' );
	$config['token']        = get_option( 'oauth_token' );
	$config['token_secret'] = get_option( 'oauth_token_secret' );
	$config['screenname']   = get_option( 'wtt_twitter_username' );
	$config['cache_expire'] = intval( apply_filters( 'wpt_cache_expire', 1800 ) );
	if ( $config['cache_expire'] < 1 ) {
		$config['cache_expire'] = 1800;
	}
	$config['directory'] = plugin_dir_path( __FILE__ );

	$obj = new WPT_TwitterFeed( $config );
	$res = $obj->getTweets( $count, $username, $options );
	update_option( 'wpt_tdf_last_error', $obj->st_last_error );

	return $res;

}

function wpt_generate_classes( $tweet ) {
	// take Tweet array and parse selected options into classes.
	$classes[] = ( $tweet['favorited'] ) ? 'favorited' : '';
	$clasees[] = ( $tweet['retweeted'] ) ? 'retweeted' : '';
	$classes[] = ( isset( $tweet['possibly_sensitive'] ) && $tweet['possibly_sensitive'] ) ? 'sensitive' : '';
	$classes[] = 'lang-' . $tweet['lang'];
	$class     = trim( implode( ' ', $classes ) );

	return $class;
}