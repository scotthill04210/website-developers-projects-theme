<?php
/**
 * Router for project, edit, and settings screens.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_screen = Lidev_Routing::screen();

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<?php require LIDEV_PATH . 'template-parts/signoffs/notice.php'; ?>
			<?php
			if ( 'settings' === $lidev_screen ) {
				require LIDEV_PATH . 'template-parts/signoffs/settings.php';
			} elseif ( 'docs' === $lidev_screen ) {
				require LIDEV_PATH . 'template-parts/signoffs/docs.php';
			} elseif ( 'activity' === $lidev_screen ) {
				require LIDEV_PATH . 'template-parts/signoffs/activity.php';
			} elseif ( 'archive' === $lidev_screen ) {
				require LIDEV_PATH . 'template-parts/signoffs/archive.php';
			} elseif ( 'new' === $lidev_screen || 'edit' === $lidev_screen ) {
				require LIDEV_PATH . 'template-parts/signoffs/project-form.php';
			} else {
				require LIDEV_PATH . 'template-parts/signoffs/project.php';
			}
			?>
		</div>
	</div>
</main>

<?php
get_footer();
