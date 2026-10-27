/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { readComparison, writeComparison } from './comparison.mjs';

/**
 * Connect shared lessons, quizzes and extension content without copying them.
 * @param {Object} props    Component props.
 * @param {number} props.id Shared skill ID.
 */
export function SharedContentEditor( { id } ) {
	const [ kind, setKind ] = useState( 'lesson' );
	const [ search, setSearch ] = useState( '' );
	const [ targets, setTargets ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ revision, setRevision ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		const timer = setTimeout(
			() =>
				readComparison( `shared-skills/${ id }/content`, {
					kind,
					search,
					search_targets: targets ? 1 : 0,
					page,
				} )
					.then( ( response ) => {
						if ( current ) {
							setData( response );
							setError( '' );
						}
					} )
					.catch( ( cause ) => current && setError( cause.message ) ),
			200
		);
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ id, kind, search, targets, page, revision ] );
	async function link( row ) {
		setBusy( true );
		try {
			await writeComparison(
				`shared-skills/${ id }/content`,
				{ content_id: row.id, linked: ! row.linked },
				'PUT'
			);
			setRevision( ( value ) => value + 1 );
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
		}
	}
	return (
		<section>
			<h3>{ __( 'Shared content', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Connected content belongs to this reusable skill. Equivalent syllabus mappings reference it. Content access and quiz question skill assignments keep their normal rules.',
					'ohmylms'
				) }
			</p>
			<SelectControl
				label={ __( 'Content type', 'ohmylms' ) }
				value={ kind }
				onChange={ ( value ) => {
					setKind( value );
					setPage( 1 );
				} }
				options={ (
					data?.types || [ 'lesson', 'quiz', 'assignment', 'course' ]
				).map( ( value ) => ( { value, label: value } ) ) }
			/>
			<Button
				variant="secondary"
				onClick={ () => {
					setTargets( ! targets );
					setPage( 1 );
				} }
			>
				{ targets
					? __( 'Show connected content', 'ohmylms' )
					: __( 'Find content to connect', 'ohmylms' ) }
			</Button>
			{ targets && (
				<TextControl
					label={ __( 'Search content', 'ohmylms' ) }
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						setPage( 1 );
					} }
				/>
			) }
			{ error && <p role="alert">{ error }</p> }
			{ data?.items.length === 0 && (
				<p>{ __( 'No content in this view.', 'ohmylms' ) }</p>
			) }
			<ul>
				{ data?.items.map( ( row ) => (
					<li key={ row.id }>
						<a href={ row.edit_url }>{ row.title }</a> ·{ ' ' }
						{ row.status }{ ' ' }
						<Button
							variant="link"
							disabled={ busy }
							onClick={ () => link( row ) }
						>
							{ row.linked
								? __( 'Disconnect', 'ohmylms' )
								: __( 'Connect', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
			<div className="ohmylms-comparison-pagination">
				<Button
					variant="link"
					disabled={ page <= 1 }
					onClick={ () => setPage( page - 1 ) }
				>
					{ __( 'Previous content', 'ohmylms' ) }
				</Button>
				<Button
					variant="link"
					disabled={ page >= ( data?.pages || 0 ) }
					onClick={ () => setPage( page + 1 ) }
				>
					{ __( 'More content', 'ohmylms' ) }
				</Button>
			</div>
		</section>
	);
}
