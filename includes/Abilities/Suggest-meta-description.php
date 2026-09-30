<?php
/**
 * The read ability. No AI in here yet — WordPress already summarises a post,
 * that is what an excerpt is.
 *
 * @package Uth
 */

declare( strict_types = 1 );

namespace Uth\Abilities;

use WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests an SEO meta description for a post.
 */
class Suggest_Meta_Description extends WP_Ability {

	/**
	 * Name, label and description arrive from the registration call. The class
	 * adds the implementation: schemas, permission, engine.
	 *
	 * @param string              $name       Ability name.
	 * @param array<string,mixed> $properties Properties from the registration call.
	 */
	public function __construct( string $name, array $properties = [] ) {
		parent::__construct(
			$name,
			array_merge(
				$properties,
				[
					'input_schema'        => $this->input_schema(),
					'output_schema'       => $this->output_schema(),
					'permission_callback' => [ $this, 'permission_callback' ],
					'execute_callback'    => [ $this, 'execute_callback' ],
					'meta'                => $this->meta(),
				]
			)
		);
	}

	/**
	 * What may go in. Core validates against this before anything runs.
	 *
	 * @return array<string,mixed>
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'post_id' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the post to describe.',
				],
			],
			'required'             => [ 'post_id' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * What comes out. Core validates the return value against this too.
	 *
	 * @return array<string,mixed>
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'    => [ 'type' => 'integer' ],
				'suggestion' => [ 'type' => 'string' ],
			],
			'required'   => [ 'post_id', 'suggestion' ],
		];
	}

	/**
	 * Who may drive it, and what clients are told about it.
	 *
	 * @return array<string,mixed>
	 */
	protected function meta(): array {
		return [
			'public'      => true,
			'annotations' => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}

	/**
	 * The ignition key. Receives the same input as the engine.
	 *
	 * @param array<string,mixed> $input Validated input.
	 * @return bool
	 */
	public function permission_callback( $input ): bool {
		return current_user_can( 'edit_post', $input['post_id'] );
	}

	/**
	 * The engine. Input is validated and permission granted by the time this runs.
	 *
	 * @param array<string,mixed> $input Validated input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function execute_callback( $input ) {
		// In real life: check the post exists and is public before reading it.

		return [
			'post_id'    => $input['post_id'],
			'suggestion' => get_the_excerpt( $input['post_id'] ),
		];
	}
}
