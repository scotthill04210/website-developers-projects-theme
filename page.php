<?php
/**
 * Page template.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'page' );

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
