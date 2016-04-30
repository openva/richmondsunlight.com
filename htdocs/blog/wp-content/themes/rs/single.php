<?php get_header(); ?>
	
	<?php ob_start(); ?>

	<div id="content">

	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

		<div class="post" id="post-<?php the_ID(); ?>">

			<div class="entry">
				<small><?php the_time('F jS, Y') ?> by <?php the_author() ?></small>
				<?php the_content('<p class="serif">Read the rest of this entry &raquo;</p>'); ?>

				<?php wp_link_pages(array('before' => '<p><strong>Pages:</strong> ', 'after' => '</p>', 'next_or_number' => 'number')); ?>

				<p class="postmetadata alt">
					<?php the_author_description() ?>
					<?php edit_post_link('<small>Edit this entry.</small>','',''); ?>
				</p>

			</div>
		</div>

	<?php comments_template(); ?>

	<?php endwhile; else: ?>

		<p>Sorry, no posts matched your criteria.</p>

<?php endif; ?>

	</div>

<?php
	$page_body = ob_get_contents();
	ob_end_clean();
?>

<?php get_footer(); ?>

<?php
	
	# OUTPUT THE PAGE
	/*display_page('page_title='.urlencode($GLOBALS['page_title']).'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($GLOBALS['page_sidebar']).
		'&site_section='.urlencode($GLOBALS['site_section']).'&html_head='.urlencode($GLOBALS['html_head']));*/
	
	$page = new Page;
	$page->page_title = $GLOBALS['page_title'];
	$page->page_body = $page_body;
	$page->page_sidebar = $GLOBALS['page_sidebar'];
	$page->site_section = $GLOBALS['site_section'];
	$page->html_head = $GLOBALS['html_head'];
	$page->process();
?>