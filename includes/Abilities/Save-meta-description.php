<?php
/**
 * The write ability. Almost identical to the read ability — the differences
 * are the ones that matter when the agent is wrong.
 *
 * @package Uth
 */

declare( strict_types = 1 );

namespace Uth\Abilities;

use WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Saves a meta description onto a post, into the key The SEO Framework reads.
 */
class Save_Meta_Description extends WP_Ability {

	/**
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
	 * @return array<string,mixed>
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'post_id'     => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the post to write to.',
				],
				'description' => [
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'The meta description to save.',
				],
			],
			'required'             => [ 'post_id', 'description' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'post_id'     => [ 'type' => 'integer' ],
				'description' => [ 'type' => 'string' ],
			],
			'required'   => [ 'post_id', 'description' ],
		];
	}

	/**
	 * The lane change. Core derives the HTTP method from the annotations:
	 * readonly -> GET, destructive AND idempotent -> DELETE, anything else -> POST.
	 *
	 * @return array<string,mixed>
	 */
	protected function meta(): array {
		return [
			'public'      => true,
			'annotations' => [
				'readonly'    => false,
				'destructive' => false, // <- flip this live and the POST comes back 405
				'idempotent'  => true,
			],
		];
	}

	/**
	 * @param array<string,mixed> $input Validated input.
	 * @return bool
	 */
	public function permission_callback( $input ): bool {
		return current_user_can( 'edit_post', $input['post_id'] );
	}

	/**
	 * @param array<string,mixed> $input Validated input.
	 * @return array<string,mixed>
	 */
	public function execute_callback( $input ): array {
		// In real life: sanitise the text, and return the previous value so the
		// change can be undone.
		update_post_meta( $input['post_id'], '_genesis_description', $input['description'] );

		return [
			'post_id'     => $input['post_id'],
			'description' => $input['description'],
		];
	}
}
