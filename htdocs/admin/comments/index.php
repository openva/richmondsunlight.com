<?php

###
# Edit Comments
#
# PURPOSE
# Provides administrative comment-editing functions.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once($_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# LOCALIZE VARIABLES
if (isset($_REQUEST['op'])) $op = $_REQUEST['op'];
if (isset($_REQUEST['id'])) $id = $_REQUEST['id'];

# PAGE METADATA
$page_title = 'Edit Comments';
$site_section = 'comments';

# PAGE CONTENT
if (!empty($op))
{
	if (empty($id)) die('No ID found.');

	/*
	 * We're going to have the Memcached cache of comments for the bill being affected here, so
	 * let's get that out of the way at the outset.
	 */
	$mc = new Memcached();
	$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
	$sql = 'SELECT bill_id AS id
			FROM comments
			WHERE id = ' . $id;
	$result = mysql_query($sql);
	$bill = mysql_fetch_array($result);
	$mc->delete('comments-' . $bill['id']);

	if ($op == 'spam')
	{
		$sql = 'UPDATE comments
				SET status="spam"
				WHERE id='.$id;
		$result = mysql_query($sql);
		if ($result === TRUE)
		{
			$page_body = '<p>Comment marked as spam.</p>';
		}
	}

	elseif ($op == 'delete')
	{
		$sql = 'UPDATE comments
				SET status="deleted"
				WHERE id='.$id;
		$result = mysql_query($sql);
		if ($result === TRUE)
		{
			$page_body = '<p>Comment marked as deleted.</p>';
		}
	}

	elseif ($op == 'pick')
	{
		$sql = 'UPDATE comments
				SET editors_pick="y"
				WHERE id='.$id;
		$result = mysql_query($sql);
		if ($result === TRUE)
		{
			$page_body = '<p>Comment marked as an editor’s pick.</p>';
		}
	}

	elseif ($op == 'edit')
	{
		$page_body = '<p>This function doesn\'t exist yet &mdash it\'s only there
			to remind me to create it.</p>';
	}
}

else
{
	# Select the last 20 comments.
	$sql = 'SELECT comments.id, bills.number AS bill_number, bills.catch_line, comments.name,
			comments.email, comments.url, comments.ip, comments.comment, comments.status,
			DATE_FORMAT(comments.date_created, "%m/%d/%y, %h:%i:%s") AS date
			FROM comments
			LEFT JOIN bills
				ON comments.bill_id = bills.id
			ORDER BY comments.date_created DESC
			LIMIT 30';
	$result = mysql_query($sql);
	$page_body = '<div id="comments">';
	while ($comment = mysql_fetch_array($result))
	{
		$page_body .= '
			<div class="comment"'.(($comment['status'] == 'deleted') ? ' style="color: #999;"' : '').'>
				<h2><a href="/bill/'.SESSION_YEAR.'/'.strtolower($comment['bill_number']).'/">'.$comment['bill_number'].'</a>:
					'.$comment['catch_line'].'</h2>
				<cite>'.(!empty($comment['url']) ? '<a href="'.$comment['url'].'">' : '').
				$comment['name'].
				(!empty($comment['url']) ? '</a>' : ''). '
				(<a href="mailto:'.$comment['email'].'">'.$comment['email'].'</a>,
				<a href="http://ws.arin.net/whois/?queryinput='.$comment['ip'].'">'.$comment['ip'].'</a>)</cite> <strong>writes</strong>:<br />'.
				nl2p($comment['comment']).'
				<div class="metadata">
					<span class="date">'.$comment['date'].'</span>
					<a href="/bill/'.SESSION_YEAR.'/'.strtolower($comment['bill_number']).'/#comments">#</a>
				</div>
				[<a href="/admin/comments?op=delete&amp;id='.$comment['id'].'">delete</a>]
				[<a href="/admin/comments?op=spam&amp;id='.$comment['id'].'">spam</a>]
				[<a href="/admin/comments?op=pick&amp;id='.$comment['id'].'">editor’s pick</a>]
			</div>';
	}
	$page_body .= '</div>';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
