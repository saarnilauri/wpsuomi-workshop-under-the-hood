<?php
/**
 * Self-driving mode. Given, not typed — read it in section 7.
 *
 * Until now a person or an external client chose which ability to call. Here
 * WordPress asks a model, and the model chooses. Core does both hard parts:
 *
 *   using_abilities()  turns each ability's description and input schema into a
 *                      tool the model can call — the same strings MCP clients read
 *   the resolver       runs a call only if its ability is on the list you gave it
 *
 * Every call still goes through the ability, so the schema and the permission
 * check run exactly as they did for WP-CLI, REST and MCP.
 *
 * @package Uth
 */

declare( strict_types = 1 );

use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the agent until it stops calling abilities.
 *
 * @param string $instruction What to ask for, in plain language.
 * @return string|WP_Error The model's closing answer.
 */
function uth_run_agent( string $instruction ) {
	// The allowlist. The model cannot call anything that is not on it.
	$abilities = [ 'uth/suggest-meta-description', 'uth/save-meta-description' ];
	$resolver  = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
	$messages  = [ new UserMessage( [ new MessagePart( $instruction ) ] ) ];

	// Bounded: a loop with no limit is a bill with no limit.
	for ( $turn = 1; $turn <= 5; $turn++ ) {
		$result = wp_ai_client_prompt( $messages )
			->using_system_instruction( 'To change a meta description, call the suggest tool first, then save exactly the text it returned. Never write a description yourself.' )
			->using_abilities( ...$abilities )
			->generate_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message    = $result->toMessage();
		$messages[] = $message;

		// No more calls: the model is reporting back.
		if ( ! $resolver->has_ability_calls( $message ) ) {
			return $result->toText();
		}

		$messages[] = $resolver->execute_abilities( $message );
	}

	return new WP_Error( 'uth_agent_out_of_turns', 'The agent ran out of turns.' );
}
