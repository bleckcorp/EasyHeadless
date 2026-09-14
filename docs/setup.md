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

For magazine-style collections, edit a WordPress category and set **Archive behaviour** to **List covers / issues**. Create each cover under **EasyHeadless → Issues**, assign it to that category, add its cover image, date and volume label, then select that issue from each article's **Cover / issue** field. The public category lists issues and each issue automatically lists its assigned articles. A guarded one-time checkbox on the category screen can convert legacy posts in that category to Issue records while preserving their IDs and content; take a database backup first.

Allowed origins are used for CORS. Leave the field empty only for local experiments; production sites should list exact frontend origins.

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
