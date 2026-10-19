<?php
/**
 * Plugin Name:       Massage List
 * Description:       Dynamiczny blok Gutenberga wyświetlający bezpośrednie elementy podrzędne wybranego menu nawigacyjnego w formie responsywnych kafelków.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Specyfikacja Techniczna
 * Text Domain:       massage-list
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MASSAGE_LIST_VERSION', '1.0.0' );
define( 'MASSAGE_LIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'MASSAGE_LIST_URL', plugin_dir_url( __FILE__ ) );

/**
 * Inicjalizacja wtyczki, rejestracja rozmiaru miniatur oraz bloku.
 */
function massage_list_init() {
	load_plugin_textdomain( 'massage-list', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Dedykowany, zoptymalizowany rozmiar obrazu dla kafelków (proporcje dopasowane do max-height: 300px).
	add_image_size( 'massage-list-tile', 640, 420, true );

	register_block_type( MASSAGE_LIST_PATH );
}
add_action( 'init', 'massage_list_init' );

/**
 * Rejestracja endpointów REST API dla edytora Gutenberga.
 * Pozwala na bezpieczne i szybkie pobranie listy menu oraz elementów wybranego menu.
 */
function massage_list_register_rest_routes() {
	register_rest_route(
		'massage-list/v1',
		'/menus',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'massage_list_rest_get_menus',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_rest_route(
		'massage-list/v1',
		'/menu-items/(?P<menu_id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'massage_list_rest_get_menu_items',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'menu_id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && intval( $param ) > 0;
					},
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'massage_list_register_rest_routes' );

/**
 * Zwraca listę zarejestrowanych menu nawigacyjnych.
 *
 * @return WP_REST_Response
 */
function massage_list_rest_get_menus() {
	$menus  = wp_get_nav_menus();
	$result = array();

	if ( ! empty( $menus ) && ! is_wp_error( $menus ) ) {
		foreach ( $menus as $menu ) {
			$result[] = array(
				'id'   => (int) $menu->term_id,
				'name' => wp_strip_all_tags( $menu->name ),
			);
		}
	}

	return rest_ensure_response( $result );
}

/**
 * Zwraca elementy wybranego menu wraz z informacją o posiadaniu elementów podrzędnych.
 *
 * @param WP_REST_Request $request Obiekt żądania REST.
 * @return WP_REST_Response
 */
function massage_list_rest_get_menu_items( WP_REST_Request $request ) {
	$menu_id = $request->get_param( 'menu_id' );
	$menu    = wp_get_nav_menu_object( $menu_id );

	if ( ! $menu || is_wp_error( $menu ) ) {
		return rest_ensure_response( array() );
	}

	$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
	if ( empty( $items ) || is_wp_error( $items ) ) {
		return rest_ensure_response( array() );
	}

	// Zliczanie bezpośrednich dzieci dla każdego elementu w celu czytelnego oznaczenia w edytorze.
	$children_count = array();
	foreach ( $items as $item ) {
		$parent_id = (int) $item->menu_item_parent;
		if ( $parent_id > 0 ) {
			if ( ! isset( $children_count[ $parent_id ] ) ) {
				$children_count[ $parent_id ] = 0;
			}
			++$children_count[ $parent_id ];
		}
	}

	$result = array();
	foreach ( $items as $item ) {
		$item_id = (int) $item->ID;
		$count   = isset( $children_count[ $item_id ] ) ? $children_count[ $item_id ] : 0;

		$result[] = array(
			'id'            => $item_id,
			'title'         => wp_strip_all_tags( $item->title ),
			'parent'        => (int) $item->menu_item_parent,
			'childrenCount' => $count,
		);
	}

	return rest_ensure_response( $result );
}