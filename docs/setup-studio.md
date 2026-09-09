# Setup: Studio

**Verified** end-to-end against Studio CLI 1.21.0, WordPress 7.1, PHP 8.3,
native runtime. All seven pre-flight checks pass, both abilities run, and both
MCP transports work.

Studio needs no Docker, and `studio wp` is a normal command on your machine,
which makes it the smoothest of the three for the second half of the session.

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
  --wp 7.1 \
  --php 8.3 \
  --runtime native
```

Pinning `--wp 7.1` matters. The Abilities API needs 6.9+, the PHP AI Client
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
five dormant plugins, and seeds the two demo posts. You keep editing files here
in the repo; the site sees them immediately.

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

So wherever you would run `wp <args>`, run this instead:

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

## Gotchas

**"Old Studio config was found…"** If you ever had the Studio desktop app
installed, the CLI may open with an interactive prompt offering to reset a
stale config. Answer yes — a config with no sites in it has nothing to lose.
Until you do, `studio list` exits without listing anything.

**`studio mcp` is not ours.** `studio mcp` is Studio's *own* MCP server, which
lets an AI agent create and manage Studio sites. It is unrelated to anything we
build in this workshop — don't go looking for our tools in there.

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
| Five plugins installed, none activated | yes |
| Workshop code runs end to end | yes |
