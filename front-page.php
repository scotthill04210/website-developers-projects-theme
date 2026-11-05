<?php
/**
 * Front page.
 *
 * Renders the static front page, or the latest posts when that is the homepage.
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
		<?php if ( is_page() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'page' );

				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
			endwhile;
			?>
		<?php else : ?>
			<header class="lidev-page-header">
				<h1><?php bloginfo( 'name' ); ?></h1>
				<?php if ( get_bloginfo( 'description' ) ) : ?>
					<p class="lidev-lede"><?php bloginfo( 'description' ); ?></p>
				<?php endif; ?>
			</header>
			<?php get_template_part( 'template-parts/loop', 'cards' ); ?>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
