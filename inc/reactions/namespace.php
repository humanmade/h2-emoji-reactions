<?php

namespace H2\Reactions;

use H2\Emoji;
use WP_Comment_Query;
use WP_Error;

/**
 * Get reaction limit for posts.
 *
 * To avoid emojispam, you might want to limit the number of reactions a post
 * can have. This limits the number of different types, but allows as many of
 * each type as you have users.
 *
 * @return int|boolean Limit number if set, or false for no limit.
 */
function get_limit() {
	$limit = get_option( 'h2.reactions.limit', false );
	if ( $limit === false ) {
		return $limit;
	}

	return (int) $limit;
}

/**
 * Group reactions by type (emoji).
 *
 * @param Reaction[] $reactions Reactions to group.
 * @return array|WP_Error Map of type (emoji) => list of Reaction objects. Error on invalid parameter.
 */
function group_by_type( $reactions ) {
	$grouped = array();
	foreach ( $reactions as $reaction ) {
		if ( ! ( $reaction instanceof Reaction ) ) {
			return new WP_Error(
				'h2.reactions.group_by_type.invalid_reaction',
				__( 'Object is not a reaction', 'h2' ),
				$reaction
			);
		}
		$type = $reaction->get_type();
		if ( ! isset( $grouped[ $type ] ) ) {
			$grouped[ $type ] = array();
		}
		$grouped[ $type ][] = $reaction;
	}

	return $grouped;
}

/**
 * Is a type valid?
 *
 * The reaction type is a single emoji (potentially multiple UTF-8 bytes). We
 * check this by comparing it to the list of emoji we know about.
 *
 * @param string $type Reaction type (emoji character(s)).
 * @return boolean True if valid emoji, false otherwise.
 */
function is_valid_type( $type ) {
	return true;
}

/**
 * Hide reactions from the Dashboard "Recent Comments" widget
 *
 * @param string $screen_id Screen being rendered.
 * @param string $context Metabox content.
 */
function hide_from_dashboard_widget( string $screen_id, string $context ) {
	if ( $screen_id !== 'dashboard' || $context !== 'normal' ) {
		return;
	}

	add_action( 'parse_comment_query', __NAMESPACE__ . '\\exclude_from_comment_query' );
}

/**
 * Exclude reactions from a comment query.
 *
 * This is hooked into `parse_comment_query` where needed, and hides reactions
 * from the relevant query.
 *
 * @param WP_Comment_Query $query Query to be performed.
 */
function exclude_from_comment_query( WP_Comment_Query $query ) {
	if ( empty( $query->query_vars['type__not_in'] ) ) {
		$query->query_vars['type__not_in'] = [];
	}
	$query->query_vars['type__not_in'][] = Reaction::TYPE;
}
