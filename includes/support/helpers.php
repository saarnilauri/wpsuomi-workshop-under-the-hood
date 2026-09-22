<?php
/**
 * Shared helpers. Given, not typed — read these in section 2.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Length we aim a meta description at. Search engines truncate near 160.
 */
const UTH_DEFAULT_MAX_LENGTH = 160;

/**
 * The ignition key, shared by every ability here.
 *
 * Two things matter. It checks edit_post against this specific post, not the
 * blanket edit_posts — so a Contributor's agent can act on its own draft and
 * nobody else's. And it receives the same input as the engine, so permission
 * can depend on the arguments.
 *
 * @param mixed $input Ability input, expected to carry a post_id.
 * @return bool|WP_Error True when the current user may edit the post.
 */
function uth_can_edit_post( $input = null ) {
	$post_id = is_array( $input ) && isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

	if ( $post_id < 1 ) {
		return new WP_Error( 'uth_missing_post_id', 'A post_id is required.', [ 'status' => 400 ] );
	}

	if ( ! get_post( $post_id ) instanceof WP_Post ) {
		return new WP_Error( 'uth_post_not_found', 'No post exists with that post_id.', [ 'status' => 404 ] );
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'uth_cannot_edit_post', 'You are not allowed to edit this post.', [ 'status' => 403 ] );
	}

	return true;
}

/**
 * Resolves a post ID into a post worth working on.
 *
 * @param int $post_id Post ID.
 * @return WP_Post|WP_Error
 */
function uth_get_post( int $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return new WP_Error( 'uth_post_not_found', 'No post exists with that post_id.', [ 'status' => 404 ] );
	}

	if ( ! is_post_type_viewable( $post->post_type ) ) {
		return new WP_Error( 'uth_post_type_not_viewable', 'This post type is not public.', [ 'status' => 400 ] );
	}

	return $post;
}

/**
 * A post's excerpt as clean plain text.
 *
 * get_the_excerpt() returns the hand-written excerpt when there is one and
 * generates one from the content when there is not, which is the fallback we
 * want. It marks generated excerpts with a trailing […], so strip that off.
 *
 * @param WP_Post $post Post to read.
 * @return string Plain text, possibly empty.
 */
function uth_get_excerpt_text( WP_Post $post ): string {
	$text = html_entity_decode( (string) get_the_excerpt( $post ), ENT_QUOTES, (string) get_bloginfo( 'charset' ) );
	$text = uth_collapse_whitespace( wp_strip_all_tags( $text, true ) );

	return (string) preg_replace( '/\s*\[\s*(?:…|\.\.\.)\s*\]\s*$/u', '', $text );
}

/**
 * A post's body as the plain text a visitor would read.
 *
 * Raw post_content is block delimiters, shortcodes and markup. A model reads
 * all of that as noise and pays for it in tokens, so render then strip.
 *
 * @param WP_Post $post Post to read.
 * @return string Plain text, possibly empty.
 */
function uth_get_plain_text( WP_Post $post ): string {
	$content = $post->post_content;

	if ( has_blocks( $content ) ) {
		$content = do_blocks( $content );
	}

	$content = wp_strip_all_tags( strip_shortcodes( $content ), true );
	$content = html_entity_decode( $content, ENT_QUOTES, (string) get_bloginfo( 'charset' ) );

	return uth_collapse_whitespace( $content );
}

/**
 * Collapses every run of whitespace into one space.
 *
 * @param string $text Text to normalise.
 * @return string
 */
function uth_collapse_whitespace( string $text ): string {
	return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Shortens text without cutting a word in half.
 *
 * @param string $text       Text to shorten.
 * @param int    $max_length Maximum characters. Below 1 disables clamping.
 * @return string
 */
function uth_clamp_text( string $text, int $max_length ): string {
	$text = uth_collapse_whitespace( $text );

	if ( $max_length < 1 || mb_strlen( $text ) <= $max_length ) {
		return $text;
	}

	$clamped    = mb_substr( $text, 0, $max_length );
	$last_space = mb_strrpos( $clamped, ' ' );

	// Only back off to a word boundary when that still keeps most of the budget.
	if ( $last_space !== false && $last_space > (int) ( $max_length * 0.6 ) ) {
		$clamped = mb_substr( $clamped, 0, $last_space );
	}

	return rtrim( $clamped, " \t\n\r\0\x0B,;:-" );
}

/**
 * Writes a line to wp-content/debug.log when debug logging is on.
 *
 * @param string $message Message to log.
 * @return void
 */
function uth_log( string $message ): void {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '[under-the-hood] ' . $message );
}
