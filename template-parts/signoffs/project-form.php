<?php
/**
 * Add or edit a project.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_project = Lidev_Routing::project();
$lidev_editing = $lidev_project instanceof WP_Post;
$lidev_clients = $lidev_editing ? lidev_project_clients( (int) $lidev_project->ID ) : array();
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( $lidev_editing ? Lidev_Routing::project_url( (int) $lidev_project->ID ) : home_url( '/' ) ); ?>">
		<?php echo esc_html( $lidev_editing ? __( 'Back to project', 'li-dev-theme' ) : __( 'Back to projects', 'li-dev-theme' ) ); ?>
	</a>
</p>

<header class="lidev-page-header">
	<h1>
		<?php
		echo esc_html(
			$lidev_editing
				? sprintf(
					/* translators: %s: project name. */
					__( 'Edit %s', 'li-dev-theme' ),
					get_the_title( $lidev_project )
				)
				: __( 'Add project', 'li-dev-theme' )
		);
		?>
	</h1>
</header>

<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( $lidev_editing ? 'lidev_update_project' : 'lidev_create_project' ); ?>">
	<?php if ( $lidev_editing ) : ?>
		<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $lidev_project->ID ); ?>">
		<?php wp_nonce_field( 'lidev_update_project', 'lidev_nonce' ); ?>
	<?php else : ?>
		<?php wp_nonce_field( 'lidev_create_project', 'lidev_nonce' ); ?>
	<?php endif; ?>

	<label class="lidev-signoffs__field" for="lidev-title">
		<span><?php esc_html_e( 'Project name', 'li-dev-theme' ); ?></span>
		<input id="lidev-title" name="lidev_title" type="text" required value="<?php echo esc_attr( $lidev_editing ? get_the_title( $lidev_project ) : '' ); ?>">
	</label>

	<label class="lidev-signoffs__field" for="lidev-domain">
		<span><?php esc_html_e( 'Domain', 'li-dev-theme' ); ?></span>
		<input id="lidev-domain" name="lidev_domain" type="text" value="<?php echo esc_attr( $lidev_editing ? (string) get_post_meta( $lidev_project->ID, '_lidev_domain', true ) : '' ); ?>" placeholder="<?php esc_attr_e( 'example.com', 'li-dev-theme' ); ?>">
		<small><?php esc_html_e( 'Add the domain if you have one.', 'li-dev-theme' ); ?></small>
	</label>

	<fieldset class="lidev-signoffs__clients">
		<legend><?php esc_html_e( 'Client', 'li-dev-theme' ); ?></legend>
		<?php if ( $lidev_editing ) : ?>
			<p class="lidev-meta"><?php esc_html_e( 'A name is saved only when the email is new. It does not rename an existing account.', 'li-dev-theme' ); ?></p>
		<?php endif; ?>

		<?php if ( $lidev_editing ) : ?>
			<?php foreach ( $lidev_clients as $lidev_index => $lidev_client ) : ?>
				<div class="lidev-signoffs__client-row">
					<label class="lidev-signoffs__field" for="lidev-client-name-<?php echo esc_attr( (string) $lidev_index ); ?>">
						<span><?php esc_html_e( 'Client name', 'li-dev-theme' ); ?></span>
						<input id="lidev-client-name-<?php echo esc_attr( (string) $lidev_index ); ?>" name="lidev_client_name[]" type="text" value="<?php echo esc_attr( $lidev_client->display_name ); ?>">
					</label>
					<label class="lidev-signoffs__field" for="lidev-client-email-<?php echo esc_attr( (string) $lidev_index ); ?>">
						<span><?php esc_html_e( 'Client email', 'li-dev-theme' ); ?></span>
						<input id="lidev-client-email-<?php echo esc_attr( (string) $lidev_index ); ?>" name="lidev_client_email[]" type="email" value="<?php echo esc_attr( $lidev_client->user_email ); ?>">
					</label>
				</div>
			<?php endforeach; ?>
			<p class="lidev-meta"><?php esc_html_e( 'Clearing an email and saving removes that client from this project. It does not delete the account.', 'li-dev-theme' ); ?></p>
		<?php endif; ?>

		<div class="lidev-signoffs__client-row">
			<label class="lidev-signoffs__field" for="lidev-client-name-new">
				<span><?php echo esc_html( $lidev_editing ? __( 'Another client name', 'li-dev-theme' ) : __( 'Client name', 'li-dev-theme' ) ); ?></span>
				<input id="lidev-client-name-new" name="lidev_client_name[]" type="text" <?php echo $lidev_editing ? '' : 'required'; ?>>
			</label>
			<label class="lidev-signoffs__field" for="lidev-client-email-new">
				<span><?php echo esc_html( $lidev_editing ? __( 'Another client email', 'li-dev-theme' ) : __( 'Client email', 'li-dev-theme' ) ); ?></span>
				<input id="lidev-client-email-new" name="lidev_client_email[]" type="email" <?php echo $lidev_editing ? '' : 'required'; ?>>
			</label>
		</div>
	</fieldset>

	<button class="lidev-button" type="submit">
		<?php echo esc_html( $lidev_editing ? __( 'Save project', 'li-dev-theme' ) : __( 'Create project', 'li-dev-theme' ) ); ?>
	</button>
</form>
