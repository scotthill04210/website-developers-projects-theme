<?php
/**
 * Card loop shared by the blog, archives, and search.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( have_posts() ) :
	?>
	<div class="lidev-cards">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'card' );
		endwhile;
		?>
	</div>
	<?php
	lidev_pagination();
else :
	get_template_part( 'template-parts/content', 'none' );
endif;
