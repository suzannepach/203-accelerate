<?php
/**
 * The template for displaying the footer
 *
 * Contains footer content and the closing of the #main and #page div elements.
 *
 * @package WordPress
 * @subpackage Accelerate Marketing
 * @since Accelerate Marketing 2.0
 */
?>

		</div><!-- #main -->

		<footer id="colophon" class="site-footer clearfix" role="contentinfo">
			<div class="site-info">
				<!-- I have moved the social menu above the site description so it looks better on mobile.
				On bigger screen I have to use flex styles to make sure the icons are positioned om teh right not on the left. -->
				<nav class="social-media-navigation" role="navigation">
					<?php wp_nav_menu( array( 'theme_location' => 'social-media', 'menu_class' => 'social-media-menu' ) ); ?>
				</nav>
				<div class="site-description">
					<p><?php bloginfo('description'); ?></p>
					<p>&copy; <?php bloginfo('title'); ?>, LLC
				</div>
			</div><!-- .site-info -->
		</footer><!-- #colophon -->

	</div><!-- #page -->

	<?php wp_footer(); ?>
</body>
</html>
