<?php
/**
 * Ability category, and the one place abilities get registered.
 *
 * Two init hooks, not one. Categories fire earlier so a category always exists
 * before an ability references it. Use the wrong hook and core drops the
 * category with a _doing_it_wrong() notice, then drops every ability using it.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

const UTH_CATEGORY = 'uth-seo';

/**
 * Registers the category our abilities are filed under.
 */
function uth_register_ability_category(): void {
	wp_register_ability_category(
		UTH_CATEGORY,
		[
			'label'       => 'SEO',
			'description' => 'Abilities that read and write the SEO metadata of a post.',
		]
	);
}
add_action( 'wp_abilities_api_categories_init', 'uth_register_ability_category' );

/**
 * Registers the abilities.
 *
 * Each one is a class. The registration says what it is called and which class
 * implements it; the class owns its schemas, permission check and engine.
 */
function uth_register_abilities(): void {
	// Section 3 adds the suggest ability here.
	// Section 4 adds the save ability here.
}
add_action( 'wp_abilities_api_init', 'uth_register_abilities' );
