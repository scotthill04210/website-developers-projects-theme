# 03 - Magic Link Login and Client Dashboard

Read `00-context.md` first. Stages 01 and 02 must be complete.

## Goal
Let clients get in with one click from an email, and show them a simple dashboard of their own projects.

## Build
1. **Home page sign-in** (the site's front page)
   - Two paths on one page: Team sign-in (standard username or email and password using WordPress auth) and Client access ("Enter your email and we'll send you a link").
   - Keep the existing theme styling. No other public content.
   - The front page itself renders the dashboard for the logged-in user's role (Team dashboard or Client dashboard). Logged-out visitors see the sign-in. Do not redirect to a separate dashboard URL.

2. **Magic link system**
   - Generate a token with `random_bytes(32)`. Store only a hash (`hash('sha256')`) with user ID, created time, expiry (30 days, from the setting), and last used time. Never store the raw token.
   - Link format: `/access/?token=...` handled by a template or rewrite rule. On a valid token: log the user in with `wp_set_auth_cookie`, log `magic_link_used`, and redirect to the front page (their dashboard), or to the specific phase if the link was issued for one.
   - Links are reusable until they expire (30 days). Each new email issues a fresh link. Expired or invalid tokens show a friendly page with the "email me a new link" form.
   - The client "email me a link" form must respond identically whether or not the email exists (no account enumeration). Rate limit it per IP and per email (for example 5 requests per hour).
   - Only `lidev_client` users can use magic links. Team accounts never use them.
   - Add a `lidev_issue_magic_link( $user_id, $phase_id = null )` helper for use by the phase email in stage 06.
   - Session: set a reasonable auth cookie lifetime so clients are not asked to log in again mid-review.

3. **Client dashboard** (rendered on the front page `/` for logged-in clients)
   - Shows only projects where the current user is an assigned client. Enforce this in the query, not just in the template.
   - Each project card: project name, domain, and the three phases in order with status (Not started, Ready for your review, Signed with date).
   - "Ready for your review" phases show a clear button that opens the sign-off screen (built in stage 04).
   - Signed phases show view and PDF download actions (PDF arrives in stage 05).
   - Clients cannot see the activity log, other clients, or Team views.

## Acceptance criteria
- A client with a valid link lands on their dashboard without typing a password.
- An expired, tampered, or reused-after-expiry token never logs anyone in.
- A client with two projects sees both and nothing else; a client with none sees an empty state.
- Direct requests for another client's project by ID return a 403 or 404.
- Include a manual test checklist covering expiry (use a test override for the lifetime).
