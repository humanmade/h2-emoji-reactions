<?php

namespace H2\Reactions;

use H2\Emoji;
use WP_Error;
use WP_Post;
use WP_User;

class Reaction {
	/**
	 * Custom comment type.
	 */
	const TYPE = 'h2_reaction';

	/**
	 * Constructor.
	 *
	 * @param stdClass $comment Comment object to wrap.
	 */
	public function __construct( $comment ) {
		$this->comment = $comment;
	}

	/**
	 * Get the reaction ID.
	 *
	 * @return int Comment ID.
	 */
	public function get_id() {
		return $this->comment->comment_ID;
	}

	/**
	 * Get the post the reaction belongs to.
	 *
	 * @return WP_Post Post object.
	 */
	public function get_post() {
		return get_post( $this->get_post_id() );
	}

	/**
	 * Get the post ID the reaction belongs to.
	 *
	 * @return int Post ID.
	 */
	public function get_post_id() {
		return $this->comment->comment_post_ID;
	}

	/**
	 * Get the type (emoji) of the reaction.
	 *
	 * @return string Emoji character.
	 */
	public function get_type() {
		return $this->comment->comment_content;
	}

	public function get_type_name() {
		$map = Emoji\get_data_map();
		$matching = wp_list_filter( $map, ['char' => $this->get_type() ] );
		if ( empty( $matching ) ) {
			return $this->get_type();
		}

		$matching = reset( $matching );
		return $matching->name;
	}

	/**
	 * Get the author.
	 *
	 * @return WP_User User object.
	 */
	public function get_user() {
		return get_user( $this->get_user_id() );
	}

	/**
	 * Get the ID of the author.
	 *
	 * @return int User ID.
	 */
	public function get_user_id() {
		return (int) $this->comment->user_id;
	}

	/**
	 * Delete the reaction.
	 *
	 * @return bool|WP_Error True on success, error otherwise.
	 */
	public function delete() {
		$did_delete = wp_delete_comment( $this->comment->comment_ID, true );
		if ( ! $did_delete ) {
			return new WP_Error(
				'h2.reactions.delete.internal_error',
				__( 'Could not delete the reaction', 'h2' ),
				[
					'status' => 500,
				]
			);
		}
		$this->comment = null;

		return true;
	}

	/**
	 * Convert data to a Reaction instance.
	 *
	 * Allows use as a callback, such as in `array_map`
	 *
	 * @param stdClass $comment Comment data
	 * @return Reaction
	 */
	protected static function to_instance( $comment ) {
		return new static( $comment );
	}

	/**
	 * Convert list of data to Reaction instances
	 *
	 * @param stdClass[] $data Raw comment objects
	 * @return Reaction[]
	 */
	protected static function to_instances( $data ) {
		return array_map( [ get_called_class(), 'to_instance' ], $data );
	}

	/**
	 * Get a single reaction by ID.
	 *
	 * @param int $id Reaction ID.
	 * @return Reaction|WP_Error Reaction on success, error on invalid ID.
	 */
	public static function get( $id ) {
		$comment = get_comment( $id );
		if ( empty( $comment ) || $comment->comment_type !== static::TYPE ) {
			return new WP_Error(
				'h2.reactions.get.not_found',
				__( 'Reaction not found', 'h2' ),
				[
					'id'     => $id,
					'status' => 404,
				]
			);
		}

		return static::to_instance( $comment );
	}

	/**
	 * Get all reactions for a post.
	 *
	 * @param WP_Post|int|null $post Post to add to. Post object, post ID (int), or null for current post.
	 * @return Reaction[]|WP_Error Reactions on success (may be empty), error on invalid arguments.
	 */
	public static function get_for_post( $post ) {
		$post = get_post( $post );
		if ( empty( $post ) ) {
			return new WP_Error(
				'h2.reactions.get_for_post.invalid_post',
				__( 'Invalid post to fetch reactions for', 'h2' ),
				// Set the data to the value of $post from the arguments
				func_get_arg( 0 )
			);
		}

		$args = [
			'type'    => static::TYPE,
			'post_id' => $post->ID,
		];
		$comments = get_comments( $args );
		$reactions = static::to_instances( $comments );

		/**
		 * Filter the reactions on a post.
		 *
		 * @param Reaction[] $reactions Reactions on the post.
		 * @param WP_Post $post Post the reactions belong to.
		 */
		return apply_filters( 'h2.reactions.get_for_post', $reactions, $post );
	}

	/**
	 * Create a new reaction to a post.
	 *
	 * @param WP_Post|int|null $post Post to add to. Post object, post ID (int), or null for current post.
	 * @param string $type Emoji character to add as a reaction.
	 * @param WP_User|null User to create reaction as. Null for current user.
	 * @param int $comment Parent comment, if reacting to a comment.
	 * @return Reaction|WP_Error Reaction object on success, error on failure.
	 */
	public static function create( $post, $type, $user = null, $comment = null ) {
		$post = get_post( $post );
		if ( empty( $post ) ) {
			return new WP_Error(
				'h2.reactions.create.invalid_post',
				__( 'Invalid post to create reaction for', 'h2' ),
				// Set the data to the value of $post from the arguments
				func_get_arg( 0 )
			);
		}

		if ( empty( $user ) ) {
			$user = wp_get_current_user();
		}

		// Check if we're at the limit
		$can_create = static::can_create_reaction( $post, $type, $user );
		if ( is_wp_error( $can_create ) ) {
			return $can_create;
		}

		// Let's make this thing.
		$data = [
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => $user->user_url,
			'user_id'              => $user->ID,
			'comment_content'      => $type,
			'comment_post_ID'      => $post->ID,
			'comment_type'         => static::TYPE,
		];

		if ( $comment ) {
			$data['comment_parent'] = $comment;
		}

		$comment_id = wp_insert_comment( wp_slash( $data ) );
		if ( ! $comment_id ) {
			return new WP_Error(
				'h2.reactions.create.db_error',
				__( 'Could not create reaction in database', 'h2' ),
				$data
			);
		}
		$comment = get_comment( $comment_id );
		return static::to_instance( $comment );
	}

	/**
	 * Can we create the reaction?
	 *
	 * @param WP_Post $post Post to create reaction for.
	 * @param string $type Emoji character to add as a reaction.
	 * @return boolean|WP_Error True if we can create the reaction, error describing why not otherwise.
	 */
	protected static function can_create_reaction( WP_Post $post, $type, WP_User $user ) {
		// Is this a valid user?
		if ( ! $user->exists() ) {
			return new WP_Error(
				'h2.reactions.create.invalid_user',
				__( 'Invalid user to create reaction as', 'h2' ),
				[
					'status' => 400,
				]
			);
		}

		// Check if we're at the limit
		$existing = Reaction::get_for_post( $post );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$grouped = group_by_type( $existing );
		if ( is_wp_error( $grouped ) ) {
			return $grouped;
		}
		$limit = get_limit();
		if ( $limit && count( $grouped ) === $limit && ! isset( $grouped[ $type ] ) ) {
			return new WP_Error(
				'h2.reactions.create.max_reactions',
				__( 'Post already has the maximum number of reactions', 'h2' ),
				[
					'post'   => $post->ID,
					'status' => 403,
				]
			);
		}

		// Is this a valid emoji?
		if ( ! is_valid_type( $type ) ) {
			return new WP_Error(
				'h2.reactions.create.invalid_type',
				__( 'Invalid reaction type', 'h2' ),
				[
					'type'   => $type,
					'status' => 400,
				]
			);
		}

		// Has the user already reacted this?
		if ( isset( $grouped[ $type ] ) ) {
			$has_reacted = array_reduce( $grouped[ $type ], function ( $carry, $reaction ) use ( $user ) {
				return $carry || $reaction->get_user_id() === $user->ID;
			}, false );
		} else {
			$has_reacted = false;
		}

		if ( $has_reacted ) {
			return new WP_Error(
				'h2.reactions.create.already_reacted',
				__( 'You have already reacted with this emoji', 'h2' ),
				[
					'status' => 403,
					'type'   => $type,
					'user'   => $user->ID,
					'post'   => $post->ID,
				]
			);
		}

		return true;
	}
}
