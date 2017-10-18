<?php

/*
Plugin Name: H2 reactions.
Plugin URI: http://hmn.md
Description: Headless reactions for the H2 app.
Version: 1.0
Author: Ryan McCue and Matthew Haines-Young
*/

add_action( 'plugins_loaded', function() {
	require_once __DIR__ . '/inc/emoji/component.php';
	require_once __DIR__ . '/inc/reactions/component.php';
});
