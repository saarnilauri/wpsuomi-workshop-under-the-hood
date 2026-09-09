<?php
/**
 * Ability categories.
 *
 * A category is the section of the owner's manual an ability is filed under.
 *
 * There are TWO init hooks, and using the wrong one is the most common way to
 * lose an afternoon:
 *
 *     wp_abilities_api_categories_init  ->  wp_register_ability_category()
 *     wp_abilities_api_init             ->  wp_register_ability()
 *
 * Categories get their own, earlier hook precisely so that a category always
 * exists before any ability can reference it. Register a category on the wrong
 * hook and core drops it with a `_doing_it_wrong()` notice — then drops every
 * ability that referenced it, too.
 *
 * @package Wpsuomi_Under_The_Hood
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The category slug every ability in this plugin belongs to.
 *
 * Core validates slugs against `^[a-z0-9]+(?:-[a-z0-9]+)*$`.
 */
const WPSUOMI_UTH_CATEGORY = 'wpsuomi-seo';

/**
 * Registers the category this workshop's abilities live in.
 *
 * @return void
 */
function wpsuomi_uth_register_ability_category(): void {
	wp_register_ability_category(
		WPSUOMI_UTH_CATEGORY,
		[
			'label'       => __( 'SEO', 'wpsuomi-under-the-hood' ),
			'description' => __( 'Abilities that read and write the SEO metadata of a post.', 'wpsuomi-under-the-hood' ),
		]
	);
}
add_action( 'wp_abilities_api_categories_init', 'wpsuomi_uth_register_ability_category' );

/*
 * The abilities themselves will live in their own files, each hooking
 * `wp_abilities_api_init`. We add them together during the session.
 */
