<?php
/**
 * Archive template.
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
			<h1><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		</header>

		<?php get_template_part( 'template-parts/loop', 'cards' ); ?>
	</div>
</main>

<?php
get_footer();
