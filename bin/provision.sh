#!/usr/bin/env bash
# Provisions the workshop environment.
#
# Installs — but deliberately does NOT activate — the plugins we need later, and
# seeds two demo posts to practise on. Safe to run as many times as you like.
#
#   wp-env:          npm run provision
#   Studio / Local:  WPSUOMI_WP_PATH="/path/to/site" bash bin/provision.sh

set -uo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=./lib-wp.sh
source "$SCRIPT_DIR/lib-wp.sh"

MCP_ADAPTER_ZIP="https://github.com/WordPress/mcp-adapter/releases/download/v0.6.1/mcp-adapter.zip"
WORKSHOP_PLUGIN="wpsuomi-workshop-under-the-hood"

bold "Provisioning the workshop environment ($(wp_mode) mode)"

if ! wp_quiet core is-installed; then
	fail "Could not reach WordPress."
	wp_hint_unreachable
	exit 1
fi

info "WordPress $(wp_run core version 2>/dev/null | tr -d '\r')"

# ---------------------------------------------------------------------------
# The workshop plugin itself — the only thing we activate.
# ---------------------------------------------------------------------------
bold "Workshop plugin"
if wp_quiet plugin is-installed "$WORKSHOP_PLUGIN"; then
	if wp_quiet plugin is-active "$WORKSHOP_PLUGIN"; then
		ok "$WORKSHOP_PLUGIN is active"
	else
		if wp_quiet plugin activate "$WORKSHOP_PLUGIN"; then
			ok "$WORKSHOP_PLUGIN activated"
		else
			fail "$WORKSHOP_PLUGIN could not be activated"
		fi
	fi
else
	fail "$WORKSHOP_PLUGIN is not in wp-content/plugins"
	if wp_is_native; then
		info "Run bin/setup-native.sh to symlink it into your site."
	fi
fi

# ---------------------------------------------------------------------------
# Installed on purpose, left switched off. Section 5 activates the MCP adapter
# live; the SEO and provider plugins are there so nobody has to download
# anything on venue wifi.
# ---------------------------------------------------------------------------
install_dormant() {
	local source="$1"
	local slug="$2"
	local label="$3"

	if wp_quiet plugin is-installed "$slug"; then
		if wp_quiet plugin is-active "$slug"; then
			warn "$label is ACTIVE (the workshop expects it switched off)"
		else
			ok "$label installed, not activated"
		fi
		return 0
	fi

	if wp_quiet plugin install "$source"; then
		ok "$label installed, not activated"
	else
		fail "$label could not be installed (carry on, it is optional)"
	fi
}

bold "Diagnostic port (activated live in section 5)"
install_dormant "$MCP_ADAPTER_ZIP" "mcp-adapter" "MCP Adapter 0.6.1"

bold "SEO plugins (optional mirror targets for section 4)"
install_dormant "wordpress-seo" "wordpress-seo" "Yoast SEO"
install_dormant "autodescription" "autodescription" "The SEO Framework"

bold "AI provider backups (section 6 — pick your own at Settings > Connectors)"
install_dormant "ai-provider-for-ollama" "ai-provider-for-ollama" "AI Provider for Ollama"
install_dormant "ai-provider-for-mistral" "ai-provider-for-mistral" "AI Provider for Mistral"

# ---------------------------------------------------------------------------
# Something to practise on.
# ---------------------------------------------------------------------------
bold "Demo content"

seed_post() {
	local slug="$1"
	local title="$2"
	local file="$3"
	local excerpt="$4"

	local existing
	existing="$( wp_run post list --post_type=post --name="$slug" --field=ID --format=ids 2>/dev/null | tr -d '\r' )"

	if [ -n "$existing" ]; then
		ok "$title (post $existing)"
		return 0
	fi

	local new_id
	new_id="$( wp_run post create \
		--post_type=post \
		--post_status=publish \
		--post_title="$title" \
		--post_name="$slug" \
		--post_excerpt="$excerpt" \
		--post_content="$( cat "$SCRIPT_DIR/seed/$file" )" \
		--porcelain 2>/dev/null | tr -d '\r' )"

	if [ -n "$new_id" ]; then
		ok "$title (post $new_id)"
	else
		fail "Could not create \"$title\""
	fi
}

seed_post "one-drivetrain-four-drivers" \
	"One drivetrain, four drivers" \
	"post-no-excerpt.html" \
	""

seed_post "should-self-driving-ask-first" \
	"Should self-driving ask before it changes lanes" \
	"post-with-excerpt.html" \
	"Read abilities and write abilities look identical on paper. The difference only shows up when the agent gets it wrong."

echo
bold "Ready."
info "Two demo posts: one without an excerpt, one with. Section 3 uses both."
if [ "$(wp_mode)" = "wp-env" ]; then
	info "Site:  http://localhost:8888   (admin / password)"
	info "Check: npm run verify"
fi
