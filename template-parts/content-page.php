<?php
/**
 * Page content.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article <?php post_class( 'lidev-entry' ); ?> id="post-<?php the_ID(); ?>">
	<header class="lidev-entry__header lidev-page-header">
		<h1 class="lidev-entry__title"><?php the_title(); ?></h1>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="lidev-entry__media">
			<?php the_post_thumbnail( 'large' ); ?>
		</figure>
	<?php endif; ?>

	<div class="lidev-entry__content">
		<?php
		the_content();
		wp_link_pages(
			array(
				'before' => '<nav class="lidev-pagination"><div class="nav-links">',
				'after'  => '</div></nav>',
			)
		);
		?>
	</div>
</article>
