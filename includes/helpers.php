<?php
/**
 * Shared parts used by every ability in this plugin.
 *
 * @package Wpsuomi_Under_The_Hood
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The default meta description length we aim for, in characters.
 *
 * Search engines truncate somewhere around 155-160 characters. We treat that
 * as a target, not a hard limit, and let callers override it.
 */
const WPSUOMI_UTH_DEFAULT_MAX_LENGTH = 160;

/**
 * The ignition key.
 *
 * This is the permission callback shared by every ability here. Two things are
 * worth noticing, because they are the whole point of section 3:
 *
 * 1. It checks `edit_post` against *this specific post*, not the blanket
 *    `edit_posts`. An agent acting as a Contributor can therefore suggest a
 *    description for its own draft but not for somebody else's published post.
 * 2. It receives the same input as the execute callback. Permission decisions
 *    can depend on the arguments, which is exactly what per-object checks need.
 *
 * @param mixed $input Ability input. Expected to be an array with a `post_id`.
 * @return bool|WP_Error True when the current user may edit the post.
 */
function wpsuomi_uth_can_edit_post( $input = null ) {
	$post_id = 0;

	if ( is_array( $input ) && isset( $input['post_id'] ) ) {
		$post_id = absint( $input['post_id'] );
	}

	if ( $post_id < 1 ) {
		return new WP_Error(
			'wpsuomi_uth_missing_post_id',
			__( 'A post_id is required.', 'wpsuomi-under-the-hood' ),
			[ 'status' => 400 ]
		);
	}

	if ( ! get_post( $post_id ) instanceof WP_Post ) {
		return new WP_Error(
			'wpsuomi_uth_post_not_found',
			__( 'No post exists with that post_id.', 'wpsuomi-under-the-hood' ),
			[ 'status' => 404 ]
		);
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'wpsuomi_uth_cannot_edit_post',
			__( 'You are not allowed to edit this post.', 'wpsuomi-under-the-hood' ),
			[ 'status' => 403 ]
		);
	}

	return true;
}

/**
 * Resolves a post ID into a post we are willing to work on.
 *
 * The permission callback has already run by the time an execute callback is
 * called, so this is about data shape rather than authorisation.
 *
 * @param int $post_id Post ID.
 * @return WP_Post|WP_Error The post, or an error explaining why not.
 */
function wpsuomi_uth_get_post( int $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return new WP_Error(
			'wpsuomi_uth_post_not_found',
			__( 'No post exists with that post_id.', 'wpsuomi-under-the-hood' ),
			[ 'status' => 404 ]
		);
	}

	if ( ! is_post_type_viewable( $post->post_type ) ) {
		return new WP_Error(
			'wpsuomi_uth_post_type_not_viewable',
			__( 'This post type is not public, so it has no meta description to write.', 'wpsuomi-under-the-hood' ),
			[ 'status' => 400 ]
		);
	}

	return $post;
}

/**
 * Flattens a post into the plain text a visitor would actually read.
 *
 * Raw `post_content` is full of block delimiters, shortcodes and markup. An AI
 * model reads all of that as noise and pays for it in tokens, so we render the
 * blocks first and then strip everything down to prose.
 *
 * @param WP_Post $post The post to read.
 * @return string Plain text, whitespace collapsed. May be an empty string.
 */
function wpsuomi_uth_get_plain_text( WP_Post $post ): string {
	$content = $post->post_content;

	// Render blocks so synced patterns and dynamic blocks contribute their text.
	if ( function_exists( 'do_blocks' ) && has_blocks( $content ) ) {
		$content = do_blocks( $content );
	}

	$content = strip_shortcodes( $content );
	$content = wp_strip_all_tags( $content, true );
	$content = html_entity_decode( $content, ENT_QUOTES, (string) get_bloginfo( 'charset' ) );

	return wpsuomi_uth_collapse_whitespace( $content );
}

/**
 * Collapses every run of whitespace into a single space and trims the result.
 *
 * @param string $text Text to normalise.
 * @return string Normalised text.
 */
function wpsuomi_uth_collapse_whitespace( string $text ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Shortens text to a maximum length without cutting a word in half.
 *
 * @param string $text       Text to shorten.
 * @param int    $max_length Maximum length in characters. Values below 1 disable clamping.
 * @return string The shortened text.
 */
function wpsuomi_uth_clamp_text( string $text, int $max_length ): string {
	$text = wpsuomi_uth_collapse_whitespace( $text );

	if ( $max_length < 1 || mb_strlen( $text ) <= $max_length ) {
		return $text;
	}

	$clamped    = mb_substr( $text, 0, $max_length );
	$last_space = mb_strrpos( $clamped, ' ' );

	/*
	 * Only fall back to the last word boundary when doing so still keeps most
	 * of the budget. Otherwise a single very long word would gut the result.
	 */
	if ( $last_space !== false && $last_space > (int) ( $max_length * 0.6 ) ) {
		$clamped = mb_substr( $clamped, 0, $last_space );
	}

	return rtrim( $clamped, " \t\n\r\0\x0B,;:-" );
}
