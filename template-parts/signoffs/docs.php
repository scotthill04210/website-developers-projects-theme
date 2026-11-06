<?php
/**
 * Role-specific instructions.
 *
 * A client account sees only the client guide. Every other visitor sees the team guide.
 *
 * @package LIDevTheme
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_for_client = is_user_logged_in() && lidev_user_is_client_only();
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<?php if ( $lidev_for_client ) : ?>
	<header class="lidev-page-header">
		<h1><?php esc_html_e( 'Client guide', 'li-dev-theme' ); ?></h1>
		<p class="lidev-lede"><?php esc_html_e( 'How to review a phase and record your approval.', 'li-dev-theme' ); ?></p>
	</header>

	<article class="lidev-signoffs__docs">
		<h2><?php esc_html_e( 'Sign in', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'Open the review link in the email from Local Image. That link signs you in and opens the phase. You can use the same link again until it expires. If it has expired, enter your email on the sign-in page and a new link will be sent.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'The three phases', 'li-dev-theme' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Content (Wireframe) — the structure and content of the site.', 'li-dev-theme' ); ?></li>
			<li><?php esc_html_e( 'Design (Figma) — the visual design.', 'li-dev-theme' ); ?></li>
			<li><?php esc_html_e( 'Pre-launch review — the site as it will go live.', 'li-dev-theme' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'Your dashboard lists each project and which phases are ready. Open a phase when its status says it is ready for you.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'Review and sign', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'Open the wireframe, Figma file, or staging site from the phase page. Read the note from the team, then sign by typing or drawing your name and confirming the statement. A signature is permanent and cannot be changed.', 'li-dev-theme' ); ?></p>
		<p><?php esc_html_e( 'The approval record stores your name, email, the time, and the IP address of the browser you signed from. Download the PDF from the phase page or your dashboard for your copy.', 'li-dev-theme' ); ?></p>
	</article>
<?php else : ?>
	<header class="lidev-page-header">
		<h1><?php esc_html_e( 'Team instructions', 'li-dev-theme' ); ?></h1>
		<p class="lidev-lede"><?php esc_html_e( 'How projects, phase emails, and approval records work.', 'li-dev-theme' ); ?></p>
	</header>

	<article class="lidev-signoffs__docs">
		<h2><?php esc_html_e( 'Add a project', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'From the dashboard, add a project with its name, an optional domain, and the client’s name and email. A new email creates a client account. An email that already belongs to a client is attached to this project as well. Creating a project does not send email. The first message is the phase you send.', 'li-dev-theme' ); ?></p>
		<p><?php esc_html_e( 'Each project has three phases, in order: Content (Wireframe), Design (Figma), and Pre-launch review. You can still send Design before Content is signed. A signed phase cannot be edited, resent, or unsigned.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'Send a phase', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'On the project, enter the wireframe, Figma, or staging link. The email starts with the default message for that phase. Use (Optional) Add to message for anything extra. The preview shows the email the client will receive. The review button in the preview is not a live link; the real sign-in link is created when you send.', 'li-dev-theme' ); ?></p>
		<p><?php esc_html_e( 'Placeholders in a message are replaced for each client: {client_name}, {project_name}, {phase_name}, {link}, {domain}, and {site_name}. Resend updates the link and the optional note and sends a fresh email.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'Reminders', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'An unsigned phase sends a reminder on the interval in Settings, by default every 3 days. Pause reminders on that phase when you are waiting on something else. WordPress also runs the reminder check twice a day.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'Approval records', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'When a client signs, the record keeps the project, phase, link, message, statement, signer name and email, the time, and the client IP address. The PDF download is that record. Changing the live project later does not change a signed record.', 'li-dev-theme' ); ?></p>

		<h2><?php esc_html_e( 'Settings', 'li-dev-theme' ); ?></h2>
		<p><?php esc_html_e( 'Administrators open Settings from the dashboard. Email, Reminders, and Messages are separate tabs, and each tab saves only its own fields. Team members use the project screens and this guide. Clients only see the client guide.', 'li-dev-theme' ); ?></p>
	</article>
<?php endif; ?>
