<?php
/**
 * Renderowanie bloku Massage List.
 *
 * @param array $attributes Atrybuty bloku.
 * @param string $content Wewnętrzna zawartość bloku.
 * @param WP_Block $block Obiekt bloku.
 */

if ( ! isset( $attributes['menuId'] ) || empty( $attributes['menuId'] ) ) {
	return '<!-- massage-list: no menuId. -->';
}

$menu_id         = (int) $attributes['menuId'];
// Uwaga, czasami opcja default ustawia string "0" więc warto to wyciągnąć jawnie
$parent_id       = isset( $attributes['parentId'] ) ? (int) $attributes['parentId'] : 0;
$columns_desktop = isset( $attributes['columnsDesktop'] ) ? (int) $attributes['columnsDesktop'] : 3;
$columns_tablet  = isset( $attributes['columnsTablet'] ) ? (int) $attributes['columnsTablet'] : 3;
$columns_mobile  = isset( $attributes['columnsMobile'] ) ? (int) $attributes['columnsMobile'] : 2;

$menu_items = wp_get_nav_menu_items( $menu_id );

if ( ! $menu_items ) {
	return '<!-- massage-list: brak elementow w menu -->';
}

$children_items = array();

// Bardzo ważne w WordPress: kiedy pobieramy menu z wp_get_nav_menu_items,
// ID wybranego menu_item jest przypisane do $item->ID.
// Relacja parent-child realizowana jest poprzez pole $item->menu_item_parent

foreach ( $menu_items as $item ) {
    // Zarówno $item->menu_item_parent jak i $parent_id mogą zachować się różnie ze względu na rzutowanie
	if ( (int) $item->menu_item_parent === $parent_id ) {
		$children_items[] = $item;
	}
}

if ( empty( $children_items ) ) {
    // Jeżeli admin nie wyberze parentId, albo w jakiś sposób wybierze parentId = 0,
    // to szukamy wszystkich elementów root (menu_item_parent == 0)
    foreach ( $menu_items as $item ) {
        if ( (int) $item->menu_item_parent === 0 ) {
            $children_items[] = $item;
        }
    }
}

if ( empty( $children_items ) ) {
    // Jeżeli cokolwiek w menu jest zepsute i nic nie pasuje do parent_id ani root,
    // wyciągamy po prostu wszystko co jest w tym menu, w celach upewnienia się
    // że w ogóle zawartość się ładuje.
    $children_items = $menu_items;
}

$wrapper_classes = array(
	'wp-block-massage-list-submenu-tiles',
	'massage-list-grid',
	'has-' . $columns_desktop . '-columns-desktop',
	'has-' . $columns_tablet . '-columns-tablet',
	'has-' . $columns_mobile . '-columns-mobile',
);

$block_wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => implode( ' ', $wrapper_classes ),
) );

$default_image_url = '/wp-content/uploads/2026/10/slowflowm.webp';

ob_start();
?>
<div <?php echo $block_wrapper_attributes; ?>>
	<?php foreach ( $children_items as $item ) :

		$item_url   = $item->url;
		$item_title = $item->title;
		$image_url  = $default_image_url;

		if ( 'post_type' === $item->type && ! empty( $item->object_id ) ) {
			$post_thumbnail_id = get_post_thumbnail_id( $item->object_id );
			if ( $post_thumbnail_id ) {
				$img_src = wp_get_attachment_image_src( $post_thumbnail_id, 'large' );
				if ( $img_src ) {
					$image_url = $img_src[0];
				}
			}
		}

		?>
		<a href="<?php echo esc_url( $item_url ); ?>" class="massage-list-tile">
			<div class="massage-list-tile__image-wrapper">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item_title ); ?>" class="massage-list-tile__image" loading="lazy" />
			</div>
			<div class="massage-list-tile__content">
				<h3 class="massage-list-tile__title"><?php echo esc_html( $item_title ); ?></h3>
			</div>
		</a>
	<?php endforeach; ?>
</div>
<?php
return ob_get_clean();
