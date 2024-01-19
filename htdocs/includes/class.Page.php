<?php

class Page
{
    public function process()
    {
        Page::assemble();
        Page::display();
    }

    public function assemble()
    {

        # We use at least two templates -- the standard ("new") template, and the "help" template.
        if (empty($this->template) || ($this->template == 'default')) {
            $this->template = 'new';
        }

        if (MEMCACHED_SERVER != '') {
            # Connect to Memcached.
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);

            # Try to retrieve this template from Memcached.
            $result = $mc->get('template-' . $this->template);
            if ($mc->getResultCode() == 0) {
                $page = unserialize($result);
            }
        }

        # The template isn't in Memcached, so get it from the filesystem.
        if (!isset($page)) {
            # Get the contents of the template.
            ob_start();
            include __DIR__ . '/templates/' . $this->template . '.inc.php';
            $page = ob_get_contents();

            # Cache this template, with a 24-hour expiration date.
            if (MEMCACHED_SERVER != '') {
                $mc->set('template-' . $this->template, serialize($page), (60 * 60 * 24));
            }
        }

        # Establish the full browser title.
        if (empty($this->page_title)) {
            $browser_title = 'Richmond Sunlight » Tracking the Virginia General Assembly';
        } else {
            if (isset($this->browser_title)) {
                $this->browser_title = 'Richmond Sunlight » ' . $this->browser_title;
            } else {
                $this->browser_title = 'Richmond Sunlight » ' . $this->page_title;
            }
            # If a right angle quote is used in the title, show only the
            # content to the left of it.
            $end_bit = mb_stristr($this->page_title, '»');
            if ($end_bit !== false) {
                $this->page_title = str_replace('» ', '', $end_bit);
            }
        }

        # Create the account header.
        if (isset($_SESSION['registered']) && ($_SESSION['registered'] == 'y')) {
            $account = '<a href="/account/">Profile</a> | <a href="/account/logout/">Log Out</a>';
        } else {
            $account = '<a href="/account/register/">Register</a> | <a href="/account/login/">Log In</a>';
        }

        # Mark the body tag with the ID corresponding to the current site section. (This is used by
        # the CSS to highlight the current section in the menu.)
        if (!empty($this->site_section)) {
            if (!isset($this->body_tag)) {
                $this->body_tag = '';
            }
            $this->body_tag .= ' id="body-' . $this->site_section . '"';
        }

        /*
         * Inject a Javascript array of bill portfolio IDs into the HTML.
         */
        if (isset($_SESSION['portfolios'])) {
            $portfolio_js = '<script>var portfolios = [];';
            foreach ($_SESSION['portfolios'] as $portfolio) {
                $portfolio_js .= 'portfolios.push("' . $portfolio['hash'] . '");';
            }
            $portfolio_js .= '</script>';

            # Note that we retain the placeholder tag, because the actual replacement comes later.
            $page = str_replace('%html_head%', '%html_head% ' . $portfolio_js, $page);
        }

        /*
         * By default, the page header says that the legislature is not in sesssion. But if they
         * are in session, modify the header to say so.
         */
        if (IN_SESSION == true) {
            $page = str_replace('Assembly is not in session', 'Assembly is now in session', $page);
        }

        # Step through and replace each variable in the template with the
        # contents of the page.
        $page = str_replace('%browser_title%', $this->browser_title, $page);
        $page = str_replace('%page_title%', $this->page_title, $page);
        $page = str_replace('%page_body%', $this->page_body, $page);
        if (!isset($this->page_sidebar) || empty($this->page_sidebar)) {
            if (empty($this->html_head)) {
                $this->html_head = '';
            }
            $this->html_head .= "\r\t" . '<style>' . "\r\t\t" . '#sidebar { display: none; }'
                . "\r\t\t" . '#content { width: 62em; }'
                . "\r\t" . '</style>';
            $page = str_replace('%page_sidebar%', '', $page);
        } else {
            $page = str_replace('%page_sidebar%', $this->page_sidebar, $page);
        }
        $page = str_replace('%html_head%', $this->html_head, $page);
        $page = str_replace('%account%', $account, $page);
        if (isset($this->body_tag)) {
            $page = str_replace('%body_tag%', $this->body_tag, $page);
        } else {
            $page = str_replace('%body_tag%', '', $page);
        }

        # See if we have any recommended bills and, if so, insert a promo for them.
        $user = new User();
        $bills = $user->recommended_bills();
        if ($bills != false) {
            $recommended_bills = 'We have <a href="/recommended-bills/">' . count($bills)
                . ' bill recommendations</a> for you.';
        } else {
            $user->get();
            if (empty($user->data['house_district_id']) || empty($user->data['senate_district_id'])) {
                $recommended_bills = 'Who’s your legislator? <a href="/your-legislators/">Look it up now!</a>';
            } else {
                $recommended_bills = '';
            }
        }
        $page = str_replace('%recommended_bills%', $recommended_bills, $page);

        # Make this variable accessible to the whole class.
        $this->output = $page;
        unset($page);

        return true;
    }

    # Send the contents of the page to the browser.
    public function display()
    {

        # Send the completed page to the browser by clearing the buffer and echoing
        # its previously-saved contents.
        ob_end_clean();

        echo $this->output;

        return true;
    }
}
