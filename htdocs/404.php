<?php

    ###
    # 404 Error
    #
    # PURPOSE
    # A straight-up 404 page.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once 'settings.inc.php';
    include_once 'vendor/autoload.php';

    # PAGE METADATA
    $page_title = 'Page Not Found';
    $site_section = '';

    # PAGE CONTENT
    $page_body = <<<EOD
		<p>We're sorry, the page you've attempted to load cannot be found.</p>

		<h2>Cause</h2>
		<p>If you've followed a link to Richmond Sunlight from another website, the
		link may just be wrong.  If you've followed a link from within our website,
		then <em>we</em> are wrong.  In either case, the fact that you've had this
		trouble has been noted, and we'll investigate how we can keep this from
		happening again.</p>

		<h2>Solutions</h2>
		<ul class="classic">
			<li><a href="/">Start back at the home page</a> and browse your way to the
			information that you're looking for.</li>
			<li><a href="/search/">Search</a> the site for the information.  The site's
			search system only finds bills, so if you're looking for something else then
			it probably won't do you much good.</li>
			<li><a href="/contact/">E-mail us and tell us the problem</a>. Please
			include the URL of the page you were trying to access, the URL of the
			page that provide you the inaccurate link, and any other information that
			you think might help us track down and solve the problem for you.</li>
		</ul>

EOD;

    # OUTPUT THE PAGE
    /*display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
        '&site_section='.urlencode($site_section));*/

    $page = new Page;
    $page->page_title = $page_title;
    $page->page_body = $page_body;
    $page->page_sidebar = $page_sidebar;
    $page->site_section = $site_section;
    $page->process();
