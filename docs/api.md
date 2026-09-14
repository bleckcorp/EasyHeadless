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

## Publication module

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

Editors manage section names, content sources, categories, layouts, story counts, visibility, order, links, and manual story IDs in **EasyHeadless → Homepage Sections**. Existing core endpoints remain unchanged.

`GET /categories` and `GET /categories/{slug}` include `archiveMode`: `stories` or `issues`. Categories configured as issue archives should render the issue collection instead of their ordinary post archive.

`GET /issues` returns published Issue records. Pass `category={slug}` to restrict results to Issues assigned to one configured archive category. Each Issue includes its categories, cover image, date, volume label, description and automatically assembled `stories`. Story membership comes from the article's **Cover / issue** selection; the legacy curated IDs remain additive for backwards compatibility.

`GET /issues/{slug}` returns one Issue and its assigned stories. Issue routes are independent of the category slug, so changing an archive category's display name does not invalidate issue URLs.

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
