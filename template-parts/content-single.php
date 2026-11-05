<?php
/**
 * Single post content.
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
		<p class="lidev-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php
			$lidev_cats = get_the_category_list( ', ' );
			if ( $lidev_cats ) {
				echo ' · ' . wp_kses_post( $lidev_cats );
			}
			?>
		</p>
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

	<?php if ( get_the_tags() ) : ?>
		<div class="lidev-tags">
			<?php the_tags( '', '' ); ?>
		</div>
	<?php endif; ?>
</article>
