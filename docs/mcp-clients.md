# Connecting an MCP client — section 5

The MCP Adapter is the diagnostic port. This page is how you plug something
into it.

Everything below is verified against **MCP Adapter 0.7.0** on WordPress 7.1.2,
protocol version **2025-11-25**, unless it says otherwise.

## What the port exposes

There is no MCP code in this repo. Activate the adapter and you get one server:

| Server | Endpoint | Tools |
|---|---|---|
| `mcp-adapter-default-server` | `/wp-json/mcp/mcp-adapter-default-server` | Three meta-tools |

```bash
npm run cli -- plugin activate mcp-adapter
npm run cli -- mcp-adapter list
```

The default server advertises three generic tools and makes the client
*discover* abilities through them:

```
mcp-adapter-discover-abilities
mcp-adapter-get-ability-info
mcp-adapter-execute-ability
```

It exposes every ability with `meta.public => true` — both of ours set it, so
they are reachable with no extra code. On a stock WordPress 7.1 install
discover returns **five** abilities: three from core, and our two.

That keeps `tools/list` tiny on a site with 200 abilities, at the cost of a
round trip before anything runs. The alternative is a custom server —
`create_server()` in an `mcp_adapter_init` hook, naming the abilities you want
— which puts them in `tools/list` as first-class tools with their schemas
already in the client. That is the context-size question from section 5; it is
on the slide, not in the code.

## Authentication

Every request needs credentials. Use an
[application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/):

```bash
npm run cli -- user application-password create admin "mcp client" --porcelain 2>/dev/null
```

Copy the 24-character result. Without it you get `rest_forbidden` with a 401.

> Application passwords normally require HTTPS. wp-env reports its environment
> type as `local`, which is why they work over plain HTTP here.

---

## Option 1: MCP Inspector — the neutral tester

Best choice for the live demo: no vendor, no model, just the protocol.

```bash
npm run inspector
```

In the Inspector UI:

- **Transport:** Streamable HTTP
- **URL:** `http://localhost:8888/wp-json/mcp/mcp-adapter-default-server`
- **Header name:** `Authorization`
- **Header value:** `Basic <base64 of admin:YOUR_APP_PASSWORD>`

Generate the header value:

```bash
printf 'admin:%s' "YOUR_APP_PASSWORD" | base64
```

Then **Connect** → **List Tools**: three meta-tools. Call
`mcp-adapter-discover-abilities` with `{}` and both `uth/*` abilities are in
the list. Call `mcp-adapter-get-ability-info` with
`{"ability_name":"uth/save-meta-description"}` to show the schema and
annotations a client receives. Then `mcp-adapter-execute-ability` with:

```json
{"ability_name": "uth/suggest-meta-description", "parameters": {"post_id": 6}}
```

---

## Option 2: remote connectors for Claude Desktop, Codex and friends

*Not re-verified since the switch to the default server.* Desktop clients speak
STDIO; these proxies run locally, speak STDIO to the client, and translate to
HTTP against WordPress.

### `@automattic/mcp-wordpress-remote`

Published on npm. It takes a `WP_API_URL` pointing at a specific endpoint:

<https://github.com/Automattic/mcp-wordpress-remote>

```json
{
  "mcpServers": {
    "under-the-hood": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "http://localhost:8888/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "your-application-password",
        "LOG_FILE": "/tmp/mcp-adapter.log"
      }
    }
  }
}
```

### `valu-digital/mcp-connector-for-wordpress`

A transparent local bridge that delegates entirely to the MCP Adapter and
proxies the default server's three meta-tools.

<https://github.com/valu-digital/mcp-connector-for-wordpress>

Not published to npm yet, so build it from source following that repo's own
README, then point the client at the build output:

```json
{
  "mcpServers": {
    "mcp-connector-for-wordpress": {
      "command": "node",
      "args": ["/absolute/path/to/mcp-connector-for-wordpress/dist/index.js"],
      "env": {
        "WP_API_URL": "http://localhost:8888",
        "WP_USERNAME": "admin",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

This one takes the **site base URL**, not an endpoint path. Set
`DEBUG=mcp-connector` for verbose output while you are wiring it up.

Config file locations: Claude Desktop uses
`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS.

---

## Option 3: STDIO straight through WP-CLI

The adapter ships a STDIO bridge:

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  | npx wp-env run cli wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin
```

**On wp-env this is awkward for a desktop client** — `wp` lives inside Docker,
so the client would spawn `npx wp-env run cli …`, an extra process layer that
swallows stdio in some clients. Prefer HTTP.

**On Studio, STDIO is the best path.** `studio wp` is a normal binary a client
can spawn:

```json
{
  "mcpServers": {
    "under-the-hood": {
      "command": "studio",
      "args": [
        "wp",
        "--path", "/Users/YOU/Studio/under-the-hood",
        "mcp-adapter", "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

Absolute path required — `~` is not expanded in client config JSON.

**On Local, prefer HTTP:**
`http://under-the-hood.local/wp-json/mcp/mcp-adapter-default-server`. The STDIO
config needs an explicit MySQL socket flag — see [setup-local.md](setup-local.md).

---

## Live-iterating the description

Edit the `description` in `includes/abilities.php`, save, and call
`mcp-adapter-get-ability-info` again — no rebuild, no restart. The description
is the only thing a model knows about your ability before it decides to call it.

Things worth trying in front of the room:

1. **Strip it to nothing.** `'description' => 'Meta description.'` — then ask a
   real client to improve a post's SEO and watch it hesitate or reach for the
   wrong tool.
2. **Remove the "without saving it" clause.** Watch a client call suggest and
   then tell the user it has saved the description.
3. **Point at the companion.** From `v2-drive` the suggest description names
   the save ability. That is what makes a client chain the two unprompted.

## Raw handshake, for when something is broken

```bash
APP_PW="your-application-password"
EP="http://localhost:8888/wp-json/mcp/mcp-adapter-default-server"

# 1. initialize — returns a Mcp-Session-Id header
curl -s -u "admin:$APP_PW" -X POST "$EP" -D /tmp/h.txt \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl","version":"1.0"}}}'

SID=$(grep -i mcp-session-id /tmp/h.txt | awk '{print $2}' | tr -d '\r')

# 2. run our ability through the meta-tool. From 0.7.0 every request after
#    initialize must repeat the negotiated version in MCP-Protocol-Version,
#    or it is rejected with -32600 "MCP-Protocol-Version header is required".
curl -s -u "admin:$APP_PW" -X POST "$EP" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Mcp-Session-Id: $SID" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"mcp-adapter-execute-ability","arguments":{"ability_name":"uth/suggest-meta-description","parameters":{"post_id":6}}}}'
```

Adapter errors go to `wp-content/debug.log`:

```bash
tail -f ~/.wp-env/*wpsuomi-workshop-under-the-hood*/WordPress/wp-content/debug.log
```
