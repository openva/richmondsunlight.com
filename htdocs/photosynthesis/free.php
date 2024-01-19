<?php

    ###
    # About Photosynthesis Free
    #
    # PURPOSE
    # Provides a pitch to sign up for Photosynthesis (free version).
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once '../includes/settings.inc.php';
    include_once '../includes/functions.inc.php';

    # PAGE METADATA
    $page_title = 'Photosynthesis';
    $site_section = 'photosynthesis';

    # PAGE CONTENT
    $page_body = <<<EOD
		<p><em>Create your own list of bills that interest you </em></p>

		<div class="left_side">
			<h2>The Tools You Need</h2>
			<p>E-mail notifications. RSS feeds. Smart portfolios. Web dashboard. Tagging. Community
			interface. <strong>Everything you need</strong> and everything you didn&rsquo;t know you
			needed.</p>

			<h2>Legislation Finds You</h2>
			<p>Provide the criteria for the sort of bills that you&rsquo;re
			interested in and they&rsquo;ll be queued for you as
			they&rsquo;re filed. <strong>It&rsquo;s that easy</strong>.
			No more hunting down voting records or sneaky bills.</p>

		</div>
		<div class="right_side">
			<h2>The Way You Work</h2>
			<p>Store bills in unlimited portfolios. Access from anywhere. Pipe legislative
			data to applications or your intranet via XML or RSS. <strong>Flexible and
			open.</strong></p>

			<h2>Crowdsource It</h2>
			<p>Create a shared portfolio of bills and your organization&rsquo;s position papers will
			be put in front of the grassroots on the bill&rsquo;s public page. Share video,
			audio, images, or link back to resources on your own website. <strong>Work
			<em>with</em> citizen activists</strong>.</p>
		</div>

		<h3>Features</h3>
		<ul style="list-style-type: disc; margin-left: 1.5em; margin-bottom: 1em;">

			<li><strong>Smart portfolios.</strong> Describe the sort of bills you&rsquo;re
			interested in &mdash; <em>bills in Courts of Justice originally introduced in the House
			tagged &ldquo;health&rdquo;</em> or <em>every bill containing the phrase &ldquo;eminent
			domain&rdquo;</em> &mdash; and they'll be added to your portfolio immediately and
			continuously.</li>

			<li><strong>E-mailed legislative updates.</strong> Receive a daily
			e-mail listing your bills that have been acted on, keeping you up to date on
			actions were taken.</li>

			<li><strong>Unlimited portfolios.</strong> Whether with smart portfolios or
			by building them by selectively culling through legisation, you can track
			as many bills in as many individual portfolios as you want.</li>

			<li><strong>RSS feeds.</strong> Every one of your portfolios has its own RSS feed,
			allowing you to track just the bills that you want on the devices that you want. Keep up
			with portfolios of crucial legislation on your iPhone, but all of them on your desktop.
			You can share those RSS feeds with as many people as you want, whether coworkers or your
			organization&rsquo;s members, keeping them <em>all</em> up to date.</li>

			<li><strong>Public portfolios.</strong> You can keep your legislative agenda secret, or
			you can selectively share portfolios publicly, giving you your own webpage where you
			can promote the bills that are important to you and provide specific calls to action
			to readers.</li>

			<li><strong>Integrate your position papers with Richmond Sunlight.</strong> Your
			comments on bills in your public portfolios will appear right on those bills&rsquo;
			public pages, labelled as your organization&rsquo;s stance on that legislation.
			Include images, audio, video, or links to resources on your own website to bolster
			your position.</li>

		</ul>
EOD;

    $page_sidebar = <<<EOD
		<h3>Pricing</h3>
		<div class="box">
			<p>The price structure is simple and competitive:</p>

			<ul style="list-style-type: disc; margin-left: 1.5em; margin-bottom: 1em;">
				<li>$500/year/organization</li>
				<li>$25/year/additional user</li>
			</ul>

			<p>Registered 501(c) non-profits are charged half-price.</p>

			<p>For example:</p>

			<ul style="list-style-type: disc; margin-left: 1.5em;">
				<li>Lobbyist: $500</li>
				<li>Non-profit lobbyist: $250</li>
				<li>Business with access for 11: $1,000</li>
				<li>Non-profit with access for 11: $500</li>
			</ul>
		</div>

		<h3>Try It</h3>
		<div class="box">
			<p>You can sign up for <a href="/photosynthesis/">a free Photosynthesis account</a> now
			to use the features that are available to the general public, which provides a great
			sense for how the system works. Or, <a href="/contact/">contact us</a> and we can
			arrange for </p>
		</div>

		<h3>Buy It</h3>
		<div class="box">
			<p style="font-size: 1.2em; font-weight: bold;">Sign up now!</p>
			<a href="/contact/">Contact the Virginia Interfaith Center for Public Policy</a> and
			let us know that you&rsquo;d like to get started. We&rsquo;ll have you up and
			running in no time.</p>
		</div>
EOD;

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
