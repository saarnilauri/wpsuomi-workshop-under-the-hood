<?php
/**
 * Self-driving mode.
 *
 * Everything so far had a human or an external client deciding which ability
 * to call. Here WordPress decides for itself.
 *
 * The loop stays small because core does both hard parts:
 *
 *   ->using_abilities( ... )   turns abilities into function declarations from
 *                              each ability's description and input schema —
 *                              the same strings an MCP client read in section 5
 *
 *   WP_AI_Client_Ability_Function_Resolver
 *                              executes only the abilities you handed it
 *
 * That allowlist is the safety boundary: a model cannot talk this loop into
 * calling something you did not pass in. Every call still goes through
 * WP_Ability::execute(), so input validation and the permission callback run
 * exactly as they did for WP-CLI and for MCP. The agent is not privileged — it
 * acts as whoever is logged in.
 *
 * @package Uth
 */

declare( strict_types = 1 );

use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;

defined( 'ABSPATH' ) || exit;

/**
 * How many model turns before we give up. A loop with no bound is a bill with
 * no bound.
 */
const UTH_AGENT_MAX_TURNS = 6;

/**
 * Runs the internal agent loop.
 *
 * @param string $instruction Plain-language instruction.
 * @param int    $max_turns   Maximum model turns.
 * @param string $model       Optional model ID to prefer. Empty lets core choose.
 * @return array<string,mixed>|WP_Error Transcript of what happened, or an error.
 */
function uth_run_agent( string $instruction, int $max_turns = UTH_AGENT_MAX_TURNS, string $model = '' ) {
	if ( ! uth_ai_is_available() ) {
		return new WP_Error( 'uth_ai_unavailable', 'No AI provider is configured.', [ 'status' => 503 ] );
	}

	if ( ! class_exists( 'WP_AI_Client_Ability_Function_Resolver' ) ) {
		return new WP_Error( 'uth_resolver_missing', 'This WordPress has no ability function resolver.', [ 'status' => 501 ] );
	}

	// The abilities we are willing to let the agent drive. Nothing else.
	$abilities = [];
	foreach ( [ 'uth/suggest-meta-description', 'uth/save-meta-description' ] as $name ) {
		$ability = wp_get_ability( $name );
		if ( $ability instanceof WP_Ability ) {
			$abilities[] = $ability;
		}
	}

	if ( ! $abilities ) {
		return new WP_Error( 'uth_no_abilities', 'The workshop abilities are not registered.', [ 'status' => 500 ] );
	}

	$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );

	$system = 'You maintain SEO metadata on a WordPress site. Work only through the tools you have been given. To change a meta description, call the suggest tool to generate text and then the save tool to store it. Do not invent a description yourself, and do not claim to have saved anything you did not save through the save tool. When you are finished, reply with one short sentence describing what you changed.';

	/**
	 * Filters which models the agent loop prefers, in order.
	 *
	 * Leave empty with a hosted provider. It matters for local models: core
	 * does require tool support, but ai-provider-for-ollama declares that
	 * option for every completion model, so a model that cannot call tools can
	 * still look like a valid candidate. Check with `ollama show` and pin one.
	 *
	 * @param string[] $models Model IDs to prefer, in order.
	 */
	$preferred = (array) apply_filters( 'uth_agent_model_preference', $model === '' ? [] : [ $model ] );

	$history    = [];
	$current    = new UserMessage( [ new MessagePart( $instruction ) ] );
	$tool_calls = [];
	$turn       = 0;

	while ( $turn < $max_turns ) {
		++$turn;

		$builder = wp_ai_client_prompt( $current )
			->using_system_instruction( $system )
			->using_abilities( ...$abilities )
			->using_temperature( 0.2 );

		if ( $preferred ) {
			$builder = $builder->using_model_preference( ...$preferred );
		}

		if ( $history ) {
			$builder = $builder->with_history( ...$history );
		}

		$result = $builder->generate_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = $result->toMessage();

		// No tool calls left: the model is reporting back, so we are done.
		if ( ! $resolver->has_ability_calls( $message ) ) {
			return [
				'instruction' => $instruction,
				'turns'       => $turn,
				'tool_calls'  => $tool_calls,
				'answer'      => uth_collapse_whitespace( $result->toText() ),
				'completed'   => true,
			];
		}

		/*
		 * One response per message, deliberately. The resolver's own
		 * execute_abilities() bundles every response into a single message,
		 * which is fine until a model emits two tool calls in one turn — then
		 * providers reject the batch with "The API only allows a single
		 * function response, as the only content of the message."
		 */
		$responses = [];

		foreach ( uth_collect_ability_calls( $message, $resolver ) as $call ) {
			$tool_calls[] = [
				'ability'   => WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( (string) $call->getName() ),
				'arguments' => $call->getArgs(),
			];

			$responses[] = new UserMessage( [ new MessagePart( $resolver->execute_ability( $call ) ) ] );
		}

		$history[] = $current;
		$history[] = $message;

		// The newest response is what we send this turn; the rest is history.
		$current = array_pop( $responses );

		foreach ( $responses as $response ) {
			$history[] = $response;
		}
	}

	return [
		'instruction' => $instruction,
		'turns'       => $turn,
		'tool_calls'  => $tool_calls,
		'answer'      => '',
		'completed'   => false,
	];
}

/**
 * Collects the ability calls a model asked for.
 *
 * Only calls the resolver recognises are returned, so a model inventing a
 * function name cannot get anything executed.
 *
 * @param object                                 $message  The model's message.
 * @param WP_AI_Client_Ability_Function_Resolver $resolver Holds the allowlist.
 * @return array<int,object> The calls to execute.
 */
function uth_collect_ability_calls( $message, WP_AI_Client_Ability_Function_Resolver $resolver ): array {
	$calls = [];

	foreach ( $message->getParts() as $part ) {
		if ( ! $part->getType()->isFunctionCall() ) {
			continue;
		}

		$call = $part->getFunctionCall();

		if ( $call && $resolver->is_ability_call( $call ) ) {
			$calls[] = $call;
		}
	}

	return $calls;
}
