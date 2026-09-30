# Setup: Studio

**Last verified** against the pre-simplification code: Studio CLI 1.21.0, WordPress 7.1, PHP 8.3, native runtime. That run predates the switch to The SEO Framework and the default MCP server, so the rows marked *not yet re-run* below are pending.

Studio is also the **easiest environment for section 5**, because `studio wp`
is a normal command on your machine — so an MCP client can drive it over STDIO
with no proxy. On wp-env, `wp` lives inside Docker and you need HTTP instead.

## Requirements

The **Studio CLI**. Note that this is a command-line tool, not just the desktop
app — `studio` needs to be on your `PATH`:

```bash
studio --version    # 1.21.0 or newer
```

It installs to `~/.studio/bin/studio`, usually symlinked into `~/.local/bin`.
No Docker required.

## Create the site

One command, with the versions pinned:

```bash
studio create \
  --path ~/Studio/under-the-hood \
  --name "Under the Hood" \
  --wp 7.1.2 \
  --php 8.3 \
  --runtime native
```

Pinning `--wp 7.1.2` matters — it is the release the workshop is verified on. The Abilities API needs 6.9+, the PHP AI Client
needs 7.0+, and `meta.public` only reaches the REST API from 7.1.

`studio create` prints the site URL and admin credentials — **save them**, the
password is generated. You can get the URL back any time with
`studio status --path ~/Studio/under-the-hood`, which also shows an auto-login
link straight into wp-admin.

Unlike Local, the WordPress root is the path you gave — there is no
`app/public` nesting.

## Link the repo in and provision

```bash
bash bin/setup-native.sh ~/Studio/under-the-hood
```

That symlinks this repo into `wp-content/plugins/`, activates it, installs the
three dormant plugins, activates The SEO Framework, and seeds the two demo
posts. You keep editing files here in the repo; the site sees them immediately,
and `git checkout v2-drive` still does what you expect.

Then tell the other scripts where the site is, and check everything:

```bash
export WPSUOMI_WP_PATH="$HOME/Studio/under-the-hood"
bash bin/verify-env.sh
```

Add that `export` to your shell profile so it survives new terminals. It should
end with *All good*.

## `studio wp`, not `wp`

This is the one thing to internalise. Studio runs its own PHP runtime and a
**SQLite drop-in** (`wp-content/db.php`), so a standalone `wp` binary cannot
reach the database even if it finds `wp-config.php`. Studio's own `STUDIO.md`
puts it plainly:

> A standalone `wp` binary is not the entry point; always run them through
> `studio wp` so they target this site's PHP runtime and database.

So every command in [the cheatsheet](cheatsheet.md) that reads
`npm run cli -- <args>` becomes:

```bash
studio wp --path ~/Studio/under-the-hood <args>
```

`bin/lib-wp.sh` handles this for you — it detects a Studio site by the
`STUDIO.md` marker in the site root and routes through `studio wp`
automatically, so `bin/provision.sh` and `bin/verify-env.sh` need no
special-casing. If your setup is unusual, override the runner outright:

```bash
export WPSUOMI_WP_CLI="studio wp --path=/some/where"
```

Useful Studio commands:

```bash
studio list                                    # all your sites
studio status --path ~/Studio/under-the-hood   # URL, versions, online state
studio start  --path ~/Studio/under-the-hood
studio stop   --path ~/Studio/under-the-hood
studio wp     --path ~/Studio/under-the-hood shell
```

## Section 5: MCP over STDIO

This is where Studio is genuinely nicer than wp-env:

```bash
studio wp --path ~/Studio/under-the-hood plugin activate mcp-adapter

echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  | studio wp --path ~/Studio/under-the-hood \
      mcp-adapter serve --server=mcp-adapter-default-server --user=admin
```

Which means a desktop client needs no proxy at all — point it straight at
`studio`:

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

Use an absolute path — `~` is not expanded inside client config JSON.

HTTP works too, on whatever port `studio status` reports (8881 in my run):

```
http://localhost:8881/wp-json/mcp/mcp-adapter-default-server
```

See [mcp-clients.md](mcp-clients.md) for MCP Inspector and the remote
connectors.

## Gotchas

**"Old Studio config was found…"** If you ever had the Studio desktop app
installed, the CLI may open with an interactive prompt offering to reset a
stale config. Answer yes — a config with no sites in it has nothing to lose.
Until you do, `studio list` exits without listing anything.

**Do not confuse `studio mcp` with our MCP server.** `studio mcp` is Studio's
*own* MCP server, which lets an AI agent create and manage Studio sites. It has
nothing to do with the adapter's default server we connect to in section 5.

**PHP version.** `studio create` defaults to PHP 8.4. The workshop is verified
on 8.3; both are fine for WordPress 7.1, but pin it so everyone matches.

**Sandbox runtime.** `--runtime sandbox` runs the site in Playground/WASM
instead of native PHP. Untested here — use `native`.

## Verified on this setup

| Check | Result |
|---|---|
| WordPress version | 7.1 |
| Abilities API + PHP AI Client in core | both present |
| Repo symlinked and plugin active | yes |
| Both abilities registered | not yet re-run |
| Three plugins installed and dormant, The SEO Framework active | not yet re-run |
| `suggest` and `save` abilities execute | not yet re-run |
| Permission callback refuses without `--user` | not yet re-run |
| MCP STDIO via `studio wp` | not yet re-run |
| MCP HTTP via `/wp-json/mcp/mcp-adapter-default-server` | not yet re-run |
