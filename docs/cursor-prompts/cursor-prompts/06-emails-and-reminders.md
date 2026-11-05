# 06 - Emails, Reminders, and Activity Log Completion

Read `00-context.md` first. Stages 01 to 05 must be complete.

## Goal
Send all the system emails and the 3-day reminders, and make sure every action is in the activity log.

## Build
1. **Email templates** (HTML plus plain-text fallback, using `wp_mail`, branded to match the theme)
   - **Client invite / phase ready**: sent when the Team sends a phase. Includes project name, phase name, the Team's message, and a button using a fresh magic link (`lidev_issue_magic_link`) that takes the client straight to that phase's sign-off screen. Mention when the link expires.
   - **Reminder**: same structure, friendlier subject, new magic link.
   - **Team alert**: sent to the shared Team address from settings when any phase is signed. Includes client name, project, phase, time, and a link to the project detail view.
   - **Signed receipt**: sent to the client and the Team address with the PDF attached.
   - Implement the stub `lidev_send_phase_email()` from stage 02. Re-sends from stage 02 use the same function.
   - Subject lines should be clear and include the project name.

2. **Reminders**
   - Schedule a recurring WP-Cron event (twice daily is fine) that finds phases with status `awaiting` where `last_reminder_at` (or `sent_at` if none) is at least the configured interval (default 3 days) old.
   - Send a reminder, update `last_reminder_at`, increment `reminder_count`, and log `reminder_sent`.
   - Stop reminders the moment a phase is signed.
   - After 3 unanswered reminders, send the Team an "unanswered phase" alert once, and keep reminding the client only if the Team has not turned reminders off for that phase. Add a per-phase "pause reminders" toggle on the Team project view.
   - Note in the README that WP-Cron depends on site traffic and recommend a real server cron calling `wp-cron.php`.

3. **Deliverability**
   - Use the site's configured mail transport; do not hard-code SMTP credentials. Make the From name and address a setting.
   - Log `email_sent` with recipient and type. Log failures as `email_failed`, and surface failures on the Team project view.

4. **Activity log completeness**
   - Audit all prior stages and confirm every event is logged: project created or edited, client user created, phase sent or re-sent, emails sent or failed, magic link issued and used, phase viewed, phase signed, PDF generated and downloaded, reminders sent or paused.
   - Ensure the Team activity view shows actor, action, phase, and timestamp in the site's timezone, with filters by project and action type.

## Acceptance criteria
- Sending a phase emails the client a working magic link to the sign-off screen.
- Signing triggers the Team alert and the receipt with the PDF attached.
- Reminders fire on schedule (provide a way to run the check manually for testing) and stop after signing.
- Email content escapes all dynamic values.
- Include a manual test checklist.
