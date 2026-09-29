<?php
/**
 * The onboard computer.
 *
 * On WordPress 7.1 the PHP AI Client is core. There is no SDK to install and
 * no HTTP client to write: wp_ai_client_prompt() returns a fluent builder, and
 * the site owner picks a provider at Settings > Connectors.
 *
 * Nothing here names a provider or a model. The same code runs against
 * Anthropic, Google, OpenAI, Mistral or a local Ollama model.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * How much of a post we send. A meta description does not need the whole
 * article, and tokens cost money and latency.
 */
const UTH_AI_INPUT_LIMIT = 4000;

/**
 * Whether a text-generation roundtrip is possible right now.
 *
 * Three things must be true and they fail differently: the core client exists,
 * AI is not switched off for this request, and a configured provider can
 * actually generate text. The last is what is false on a fresh install.
 */
function uth_ai_is_available(): bool {
	static $available = null;

	if ( $available !== null ) {
		return $available;
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
		$available = false;
		return $available;
	}

	/**
	 * Filters whether this plugin uses AI at all.
	 *
	 * Set it false to force the section 3 excerpt path and compare the two live.
	 *
	 * @param bool $use_ai Whether to attempt a roundtrip. Default true.
	 */
	if ( ! apply_filters( 'uth_use_ai', true ) ) {
		$available = false;
		return $available;
	}

	$available = wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation() === true;

	return $available;
}

/**
 * Asks a model for a meta description.
 *
 * @param WP_Post $post       Post to describe.
 * @param int     $max_length Target maximum characters.
 * @return string|WP_Error The suggestion, or an error.
 */
function uth_ai_suggest( WP_Post $post, int $max_length ) {
	if ( ! uth_ai_is_available() ) {
		return new WP_Error( 'uth_ai_unavailable', 'No AI provider is configured.', [ 'status' => 503 ] );
	}

	$body = uth_get_plain_text( $post );

	if ( $body === '' ) {
		return new WP_Error( 'uth_nothing_to_summarise', 'This post has no body text.', [ 'status' => 422 ] );
	}

	/*
	 * Models count words far better than characters, so give the budget both
	 * ways and aim slightly short. The clamp downstream is a safety net, and a
	 * clamp that fires cuts a sentence in half on screen.
	 */
	$char_budget = max( 50, $max_length - 10 );
	$word_budget = max( 8, (int) round( $char_budget / 6.5 ) );

	$instruction = sprintf(
		'You write SEO meta descriptions for WordPress posts. Reply with exactly one description and nothing else: no quotes, no preamble, no alternatives. Keep it under %1$d characters, roughly %2$d words, and finish the sentence. Write in the same language as the post. Summarise what the post says and never invent facts.',
		$char_budget,
		$word_budget
	);

	$prompt = sprintf(
		"Title: %s\n\nContent:\n%s",
		$post->post_title,
		uth_clamp_text( $body, UTH_AI_INPUT_LIMIT )
	);

	// Only the generating call returns a WP_Error; the fluent chain holds
	// errors until then so it never breaks mid-sentence.
	$generated = wp_ai_client_prompt( $prompt )
		->using_system_instruction( $instruction )
		->using_temperature( 0.3 )
		->using_max_tokens( 300 )
		->generate_text();

	if ( is_wp_error( $generated ) ) {
		return $generated;
	}

	$cleaned = uth_clean_ai_text( (string) $generated );

	if ( $cleaned === '' ) {
		return new WP_Error( 'uth_ai_empty_response', 'The model returned nothing usable.', [ 'status' => 502 ] );
	}

	return $cleaned;
}

/**
 * Tidies up the habits models have even when told not to: labelling the answer
 * and wrapping it in quotes. Cheaper to strip than to prompt away.
 *
 * @param string $text Raw model output.
 * @return string
 */
function uth_clean_ai_text( string $text ): string {
	$text = uth_collapse_whitespace( $text );
	$text = (string) preg_replace( '/^(?:meta\s+)?description\s*[:\-–]\s*/iu', '', $text );

	return uth_collapse_whitespace( trim( $text, "\"'“”‘’" ) );
}
