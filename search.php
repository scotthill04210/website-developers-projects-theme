<?php
/**
 * Search results template.
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
		<header class="lidev-page-header">
			<h1>
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Search results for "%s"', 'li-dev-theme' ),
					esc_html( get_search_query( false ) )
				);
				?>
			</h1>
			<?php get_search_form(); ?>
		</header>

		<?php get_template_part( 'template-parts/loop', 'cards' ); ?>
	</div>
</main>

<?php
get_footer();
