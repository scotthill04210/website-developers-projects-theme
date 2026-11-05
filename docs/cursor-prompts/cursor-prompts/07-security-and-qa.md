# 07 - Security Review and QA Pass

Read `00-context.md` first. Stages 01 to 06 must be complete.

## Goal
Review everything built so far as a skeptical security reviewer, fix what you find, and leave behind a test plan.

## Do
1. **Security review.** Go through every endpoint, form, AJAX or REST handler, rewrite rule, and template added in stages 01 to 06. For each, confirm:
   - Capability checks, nonces, and ownership checks (client is assigned to this project).
   - Input sanitized and output escaped; all SQL uses `$wpdb->prepare`.
   - No ID in a URL or form can be changed to reach another client's data (test project, phase, signoff, PDF, and log IDs).
   - Magic link tokens are stored hashed, expire, are rate limited, and do not leak through logs, referrers, or error messages. Add `Referrer-Policy: no-referrer` on the access page and make sure the token is not cached.
   - Uploaded or generated files are not directly reachable.
   - No code path updates or deletes a signoff or activity row, including wp-admin screens and bulk actions. Remove or lock any default admin UI that would expose them.
   - Team and client roles have only the capabilities listed in stage 01, and neither can reach wp-admin screens (test by direct URL). Confirm the whole workflow works with wp-admin unavailable to everyone but Administrators.

2. **Fix** every issue found. List what you changed and why.

3. **QA plan.** Write `docs/signoffs-test-plan.md` with step-by-step manual tests for:
   - Happy path for all three phases end to end (add project, send, magic link, sign, PDF, emails).
   - Two clients and two projects to prove isolation.
   - Expired link, invalid link, replayed submission, signing an unsent phase, signing twice.
   - Reminders (including pause, stop on sign, and the unanswered alert).
   - Hash chain verification.
   - Mobile and touch signing; common email clients rendering the templates.

4. **README.** Write `docs/signoffs-README.md` covering roles, settings, cron setup, where PDFs are stored, how to run the hash verification, and how to add a project.

## Acceptance criteria
- A written list of findings and fixes.
- The test plan and README exist and match the actual behavior.
- Nothing from later work or new features was added.
