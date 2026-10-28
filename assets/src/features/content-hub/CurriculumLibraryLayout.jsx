import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { libraryCurriculum } from './libraryCurriculum.mjs';

/**
 * Shared curriculum category navigation for reusable content libraries.
 * @param {Object}               props          Component properties.
 * @param {number}               props.value    Selected curriculum ID, zero for all content.
 * @param {(id: number) => void} props.onChange Select a category, including its descendants.
 * @param {Element}              props.children Library results and existing controls.
 * @return {Element} Category navigation and results.
 */
export function CurriculumLibraryLayout( { value = 0, onChange, children } ) {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ search, setSearch ] = useState( '' );
	const [ collapsed, setCollapsed ] = useState( () => new Set() );
	useEffect( () => {
		let active = true;
		setError( '' );
		window.wp
			.apiFetch( { path: '/ohmylms/v1/curriculum/outline' } )
			.then( ( response ) => active && setItems( response.items || [] ) )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ attempt ] );
	const renderNodes = ( nodes ) => (
		<ul>
			{ nodes.map( ( node ) => {
				const open = !! search || ! collapsed.has( node.id );
				return (
					<li key={ node.id }>
						<div className="ohmylms-library-category-row">
							{ node.children.length > 0 ? (
								<Button
									aria-label={ sprintf(
										/* translators: %s: category name. */ __(
											'Toggle %s',
											'ohmylms'
										),
										node.name
									) }
									aria-expanded={ open }
									onClick={ () =>
										setCollapsed( ( current ) => {
											const next = new Set( current );
											if ( next.has( node.id ) ) {
												next.delete( node.id );
											} else {
												next.add( node.id );
											}
											return next;
										} )
									}
								>
									{ open ? '▾' : '▸' }
								</Button>
							) : (
								<span className="ohmylms-library-category-spacer" />
							) }
							<Button
								aria-current={
									Number( value ) === node.id
										? 'true'
										: undefined
								}
								onClick={ () => onChange( node.id ) }
							>
								{ node.name }
							</Button>
						</div>
						{ open &&
							node.children.length > 0 &&
							renderNodes( node.children ) }
					</li>
				);
			} ) }
		</ul>
	);
	const nodes = libraryCurriculum( items || [], search );
	return (
		<div className="ohmylms-library-layout">
			<nav
				className="ohmylms-library-categories"
				aria-label={ __( 'Curriculum categories', 'ohmylms' ) }
			>
				<h3>{ __( 'Curriculum', 'ohmylms' ) }</h3>
				<TextControl
					label={ __( 'Find a category', 'ohmylms' ) }
					type="search"
					value={ search }
					onChange={ setSearch }
				/>
				<Button
					aria-current={ ! Number( value ) ? 'true' : undefined }
					onClick={ () => onChange( 0 ) }
				>
					{ __( 'All content', 'ohmylms' ) }
				</Button>
				<p>
					{ __(
						'Choose a category to include everything below it. All content also includes items without a curriculum connection.',
						'ohmylms'
					) }
				</p>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
						<Button onClick={ () => setAttempt( attempt + 1 ) }>
							{ __( 'Retry', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
				{ ! error && ! items && <Spinner /> }
				{ ! error && items && renderNodes( nodes ) }
				{ ! error && items && ! nodes.length && (
					<p>{ __( 'No matching categories.', 'ohmylms' ) }</p>
				) }
			</nav>
			<div className="ohmylms-library-results">
				{ Number( value ) > 0 && (
					<p className="ohmylms-library-current-category">
						{
							items?.find(
								( row ) => Number( row.id ) === Number( value )
							)?.name
						}
					</p>
				) }
				{ children }
			</div>
		</div>
	);
}
