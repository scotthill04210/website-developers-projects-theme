<?php
/**
 * Sign-off emails and reminder checks.
 *
 * Mail uses the site's existing transport. From name and address come from settings.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML and plain-text messages for phases, reminders, and signed records.
 *
 * @since 1.6.0
 */
final class Lidev_Mail {

	private const HOOK = 'lidev_send_reminders';

	private static string $plain = '';

	/**
	 * What the mailer actually used on the last delivery. Filled at send time.
	 *
	 * @var array{from_email: string, from_name: string, reply_to: string, subject: string, headers: string, error: string}
	 */
	private static array $envelope = array(
		'from_email' => '',
		'from_name'  => '',
		'reply_to'   => '',
		'subject'    => '',
		'headers'    => '',
		'error'      => '',
	);

	/**
	 * Twice a day. Traffic runs WP-Cron; a server cron hitting wp-cron.php is more reliable.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::HOOK );
	}

	/**
	 * Email every assigned client when a phase is sent or resent.
	 *
	 * @since 1.2.0
	 * @param int $project_id Project ID.
	 * @param int $phase_id   Phase row ID.
	 * @return void
	 */
	public static function send_phase( int $project_id, int $phase_id ): void {
		$context = self::phase_context( $project_id, $phase_id );

		if ( ! $context ) {
			self::deliver( '', '', '', '', 'phase_ready', $project_id, $phase_id, array() );
			return;
		}

		if ( ! $context['clients'] ) {
			self::deliver( '', '', '', '', 'phase_ready', $project_id, $phase_id, array() );
			return;
		}

		foreach ( $context['clients'] as $client ) {
			$link = lidev_issue_magic_link( (int) $client->ID, $phase_id );

			if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
				self::deliver( (string) $client->user_email, '', '', '', 'phase_ready', $project_id, $phase_id, array() );
				continue;
			}

			$subject = sprintf(
				/* translators: 1: project name, 2: phase name. */
				__( 'Please review %1$s — %2$s', 'li-dev-theme' ),
				$context['project'],
				$context['phase']
			);
			$intro = __( 'A phase is ready for your approval.', 'li-dev-theme' );
			self::send_client_link( $client, $context, $link, $subject, $intro, 'phase_ready' );
		}
	}

	/**
	 * Tell the team a phase was signed, then send the PDF receipt.
	 *
	 * @since 1.6.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return void
	 */
	public static function on_signed( int $signoff_id ): void {
		$row = Lidev_Signoff_Repository::get( $signoff_id );

		if ( ! is_array( $row ) ) {
			return;
		}

		$snap    = lidev_signoff_snapshot( $row );
		$project = '' !== $snap['project_name'] ? $snap['project_name'] : __( 'Project', 'li-dev-theme' );
		$phase   = '' !== $snap['phase_label'] ? $snap['phase_label'] : __( 'Phase', 'li-dev-theme' );
		$signer  = '' !== $snap['signer_name'] ? $snap['signer_name'] : (string) ( $row['signer_name'] ?? '' );
		$when    = lidev_format_mysql_date( (string) ( $row['signed_at'] ?? '' ), true );
		$url     = Lidev_Routing::project_url( (int) $row['project_id'] );
		$pid     = (int) $row['project_id'];
		$phid    = (int) $row['phase_id'];

		$subject = sprintf(
			/* translators: 1: project name, 2: phase name. */
			__( '%1$s was signed — %2$s', 'li-dev-theme' ),
			$project,
			$phase
		);
		$html = self::document(
			$subject,
			__( 'A client approved a phase.', 'li-dev-theme' ),
			self::facts(
				array(
					__( 'Client', 'li-dev-theme' )  => $signer,
					__( 'Email', 'li-dev-theme' )   => $snap['signer_email'],
					__( 'Project', 'li-dev-theme' ) => $project,
					__( 'Phase', 'li-dev-theme' )   => $phase,
					__( 'Signed', 'li-dev-theme' )  => $when,
				)
			),
			'',
			$url,
			__( 'Open the project', 'li-dev-theme' ),
			''
		);
		$text = self::plain_from_parts(
			$subject,
			__( 'A client approved a phase.', 'li-dev-theme' ),
			array(
				__( 'Client', 'li-dev-theme' )  => $signer,
				__( 'Project', 'li-dev-theme' ) => $project,
				__( 'Phase', 'li-dev-theme' )   => $phase,
				__( 'Signed', 'li-dev-theme' )  => $when,
			),
			'',
			$url,
			''
		);
		self::deliver( (string) Lidev_Settings::get()['team_email'], $subject, $html, $text, 'team_signed', $pid, $phid > 0 ? $phid : null, array() );

		$copy = Lidev_Pdf::mail_copy( $signoff_id );
		$files = is_string( $copy ) ? array( $copy ) : array();

		if ( is_wp_error( $copy ) ) {
			lidev_log(
				'email_failed',
				$pid,
				$phid > 0 ? $phid : null,
				array(
					'type'      => 'receipt',
					'recipient' => '',
					'reason'    => 'pdf',
				)
			);
		}

		$receipt_subject = sprintf(
			/* translators: %s: project name. */
			__( 'Approval record for %s', 'li-dev-theme' ),
			$project
		);
		$note = $files
			? __( 'The approval record is attached.', 'li-dev-theme' )
			: __( 'The approval record is saved on the project.', 'li-dev-theme' );
		$receipt_html = self::document(
			$receipt_subject,
			__( 'This phase is signed and cannot be changed.', 'li-dev-theme' ),
			self::facts(
				array(
					__( 'Project', 'li-dev-theme' ) => $project,
					__( 'Phase', 'li-dev-theme' )   => $phase,
					__( 'Signed by', 'li-dev-theme' ) => $signer,
					__( 'Signed', 'li-dev-theme' )  => $when,
				)
			),
			'',
			$url,
			__( 'Open the project', 'li-dev-theme' ),
			$note
		);
		$receipt_text = self::plain_from_parts(
			$receipt_subject,
			__( 'This phase is signed and cannot be changed.', 'li-dev-theme' ),
			array(
				__( 'Project', 'li-dev-theme' ) => $project,
				__( 'Phase', 'li-dev-theme' )   => $phase,
				__( 'Signed by', 'li-dev-theme' ) => $signer,
				__( 'Signed', 'li-dev-theme' )  => $when,
			),
			'',
			$url,
			$note
		);

		$sent_to = array();

		foreach ( lidev_project_clients( $pid ) as $client ) {
			$email = sanitize_email( (string) $client->user_email );

			if ( isset( $sent_to[ $email ] ) ) {
				continue;
			}

			$sent_to[ $email ] = true;
			self::deliver( $email, $receipt_subject, $receipt_html, $receipt_text, 'receipt', $pid, $phid > 0 ? $phid : null, $files );
		}

		$team = sanitize_email( (string) Lidev_Settings::get()['team_email'] );

		if ( ! isset( $sent_to[ $team ] ) ) {
			self::deliver( $team, $receipt_subject, $receipt_html, $receipt_text, 'receipt', $pid, $phid > 0 ? $phid : null, $files );
		}

		foreach ( $files as $file ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Send reminders that are due. Safe to run from cron or by hand.
	 *
	 * @since 1.6.0
	 * @return int How many client reminders were sent.
	 */
	public static function send_due_reminders(): int {
		global $wpdb;

		$days   = Lidev_Settings::get()['reminder_days'];
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table  = lidev_table( 'phases' );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND reminders_paused = 0 AND ( ( last_reminder_at IS NOT NULL AND last_reminder_at <= %s ) OR ( last_reminder_at IS NULL AND sent_at IS NOT NULL AND sent_at <= %s ) )",
				'awaiting',
				$cutoff,
				$cutoff
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$sent = 0;

		foreach ( $rows as $phase ) {
			$phase_id = (int) $phase['id'];
			$current  = Lidev_Phase_Repository::get( $phase_id );

			if ( ! $current || 'awaiting' !== (string) $current['status'] || ! empty( $current['reminders_paused'] ) ) {
				continue;
			}

			if ( lidev_project_is_archived( (int) $current['project_id'] ) ) {
				continue;
			}

			$count = self::remind_phase( $current );

			if ( $count < 1 ) {
				continue;
			}

			++$sent;

			if ( $count >= 3 && empty( $current['unanswered_alerted'] ) ) {
				self::send_unanswered( $current );
				Lidev_Phase_Repository::mark_unanswered_alerted( $phase_id );
			}
		}

		return $sent;
	}

	/**
	 * @since 1.6.0
	 * @param string $email From address proposed by WordPress.
	 * @return string
	 */
	public static function filter_from( string $email ): string {
		$from = Lidev_Settings::get()['from_email'];

		return is_email( $from ) ? $from : $email;
	}

	/**
	 * @since 1.6.0
	 * @param string $name From name proposed by WordPress.
	 * @return string
	 */
	public static function filter_from_name( string $name ): string {
		$from = Lidev_Settings::get()['from_name'];

		return '' !== $from ? $from : $name;
	}

	/**
	 * @since 1.6.0
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 * @return void
	 */
	public static function filter_phpmailer( $phpmailer ): void {
		if ( is_object( $phpmailer ) && '' !== self::$plain ) {
			$phpmailer->AltBody = self::$plain;
		}
	}

	/**
	 * Read the headers after every other mail plugin has run. Does not change the message.
	 *
	 * @since 1.7.0
	 * @param object $phpmailer Mailer instance.
	 * @return void
	 */
	public static function capture_envelope( $phpmailer ): void {
		if ( ! is_object( $phpmailer ) ) {
			return;
		}

		$from_email = (string) ( $phpmailer->From ?? '' );
		$from_name  = (string) ( $phpmailer->FromName ?? '' );
		$reply      = array();

		if ( ! empty( $phpmailer->ReplyTo ) && is_array( $phpmailer->ReplyTo ) ) {
			foreach ( $phpmailer->ReplyTo as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$address = (string) ( $row[0] ?? '' );
				$name    = (string) ( $row[1] ?? '' );
				$reply[] = '' !== $name ? $name . ' <' . $address . '>' : $address;
			}
		}

		$lines = array(
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Content-Type: ' . (string) ( $phpmailer->ContentType ?? '' ),
		);

		if ( $reply ) {
			$lines[] = 'Reply-To: ' . implode( ', ', $reply );
		}

		$custom = method_exists( $phpmailer, 'getCustomHeaders' ) ? $phpmailer->getCustomHeaders() : array();

		if ( is_array( $custom ) ) {
			foreach ( $custom as $header ) {
				if ( is_array( $header ) && isset( $header[0], $header[1] ) ) {
					$lines[] = (string) $header[0] . ': ' . (string) $header[1];
				}
			}
		}

		self::$envelope = array(
			'from_email' => $from_email,
			'from_name'  => $from_name,
			'reply_to'   => implode( ', ', $reply ),
			'subject'    => (string) ( $phpmailer->Subject ?? '' ),
			'headers'    => implode( "\n", $lines ),
			'error'      => self::$envelope['error'],
		);
	}

	/**
	 * @since 1.7.0
	 * @param WP_Error $error Failure from wp_mail.
	 * @return void
	 */
	public static function capture_failure( $error ): void {
		if ( $error instanceof WP_Error ) {
			self::$envelope['error'] = $error->get_error_message();
		}
	}

	/**
	 * Headers from the most recent delivery.
	 *
	 * @since 1.7.0
	 * @return array{from_email: string, from_name: string, reply_to: string, subject: string, headers: string, error: string}
	 */
	public static function last_delivery(): array {
		return self::$envelope;
	}

	/**
	 * Send one phase email through the same builders as a normal send.
	 *
	 * The magic link belongs to an assigned client. When $email is that client,
	 * this is the same send. When it is not, only the recipient changes.
	 *
	 * @since 1.7.0
	 * @param string $email    Recipient.
	 * @param int    $phase_id Phase row ID.
	 * @return bool
	 */
	public static function send_phase_to( string $email, int $phase_id ): bool {
		$email = sanitize_email( $email );
		$phase = Lidev_Phase_Repository::get( $phase_id );

		if ( ! is_email( $email ) || ! $phase ) {
			return false;
		}

		$project_id = (int) $phase['project_id'];
		$context    = self::phase_context( $project_id, $phase_id );

		if ( ! $context || ! $context['clients'] ) {
			self::deliver( $email, '', '', '', 'phase_ready', $project_id, $phase_id, array() );
			return false;
		}

		$client = $context['clients'][0];

		foreach ( $context['clients'] as $candidate ) {
			if ( strtolower( (string) $candidate->user_email ) === strtolower( $email ) ) {
				$client = $candidate;
				break;
			}
		}

		$link = lidev_issue_magic_link( (int) $client->ID, $phase_id );

		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			self::deliver( $email, '', '', '', 'phase_ready', $project_id, $phase_id, array() );
			return false;
		}

		$subject = sprintf(
			/* translators: 1: project name, 2: phase name. */
			__( 'Please review %1$s — %2$s', 'li-dev-theme' ),
			$context['project'],
			$context['phase']
		);

		return self::send_client_link(
			$client,
			$context,
			$link,
			$subject,
			__( 'A phase is ready for your approval.', 'li-dev-theme' ),
			'phase_ready',
			$email
		);
	}

	/**
	 * HTML for the Team preview. Same document as the phase email, without a magic link.
	 *
	 * @since 1.7.0
	 * @param int    $project_id Project ID.
	 * @param int    $phase_id   Phase ID.
	 * @param string $link       Link typed on the form.
	 * @param string $extra      Optional note typed on the form.
	 * @return array{html: string, caption: string}
	 */
	public static function preview_phase( int $project_id, int $phase_id, string $link, string $extra ): array {
		$context = self::phase_context( $project_id, $phase_id );

		if ( ! $context ) {
			return array(
				'html'    => '',
				'caption' => '',
			);
		}

		$context['link']    = $link;
		$context['message'] = $extra;
		$clients            = $context['clients'];
		$client             = $clients[0] ?? null;
		$name               = $client instanceof WP_User && '' !== (string) $client->display_name
			? (string) $client->display_name
			: __( 'the client', 'li-dev-theme' );

		if ( ! $client instanceof WP_User ) {
			$client = new WP_User();
		}

		$subject = sprintf(
			/* translators: 1: project name, 2: phase name. */
			__( 'Please review %1$s — %2$s', 'li-dev-theme' ),
			$context['project'],
			$context['phase']
		);
		$days = (int) Lidev_Settings::get()['magic_link_days'];
		$note = sprintf(
			/* translators: %d: number of days. */
			__( 'This link expires in %d days.', 'li-dev-theme' ),
			$days
		);
		$html = self::document(
			$subject,
			__( 'A phase is ready for your approval.', 'li-dev-theme' ),
			self::facts(
				array(
					__( 'Project', 'li-dev-theme' ) => (string) $context['project'],
					__( 'Phase', 'li-dev-theme' )   => (string) $context['phase'],
				)
			),
			self::client_message( $client, $context ),
			'#',
			__( 'Review and sign', 'li-dev-theme' ),
			$note
		);

		if ( count( $clients ) > 1 ) {
			$caption = sprintf(
				/* translators: %s: client name shown in this preview. */
				__( 'Preview for %s. Each client receives their own name.', 'li-dev-theme' ),
				$name
			);
		} else {
			$caption = sprintf(
				/* translators: %s: client name shown in this preview. */
				__( 'Preview for %s.', 'li-dev-theme' ),
				$name
			);
		}

		return array(
			'html'    => $html,
			'caption' => $caption,
		);
	}

	/**
	 * @since 1.6.0
	 * @param array<string, mixed> $phase Phase row before the reminder is counted.
	 * @return int New reminder count, or 0 when nothing was sent.
	 */
	private static function remind_phase( array $phase ): int {
		$project_id = (int) $phase['project_id'];
		$phase_id   = (int) $phase['id'];
		$context    = self::phase_context( $project_id, $phase_id );

		if ( ! $context || ! $context['clients'] ) {
			self::deliver( '', '', '', '', 'reminder', $project_id, $phase_id, array() );
			return 0;
		}

		$subject = sprintf(
			/* translators: %s: project name. */
			__( 'Reminder: %s is waiting for your approval', 'li-dev-theme' ),
			$context['project']
		);
		$intro   = __( 'This is a reminder to review and sign when you are ready.', 'li-dev-theme' );
		$any     = false;

		foreach ( $context['clients'] as $client ) {
			$link = lidev_issue_magic_link( (int) $client->ID, $phase_id );

			if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
				self::deliver( (string) $client->user_email, '', '', '', 'reminder', $project_id, $phase_id, array() );
				continue;
			}

			if ( self::send_client_link( $client, $context, $link, $subject, $intro, 'reminder' ) ) {
				$any = true;
			}
		}

		if ( ! $any ) {
			return 0;
		}

		$count = Lidev_Phase_Repository::mark_reminded( $phase_id );

		if ( $count > 0 ) {
			lidev_log(
				'reminder_sent',
				$project_id,
				$phase_id,
				array(
					'count' => $count,
				)
			);
		}

		return $count;
	}

	/**
	 * @since 1.6.0
	 * @param array<string, mixed> $phase Awaiting phase that has had three reminders.
	 * @return void
	 */
	private static function send_unanswered( array $phase ): void {
		$project_id = (int) $phase['project_id'];
		$phase_id   = (int) $phase['id'];
		$context = self::phase_context( $project_id, $phase_id );
		$project = is_array( $context ) ? (string) $context['project'] : get_the_title( $project_id );
		$label   = is_array( $context ) ? (string) $context['phase'] : lidev_phase_panel_title( (string) ( $phase['type'] ?? '' ) );
		$url        = Lidev_Routing::project_url( $project_id );
		$subject    = sprintf(
			/* translators: 1: project name, 2: phase name. */
			__( '%1$s has not been signed — %2$s', 'li-dev-theme' ),
			$project,
			$label
		);
		$intro = __( 'Three reminders have gone out and this phase is still unsigned.', 'li-dev-theme' );
		$html  = self::document( $subject, $intro, self::facts( array( __( 'Project', 'li-dev-theme' ) => $project, __( 'Phase', 'li-dev-theme' ) => $label ) ), '', $url, __( 'Open the project', 'li-dev-theme' ), '' );
		$text  = self::plain_from_parts( $subject, $intro, array( __( 'Project', 'li-dev-theme' ) => $project, __( 'Phase', 'li-dev-theme' ) => $label ), '', $url, '' );

		self::deliver( (string) Lidev_Settings::get()['team_email'], $subject, $html, $text, 'unanswered', $project_id, $phase_id, array() );
		lidev_log( 'reminder_unanswered', $project_id, $phase_id, array( 'count' => 3 ) );
	}

	/**
	 * @since 1.6.0
	 * @param WP_User              $client  Assigned client.
	 * @param array<string, mixed> $context Project and phase text.
	 * @param string               $link    Magic link URL.
	 * @param string               $subject Mail subject.
	 * @param string               $intro   Opening sentence.
	 * @param string               $type      email_sent type.
	 * @param string               $recipient Recipient override. Empty uses the client's address.
	 * @return bool
	 */
	private static function send_client_link( WP_User $client, array $context, string $link, string $subject, string $intro, string $type, string $recipient = '' ): bool {
		$days = (int) Lidev_Settings::get()['magic_link_days'];
		$note = sprintf(
			/* translators: %d: number of days. */
			__( 'This link expires in %d days.', 'li-dev-theme' ),
			$days
		);
		$message = self::client_message( $client, $context );
		$html = self::document(
			$subject,
			$intro,
			self::facts(
				array(
					__( 'Project', 'li-dev-theme' ) => (string) $context['project'],
					__( 'Phase', 'li-dev-theme' )   => (string) $context['phase'],
				)
			),
			$message,
			$link,
			__( 'Review and sign', 'li-dev-theme' ),
			$note
		);
		$text = self::plain_from_parts(
			$subject,
			$intro,
			array(
				__( 'Project', 'li-dev-theme' ) => (string) $context['project'],
				__( 'Phase', 'li-dev-theme' )   => (string) $context['phase'],
			),
			$message,
			$link,
			$note
		);

		$to = is_email( $recipient ) ? $recipient : (string) $client->user_email;

		return self::deliver( $to, $subject, $html, $text, $type, (int) $context['project_id'], (int) $context['phase_id'], array() );
	}

	/**
	 * @since 1.6.0
	 * @param string        $to          Recipient.
	 * @param string        $subject     Subject.
	 * @param string        $html        HTML body.
	 * @param string        $text        Plain-text body.
	 * @param string        $type        Machine type.
	 * @param int           $project_id  Project ID.
	 * @param int|null      $phase_id    Phase ID.
	 * @param list<string>  $attachments File paths.
	 * @return bool
	 */
	private static function deliver( string $to, string $subject, string $html, string $text, string $type, int $project_id, ?int $phase_id, array $attachments ): bool {
		$to       = sanitize_email( $to );
		$settings = Lidev_Settings::get();
		self::$envelope = array(
			'from_email' => is_email( $settings['from_email'] ) ? $settings['from_email'] : '',
			'from_name'  => $settings['from_name'],
			'reply_to'   => '',
			'subject'    => $subject,
			'headers'    => '',
			'error'      => '',
		);

		if ( ! is_email( $to ) || '' === $subject ) {
			self::log_delivery( false, $type, $to, $project_id, $phase_id );
			return false;
		}

		self::$plain = $text;
		add_filter( 'wp_mail_from', array( self::class, 'filter_from' ) );
		add_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ) );
		add_action( 'phpmailer_init', array( self::class, 'filter_phpmailer' ) );
		add_action( 'phpmailer_init', array( self::class, 'capture_envelope' ), PHP_INT_MAX );
		add_action( 'wp_mail_failed', array( self::class, 'capture_failure' ) );

		try {
			$sent = wp_mail(
				$to,
				sanitize_text_field( $subject ),
				$html,
				array( 'Content-Type: text/html; charset=UTF-8' ),
				$attachments
			);
		} finally {
			remove_filter( 'wp_mail_from', array( self::class, 'filter_from' ) );
			remove_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ) );
			remove_action( 'phpmailer_init', array( self::class, 'filter_phpmailer' ) );
			remove_action( 'phpmailer_init', array( self::class, 'capture_envelope' ), PHP_INT_MAX );
			remove_action( 'wp_mail_failed', array( self::class, 'capture_failure' ) );
			self::$plain = '';
		}

		self::log_delivery( (bool) $sent, $type, $to, $project_id, $phase_id );

		return (bool) $sent;
	}

	/**
	 * @since 1.7.0
	 * @param bool     $sent       Whether wp_mail accepted the message.
	 * @param string   $type       Machine type.
	 * @param string   $to         Recipient.
	 * @param int      $project_id Project ID.
	 * @param int|null $phase_id   Phase ID.
	 * @return void
	 */
	private static function log_delivery( bool $sent, string $type, string $to, int $project_id, ?int $phase_id ): void {
		$details = array(
			'type'       => $type,
			'recipient'  => $to,
			'from_email' => self::$envelope['from_email'],
			'from_name'  => self::$envelope['from_name'],
			'subject'    => self::$envelope['subject'],
		);

		if ( ! $sent && '' !== self::$envelope['error'] ) {
			$details['error'] = self::$envelope['error'];
		}

		lidev_log( $sent ? 'email_sent' : 'email_failed', $project_id, $phase_id, $details );
	}

	/**
	 * @since 1.6.0
	 * @param int $project_id Project ID.
	 * @param int $phase_id   Phase ID.
	 * @return array<string, mixed>|null
	 */
	private static function phase_context( int $project_id, int $phase_id ): ?array {
		$project = get_post( $project_id );
		$phase   = Lidev_Phase_Repository::get( $phase_id );

		if ( ! $project instanceof WP_Post || 'lidev_project' !== $project->post_type || ! $phase || (int) $phase['project_id'] !== $project_id ) {
			return null;
		}

		return array(
			'project_id' => $project_id,
			'phase_id'   => $phase_id,
			'project'    => (string) $project->post_title,
			'phase'      => lidev_phase_panel_title( (string) $phase['type'] ),
			'type'       => (string) $phase['type'],
			'link'       => (string) ( $phase['external_link'] ?? '' ),
			'domain'     => (string) get_post_meta( $project_id, '_lidev_domain', true ),
			'message'    => (string) ( $phase['team_message'] ?? '' ),
			'clients'    => lidev_project_clients( $project_id ),
		);
	}

	/**
	 * The message one client receives: the phase default, then the optional note.
	 *
	 * @since 1.7.0
	 * @param WP_User              $client  Recipient. An empty user leaves {client_name} in place.
	 * @param array<string, mixed> $context Project and phase text.
	 * @return string
	 */
	private static function client_message( WP_User $client, array $context ): string {
		$name = '' !== (string) $client->display_name ? (string) $client->display_name : '';

		return lidev_compose_phase_message(
			(string) $context['type'],
			(string) $context['message'],
			lidev_phase_message_values(
				$name,
				(string) $context['project'],
				(string) $context['phase'],
				(string) $context['link'],
				(string) $context['domain']
			)
		);
	}

	/**
	 * @since 1.6.0
	 * @param array<string, string> $rows Label and value pairs.
	 * @return string
	 */
	private static function facts( array $rows ): string {
		$html = '';

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}

			$html .= '<tr><th style="text-align:left;padding:6px 12px 6px 0;color:#545454;font-weight:normal;">' . esc_html( $label ) . '</th><td style="padding:6px 0;color:#1e1e21;">' . esc_html( $value ) . '</td></tr>';
		}

		return '' === $html ? '' : '<table style="border-collapse:collapse;margin:16px 0;">' . $html . '</table>';
	}

	/**
	 * @since 1.6.0
	 * @param string $title   Heading.
	 * @param string $intro   Opening sentence.
	 * @param string $facts   Escaped table, or empty.
	 * @param string $message Plain team message.
	 * @param string $url     Button URL.
	 * @param string $button  Button label.
	 * @param string $note    Extra sentence.
	 * @return string
	 */
	private static function document( string $title, string $intro, string $facts, string $message, string $url, string $button, string $note ): string {
		$logo = esc_url( LIDEV_URI . 'assets/images/LI_MainLogo_DarkJungle.png' );
		$message_html = '';

		$formatted = lidev_format_email_html( $message, true );

		if ( '' !== $formatted ) {
			$message_html = '<div style="margin:16px 0;color:#1e1e21;">' . $formatted . '</div>';
		}

		$button_html = '';

		if ( '' !== $url && '' !== $button ) {
			$button_html = '<p style="margin:20px 0;"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#1e1e21;color:#f7f6f5;text-decoration:none;padding:12px 18px;border-radius:6px;">' . esc_html( $button ) . '</a></p>';
		}

		$note_html = '' !== $note ? '<p style="margin:16px 0;color:#545454;">' . esc_html( $note ) . '</p>' : '';

		return '<!DOCTYPE html><html><body style="margin:0;background:#f7f6f5;font-family:Arial,sans-serif;">'
			. '<div style="height:6px;background:#bfd100;"></div>'
			. '<div style="max-width:560px;margin:0 auto;padding:28px 24px;background:#ffffff;">'
			. '<img src="' . $logo . '" alt="Local Image" height="34" style="height:34px;">'
			. '<h1 style="font-size:22px;color:#1e1e21;margin:18px 0 8px;">' . esc_html( $title ) . '</h1>'
			. '<p style="margin:0 0 8px;color:#1e1e21;">' . esc_html( $intro ) . '</p>'
			. $facts
			. $message_html
			. $button_html
			. $note_html
			. '</div></body></html>';
	}

	/**
	 * @since 1.6.0
	 * @param string                $title   Heading.
	 * @param string                $intro   Opening sentence.
	 * @param array<string, string> $facts   Label and value pairs.
	 * @param string                $message Plain team message.
	 * @param string                $url     Link.
	 * @param string                $note    Extra sentence.
	 * @return string
	 */
	private static function plain_from_parts( string $title, string $intro, array $facts, string $message, string $url, string $note ): string {
		$lines = array( $title, '', $intro, '' );

		foreach ( $facts as $label => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}

			$lines[] = $label . ': ' . $value;
		}

		$plain_message = lidev_email_plain( $message );

		if ( '' !== $plain_message ) {
			$lines[] = '';
			$lines[] = $plain_message;
		}

		if ( '' !== $url ) {
			$lines[] = '';
			$lines[] = $url;
		}

		if ( '' !== $note ) {
			$lines[] = '';
			$lines[] = $note;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Branded HTML and plain text for account mail: sign-in links, password resets, and invites.
	 *
	 * @since 1.8.0
	 * @param string $title   Heading.
	 * @param string $intro   Opening sentence.
	 * @param string $message Extra text.
	 * @param string $url     Button URL.
	 * @param string $button  Button label.
	 * @param string $note    Closing sentence.
	 * @return array{html: string, text: string}
	 */
	public static function branded( string $title, string $intro, string $message, string $url, string $button, string $note ): array {
		return array(
			'html' => self::document( $title, $intro, '', $message, $url, $button, $note ),
			'text' => self::plain_from_parts( $title, $intro, array(), $message, $url, $note ),
		);
	}

	/**
	 * Send one branded account email through the same mail path as a phase message.
	 *
	 * @since 1.8.0
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $title   Heading.
	 * @param string $intro   Opening sentence.
	 * @param string $message Extra text.
	 * @param string $url     Button URL.
	 * @param string $button  Button label.
	 * @param string $note    Closing sentence.
	 * @param string $type    Activity type.
	 * @return bool
	 */
	public static function send_branded( string $to, string $subject, string $title, string $intro, string $message, string $url, string $button, string $note, string $type ): bool {
		$built = self::branded( $title, $intro, $message, $url, $button, $note );

		return self::deliver( $to, $subject, $built['html'], $built['text'], $type, 0, null, array() );
	}

	/**
	 * Password reset mail uses the same layout as a phase email.
	 *
	 * @since 1.8.0
	 * @param array<string, mixed> $email      Mail parts.
	 * @param string               $key        Reset key. Not stored.
	 * @param string               $user_login Account login.
	 * @param WP_User              $user       Account.
	 * @return array<string, mixed>
	 */
	public static function brand_password_reset( array $email, string $key, string $user_login, $user ): array {
		unset( $user );

		$url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' );
		$built = self::branded(
			__( 'Reset your password', 'li-dev-theme' ),
			__( 'A password reset was requested for your Development Team account.', 'li-dev-theme' ),
			'',
			$url,
			__( 'Reset password', 'li-dev-theme' ),
			__( 'If you did not ask for this, you can ignore this email.', 'li-dev-theme' )
		);

		return self::as_html_mail( $email, $built );
	}

	/**
	 * New-account mail uses the same layout. The reset link already in the message is kept.
	 *
	 * @since 1.8.0
	 * @param array<string, mixed> $email    Mail parts.
	 * @param WP_User              $user     New account.
	 * @param string               $blogname Site name.
	 * @return array<string, mixed>
	 */
	public static function brand_new_user( array $email, $user, string $blogname ): array {
		unset( $user, $blogname );

		$url   = self::first_url( (string) ( $email['message'] ?? '' ) );
		$built = self::branded(
			__( 'Your account', 'li-dev-theme' ),
			__( 'An account is ready for you.', 'li-dev-theme' ),
			'',
			$url,
			'' !== $url ? __( 'Set your password', 'li-dev-theme' ) : '',
			__( 'This message was sent because an account was created for this address.', 'li-dev-theme' )
		);

		return self::as_html_mail( $email, $built );
	}

	/**
	 * Password-changed confirmation uses the same layout.
	 *
	 * @since 1.8.0
	 * @param array<string, mixed> $email    Mail parts.
	 * @param array<string, mixed> $user     Updated account fields.
	 * @param array<string, mixed> $userdata Previous account fields.
	 * @return array<string, mixed>
	 */
	public static function brand_password_changed( array $email, array $user, array $userdata ): array {
		unset( $user, $userdata );

		$built = self::branded(
			__( 'Password changed', 'li-dev-theme' ),
			__( 'The password for your account was just changed.', 'li-dev-theme' ),
			'',
			home_url( '/' ),
			__( 'Sign in', 'li-dev-theme' ),
			__( 'If you did not do this, reset your password from the sign-in page.', 'li-dev-theme' )
		);

		return self::as_html_mail( $email, $built );
	}

	/**
	 * @since 1.8.0
	 * @param array<string, mixed>     $email Mail parts.
	 * @param array{html: string, text: string} $built Branded bodies.
	 * @return array<string, mixed>
	 */
	private static function as_html_mail( array $email, array $built ): array {
		self::arm_alternative( $built['text'] );
		$email['message'] = $built['html'];
		$email['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );

		return $email;
	}

	/**
	 * @since 1.8.0
	 * @param string $text Plain alternative.
	 * @return void
	 */
	private static function arm_alternative( string $text ): void {
		self::$plain = $text;
		add_filter( 'wp_mail_from', array( self::class, 'filter_from' ) );
		add_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ) );
		add_action( 'phpmailer_init', array( self::class, 'filter_phpmailer' ) );
		add_action( 'phpmailer_init', array( self::class, 'disarm_alternative' ), PHP_INT_MAX );
	}

	/**
	 * @since 1.8.0
	 * @return void
	 */
	public static function disarm_alternative(): void {
		self::$plain = '';
		remove_filter( 'wp_mail_from', array( self::class, 'filter_from' ) );
		remove_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ) );
		remove_action( 'phpmailer_init', array( self::class, 'filter_phpmailer' ) );
		remove_action( 'phpmailer_init', array( self::class, 'disarm_alternative' ), PHP_INT_MAX );
	}

	/**
	 * @since 1.8.0
	 * @param string $text Message that may contain a link.
	 * @return string
	 */
	private static function first_url( string $text ): string {
		if ( ! preg_match( '#https?://[^\s<]+#', $text, $found ) ) {
			return '';
		}

		return rtrim( $found[0], '.)' );
	}
}

/**
 * Send the client sign-off email for a phase.
 *
 * @since 1.2.0
 * @param int $project_id Project ID.
 * @param int $phase_id   Phase row ID.
 * @return void
 */
function lidev_send_phase_email( int $project_id, int $phase_id ): void {
	Lidev_Mail::send_phase( $project_id, $phase_id );
}

/**
 * Run the reminder check now. WP-Cron calls the same method.
 *
 * @since 1.6.0
 * @return int
 */
function lidev_run_reminders(): int {
	return Lidev_Mail::send_due_reminders();
}
