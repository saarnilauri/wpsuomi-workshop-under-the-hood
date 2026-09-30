<?php
/**
 * `wp uth agent` — drives the agent loop from the terminal. Given, not typed.
 *
 * @package Uth
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Asks the agent to do something and prints its answer.
 *
 * ## OPTIONS
 *
 * <instruction>
 * : What to ask the agent to do.
 *
 * ## EXAMPLES
 *
 *     wp uth agent "Improve the meta description of post 6." --user=admin
 *
 * @param array<int,string> $args Positional arguments.
 */
function uth_cli_agent( array $args ): void {
	$answer = uth_run_agent( $args[0] );

	if ( is_wp_error( $answer ) ) {
		WP_CLI::error( $answer->get_error_message() );
	}

	WP_CLI::success( $answer );
}
WP_CLI::add_command( 'uth agent', 'uth_cli_agent' );
