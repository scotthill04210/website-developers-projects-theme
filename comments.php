<?php
/**
 * Comments template.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="lidev-comments">
	<?php if ( have_comments() ) : ?>
		<h2>
			<?php
			$lidev_comment_count = get_comments_number();
			echo esc_html(
				sprintf(
					/* translators: %s: comment count. */
					_n( '%s comment', '%s comments', $lidev_comment_count, 'li-dev-theme' ),
					number_format_i18n( $lidev_comment_count )
				)
			);
			?>
		</h2>

		<ol class="lidev-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php comment_form(); ?>
</div>
