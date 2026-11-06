# LI Dev Theme

Client sign-off for Local Image website builds. Team and Client work happens on the site. Only Administrators use wp-admin.

## Reminders

WordPress cron sends due reminders twice a day on the hook `lidev_send_reminders`. That only runs when the site receives a visit. For a reliable schedule, add a server cron that requests `wp-cron.php` every 15 minutes, for example:

```
*/15 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null
```

The check can also be run by hand from Sign-off settings, or with `wp eval 'lidev_run_reminders();'`.

## Updates

Administrators install a newer copy from Appearance → Themes or Dashboard → Updates. The package is the `main` branch of https://github.com/scotthill04210/website-developers-projects-theme. Push to `main` to publish a change. WordPress looks for a new commit about every half hour, and Dashboard → Updates → Check again looks immediately.
