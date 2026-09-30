# Under the Hood: How Agentic WordPress Works

[![Verify environments](https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood/actions/workflows/verify-environments.yml/badge.svg)](https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood/actions/workflows/verify-environments.yml)

Workshop repo for WP Suomi. We build **one real ability** — suggest a meta
description from post content — drive it from WP-CLI, REST and an external
agent over MCP without changing it, then give it an AI engine and let WordPress
drive it itself.

| Vehicle | WordPress |
|---|---|
| Drivetrain — what the machine can do | Abilities API |
| Dashboard labels & owner's manual | Ability descriptions |
| Ignition key | Permission callbacks |
| Diagnostic port (OBD-II) | MCP Adapter |
| External diagnostic tool / driver | MCP client (Claude Desktop, Codex, …) |
| Onboard computer | PHP AI Client |
| Self-driving mode | Internal agent loop |

Everything here runs on **WordPress 7.1**, which ships the Abilities API, the
PHP AI Client and the Connectors screen in core. The only plugins involved are
The SEO Framework (active from setup) and the MCP Adapter (switched on in
section 5).

---

## Quickstart (wp-env)

You need [Docker](https://www.docker.com/products/docker-desktop/) running and
Node 18+.

```bash
git clone git@github.com:saarnilauri/wpsuomi-workshop-under-the-hood.git
cd wpsuomi-workshop-under-the-hood
npm ci
npm run setup
```

`npm run setup` boots WordPress 7.1, activates The SEO Framework, installs the
plugins we need later (**without** activating them), seeds two demo posts, and
then verifies the lot.

Site: <http://localhost:8888> — log in as `admin` / `password`.

**Please run this before the workshop.** A laptop that breaks at minute three
costs the whole room fifteen minutes. `npm run verify` is the pre-flight check
and it should end with *All good*.

wp-env is the default because it needs no GUI step, and it is the one verified
against the current code. [Studio](docs/setup-studio.md) is the easiest for section 5 —
`studio wp` lets an MCP client connect over STDIO with no proxy.
[Local](docs/setup-local.md) gives the nicest MCP endpoint, a real hostname at
`http://under-the-hood.local`.

---

## Operating systems

| | Status |
|---|---|
| **macOS** (Apple Silicon) | ✅ wp-env verified end to end on the current code; Studio and Local verified on the previous version |
| **Linux** | ✅ wp-env and Studio, verified in CI on every push |
| **Windows** | ⚠️ **Not verified** — run the scripts inside WSL2 |

The setup scripts are bash. On Windows, run them from a **WSL2** shell with
Docker Desktop's WSL2 integration enabled, which puts you on the tested Linux
path. Without WSL2 they will not run, and the error misleads: Windows' own
`bash.exe` in `System32` is a WSL *launcher*, not a shell, so with no distro
installed it complains about WSL rather than about anything real.

Node versions differ between environments — wp-env is happy on 18, the Studio
CLI declares `engines.node >= 22`.

Local on Linux is untested. `main` carries the spoiler-free setup guides; the
full versions with their MCP sections arrive at `v2-drive`.

## Where to start

`main` is the starting point: the environment, the ability category, and a
**skeleton** of the first ability with `TODO`s where the interesting parts go.
Give yourself a branch to commit on:

```bash
git checkout -b my-attempt main
```

## Checkpoints

If your laptop stalls, restore the files from the next checkpoint and rejoin —
this is the survival mechanism, so use it early rather than falling behind.

```bash
git fetch --tags
git checkout v1-ignition -- includes/ wpsuomi-workshop-under-the-hood.php
```

| Tag | End of section | What exists at this tag |
|---|---|---|
| *(`main`)* | 1–2 | Ability **category**, and the suggest ability as a skeleton. |
| `v1-ignition` | 3 | `uth/suggest-meta-description` — returns the excerpt. No AI. The write ability's file is present but not loaded yet. |
| `v2-drive` | 4 | `uth/save-meta-description` — writes into The SEO Framework. |
| — | 5 | No code: the MCP Adapter's default server exposes both abilities. |
| `v3-computer` | 6 | The suggest ability asks a real model. |
| `v4-selfdriving` | 7 | The agent loop and `wp uth agent` — it suggests *and* saves. Both already required in the bootstrap. |

Diffs are the teaching material:

```bash
git diff main v1-ignition
git diff v2-drive v3-computer
```

If the tags were re-cut after you fetched them, a plain `git pull` keeps the old
ones without saying so. Use `git fetch --tags --force`.

---

## Repo map

```
wpsuomi-workshop-under-the-hood.php   Plugin bootstrap — one require per ability
includes/
  abilities.php                       Category, and where abilities get registered
  Abilities/
    Suggest-meta-description.php      The read ability — the skeleton we fill in
    Save-meta-description.php         The write ability
  support/                            Given, not typed, and already required
    agent-loop.php                    v4 — self-driving mode
    cli.php                           v4 — wp uth agent
bin/                                  Setup, provisioning, pre-flight check
docs/                                 Setup and connection guides
WORKSHOP.md                           Run sheet with timings
```

Abilities are classes extending `WP_Ability`. The registration says what an
ability is **called**; the class says what it **does**:

```php
wp_register_ability(
    'uth/suggest-meta-description',
    [
        'ability_class' => \Uth\Abilities\Suggest_Meta_Description::class,
        'category'      => UTH_CATEGORY,
        'label'         => 'Suggest a meta description',
        'description'   => 'Suggests an SEO meta description …',
    ]
);
```

The code is deliberately bare. Where production code would check that a post
exists, sanitise text or fall back when there is no AI provider, a comment says
so instead.

## Docs

| Doc | From |
|---|---|
| [Setup: wp-env](docs/setup-wp-env.md) — the default path | `main` |
| [Setup: Studio](docs/setup-studio.md) | `main` |
| [Setup: Local](docs/setup-local.md) | `main` |
| [Command cheatsheet](docs/cheatsheet.md) — what you'll type on the day | `v1-ignition` |
| [Connecting an MCP client](docs/mcp-clients.md) — section 5 | `v2-drive` |
| [AI providers](docs/ai-providers.md) — sections 6 and 7 | `v3-computer` |
| [Running a local model with Ollama](docs/ollama.md) — free, no key | `v3-computer` |

## License

GPL-2.0-or-later.
