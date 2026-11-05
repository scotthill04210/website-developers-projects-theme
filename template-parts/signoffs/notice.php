<?php
/**
 * One-time notice for the current Team user.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_notice = lidev_pull_notice();
$lidev_text   = lidev_notice_text( $lidev_notice );

if ( '' === $lidev_text ) {
	return;
}
?>
<p class="lidev-signoffs__notice<?php echo 'error' === $lidev_notice['code'] ? ' lidev-signoffs__notice--error' : ''; ?>" role="status">
	<?php echo esc_html( $lidev_text ); ?>
</p>
