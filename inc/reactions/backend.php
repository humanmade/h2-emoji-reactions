<?php

namespace H2\Reactions\Backend;

use H2\Reactions;
use H2\Reactions\API_Endpoint;
use WP_REST_Response;

function get_classmap( $classmap = array() ) {
	$classmap[ 'H2\\Reactions\\API_Endpoint' ] = __DIR__ . '/inc/class-api-endpoint.php';
	$classmap[ 'H2\\Reactions\\Reaction' ] = __DIR__ . '/inc/class-reaction.php';

	return $classmap;
}

function register_endpoints() {
	$endpoint = new API_Endpoint();
	$endpoint->register_routes();
}

function add_extra_post_data( WP_REST_Response $response, $post ) {
	$data = $response->get_data();

	$data['is']['reactable'] = comments_open( $post );
	$data['can']['react'] = is_user_logged_in(); // TODO: fix this :)

	$response->set_data( $data );
	return $response;
}

function register_link_filters() {
	foreach ( array( 'post' ) as $type ) {
		add_filter( 'rest_prepare_' . $type, __NAMESPACE__ . '\\add_reaction_link_to_response' );
	}
}

function add_reaction_link_to_response( WP_REST_Response $response ) {
	$data = $response->get_data();
	$id = $data['id'];
	$url = get_rest_url( null, 'h2/v1/reactions' );
	$url = add_query_arg( 'post', $id, $url );
	$response->add_link( 'h2:reactions', $url, array( 'embeddable' => true ) );
	return $response;
}
