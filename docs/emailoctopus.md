# EmailOctopus newsletter adapter

The adapter listens to existing Publication newsletter opt-in and opt-out hooks. It does not alter reader authentication, public responses or the article editor. It is disabled by default.

## WordPress setup

1. Install the plugin build containing `class-easyheadless-emailoctopus.php`.
2. Open **Film Efiko Custom / EasyHeadless → EmailOctopus** (administrator access).
3. Enter a v2 API key and Save. The saved key is never echoed back into the form or public REST. Alternatively define `EASYHEADLESS_EMAILOCTOPUS_API_KEY` in `wp-config.php`; the constant takes precedence. Stored keys use a non-autoloaded WordPress option; restrict database/backups access.
4. **Fetch lists using saved key**, choose/paste a destination ID and Save. Lists are fetched only on request (first 100; other IDs may be pasted).
5. If necessary, **Create and select list** creates a named list. Verify double opt-in in EmailOctopus. Creation is never automatically retried: check the provider after an uncertain timeout before creating again.
6. Enable sync and Save. This does not bulk-export older contacts.
7. Submit an approved new test email through the website newsletter form. Confirm it is in WordPress **Readers & Contacts** and the selected EmailOctopus list with the correct pending/subscribed state. Do not use sign-in as a newsletter test.

## Delivery and consent

Explicit newsletter subscription stores WordPress consent first, then attempts the provider immediately. A non-autoloaded, per-email/list job is saved before the request. Retries re-read the current local consent; deleted contacts and jobs for an old destination are discarded. Sign-in/contact forms do not enqueue sync. WordPress opt-out sends `unsubscribed` to the selected list. Provider opt-outs are not pulled into WordPress: the adapter never changes existing contacts back to subscribed, and local consent is not proof of provider subscription.

New subscribers are created with POST `/lists/{id}/contacts`, using `pending` when the list has double opt-in, otherwise `subscribed`. HTTP 409 is treated as an existing contact without changing its status. Opt-out uses PUT `/lists/{id}/contacts`. Bearer authentication is sent only to the fixed `https://api.emailoctopus.com` host with redirects disabled. API calls use v2, not legacy v1.6.

Transport failures, invalid JSON, HTTP 429 and 5xx responses retry with exponential backoff (eight attempts maximum, six-hour maximum delay). Other HTTP errors remain failed until an admin corrects settings and chooses **Retry saved jobs**. Job data stays in WordPress, not public REST or logs. Admin status messages omit email addresses, key material and raw provider responses. No campaigns are sent by this adapter; provider automations or double-opt-in messages may run when contacts are added.

Configure reliable WP-Cron execution; visitor-driven cron alone can delay retries on quiet sites. Install/deploy and a live end-to-end test are separate from local automated checks. The provider was returning HTTP 503 during development, so live v2 response/duplicate/confirmation behavior remains an acceptance gate before enabling production sync. No API key is included in source.
