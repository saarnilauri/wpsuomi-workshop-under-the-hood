<?php
/**
 * Plugin Name:       Under the Hood — Agentic WordPress
 * Plugin URI:        https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood
 * Description:       Workshop plugin for "Under the Hood: How Agentic WordPress Works".
 * Version:           0.2.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            Lauri Saarni
 * Author URI:        https://valu.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

const UTH_DIR = __DIR__ . '/';

/**
 * Everything loads from here. Each checkpoint adds one line.
 *
 * includes/         built together on the day
 * includes/support/ given to you, so the code-along stays focused
 */
require_once UTH_DIR . 'includes/support/helpers.php';
require_once UTH_DIR . 'includes/abilities.php';

/**
 * Warns when the Abilities API is missing. It is core from WordPress 6.9, but
 * check the function rather than the version so a backport still works.
 */
function uth_requirements_notice(): void {
	if ( function_exists( 'wp_register_ability' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( 'Under the Hood needs the Abilities API, which is core from WordPress 6.9. Please update.' )
	);
}
add_action( 'admin_notices', 'uth_requirements_notice' );
