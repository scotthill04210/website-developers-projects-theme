<?php
/**
 * Main posts template.
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
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<header class="lidev-page-header">
				<h1><?php single_post_title(); ?></h1>
			</header>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/loop', 'cards' ); ?>
	</div>
</main>

<?php
get_footer();
