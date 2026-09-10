# Under the Hood: How Agentic WordPress Works

[![Verify environments](https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood/actions/workflows/verify-environments.yml/badge.svg)](https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood/actions/workflows/verify-environments.yml)

Workshop repo for **WP Suomi**. This branch is the starting point — it sets up
your environment and nothing else. We build the actual code together on the day.

**Please get this working before you arrive.** A laptop that breaks at minute
three costs the whole room fifteen minutes, and the check below takes two.

---

## 1. Pick an environment

Pick whichever you already have. If you have none of them, use **wp-env**.

| | Needs | Guide |
|---|---|---|
| **wp-env** | Docker + Node 18+ | [docs/setup-wp-env.md](docs/setup-wp-env.md) |
| **Studio** | the `studio` CLI, Node **22+** | [docs/setup-studio.md](docs/setup-studio.md) |
| **Local** | Local 10+ | [docs/setup-local.md](docs/setup-local.md) |

WordPress **7.1** is required, not just recommended. It ships the pieces we
rely on in core, and the setup guides pin it for you.

Note the Node versions differ: wp-env is happy on 18, but the Studio CLI
declares `engines.node >= 22`.

### What's actually been tested

| | Status |
|---|---|
| **macOS** (Apple Silicon) | ✅ All three environments, verified by hand, end to end |
| **Linux** | ✅ wp-env and Studio, verified in CI on every push |
| **Windows** | ⚠️ **Not verified** — use WSL2, see below |

Linux is checked automatically, so if something rots you'll see it in the badge
above rather than on the day. Local on Linux is untested — it has a Linux
build, but nobody has run this repo against it.

### If you're on Windows

The setup scripts here are **bash**, so run them inside **WSL2** rather than
PowerShell:

1. Install WSL2 and a distro (`wsl --install`)
2. Enable Docker Desktop's WSL2 integration
3. Clone the repo and follow [the wp-env guide](docs/setup-wp-env.md) **from
   inside your WSL shell**

That puts you on the Linux path, which is tested.

Without WSL2 the scripts will not run — and the error will be misleading,
because Windows' own `bash.exe` in `System32` is a WSL *launcher*, not a shell.
With no distro installed it fails complaining about WSL rather than telling you
anything useful. We hit exactly this and ran out of time to chase it further,
which is why Windows is marked unverified rather than broken.

**If it doesn't work, pair up on the day.** You do not need a working laptop to
follow along, and we would much rather you watched than spent the session
fighting an environment.

## 2. Set it up

The short version for wp-env:

```bash
git clone git@github.com:saarnilauri/wpsuomi-workshop-under-the-hood.git
cd wpsuomi-workshop-under-the-hood
npm ci
npm run setup
```

That boots WordPress 7.1, installs a few plugins we need later (without
activating them), adds two demo posts, and then checks everything.

For Studio or Local, follow the matching guide — each has one step that has to
happen in that app.

## 3. Check it

```bash
npm run verify        # wp-env

# Studio or Local: point it at your site first
export WPSUOMI_WP_PATH="/path/to/your/site"
bash bin/verify-env.sh
```

It must end with **All good. See you at the workshop.**

If it doesn't, bring the output and we'll sort it out in the first five
minutes — or pair up with someone whose laptop works and keep moving.

---

## What we'll do on the day

Build one small, real piece of WordPress functionality, and then drive it four
different ways without changing it: from your own PHP, from an ordinary hook,
from an external AI agent, and from WordPress itself.

The code arrives here as tagged checkpoints shortly before the session, so if
you fall behind you can jump straight to the next one and rejoin. Nothing to
do about that now.

## Optional, for the second half

Not needed for the hands-on part, and not needed to follow along:

- An AI provider key (Anthropic, Google, OpenAI, Mistral), **or**
- [Ollama](https://ollama.com/download) with a tool-capable model:
  `ollama pull qwen2.5:3b`

## License

GPL-2.0-or-later.
