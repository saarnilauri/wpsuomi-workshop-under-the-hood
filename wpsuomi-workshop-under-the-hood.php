<?php
/**
 * Plugin Name:       Under the Hood — Agentic WordPress
 * Plugin URI:        https://github.com/saarnilauri/wpsuomi-workshop-under-the-hood
 * Description:       Workshop plugin for "Under the Hood: How Agentic WordPress Works". Builds one real ability — suggest a meta description — and wires it up to AI agents through the Abilities API, the MCP Adapter and the core PHP AI Client.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Lauri Saarni
 * Author URI:        https://valu.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpsuomi-under-the-hood
 *
 * @package Wpsuomi_Under_The_Hood
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'WPSUOMI_UTH_VERSION', '0.1.0' );
define( 'WPSUOMI_UTH_FILE', __FILE__ );
define( 'WPSUOMI_UTH_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The drivetrain: every part of this plugin is loaded from here.
 *
 * Each step of the workshop adds exactly one require_once line below.
 */
require_once WPSUOMI_UTH_DIR . 'includes/helpers.php';
require_once WPSUOMI_UTH_DIR . 'includes/abilities.php';

/**
 * Checks whether this WordPress install has the Abilities API.
 *
 * The Abilities API landed in WordPress core in 6.9. Before that it lived in a
 * feature plugin. We check for the function rather than the version number so
 * the plugin keeps working if someone backports it.
 *
 * @return bool True when abilities can be registered.
 */
function wpsuomi_uth_has_abilities_api(): bool {
	return function_exists( 'wp_register_ability' );
}

/**
 * Warns the site owner when the engine has nowhere to bolt onto.
 *
 * @return void
 */
function wpsuomi_uth_requirements_notice(): void {
	if ( wpsuomi_uth_has_abilities_api() ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__(
			'Under the Hood needs the Abilities API, which is part of WordPress 6.9 and later. Please update WordPress.',
			'wpsuomi-under-the-hood'
		)
	);
}
add_action( 'admin_notices', 'wpsuomi_uth_requirements_notice' );
