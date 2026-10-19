import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
	const { menuId, parentId, columnsDesktop, columnsTablet, columnsMobile } = attributes;

	const blockProps = useBlockProps();

	const [ menus, setMenus ] = useState( [] );
	const [ menuItems, setMenuItems ] = useState( [] );
	const [ isLoadingMenus, setIsLoadingMenus ] = useState( true );
	const [ isLoadingItems, setIsLoadingItems ] = useState( false );

	// Fetch menus on mount
	useEffect( () => {
		apiFetch( { path: '/massage-list/v1/menus' } )
			.then( ( data ) => {
				setMenus( data );
				setIsLoadingMenus( false );
			} )
			.catch( ( error ) => {
				console.error( error );
				setIsLoadingMenus( false );
			} );
	}, [] );

	// Fetch menu items when menuId changes
	useEffect( () => {
		if ( ! menuId ) {
			setMenuItems( [] );
			return;
		}

		setIsLoadingItems( true );
		apiFetch( { path: `/massage-list/v1/menus/${ menuId }` } )
			.then( ( data ) => {
				setMenuItems( data );
				setIsLoadingItems( false );
			} )
			.catch( ( error ) => {
				console.error( error );
				setIsLoadingItems( false );
			} );
	}, [ menuId ] );

	const menuOptions = [
		{ label: __( 'Wybierz menu', 'massage-list' ), value: 0 },
		...menus.map( ( menu ) => ( { label: menu.name, value: menu.id } ) )
	];

	// Create options for parent, only allowing top level items or items with children as parent option (simplified to all for now)
	const parentOptions = [
		{ label: __( 'Główny poziom menu (Root)', 'massage-list' ), value: 0 },
		...menuItems.map( ( item ) => ( { label: item.title, value: item.id } ) )
	];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Ustawienia Źródła', 'massage-list' ) }>
					{ isLoadingMenus ? (
						<Spinner />
					) : (
						<SelectControl
							label={ __( 'Wybierz Menu', 'massage-list' ) }
							value={ menuId }
							options={ menuOptions }
							onChange={ ( val ) => setAttributes( { menuId: parseInt( val, 10 ) } ) }
						/>
					) }

					{ menuId > 0 && (
						isLoadingItems ? (
							<Spinner />
						) : (
							<SelectControl
								label={ __( 'Wybierz Rodzica Podstron', 'massage-list' ) }
								value={ parentId }
								options={ parentOptions }
								onChange={ ( val ) => setAttributes( { parentId: parseInt( val, 10 ) } ) }
								help={ __( 'Wyświetlone zostaną tylko elementy będące dziećmi wybranego rodzica.', 'massage-list' ) }
							/>
						)
					) }
				</PanelBody>

				<PanelBody title={ __( 'Ustawienia Siatki (Kolumny)', 'massage-list' ) }>
					<RangeControl
						label={ __( 'Desktop', 'massage-list' ) }
						value={ columnsDesktop }
						onChange={ ( val ) => setAttributes( { columnsDesktop: val } ) }
						min={ 3 }
						max={ 6 }
					/>
					<RangeControl
						label={ __( 'Tablet', 'massage-list' ) }
						value={ columnsTablet }
						onChange={ ( val ) => setAttributes( { columnsTablet: val } ) }
						min={ 2 }
						max={ 4 }
					/>
					<RangeControl
						label={ __( 'Mobile', 'massage-list' ) }
						value={ columnsMobile }
						onChange={ ( val ) => setAttributes( { columnsMobile: val } ) }
						min={ 1 }
						max={ 2 }
					/>
				</PanelBody>
			</InspectorControls>

			<div className="massage-list-editor-preview">
				{ menuId === 0 ? (
					<p>{ __( 'Wybierz menu w opcjach bloku (panel po prawej stronie).', 'massage-list' ) }</p>
				) : (
					<p>{ __( 'Podgląd w edytorze: Elementy z wybranego menu wyrenderują się poprawnie na front-endzie. Aby zobaczyć układ wybranego menu, zapisz i sprawdź podgląd strony.', 'massage-list' ) }</p>
				) }
			</div>
		</div>
	);
}
