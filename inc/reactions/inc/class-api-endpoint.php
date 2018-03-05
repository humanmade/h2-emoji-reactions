<?php

namespace H2\Reactions;

use stdClass;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class API_Endpoint extends WP_REST_Controller {
	public function register_routes() {
		register_rest_route( 'h2/v1', '/reactions', array(
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_items' ),
				'args'     => array(
					'post' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_post_argument' ),
					),
					'comment' => array(
						'required'          => false,
						'default'           => null,
						'sanitize_callback' => array( $this, 'validate_comment_id' ),
					),
				),
			],
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'post' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_post_argument' ),
					),
					'comment' => array(
						'required'          => false,
						'default'           => null,
						'sanitize_callback' => array( $this, 'validate_comment_id' ),
					),
					'type' => array(
						'required'          => true,
						'validate_callback' => __NAMESPACE__ . '\\is_valid_type',
					),
				),
			),
		) );
		register_rest_route( 'h2/v1', '/reactions/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => array(
						'default' => 'view',
					),
					'id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_reaction_id' ),
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'default'           => false,
						'sanitize_callback' => function ( $value ) {
							return (bool) $value;
						}
					),
					'id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_reaction_id' ),
					),
				),
			),
		) );
	}

	public function get_items( $request ) {
		$post = get_post( $request['post'] );
		if ( $request['comment'] ) {
			$reactions = Reaction::get_for_comment( $post, $request['comment'] );
		} else {
			$reactions = Reaction::get_for_post( $post );
		}

		if ( is_wp_error( $reactions ) ) {
			return $reactions;
		}

		$items = array();
		foreach ( $reactions as $reaction ) {
			$data = $this->prepare_item_for_response( $reaction, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return $items;
	}

	public function get_item( $request ) {
		$reaction = Reaction::get( $request['id'] );
		if ( is_wp_error( $reaction ) ) {
			return $reaction;
		}

		return $this->prepare_item_for_response( $reaction, $request );
	}

	public function get_item_permissions_check( $request ) {
		return true;
	}

	public function create_item( $request ) {
		$post = get_post( $request['post'] );
		$type = $request['type'];
		$comment = $request['comment'] ? $request['comment'] : null;
		$reaction = Reaction::create( $post, $type, null, $comment );
		if ( is_wp_error( $reaction ) ) {
			return $reaction;
		}

		$get_request = new WP_REST_Request();
		$get_request['id'] = $reaction->get_id();
		return $this->get_item( $get_request );
	}

	/**
	 * Check whether a user can react to a post.
	 *
	 * @return bool True if the user can react, false otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		// WP permissions suck
		return true;
		// return is_user_logged_in();
	}

	public function delete_item( $request ) {
		$reaction = Reaction::get( $request['id'] );
		if ( is_wp_error( $reaction ) ) {
			return $reaction;
		}

		$deleted = $reaction->delete();
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return array(
			'deleted' => true,
		);
	}

	/**
	 * Check whether a user can delete a reaction.
	 *
	 * @param WP_REST_Request $request Request to check permissions for.
	 * @return bool True if the user can delete the reaction, false otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$id      = $request->get_param( 'id' );
		$comment = get_comment( $id );

		if ( ! $comment || ! $comment->user_id ) {
			return false;
		}

		return get_current_user_id() === (int) $comment->user_id;
	}

	public function validate_post_argument( $value ) {
		if ( empty( $value ) ) {
			return false;
		}

		if ( ! preg_match( '/^\d+$/', $value ) ) {
			return false;
		}

		if ( ! get_post( $value ) ) {
			return new WP_Error(
				'h2.reactions.invalid_post',
				__( 'Invalid post ID', 'h2' ),
				[
					'post'   => $value,
					'status' => 404,
				]
			);
		}

		return true;
	}

	public function validate_comment_id( $value, WP_REST_Request $request ) {
		if ( empty( $value ) || ! preg_match( '/^\d+$/', $value ) ) {
			return false;
		}

		/** @var \WP_Comment */
		$comment = get_comment( $value );
		if ( ! $comment || ( $comment->comment_type !== 'comment' && $comment->comment_type !== '' ) ) {
			return new WP_Error(
				'h2.reactions.invalid_comment',
				__( 'Invalid comment ID', 'h2' ),
				[
					'id'     => $value,
					'status' => 400,
				]
			);
		}

		if ( (int) $comment->comment_post_ID !== (int) $request['post'] ) {
			return new WP_Error(
				'h2.reactions.invalid_comment_for_post',
				__( 'Comment does not belong to specified post.', 'h2' ),
				[
					'id'     => $value,
					'status' => 400,
				]
			);
		}

		return $comment->comment_ID;
	}

	public function validate_reaction_id( $value ) {
		if ( empty( $value ) ) {
			return false;
		}

		if ( ! preg_match( '/^\d+$/', $value ) ) {
			return false;
		}

		$comment = get_comment( $value );
		if ( ! $comment || $comment->comment_type !== Reaction::TYPE ) {
			return new WP_Error(
				'h2.reactions.invalid_reaction',
				__( 'Invalid reaction ID', 'h2' ),
				[
					'id' => $value,
					'status' => 404,
				]
			);
		}

		return true;
	}

	/**
	 * Prepare the reaction for the REST response
	 *
	 * @param Reaction $item Reaction to prepare for response.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		$comment = (int) $item->get_comment_id();
		$data = [
			'id'        => $item->get_id(),
			'type'      => $item->get_type(),
			'type_name' => $item->get_type_name(),
			'author'    => $item->get_user_id(),
			'post'      => $item->get_post_id(),
			'comment'   => $comment ? $comment : null,
		];

		$response = new WP_REST_Response( $data );

		$author_route = sprintf( 'wp/v2/users/%d', $item->get_user_id() );
		$response->add_link( 'author', get_rest_url( null, $author_route ), [ 'embeddable' => true ] );

		return $response;
	}
}
