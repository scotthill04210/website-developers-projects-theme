# 04 - Signature Sign-off

Read `00-context.md` first. Stages 01 to 03 must be complete.

## Goal
Build the sign-off screen where a client reviews a phase and signs it. The result is an immutable record.

## Build
1. **Sign-off screen** (client, per phase)
   - Shows project name, domain, phase name, the Team's message, and a prominent button to open the external link in a new tab (`rel="noopener noreferrer"`).
   - Log `phase_viewed` when opened.
   - Plain-language statement per phase, shown above the signature:
     - Content: "I approve the content and structure shown in the wireframe."
     - Design: "I approve the design shown in the Figma file."
     - Launch: "I approve this website to launch live."
   - Only available when the phase status is `awaiting`. Signed phases show a read-only view instead.

2. **Signature capture**
   - Required full name field.
   - Two modes in tabs: **Type** (renders the typed name in a signature-style font; bundle the font locally, no external font calls) and **Draw** (HTML canvas with mouse and touch support, clear button).
   - Required acknowledgement checkbox ("I understand this is a legally binding approval and cannot be undone").
   - Submit is disabled until the name, signature, and checkbox are complete. Validate again on the server.
   - Drawn signatures are submitted as a PNG data URL. Validate on the server: correct mime type, a sane size limit, and a non-blank image. Typed signatures store the name plus the font identifier.

3. **Server-side submission**
   - In a single transaction: verify nonce, verify the user is a client assigned to the project, verify the phase is `awaiting`, then insert the signoff record, set the phase to `signed` with `signed_at` and `signoff_id`.
   - Record: user, signer name, signature type and data, IP address, user agent, timestamp.
   - Compute `record_hash` as SHA-256 over a canonical string of the signed content (project, phase, external link, message, signer, signature data hash, timestamp) and set `prev_hash` to the previous signoff's hash. This chain makes tampering detectable.
   - Prevent double submission (idempotent; a second submit shows the signed view).
   - Log `phase_signed`.
   - Fire an action hook `lidev_phase_signed( $signoff_id )` that stages 05 and 06 will listen to. Do not send email or build the PDF in this stage.

4. **Confirmation screen**
   - Thank-you message, summary, and (once stage 05 exists) a download link.

## Acceptance criteria
- A client can sign an awaiting phase with a typed or a drawn signature.
- Another client, a logged-out user, or a Team user cannot sign.
- A second submit, a replayed request, or a request against a `not_sent` or `signed` phase fails safely.
- No UI or code path can unsign or edit a signed phase.
- Include a small verification function that re-computes the hash chain and reports any mismatch.
- Include a manual test checklist.
