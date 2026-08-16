# EasyHeadless

EasyHeadless is an internal agency toolkit for building fast headless marketing sites with WordPress as the client CMS and Next.js as the frontend.

## What is included

- `easyheadless-bridge`: a WordPress plugin that exposes normalized REST endpoints for site settings, routes, pages, posts, forms, plugin health, ACF content, and Yoast SEO metadata.
- `frontend`: a Next.js starter that consumes the bridge through a typed API client and renders pages, posts, SEO metadata, and headless forms.
- `mcp-server`: an MCP server that lets AI tools inspect and safely update EasyHeadless WordPress content.
- `packages/client`: a shared TypeScript client used by MCP-side tooling.
- `docs`: setup and implementation notes for WordPress, ACF, Yoast, Fluent Forms, previews, CORS, and deployment.
- Signed EasyHeadless-only releases with compatibility checks, integrity
  verification, WordPress-native rollback safeguards, and explicit MCP control.

## V1 scope

EasyHeadless V1 targets small-business marketing websites: landing pages, service pages, blog posts, testimonials, team members, FAQs, contact details, and forms. E-commerce, memberships, bookings, portals, and complex app workflows are intentionally out of scope.

## Quick Start

1. Copy `easyheadless-bridge` into `wp-content/plugins/easyheadless-bridge`.
2. Activate **EasyHeadless Bridge** in WordPress.
3. Install and activate ACF. Yoast SEO and Fluent Forms are optional but supported.
4. Configure allowed frontend origins in **Settings > EasyHeadless**.
5. Copy `frontend/.env.example` to `frontend/.env.local` and set `NEXT_PUBLIC_EASYHEADLESS_API_URL`.
6. Run the frontend:

```bash
npm install
npm --workspace frontend run dev
```

See [docs/setup.md](docs/setup.md) for detailed setup and acceptance checks.

See [docs/mcp.md](docs/mcp.md) for MCP setup and AI-tool access.

See [docs/updates.md](docs/updates.md) before enabling remote production updates.
