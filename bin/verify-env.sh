#!/usr/bin/env bash
# The pre-flight check. Run this BEFORE the workshop starts.
#
# A broken laptop at minute three costs the room fifteen minutes, so this
# script exists to move that discovery to the night before.
#
#   wp-env:          npm run verify
#   Studio / Local:  WPSUOMI_WP_PATH="/path/to/site" bash bin/verify-env.sh

set -uo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# shellcheck source=./lib-wp.sh
source "$SCRIPT_DIR/lib-wp.sh"

FAILURES=0
note_fail() { fail "$1"; FAILURES=$(( FAILURES + 1 )); }

# Evaluates a PHP expression inside WordPress and echoes the result.
wp_php() {
	wp_run eval "$1" 2>/dev/null | tr -d '\r'
}

bold "1. WordPress"
if ! wp_quiet core is-installed; then
	note_fail "Cannot reach WordPress."

	# The commonest cause on wp-env, and the one with the worst error message:
	# another environment already owns the port. wp-env buries this under a
	# docker compose dump that never says "port in use" near the top.
	if [ "$(wp_mode)" = "wp-env" ]; then
		for port in 8888 8889; do
			if command -v lsof >/dev/null 2>&1 && lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
				owner="$( docker ps --format '{{.Names}}' 2>/dev/null \
					| while read -r c; do
						docker port "$c" 2>/dev/null | grep -q ":$port$" && echo "$c"
					done | head -1 )"
				warn "Port $port is already in use${owner:+ by $owner}."
				warn "  Another wp-env is probably running. Stop it, or set a"
				warn "  different port in .wp-env.override.json:  { \"port\": 8890 }"
			fi
		done
	fi

	wp_hint_unreachable
	echo
	bold "$FAILURES check(s) failed."
	exit 1
fi

WP_VERSION="$( wp_run core version 2>/dev/null | tr -d '\r' )"
ok "WordPress $WP_VERSION"

bold "2. Drivetrain — Abilities API"
if [ "$( wp_php 'echo (int) function_exists( "wp_register_ability" );' )" = "1" ]; then
	ok "Abilities API present (core 6.9+)"
else
	note_fail "Abilities API missing — you need WordPress 6.9 or newer"
fi

bold "3. Onboard computer — core PHP AI Client"
if [ "$( wp_php 'echo (int) function_exists( "wp_ai_client_prompt" );' )" = "1" ]; then
	ok "PHP AI Client present (core 7.0+)"
else
	warn "PHP AI Client missing — sections 1-5 still work, section 6 needs core 7.0+"
fi

bold "4. Workshop plugin"
if wp_quiet plugin is-active wpsuomi-workshop-under-the-hood; then
	ok "wpsuomi-workshop-under-the-hood is active"
else
	note_fail "wpsuomi-workshop-under-the-hood is not active"
	info "Run: bash bin/provision.sh"
fi

bold "5. Registered abilities"
ABILITIES="$( wp_php 'foreach ( wp_get_abilities() as $a ) { if ( strpos( $a->get_name(), "wpsuomi/" ) === 0 ) { echo $a->get_name(), " "; } }' )"
if [ -n "${ABILITIES// /}" ]; then
	for ability in $ABILITIES; do
		ok "$ability"
	done
else
	info "No wpsuomi/* abilities registered yet — expected before we build them"
fi

bold "6. Diagnostic port — MCP Adapter"
if wp_quiet plugin is-installed mcp-adapter; then
	if wp_quiet plugin is-active mcp-adapter; then
		warn "mcp-adapter is ACTIVE — the workshop activates it live in section 5"
	else
		ok "mcp-adapter installed, not activated (correct)"
	fi
else
	note_fail "mcp-adapter is not installed"
	info "Run: bash bin/provision.sh"
fi

bold "7. Demo content"
POST_COUNT="$( wp_run post list --post_type=post --post_status=publish --format=count 2>/dev/null | tr -d '\r' )"
if [ "${POST_COUNT:-0}" -ge 2 ] 2>/dev/null; then
	ok "$POST_COUNT published posts to practise on"
else
	note_fail "Fewer than two demo posts found"
	info "Run: bash bin/provision.sh"
fi

echo
if [ "$FAILURES" -eq 0 ]; then
	bold "All good. See you at the workshop."
else
	bold "$FAILURES check(s) failed — please fix before the workshop."
	exit 1
fi
