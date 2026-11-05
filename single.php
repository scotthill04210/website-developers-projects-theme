<?php
/**
 * Single post template.
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
			get_template_part( 'template-parts/content', 'single' );

			the_post_navigation(
				array(
					'prev_text' => '<span class="lidev-meta">' . esc_html__( 'Previous', 'li-dev-theme' ) . '</span><span>%title</span>',
					'next_text' => '<span class="lidev-meta">' . esc_html__( 'Next', 'li-dev-theme' ) . '</span><span>%title</span>',
				)
			);

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
