<?php
/**
 * Post card in lists.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article <?php post_class( 'lidev-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="lidev-card__media" href="<?php the_permalink(); ?>">
			<?php the_post_thumbnail( 'medium_large' ); ?>
		</a>
	<?php endif; ?>

	<p class="lidev-meta">
		<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
	</p>
	<h2 class="lidev-card__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>
	<div class="lidev-card__excerpt">
		<?php the_excerpt(); ?>
	</div>
</article>
