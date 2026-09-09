#!/usr/bin/env bash
# Shared WP-CLI plumbing for the workshop scripts.
#
# There are three supported environments and one command runner, so every other
# script in bin/ can just call `wp_run plugin list` and forget where WordPress
# actually lives.
#
#   wp-env  ->  commands are proxied into the Docker container
#   Studio  ->  `studio wp --path=...`  (mandatory: Studio runs its own PHP
#               runtime and a SQLite drop-in, so a standalone `wp` binary
#               cannot reach the site's database at all)
#   Local   ->  `wp --path=...` when run from Local's own "Site shell", which
#               already points PHP at the right MySQL socket. From an ordinary
#               terminal we derive that socket ourselves and drive Local's
#               bundled WP-CLI, because Local's MySQL only accepts socket
#               connections — TCP is refused outright with ERROR 1130.
#
# Point the native environments at your site's WordPress root — the folder that
# contains wp-config.php:
#
#   export WPSUOMI_WP_PATH="$HOME/Studio/under-the-hood"
#   export WPSUOMI_WP_PATH="$HOME/Local Sites/under-the-hood/app/public"
#
# If your setup is unusual, override the runner outright:
#
#   export WPSUOMI_WP_CLI="studio wp --path=/some/where"

set -uo pipefail

WPSUOMI_WP_PATH="${WPSUOMI_WP_PATH:-}"
WPSUOMI_WP_CLI="${WPSUOMI_WP_CLI:-}"

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1"; }
info() { printf '  · %s\n' "$1"; }

WPSUOMI_LOCAL_WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar"
WPSUOMI_LOCAL_SITES_JSON="$HOME/Library/Application Support/Local/sites.json"

# Prints the MySQL socket for a Local site, or nothing if it cannot be found.
#
# Local names each site's run directory after the site's own ID in sites.json,
# so the socket is derivable — but only while the site is running, because
# Local renders the config and creates the socket on start.
wpsuomi_local_socket() {
	local site_path="$1"

	[ -f "$WPSUOMI_LOCAL_SITES_JSON" ] || return 0
	command -v python3 >/dev/null 2>&1 || return 0

	SITE_PATH="$site_path" HOME="$HOME" python3 - "$WPSUOMI_LOCAL_SITES_JSON" <<'PYEOF' 2>/dev/null
import json, os, sys, pathlib

target = pathlib.Path(os.environ["SITE_PATH"]).resolve()
home = pathlib.Path(os.environ["HOME"])

try:
    sites = json.loads(pathlib.Path(sys.argv[1]).read_text())
except Exception:
    sys.exit(0)

for site_id, site in sites.items():
    raw = site.get("path") or ""
    root = pathlib.Path(raw.replace("~", str(home), 1) if raw.startswith("~") else raw)
    # sites.json stores the site folder; WordPress lives in app/public under it.
    if target == root.resolve() or root.resolve() in target.parents:
        sock = home / "Library/Application Support/Local/run" / site_id / "mysql/mysqld.sock"
        if sock.exists():
            print(sock)
        sys.exit(0)
PYEOF
}

# True when the given path looks like a site Local manages.
wpsuomi_is_local_site() {
	[ -f "$1/../../conf/php/php.ini.hbs" ] || [ -f "$1/../../conf/mysql/my.cnf.hbs" ]
}

# Works out which runner to use, once. Sets WPSUOMI_RUNNER.
wpsuomi_detect_runner() {
	if [ -n "${WPSUOMI_RUNNER:-}" ]; then
		return
	fi

	if [ -n "$WPSUOMI_WP_CLI" ]; then
		WPSUOMI_RUNNER="custom"
		return
	fi

	if [ -z "$WPSUOMI_WP_PATH" ]; then
		WPSUOMI_RUNNER="wp-env"
		return
	fi

	# Studio drops a STUDIO.md marker in the site root. Check Studio before a
	# bare `wp`, because on a Studio site a bare `wp` would find wp-config.php
	# and then fail against a database it cannot see.
	if [ -f "$WPSUOMI_WP_PATH/STUDIO.md" ] && command -v studio >/dev/null 2>&1; then
		WPSUOMI_RUNNER="studio"
		return
	fi

	# A bare `wp` is right whenever it can actually reach the database — which
	# is the case inside Local's own "Site shell". So probe rather than assume:
	# `core version` would succeed without a database and tell us nothing.
	if command -v wp >/dev/null 2>&1 && wp --path="$WPSUOMI_WP_PATH" option get home >/dev/null 2>&1; then
		WPSUOMI_RUNNER="wp"
		return
	fi

	# Outside that shell, drive Local's bundled WP-CLI with the socket supplied.
	if wpsuomi_is_local_site "$WPSUOMI_WP_PATH" \
		&& [ -f "$WPSUOMI_LOCAL_WP_CLI" ] \
		&& command -v php >/dev/null 2>&1; then
		WPSUOMI_LOCAL_SOCKET="$( wpsuomi_local_socket "$WPSUOMI_WP_PATH" )"
		if [ -n "$WPSUOMI_LOCAL_SOCKET" ]; then
			WPSUOMI_RUNNER="local"
			return
		fi
	fi

	# Deliberately no catch-all here. Having `studio` on your PATH does not make
	# an arbitrary directory a Studio site, and guessing produced a confusing
	# "cannot reach WordPress" for a Local site that was merely stopped.
	WPSUOMI_RUNNER="none"
}

# True when we are pointed at a site on this machine rather than at wp-env.
wp_is_native() {
	[ "$( wp_mode )" != "wp-env" ]
}

# Prints advice for when no runner can reach the site.
wp_hint_unreachable() {
	if [ -z "$WPSUOMI_WP_PATH" ]; then
		info "Is Docker running? Try: npm start"
		return
	fi

	if [ -f "$WPSUOMI_WP_PATH/STUDIO.md" ]; then
		info "Studio: is the site running? studio start --path \"$WPSUOMI_WP_PATH\""
	elif wpsuomi_is_local_site "$WPSUOMI_WP_PATH"; then
		info "Local: the site looks stopped. Start it in Local — the database"
		info "socket only exists while the site runs."
	else
		info "Check that WPSUOMI_WP_PATH points at the folder containing wp-config.php."
		info "Or set WPSUOMI_WP_CLI to a command that works."
	fi
}

# Prints the runner in use: wp-env, studio, wp, custom or none.
wp_mode() {
	wpsuomi_detect_runner
	echo "$WPSUOMI_RUNNER"
}

# Prints a human-readable description of where commands are going.
wp_target() {
	wpsuomi_detect_runner

	case "$WPSUOMI_RUNNER" in
		wp-env) echo "wp-env (Docker)" ;;
		studio) echo "Studio at $WPSUOMI_WP_PATH" ;;
		wp)     echo "WP-CLI at $WPSUOMI_WP_PATH" ;;
		local)  echo "Local at $WPSUOMI_WP_PATH (bundled WP-CLI, own socket)" ;;
		custom) echo "custom runner: $WPSUOMI_WP_CLI" ;;
		*)      echo "no runner found" ;;
	esac
}

# Runs a WP-CLI command in whichever environment is active.
#
# Every runner writes the command's real output to stdout and its own progress
# chatter to stderr, so callers can capture stdout and stay clean.
wp_run() {
	# Detect in this shell, not via $(wp_mode): the Local runner sets
	# WPSUOMI_LOCAL_SOCKET during detection, and a subshell would discard it.
	wpsuomi_detect_runner

	case "$WPSUOMI_RUNNER" in
		custom)
			# Deliberately unquoted: the override carries its own arguments.
			# shellcheck disable=SC2086
			$WPSUOMI_WP_CLI "$@"
			;;
		studio)
			studio wp --path "$WPSUOMI_WP_PATH" "$@"
			;;
		wp)
			wp --path="$WPSUOMI_WP_PATH" "$@"
			;;
		local)
			php -d mysqli.default_socket="$WPSUOMI_LOCAL_SOCKET" \
				"$WPSUOMI_LOCAL_WP_CLI" --path="$WPSUOMI_WP_PATH" "$@"
			;;
		wp-env)
			npx wp-env run cli wp "$@"
			;;
		*)
			fail "No WP-CLI runner available." >&2
			return 1
			;;
	esac
}

# Same as wp_run but throws away output — for existence checks.
wp_quiet() {
	wp_run "$@" >/dev/null 2>&1
}
