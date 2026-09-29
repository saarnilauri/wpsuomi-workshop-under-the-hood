<?php
/**
 * A driver that is just an ordinary WordPress hook.
 *
 * No agent, no MCP, no model. save_post calls the same ability, and you still
 * get schema validation and the permission check without writing either twice.
 *
 * Production would put this behind a setting. A workshop plugin runs it so you
 * can press Update in the editor and watch the log.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Suggests a meta description whenever a published post is saved.
 *
 * Untyped parameters on purpose. This file declares strict_types, and a strict
 * signature on a hook callback turns any loose caller into a fatal error.
 *
 * @param mixed $post_id Post ID.
 * @param mixed $post    Post object.
 * @return void
 */
function uth_suggest_on_save( $post_id, $post = null ): void {
	$post_id = absint( $post_id );

	if ( ! $post instanceof WP_Post ) {
		$post = get_post( $post_id );
	}

	if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
		return;
	}

	// Autosaves and revisions are not editorial decisions.
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$ability = wp_get_ability( 'uth/suggest-meta-description' );
	if ( ! $ability instanceof WP_Ability ) {
		return;
	}

	$result = $ability->execute( [ 'post_id' => $post_id ] );

	if ( is_wp_error( $result ) ) {
		uth_log( sprintf(
			'save_post refused for post %d: [%s] %s',
			$post_id,
			$result->get_error_code(),
			$result->get_error_message()
		) );
		return;
	}

	uth_log( sprintf(
		'save_post suggested for post %d (%d chars, source: %s): %s',
		$post_id,
		(int) $result['length'],
		(string) $result['source'],
		(string) $result['suggestion']
	) );
}
add_action( 'save_post', 'uth_suggest_on_save', 10, 2 );
