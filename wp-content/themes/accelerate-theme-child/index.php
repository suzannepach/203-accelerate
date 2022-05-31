<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme and one
 * of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query,
 * e.g., it puts together the home page when no home.php file exists.
 *
 * @link http://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Accelerate Marketing
 * @since Accelerate Marketing 2.0
 */

get_header(); ?>
	<!-- BLOG PAGE -->
	<section class="index-page">
		<div class="site-content">
			<div class="main-content">
				<?php if ( have_posts() ): ?>
					<?php while ( have_posts() ) : the_post(); ?>
						<?php get_template_part('content-blog', get_post_format()); ?>
					<?php endwhile; ?>
				<?php endif; ?>
			</div>

			<!-- On mobile I want the nav section to show above the sidebar. To achieve this I 
			have added an extra nav section (same as the one below only this one with class nav-mobile 
			and the other one with class nav-desktop). I have added css so this nav only 
			shows on mobile and the other only on desktop. (All of this is probably way easier with css grid!)-->
			<nav id="navigation" class="container nav-mobile">
				<div class="left"><?php previous_posts_link('&larr; <span>Newer Posts</span>'); ?></div>
				<div class="pagination">
					<?php $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
						echo 'Page '.$paged.' of '.$wp_query->max_num_pages;
					?>
				</div>
				<div class="right"><?php next_posts_link('<span>Older Posts</span> &rarr;'); ?></div>
			</nav>

			<?php get_sidebar(); ?>

		</div>
	</section>

	<!-- I have added the class nav-desktop, see explaination in comment above.
	I have also moved the older post link to the right and the newer post link to 
	the left. This seems to make more sense, because newer post are shown first. -->
	<nav id="navigation" class="container nav-desktop">
		<div class="left"><?php previous_posts_link('&larr; <span>Newer Posts</span>'); ?></div>
		<div class="pagination">
			<?php $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
				echo 'Page '.$paged.' of '.$wp_query->max_num_pages;
			?>
		</div>
		<div class="right"><?php next_posts_link('<span>Older Posts</span> &rarr;'); ?></div>
	</nav>

<?php get_footer();
