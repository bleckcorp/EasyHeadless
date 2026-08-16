import assert from "node:assert/strict";
import { createServer } from "node:http";
import { fileURLToPath } from "node:url";
import test from "node:test";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";

test("publishes updater status, check, and explicit install tools", async () => {
  const server = createServer((request, response) => {
    response.setHeader("Content-Type", "application/json");
    response.end(JSON.stringify({ status: "ready", updateAvailable: false }));
  });
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
  const address = server.address();
  const serverPath = fileURLToPath(new URL("../dist/index.js", import.meta.url));
  const transport = new StdioClientTransport({
    command: "node",
    args: [serverPath],
    env: {
      ...process.env,
      EASYHEADLESS_API_URL: `http://127.0.0.1:${address.port}/wp-json/easyheadless/v1`,
      EASYHEADLESS_API_KEY: "updater-test-key",
    },
  });
  const client = new Client({ name: "easyheadless-updater-test", version: "0.4.0" });
  await client.connect(transport);

  try {
    const tools = await client.listTools();
    const names = tools.tools.map((tool) => tool.name);
    assert.ok(names.includes("easyheadless.get_updater_status"));
    assert.ok(names.includes("easyheadless.check_update"));
    assert.ok(names.includes("easyheadless.install_update"));
    const install = tools.tools.find((tool) => tool.name === "easyheadless.install_update");
    assert.match(install.description, /PRODUCTION MUTATION/);
    assert.deepEqual(install.inputSchema.properties, {});
  } finally {
    await client.close();
    await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
  }
});
