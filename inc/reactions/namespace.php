<?php

namespace H2\Reactions;

use H2\Emoji;
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
	$all_emoji = Emoji\get_data_map();
	$emoji = wp_list_pluck( $all_emoji, 'char' );
	return in_array( $type, $emoji );
}
