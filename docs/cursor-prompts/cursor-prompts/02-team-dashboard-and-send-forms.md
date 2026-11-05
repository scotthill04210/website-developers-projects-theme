# 02 - Team Dashboard and Phase Send Forms

Read `00-context.md` first. Stage 01 must be complete.

## Goal
Give Team members a front-end dashboard and the forms that send each phase to a client.

## Build

### Part A - Remove stage 01's wp-admin UI (do this first)
Stage 01 is already built and added some wp-admin screens. We want everything on the front end, so before building the new views:
1. **Project post type:** set `show_ui`, `show_in_menu`, and `show_in_admin_bar` to false for `lidev_project`. Keep the meta fields and the data layer. Move the logic that creates the three phase rows and logs `project_created` out of any wp-admin save hook into a plain, idempotent function, for example `lidev_create_project( $args )`, that the front-end Add project form below will call. Calling it twice for the same project must never duplicate phase rows or log entries.
2. **Settings:** remove the wp-admin settings screen (Projects, Sign-off settings). Keep the options and the helper that reads them, with their defaults. Add the capability `lidev_manage_settings`, granted to Administrators by default.
3. **Roles:** Team users get the same lockout as Clients: redirect them away from wp-admin to the front page and hide the admin bar. Only Administrators keep wp-admin. Keep `admin-ajax.php` and `admin-post.php` working for Team and Client users, since front-end forms post to them.
4. Remove any other wp-admin-only UI from stage 01 (menus, meta boxes, list columns). Do not touch the tables, repositories, activity log, or append-only behavior.

### Part B - Build the front-end Team experience
1. **Team dashboard** (rendered on the front page `/` when a `lidev_team` user is logged in; no redirect to another URL)
   - Lists all clients and their projects with phase progress (three phase chips per project showing Not sent, Awaiting, or Signed with the date).
   - Search or filter by client name, email, project, or status.
   - Each project links to a Project detail view.
   - "Add project" action.

2. **Add project form**
   - Fields: project name (required), domain (optional, with gentle prompt), client name, client email (required).
   - On submit: create the `lidev_project`, create three phase rows, and find or create the client user (see below). Log everything.

3. **Client user creation**
   - If the email has no user, create one with role `lidev_client`, a random unusable password, and the display name provided. If it exists and is a client, attach it to the project. If it exists with another role, refuse and show a clear error.
   - Allow more than one client user per project (for example a co-owner) by adding another email on the project detail view.

4. **Project detail view** (Team)
   - Header with project name, domain, client(s).
   - Three phase panels in order: Content (Wireframe), Design (Figma), Ready to Launch.
   - Each panel shows status, the external link, the message sent, sent date, sent by, and for signed phases the signer, date, and a PDF download link placeholder (the PDF arrives in stage 05).
   - A collapsible activity log for the project, newest first.

5. **Phase send form** (per phase, in its panel)
   - Fields: external link (required, validated URL, with a label that matches the phase: "Wireframe link", "Figma link", "Staging site link"), message to client (optional).
   - Submit moves the phase from `not_sent` to `awaiting`, stores the link and message, logs `phase_sent`, and calls a stub `lidev_send_phase_email()` that stage 06 will implement.
   - Allow the Team to re-send (resend email, update link or message) while the phase is `awaiting`. Log it.
   - Block sending a phase once it is `signed`.
   - Do not hard-lock later phases behind earlier ones; the Team controls timing. Show a soft warning if sending Design before Content is signed.

6. **Front-end only**
   - Add project, edit project (name, domain, client emails), the project detail view, the phase send forms, and the activity log are all front-end views. Use clean front-end URLs (for example `/project/{id}/`) via rewrite rules or the theme's routing, and flush rewrite rules on activation.
   - Add a front-end **Settings** view (Team email, reminder interval, magic link lifetime, From name and address) reachable from the dashboard, visible only to users with `lidev_manage_settings`. Remove the wp-admin settings screen if one exists.
   - Handle form posts with `admin-post.php` or `admin-ajax.php` handlers, or the REST API with a nonce, all with capability checks. Using those endpoints does not count as using wp-admin screens.

## Acceptance criteria
- A Team user can add and edit a project, see it on the front-page dashboard, and send each phase without ever opening wp-admin.
- A client user cannot load any Team view or endpoint (test by direct URL and AJAX).
- All forms use nonces, capability checks, sanitization, and escaping.
- Include a manual test checklist.
