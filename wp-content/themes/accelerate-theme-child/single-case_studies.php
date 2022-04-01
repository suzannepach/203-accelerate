<?php
/**
 * The template for displaying a single case study
 *
 * @package WordPress
 * @subpackage Accelerate Marketing
 * @since Accelerate Marketing 2.0
 */

get_header(); ?>

	<!-- The case studies form had custom fields added using the plugin Advanced Cusatom Fields. The code in the loop here relates to those custom fields (see lesson 8 step 5). -->
	<div id="primary" class="site-content">
		<div class="main-content" role="main">
			<?php while ( have_posts() ) : the_post(); 
				$size = "full";
				$services = get_field('services');
				$client = get_field('client');
				$site_link = get_field('site_link');
				$image_1 = get_field('image_1');
				$image_2 = get_field('image_2');
				$image_3 = get_field('image_3');
			?>
				<article class="case-study">
					<aside>
						<h2><?php the_title(); ?></h2> 
						<h5><?php echo $services; ?></h5>
						<h6><span><?php echo $client; ?></span></h6>

						<?php the_content(); ?>

						<p class="read-more-link"><a href="<?php echo $link; ?>">Visit Live Site ›</a></p>
					</aside>
					<div class="case-study-images">
						<?php if($image_1) { 
							echo wp_get_attachment_image( $image_1, $size );
						} ?>
						<?php if($image_2) {
							echo wp_get_attachment_image( $image_2, $size );
						} ?>
						<?php if($image_3) {
							echo wp_get_attachment_image( $image_3, $size );
						} ?>
					</div>
				</article>	

			<?php endwhile; // end of the loop. ?>
		</div><!-- .main-content -->

	</div><!-- #primary -->

<?php get_footer(); ?>
