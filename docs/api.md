# EasyHeadless API Contract

All endpoints return JSON. Errors use WordPress REST error responses with stable error codes.

## `GET /site`

Returns global site data:

- `name`
- `description`
- `url`
- `frontendUrl`
- `lmsUrl`
- `portalLinks`
- `settings`
- `contact`
- `social`
- `health`
- `capabilities`

The public `settings` object omits the revalidation webhook URL and secret. Those are configuration secrets, not frontend settings.

## `GET /capabilities`

Returns `ready`, `degraded`, or `disabled` state for Core, Portfolio, Church, Tutor LMS, Forms, and the signed updater. Missing optional plugins never cause a fatal response.

## `GET /navigation`

Returns the selected WordPress menu as normalized `items`, with page and post links rewritten to the configured public frontend.

## `GET /portfolio`

Returns ordered Media Library attachments with responsive sources, dimensions, alt text, title, caption, category, and stored overrides.

## `PATCH /portfolio`

Authenticated. Updates attachment ordering and public metadata overrides without copying media.

## `GET /courses`

Returns normalized public Tutor LMS course cards. Query parameters:

- `selected` — comma-separated course IDs
- `category` — Tutor course category slug
- `page`
- `perPage`

If selected IDs are not supplied, the curated WordPress selection is used; otherwise the module falls back to latest published courses.

## `GET /courses/{slug}`

Returns one public course card. Lessons, credentials, learner records, and protected content are never included.

## `GET /routes`

Returns routable pages and posts:

- `type`
- `slug`
- `path`
- `title`
- `modified`

## `GET /page?slug=/about`

Returns a normalized page:

- `id`
- `slug`
- `title`
- `content`
- `excerpt`
- `featuredImage`
- `acf`
- `seo`
- `modified`

Use `previewToken` to fetch drafts:

```text
/page?slug=/about&preview=true&previewToken=...
```

## `GET /posts`

Returns posts. Query parameters:

- `page`
- `perPage`
- `slug`

Each normalized post also includes `date` (published ISO 8601 timestamp) and `categories` (objects with `id`, `name`, and `slug`). For pages and custom post types, `categories` is an empty list unless WordPress assigns the category taxonomy to that type. These fields are additive to the existing response.

## Publication module

### Optional Efiko Score per article

Every post has an optional **Efiko Score** editor panel with a score out of 10 (one decimal place) and a plain-text verdict/description. Categories do not enable it: an explicit valid score does. Blank scores produce `review: null` in Publication story responses; numeric zero remains a valid score. Frontends must render no score card when `review` is null or missing. A configured score adds `review: {score: 5.1, description: "The verdict"}`. These fields do not modify article HTML or automatically extract existing inline scores. Film Efiko renders only the bottom card, before sharing, with score and verdict side by side on desktop and mobile; it does not render a title badge.

Editorial API: `GET /publication/posts/id/{id}/review` inspects the editable values, including draft posts; `PATCH /publication/posts/id/{id}/review` accepts `{score: 5.1, description: "The verdict"}`. Both require the EasyHeadless API key (not a reader session or frontend secret), matching the existing issue-editing permission model. Unknown fields and invalid scores are rejected before saving. Omitted keys preserve existing values; `score: null` or `score: ""` clears the score and hides the feature, retaining the verdict for future editing. `description: ""` clears the verdict. The edit form uses a nonce and post-specific edit capability, skips autosaves and revisions, and never wipes metadata when its controls are absent. Updates notify the configured revalidation hooks; no category or existing body content changes.

### Reader magic-link authentication

Reader sign-in and registration share the same single-use email-link flow:

- `POST /publication/auth/request-link` accepts `email`, `redirect` (an `/account` or `/stories/{slug}` path), and the trusted frontend's `request_ip`. Returns `{sent:true, method:"magic-link", expiresIn:600}`. It never returns a token, even in debug mode.
- `POST /publication/auth/verify-link` accepts `email`, `token`, and optional `source_article`. Returns the existing `session_token`, expiry and reader account. Keep the token and frontend secret server-side, and put the session in an HttpOnly cookie.
- Legacy `/auth/request-code` and `/auth/verify-code` paths alias the link handlers. No numeric codes are generated or accepted. Frontends must switch their UI and verification payload to the link flow.

Configure the sign-in frontend URL in **EasyHeadless → Publication** (falls back to Site & URLs). The emailed URL uses `/login/magic#token=…&email=…&redirect=…`. A frontend confirmation page should capture and clear the fragment, then redeem the token with a POST only after the reader clicks Confirm. Opening a link with GET does not consume it, preventing mail scanners from accidentally signing in. The destination origin comes exclusively from admin configuration. Tokens are hashed at rest, expire after ten minutes, and are atomically consumed once. Requests are rate limited by email and IP.

Admins can edit the email publication name, logo URL, subject, heading, rich-text message, button label and footer. Subject, heading and message support `{site_name}`, `{email}`, `{expires_minutes}`. HTML messages are sanitized. The branded template always includes the sign-in button, expiration notice, fallback link, and newsletter-consent explanation. WordPress `wp_mail` sends the email; the site's SMTP/email provider remains responsible for delivery. Verified readers stay in Readers & Contacts and WordPress subscriber accounts; no newsletter consent is added by sign-in.

When the optional Publication module is enabled, its additive API is available under:

```text
/wp-json/easyheadless/v1/publication
```

`GET /homepage` returns the curated hero, supporting stories, and ordered homepage sections. Each section includes:

- `key` — stable frontend identity
- `title` — configured display title, selected WordPress category name, or default title
- `source` — `latest`, `popular`, or `category`
- `category` — selected WordPress category metadata, when configured
- `layout` — `rail`, `lead`, `feature`, `portrait`, `editorial-rail`, or `portrait-rail`
- `href` — configured link, selected category archive, or safe section fallback
- `stories` — selected stories followed by automatically sourced stories up to the configured limit

Popular feeds prioritise selected stories, then rank published articles by view events received during the preceding rolling 168 hours (UTC), independent of article publication date. Random published articles fill remaining slots. Existing homepage exclusion/category rules still apply. `POST /publication/views` records an anonymous timestamped event for a published post; unsuccessful storage returns 503 instead of claiming success. The event table is created automatically on first use after updating. It stores no reader identity and prunes events older than eight days during daily traffic-driven maintenance. Existing lifetime view totals remain available but are not used for the rolling ranking and cannot be backfilled into a dated history. Until dated events accumulate, random fallback keeps the feed populated. The frontend renders the Hot-Hot heading as text, while story links remain clickable.

Editors manage section names, content sources, categories, layouts, story counts, visibility, order, links, and manual story IDs in **EasyHeadless → Homepage Sections**. Existing core endpoints remain unchanged.

`GET /categories` and `GET /categories/{slug}` include `archiveMode`: `stories` or `issues`. Categories configured as issue archives should render the issue collection instead of their ordinary post archive.

`GET /issues` returns published Issue records. Pass `category={slug}` to restrict results to Issues assigned to one configured archive category. Each Issue includes its categories, cover image, date, volume label, description and automatically assembled `stories`. Story membership comes from the article's **Cover / issue** selection; the legacy curated IDs remain additive for backwards compatibility.

`GET /issues/{slug}` returns one Issue and its assigned stories. Issue routes are independent of the category slug, so changing an archive category's display name does not invalidate issue URLs.

### Authenticated issue editing

These routes require the EasyHeadless **API key**, not a reader frontend secret. The Publication module must be enabled. Existing public issue responses remain unchanged.

- `GET /publication/story-search?search=title&page=1&perPage=20` searches published post titles only. Returns `items` (ID, slug, title, featured image URL and attachment ID, current issue assignment) and `pagination`. It never returns article bodies. `perPage` is capped at 50.
- `POST /publication/issues` creates a separate `eh_issue` record, draft by default.
- `GET /publication/issues/id/{id}` inspects one edition, including drafts/private issues.
- `PATCH /publication/issues/id/{id}` updates only supplied fields.

Create/update fields: `title`, `slug`, HTML `description`, plain-text `excerpt`, `status` (`draft`, `pending`, `publish`, `private`), `date` (valid `YYYY-MM-DD` or empty to clear), `volume`, `featuredImageId`, `featuredStoryId`, `storyIds` (up to 100 ordered published post IDs). Creating requires a title. Covers reference existing image attachments; `0` clears cover/featured article. `storyIds: []` clears curated links. Omitted values are preserved. Unsupported fields and invalid dates/IDs are rejected before writing. Duplicate slugs, including drafts, return HTTP 409 with code `issue_slug_exists` and the existing `issueId` in error data.

Public and editorial Issue responses include `excerpt`, taken only from the manually authored WordPress Excerpt field. Empty excerpts stay empty; they are not generated from categories or the issue body. Use this field for archive summaries. `description` remains the full rendered issue content. Send `excerpt: ""` to clear a summary.

Editorial responses extend the public Issue object with `status`, `featuredImageId`, `featuredStoryId` and `storyIds` (curated list). `stories` also includes independently assigned articles. Saving curated links changes only the edition; original posts are not converted or rewritten. Inspect and merge `storyIds` before appending articles.

## `GET /forms`

Returns available form summaries:

- `id`
- `title`
- `provider`

## `GET /forms/{id}`

Returns normalized form schema:

- `id`
- `title`
- `provider`
- `fields`

## `POST /forms/{id}/submit`

Request:

```json
{
  "fields": {
    "email": "hello@example.com"
  }
}
```

## Authenticated Write Endpoints

Write endpoints require:

```http
Authorization: Bearer <EasyHeadless API key>
```

The API key is generated in **Settings > EasyHeadless**.

### `POST /settings`

Updates the native EasyHeadless site profile. Existing ACF values remain a read-only migration fallback.

```json
{
  "settings": {
    "company_name": "Example Co",
    "phone": "+1 555 0100"
  }
}
```

### `POST /collections/{type}`

Creates an item in an allowlisted collection. Supported collection types:

- `services`
- `testimonials`
- `faqs`
- `teamMembers`

### `PATCH /collections/{type}/{id}`

Updates an item in an allowlisted collection.

### `POST /posts`

Creates a blog post.

### `PATCH /posts/{id}`

Updates a blog post.

### `POST /pages/{id}/acf`

Updates page ACF data. V1 only writes the `sections` field. Use `previewOnly: true` to return current/proposed values without writing.

### Signed updater endpoints

- `GET /updater` returns non-sensitive readiness, version, and last-attempt data.
- `POST /updater/check` refreshes and verifies the configured signed manifest.
- `POST /updater/install` explicitly installs the trusted newer EasyHeadless
  release through WordPress core.

The two POST routes require the API key. They accept no plugin slug, package URL,
ZIP, signing key, or request body. See [updates.md](updates.md) for the release
and rollback model.

Success response:

```json
{
  "success": true,
  "confirmation": {
    "type": "message",
    "message": "Thanks for contacting us."
  }
}
```

Validation error response:

```json
{
  "code": "form_validation_failed",
  "message": "Please correct the highlighted fields.",
  "data": {
    "status": 422,
    "fieldErrors": {
      "email": ["Email is required."]
    }
  }
}
```
