<?php
/**
 * Where a saved meta description goes.
 *
 * A bare WordPress install has nowhere native to put one, so we register our
 * own key. That way the save ability does something observable on every laptop
 * in the room, with no SEO plugin installed.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Our own meta key. Always written.
 */
const UTH_META_KEY = '_uth_meta_description';

/**
 * Registers the meta key.
 *
 * register_post_meta() rather than a raw update_post_meta(), because
 * registering buys REST exposure and a sanitisation callback for free — the
 * same trade the Abilities API makes.
 */
function uth_register_post_meta(): void {
	register_post_meta(
		'post',
		UTH_META_KEY,
		[
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => 'uth_can_edit_meta',
		]
	);
}
add_action( 'init', 'uth_register_post_meta' );

/**
 * Gates REST access to our meta key.
 *
 * Untyped parameters on purpose: WordPress calls auth callbacks with a
 * variable signature, and this file declares strict_types.
 *
 * @param mixed $allowed   Unused.
 * @param mixed $meta_key  Unused.
 * @param mixed $object_id Post ID.
 * @return bool
 */
function uth_can_edit_meta( $allowed = false, $meta_key = '', $object_id = 0 ): bool {
	return current_user_can( 'edit_post', absint( $object_id ) );
}

/**
 * Every key a saved description is written to.
 *
 * Ours unconditionally. The SEO plugin keys only while that plugin is active —
 * writing _yoast_wpseo_metadesc on a site without Yoast just leaves an orphan
 * row nothing will ever render.
 *
 * @return list<string>
 */
function uth_meta_description_keys(): array {
	$keys = [ UTH_META_KEY ];

	// Yoast composes its keys as '_yoast_wpseo_' . $field.
	if ( defined( 'WPSEO_VERSION' ) ) {
		$keys[] = '_yoast_wpseo_metadesc';
	}

	// The SEO Framework stores each field as its own row.
	if ( defined( 'THE_SEO_FRAMEWORK_PRESENT' ) ) {
		$keys[] = '_genesis_description';
	}

	return $keys;
}
