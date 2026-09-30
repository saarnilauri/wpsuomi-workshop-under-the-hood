# Under the Hood: How Agentic WordPress Works — run sheet

**Format:** Hands-on garage session — instructor builds, participants wrench along
**Duration:** 60 minutes
**Goal:** Build one real ability — *suggest a meta description from post content* —
drive it from WP-CLI, REST and an external agent over MCP, then give it an AI
engine and let WordPress drive it itself.

| Vehicle | WordPress |
|---|---|
| Drivetrain — what the machine can do | Abilities API |
| Dashboard labels & owner's manual | Ability descriptions |
| Ignition key | Permission callbacks |
| Diagnostic port (OBD-II) | MCP Adapter |
| External diagnostic tool / driver | MCP client (MCP Inspector, Claude Desktop, …) |
| Onboard computer | PHP AI Client |
| Self-driving mode | Internal agent loop |

---

## Prerequisites (sent to participants beforehand)

- Local WordPress running and **verified before arriving**: `npm run setup`
  must end with *All good*
- This repo cloned. Checkpoint tags let anyone jump ahead:
  `v1-ignition`, `v2-drive`, `v3-computer`, `v4-selfdriving`
- Optional, for sections 6 and 7: an AI provider key or Ollama, configured at
  **Settings → Connectors**

**On WordPress 7.1 the Abilities API, the PHP AI Client and the Connectors
screen are in core.** The only plugins involved are The SEO Framework (active
from setup) and the MCP Adapter (switched on in section 5).

---

## 1. Pop the hood — 5 min

- AI in WordPress so far: chat boxes and content generators. What changes when
  the machine can *drive itself*?
- The vehicle map above — every part we touch today
- Environment check: `npm run verify`. Broken laptop → pair up, keep moving

## 2. The parts diagram — anatomy of an ability — 7 min

*Read-along:* `includes/abilities.php`, `includes/Abilities/Suggest-meta-description.php`

- What an ability is: a registered, described, permission-aware unit of site
  functionality
- **The registration says what it is** — name, label, description, category,
  and which class implements it
- **The class says how** — input and output schema (already written in the
  skeleton), meta, permission callback, execute callback
- **Two init hooks, not one:**
  - `wp_abilities_api_categories_init` → `wp_register_ability_category()`
  - `wp_abilities_api_init` → `wp_register_ability()`
- The description is the dashboard label — foreshadow: agents read it too

## 3. First ignition — fill in the skeleton — 15 min

*💻 Code-along:* `includes/Abilities/Suggest-meta-description.php`

- Type the three parts that matter: `meta()`, the permission check, the
  execute body. The schemas are already there.
- Uncomment the registration in `includes/abilities.php` and fill its three
  `TODO`s. Add one `require_once` for the class in the plugin file.
- **No AI yet:** the suggestion is the post excerpt — the engine turns over
  before we install the computer
- Drive it, same ability, zero extra code:
  - **WP-CLI** — `wp eval … --user=admin`
  - **REST** — `GET /wp-abilities/v1/abilities/…/run`, free from `meta.public`.
    Demo it in Hoppscotch so the room sees headers and body
  - **MCP** — the third driver, and it arrives in section 5
- Run it **without** `--user` once. The ignition key is a real lock.
- Show the schema refusing bad input: `post_id` 0, and an undeclared property.
- ✅ `v1-ignition`

## 4. Engaging the drive — the write operation — 7 min

*Instructor demo + discussion:* `includes/Abilities/Save-meta-description.php`

- Second ability: save the suggestion into The SEO Framework's meta
  description field, then show it in TSF's box in the editor
- The `annotations` are not decoration — core **derives the HTTP method** from
  them. GET on the write ability comes back 405. Flip `destructive` to true live
  and the working POST comes back 405 too, asking for DELETE.
- 💬 **Should self-driving ask before it changes lanes?** Read vs write
  abilities, acceptance for agent writes, who is responsible when the agent steers
- ✅ `v2-drive`

## 5. The diagnostic port — connecting MCP — 8 min

*Instructor demo, no code*

- The MCP adapter is the OBD-II port: one standardised connector, any external
  tool plugs in
- Activate the adapter and you already have
  `/wp-json/mcp/mcp-adapter-default-server`. Plug in MCP Inspector: three
  meta-tools — `mcp-adapter-discover-abilities`, `mcp-adapter-get-ability-info`,
  `mcp-adapter-execute-ability` — and discover already finds both of ours,
  because they are public
- Execute the suggest ability through it. No new code: the ability we built in
  section 3 is the tool
- A custom server (`create_server()`) would list named abilities as
  first-class tools instead — on the slide, not in the code
- 💬 Context size: discover returns five abilities on a stock install before
  you add any. What does that look like at 50? At 100?

## 6. The onboard computer — 8 min

*💻 Code-along:* the execute body of `Suggest-meta-description.php`

- The PHP AI Client is in core 7.1; the provider is chosen at
  **Settings → Connectors**. Nothing in the code names one.
- Replace the excerpt with one `wp_ai_client_prompt( … )->generate_text()`
  call, and return the error if it fails
- Same input schema, same output schema — every driver from sections 3–5 still
  works, and now gets a real description
- ✅ `v3-computer`

## 7. Self-driving mode — 7 min

*Instructor demo:* `includes/support/agent-loop.php`

- `->using_abilities()` turns abilities into tools the model can call, from the
  same descriptions MCP clients read
- The resolver is the allowlist; the loop is bounded; one system instruction
  keeps a small model honest
- `wp uth agent "Write a better meta description for post 6 and save it." --user=admin`
  → the agent suggests *and* saves
- Callback to section 4: run it **without** `--user` and watch the permission
  check refuse it, with the site left unchanged. The agent is not privileged.
- ✅ `v4-selfdriving`

> **Instructor prep, learned the hard way.**
> - **Say "save it" in the instruction.** "Improve the meta description" lets a
>   small model stop after suggesting; "…and save it" went 26 of 27 in testing.
> - **Only keep tool-capable models installed in Ollama.** Text generation and
>   tool calling are different capabilities, and `ai-provider-for-ollama` tells
>   core that *every* model can call tools — so with `gemma3` installed next to
>   `qwen2.5:3b`, core may pick the one that cannot. Check with
>   `ollama show <model>` and look for `tools`.
> - **One configured provider at a time.** With both a hosted key and Ollama
>   configured, core uses whichever is registered first. On the prep machine
>   Anthropic was registered ahead of Ollama, so every "local" run would have
>   billed the Anthropic key. Remove the key you are not using.
> - **Warm the model up before you present** — the first request loads it into
>   memory and is slow.

## 8. Out of the garage — 3 min

- One drivetrain, many drivers: WP-CLI, REST, external agents via the port,
  and WordPress itself
- Component status: Abilities API, PHP AI Client and Connectors are **core** as
  of 7.1. The MCP Adapter is still a plugin. *(Accurate as of this repo's last
  commit — date this slide.)*
- Repo link with all checkpoints

---

## Practical notes

- **Checkpoint tags are the survival mechanism.** Behind? Restore the files
  from the next tag and rejoin:
  `git checkout v1-ignition -- includes/ wpsuomi-workshop-under-the-hood.php`.
  One stalled laptop must not stop the room.
- **Sync the room to `v1-ignition` before section 4.** The write ability
  arrives with that checkpoint, so anyone who typed along without restoring will
  not have the file you are about to show.
- **No API keys needed until section 6.** Sections 1–5 never call a model.
- **Timing pressure valve:** if section 3 runs long, fold section 4 into two
  slides. If section 6 runs long, restore `v3-computer` and go straight to 7.
- **Demo insurance:** record backup videos of the MCP connection and the agent
  loop.
