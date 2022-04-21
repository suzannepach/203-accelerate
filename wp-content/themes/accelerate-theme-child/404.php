<?php
/**
 * The template for displaying the 404 page.
 
 * @package WordPress
 * @subpackage Accelerate Marketing
 * @since Accelerate Marketing 2.0
 */

get_header(); ?>

<div id="primary" class="hero-404">
	<div class="main-content" role="main">
		<div class="content-404">
				<h1>Lost?</h1>
				<div class="take-home">
					<h3>No worries.<br>
						We will take you</h3>
					<a href="<?php echo home_url(); ?>"><img src="wp-content\themes\accelerate-theme-child\img\mini-taxi-home.png" alt='Mini taxi with "Home" written on top.'></a>
				</div>
		</div>
	</div><!-- .main-content -->
</div><!-- #primary -->

<?php get_footer(); ?>
