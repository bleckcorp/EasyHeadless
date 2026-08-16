#!/usr/bin/env node
import { EasyHeadlessClient, type CollectionType, type WritePostInput } from "@easyheadless/client";
import { McpServer, ResourceTemplate } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const apiUrl = process.env.EASYHEADLESS_API_URL;
const apiKey = process.env.EASYHEADLESS_API_KEY;

if (!apiUrl) {
  throw new Error("EASYHEADLESS_API_URL is required.");
}

const client = new EasyHeadlessClient({ apiUrl, apiKey });

const server = new McpServer({
  name: "easyheadless",
  version: "0.4.2",
});

const collectionTypeSchema = z.enum([
  "services",
  "testimonials",
  "faqs",
  "teamMembers",
  "churchSettings",
  "sermons",
  "events",
  "ministries",
  "leaders",
  "serviceTimes",
  "policies",
]);
const acfSchema = z.record(z.string(), z.unknown()).optional();
const writePostSchema = {
  title: z.string().optional(),
  content: z.string().optional(),
  excerpt: z.string().optional(),
  status: z.enum(["draft", "pending", "publish", "private"]).optional(),
  acf: acfSchema,
};

function textResult(value: unknown) {
  return {
    content: [
      {
        type: "text" as const,
        text: typeof value === "string" ? value : JSON.stringify(value, null, 2),
      },
    ],
  };
}

function writeInput(input: z.infer<z.ZodObject<typeof writePostSchema>>): WritePostInput {
  return {
    title: input.title,
    content: input.content,
    excerpt: input.excerpt,
    status: input.status,
    acf: input.acf,
  };
}

function registerChurchWriteTools(type: CollectionType, singular: string, title: string) {
  server.registerTool(
    `easyheadless.create_${singular}`,
    {
      title: `Create ${title}`,
      description: `Create an EasyHeadless church ${title.toLowerCase()} item. Requires EASYHEADLESS_API_KEY.`,
      inputSchema: writePostSchema,
    },
    async (input) => textResult(await client.createCollectionItem(type, writeInput(input))),
  );

  server.registerTool(
    `easyheadless.update_${singular}`,
    {
      title: `Update ${title}`,
      description: `Update an EasyHeadless church ${title.toLowerCase()} item. Requires EASYHEADLESS_API_KEY.`,
      inputSchema: {
        id: z.number().int().positive(),
        ...writePostSchema,
      },
    },
    async ({ id, ...input }) => textResult(await client.updateCollectionItem(type, id, writeInput(input))),
  );
}

async function readResource(uri: URL, value: unknown) {
  return {
    contents: [
      {
        uri: uri.href,
        mimeType: "application/json",
        text: JSON.stringify(value, null, 2),
      },
    ],
  };
}

server.registerTool(
  "easyheadless.health",
  {
    title: "Check EasyHeadless Health",
    description: "Read EasyHeadless plugin health and detected WordPress integrations.",
    inputSchema: {},
  },
  async () => textResult(await client.health()),
);

server.registerTool(
  "easyheadless.get_site",
  {
    title: "Get Site",
    description: "Read normalized site settings, contact details, collections, and plugin health.",
    inputSchema: {},
  },
  async () => textResult(await client.getSite()),
);

server.registerTool(
  "easyheadless.get_capabilities",
  {
    title: "Inspect Capabilities",
    description: "Inspect enabled EasyHeadless modules and any degraded dependencies.",
    inputSchema: {},
  },
  async () => textResult(await client.getCapabilities()),
);

server.registerTool(
  "easyheadless.get_navigation",
  {
    title: "Get Navigation",
    description: "Read the normalized public navigation configured in WordPress.",
    inputSchema: {},
  },
  async () => textResult(await client.getNavigation()),
);

server.registerTool(
  "easyheadless.get_portfolio",
  {
    title: "Get Portfolio",
    description: "Read the ordered WordPress Media Library portfolio.",
    inputSchema: {},
  },
  async () => textResult(await client.getPortfolio()),
);

server.registerTool(
  "easyheadless.get_updater_status",
  {
    title: "Get Updater Status",
    description: "Inspect signed EasyHeadless release configuration, compatibility, and the last update result without changing WordPress.",
    inputSchema: {},
  },
  async () => textResult(await client.getUpdaterStatus()),
);

server.registerTool(
  "easyheadless.check_update",
  {
    title: "Check Signed Update",
    description: "Force-refresh and verify the trusted EasyHeadless release manifest. Requires EASYHEADLESS_API_KEY; does not install anything.",
    inputSchema: {},
  },
  async () => textResult(await client.checkUpdate()),
);

server.registerTool(
  "easyheadless.install_update",
  {
    title: "Install Signed EasyHeadless Update",
    description: "PRODUCTION MUTATION: install only the latest compatibility-checked, signature-verified EasyHeadless release through WordPress core. Requires EASYHEADLESS_API_KEY. No arbitrary URL or ZIP is accepted.",
    inputSchema: {},
  },
  async () => textResult(await client.installUpdate()),
);

server.registerTool(
  "easyheadless.update_portfolio",
  {
    title: "Update Portfolio",
    description: "Update portfolio ordering and per-image metadata. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      items: z.array(z.object({
        attachmentId: z.number().int().positive(),
        title: z.string(),
        caption: z.string(),
        alt: z.string(),
        category: z.string(),
      })),
    },
  },
  async ({ items }) => textResult(await client.updatePortfolio(items)),
);

server.registerTool(
  "easyheadless.update_approved_forms",
  {
    title: "Update Approved Forms",
    description: "Approve the exact provider form IDs that may be read and submitted through the public EasyHeadless API. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      ids: z.array(z.string().regex(/^[a-z0-9_-]+:[0-9]+$/)).default([]),
    },
  },
  async ({ ids }) => textResult(await client.updateApprovedForms(ids)),
);

server.registerTool(
  "easyheadless.list_courses",
  {
    title: "List Tutor Courses",
    description: "Read normalized public Tutor LMS courses. Learner and protected lesson data are never exposed.",
    inputSchema: {
      selected: z.array(z.number().int().positive()).optional(),
      category: z.string().optional(),
      page: z.number().int().positive().default(1),
      perPage: z.number().int().positive().max(50).default(6),
    },
  },
  async (input) => textResult(await client.listCourses(input)),
);

server.registerTool(
  "easyheadless.get_course",
  {
    title: "Get Tutor Course",
    description: "Read one normalized public Tutor LMS course by slug.",
    inputSchema: { slug: z.string() },
  },
  async ({ slug }) => textResult(await client.getCourse(slug)),
);

server.registerTool(
  "easyheadless.get_church",
  {
    title: "Get Church Settings",
    description: "Read church-specific settings, leaders, policies, and service times.",
    inputSchema: {},
  },
  async () => textResult(await client.getChurch()),
);

server.registerTool(
  "easyheadless.list_routes",
  {
    title: "List Routes",
    description: "List routable WordPress pages and posts exposed by EasyHeadless.",
    inputSchema: {},
  },
  async () => textResult(await client.listRoutes()),
);

server.registerTool(
  "easyheadless.get_page",
  {
    title: "Get Page",
    description: "Read a normalized WordPress page by slug or path.",
    inputSchema: {
      slug: z.string().describe("Page path such as '/', '/about', or 'services'."),
    },
  },
  async ({ slug }) => textResult(await client.getPage(slug)),
);

server.registerTool(
  "easyheadless.list_posts",
  {
    title: "List Posts",
    description: "List published WordPress posts.",
    inputSchema: {
      page: z.number().int().positive().default(1),
      perPage: z.number().int().positive().max(50).default(10),
    },
  },
  async ({ page, perPage }) => textResult(await client.listPosts(page, perPage)),
);

server.registerTool(
  "easyheadless.get_post",
  {
    title: "Get Post",
    description: "Read a normalized WordPress post by slug.",
    inputSchema: {
      slug: z.string(),
    },
  },
  async ({ slug }) => textResult(await client.getPost(slug)),
);

server.registerTool(
  "easyheadless.list_forms",
  {
    title: "List Forms",
    description: "List supported WordPress forms exposed by EasyHeadless.",
    inputSchema: {},
  },
  async () => textResult(await client.listForms()),
);

server.registerTool(
  "easyheadless.get_form_schema",
  {
    title: "Get Form Schema",
    description: "Read a normalized form schema by EasyHeadless form ID.",
    inputSchema: {
      id: z.string(),
    },
  },
  async ({ id }) => textResult(await client.getForm(id)),
);

server.registerTool(
  "easyheadless.get_collections",
  {
    title: "Get Collections",
    description: "Read reusable ACF-backed collections from the site payload.",
    inputSchema: {
      type: collectionTypeSchema.optional(),
    },
  },
  async ({ type }) => {
    const site = await client.getSite();
    return textResult(type ? site.collections[type] : site.collections);
  },
);

server.registerTool(
  "easyheadless.list_sermons",
  {
    title: "List Sermons",
    description: "List church sermon entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listSermons()),
);

server.registerTool(
  "easyheadless.get_sermon",
  {
    title: "Get Sermon",
    description: "Read a church sermon by slug.",
    inputSchema: {
      slug: z.string(),
    },
  },
  async ({ slug }) => textResult(await client.getSermon(slug)),
);

server.registerTool(
  "easyheadless.list_events",
  {
    title: "List Events",
    description: "List church event entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listEvents()),
);

server.registerTool(
  "easyheadless.list_ministries",
  {
    title: "List Ministries",
    description: "List church ministry entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listMinistries()),
);

server.registerTool(
  "easyheadless.list_leaders",
  {
    title: "List Leaders",
    description: "List church leader entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listLeaders()),
);

server.registerTool(
  "easyheadless.list_service_times",
  {
    title: "List Service Times",
    description: "List church service time entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listServiceTimes()),
);

server.registerTool(
  "easyheadless.list_policies",
  {
    title: "List Policies",
    description: "List church policy entries.",
    inputSchema: {},
  },
  async () => textResult(await client.listPolicies()),
);

server.registerTool(
  "easyheadless.get_seo_metadata",
  {
    title: "Get SEO Metadata",
    description: "Read Yoast-normalized SEO metadata for a page or post.",
    inputSchema: {
      kind: z.enum(["page", "post"]),
      slug: z.string(),
    },
  },
  async ({ kind, slug }) => {
    const entry = kind === "page" ? await client.getPage(slug) : await client.getPost(slug);
    return textResult(entry.seo);
  },
);

server.registerTool(
  "easyheadless.update_site_settings",
  {
    title: "Update Site Settings",
    description: "Safely update allowlisted ACF site settings. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      settings: z.record(z.string(), z.unknown()),
    },
  },
  async ({ settings }) => textResult(await client.updateSettings(settings)),
);

server.registerTool(
  "easyheadless.update_modules",
  {
    title: "Update Modules",
    description: "Enable or disable optional EasyHeadless modules. Core always remains enabled. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      portfolio: z.boolean().optional(),
      church: z.boolean().optional(),
      tutor: z.boolean().optional(),
      forms: z.boolean().optional(),
    },
  },
  async (modules) => textResult(await client.updateModules(modules)),
);

registerChurchWriteTools("churchSettings", "church_settings", "Church Settings");
registerChurchWriteTools("sermons", "sermon", "Sermon");
registerChurchWriteTools("events", "event", "Event");
registerChurchWriteTools("ministries", "ministry", "Ministry");
registerChurchWriteTools("leaders", "leader", "Leader");
registerChurchWriteTools("serviceTimes", "service_time", "Service Time");
registerChurchWriteTools("policies", "policy", "Policy");

server.registerTool(
  "easyheadless.create_service",
  {
    title: "Create Service",
    description: "Create an EasyHeadless service collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: writePostSchema,
  },
  async (input) => textResult(await client.createCollectionItem("services", writeInput(input))),
);

server.registerTool(
  "easyheadless.update_service",
  {
    title: "Update Service",
    description: "Update an EasyHeadless service collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      id: z.number().int().positive(),
      ...writePostSchema,
    },
  },
  async ({ id, ...input }) => textResult(await client.updateCollectionItem("services", id, writeInput(input))),
);

server.registerTool(
  "easyheadless.create_testimonial",
  {
    title: "Create Testimonial",
    description: "Create an EasyHeadless testimonial collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: writePostSchema,
  },
  async (input) => textResult(await client.createCollectionItem("testimonials", writeInput(input))),
);

server.registerTool(
  "easyheadless.update_testimonial",
  {
    title: "Update Testimonial",
    description: "Update an EasyHeadless testimonial collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      id: z.number().int().positive(),
      ...writePostSchema,
    },
  },
  async ({ id, ...input }) => textResult(await client.updateCollectionItem("testimonials", id, writeInput(input))),
);

server.registerTool(
  "easyheadless.create_faq",
  {
    title: "Create FAQ",
    description: "Create an EasyHeadless FAQ collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: writePostSchema,
  },
  async (input) => textResult(await client.createCollectionItem("faqs", writeInput(input))),
);

server.registerTool(
  "easyheadless.update_faq",
  {
    title: "Update FAQ",
    description: "Update an EasyHeadless FAQ collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      id: z.number().int().positive(),
      ...writePostSchema,
    },
  },
  async ({ id, ...input }) => textResult(await client.updateCollectionItem("faqs", id, writeInput(input))),
);

server.registerTool(
  "easyheadless.create_team_member",
  {
    title: "Create Team Member",
    description: "Create an EasyHeadless team member collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: writePostSchema,
  },
  async (input) => textResult(await client.createCollectionItem("teamMembers", writeInput(input))),
);

server.registerTool(
  "easyheadless.update_team_member",
  {
    title: "Update Team Member",
    description: "Update an EasyHeadless team member collection item. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      id: z.number().int().positive(),
      ...writePostSchema,
    },
  },
  async ({ id, ...input }) => textResult(await client.updateCollectionItem("teamMembers", id, writeInput(input))),
);

server.registerTool(
  "easyheadless.update_page_sections",
  {
    title: "Update Page Sections",
    description: "Preview or update the ACF sections array for a WordPress page. Requires EASYHEADLESS_API_KEY.",
    inputSchema: {
      id: z.number().int().positive(),
      sections: z.array(z.record(z.string(), z.unknown())),
      previewOnly: z.boolean().default(true),
    },
  },
  async ({ id, sections, previewOnly }) => {
    const result = await client.updatePageAcf(id, { sections }, previewOnly);
    return textResult(result);
  },
);

server.registerResource(
  "site",
  "easyheadless://site",
  {
    title: "EasyHeadless Site",
    description: "Normalized site settings and collections.",
    mimeType: "application/json",
  },
  async (uri) => readResource(uri, await client.getSite()),
);

server.registerResource(
  "routes",
  "easyheadless://routes",
  {
    title: "EasyHeadless Routes",
    description: "WordPress pages and posts available to the frontend.",
    mimeType: "application/json",
  },
  async (uri) => readResource(uri, await client.listRoutes()),
);

server.registerResource(
  "capabilities",
  "easyheadless://capabilities",
  {
    title: "EasyHeadless Capabilities",
    description: "Enabled modules, dependency availability, and degraded states.",
    mimeType: "application/json",
  },
  async (uri) => readResource(uri, await client.getCapabilities()),
);

server.registerResource(
  "navigation",
  "easyheadless://navigation",
  {
    title: "EasyHeadless Navigation",
    description: "Normalized public WordPress navigation.",
    mimeType: "application/json",
  },
  async (uri) => readResource(uri, await client.getNavigation()),
);

server.registerResource(
  "portfolio",
  "easyheadless://portfolio",
  {
    title: "EasyHeadless Portfolio",
    description: "Ordered public portfolio media.",
    mimeType: "application/json",
  },
  async (uri) => readResource(uri, await client.getPortfolio()),
);

server.registerResource(
  "course",
  new ResourceTemplate("easyheadless://courses/{slug}", { list: undefined }),
  {
    title: "EasyHeadless Course",
    description: "Normalized public Tutor LMS course.",
    mimeType: "application/json",
  },
  async (uri, { slug }) => readResource(uri, await client.getCourse(String(slug))),
);

server.registerResource(
  "page",
  new ResourceTemplate("easyheadless://pages/{slug}", { list: undefined }),
  {
    title: "EasyHeadless Page",
    description: "Normalized page by slug.",
    mimeType: "application/json",
  },
  async (uri, { slug }) => readResource(uri, await client.getPage(String(slug))),
);

server.registerResource(
  "post",
  new ResourceTemplate("easyheadless://posts/{slug}", { list: undefined }),
  {
    title: "EasyHeadless Post",
    description: "Normalized post by slug.",
    mimeType: "application/json",
  },
  async (uri, { slug }) => readResource(uri, await client.getPost(String(slug))),
);

server.registerResource(
  "form",
  new ResourceTemplate("easyheadless://forms/{id}", { list: undefined }),
  {
    title: "EasyHeadless Form",
    description: "Normalized form schema by ID.",
    mimeType: "application/json",
  },
  async (uri, { id }) => readResource(uri, await client.getForm(String(id))),
);

server.registerPrompt(
  "audit_site_content",
  {
    title: "Audit Site Content",
    description: "Review EasyHeadless site content for missing small-business marketing essentials.",
    argsSchema: {
      focus: z.string().optional(),
    },
  },
  ({ focus }) => ({
    messages: [
      {
        role: "user",
        content: {
          type: "text",
          text: `Use EasyHeadless MCP tools to audit the WordPress site content. Check routes, settings, services, testimonials, FAQs, team members, forms, and SEO metadata.${focus ? ` Focus on: ${focus}.` : ""}`,
        },
      },
    ],
  }),
);

server.registerPrompt(
  "generate_next_sections_from_wordpress",
  {
    title: "Generate Next.js Sections From WordPress",
    description: "Use live WordPress data to plan or implement frontend sections.",
    argsSchema: {
      pageSlug: z.string(),
    },
  },
  ({ pageSlug }) => ({
    messages: [
      {
        role: "user",
        content: {
          type: "text",
          text: `Fetch EasyHeadless page '${pageSlug}', site settings, and collections. Generate a Next.js section implementation that preserves WordPress as the source of editable content.`,
        },
      },
    ],
  }),
);

server.registerPrompt(
  "check_frontend_route_coverage",
  {
    title: "Check Frontend Route Coverage",
    description: "Compare WordPress routes against the frontend route implementation.",
    argsSchema: {},
  },
  () => ({
    messages: [
      {
        role: "user",
        content: {
          type: "text",
          text: "Use EasyHeadless routes and inspect the frontend app routes. Report missing, stale, or mismatched routes.",
        },
      },
    ],
  }),
);

server.registerPrompt(
  "prepare_client_content_update",
  {
    title: "Prepare Client Content Update",
    description: "Draft a safe content update plan before using write tools.",
    argsSchema: {
      request: z.string(),
    },
  },
  ({ request }) => ({
    messages: [
      {
        role: "user",
        content: {
          type: "text",
          text: `Prepare a safe EasyHeadless content update plan for this client request: ${request}. Read current content first, prefer previewOnly for page section changes, and avoid delete operations.`,
        },
      },
    ],
  }),
);

const transport = new StdioServerTransport();
await server.connect(transport);
