<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
	
function wpt_updated_settings() {
	wpt_check_version();

	if ( ! empty( $_POST['_wpnonce'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'wp-to-twitter-nonce' ) ) {
			die( "Security check failed" );
		}
	}

	if ( isset( $_POST['oauth_settings'] ) ) {
		$oauth_message = wpt_update_oauth_settings( false, $_POST );
	} else {
		$oauth_message = '';
	}

	$message = "";

	// notifications from oauth connection		
	if ( isset( $_POST['oauth_settings'] ) ) {
		if ( $oauth_message == "success" ) {
			$admin_url = admin_url( 'admin.php?page=wp-tweets-pro?tab=basic' );

			print( '
				<div id="message" class="updated fade">
					<p>' . __( 'WP to Twitter is now connected with Twitter.', 'wp-to-twitter' ) . " <a href='$admin_url'>" . __( 'Configure your Tweet templates', 'wp-to-twitter' ) . '</a></p>
				</div>
			' );
		} else if ( $oauth_message == "failed" ) {
			print( '
				<div id="message" class="error fade">
					<p>' . __( 'WP to Twitter failed to connect with Twitter.', 'wp-to-twitter' ) . ' <strong>' . __( 'Error:', 'wp-to-twitter' ) . '</strong> ' . get_option( 'wpt_error' ) . '</p>
				</div>
			' );
		} else if ( $oauth_message == "cleared" ) {
			print( '
				<div id="message" class="updated fade">
					<p>' . __( 'OAuth Authentication Data Cleared.', 'wp-to-twitter' ) . '</p>
				</div>
			' );
		} else if ( $oauth_message == 'nosync' ) {
			print( '
				<div id="message" class="error fade">
					<p>' . __( 'OAuth Authentication Failed. Your server time is not in sync with the Twitter servers. Talk to your hosting service to see what can be done.', 'wp-to-twitter' ) . '</p>
				</div>
			' );
		} else {
			print( '
				<div id="message" class="error fade">
					<p>' . __( 'OAuth Authentication response not understood.', 'wp-to-twitter' ) . '</p>
				</div>			
			' );
		}
	}

	if ( isset( $_POST['submit-type'] ) && $_POST['submit-type'] == 'advanced' ) {
		update_option( 'jd_tweet_default', ( isset( $_POST['jd_tweet_default'] ) ) ? $_POST['jd_tweet_default'] : 0 );
		update_option( 'jd_tweet_default_edit', ( isset( $_POST['jd_tweet_default_edit'] ) ) ? $_POST['jd_tweet_default_edit'] : 0 );
		
		if ( isset( $_POST['wpt_rate_limiting'] ) && get_option( 'wpt_rate_limiting' ) != 1 ) {
			$extend = __( 'Rate Limiting is enabled. Default rate limits are set at 10 posts per category/term per hour. <a href="#special_cases">Edit global default</a> or edit individual terms to customize limits for each category or taxonomy term.', 'wp-to-twitter' );
			wp_schedule_event( current_time( 'timestamp' )+3600, 'hourly', 'wptratelimits' );
		} else {
			$extend = '';
			wp_clear_scheduled_hook( 'wptratelimits' );
		}		
		
		update_option( 'wpt_rate_limiting', ( isset( $_POST['wpt_rate_limiting'] ) ) ? 1 : 0 );
		update_option( 'wpt_inline_edits', ( isset( $_POST['wpt_inline_edits'] ) ) ? $_POST['wpt_inline_edits'] : 0 );
		update_option( 'jd_twit_remote', ( isset( $_POST['jd_twit_remote'] ) ) ? $_POST['jd_twit_remote'] : 0 );
		update_option( 'jd_twit_custom_url', $_POST['jd_twit_custom_url'] );
		update_option( 'wpt_default_rate_limit', ( isset( $_POST['wpt_default_rate_limit'] ) ? intval( $_POST['wpt_default_rate_limit'] ) : false ) );
		update_option( 'jd_strip_nonan', ( isset( $_POST['jd_strip_nonan'] ) ) ? $_POST['jd_strip_nonan'] : 0 );
		update_option( 'jd_twit_prepend', $_POST['jd_twit_prepend'] );
		update_option( 'jd_twit_append', $_POST['jd_twit_append'] );
		update_option( 'jd_post_excerpt', $_POST['jd_post_excerpt'] );
		update_option( 'jd_max_tags', $_POST['jd_max_tags'] );
		update_option( 'wpt_tag_source', ( ( isset( $_POST['wpt_tag_source'] ) && $_POST['wpt_tag_source'] == 'slug' ) ? 'slug' : '' ) );
		update_option( 'jd_max_characters', $_POST['jd_max_characters'] );
		update_option( 'jd_replace_character', $_POST['jd_replace_character'] );
		update_option( 'jd_date_format', $_POST['jd_date_format'] );
		update_option( 'jd_dynamic_analytics', $_POST['jd-dynamic-analytics'] );

		$twitter_analytics = ( isset( $_POST['twitter-analytics'] ) ) ? $_POST['twitter-analytics'] : 0;
		if ( $twitter_analytics == 1 ) {
			update_option( 'use_dynamic_analytics', 0 );
			update_option( 'use-twitter-analytics', 1 );
			update_option( 'no-analytics', 0 );
		} else if ( $twitter_analytics == 2 ) {
			update_option( 'use_dynamic_analytics', 1 );
			update_option( 'use-twitter-analytics', 0 );
			update_option( 'no-analytics', 0 );
		} else {
			update_option( 'use_dynamic_analytics', 0 );
			update_option( 'use-twitter-analytics', 0 );
			update_option( 'no-analytics', 1 );
		}

		update_option( 'twitter-analytics-campaign', $_POST['twitter-analytics-campaign'] );
		update_option( 'jd_individual_twitter_users', ( isset( $_POST['jd_individual_twitter_users'] ) ? $_POST['jd_individual_twitter_users'] : 0 ) );


		if ( isset( $_POST['wpt_caps'] ) ) {
			$perms = $_POST['wpt_caps'];
			$caps  = array(
				'wpt_twitter_oauth',
				'wpt_twitter_custom',
				'wpt_twitter_switch',
				'wpt_can_tweet',
				'wpt_tweet_now'
			);
			foreach ( $perms as $key => $value ) {
				$role = get_role( $key );
				if ( is_object( $role ) ) {
					foreach ( $caps as $v ) {
						if ( isset( $value[ $v ] ) ) {
							$role->add_cap( $v );
						} else {
							$role->remove_cap( $v );
						}
					}
				}
			}
		}

		update_option( 'wpt_permit_feed_styles', ( isset( $_POST['wpt_permit_feed_styles'] ) ) ? 1 : 0 );
		update_option( 'wp_debug_oauth', ( isset( $_POST['wp_debug_oauth'] ) ) ? 1 : 0 );
		update_option( 'jd_donations', ( isset( $_POST['jd_donations'] ) ) ? 1 : 0 );
		$wpt_truncation_order = $_POST['wpt_truncation_order'];
		update_option( 'wpt_truncation_order', $wpt_truncation_order );
		$message .= __( 'WP to Twitter Advanced Options Updated', 'wp-to-twitter' ) . '. ' . $extend;
	}

	if ( isset( $_POST['submit-type'] ) && $_POST['submit-type'] == 'options' ) {
		// UPDATE OPTIONS
		$wpt_settings = get_option( 'wpt_post_types' );
		foreach ( $_POST['wpt_post_types'] as $key => $value ) {
			$array                = array(
				'post-published-update' => ( isset( $value["post-published-update"] ) ) ? $value["post-published-update"] : "",
				'post-published-text'   => $value["post-published-text"],
				'post-edited-update'    => ( isset( $value["post-edited-update"] ) ) ? $value["post-edited-update"] : "",
				'post-edited-text'      => $value["post-edited-text"]
			);
			$wpt_settings[ $key ] = $array;
		}
		update_option( 'wpt_post_types', $wpt_settings );
		update_option( 'newlink-published-text', $_POST['newlink-published-text'] );
		update_option( 'jd_twit_blogroll', ( isset( $_POST['jd_twit_blogroll'] ) ) ? $_POST['jd_twit_blogroll'] : "" );
		$message = wpt_select_shortener( $_POST );
		$message .= __( 'WP to Twitter Options Updated', 'wp-to-twitter' );
		$message = apply_filters( 'wpt_settings', $message, $_POST );
	}

	if ( isset( $_POST['wpt_shortener_update'] ) && $_POST['wpt_shortener_update'] == 'true' ) {
		$message = wpt_shortener_update( $_POST );
	}

	// Check whether the server has supported for needed functions.
	if ( isset( $_POST['submit-type'] ) && $_POST['submit-type'] == 'check-support' ) {
		$message = wpt_check_functions();
	}

	if ( $message ) {
		echo '<div id="message" class="updated is-dismissible"><p>' . $message . '</p></div>';
	}
}

function wpt_update_settings() {
	?>
	<div class="wrap" id="wp-to-twitter">
	<?php 
		if ( defined( 'WPT_STAGING_MODE' ) && WPT_STAGING_MODE == true ) {
			echo "<div class='updated notice'><p>" . __( 'WP to Twitter is in staging mode. Tweets will be reported as if successfully sent to Twitter but will not be sent.', 'wp-to-twitter' ) . "</p></div>";
		}
		wpt_updated_settings(); 
		wpt_show_last_tweet();
		wpt_handle_errors();
		wpt_show_debug();
	?>
	<?php $elem = ( version_compare( '4.3', get_option( 'version' ), '>=' ) ) ? 'h1' : 'h2'; ?>
	<<?php echo $elem; ?>><?php _e( "WP to Twitter Options", 'wp-to-twitter' ); ?></<?php echo $elem; ?>>
	
	<div class='nav-tab-wrapper'>
		<?php wpt_settings_tabs(); ?>
	</div>
	<div id="wpt_settings_page" class="postbox-container jcd-wide">
	<div class="metabox-holder">

	<?php 
		$default = ( get_option( 'wtt_twitter_username' ) == '' ) ? 'connection' : 'basic';
		$current = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : $default;
	if ( $current == 'connection' ) {
		if ( function_exists( 'wtt_connect_oauth' ) ) {
			wtt_connect_oauth();
		}
	}
	if ( $current == 'pro' ) {
		if ( function_exists( 'wpt_pro_functions' ) ) {
			wpt_pro_functions();
			if ( function_exists( 'wpt_notes' ) ) {
				wpt_notes();
			}
		} else {
			if ( ! function_exists( 'wpt_pro_exists' ) ) { ?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3 class='wpt-upgrade'><span><strong><?php _e( 'Upgrade Now!', 'wp-to-twitter' ); ?></strong></span></h3>

						<div class="inside purchase">
							<h4><strong><?php _e( 'What can WP Tweets PRO do for you?', 'wp-to-twitter' ); ?></strong></h4>
							<p>
								<?php 
									_e( 'WP Tweets PRO takes the great Tweeting abilities from WP to Twitter and puts them in high gear.', 'wp-to-twitter' );
								?>
							</p>
							<ul>
								<li><?php _e( 'Publish to unique Twitter accounts for each site author.','wp-to-twitter' ); ?></li>
								<li><?php _e( 'Schedule up to 3 re-posts of Tweets at an interval of your choice.', 'wp-to-twitter' ); ?></li>
								<li><?php _e( 'With a delay between publishing and Tweeting, verify your tweets before you share online.', 'wp-to-twitter' ); ?></li>
							</ul>
							<p>
								<?php 
									_e( "Use WP Tweets PRO to keep traffic coming for every post.", 'wp-to-twitter' ); 
								?>
							</p>
							<p class='wpt-button'>
								<strong class='cta'><a href="http://www.wptweetspro.com/wp-tweets-pro"><?php _e( 'Upgrade to <strong>WP Tweets PRO</strong>!', 'wp-to-twitter' ); ?></a></strong>
							</p>	
							
							<h4><?php _e( 'What else does WP Tweets PRO do?', 'wp-to-twitter' ); ?></h4>
							
							<p>
								<?php _e( 'WP Tweets PRO is packed with features to help you increase engagement with your Twitter followers. Upload images, use Twitter Cards, and automated re-posting of your Tweets are just a few of the features available in the premium add-on to WP to Twitter.', 'wp-to-twitter' ); ?>
							</p>
							<p>
								<?php sprintf( _e( '<a href="%s">Learn more about WP Tweets PRO</a>!', 'wp-to-twitter' ), 'http://www.wptweetspro.com/wp-tweets-pro?campaign=get-wpt' ); ?>
							</p>
							
							<p class='wpt-button'>
								<strong class='cta'><a href="http://www.wptweetspro.com/wp-tweets-pro"><?php _e( 'Buy WP Tweets PRO today!', 'wp-to-twitter' ); ?></a></strong>
							</p>
							
						</div>
					</div>
				</div>
			<?php
			}
		}
	}
	if ( $current == 'basic' ) {
	?>
	<div class="ui-sortable meta-box-sortables">
		<div class="postbox">
			<h3><span><?php _e( 'Status Update Templates', 'wp-to-twitter' ); ?></span></h3>

			<div class="inside wpt-settings">
				<form method="post" action="">
					<?php $nonce = wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false );
					echo "<div>$nonce</div>"; ?>
					<div>
						<?php echo apply_filters( 'wpt_pick_shortener', '' ); ?>
						<?php
						$post_types   = get_post_types( array( 'public' => true ), 'objects' );
						$wpt_settings = get_option( 'wpt_post_types' );
						$tabs         = "<ul class='tabs' role='tablist'>";
						foreach ( $post_types as $type ) {
							$name     = $type->labels->name;
							$slug     = $type->name;
							if ( $slug == 'attachment' || $slug == 'nav_menu_item' || $slug == 'revision' ) {
							} else {
								$tabs .= "<li><a href='#wpt_$slug' role='tab' id='tab_wpt_$slug' aria-controls='wpt_$slug'>$name</a></li>";
							}
						}
						$tabs .= "<li><a href='#wpt_links' id='tab_wpt_links' aria-controls='wpt_links'>" . __( 'Links', 'wp-to-twitter' ) . "</a></li>
			</ul>";
						echo $tabs;
						foreach ( $post_types as $type ) {
							$name     = $type->labels->name;
							$singular = $type->labels->singular_name;
							$slug     = $type->name;
							if ( $slug == 'attachment' || $slug == 'nav_menu_item' || $slug == 'revision' ) {
								continue;
							} else {
								$vowels = array( 'a', 'e', 'i', 'o', 'u' );
								foreach ( $vowels as $vowel ) {
									if ( strpos( $name, $vowel ) === 0 ) {
										$word = 'an';
										break;
									} else {
										$word = 'a';
									}
								}
								?>
	
								<div class='wptab wpt_types wpt_<?php echo $slug; ?>' aria-labelledby='tab_wpt_<?php echo $slug; ?>' role="tabpanel" id='wpt_<?php echo $slug; ?>'>
									<?php
									// share information about any usage of pre 2.8 category filters
									if ( get_option( 'limit_categories' ) != '0' && $slug == 'post' ) {
										$falseness  = get_option( 'jd_twit_cats' );
										$categories = get_option( 'tweet_categories' );
										if ( $falseness == 1 ) {
											echo "<p>" . __( 'These categories are currently <strong>excluded</strong> by the deprecated WP to Twitter category filters.', 'wp-to-twitter' ) . "</p>";
										} else {
											echo "<p>" . __( 'These categories are currently <strong>allowed</strong> by the deprecated WP to Twitter category filters.', 'wp-to-twitter' ) . "</p>";
										}
										echo "<ul>";
										if ( is_array( $categories ) ) {
											foreach ( $categories as $cat ) {
												$category = get_the_category_by_ID( $cat );
												echo "<li>$category</li>";
											}
										}
										echo "</ul>";
										if ( ! function_exists( 'wpt_pro_exists' ) ) {
											printf( __( '<a href="%s">Upgrade to WP Tweets PRO</a> to filter posts in all custom post types on any taxonomy.', 'wp-to-twitter' ), "http://www.wptweetspro.com/wp-tweets-pro" );
										} else {
											_e( 'Updating the WP Tweets PRO taxonomy filters will overwrite your old category filters.', 'wp-to-twitter' );
										}
									}
									?>
									<fieldset>
										<legend><?php _e( 'Tweet Templates', 'wp-to-twitter' ); ?></legend>
										<p>
											<input type="checkbox"
											       name="wpt_post_types[<?php echo $slug; ?>][post-published-update]"
											       id="<?php echo $slug; ?>-post-published-update"
											       value="1" <?php echo jd_checkCheckbox( 'wpt_post_types', $slug, 'post-published-update' ) ?> />
											<label
												for="<?php echo $slug; ?>-post-published-update"><strong><?php printf( __( 'Update when %1$s %2$s is published', 'wp-to-twitter' ), $word, $singular ); ?></strong></label>
											<label
												for="<?php echo $slug; ?>-post-published-text"><br/><?php printf( __( 'Template for new %1$s updates', 'wp-to-twitter' ), $name ); ?>
											</label><br/><textarea class="wpt-template"
											                       name="wpt_post_types[<?php echo $slug; ?>][post-published-text]"
											                       id="<?php echo $slug; ?>-post-published-text"
											                       cols="60"
											                       rows="3"><?php if ( isset( $wpt_settings[ $slug ] ) ) {
													echo esc_attr( stripslashes( $wpt_settings[ $slug ]['post-published-text'] ) );
												} ?></textarea>
										</p>

										<p>
											<input type="checkbox"
											       name="wpt_post_types[<?php echo $slug; ?>][post-edited-update]"
											       id="<?php echo $slug; ?>-post-edited-update"
											       value="1" <?php echo jd_checkCheckbox( 'wpt_post_types', $slug, 'post-edited-update' ) ?> />
											<label
												for="<?php echo $slug; ?>-post-edited-update"><strong><?php printf( __( 'Update when %1$s %2$s is edited', 'wp-to-twitter' ), $word, $singular ); ?></strong></label><br/><label
												for="<?php echo $slug; ?>-post-edited-text"><?php printf( __( 'Template for %1$s editing updates', 'wp-to-twitter' ), $name ); ?></label><br/><textarea
												class="wpt-template"
												name="wpt_post_types[<?php echo $slug; ?>][post-edited-text]"
												id="<?php echo $slug; ?>-post-edited-text" cols="60"
												rows="3"><?php if ( isset( $wpt_settings[ $slug ] ) ) {
													echo esc_attr( stripslashes( $wpt_settings[ $slug ]['post-edited-text'] ) );
												} ?></textarea>
										</p>
									</fieldset>
									<?php if ( function_exists( 'wpt_list_terms' ) ) {
										wpt_list_terms( $slug, $name );
									} ?>
								</div>
							<?php
							}
						}
						?>
						<div class='wptab wpt_types wpt_links' id="wpt_links">
							<fieldset>
								<legend><span><?php _e( 'Links', 'wp-to-twitter' ); ?></span></legend>
								<p>
									<input type="checkbox" name="jd_twit_blogroll" id="jd_twit_blogroll"
									       value="1" <?php echo jd_checkCheckbox( 'jd_twit_blogroll' ) ?> />
									<label
										for="jd_twit_blogroll"><strong><?php _e( "Update Twitter when you post a Blogroll link", 'wp-to-twitter' ); ?></strong></label><br/>
									<label
										for="newlink-published-text"><?php _e( "Text for new link updates:", 'wp-to-twitter' ); ?></label>
									<input aria-describedby="newlink-published-text-label" type="text"
									       class="wpt-template" name="newlink-published-text"
									       id="newlink-published-text" size="60" maxlength="120"
									       value="<?php esc_attr_e( stripslashes( get_option( 'newlink-published-text' ) ) ); ?>"/><br/><span
										id="newlink-published-text-label"><?php _e( 'Available shortcodes: <code>#url#</code>, <code>#title#</code>, and <code>#description#</code>.', 'wp-to-twitter' ); ?></span>
								</p>
							</fieldset>
						</div>
						<br class='clear'/>

						<div>
							<input type="hidden" name="submit-type" value="options"/>
						</div>
						<input type="submit" name="submit"
						       value="<?php _e( "Save WP to Twitter Options", 'wp-to-twitter' ); ?>"
						       class="button-primary"/>
					</div>
				</form>
			</div>
		</div>
	</div>
	
	<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h3><span><?php _e( 'Tweet Template Tags', 'wp-to-twitter' ); ?></span></h3>

				<div class="inside">
					<ul>
						<li><?php _e( "<code>#title#</code>: the title of your blog post", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#blog#</code>: the title of your blog", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#post#</code>: a short excerpt of the post content", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#category#</code>: the first selected category for the post", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#cat_desc#</code>: custom value from the category description field", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#date#</code>: the post date", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#modified#</code>: the post modified date", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#url#</code>: the post URL", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#longurl#</code>: the unshortened post URL", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#author#</code>: the post author (@reference if available, otherwise display name)", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#displayname#</code>: post author's display name", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#account#</code>: the twitter @reference for the account (or the author, if author settings are enabled and set.)", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#@#</code>: the twitter @reference for the author or blank, if not set", 'wp-to-twitter' ); ?></li>
						<li><?php _e( "<code>#tags#</code>: your tags modified into hashtags. See options in the Advanced Settings section, below.", 'wp-to-twitter' ); ?></li>
						<?php if ( function_exists( 'wpt_pro_exists' ) && wpt_pro_exists() == true ) { ?>
							<li><?php _e( "<code>#reference#</code>: Used only in co-tweeting. @reference to main account when posted to author account, @reference to author account in post to main account.", 'wp-to-twitter' ); ?></li>
						<?php } ?>
					</ul>
					<p>
					<?php 
						_e( "Create custom shortcodes and access WordPress custom fields by using square brackets and the name of your custom field.", 'wp-to-twitter' );
					?>
					<br />
					<?php
						_e( "<strong>Example:</strong> <code>[[custom_field]]</code>", 'wp-to-twitter' ); 
					?>
					</p>
				</div>
			</div>
		</div>	
	<?php } 
	if ( $current == 'shortener' ) { 
		echo apply_filters( 'wpt_shortener_controls', '' ); 
	}
	
	if ( $current == 'advanced' ) {
	?>
	<div class="ui-sortable meta-box-sortables">
		<div class="postbox">
			<h3><span><?php _e( 'Advanced Settings', 'wp-to-twitter' ); ?></span></h3>
			<div class="inside">
				<form method="post" action="">
					<div>
						<?php 
							$nonce = wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false );
							echo "<div>$nonce</div>"; 
						?>

						<fieldset>
							<legend><?php _e( 'Hashtags', 'wp-to-twitter' ); ?></legend>
							<p>
								<input type="checkbox" name="jd_strip_nonan" id="jd_strip_nonan"
								       value="1" <?php echo jd_checkCheckbox( 'jd_strip_nonan' ); ?> /> <label
									for="jd_strip_nonan"><?php _e( "Strip nonalphanumeric characters from tags", 'wp-to-twitter' ); ?></label>
							</p>

							<p>
								<input type="checkbox" name="wpt_tag_source" id="wpt_tag_source"
								       value="slug" <?php echo jd_checkSelect( 'wpt_tag_source', 'slug', 'checkbox' ); ?> />
								<label
									for="wpt_tag_source"><?php _e( "Use tag slug as hashtag value", 'wp-to-twitter' ); ?></label><br/>
							</p>

							<p>
								<label
									for="jd_replace_character"><?php _e( "Spaces in tags replaced with:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_replace_character" id="jd_replace_character"
								       value="<?php esc_attr_e( get_option( 'jd_replace_character' ) ); ?>"
								       size="3"/>
							</p>

							<p>
								<label
									for="jd_max_tags"><?php _e( "Maximum number of tags to include:", 'wp-to-twitter' ); ?></label>
								<input aria-describedby="jd_max_characters_label" type="text" name="jd_max_tags"
								       id="jd_max_tags" value="<?php esc_attr_e( get_option( 'jd_max_tags' ) ); ?>"
								       size="3"/>
							</p>
							<p>
								<label
									for="jd_max_characters"><?php _e( "Maximum length in characters for included tags:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_max_characters" id="jd_max_characters"
								       value="<?php esc_attr_e( get_option( 'jd_max_characters' ) ); ?>" size="3"/>
							</p>
						</fieldset>
						<fieldset>
							<legend><?php _e( 'Template Settings', 'wp-to-twitter' ); ?></legend>
							<p>
								<label
									for="jd_post_excerpt"><?php _e( "Post excerpt (#post#) in characters:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_post_excerpt" id="jd_post_excerpt" size="3" maxlength="3" value="<?php echo( esc_attr( get_option( 'jd_post_excerpt' ) ) ) ?>"/>
							</p>

							<p>
								<label
									for="jd_date_format"><?php _e( "Date Format (#date#):", 'wp-to-twitter' ); ?></label>
								<input type="text" aria-describedby="date_format_label" name="jd_date_format"
								       id="jd_date_format" size="12" maxlength="12"
								       value="<?php if ( get_option( 'jd_date_format' ) == '' ) {
									       echo( esc_attr( stripslashes( get_option( 'date_format' ) ) ) );
								       } else {
									       echo( esc_attr( get_option( 'jd_date_format' ) ) );
								       } ?>"/> <?php if ( get_option( 'jd_date_format' ) != '' ) {
									echo date_i18n( get_option( 'jd_date_format' ) );
								} else {
									echo "<em>" . date_i18n( get_option( 'date_format' ) ) . "</em>";
								} ?> (<em
									id="date_format_label"><a href='http://codex.wordpress.org/Formatting_Date_and_Time'><?php _e( "Date Formatting", 'wp-to-twitter' ); ?></a></em>)
							</p>

							<p>
								<label for="jd_twit_prepend"><?php _e( "Custom text before Tweets:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_twit_prepend" id="jd_twit_prepend" size="20"
								       value="<?php esc_attr_e( stripslashes( get_option( 'jd_twit_prepend' ) ) ) ?>"/>
							</p>
							<p>
								<label for="jd_twit_append"><?php _e( "Custom text after Tweets:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_twit_append" id="jd_twit_append" size="20"
								       value="<?php esc_attr_e( stripslashes( get_option( 'jd_twit_append' ) ) ) ?>"/>
							</p>
							<p>
								<label for="jd_twit_custom_url"><?php _e( "Custom field for alternate post URL:", 'wp-to-twitter' ); ?></label>
								<input type="text" name="jd_twit_custom_url" id="jd_twit_custom_url" size="30" maxlength="120"
								       value="<?php esc_attr_e( stripslashes( get_option( 'jd_twit_custom_url' ) ) ) ?>"/>
							</p>
						</fieldset>
						<fieldset>
							<legend id="special_cases"><?php _e( "Special Cases", 'wp-to-twitter' ); ?></legend>
							<p>
								<input type="checkbox" name="jd_tweet_default" id="jd_tweet_default"
								       value="1" <?php echo jd_checkCheckbox( 'jd_tweet_default' ) ?> />
								<label
									for="jd_tweet_default"><?php _e( "Do not post Tweets by default", 'wp-to-twitter' ); ?></label><br/>
								<input type="checkbox" name="jd_tweet_default_edit" id="jd_tweet_default_edit"
								       value="1" <?php echo jd_checkCheckbox( 'jd_tweet_default_edit' ) ?> />
								<label
									for="jd_tweet_default_edit"><?php _e( "Do not post Tweets by default (editing only)", 'wp-to-twitter' ); ?></label><br/>
								<input type="checkbox" name="wpt_inline_edits" id="wpt_inline_edits"
								       value="1" <?php echo jd_checkCheckbox( 'wpt_inline_edits' ) ?> />
								<label
									for="wpt_inline_edits"><?php _e( "Allow status updates from Quick Edit", 'wp-to-twitter' ); ?></label><br/>
								<input type="checkbox" name="wpt_rate_limiting" id="wpt_rate_limiting"
								       value="1" <?php echo jd_checkCheckbox( 'wpt_rate_limiting' ) ?> />
								<label
									for="wpt_rate_limiting"><?php _e( "Enable Rate Limiting", 'wp-to-twitter' ); ?></label><br/>
								<?php
								if ( get_option( 'wpt_rate_limiting' ) == 1 ) {
									?>
								<input type="number" name="wpt_default_rate_limit" min="1" id="wpt_default_rate_limit"
								       value="<?php echo wpt_default_rate_limit(); ?>" />
								<label
									for="wpt_default_rate_limit"><?php _e( "Default Rate Limit per category per hour", 'wp-to-twitter' ); ?></label><br/>							
									<?php
								}
								?>
							</p>
						</fieldset>
						<fieldset>
							<legend><?php _e( "Google Analytics Settings", 'wp-to-twitter' ); ?></legend>

							<p>
								<input type="radio" name="twitter-analytics" id="use-twitter-analytics"
								       value="1" <?php echo jd_checkCheckbox( 'use-twitter-analytics' ) ?> />
								<label
									for="use-twitter-analytics"><?php _e( "Use a Static Identifier", 'wp-to-twitter' ); ?></label><br/>
								<label
									for="twitter-analytics-campaign"><?php _e( "Static Campaign identifier", 'wp-to-twitter' ); ?></label>
								<input type="text" name="twitter-analytics-campaign" id="twitter-analytics-campaign"
								       size="40" maxlength="120"
								       value="<?php esc_attr_e( get_option( 'twitter-analytics-campaign' ) ) ?>"/><br/>
							</p>

							<p>
								<input type="radio" name="twitter-analytics" id="use-dynamic-analytics"
								       value="2" <?php echo jd_checkCheckbox( 'use_dynamic_analytics' ) ?> />
								<label
									for="use-dynamic-analytics"><?php _e( "Use a dynamic identifier", 'wp-to-twitter' ); ?></label><br/>
								<label
									for="jd-dynamic-analytics"><?php _e( "What dynamic identifier would you like to use?", "wp-to-twitter" ); ?></label>
								<select name="jd-dynamic-analytics" id="jd-dynamic-analytics">
									<option
										value="post_category"<?php echo jd_checkSelect( 'jd_dynamic_analytics', 'post_category' ); ?>><?php _e( "Category", "wp-to-twitter" ); ?></option>
									<option
										value="post_ID"<?php echo jd_checkSelect( 'jd_dynamic_analytics', 'post_ID' ); ?>><?php _e( "Post ID", "wp-to-twitter" ); ?></option>
									<option
										value="post_title"<?php echo jd_checkSelect( 'jd_dynamic_analytics', 'post_title' ); ?>><?php _e( "Post Title", "wp-to-twitter" ); ?></option>
									<option
										value="post_author"<?php echo jd_checkSelect( 'jd_dynamic_analytics', 'post_author' ); ?>><?php _e( "Author", "wp-to-twitter" ); ?></option>
								</select><br/>
							</p>
							<p>
								<input type="radio" name="twitter-analytics" id="no-analytics"
								       value="3" <?php echo jd_checkCheckbox( 'no-analytics' ); ?> /> <label
									for="no-analytics"><?php _e( "No Analytics", 'wp-to-twitter' ); ?></label>
							</p>
						</fieldset>
						<fieldset id="indauthors">
							<legend><?php _e( 'Author Settings', 'wp-to-twitter' ); ?></legend>
							<p>
								<input type="checkbox" name="jd_individual_twitter_users" id="jd_individual_twitter_users"
								       value="1" <?php echo jd_checkCheckbox( 'jd_individual_twitter_users' ) ?> />
								<label
									for="jd_individual_twitter_users"><?php _e( "Authors have individual Twitter accounts", 'wp-to-twitter' ); ?></label>
							</p>

						</fieldset>
						<div class='wpt-permissions'>
							<fieldset>
								<legend><?php _e( 'Permissions', 'wp-to-twitter' ); ?></legend>
								<?php
								global $wp_roles;
								$roles     = $wp_roles->get_names();
								$caps      = array(
									'wpt_can_tweet'      => __( 'Can send Tweets', 'wp-to-twitter' ),
									'wpt_twitter_custom' => __( 'See Custom Tweet Field when creating a Post', 'wp-to-twitter' ),
									'wpt_twitter_switch' => __( 'Toggle the Tweet/Don\'t Tweet option', 'wp-to-twitter' ),
									'wpt_tweet_now'      => __( 'Can see Tweet Now button', 'wp-to-twitter' ),
									'wpt_twitter_oauth'  => __( 'Allow user to authenticate with Twitter', 'wp-to-twitter' )
								);
								$role_tabs = $role_container = '';
								foreach ( $roles as $role => $rolename ) {
									if ( $role == 'administrator' ) {
										continue;
									}
									$role_tabs .= "<li><a href='#wpt_" . sanitize_title( $role ) . "'>$rolename</a></li>\n";
									$role_container .= "<div class='wptab wpt_$role' id='wpt_" . sanitize_title( $role ) . "' aria-live='assertive'><fieldset id='wpt_$role' class='roles'><legend>$rolename</legend>";
									$role_container .= "<input type='hidden' value='none' name='wpt_caps[" . $role . "][none]' />
			<ul class='wpt-settings checkboxes'>";
									foreach ( $caps as $cap => $name ) {
										$role_container .= wpt_cap_checkbox( $role, $cap, $name );
									}
									$role_container .= "
			</ul></fieldset></div>\n";
								}
								echo "
		<ul class='tabs'>
			$role_tabs
		</ul>
		$role_container";
								?>
							</fieldset>
						</div>
	<?php
						$inputs          = '';
						$default_order   = array(
							'excerpt'  => 0,
							'title'    => 1,
							'date'     => 2,
							'category' => 3,
							'blogname' => 4,
							'author'   => 5,
							'account'  => 6,
							'tags'     => 7,
							'modified' => 8,
							'@'        => 9,
							'cat_desc' => 10
						);
						$preferred_order = get_option( 'wpt_truncation_order' );
						if ( ! $preferred_order ) {
							$preferred_order = array();
						}
						$preferred_order = array_merge( $default_order, $preferred_order );
						if ( is_array( $preferred_order ) ) {
							$default_order = $preferred_order;
						}
						asort( $default_order );
						foreach ( $default_order as $k => $v ) {
							$label = '<code>#' . $k . '#</code>';
							$inputs .= "<div class='wpt-truncate'><label for='$k-$v'>$label</label><br /><input type='number' size='3' value='$v' name='wpt_truncation_order[$k]' /></div> ";
						}
						?>
						<fieldset>
							<legend><?php _e( 'Template tag priority order', 'wp-to-twitter' ); ?></legend>
							<p><?php _e( 'The order in which items will be abbreviated or removed from your Tweet if the Tweet is too long to send to Twitter.', 'wp-to-twitter' ); ?> <?php _e( 'Tags with lower values will be modified first.', 'wp-to-twitter' ); ?></p>
							<p>
								<?php echo $inputs; ?>
							</p>
						</fieldset>						
						<fieldset>
							<legend><?php _e( 'Miscellaneous Settings', 'wp-to-twitter' ); ?></legend>
							<ul>
								<li>
									<input type="checkbox" name="wpt_permit_feed_styles" id="wpt_permit_feed_styles" value="1" <?php echo jd_checkCheckbox( 'wpt_permit_feed_styles' ) ?> />
									<label for="wpt_permit_feed_styles"><?php _e( "Disable Twitter Feed Stylesheet", 'wp-to-twitter' ); ?></label>
								</li>
								<li>
									<input type="checkbox" name="wp_debug_oauth" id="wp_debug_oauth" value="1" <?php echo jd_checkCheckbox( 'wp_debug_oauth' ) ?> /> <label for="wp_debug_oauth"><?php _e( "Get Debugging Data for OAuth Connection", 'wp-to-twitter' ); ?></label>
								</li>
								<li><input type="checkbox" name="jd_donations" id="jd_donations"
								           value="1" <?php echo jd_checkCheckbox( 'jd_donations' ) ?> /> <label
										for="jd_donations"><strong><?php _e( "I made a donation, so stop whinging at me, please.", 'wp-to-twitter' ); ?></strong></label>
								</li>
							</ul>
						</fieldset>
						<div>
							<input type="hidden" name="submit-type" value="advanced"/>
						</div>
						<input type="submit" name="submit"
						       value="<?php _e( "Save Advanced WP to Twitter Options", 'wp-to-twitter' ); ?>"
						       class="button-primary"/>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php }
	if ( $current == 'support' ) {
	?>
	<div class="postbox" id="get-support">
		<h3><span><?php _e( 'Get Plug-in Support', 'wp-to-twitter' ); ?></span></h3>

		<div class="inside">
				<?php if ( ! function_exists( 'wpt_pro_exists' ) ) { ?>
				<div class='wpt-support-me'>
					<p>
						<?php printf(
							__( 'Please, consider a <a href="%s">purchase</a> to support WP to Twitter!', 'wp-to-twitter' ), "http://www.wptweetspro.com/wp-tweets-pro" ); ?>
					</p>
				</div>	
				<?php } ?>
			<?php wpt_get_support_form(); ?>
		</div>
	</div>
	<?php } ?>
	</div>
	</div>
	<?php wpt_sidebar(); ?>
	</div>
	</div>
	<?php
}

function wpt_sidebar() {
	$context = ( ! function_exists( 'wpt_pro_exists' ) ) ? 'free' : 'premium';
	?>
	<div class="postbox-container jcd-narrow">
	<div class="metabox-holder">
		<div class="ui-sortable meta-box-sortables<?php echo ' ' . $context; ?>">
			<div class="postbox">
				<?php if ( $context == 'free' ) { ?>
					<h3>
						<span><strong><?php _e( 'Support WP to Twitter', 'wp-to-twitter' ); ?></strong></span></h3>
				<?php } else { ?>
					<h3>
						<span><strong><?php _e( 'WP to Twitter Support', 'wp-to-twitter' ); ?></strong></span></h3>
				<?php } ?>
				<div class="inside resources">
					<?php if ( get_option( 'jd_donations' ) != 1 && ! function_exists( 'wpt_pro_exists' ) ) { ?>
						<p class='cta'><?php _e( '<a href="http://www.wptweetspro.com/wp-tweets-pro">Get WP Tweets Pro</a>', 'wp-to-twitter' ); ?></p>
					<?php } ?>
					<p>
						<a href="https://twitter.com/intent/follow?screen_name=joedolson" class="twitter-follow-button"
						   data-size="small" data-related="joedolson">Follow @joedolson</a>
						<script>!function (d, s, id) {
								var js, fjs = d.getElementsByTagName(s)[0];
								if (!d.getElementById(id)) {
									js = d.createElement(s);
									js.id = id;
									js.src = "https://platform.twitter.com/widgets.js";
									fjs.parentNode.insertBefore(js, fjs);
								}
							}(document, "script", "twitter-wjs");</script>
					</p>
					<?php
					if ( $context == 'premium' ) {
						$support_url = admin_url( 'admin.php?page=wp-tweets-pro' );
						$support =	'<a href="' . esc_url( add_query_arg( 'tab', 'support', $support_url ) ) . '#get-support">' . __( "Get Support", 'wp-to-twitter' ) . '</a> &bull;';
					} else {
						$support_url = false;
						$support = '';
					} 
					echo $support;
					?>
					<a href="https://www.joedolson.com/wp-content/uploads/wp-tweets-pro-users-guide-current.pdf"><?php _e( 'Read the Manual', 'wp-to-twitter' ); ?></a>
				</div>
			</div>
		</div>
		
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h3><?php _e( 'Twitter Time Check', 'wp-to-twitter' ); ?></h3>

				<div class="inside server">
						<?php wpt_do_server_check(); ?>
				</div>
			</div>
		</div>
		
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h3><?php _e( 'Test WP to Twitter', 'wp-to-twitter' ); ?></h3>
				
				<div class="inside test">
				<p>
					<?php _e( 'Check whether WP to Twitter is setup correctly for Twitter and your URL Shortener. The test sends a status update to Twitter and shortens a URL using your chosen shortener.', 'wp-to-twitter' ); ?>
				</p>
				<form method="post" action="">
					<fieldset>
						<input type="hidden" name="submit-type" value="check-support"/>
						<?php $nonce = wp_nonce_field( 'wp-to-twitter-nonce', '_wpnonce', true, false ) . wp_referer_field( false );
						echo "<div>$nonce</div>"; ?>
						<p>
							<input type="submit" name="submit" value="<?php _e( 'Test WP to Twitter', 'wp-to-twitter' ); ?>" class="button-primary" />
						</p>
					</fieldset>
				</form>				
				</div>
			</div>
		</div>

		<?php if ( get_option( 'wpt_rate_limiting' ) == 1 ) { ?>
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h3><?php _e( 'Monitor Rate Limiting', 'wp-to-twitter' ); ?></h3>

				<div class="inside server">		
					<?php echo wpt_view_rate_limits(); ?>
				</div>
			</div>
		</div>	
		<?php } ?>
	</div>
<?php
}

function wpt_do_server_check( $test = false ) {
	$wpt_server_string = get_option( 'wpt_server_string' );
	if ( !$wpt_server_string || isset( $_GET['refresh_wpt_server_string'] ) || $test == true ) {
		$server_time = date( DATE_COOKIE );
		$response    = wp_remote_get( "https://twitter.com/", array( 'timeout' => 30, 'redirection' => 1 ) );
		
		if ( is_wp_error( $response ) ) {
			$warning = '';
			$error   = $response->errors;
			if ( is_array( $error ) ) {
				$warning = "<ul>";
				foreach ( $error as $k => $e ) {
					foreach ( $e as $v ) {
						$warning .= "<li>" . $v . "</li>";
					}
				}
				$warning .= "</ul>";
			}
			$errors = "<li>" . $ssl . $warning . "</li>";
		} else {
			$date   = date( DATE_COOKIE, strtotime( $response['headers']['date'] ) );
			$errors = '';
		}

		if ( ! is_wp_error( $response ) ) {
			if ( abs( strtotime( $server_time ) - strtotime( $response['headers']['date'] ) ) > 300 ) {
				$diff = __( 'Your time stamps are more than 5 minutes apart. Your server could lose its connection with Twitter.', 'wp-to-twitter' );
			} else {
				$diff = __( 'Your time stamp matches the Twitter server time', 'wp-to-twitter' );
			}
			$diff = "<li>$diff</li>";
		} else {
			$diff = "<li>" . __( 'WP to Twitter could not contact Twitter\'s remote server.', 'wp-to-twitter' ) . "</li>";
		}

		$timezone = '<li>' . __( 'Your server timezone:', 'wp-to-twitter' ) . ' ' . date_default_timezone_get() . '</li>';

		$search = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$replace = array( 'Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun' );
		
		$server_time = str_replace( $search, $replace, $server_time );
		$date = str_replace( $search, $replace, $date );
		
		$wpt_server_string =
			"<ul>
				<li>" . __( 'Your server time:', 'wp-to-twitter' ) . '<br /><code>' . $server_time . '</code>' . "</li>" . 
				"<li>" . __( 'Twitter\'s server time: ', 'wp-to-twitter' ) . '<br /><code>' . $date . '</code>' . "</li>
				$timezone
				$diff
				$errors
			</ul>";
		update_option( 'wpt_server_string', $wpt_server_string );
	}
	echo $wpt_server_string;
	$admin_url = admin_url( 'admin.php?page=wp-tweets-pro&amp;refresh_wpt_server_string=true' );
	echo "<p><a href='" . $admin_url . "'>" . __( 'Test again', 'wp-to-twitter' ) . "</a></p>";
}