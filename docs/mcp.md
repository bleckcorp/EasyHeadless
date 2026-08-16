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
- `easyheadless.check_update` (verification only)
- `easyheadless.install_update` (explicit production mutation)

`easyheadless.update_page_sections` defaults to `previewOnly: true`. Set `previewOnly: false` only after reviewing the proposed ACF section payload.

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
