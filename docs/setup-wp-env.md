# Setup: wp-env

This is the supported environment for the workshop. It has been tested
end-to-end on WordPress 7.1.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/), **running**
- Node 18 or newer

## Install

```bash
git clone git@github.com:saarnilauri/wpsuomi-workshop-under-the-hood.git
cd wpsuomi-workshop-under-the-hood
npm ci
npm run setup
```

`npm run setup` runs three things in order:

1. `npm start` — boots WordPress 7.1 on <http://localhost:8888>
2. `npm run provision` — installs the plugins we need later without activating
   them, and seeds two demo posts
3. `npm run verify` — the pre-flight check

The first boot downloads WordPress and a MariaDB image, so give it a few
minutes. Later boots take seconds.

Log in at <http://localhost:8888/wp-admin> with **`admin` / `password`**.

## What provisioning installs

Deliberately **installed but not activated** — we switch these on during the
workshop, or not at all:

| Plugin | Why |
|---|---|
| MCP Adapter 0.6.1 | We switch this on together |
| Yoast SEO | Optional, used in one exercise |
| The SEO Framework | Optional, used in one exercise |
| AI Provider for Ollama | Backup AI provider |
| AI Provider for Mistral | Backup AI provider |

Only `wpsuomi-workshop-under-the-hood` is active, and wp-env activates it for
you because the repo is listed in `.wp-env.json` under `plugins`.

## Everyday commands

```bash
npm start              # boot
npm stop               # shut down, keep the database
npm run verify         # pre-flight check
npm run provision      # re-run provisioning (safe to repeat)
npm run cli -- <args>  # any WP-CLI command, e.g. npm run cli -- plugin list
```

`wp-env` prints its own progress chatter on **stderr** while the command's real
output goes to **stdout**, so piping works normally:

```bash
npm run cli -- post list --format=json 2>/dev/null
```

## Troubleshooting

**Port 8888 already in use.** Another wp-env is running somewhere else. Find it
and `wp-env stop` in that folder, or check `docker ps`.

**`Environment not initialized`.** Run wp-env commands from the repo root —
it resolves `.wp-env.json` from the current directory.

**Changes to PHP not showing up.** The repo is mounted live, so they should
appear instantly. If not, confirm the plugin is active:
`npm run cli -- plugin list`.

**Start over completely.**

```bash
npm run destroy && npm run setup
```
