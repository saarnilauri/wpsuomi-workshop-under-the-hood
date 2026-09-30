# Running a local model with Ollama

No API key, no billing, no rate limit, and it keeps working when the venue wifi
does not. This is the recommended provider for sections 6 and 7.

> Much of this follows Darin Kotter's excellent write-up,
> **["Open Source, AI and WordPress"](https://darinkotter.com/open-source-ai-and-wordpress/)**
> (28 July 2026). Credit where it is due — go read the original.

## 1. Install Ollama and pull a tool-capable model

Install from <https://ollama.com/download>, then:

```bash
ollama pull qwen2.5:3b
```

About 1.9 GB, and verified working for both sections 6 and 7.

## 2. The gotcha that will cost you the demo

**Text generation and tool calling are different capabilities, and not every
model has both.** Section 6 only generates text; section 7's agent loop calls
tools.

`gemma3`, for example, generates text beautifully and **cannot call tools at
all**. The code does not pin a model, and `ai-provider-for-ollama` tells core
that every model supports tools — so with `gemma3` installed, core may pick it
for the agent, and you get:

```
Bad Request (400) - registry.ollama.ai/library/gemma3:latest does not support tools
```

So **keep only tool-capable models installed** on the demo machine. Check each
one:

```bash
ollama list
ollama show qwen2.5:3b
#   Capabilities
#     completion
#     tools           <- this is the one that matters
```

`llama3.1`, `llama3.2` and `mistral` also advertise `tools` — check with
`ollama show` rather than trusting a blog post, including this one.

## 3. Point WordPress at it

Activate the provider plugin (it ships pre-installed by `npm run provision`):

```bash
npm run cli -- plugin activate ai-provider-for-ollama
```

**On wp-env there is one extra step.** WordPress runs inside Docker, so
`localhost:11434` means "this container", not "my Mac". Point it at the host:

```bash
npm run cli -- option update ai_provider_for_ollama_settings \
  --format=json '{"host":"http://host.docker.internal:11434"}'
```

On Studio or Local, WordPress runs natively, so the default
`http://localhost:11434` is already correct and you can skip this.

You can also set the host in the admin at **Settings → Ollama**, or export an
`OLLAMA_HOST` environment variable, which takes precedence over both.

**Remove any hosted provider's key** at Settings → Connectors while you use
Ollama. Core uses the first configured provider it finds, and on the prep
machine that was Anthropic.

## 4. Check it from WordPress

```bash
npm run cli -- eval 'var_dump( wp_ai_client_prompt()->is_supported_for_text_generation() );'
# bool(true)
```

**Settings → Connectors** should now show Ollama as connected.

## 5. Use it

```bash
# section 6: the ability, with a real model behind it
npm run cli -- eval 'print_r( wp_get_ability( "uth/suggest-meta-description" )->execute( [ "post_id" => 5 ] ) );' --user=admin

# section 7: the agent. Say "save it" — see below.
npm run cli -- uth agent "Write a better meta description for post 6 and save it." --user=admin
```

## Caveats

- **Say "save it".** With "Improve the meta description of post 6", a 3B model
  sometimes suggests and then stops to ask. "…and save it" went 26 of 27 in
  testing.
- **First request is slow.** Ollama loads the model into memory on demand, so
  *warm the model up before you present* — run one throwaway prompt.
- **Small models are small.** A 3B model ignores "under 160 characters" now
  and then, or writes a flat description. That is honest material for the room,
  and a good argument for why the description you write in PHP matters.
- **Hosted alternatives exist** if local performance disappoints — Darin
  mentions Ollama Cloud, OpenRouter and Together.ai.

## Numbers from this repo

Measured with `qwen2.5:3b` on an Apple Silicon laptop against the demo posts:

| Task | Time |
|---|---|
| suggest, one roundtrip (section 6) | 2–5 s |
| `uth agent`, suggest then save (section 7) | 5–6 s |
