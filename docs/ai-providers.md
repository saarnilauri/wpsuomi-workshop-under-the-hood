# AI providers — sections 6 and 7

On WordPress 7.1 the PHP AI Client is **in core**. There is no SDK to install,
no HTTP client to write, and no provider abstraction to build. What you need is
a provider with credentials.

## The Connectors screen does the work

**Settings → Connectors** (capability `manage_options`) is a core screen new in
7.1. It lists the known AI providers and, for each one, offers to **install and
activate the provider plugin for you** and then takes the API key.

That is why the code names no provider and no model: pick yours on the screen,
and the one `wp_ai_client_prompt()` call in the suggest ability works unchanged.

Core knows about these out of the box:

| Provider | Plugin installed by the screen | Key constant | Get a key |
|---|---|---|---|
| Anthropic | `ai-provider-for-anthropic` | `ANTHROPIC_API_KEY` | <https://platform.claude.com/settings/keys> |
| Google | `ai-provider-for-google` | `GOOGLE_API_KEY` | <https://aistudio.google.com/api-keys> |
| OpenAI | `ai-provider-for-openai` | `OPENAI_API_KEY` | <https://platform.openai.com/api-keys> |

Two more are **pre-installed but not activated** by `npm run provision`, as
backups:

```bash
npm run cli -- plugin activate ai-provider-for-ollama    # free, local, no key
npm run cli -- plugin activate ai-provider-for-mistral   # needs MISTRAL_API_KEY
```

See [ollama.md](ollama.md) for the local option — the one to reach for if you
would rather not put a key on a screen in front of a room.

## Configure one provider, not two

With more than one provider configured, core uses whichever is **registered
first** — there is no setting or filter to choose. On the prep machine
Anthropic was registered ahead of Ollama, so with both set up every "local"
run went to the Anthropic key and was billed.

List what is registered and which are configured — this makes no generation
call:

```bash
npm run cli -- eval '$r = \WordPress\AiClient\AiClient::defaultRegistry(); foreach ( $r->getRegisteredProviderIds() as $id ) { echo $id, " configured=", $r->isProviderConfigured( $id ) ? "yes" : "no", "\n"; }'
```

Then remove the key for any provider you are not using.

## Where credentials can live

Each provider accepts its key three ways, in this order of precedence:

1. **Environment variable** — `ANTHROPIC_API_KEY`
2. **PHP constant** in `wp-config.php` — `define( 'ANTHROPIC_API_KEY', '...' );`
3. **Option**, set through the Connectors screen — `connectors_ai_anthropic_api_key`

The naming is mechanical: `connectors_ai_{provider}_api_key` for the option, and
`{PROVIDER}_API_KEY` for the constant and environment variable.

For wp-env, put constants in **`.wp-env.override.json`**, not `.wp-env.json` —
the override file is already in `.gitignore`, and committing an API key is a
bad afternoon:

```jsonc
{
  "config": {
    "ANTHROPIC_API_KEY": "sk-ant-..."
  }
}
```

## Is AI actually available?

One check, and it makes no generation call:

```bash
npm run cli -- eval 'var_dump( wp_ai_client_prompt()->is_supported_for_text_generation() );'
```

`false` means no configured provider can generate text, or AI is switched off
— `wp_supports_ai()` respects a `WP_AI_SUPPORT` constant and a `wp_supports_ai`
filter.

## What the code does

The whole AI integration is typed into the suggest ability's execute callback
in section 6:

```php
$suggestion = wp_ai_client_prompt(
    'Write one SEO meta description, under 160 characters, for this post. ' .
    'Reply with the description only. ' .
    wp_strip_all_tags( $post->post_content )
)->generate_text();

// In real life: fall back to the excerpt when no provider is configured.
if ( is_wp_error( $suggestion ) ) {
    return $suggestion;
}
```

Only the generating call returns a `WP_Error`; errors earlier in a fluent chain
are held until then. With no provider you get the error straight back — for
example `prompt_prevented` when AI is switched off.

Real code would do more: fall back to the excerpt, cap how much of the post is
sent, and clean up quotes or "Description:" prefixes that models add. None of
it changes the idea, so none of it is in the workshop.

## The agent loop, and one sharp edge

`includes/support/agent-loop.php` hands the model both abilities with
`->using_abilities()`, and core turns each ability's description and input
schema into a tool the model can call.

Tool calling is a separate capability from text generation, and core's model
selection does filter on it. The sharp edge is `ai-provider-for-ollama`: it
declares tool support for **every** completion model, whether or not the model
can call tools. So with `gemma3` installed next to `qwen2.5:3b`, core may pick
`gemma3` and the agent fails with a late
`400 … does not support tools` from Ollama.

The code does not pin a model, so the fix lives on the machine: keep only
tool-capable models installed. Check with `ollama show <model>` — you want
`tools` in the capability list.

## Cost and safety notes for the room

- Sections 1–5 make **zero** provider calls. Nobody needs a key to follow along.
- A meta description is a small prompt, but the whole post body is sent. Fine
  for two demo posts; cap it in real code.
- The agent loop stops after five turns. A loop with no bound is a bill with no
  bound.
- The agent is not privileged. Every ability it calls runs the same schema
  validation and permission check as WP-CLI, REST and MCP, as whoever is logged
  in.
