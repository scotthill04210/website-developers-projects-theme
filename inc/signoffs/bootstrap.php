<?php
/**
 * Sign-off data layer bootstrap.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIDEV_SCHEMA_VERSION', '2' );

require_once LIDEV_PATH . 'inc/signoffs/helpers.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-activity-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-signoff-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-phase-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/log.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-install.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-roles.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-project.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-settings.php';
require_once LIDEV_PATH . 'inc/signoffs/clients.php';
require_once LIDEV_PATH . 'inc/signoffs/mail.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-routing.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-team-actions.php';
require_once LIDEV_PATH . 'inc/signoffs/dashboard.php';

add_action( 'after_switch_theme', array( 'Lidev_Install', 'run' ) );
add_action( 'init', array( 'Lidev_Routing', 'register' ), 4 );
add_action( 'init', array( 'Lidev_Install', 'maybe_run' ), 5 );
add_action( 'init', array( 'Lidev_Project', 'register' ) );
add_filter( 'query_vars', array( 'Lidev_Routing', 'query_vars' ) );
add_action( 'template_redirect', array( 'Lidev_Routing', 'guard' ) );
add_filter( 'pre_handle_404', array( 'Lidev_Routing', 'prevent_404' ), 10, 2 );
add_filter( 'template_include', array( 'Lidev_Routing', 'template' ) );
add_filter( 'document_title_parts', array( 'Lidev_Routing', 'title' ) );
add_action( 'wp_enqueue_scripts', array( 'Lidev_Routing', 'enqueue' ) );
add_action( 'admin_init', array( 'Lidev_Roles', 'block_client_admin' ) );
add_filter( 'show_admin_bar', array( 'Lidev_Roles', 'filter_admin_bar' ) );
add_filter( 'login_redirect', array( 'Lidev_Roles', 'filter_login_redirect' ), 10, 3 );
add_action( 'admin_post_lidev_create_project', array( 'Lidev_Team_Actions', 'create_project' ) );
add_action( 'admin_post_lidev_update_project', array( 'Lidev_Team_Actions', 'update_project' ) );
add_action( 'admin_post_lidev_add_client', array( 'Lidev_Team_Actions', 'add_client' ) );
add_action( 'admin_post_lidev_send_phase', array( 'Lidev_Team_Actions', 'send_phase' ) );
add_action( 'admin_post_lidev_save_settings', array( 'Lidev_Team_Actions', 'save_settings' ) );
add_action( 'wp_ajax_lidev_create_project', array( 'Lidev_Team_Actions', 'create_project' ) );
add_action( 'wp_ajax_lidev_update_project', array( 'Lidev_Team_Actions', 'update_project' ) );
add_action( 'wp_ajax_lidev_add_client', array( 'Lidev_Team_Actions', 'add_client' ) );
add_action( 'wp_ajax_lidev_send_phase', array( 'Lidev_Team_Actions', 'send_phase' ) );
add_action( 'wp_ajax_lidev_save_settings', array( 'Lidev_Team_Actions', 'save_settings' ) );
