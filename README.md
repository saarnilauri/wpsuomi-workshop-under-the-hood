# Under the Hood: How Agentic WordPress Works

Workshop repo for **WP Suomi**. This branch is the starting point — it sets up
your environment and nothing else. We build the actual code together on the day.

**Please get this working before you arrive.** A laptop that breaks at minute
three costs the whole room fifteen minutes, and the check below takes two.

---

## 1. Pick an environment

All three are verified on WordPress 7.1. Pick whichever you already have; if
you have none of them, use wp-env.

| | Needs | Guide |
|---|---|---|
| **wp-env** | Docker + Node 18+ | [docs/setup-wp-env.md](docs/setup-wp-env.md) |
| **Studio** | the `studio` CLI | [docs/setup-studio.md](docs/setup-studio.md) |
| **Local** | Local 10+ | [docs/setup-local.md](docs/setup-local.md) |

WordPress **7.1** is required, not just recommended. It ships the pieces we
rely on in core, and the setup guides pin it for you.

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
