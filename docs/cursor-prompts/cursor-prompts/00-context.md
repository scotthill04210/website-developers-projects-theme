# 00 - Shared Context (include with every prompt)

## What we're building
Functionality for the existing Local Image WordPress theme: a client sign-off system for website builds. The theme's visual design is already in place. Do not redesign it. Match its existing markup, CSS approach, and naming.

Before writing anything, inspect the theme: file structure, how templates and assets are organized, the CSS/JS build setup, and existing coding style. Follow those conventions. Known about the theme: it is a classic PHP theme with no build step. Styles are plain CSS (`style.css` and files in `assets/css/`), templates are `header.php`, `footer.php`, and `template-parts/`, and behavior is loaded from `functions.php`. Files use WordPress file headers and `declare(strict_types=1)`.

Put new PHP in `inc/signoffs/` and load it from `functions.php`. Put new styles in `assets/css/` and new templates in `template-parts/`. Do not introduce a build step.

## Branding and design guardrails
- Do not change the theme's existing branding or design: logo, colors, typography, spacing, header, footer, navigation, and existing templates and styles stay exactly as they are.
- Do not edit existing CSS rules in `style.css` or `assets/css/*`, and do not edit existing templates, unless a stage explicitly requires it. If you think an existing file must change, stop and ask first, and explain the smallest possible change.
- New screens (dashboards, forms, sign-off, login, access pages) must reuse the theme's existing colors, fonts, buttons, form styles, and layout patterns. Read how the theme already styles these before writing new CSS, and reuse its CSS variables or classes where they exist.
- Put all new styles in a new file (for example `assets/css/signoffs.css`), scoped under a wrapper class such as `.lidev-signoffs`, so nothing leaks into the rest of the site. Enqueue it only on the sign-off screens.
- Where the design doesn't cover a new element (signature pad, status chips, tables), keep it simple and consistent with the theme's look rather than introducing a new visual style.
- Emails and the PDF should use the theme's logo and brand colors.

## Vocabulary
- **Project**: one website build (project name, optional domain).
- **Phase**: one of three per project, in this order:
  1. `content` (Wireframe)
  2. `design` (Figma)
  3. `launch` (Ready to Launch, the finished site)
- **Team**: Local Image staff, role `lidev_team`. Sees all clients and projects.
- **Client**: end customer, role `lidev_client`. Sees only their own projects.

## Product rules (do not deviate)
- Single WordPress site. There is no public content.
- **Everything runs on the front end, not wp-admin.** The front page (`/`) is the sign-in for logged-out visitors, the Team dashboard for logged-in Team users, and the Client dashboard for logged-in clients. Project creation and editing, phase sending, settings, the activity log, sign-off, and PDF downloads are all front-end views styled by the theme. No Team or Client workflow may depend on a wp-admin screen. Team and Client users have no wp-admin access and no admin bar; only Administrators use wp-admin.
- The Team fills out a "send to client" form per phase. It captures an external link (wireframe, Figma, or staging site) and a message, then emails the client to prompt their sign-off.
- The first send form for a client email auto-creates a WordPress user with the `lidev_client` role. One client email can belong to many projects.
- Clients log in via magic link (30-day expiry) and see only their own projects and progress.
- Sign-off is approval only (no "request changes"). The client signs by typing or drawing a signature.
- A signed phase can be viewed and downloaded as a PDF but can never be unapproved, edited, or deleted by anyone, including admins in the UI.
- Every sign-off emails a single shared Team address (a setting).
- An append-only activity log records who did what and when.
- Reminders to unsigned clients every 3 days.

## Engineering standards
- Use WordPress-native APIs: custom post types, roles and capabilities, `$wpdb->prepare`, nonces, `wp_mail`, WP-Cron.
- Sanitize all input, escape all output, check capabilities and nonces on every action.
- Client data isolation is critical: a client must never be able to read another client's project, phase, signature, PDF, or log.
- Prefix everything with `lidev_` (functions, hooks, options, tables, CSS hooks where appropriate).
- No new dependencies unless a prompt calls for one. If you need one, say why first.
- Keep code readable and commented where intent isn't obvious.

## How to work
Complete only the stage you are given. When done, list the files you created or changed, how to test it manually, and anything you were unsure about. Do not start later stages.
