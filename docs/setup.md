# EasyHeadless Setup

## WordPress

Install the plugin folder at:

```text
wp-content/plugins/easyheadless-bridge
```

Activate **EasyHeadless Bridge** from the WordPress admin.

Optional integrations:

- Advanced Custom Fields
- Yoast SEO
- Fluent Forms
- WPGraphQL and WPGraphQL for ACF when a project needs GraphQL access outside the normalized EasyHeadless API

## Configuration

Go to **Settings > EasyHeadless** and configure:

- enabled modules
- public frontend and LMS URLs
- article path prefix (`stories` for Film Efiko; existing installations default to `insights`)
- login, registration, dashboard, and course catalogue URLs
- WordPress menu location
- headless routing
- ordered Media Library portfolio
- curated Tutor course IDs
- approved public Fluent Form IDs
- revalidation webhook and secret
- allowed frontend origins, one per line
- preview token, generated automatically on activation
- MCP API key, generated automatically on activation and regenerated manually when needed

Publication is visible under **EasyHeadless → Modules** and in the **Content** group of the EasyHeadless sidebar. Enable it to expose Homepage Sections and Readers & Contacts. An older deployed plugin must be upgraded before these entries can appear.

Editorial installations can additionally use **EasyHeadless → Homepage Sections** to configure each homepage section's displayed name, WordPress category or automatic feed, presentation style, story count, visibility, order, link, and selected story IDs.

Set **EasyHeadless → Site & URLs → Article path prefix** to the frontend's post route. Film Efiko uses `stories`, producing canonical URLs such as `/stories/article-slug`. With headless routing enabled, legacy WordPress post permalinks permanently redirect to that route; Publication issues redirect to `/issues/{slug}`.

Manage magazine editions directly under **EasyHeadless → Issues**. Edit an existing issue or create a new one, add its cover image, date and volume label, then select that issue from each article's **Cover / issue** field. The frontend's `/issues` archive lists editions, and opening an issue lists its assigned articles. Category settings control presentation only; category-to-issue conversion is no longer available. Previously configured category archive metadata is retained for compatibility.

In the post editor, open the **Publication** panel. **Magazine issue** lets you search by edition title or volume, select an issue and see its cover, date and status. **Presentation** controls this article's layout. **Reader access** shows preview paragraphs and the sign-in message only when “Verified email readers” is selected. Save/update the post to persist changes. Existing field storage and assignments remain compatible; if JavaScript is unavailable, the ordinary issue selector and access fields remain usable.

Allowed origins are used for CORS. Leave the field empty only for local experiments; production sites should list exact frontend origins.

### Film Efiko TV & Documentary

Keep **TV** (`tv`) and **Documentary** (`documentary`) as separate WordPress categories and assign the appropriate category when editing each post. No slug or article-permalink migration is needed. The frontend exposes separate archives at `/tv` and `/docu`, with matching menu/footer links. Old `/category/tv` and `/category/documentary` links redirect to these archives, preserving pagination.

After installing the updated Publication plugin, leave **Homepage Sections → TV & Documentary → Content source** on **Latest stories** to combine the newest stories from either category in one homepage block. Selected story IDs remain first and homepage deduplication still applies; unrelated stories are not used as automatic filler. An explicitly selected single-category source still uses that category. Save homepage curation and refresh the frontend cache. This does not change the currently live WordPress taxonomy or switch the public domain.

### Article hero styles

In **Posts → Edit post → Publication → Presentation → Article layout**, select **Standard**, **New Yorker Black** (white text on a black hero), or **New Yorker White** (black text on a white hero), then update the post. **Inherit from category** uses the category's presentation setting. Both New Yorker options use the same centered split hero and responsive layout; only hero colors change. The article body remains white. The existing `new-yorker` stored value means Black; White uses `new-yorker-white`. No content migration is needed.

### Efiko Score card (Film Efiko Custom 0.6.16+)

1. Install the updated Film Efiko Custom plugin and deploy the matching frontend.
2. Open **Posts → All Posts → Edit** on any article (the ordinary WordPress editor, not Elementor).
3. Find **Efiko Score** in the editor sidebar. In the block editor, enable this panel in Preferences → Panels if hidden; in the classic editor use Screen Options.
4. Enter **Score out of 10** (0–10, at most one decimal, e.g. 5.1) and **Verdict / description**. Click **Update** or **Publish**.
5. The card appears after the full article, before sharing. Its orange score box stays to the left of the verdict on mobile. No top badge is added, and no Reviews category is required.

Leave the score blank and update to remove the entire card; a saved description alone does not display it. A score of 0 is valid. A score without a description displays only the score box. Existing inline Elementor scores are not moved or removed automatically: remove duplicate inline verdicts manually if desired. Normal frontend cache revalidation must be configured for immediate updates; otherwise allow the story cache to refresh.

## API Endpoints

Base path:

```text
/wp-json/easyheadless/v1
```

Endpoints:

- `GET /site`
- `GET /capabilities`
- `GET /navigation`
- `GET /portfolio`
- `GET /courses`
- `GET /routes`
- `GET /page?slug=/services`
- `GET /posts`
- `GET /posts?slug=post-slug`
- `GET /forms`
- `GET /forms/{id}`
- `POST /forms/{id}/submit`
- `GET /health`

## Fluent Forms V1 Spike

### Film Efiko Contact page (dynamic Fluent Forms)

After updating the plugin and frontend, go to **Film Efiko Custom → Forms → Contact page form**, choose a standard Fluent Form and **Save public access**. Selecting it also approves its public schema. Choose **No form selected** to disable the contact form. Existing general public forms retain their approval checkboxes. An unavailable, deleted, unapproved or unsupported selected form shows an unavailable message; it never silently falls back to the old email handler.

Manage labels, required fields, options, default values, help text and notifications in **Fluent Forms**. The Contact page fetches the selected schema without caching, so updates appear on the next page load, without a rebuild. A form already open before a change asks the reader to reload rather than submitting against a different schema. Entries, validation and configured notifications run through Fluent Forms; Contact submissions no longer create reader/newsletter contacts or send a separate `wp_mail` message.

Supported: text, email, URL, numeric, telephone/mask, date, textarea, single/multi-select, radio, checkbox groups, consent, static hidden values, names and address groups, country lists, and fields inside column containers. Section breaks and custom HTML display as safe plain text rather than scripts/styles. Nested fields retain their Fluent Forms names and field-level errors. Layout is styled by the frontend rather than copied from the WordPress theme.

Payments, uploads, passwords/user-registration, CAPTCHA elements, conditional logic, multi-step/repeater/advanced Pro widgets and dynamic default shortcodes are not supported. They block selection instead of being silently discarded. Remove native CAPTCHA fields from this Contact form. Existing Fluent Forms validation and server anti-spam hooks are not bypassed.

Turnstile is optional: configure `NEXT_PUBLIC_TURNSTILE_SITE_KEY`, server-only `TURNSTILE_SECRET` (or `TURNSTILE_SECRET_KEY`) and exact `TURNSTILE_HOSTNAMES` on the frontend. With no keys, no widget or verification is used. Partial configuration fails closed. With full configuration, `/api/contact` verifies success, action `contact`, and hostname via Cloudflare Siteverify before calling the protected Publication endpoint. Tokens are reset after each attempt. No new widget or secret is created by this change. The selected contact form cannot be submitted through the generic public EasyHeadless forms endpoint; other forms are unchanged. Native WordPress/Fluent Forms endpoints retain their own security configuration.

Deploy the plugin first, select the form, then deploy the frontend. The existing `EASYHEADLESS_FRONTEND_SECRET` must match Publication's frontend secret. Test a submission and verify its entry and notification in Fluent Forms before launch. No live test email is sent automatically by the implementation.

The plugin includes a Fluent Forms adapter with defensive feature detection. It uses Fluent Forms classes/functions only when they are present and returns a clear `adapter_unavailable` response when the installed Fluent Forms version does not expose a safe submission pathway.

Acceptance for production use:

- submitted entries appear inside Fluent Forms
- plugin notifications still send
- validation errors return field-level messages
- anti-spam behavior is preserved
- exports continue to include headless submissions

If a specific Fluent Forms installation does not expose a stable safe submission API, use the adapter interface to add a project-specific submission bridge. File uploads, payments, and user-registration forms are outside the V1 public bridge.

## Next.js

Set:

```bash
EASYHEADLESS_API_URL=https://learn.example.com/wp-json/easyheadless/v1
NEXT_PUBLIC_EASYHEADLESS_API_URL=https://learn.example.com/wp-json/easyheadless/v1
EASYHEADLESS_REVALIDATION_SECRET=replace-me
EASYHEADLESS_PREVIEW_SECRET=replace-me
EASYHEADLESS_CONTACT_FORM_ID=fluentforms:1
NEXT_PUBLIC_SITE_URL=https://example.com
```

Run:

```bash
npm --workspace @easyheadless/accel-skills-hub run dev
```

The Accel app includes reviewed draft fallback content so preview builds remain usable before the staging bridge is connected.

## Manual Acceptance

- Edit a service or contact value in WordPress and confirm the frontend updates.
- Publish a blog post and confirm the route appears.
- View page source and confirm SEO title/meta/social tags render.
- Submit a form from the frontend and confirm the entry appears in the WordPress form plugin.

## Donation payment link (Film Efiko)

In **Film Efiko Custom → Publication → Donate URL**, paste the complete public HTTP(S) checkout/payment link from your preferred provider and click **Save publication settings**. The frontend reads `donationUrl` from the Publication settings endpoint and opens Donate in a new tab. Blank or invalid links hide the button. Allow up to five minutes for cached settings to refresh.

Payment collection, receipts and any post-payment return URL are managed by the payment provider. This link-only integration does not record donations or confirm payment in WordPress. Configure a return/thank-you destination in the provider's dashboard if supported. Test with the provider's sandbox link before accepting real payments.

For Paystack Payment Pages, set **Show advanced options → Redirect after payment** to `https://YOUR-FRONTEND-DOMAIN/donate/thank-you` after deploying the frontend. During Film Efiko staging, use `https://film-efiko-web.vercel.app/donate/thank-you`; replace it with the final public domain at cutover. This public page thanks the reader and links back to stories, the newsletter and contact. It does not validate a transaction reference, record a donation, or grant reader access. Keep Paystack's customer receipts enabled for actual payment confirmation. The route is marked noindex.

## Reader sign-in email

In **EasyHeadless → Publication → Reader sign-in email**, set the frontend URL (or use the existing Site & URLs value), publication name and optional public logo URL. Edit the subject, heading, message, button and footer there. Install the corresponding frontend `/login/magic` confirmation route before switching readers to magic links. During local testing, the admin frontend URL can point to `http://localhost:3001`; restore the public URL before production. Use a working WordPress mail transport. Link requests return delivery errors if `wp_mail` fails.

Signing in and joining use the same flow: an expiring email link confirms email ownership and creates a reader account only when needed. Existing reader accounts and sessions remain valid. Newsletter consent stays separate.

## Footer social links

Use **EasyHeadless → Site & URLs → Social media links** to enter LinkedIn, Instagram, YouTube, TikTok, and X / Twitter profile URLs. Existing additional networks remain editable. A blank URL removes that link. Save site settings; the public `GET /site` response exposes them as `social: [{label, url}]`. Frontends can refresh this public response on page load or rebuild to pick up changes. No API key is required to read these public links.
