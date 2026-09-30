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
 * Registers the abilities. The name, label and description live here, so you
 * can see what is registered; the class implements it.
 */
function uth_register_abilities(): void {
	wp_register_ability(
		'uth/suggest-meta-description',
		[
			'ability_class' => \Uth\Abilities\Suggest_Meta_Description::class,
			'category'      => UTH_CATEGORY,

			// Who reads which string? One is for people, one is for models.
			'label'         => 'Suggest a meta description',
			'description'   => 'Suggests an SEO meta description for one existing post, derived from that post\'s own content. Returns the text without saving it.',
		]
	);
}
add_action( 'wp_abilities_api_init', 'uth_register_abilities' );
