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
