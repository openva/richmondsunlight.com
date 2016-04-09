<?php get_header(); ?>
	
	<?php ob_start(); ?>

	<div id="content">

	<?php if (have_posts()) : ?>

		<?php while (have_posts()) : the_post(); ?>

			<div class="post" id="post-<?php the_ID(); ?>">
				<h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
				<small><?php the_time('F jS, Y') ?> by <?php the_author() ?></small>

				<div class="entry">
					<?php the_content('Read the rest of this entry &raquo;'); ?>
				</div>

				<!--<p class="postmetadata"><?php the_tags('Tags: ', ', ', '<br />'); ?> Posted in <?php the_category(', ') ?> | <?php edit_post_link('Edit', '', ' | '); ?>  <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>-->
			</div>

		<?php endwhile; ?>

		<div class="navigation">
			<div class="alignleft"><?php next_posts_link('&laquo; Older Entries') ?></div>
			<div class="alignright"><?php previous_posts_link('Newer Entries &raquo;') ?></div>
		</div>

	<?php else : ?>

		<h2 class="center">Not Found</h2>
		<p class="center">Sorry, but you are looking for something that isn't here.</p>
		<?php include (TEMPLATEPATH . "/searchform.php"); ?>

	<?php endif; ?>

	</div>
	

<?php
	$page_body = ob_get_contents();
	ob_end_clean();
?>

<?php
	ob_start();
	get_sidebar();
	$GLOBALS['page_sidebar'] = ob_get_contents();
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