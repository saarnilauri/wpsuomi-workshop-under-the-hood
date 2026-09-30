# Setup: Local

**Last verified** against the pre-simplification code: Local 10.1.1, WordPress 7.1, PHP 8.3.30, MySQL 8.4.0, nginx. That run predates the switch to The SEO Framework and the default MCP server, so the rows marked *not yet re-run* below are pending.

## Requirements

[Local](https://localwp.com/). No Docker, no Node needed for the site itself.

Local has **no CLI for creating sites**, so that one step is in the app.

## Create the site

In Local, click **+** → **Create a new site**:

| Field | Value |
|---|---|
| Site name | `Under the Hood` |
| Environment | **Custom** → PHP **8.3.x**, nginx, MySQL 8.x |
| WP admin username | `admin` |
| WP admin password | `password` |

Then **start the site**. That matters more than it sounds — see the socket note
below.

**Check the WordPress version on the site's Overview tab.** Local ships a
bundled WordPress 7.0 tarball but normally downloads the current release, so a
new site should land on 7.1.2. If yours says anything older, update it before continuing:

```bash
export WPSUOMI_WP_PATH="$HOME/Local Sites/under-the-hood/app/public"
bash -c 'source bin/lib-wp.sh; wp_run core update'
```

7.0 is not enough. The Abilities API needs 6.9+ and the PHP AI Client needs
7.0+, but `meta.public` only seeds `show_in_rest` from **7.1**, so section 3's
REST channel behaves differently on 7.0.

The WordPress root is **`app/public`** inside the site folder, not the site
folder itself:

```
~/Local Sites/under-the-hood/app/public      <- wp-config.php lives here
```

## Link the repo in and provision

```bash
bash bin/setup-native.sh "$HOME/Local Sites/under-the-hood/app/public"
```

That symlinks this repo into `wp-content/plugins/`, activates it, installs the
three dormant plugins, activates The SEO Framework and seeds the two demo
posts. Then:

```bash
export WPSUOMI_WP_PATH="$HOME/Local Sites/under-the-hood/app/public"
bash bin/verify-env.sh
```

Add that `export` to your shell profile. It should end with *All good*.

## WP-CLI on Local: two ways, both work

Local's MySQL **only accepts socket connections**. Point anything at TCP and
you get:

```
ERROR 1130 (HY000): Host '127.0.0.1' is not allowed to connect to this MySQL server
```

Meanwhile `wp-config.php` says `DB_HOST = localhost`, which makes PHP use its
*default* socket — not Local's. Local resolves this by rendering a per-site
`php.ini` from `conf/php/php.ini.hbs`, which sets
`mysqli.default_socket` to that site's own socket.

So there are two ways to run WP-CLI, and this repo supports both:

### 1. Local's Site shell (simplest)

Click **Site shell** on the site's Overview tab. That opens a terminal where
`wp` is on the `PATH` and already wired to the right socket. Everything in
[the cheatsheet](cheatsheet.md) works with plain `wp`:

```bash
wp core version
wp plugin list
```

You can `cd` to this repo from that shell and run `bin/*.sh` there too.

### 2. Any ordinary terminal (what the scripts do)

`bin/lib-wp.sh` works it out for you. It probes whether a bare `wp` can
actually reach the database — which is true inside the Site shell — and if not,
it derives the site's socket and drives Local's own bundled WP-CLI:

```bash
php -d mysqli.default_socket="<site socket>" \
  /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar \
  --path="$HOME/Local Sites/under-the-hood/app/public" <args>
```

The socket is derivable because Local names each site's run directory after
that site's ID in `sites.json`:

```
~/Library/Application Support/Local/run/<siteId>/mysql/mysqld.sock
```

`bin/setup-native.sh` prints which runner it chose, so you always know.

> **The site must be running.** Local creates the socket on start and removes
> it on stop, so every command here fails while the site is stopped. If the
> scripts say *"No WP-CLI runner can reach this site"*, start the site first.

If your setup is unusual, override the runner outright:

```bash
export WPSUOMI_WP_CLI="wp --path=/some/where"
```

## Section 5: MCP

Both transports verified.

**HTTP is the nicer path on Local**, because the site has a real hostname:

```
http://under-the-hood.local/wp-json/mcp/mcp-adapter-default-server
```

Use that with MCP Inspector or either remote connector — see
[mcp-clients.md](mcp-clients.md). Create an application password first:

```bash
bash -c 'source bin/lib-wp.sh; wp_run user application-password create admin "mcp client" --porcelain'
```

**STDIO works too**, but the command is verbose off Local's own shell, because
it needs the socket flag:

```json
{
  "mcpServers": {
    "under-the-hood": {
      "command": "php",
      "args": [
        "-d", "mysqli.default_socket=/Users/YOU/Library/Application Support/Local/run/SITEID/mysql/mysqld.sock",
        "/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar",
        "--path=/Users/YOU/Local Sites/under-the-hood/app/public",
        "mcp-adapter", "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

Find your `SITEID` with:

```bash
python3 -c "import json,pathlib;d=json.loads((pathlib.Path.home()/'Library/Application Support/Local/sites.json').read_text());[print(k,'->',v.get('name')) for k,v in d.items()]"
```

On Studio the same thing is a two-line config, because `studio wp` handles it —
see [setup-studio.md](setup-studio.md).

## Gotchas

**HTTPS.** Local offers a certificate for `under-the-hood.local` but it is not
trusted until you click **Trust**. Use `http://` for the MCP endpoint unless
you have trusted it, or curl will reject the cert.

**Don't confuse the site folder with the WordPress root.** `--path` must point
at `app/public`.

**Local's PHP vs your PHP.** The runner uses whatever `php` is on your `PATH`
to execute Local's WP-CLI phar. Any PHP 7.4+ works; it is only the *socket*
that has to be right.

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
| MCP STDIO via Local's bundled WP-CLI | not yet re-run |
| MCP HTTP via `http://under-the-hood.local` | not yet re-run |
