<?php
// Compile emoji data list into an autocompleteable list

/**
 * Convert a Unicode codepoint to UTF-8 characters.
 *
 * @internal I am extremely disappointed in the existing Unicode-to-UTF8 code out there, which doesn't handle BMP characters, or simply breaks. I had to write this from scratch instead.
 * @param int|string $value Codepoint, as integer, or as string of hex digits.
 * @return string UTF8 encoded character string. On invalid input, returns the U+FFFD replacement character (diamond with question mark.)
 */
function unicode_hex_to_utf8( $value ) {
	if ( is_string( $value ) ) {
		$value = hexdec( $value );
	}

	$string = '';
	switch ( true ) {
		case $value <= 0x7F:
			// Optimisation for ASCII
			return chr( $value );

		case $value <= 0x07FF:
			$bytes = 2;
			$b1 = 0xC0;
			break;

		case $value <= 0xFFFF:
			$bytes = 3;
			$b1 = 0xE0;
			break;

		case $value <= 0x10FFFF:
			$bytes = 4;
			$b1 = 0xF0;
			break;

		default:
			// Invalid character, return replacement
			return unicode_hex_to_utf8( 'FFFD' );
	}

	// Exclude surrogate pairs
	if ( $value >= 0xD800 && $value <= 0xDFFF ) {
		return unicode_hex_to_utf8( 'FFFD' );
	}

	while ( $bytes > 1 ) {
		$string = chr( $value & 0x3F | 0x80 ) . $string;
		$value = $value >> 6;
		$bytes--;
	}

	// Final byte.
	$string = chr( $value | $b1 ) . $string;
	return $string;
}

$contents = file_get_contents( 'https://raw.githubusercontent.com/iamcal/emoji-data/master/emoji.json' );
file_put_contents( dirname( __DIR__ ) . '/components/emoji/data/emoji-raw.json', $contents );

$data = json_decode( $contents );
$map = array();

foreach ( $data as $emoji ) {
	// Exclude any not supported by Twemoji
	if ( empty( $emoji->has_img_twitter ) ) {
		continue;
	}

	foreach ( $emoji->short_names as $name ) {
		$bytes = explode( '-', $emoji->unified );

		$bytes = array_reduce( $bytes, function ( $string, $byte ) {
			return $string . unicode_hex_to_utf8( $byte );
		}, '' );

		$map[ $name ] = array(
			'char' => $bytes,
			'name' => $name,
		);
	}
}

file_put_contents( dirname( __DIR__ ) . '/components/emoji/data/emoji.json', json_encode( $map ) );
