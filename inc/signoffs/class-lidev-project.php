<?php
/**
 * Project records.
 *
 * The post type stays in the database and out of wp-admin. The front-end
 * forms call lidev_create_project() and lidev_update_project().
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Private project post type.
 *
 * @since 1.1.0
 */
final class Lidev_Project {

	/**
	 * Register the hidden post type.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			'lidev_project',
			array(
				'labels'              => array(
					'name'          => __( 'Projects', 'li-dev-theme' ),
					'singular_name' => __( 'Project', 'li-dev-theme' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'show_in_admin_bar'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => array( 'lidev_project', 'lidev_projects' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_post'              => 'lidev_manage_projects',
					'read_post'              => 'lidev_view_all_projects',
					'delete_post'            => 'lidev_manage_projects',
					'edit_posts'             => 'lidev_manage_projects',
					'edit_others_posts'      => 'lidev_manage_projects',
					'delete_posts'           => 'lidev_manage_projects',
					'publish_posts'          => 'lidev_manage_projects',
					'read_private_posts'     => 'lidev_view_all_projects',
					'create_posts'           => 'lidev_manage_projects',
					'delete_private_posts'   => 'lidev_manage_projects',
					'delete_published_posts' => 'lidev_manage_projects',
					'delete_others_posts'    => 'lidev_manage_projects',
					'edit_private_posts'     => 'lidev_manage_projects',
					'edit_published_posts'   => 'lidev_manage_projects',
				),
				'supports'            => array( 'title' ),
			)
		);

		register_post_meta(
			'lidev_project',
			'_lidev_domain',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'lidev_manage_projects' );
				},
			)
		);

		add_filter( 'pre_trash_post', array( self::class, 'block_signed_removal' ), 10, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'block_signed_removal' ), 10, 2 );
	}

	/**
	 * Create a project, or finish setup for an existing one without duplicating phases or the created log.
	 *
	 * @since 1.2.0
	 * @param array<string, mixed> $args title, domain, client_ids, created_by, and optional project_id.
	 * @return int|WP_Error Project ID.
	 */
	public static function create( array $args ) {
		$existing   = absint( $args['project_id'] ?? 0 );
		$project_id = 0;

		if ( $existing > 0 && 'lidev_project' === get_post_type( $existing ) ) {
			$project_id = $existing;
		} else {
			$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );

			if ( '' === $title ) {
				return new WP_Error( 'lidev_project_title', __( 'A project name is required.', 'li-dev-theme' ) );
			}

			$inserted = wp_insert_post(
				array(
					'post_type'   => 'lidev_project',
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);

			if ( is_wp_error( $inserted ) ) {
				return $inserted;
			}

			$project_id = (int) $inserted;
		}

		if ( array_key_exists( 'domain', $args ) ) {
			update_post_meta( $project_id, '_lidev_domain', self::domain( (string) $args['domain'] ) );
		}

		if ( isset( $args['client_ids'] ) ) {
			update_post_meta( $project_id, '_lidev_client_ids', lidev_sanitize_user_ids( $args['client_ids'] ) );
		}

		if ( ! get_post_meta( $project_id, '_lidev_created_by', true ) ) {
			$created_by = absint( $args['created_by'] ?? get_current_user_id() );

			if ( $created_by > 0 ) {
				update_post_meta( $project_id, '_lidev_created_by', $created_by );
			}
		}

		$before = Lidev_Phase_Repository::count_for_project( $project_id );
		$ready  = Lidev_Phase_Repository::ensure_for_project( $project_id );

		if ( $ready && 0 === $before && ! get_post_meta( $project_id, '_lidev_project_logged', true ) ) {
			update_post_meta( $project_id, '_lidev_project_logged', '1' );
			lidev_log(
				'project_created',
				$project_id,
				null,
				array(
					'title' => get_the_title( $project_id ),
				)
			);
		}

		return $project_id;
	}

	/**
	 * Update the name, domain, and client list. Does not touch phases.
	 *
	 * @since 1.2.0
	 * @param int                  $project_id Project ID.
	 * @param array<string, mixed> $args       title, domain, client_ids.
	 * @return true|WP_Error
	 */
	public static function update( int $project_id, array $args ) {
		if ( 'lidev_project' !== get_post_type( $project_id ) ) {
			return new WP_Error( 'lidev_project_missing', __( 'That project does not exist.', 'li-dev-theme' ) );
		}

		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );

		if ( '' === $title ) {
			return new WP_Error( 'lidev_project_title', __( 'A project name is required.', 'li-dev-theme' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'         => $project_id,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		update_post_meta( $project_id, '_lidev_domain', self::domain( (string) ( $args['domain'] ?? '' ) ) );
		update_post_meta( $project_id, '_lidev_client_ids', lidev_sanitize_user_ids( $args['client_ids'] ?? array() ) );

		lidev_log(
			'project_updated',
			$project_id,
			null,
			array(
				'title' => $title,
			)
		);

		return true;
	}

	/**
	 * Stop Trash and Delete once any phase is signed.
	 *
	 * @since 1.1.0
	 * @param mixed        $short_circuit Null to continue.
	 * @param WP_Post|null $post          Post being removed.
	 * @return mixed
	 */
	public static function block_signed_removal( $short_circuit, $post ) {
		if ( null !== $short_circuit || ! $post instanceof WP_Post || 'lidev_project' !== $post->post_type ) {
			return $short_circuit;
		}

		if ( Lidev_Phase_Repository::project_has_signed( (int) $post->ID ) ) {
			return false;
		}

		return $short_circuit;
	}

	/**
	 * Optional domain, stored as plain text.
	 *
	 * @since 1.2.0
	 * @param string $domain Raw domain.
	 * @return string
	 */
	private static function domain( string $domain ): string {
		return substr( sanitize_text_field( $domain ), 0, 253 );
	}
}

/**
 * Create a project and its three phase rows.
 *
 * Calling this again with project_id for the same project does not add phase rows or another project_created log.
 *
 * @since 1.2.0
 * @param array<string, mixed> $args Project fields.
 * @return int|WP_Error
 */
function lidev_create_project( array $args ) {
	return Lidev_Project::create( $args );
}

/**
 * Update a project's name, domain, and clients.
 *
 * @since 1.2.0
 * @param int                  $project_id Project ID.
 * @param array<string, mixed> $args       Fields to store.
 * @return true|WP_Error
 */
function lidev_update_project( int $project_id, array $args ) {
	return Lidev_Project::update( $project_id, $args );
}
