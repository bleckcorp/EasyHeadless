import assert from "node:assert/strict";
import { createServer } from "node:http";
import { fileURLToPath } from "node:url";
import test from "node:test";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";

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

test("starts MCP server, lists tools, and calls health", async () => {
  await withMockServer(
    (request, response) => {
      if (request.url === "/wp-json/easyheadless/v1/health") {
        response.setHeader("Content-Type", "application/json");
        response.end(JSON.stringify({ version: "test", plugins: { acf: true, wpGraphql: false, yoast: true, forms: {} } }));
        return;
      }

      response.statusCode = 404;
      response.end(JSON.stringify({ code: "not_found", message: "Not found" }));
    },
    async (apiUrl) => {
      const serverPath = fileURLToPath(new URL("../dist/index.js", import.meta.url));
      const transport = new StdioClientTransport({
        command: "node",
        args: [serverPath],
        env: {
          ...process.env,
          EASYHEADLESS_API_URL: apiUrl,
        },
      });
      const client = new Client({ name: "easyheadless-test", version: "0.1.0" });

      await client.connect(transport);

      try {
        const tools = await client.listTools();
        const toolNames = tools.tools.map((tool) => tool.name);
        assert.ok(toolNames.includes("easyheadless.health"));
        assert.ok(toolNames.includes("easyheadless.create_service"));
        assert.ok(toolNames.includes("easyheadless.get_capabilities"));
        assert.ok(toolNames.includes("easyheadless.get_navigation"));
        assert.ok(toolNames.includes("easyheadless.get_portfolio"));
        assert.ok(toolNames.includes("easyheadless.update_portfolio"));
        assert.ok(toolNames.includes("easyheadless.update_approved_forms"));
        assert.ok(toolNames.includes("easyheadless.update_modules"));
        assert.ok(toolNames.includes("easyheadless.list_courses"));
        assert.ok(toolNames.includes("easyheadless.get_course"));
        assert.ok(toolNames.includes("easyheadless.list_issues"));
        assert.ok(toolNames.includes("easyheadless.get_issue"));
        assert.ok(toolNames.includes("easyheadless.assign_post_to_issue"));
        for (const name of ["search_stories", "inspect_issue", "create_issue", "update_issue"]) {
          assert.ok(toolNames.includes(`easyheadless.${name}`));
        }

        const result = await client.callTool({ name: "easyheadless.health", arguments: {} });
        assert.equal(result.content[0].type, "text");
        assert.match(result.content[0].text, /"version": "test"/);
      } finally {
        await client.close();
      }
    },
  );
});

test("issue MCP tools forward only validated input to authenticated editorial routes", async () => {
  const seen = [];
  await withMockServer(async (request, response) => {
    assert.equal(request.headers.authorization, "Bearer issue-key");
    let raw = "";
    for await (const chunk of request) raw += chunk;
    const body = raw ? JSON.parse(raw) : {};
    seen.push({ method: request.method, url: request.url, body });
    response.setHeader("Content-Type", "application/json");
    response.end(JSON.stringify(request.url.includes("story-search")
      ? { items: [{ id: 10, title: "Article", featuredImageId: 99 }], pagination: {} }
      : { id: 42, status: "draft", storyIds: [10], featuredImageId: 99 }));
  }, async (apiUrl) => {
    const transport = new StdioClientTransport({
      command: "node", args: [fileURLToPath(new URL("../dist/index.js", import.meta.url))],
      env: { ...process.env, EASYHEADLESS_API_URL: apiUrl, EASYHEADLESS_API_KEY: "issue-key" },
    });
    const client = new Client({ name: "issue-test", version: "0.1.0" });
    await client.connect(transport);
    try {
      for (const [name, args] of [
        ["search_stories", { search: "Article" }],
        ["create_issue", { title: "Volume 1", featuredImageId: 99, storyIds: [10] }],
        ["inspect_issue", { id: 42 }],
        ["update_issue", { id: 42, volume: "Volume 1" }],
      ]) {
        const result = await client.callTool({ name: `easyheadless.${name}`, arguments: args });
        assert.ok(!result.isError);
        assert.ok(JSON.parse(result.content[0].text));
      }
      for (const args of [{}, { title: "" }, { title: "Issue", storyIds: [0] }]) {
        const result = await client.callTool({ name: "easyheadless.create_issue", arguments: args });
        assert.equal(result.isError, true);
      }
    } finally { await client.close(); }
  });
  assert.equal(seen.length, 4, "Invalid tool inputs cannot reach WordPress");
  assert.deepEqual(seen[1], {
    method: "POST", url: "/wp-json/easyheadless/v1/publication/issues",
    body: { title: "Volume 1", featuredImageId: 99, storyIds: [10] },
  });
  assert.deepEqual(seen[3].body, { volume: "Volume 1" }, "Sparse updates do not erase omitted values");
});
