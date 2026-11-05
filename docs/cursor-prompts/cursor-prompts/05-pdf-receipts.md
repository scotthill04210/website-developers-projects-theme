# 05 - Signed PDF Documents

Read `00-context.md` first. Stages 01 to 04 must be complete.

## Goal
Generate a PDF for every sign-off, store it privately, and let the right people download it.

## Build
1. **PDF library**
   - Check what is already available in the theme or the server. If nothing suits, propose adding Dompdf via Composer (vendor folder committed or built per the project's convention) and explain the choice before adding it.

2. **PDF contents**
   - Local Image branding (use the theme's logo and colors).
   - Title: "Approval Record".
   - Project name, domain, phase name, the approval statement the client agreed to, the external link that was reviewed, and the Team's message.
   - The signature (typed in the signature font, or the drawn image), signer name and email, date and time with timezone, and the record hash.
   - A footer with the signoff ID and a note that the record is stored and verifiable.

3. **Storage and access**
   - Generate on the `lidev_phase_signed` hook and store in a private directory under uploads with an `.htaccess` deny rule (and an `index.php`), using unguessable filenames. Support nginx setups by also routing downloads through PHP so direct file access is never required.
   - Downloads go through an authenticated endpoint: Team can download any PDF; a client can download only PDFs for their own projects. Log `pdf_downloaded`.
   - If the file is missing, regenerate it from the stored signoff record (the record is the source of truth).

4. **Where downloads appear**
   - Client dashboard and sign-off confirmation screen.
   - Team project detail view next to each signed phase.

## Acceptance criteria
- Signing generates a PDF automatically; the file is not reachable by direct URL.
- A client cannot download another client's PDF by changing an ID.
- Regenerating a PDF produces the same content (including the original timestamp and hash).
- Include a manual test checklist.
