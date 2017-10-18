<?php
/**
 * Component Name: Reactions
 * Description: Add emoji reactions to your posts.
 * Depends: core, emoji
 */

namespace H2\Reactions;

if ( function_exists( __NAMESPACE__ . '\\Backend\\get_classmap' ) ) {
	$ref = new \ReflectionFunction( __NAMESPACE__ . '\\Backend\\get_classmap' );
	exit;
}

require_once __DIR__ . '/backend.php';
require_once __DIR__ . '/namespace.php';
require_once __DIR__ . '/inc/class-reaction.php';
require_once __DIR__ . '/inc/class-api-endpoint.php';


add_filter( 'h2.core.classmap', __NAMESPACE__ . '\\Backend\\get_classmap' );
add_action( 'rest_api_init', __NAMESPACE__ . '\\Backend\\register_endpoints' );
add_action( 'rest_api_init', __NAMESPACE__ . '\\Backend\\register_link_filters' );
add_filter( 'rest_prepare_post', __NAMESPACE__ . '\\Backend\\add_extra_post_data', 10, 2 );
