<?php
/**
 * Component Name: Emoji
 * Description: Basic emoji features, including autocompletion.
 */

namespace H2\Emoji;

/**
 * Get emoji data map.
 *
 * @return array Map of emoji name => emoji data.
 */
function get_data_map() {
	static $data;
	if ( empty( $data ) ) {
		$file = file_get_contents( __DIR__ . '/data/emoji.json' );
		$data = (array) json_decode( $file );
	}

	return $data;
}
