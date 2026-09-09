#!/usr/bin/env bash
# One-time setup for Studio and Local.
#
# Symlinks this repo into your site's plugins folder, then provisions the rest.
# You keep editing files here in the repo; WordPress sees them immediately, and
# switching branches or checkpoints still does what you expect.
#
#   bash bin/setup-native.sh "/path/to/site/wordpress-root"
#
# The path is the folder that contains wp-config.php. For example:
#   Local:   ~/Local Sites/under-the-hood/app/public
#   Studio:  ~/Studio/under-the-hood

set -uo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_DIR="$( cd "$SCRIPT_DIR/.." && pwd )"
# shellcheck source=./lib-wp.sh
source "$SCRIPT_DIR/lib-wp.sh"

SITE_PATH="${1:-${WPSUOMI_WP_PATH:-}}"
PLUGIN_SLUG="wpsuomi-workshop-under-the-hood"

if [ -z "$SITE_PATH" ]; then
	fail "No site path given."
	info "Usage: bash bin/setup-native.sh \"/path/to/site/wordpress-root\""
	info "That is the folder containing wp-config.php."
	exit 1
fi

# Expand ~ and resolve to an absolute path.
SITE_PATH="${SITE_PATH/#\~/$HOME}"
if [ ! -d "$SITE_PATH" ]; then
	fail "No such folder: $SITE_PATH"
	exit 1
fi
SITE_PATH="$( cd "$SITE_PATH" && pwd )"

bold "Setting up $SITE_PATH"

if [ ! -f "$SITE_PATH/wp-config.php" ]; then
	fail "No wp-config.php in that folder."
	info "Point this at your WordPress root, not the site folder above it."
	exit 1
fi
ok "Found wp-config.php"

# Which WP-CLI can reach this site? lib-wp.sh works that out for us.
export WPSUOMI_WP_PATH="$SITE_PATH"
unset WPSUOMI_RUNNER
MODE="$( wp_mode )"

case "$MODE" in
	studio)
		ok "Studio CLI will run WP-CLI for this site"
		info "Studio runs its own PHP and a SQLite drop-in, so a standalone"
		info "'wp' cannot reach this database. All commands go via 'studio wp'."
		;;
	wp)
		ok "WP-CLI $( wp --version 2>/dev/null | awk '{print $2}' )"
		;;
	local)
		ok "Local's bundled WP-CLI will run commands for this site"
		info "Local's MySQL only accepts socket connections, so we point PHP at"
		info "the site's own socket. Local's 'Site shell' does the same thing."
		;;
	custom)
		ok "Using WPSUOMI_WP_CLI: $WPSUOMI_WP_CLI"
		;;
	*)
		fail "No WP-CLI runner can reach this site."
		info "Studio: install the Studio CLI so 'studio' is on your PATH."
		info "Local:  make sure the site is STARTED (the socket only exists while"
		info "        it runs), or click the site's 'Site shell' button and run"
		info "        this script from there."
		info "Or set WPSUOMI_WP_CLI to a command that works."
		exit 1
		;;
esac

if ! wp_quiet core is-installed; then
	fail "Found a runner, but it cannot reach WordPress."
	info "Is the site running? Studio: studio start --path \"$SITE_PATH\""
	exit 1
fi
ok "WordPress $( wp_run core version 2>/dev/null | tr -d '\r' )"

PLUGIN_TARGET="$SITE_PATH/wp-content/plugins/$PLUGIN_SLUG"

if [ -L "$PLUGIN_TARGET" ]; then
	ok "Symlink already in place"
elif [ -e "$PLUGIN_TARGET" ]; then
	warn "$PLUGIN_TARGET exists and is not a symlink — leaving it alone"
	info "Delete it first if you want the repo linked instead."
else
	if ln -s "$REPO_DIR" "$PLUGIN_TARGET"; then
		ok "Linked repo -> wp-content/plugins/$PLUGIN_SLUG"
	else
		fail "Could not create the symlink."
		info "Fallback: copy this folder into wp-content/plugins/ instead."
		exit 1
	fi
fi

echo
bash "$SCRIPT_DIR/provision.sh"

echo
bold "Next"
info "Add this to your shell profile so the other scripts find the site:"
printf '\n    export WPSUOMI_WP_PATH="%s"\n\n' "$SITE_PATH"
info "Then check everything: bash bin/verify-env.sh"
