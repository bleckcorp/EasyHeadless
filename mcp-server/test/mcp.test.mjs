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

        const result = await client.callTool({ name: "easyheadless.health", arguments: {} });
        assert.equal(result.content[0].type, "text");
        assert.match(result.content[0].text, /"version": "test"/);
      } finally {
        await client.close();
      }
    },
  );
});
