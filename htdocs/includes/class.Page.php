<?php

class Page
{
	
	function process()
	{
		Page::assemble();
		Page::display();
	}
	
	function assemble()
	{
		
		# We use at least two templates -- the standard ("new") template, and the "help" template.
		if (empty($this->template) || ($this->template == 'default'))
		{
			$this->template = 'new';
		}
		
		# Connect to Memcached.
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		
		# Try to retrieve this template from Memcached.
		$result = $mc->get('template-' . $this->template);
		if ($mc->getResultCode() == 0)
		{
			$page = unserialize($result);
		}
		
		# The template isn't in Memcached, so get it from the filesystem.
		else
		{	

			# Get the contents of the template.
			ob_start();
			include dirname(__FILE__) . '/templates/' . $this->template . '.inc.php';
			$page = ob_get_contents();
			
			# Cache this template, with no expiration date.
			$mc->set('template-' . $this->template, serialize($page));
			
		}
		
		# Establish the full browser title.
		if (empty($this->page_title))
		{
			$browser_title = 'Richmond Sunlight » Tracking the Virginia General Assembly';
		}
		else
		{
			if (isset($this->browser_title))
			{
				$this->browser_title = 'Richmond Sunlight » '.$this->browser_title;
			}
			else
			{
				$this->browser_title = 'Richmond Sunlight » '.$this->page_title;
			}
			# If a right angle quote is used in the title, show only the
			# content to the left of it.
			$end_bit = stristr($this->page_title, '»');
			if ($end_bit !== FALSE)
			{
				$this->page_title = str_replace('» ', '', $end_bit);
			}
		}
		
		# Create the account header.
		if ( isset($_SESSION['registered']) && ($_SESSION['registered'] == 'y') )
		{
			$account = '<a href="/account/">Profile</a> | <a href="/account/logout/">Log Out</a>';
		}
		else
		{
			$account = '<a href="/account/register/">Register</a> | <a href="/account/login/">Log In</a>';
		}
		
		# Mark the body tag with the ID corresponding to the current site section. (This is used by
		# the CSS to highlight the current section in the menu.)
		if (!empty($this->site_section))
		{
			if (!isset($this->body_tag))
			{
				$this->body_tag = '';
			}
			$this->body_tag .= ' id="body-'.$this->site_section.'"';
		}
		
		# Step through and replace each variable in the template with the
		# contents of the page.
		$page = str_replace('%browser_title%', $this->browser_title, $page);
		$page = str_replace('%page_title%', $this->page_title, $page);
		$page = str_replace('%page_body%', $this->page_body, $page);
		if (!isset($this->page_sidebar) || empty($this->page_sidebar))
		{
			if (empty($this->html_head))
			{
				$this->html_head = '';
			}
			$this->html_head .= "\r\t".'<style type="text/css">'."\r\t\t".'#sidebar { display: none; }'
				."\r\t\t".'#content { width: 62em; }'
				."\r\t".'</style>';
			$page = str_replace('%page_sidebar%', '', $page);
		}
		else
		{
			$page = str_replace('%page_sidebar%', $this->page_sidebar, $page);
		}
		$page = str_replace('%html_head%', $this->html_head, $page);
		$page = str_replace('%account%', $account, $page);
		if (isset($this->body_tag))
		{
			$page = str_replace('%body_tag%', $this->body_tag, $page);
		}

		# See if we have any recommended bills and, if so, insert a promo for them.
		
		$user = new User();
		$bills = $user->recommended_bills();
		if ($bills != FALSE)
		{
			$recommended_bills = 'We have <a href="/recommended-bills/">'.count($bills).' bill recommendations</a> for you.';
		}
		else
		{
		
			$user->get();
			if ( empty($user->data['house_district_id']) || empty($user->data['senate_district_id']) )
			{
				$recommended_bills = 'Who’s your legislator? <a href="/your-legislators/">Look it up now!</a>';
			}
			else
			{
				$recommended_Bills = '';
			}
			
		}
		$page = str_replace('%recommended_bills%', $recommended_bills, $page);
		
		# Make this variable accessible to the whole class.
		$this->output = $page;
		unset($page);
		
		return true;
		
	}
	
	# Send the contents of the page to the browser.
	function display()
	{
		
		# Send the completed page to the browser by clearing the buffer and echoing
		# its previously-saved contents.
		ob_end_clean();
		
		echo $this->output;
		
		return TRUE;
		
	}
}

?>
