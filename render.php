<?php
/**
 * Szablon renderowania po stronie serwera dla bloku massage-list/submenu-tiles.
 *
 * @var array    $attributes Atrybuty bloku.
 * @var string   $content    Zawartość wewnętrzna bloku.
 * @var WP_Block $block      Instancja obiektu bloku.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

/**
 * Funkcja pomocnicza wyświetlająca komunikaty wyłącznie w edytorze Gutenberga.
 * Na stronie publicznej zwraca pusty ciąg, nie generując zbędnego kodu HTML.
 */
if ( ! function_exists( 'massage_list_render_editor_notice' ) ) {
	function massage_list_render_editor_notice( $message, $is_editor_context ) {
		if ( ! $is_editor_context ) {
			return '';
		}
		return sprintf(
			'<div class="massage-list-editor-notice">%s</div>',
			esc_html( $message )
		);
	}
}

// 1. Sprawdzenie czy w systemie istnieją jakiekolwiek menu.
$all_menus = wp_get_nav_menus();
if ( empty( $all_menus ) || is_wp_error( $all_menus ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Brak dostępnych menu w WordPressie. Utwórz menu nawigacyjne w panelu administracyjnym.', 'massage-list' ),
		$is_editor
	);
	return;
}

// 2. Pobranie i walidacja atrybutów.
$menu_id   = isset( $attributes['menuId'] ) ? absint( $attributes['menuId'] ) : 0;
$parent_id = isset( $attributes['parentId'] ) ? absint( $attributes['parentId'] ) : 0;

$cols_desktop = isset( $attributes['columnsDesktop'] ) ? max( 3, min( 6, absint( $attributes['columnsDesktop'] ) ) ) : 3;
$cols_tablet  = isset( $attributes['columnsTablet'] ) ? max( 2, min( 4, absint( $attributes['columnsTablet'] ) ) ) : 3;
$cols_mobile  = isset( $attributes['columnsMobile'] ) ? max( 1, min( 2, absint( $attributes['columnsMobile'] ) ) ) : 2;

if ( 0 === $menu_id || 0 === $parent_id ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Wybierz menu oraz element nadrzędny w panelu ustawień bloku po prawej stronie.', 'massage-list' ),
		$is_editor
	);
	return;
}

// 3. Weryfikacja istnienia wybranego menu.
$menu_obj = wp_get_nav_menu_object( $menu_id );
if ( ! $menu_obj || is_wp_error( $menu_obj ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Wybrane menu zostało usunięte lub nie jest już dostępne. Wybierz inne menu w ustawieniach bloku.', 'massage-list' ),
		$is_editor
	);
	return;
}

$menu_items = wp_get_nav_menu_items( $menu_obj->term_id );
if ( empty( $menu_items ) || is_wp_error( $menu_items ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Wybrane menu nie zawiera żadnych elementów.', 'massage-list' ),
		$is_editor
	);
	return;
}

// 4. Weryfikacja istnienia elementu nadrzędnego oraz filtracja bezpośrednich dzieci.
$parent_exists   = false;
$direct_children = array();
$post_ids_to_prime = array();

foreach ( $menu_items as $item ) {
	if ( (int) $item->ID === $parent_id ) {
		$parent_exists = true;
	}
	if ( (int) $item->menu_item_parent === $parent_id ) {
		$direct_children[] = $item;
		if ( 'post_type' === $item->type && ! empty( $item->object_id ) ) {
			$post_ids_to_prime[] = (int) $item->object_id;
		}
	}
}

if ( ! $parent_exists ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Wybrany element nadrzędny został usunięty z menu. Wybierz prawidłowy element nadrzędny.', 'massage-list' ),
		$is_editor
	);
	return;
}

if ( empty( $direct_children ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Wybrany element nadrzędny nie posiada bezpośrednich elementów podrzędnych.', 'massage-list' ),
		$is_editor
	);
	return;
}

// 5. Optymalizacja zapytań bazodanowych: jednoczesne załadowanie postów i ich metadanych (w tym miniatur).
if ( ! empty( $post_ids_to_prime ) && function_exists( '_prime_post_caches' ) ) {
	_prime_post_caches( array_unique( $post_ids_to_prime ), false, true );
}

// Przygotowanie ścieżki i adresu obrazu zastępczego.
$fallback_rel_path = '/wp-content/uploads/2026/10/slowflowm.webp';
$fallback_url      = home_url( $fallback_rel_path );

// 6. Walidacja każdego elementu podrzędnego i budowa danych kafelków.
$valid_tiles = array();

foreach ( $direct_children as $child ) {
	$title = isset( $child->title ) ? trim( wp_strip_all_tags( $child->title ) ) : '';
	if ( '' === $title ) {
		continue; // Pomijamy elementy bez tytułu (ochrona przed pustymi nagłówkami <h3>).
	}

	$url          = '';
	$thumbnail_id = 0;

	if ( 'post_type' === $child->type ) {
		$object_id = absint( $child->object_id );
		$post_obj  = get_post( $object_id );

		// Sprawdzenie, czy treść istnieje i jest publicznie opublikowana.
		if ( ! $post_obj || 'publish' !== $post_obj->post_status || post_password_required( $post_obj ) ) {
			continue;
		}

		// Użycie kanonicznego adresu URL docelowej podstrony.
		$permalink = get_permalink( $post_obj );
		$url       = $permalink ? $permalink : $child->url;

		// Pobranie obrazka wyróżniającego tylko dla lokalnych typów treści.
		$thumb_id = get_post_thumbnail_id( $post_obj );
		if ( $thumb_id ) {
			$thumbnail_id = (int) $thumb_id;
		}
	} elseif ( 'taxonomy' === $child->type ) {
		$term_id  = absint( $child->object_id );
		$taxonomy = sanitize_key( $child->object );
		$term_obj = get_term( $term_id, $taxonomy );

		if ( ! $term_obj || is_wp_error( $term_obj ) ) {
			continue;
		}

		$term_link = get_term_link( $term_obj, $taxonomy );
		$url       = ! is_wp_error( $term_link ) ? $term_link : $child->url;
	} else {
		// Zewnętrzne lub niestandardowe adresy URL – brak przypisywania lokalnych obrazków wyróżniających.
		$url = $child->url;
	}

	$url = esc_url_raw( trim( $url ) );
	if ( empty( $url ) || '#' === $url || ! wp_http_validate_url( $url ) ) {
		// Dopuszczamy również poprawne adresy względne rozpoczynające się od '/'.
		if ( 0 === strpos( $url, '/' ) && strlen( $url ) > 1 ) {
			$url = home_url( $url );
		} else {
			continue;
		}
	}

	// Przygotowanie danych obrazu (zoptymalizowana miniatura lub placeholder).
	$img_src    = '';
	$img_srcset = '';
	$img_sizes  = '';
	$img_width  = 640;
	$img_height = 420;
	$img_alt    = '';

	if ( $thumbnail_id > 0 ) {
		$img_data = wp_get_attachment_image_src( $thumbnail_id, 'massage-list-tile' );
		if ( ! $img_data ) {
			$img_data = wp_get_attachment_image_src( $thumbnail_id, 'medium_large' );
		}

		if ( $img_data && ! empty( $img_data[0] ) ) {
			$img_src    = $img_data[0];
			$img_width  = ! empty( $img_data[1] ) ? (int) $img_data[1] : 640;
			$img_height = ! empty( $img_data[2] ) ? (int) $img_data[2] : 420;
			$img_srcset = wp_get_attachment_image_srcset( $thumbnail_id, 'massage-list-tile' );
			$img_sizes  = '(max-width: 767px) 100vw, (max-width: 1023px) 50vw, 33vw';

			// Pobranie tekstu alternatywnego z biblioteki mediów WordPressa.
			$saved_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
			$saved_alt = is_string( $saved_alt ) ? trim( wp_strip_all_tags( $saved_alt ) ) : '';

			// Unikamy duplikowania tekstu tytułu <h3> wewnątrz tego samego linku <a> (zgodność z WCAG i SEO).
			if ( '' !== $saved_alt && strtolower( $saved_alt ) !== strtolower( $title ) ) {
				$img_alt = $saved_alt;
			}
		}
	}

	// Jeśli podstrona nie ma obrazka wyróżniającego lub plik miniatury jest niedostępny, użyj obrazu zastępczego.
	if ( empty( $img_src ) ) {
		$img_src = $fallback_url;
		$img_alt = ''; // Obraz zastępczy wewnątrz nazwanego linku z nagłówkiem <h3> pełni funkcję dekoracyjną.
	}

	$valid_tiles[] = array(
		'title'      => $title,
		'url'        => $url,
		'target'     => ( '_blank' === $child->target ) ? '_blank' : '',
		'img_src'    => $img_src,
		'img_srcset' => $img_srcset ? $img_srcset : '',
		'img_sizes'  => $img_sizes,
		'img_width'  => $img_width,
		'img_height' => $img_height,
		'img_alt'    => $img_alt,
	);
}

if ( empty( $valid_tiles ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo massage_list_render_editor_notice(
		__( 'Brak opublikowanych, publicznie dostępnych podstron dla wybranego elementu nadrzędnego.', 'massage-list' ),
		$is_editor
	);
	return;
}

// 7. Przygotowanie atrybutów kontenera bloku i zmiennych CSS Grid.
$inline_vars = sprintf(
	'--ml-cols-desktop:%d;--ml-cols-tablet:%d;--ml-cols-mobile:%d;',
	$cols_desktop,
	$cols_tablet,
	$cols_mobile
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'massage-list-grid',
		'style' => $inline_vars,
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php foreach ( $valid_tiles as $tile ) : ?>
		<a
			href="<?php echo esc_url( $tile['url'] ); ?>"
			class="massage-list-tile"
			<?php if ( '_blank' === $tile['target'] ) : ?>
				target="_blank" rel="noopener noreferrer"
			<?php endif; ?>
			<?php if ( $is_editor ) : ?>
				onclick="return false;"
			<?php endif; ?>
		>
			<div class="massage-list-tile__media">
				<img
					src="<?php echo esc_url( $tile['img_src'] ); ?>"
					alt="<?php echo esc_attr( $tile['img_alt'] ); ?>"
					width="<?php echo esc_attr( $tile['img_width'] ); ?>"
					height="<?php echo esc_attr( $tile['img_height'] ); ?>"
					<?php if ( ! empty( $tile['img_srcset'] ) ) : ?>
						srcset="<?php echo esc_attr( $tile['img_srcset'] ); ?>"
						sizes="<?php echo esc_attr( $tile['img_sizes'] ); ?>"
					<?php endif; ?>
					loading="lazy"
					decoding="async"
					class="massage-list-tile__image"
				/>
			</div>
			<div class="massage-list-tile__content">
				<h3 class="massage-list-tile__title"><?php echo esc_html( $tile['title'] ); ?></h3>
			</div>
		</a>
	<?php endforeach; ?>
</div>