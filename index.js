( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState, useEffect } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, RangeControl, Spinner, Notice } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'massage-list/submenu-tiles', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { menuId, parentId, columnsDesktop, columnsTablet, columnsMobile } = attributes;

			const [ menus, setMenus ] = useState( [] );
			const [ isLoadingMenus, setIsLoadingMenus ] = useState( true );
			const [ menuItems, setMenuItems ] = useState( [] );
			const [ isLoadingItems, setIsLoadingItems ] = useState( false );

			const blockProps = useBlockProps();

			// Pobranie listy dostępnych menu nawigacyjnych przy inicjalizacji bloku.
			useEffect( function () {
				setIsLoadingMenus( true );
				apiFetch( { path: '/massage-list/v1/menus' } )
					.then( function ( data ) {
						setMenus( Array.isArray( data ) ? data : [] );
						setIsLoadingMenus( false );
					} )
					.catch( function () {
						setMenus( [] );
						setIsLoadingMenus( false );
					} );
			}, [] );

			// Pobranie elementów menu po zmianie wybranego menuId.
			useEffect(
				function () {
					if ( ! menuId || menuId <= 0 ) {
						setMenuItems( [] );
						return;
					}

					setIsLoadingItems( true );
					apiFetch( { path: '/massage-list/v1/menu-items/' + menuId } )
						.then( function ( data ) {
							setMenuItems( Array.isArray( data ) ? data : [] );
							setIsLoadingItems( false );
						} )
						.catch( function () {
							setMenuItems( [] );
							setIsLoadingItems( false );
						} );
				},
				[ menuId ]
			);

			// Budowanie opcji dla pierwszej listy rozwijanej (Wybór menu).
			const menuOptions = [
				{ label: __( '— Wybierz menu —', 'massage-list' ), value: 0 },
			];
			menus.forEach( function ( menu ) {
				menuOptions.push( {
					label: menu.name,
					value: menu.id,
				} );
			} );

			// Budowanie opcji dla drugiej listy rozwijanej (Wybór elementu nadrzędnego).
			const parentOptions = [
				{ label: __( '— Wybierz element nadrzędny —', 'massage-list' ), value: 0 },
			];
			menuItems.forEach( function ( item ) {
				const prefix = item.parent > 0 ? '— ' : '';
				const suffix =
					item.childrenCount > 0
						? ' (' + item.childrenCount + ')'
						: ' (' + __( 'brak podstron', 'massage-list' ) + ')';

				parentOptions.push( {
					label: prefix + item.title + suffix,
					value: item.id,
				} );
			} );

			// Walidacja zakresów kolumn.
			const clamp = function ( val, min, max, fallback ) {
				const parsed = parseInt( val, 10 );
				if ( isNaN( parsed ) ) {
					return fallback;
				}
				return Math.max( min, Math.min( max, parsed ) );
			};

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Źródło danych menu', 'massage-list' ),
							initialOpen: true,
						},
						isLoadingMenus
							? el( Spinner, null )
							: menus.length === 0
							? el(
									Notice,
									{ status: 'warning', isDismissible: false },
									__( 'Brak zdefiniowanych menu w WordPressie. Utwórz menu w sekcji Wygląd -> Menu.', 'massage-list' )
							  )
							: el( SelectControl, {
									label: __( 'Wybór menu', 'massage-list' ),
									value: menuId,
									options: menuOptions,
									onChange: function ( newMenuId ) {
										setAttributes( {
											menuId: parseInt( newMenuId, 10 ) || 0,
											parentId: 0, // Reset rodzica po zmianie menu
										} );
									},
							  } ),
						menuId > 0 &&
							( isLoadingItems
								? el( Spinner, null )
								: el( SelectControl, {
										label: __( 'Wybór elementu nadrzędnego', 'massage-list' ),
										help: __(
											'Wyświetlone zostaną bezpośrednie elementy podrzędne wybranego rodzica.',
											'massage-list'
										),
										value: parentId,
										options: parentOptions,
										onChange: function ( newParentId ) {
											setAttributes( {
												parentId: parseInt( newParentId, 10 ) || 0,
											} );
										},
								  } ) )
					),
					el(
						PanelBody,
						{
							title: __( 'Układ kolumn (Responsywność)', 'massage-list' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Liczba kolumn – Desktop', 'massage-list' ),
							help: __( 'Zakres od 3 do 6 kolumn (domyślnie: 3).', 'massage-list' ),
							value: columnsDesktop,
							min: 3,
							max: 6,
							step: 1,
							onChange: function ( val ) {
								setAttributes( { columnsDesktop: clamp( val, 3, 6, 3 ) } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Liczba kolumn – Tablet', 'massage-list' ),
							help: __( 'Zakres od 2 do 4 kolumn (domyślnie: 3).', 'massage-list' ),
							value: columnsTablet,
							min: 2,
							max: 4,
							step: 1,
							onChange: function ( val ) {
								setAttributes( { columnsTablet: clamp( val, 2, 4, 3 ) } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Liczba kolumn – Mobile', 'massage-list' ),
							help: __( 'Zakres od 1 do 2 kolumn (domyślnie: 2).', 'massage-list' ),
							value: columnsMobile,
							min: 1,
							max: 2,
							step: 1,
							onChange: function ( val ) {
								setAttributes( { columnsMobile: clamp( val, 1, 2, 2 ) } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'massage-list/submenu-tiles',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null; // Blok dynamiczny renderowany przez render.php
		},
	} );
} )( window.wp );