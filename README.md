# EasyHeadless

EasyHeadless is an agency toolkit for building fast, maintainable headless marketing websites with WordPress as the CMS backend and Next.js as the modern frontend.

---

## Architecture Overview

```text
┌─────────────────────────────────────────────────────────────┐
│                       Next.js Frontend                      │
│     (App Router, TypeScript, Tailwind, Static/SSR Pages)     │
└──────────────────────────────┬──────────────────────────────┘
                               │  REST API
┌──────────────────────────────▼──────────────────────────────┐
│                  EasyHeadless Bridge Plugin                 │
│       (WordPress REST API: Site, Routes, Forms, SEO, ACF)   │
└──────────────────────────────┬──────────────────────────────┘
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
┌───────────────────────┐             ┌───────────────────────┐
│ WordPress Core & Data │             │ EasyHeadless MCP Tool │
│ (Posts, Pages, Menus, │             │ (AI Coding Assistants:│
│ ACF, Yoast, Forms)    │             │ Claude, Cursor, etc.) │
└───────────────────────┘             └───────────────────────┘
```

---

## What is Included

- **`easyheadless-bridge/`**: WordPress plugin exposing normalized, cache-friendly REST endpoints for site settings, routes, pages, posts, forms, ACF custom fields, Yoast SEO metadata, and health checks.
- **`frontend/`**: Next.js (App Router) starter consuming the bridge via a typed client, rendering pages, blog posts, SEO tags, and headless forms out of the box.
- **`packages/client/`**: Shared TypeScript client library for typed interaction with the EasyHeadless REST API.
- **`mcp-server/`**: Model Context Protocol (MCP) server enabling AI tools (such as Claude Desktop, Cursor, etc.) to inspect and safely update WordPress content within bounded guardrails.
- **`docs/`**: Comprehensive guides for setup, REST API specifications, MCP configuration, signed releases, and production launch runbooks.
- **Release Security**: Built-in Ed25519 signature verification and SHA-256 integrity checks for safe, authenticated remote plugin updates with WordPress-native rollback safeguards.

---

## V1 Scope

EasyHeadless V1 focuses on high-performance small-business and agency marketing websites:
- Landing pages and service pages
- Dynamic sections via ACF Flexible Content
- Blog posts, authors, and categories
- Testimonials, FAQs, and team member collections
- Headless contact forms (Fluent Forms integration)
- Media Library portfolio management
- Curated Tutor LMS public course cards
- Complete Yoast SEO metadata parity (OpenGraph, Twitter Cards, Schema)

---

## Quick Start

### 1. WordPress Plugin Setup

1. Copy or symlink `easyheadless-bridge` into your WordPress plugins directory:
   ```bash
   cp -r easyheadless-bridge /path/to/wordpress/wp-content/plugins/
   ```
2. Activate **EasyHeadless Bridge** in **WordPress Admin > Plugins**.
3. (Optional but recommended) Activate **Advanced Custom Fields (ACF)**, **Yoast SEO**, and **Fluent Forms**.
4. Go to **Settings > EasyHeadless** to configure:
   - Enabled modules (Core, Portfolio, Forms, Tutor LMS)
   - Public frontend URL and CORS allowed origins
   - Menu locations and revalidation webhook secrets

### 2. Next.js Frontend Setup

1. Copy the environment variables template:
   ```bash
   cp frontend/.env.example frontend/.env.local
   ```
2. Set your WordPress API URL in `frontend/.env.local`:
   ```bash
   NEXT_PUBLIC_EASYHEADLESS_API_URL=https://your-wordpress-site.com/wp-json/easyheadless/v1
   ```
3. Install dependencies and run the development server:
   ```bash
   npm install
   npm --workspace frontend run dev
   ```
4. Open [http://localhost:3000](http://localhost:3000) to view the frontend.

### 3. MCP Server (AI-Assisted Content Workflows)

To connect AI coding tools (Claude Desktop, Cursor, etc.) to your EasyHeadless instance:

1. Build the client and MCP server packages:
   ```bash
   npm run client:build
   npm run mcp:build
   ```
2. Configure your MCP client with the server path and credentials:
   ```json
   {
     "mcpServers": {
       "easyheadless": {
         "command": "node",
         "args": ["/path/to/EasyHeadless/mcp-server/dist/index.js"],
         "env": {
           "EASYHEADLESS_API_URL": "https://your-wordpress-site.com/wp-json/easyheadless/v1",
           "EASYHEADLESS_API_KEY": "eh_your_generated_api_key"
         }
       }
     }
   }
   ```
   *(See [docs/mcp.md](docs/mcp.md) for available tools, resources, and prompts.)*

---

## Development & Testing Scripts

| Command | Description |
|---|---|
| `npm run test:php` | Run PHP smoke, admin menu/asset, updater, and Fluent Forms test suites |
| `npm run lint:php` | Lint all PHP files in the plugin |
| `npm run client:build` | Build the `@easyheadless/client` TypeScript package |
| `npm run client:typecheck` | Typecheck the client library |
| `npm run mcp:build` | Build the `@easyheadless/mcp-server` package |
| `npm run mcp:typecheck` | Typecheck the MCP server |
| `npm run frontend:typecheck` | Typecheck the Next.js frontend |
| `npm run frontend:lint` | Run Next.js ESLint checks |
| `npm run package:plugin` | Create a standalone distribution ZIP of `easyheadless-bridge` |

---

## Documentation Index

- **[Setup & Acceptance Guide](docs/setup.md)**: WordPress configuration, environment variables, and manual acceptance checklists.
- **[REST API Reference](docs/api.md)**: Complete endpoint contract for site, navigation, portfolio, routes, pages, posts, and forms.
- **[MCP Server Guide](docs/mcp.md)**: Architecture, tools, resources, and safety boundaries for AI agents.
- **[Signed Updates & Security](docs/updates.md)**: Ed25519 release signing, manifest format, and rollback safeguards.
- **[Accel Launch Runbook](docs/accel-launch-runbook.md)**: Production headless migration, domain routing, and cutover procedures.

---

## License

Internal Agency Toolkit / MIT License.

