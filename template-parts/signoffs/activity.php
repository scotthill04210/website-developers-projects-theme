<?php
/**
 * Team activity across projects.
 *
 * @package LIDevTheme
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_project_filter = isset( $_GET['lidev_project'] ) ? absint( $_GET['lidev_project'] ) : 0;
$lidev_action         = isset( $_GET['lidev_action'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_action'] ) ) : '';
$lidev_projects       = get_posts(
	array(
		'post_type'      => 'lidev_project',
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'posts_per_page' => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
$lidev_rows = Lidev_Activity_Repository::search( $lidev_project_filter, $lidev_action );
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<header class="lidev-page-header">
	<h1><?php esc_html_e( 'Activity', 'li-dev-theme' ); ?></h1>
	<p class="lidev-lede"><?php esc_html_e( 'Who did what, in the site timezone.', 'li-dev-theme' ); ?></p>
</header>

<form class="lidev-search lidev-signoffs__filters" method="get" action="<?php echo esc_url( Lidev_Routing::activity_url() ); ?>">
	<label class="lidev-signoffs__sr" for="lidev-activity-project"><?php esc_html_e( 'Project', 'li-dev-theme' ); ?></label>
	<select id="lidev-activity-project" name="lidev_project">
		<option value="0"><?php esc_html_e( 'Any project', 'li-dev-theme' ); ?></option>
		<?php foreach ( $lidev_projects as $lidev_project_option ) : ?>
			<option value="<?php echo esc_attr( (string) $lidev_project_option->ID ); ?>" <?php selected( $lidev_project_filter, (int) $lidev_project_option->ID ); ?>>
				<?php echo esc_html( get_the_title( $lidev_project_option ) ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<label class="lidev-signoffs__sr" for="lidev-activity-action"><?php esc_html_e( 'Action', 'li-dev-theme' ); ?></label>
	<select id="lidev-activity-action" name="lidev_action">
		<option value=""><?php esc_html_e( 'Any action', 'li-dev-theme' ); ?></option>
		<?php foreach ( lidev_activity_actions() as $lidev_key => $lidev_label ) : ?>
			<option value="<?php echo esc_attr( $lidev_key ); ?>" <?php selected( $lidev_action, $lidev_key ); ?>><?php echo esc_html( $lidev_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit"><?php esc_html_e( 'Filter', 'li-dev-theme' ); ?></button>
</form>

<?php if ( ! $lidev_rows ) : ?>
	<p class="lidev-signoffs__empty"><?php esc_html_e( 'No activity yet.', 'li-dev-theme' ); ?></p>
<?php else : ?>
	<ol class="lidev-signoffs__log">
		<?php foreach ( $lidev_rows as $lidev_row ) : ?>
			<?php
			$lidev_actor_id   = absint( $lidev_row['actor_user_id'] ?? 0 );
			$lidev_actor      = $lidev_actor_id ? get_userdata( $lidev_actor_id ) : false;
			$lidev_who        = $lidev_actor instanceof WP_User ? $lidev_actor->display_name : (string) ( $lidev_row['actor_label'] ?? '' );
			$lidev_phase_name = lidev_activity_phase_label( $lidev_row );
			$lidev_project_id = absint( $lidev_row['project_id'] ?? 0 );
			?>
			<li>
				<strong><?php echo esc_html( lidev_activity_label( (string) $lidev_row['action'] ) ); ?></strong>
				<span>
					<?php if ( $lidev_project_id > 0 ) : ?>
						<a href="<?php echo esc_url( Lidev_Routing::project_url( $lidev_project_id ) ); ?>"><?php echo esc_html( get_the_title( $lidev_project_id ) ); ?></a>
					<?php endif; ?>
					<?php echo esc_html( trim( $lidev_who . ' ' . $lidev_phase_name ) ); ?>
					<time><?php echo esc_html( lidev_format_mysql_date( (string) ( $lidev_row['created_at'] ?? '' ), true ) ); ?></time>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>
