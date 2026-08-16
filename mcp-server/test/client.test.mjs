import assert from "node:assert/strict";
import { createServer } from "node:http";
import test from "node:test";
import { EasyHeadlessClient } from "../../packages/client/dist/index.js";

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = "";
    request.setEncoding("utf8");
    request.on("data", (chunk) => {
      body += chunk;
    });
    request.on("end", () => resolve(body ? JSON.parse(body) : {}));
    request.on("error", reject);
  });
}

async function withMockServer(handler, run) {
  const server = createServer(handler);
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
  const address = server.address();
  const apiUrl = `http://127.0.0.1:${address.port}/wp-json/easyheadless/v1`;

  try {
    await run(apiUrl);
  } finally {
    await new Promise((resolve, reject) => {
      server.close((error) => (error ? reject(error) : resolve()));
    });
  }
}

test("reads routes from EasyHeadless", async () => {
  await withMockServer(
    (request, response) => {
      assert.equal(request.url, "/wp-json/easyheadless/v1/routes");
      response.setHeader("Content-Type", "application/json");
      response.end(JSON.stringify([{ type: "page", slug: "home", path: "/", title: "Home", modified: "2026-01-01T00:00:00Z" }]));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl });
      const routes = await client.listRoutes();
      assert.equal(routes[0].path, "/");
    },
  );
});

test("sends bearer auth for write requests", async () => {
  await withMockServer(
    async (request, response) => {
      assert.equal(request.url, "/wp-json/easyheadless/v1/collections/services");
      assert.equal(request.headers.authorization, "Bearer test-key");
      const body = await readBody(request);
      assert.equal(body.title, "Strategy");
      response.setHeader("Content-Type", "application/json");
      response.end(JSON.stringify({ id: 10, slug: "strategy", path: "/services/strategy", type: "eh_service", title: "Strategy", content: "", excerpt: "", featuredImage: null, acf: {}, seo: {}, modified: "2026-01-01T00:00:00Z" }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl, apiKey: "test-key" });
      const service = await client.createCollectionItem("services", { title: "Strategy" });
      assert.equal(service.id, 10);
    },
  );
});

test("blocks write requests without API key", async () => {
  const client = new EasyHeadlessClient({ apiUrl: "http://127.0.0.1:1/wp-json/easyheadless/v1" });
  await assert.rejects(
    () => client.createCollectionItem("services", { title: "Strategy" }),
    /EASYHEADLESS_API_KEY is required/,
  );
});

test("reads capabilities, navigation, portfolio, and Tutor courses", async () => {
  await withMockServer(
    (request, response) => {
      response.setHeader("Content-Type", "application/json");
      if (request.url === "/wp-json/easyheadless/v1/capabilities") {
        response.end(JSON.stringify({ core: { enabled: true, available: true, status: "ready", message: "" } }));
        return;
      }
      if (request.url === "/wp-json/easyheadless/v1/navigation") {
        response.end(JSON.stringify({ items: [{ id: 1, label: "About", url: "/about" }] }));
        return;
      }
      if (request.url === "/wp-json/easyheadless/v1/portfolio") {
        response.end(JSON.stringify({ items: [{ id: 8, attachmentId: 8, title: "Launch" }] }));
        return;
      }
      assert.equal(request.url, "/wp-json/easyheadless/v1/courses?category=automation&page=1&perPage=3");
      response.end(JSON.stringify({ items: [{ id: 20, slug: "automation", title: "Automation" }], pagination: { page: 1, perPage: 3, total: 1, totalPages: 1 }, source: "category" }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl });
      assert.equal((await client.getCapabilities()).core.status, "ready");
      assert.equal((await client.getNavigation()).items[0].label, "About");
      assert.equal((await client.getPortfolio()).items[0].attachmentId, 8);
      assert.equal((await client.listCourses({ category: "automation", page: 1, perPage: 3 })).items[0].slug, "automation");
    },
  );
});

test("updates portfolio with bearer auth", async () => {
  await withMockServer(
    async (request, response) => {
      assert.equal(request.url, "/wp-json/easyheadless/v1/portfolio");
      assert.equal(request.method, "PATCH");
      assert.equal(request.headers.authorization, "Bearer portfolio-key");
      const body = await readBody(request);
      assert.equal(body.items[0].attachmentId, 42);
      response.setHeader("Content-Type", "application/json");
      response.end(JSON.stringify({ success: true, items: body.items }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl, apiKey: "portfolio-key" });
      const result = await client.updatePortfolio([{ attachmentId: 42, title: "Campaign", caption: "", alt: "Campaign still", category: "film" }]);
      assert.equal(result.items[0].title, "Campaign");
    },
  );
});

test("updates optional modules with bearer auth", async () => {
  await withMockServer(
    async (request, response) => {
      assert.equal(request.url, "/wp-json/easyheadless/v1/modules");
      assert.equal(request.method, "POST");
      assert.equal(request.headers.authorization, "Bearer module-key");
      const body = await readBody(request);
      assert.equal(body.modules.church, false);
      response.setHeader("Content-Type", "application/json");
      response.end(JSON.stringify({ success: true, modules: { core: true, church: false }, capabilities: {} }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl, apiKey: "module-key" });
      const result = await client.updateModules({ church: false });
      assert.equal(result.modules.church, false);
    },
  );
});

test("updates approved forms with bearer auth", async () => {
  await withMockServer(
    async (request, response) => {
      assert.equal(request.url, "/wp-json/easyheadless/v1/forms/approved");
      assert.equal(request.method, "PATCH");
      assert.equal(request.headers.authorization, "Bearer forms-key");
      const body = await readBody(request);
      assert.deepEqual(body.ids, ["fluentforms:1"]);
      response.setHeader("Content-Type", "application/json");
      response.end(JSON.stringify({ success: true, ids: body.ids, forms: [{ id: "fluentforms:1", title: "Contact Form" }] }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl, apiKey: "forms-key" });
      const result = await client.updateApprovedForms(["fluentforms:1"]);
      assert.deepEqual(result.ids, ["fluentforms:1"]);
    },
  );
});

test("checks and installs only through updater-specific authenticated routes", async () => {
  const seen = [];
  await withMockServer(
    (request, response) => {
      seen.push({ url: request.url, method: request.method, authorization: request.headers.authorization });
      response.setHeader("Content-Type", "application/json");
      if (request.url.endsWith("/updater/install")) {
        response.end(JSON.stringify({ success: true, status: { success: true }, updater: { currentVersion: "0.4.1", updateAvailable: false } }));
        return;
      }
      response.end(JSON.stringify({ currentVersion: "0.4.0", latestVersion: "0.4.1", updateAvailable: true }));
    },
    async (apiUrl) => {
      const client = new EasyHeadlessClient({ apiUrl, apiKey: "update-key" });
      assert.equal((await client.getUpdaterStatus()).updateAvailable, true);
      assert.equal((await client.checkUpdate()).latestVersion, "0.4.1");
      assert.equal((await client.installUpdate()).updater.currentVersion, "0.4.1");
    },
  );
  assert.deepEqual(seen.map((item) => item.url), [
    "/wp-json/easyheadless/v1/updater",
    "/wp-json/easyheadless/v1/updater/check",
    "/wp-json/easyheadless/v1/updater/install",
  ]);
  assert.equal(seen[0].authorization, undefined);
  assert.equal(seen[1].authorization, "Bearer update-key");
  assert.equal(seen[2].authorization, "Bearer update-key");
});
