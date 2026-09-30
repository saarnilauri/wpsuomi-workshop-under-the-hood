# Command cheatsheet

Everything you'll type on the day. wp-env commands assume you're in the repo
root.

## Environment

```bash
npm start                 # boot WordPress 7.1 on localhost:8888
npm stop                  # shut down, keep the database
npm run verify            # pre-flight check
npm run provision         # re-run provisioning (safe to repeat)
npm run destroy           # nuke it
```

Admin: <http://localhost:8888/wp-admin> — `admin` / `password`

**Not on wp-env?** Every `npm run cli -- <args>` below translates:

| Environment | Equivalent |
|---|---|
| Studio | `studio wp --path ~/Studio/under-the-hood <args>` |
| Local, from its **Site shell** | `wp <args>` |
| Local, any other terminal | `bash -c 'source bin/lib-wp.sh; wp_run <args>'` |

Neither Studio nor Local can be driven by a plain `wp` from an ordinary shell —
Studio uses SQLite behind its own PHP runtime, and Local's MySQL is socket-only.
See [setup-studio.md](setup-studio.md) and [setup-local.md](setup-local.md).

`wp-env` writes progress chatter to **stderr** and the command's output to
**stdout**, so add `2>/dev/null` when you want clean output to pipe.

## Checkpoints

| Tag | End of section |
|---|---|
| `v1-ignition` | 3 — the read ability, filled in |
| `v2-drive` | 4 — the write ability |
| `v3-computer` | 6 — the AI call |
| `v4-selfdriving` | 7 — the agent loop |

```bash
# behind? restore the files from a checkpoint, keep the rest of your tree
git checkout v1-ignition -- includes/ wpsuomi-workshop-under-the-hood.php

git diff v1-ignition v2-drive          # what changed between checkpoints
git tag -l                             # list all checkpoints

# tags look wrong after they were re-cut? a plain pull will not update them
git fetch --tags --force
```

## Watch the log

```bash
tail -f ~/.wp-env/*wpsuomi-workshop-under-the-hood*/WordPress/wp-content/debug.log
```

## The read ability: WP-CLI (section 3)

`wp eval` is the driver, and WP-CLI's own `--user` flag decides who is at the
wheel.

```bash
# --user is REQUIRED: the permission callback checks a real user
npm run cli -- eval 'print_r( wp_get_ability( "uth/suggest-meta-description" )->execute( [ "post_id" => 5 ] ) );' --user=admin
```

Leave `--user` off on purpose once — the ignition key is a real lock, and
you'll get `ability_invalid_permissions`.

The schema refuses bad input before your code runs:

```bash
# below the schema minimum of 1
npm run cli -- eval 'print_r( wp_get_ability( "uth/suggest-meta-description" )->execute( [ "post_id" => 0 ] ) );' --user=admin

# a property the schema does not declare
npm run cli -- eval 'print_r( wp_get_ability( "uth/suggest-meta-description" )->execute( [ "post_id" => 5, "colour" => "red" ] ) );' --user=admin
```

What the model will read about every ability on the site:

```bash
npm run cli -- eval 'foreach ( wp_get_abilities() as $a ) { echo $a->get_name(), "\n  ", $a->get_description(), "\n\n"; }'
```

## The read ability: REST (section 3)

`meta.public` puts an ability on the REST API with no extra code. Make an
application password first:

```bash
npm run cli -- user application-password create admin "workshop" --porcelain 2>/dev/null
```

```bash
APP_PW="paste-it-here"

# list
curl -s -u "admin:$APP_PW" http://localhost:8888/wp-json/wp-abilities/v1/abilities

# run — input is wrapped in an "input" object, and readonly means GET
curl -s -u "admin:$APP_PW" -G \
  --data-urlencode 'input[post_id]=5' \
  http://localhost:8888/wp-json/wp-abilities/v1/abilities/uth/suggest-meta-description/run
```

## The write ability (section 4)

It writes into The SEO Framework's meta description field. Open the post in the
editor afterwards and the text is in TSF's box.

```bash
npm run cli -- eval 'print_r( wp_get_ability( "uth/save-meta-description" )->execute( [ "post_id" => 5, "description" => "One drivetrain, many drivers." ] ) );' --user=admin

npm run cli -- post meta get 5 _genesis_description
```

### Annotations decide the HTTP method

| Annotations | Required method |
|---|---|
| `readonly: true` | `GET` |
| `destructive: true` **and** `idempotent: true` | `DELETE` |
| anything else | `POST` |

So GET on the write ability comes back 405:

```bash
curl -s -u "admin:$APP_PW" -G --data-urlencode 'input[post_id]=5' \
  http://localhost:8888/wp-json/wp-abilities/v1/abilities/uth/save-meta-description/run
# -> "Abilities that perform updates require POST method."
```

...and POST works:

```bash
curl -s -u "admin:$APP_PW" -X POST -H "Content-Type: application/json" \
  -d '{"input":{"post_id":5,"description":"Set from the REST channel."}}' \
  http://localhost:8888/wp-json/wp-abilities/v1/abilities/uth/save-meta-description/run
```

Flip `destructive` to `true` in `includes/Abilities/Save-meta-description.php`
and the same POST comes back 405, asking for DELETE.

## MCP (section 5)

No code. Switch the port on and connect:

```bash
npm run cli -- plugin activate mcp-adapter

# credentials for any client
npm run cli -- user application-password create admin "mcp client" --porcelain 2>/dev/null

# the neutral tester
npm run inspector
```

Inspector settings: **Streamable HTTP**, URL
`http://localhost:8888/wp-json/mcp/mcp-adapter-default-server`, header
`Authorization: Basic <base64 of admin:APP_PASSWORD>`.

`tools/list` shows three meta-tools. Call `mcp-adapter-discover-abilities` to
find ours, then `mcp-adapter-execute-ability` with
`{"ability_name":"uth/suggest-meta-description","parameters":{"post_id":6}}`.

Full guide: [mcp-clients.md](mcp-clients.md).

## The onboard computer (section 6)

Pick **one** provider at **Settings → Connectors**. Or go local and free:

```bash
npm run cli -- plugin activate ai-provider-for-ollama
npm run cli -- option update ai_provider_for_ollama_settings \
  --format=json '{"host":"http://host.docker.internal:11434"}'
```

Then the same ability, same schemas, a real model behind it:

```bash
npm run cli -- eval 'print_r( wp_get_ability( "uth/suggest-meta-description" )->execute( [ "post_id" => 5 ] ) );' --user=admin
```

With no provider configured you get the error back instead —
`prompt_prevented` or similar. Real code would fall back to the excerpt.

Details: [ai-providers.md](ai-providers.md), [ollama.md](ollama.md).

## Self-driving mode (section 7)

```bash
npm run cli -- uth agent "Write a better meta description for post 6 and save it." --user=admin
```

Say "save it". With just "improve", a small model sometimes suggests and stops.

The agent is **not** privileged. Drop `--user` and the permission check refuses
it; the site does not change:

```bash
npm run cli -- uth agent "Write a better meta description for post 6 and save it."
```

## Demo posts

| ID | Title | Excerpt? |
|---|---|---|
| 5 | One drivetrain, four drivers | no — WordPress generates one, ending in `[…]` |
| 6 | Should self-driving ask before it changes lanes | yes — hand-written |

Recreate them any time with `npm run provision`.
