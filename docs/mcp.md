# EasyHeadless MCP

The EasyHeadless MCP server gives AI coding tools controlled access to WordPress content through the EasyHeadless Bridge plugin.

## Architecture

```text
Codex / Claude / Cursor
  -> EasyHeadless MCP Server
  -> EasyHeadless Bridge REST API
  -> WordPress, ACF, Yoast, Fluent Forms
```

The MCP server is a Node/TypeScript sidecar. It does not run inside WordPress and it does not expose arbitrary PHP, theme, plugin, SQL, or delete operations.

## Setup

In WordPress:

1. Activate **EasyHeadless Bridge**.
2. Go to **Settings > EasyHeadless**.
3. Copy or regenerate the MCP API key.

In the repo:

```bash
npm install
npm run client:build
npm run mcp:build
```

Run locally over stdio:

```bash
EASYHEADLESS_API_URL=https://client.com/wp-json/easyheadless/v1 \
EASYHEADLESS_API_KEY=eh_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx \
npm --workspace @easyheadless/mcp-server run start
```

## Example MCP Client Config

Use the absolute path to this repo in your client config:

```json
{
  "mcpServers": {
    "easyheadless": {
      "command": "node",
      "args": ["/Users/bleckcorp/IdeaProjects/EasyHeadless/mcp-server/dist/index.js"],
      "env": {
        "EASYHEADLESS_API_URL": "https://client.com/wp-json/easyheadless/v1",
        "EASYHEADLESS_API_KEY": "eh_your_key_here"
      }
    }
  }
}
```

## Tools

Read tools:

- `easyheadless.health`
- `easyheadless.get_site`
- `easyheadless.get_capabilities`
- `easyheadless.get_navigation`
- `easyheadless.get_portfolio`
- `easyheadless.list_courses`
- `easyheadless.get_course`
- `easyheadless.list_routes`
- `easyheadless.get_page`
- `easyheadless.list_posts`
- `easyheadless.get_post`
- `easyheadless.list_issues`
- `easyheadless.get_issue`
- `easyheadless.search_stories` (API key required; title search and cover attachment IDs)
- `easyheadless.inspect_issue` (API key required; includes draft editions and editable fields)
- `easyheadless.list_forms`
- `easyheadless.get_form_schema`
- `easyheadless.get_collections`
- `easyheadless.get_seo_metadata`
- `easyheadless.get_updater_status`

Write tools:

- `easyheadless.update_site_settings`
- `easyheadless.update_modules`
- `easyheadless.update_portfolio`
- `easyheadless.create_service`
- `easyheadless.update_service`
- `easyheadless.create_testimonial`
- `easyheadless.update_testimonial`
- `easyheadless.create_faq`
- `easyheadless.update_faq`
- `easyheadless.create_team_member`
- `easyheadless.update_team_member`
- `easyheadless.update_page_sections`
- `easyheadless.assign_post_to_issue`
- `easyheadless.create_issue`
- `easyheadless.update_issue`
- `easyheadless.check_update` (verification only)
- `easyheadless.install_update` (explicit production mutation)

`easyheadless.update_page_sections` defaults to `previewOnly: true`. Set `previewOnly: false` only after reviewing the proposed ACF section payload.

### Manage editions without migrating posts

Use `search_stories` with a title to find published article IDs and existing Media Library `featuredImageId` values. Use `list_issues` to check published editions, then `create_issue` with a title, explicit slug, `date` (`YYYY-MM-DD`), volume, cover `featuredImageId`, `featuredStoryId` and ordered `storyIds`. Creation defaults to a **draft**; set `status: "publish"` explicitly to publish. Duplicate issue slugs return `issue_slug_exists` with the existing issue ID, including draft editions.

Use `inspect_issue` by ID before calling `update_issue`. Updates are partial: omitted fields are preserved. `storyIds` replaces the curated list, so merge existing IDs to append; `[]` clears it. Use `0` to clear the cover or featured article. Linked articles must be published posts; covers must be existing image attachments. These tools never convert, delete or rewrite source posts. Independent **Cover / issue** assignments remain additive; clearing curated IDs does not remove those assignments.

The plugin must include the matching editorial REST routes and the MCP process must be rebuilt/restarted before these tools can be used on an installed site. They do not expose SQL or migration operations.

## Resources

- `easyheadless://site`
- `easyheadless://routes`
- `easyheadless://capabilities`
- `easyheadless://navigation`
- `easyheadless://portfolio`
- `easyheadless://courses/{slug}`
- `easyheadless://pages/{slug}`
- `easyheadless://posts/{slug}`
- `easyheadless://forms/{id}`

## Prompts

- `audit_site_content`
- `generate_next_sections_from_wordpress`
- `check_frontend_route_coverage`
- `prepare_client_content_update`

## Safety Model

- API key required for all write endpoints.
- No delete tools in V1.
- No arbitrary PHP, theme, filesystem, or SQL access.
- Plugin updating is restricted to the configured, signed EasyHeadless release;
  tools cannot supply a slug, URL, ZIP, or trust key.
- Collection writes are restricted to services, testimonials, FAQs, and team members.
- Site settings writes are restricted to the native EasyHeadless profile.
- Tutor data is read-only.
- Portfolio writes store attachment IDs and metadata overrides only.
- Page ACF writes are restricted to the `sections` field.
