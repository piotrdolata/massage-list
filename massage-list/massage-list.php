<?php
/**
 * Plugin Name:       Massage List
 * Description:       Wyświetla zawartość submenu wybranego menu jako kafelki.
 * Version:           1.0.2
 * Author:            Jules
 * Text Domain:       massage-list
 *
 * @package           massage-list
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; // Exit if accessed directly.
}

/**
 * Zarejestruj blok i jego skrypty.
 */
function massage_list_submenu_tiles_block_init() {
	register_block_type_from_metadata( __DIR__ . '/build' );
}
add_action( 'init', 'massage_list_submenu_tiles_block_init' );

/**
 * Dodaj REST API endpointy do pobierania list menu i elementów menu.
 */
function massage_list_register_rest_routes() {
    register_rest_route( 'massage-list/v1', '/menus', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'massage_list_get_menus',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        }
    ) );

    register_rest_route( 'massage-list/v1', '/menus/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'massage_list_get_menu_items',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            'id' => array(
                'validate_callback' => function( $param, $request, $key ) {
                    return is_numeric( $param );
                }
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'massage_list_register_rest_routes' );

/**
 * Zwraca listę wszystkich zapisanych menu.
 */
function massage_list_get_menus( $request ) {
    $menus = wp_get_nav_menus();
    $result = array();

    if ( $menus ) {
        foreach ( $menus as $menu ) {
            $result[] = array(
                'id'   => $menu->term_id,
                'name' => $menu->name,
            );
        }
    }

    return rest_ensure_response( $result );
}

/**
 * Zwraca listę elementów dla określonego menu.
 */
function massage_list_get_menu_items( $request ) {
    $menu_id = (int) $request->get_param( 'id' );
    $menu_items = wp_get_nav_menu_items( $menu_id );
    $result = array();

    if ( $menu_items ) {
        foreach ( $menu_items as $item ) {
            // Uwzględnij również ID elementu w relacji bazy danych
            $result[] = array(
                'id'       => $item->ID, // To jest post_id w tabeli wp_posts
                'db_id'    => $item->db_id, // Często w WP menu_item_parent używa tego, a nie ID
                'title'    => $item->title,
                'parent'   => (int) $item->menu_item_parent,
                'object'   => $item->object,
                'objectId' => $item->object_id,
            );
        }
    }

    return rest_ensure_response( $result );
}
