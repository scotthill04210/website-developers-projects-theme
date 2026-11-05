# 01 - Foundation: Roles, Project Post Type, Tables, Activity Log

Read `00-context.md` first.

## Goal
Create the data layer everything else depends on.

## Build
1. **Roles and capabilities**
   - `lidev_team`: can view all projects, create and edit projects, send phases, view the activity log. Cannot manage plugins, themes, or users beyond creating clients. No wp-admin access and no admin bar.
   - `lidev_client`: read-only access to their own projects. No wp-admin access; redirect them away from wp-admin and hide the admin bar.
   - Define custom capabilities (for example `lidev_manage_projects`, `lidev_view_all_projects`, `lidev_view_own_projects`) rather than relying on role names in code.
   - Add roles on theme activation and keep them in sync if the theme is updated.

2. **Project custom post type** `lidev_project`
   - Fields: project name (post title), domain (optional), assigned client user IDs (multiple), created by.
   - Not public, not in site search or archives. No wp-admin menu or editing screen (`show_ui` false). All management happens on the front end (stage 02).
   - The domain field should be easy to fill in and gently prompted ("Add the domain if you have one") but not required.

3. **Custom tables** (create with `dbDelta` on activation, with a schema version option for upgrades)
   - `{prefix}lidev_phases`: id, project_id, type (`content|design|launch`), status (`not_sent|awaiting|signed`), external_link, team_message, sent_at, sent_by, last_reminder_at, reminder_count, signed_at, signoff_id.
   - `{prefix}lidev_signoffs`: id, project_id, phase_id, user_id, signer_name, signature_type (`typed|drawn`), signature_data, record_hash, prev_hash, ip_address, user_agent, signed_at.
   - `{prefix}lidev_activity`: id, actor_user_id (null for system), actor_label, action, project_id, phase_id, details (JSON), ip_address, created_at.
   - Three phase rows are created automatically (status `not_sent`) when a project is created.

4. **Append-only enforcement**
   - Write a small repository class for signoffs and activity with insert and read methods only. No update or delete methods for signoffs, and none for activity.
   - Phase rows may be updated only through defined transitions (`not_sent` to `awaiting` to `signed`). Reject any transition out of `signed`.
   - `lidev_log( $action, $project_id, $phase_id, $details )` helper that fills in actor and IP.

5. **Settings**
   - Store settings as options with a helper to read them (the front-end settings view is built in stage 02, gated by a `lidev_manage_settings` capability that Administrators have by default). Settings: shared Team notification email address, reminder interval (default 3 days), magic link lifetime (default 30 days).

## Acceptance criteria
- Activating the theme creates roles, tables, and the post type without errors; re-activating is safe.
- Creating a project creates three phase rows and logs `project_created`.
- No code path exists that updates or deletes a signoff or activity row.
- A `lidev_client` user cannot reach wp-admin.
- Include a short test checklist in your final message.
