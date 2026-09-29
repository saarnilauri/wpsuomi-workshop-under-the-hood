<?php
/**
 * WP-CLI commands for section 6.
 *
 * Given, not typed. Everything before section 6 is driven with plain `wp eval`.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Runs the internal agent loop and prints what it chose to do.
 *
 * ## OPTIONS
 *
 * <instruction>
 * : What to ask the agent to do.
 *
 * [--max-turns=<n>]
 * : Maximum model turns. Default 6.
 *
 * [--model=<id>]
 * : Model to prefer, e.g. qwen2.5:3b. Recommended with Ollama.
 *
 * ## EXAMPLES
 *
 *     wp uth agent "Improve the meta description of post 6." --model=qwen2.5:3b --user=admin
 *
 * @param array<int,string>    $args       Positional arguments.
 * @param array<string,string> $assoc_args Flags.
 */
function uth_cli_agent( array $args, array $assoc_args ): void {
	$instruction = (string) ( $args[0] ?? '' );

	if ( $instruction === '' ) {
		WP_CLI::error( 'Give the agent an instruction.' );
	}

	$result = uth_run_agent(
		$instruction,
		(int) ( $assoc_args['max-turns'] ?? UTH_AGENT_MAX_TURNS ),
		(string) ( $assoc_args['model'] ?? '' )
	);

	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $result->get_error_message() );
	}

	WP_CLI::line( '' );
	WP_CLI::line( sprintf( 'Turns: %d   Ability calls: %d', $result['turns'], count( $result['tool_calls'] ) ) );
	WP_CLI::line( '' );

	foreach ( $result['tool_calls'] as $i => $call ) {
		WP_CLI::line( sprintf( '  %d. %s', $i + 1, $call['ability'] ) );
		WP_CLI::line( '     ' . wp_json_encode( $call['arguments'] ) );
	}

	WP_CLI::line( '' );

	if ( ! $result['completed'] ) {
		WP_CLI::warning( 'Hit the turn limit before finishing.' );
		return;
	}

	if ( $result['answer'] === '' ) {
		WP_CLI::success( 'Finished without a closing message.' );
		return;
	}

	WP_CLI::success( $result['answer'] );
}
WP_CLI::add_command( 'uth agent', 'uth_cli_agent' );

/**
 * Lists the models this site can actually reach.
 *
 * Separates "provider plugin inactive" from "active but no credentials", which
 * is the fastest answer to "why is my provider not showing up".
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : table or ids. Default table.
 *
 * @param array<int,string>    $args       Positional arguments.
 * @param array<string,string> $assoc_args Flags.
 */
function uth_cli_list_models( array $args, array $assoc_args ): void {
	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		WP_CLI::error( 'This WordPress has no AI client.' );
	}

	$rows = [];

	foreach ( uth_ai_provider_models() as $provider => $models ) {
		if ( $models === null ) {
			$rows[] = [
				'provider' => $provider,
				'model'    => '',
				'status'   => 'registered, not configured',
			];
			continue;
		}

		foreach ( $models as $model ) {
			$rows[] = [
				'provider' => $provider,
				'model'    => $model,
				'status'   => 'available',
			];
		}
	}

	if ( ! $rows ) {
		WP_CLI::warning( 'No provider is registered. Activate a provider plugin, then configure it at Settings > Connectors.' );
		return;
	}

	if ( ( $assoc_args['format'] ?? 'table' ) === 'ids' ) {
		foreach ( $rows as $row ) {
			if ( $row['model'] !== '' ) {
				WP_CLI::line( $row['model'] );
			}
		}
		return;
	}

	WP_CLI\Utils\format_items( 'table', $rows, [ 'provider', 'model', 'status' ] );
}
WP_CLI::add_command( 'uth list-models', 'uth_cli_list_models' );
