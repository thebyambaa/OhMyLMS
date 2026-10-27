/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	addLink,
	canMoveLink,
	linkKey,
	loadLinks,
	moveLink,
	removeLink,
	saveLinks,
	searchCourses,
	LINK_TYPES,
} from './courseLinks.mjs';
const typeLabel = ( type ) =>
	( {
		prerequisite: __( 'Prerequisite', 'ohmylms' ),
		subcourse: __( 'Sub-course', 'ohmylms' ),
		get_ready: __( 'Get ready for', 'ohmylms' ),
	} )[ type ] || type;

/**
 * Advisory links to other courses. They never block enrollment; they only describe how courses
 * relate on the course page. Saved separately from the learning program.
 * @param {Object} root0
 * @param {number} root0.courseId Course being edited.
 */
export function CourseLinks( { courseId } ) {
	const [ links, setLinks ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ type, setType ] = useState( 'prerequisite' );
	const [ matches, setMatches ] = useState( null );
	useEffect( () => {
		let live = true;
		setLinks( null );
		loadLinks( courseId )
			.then( ( data ) => live && setLinks( data.links || [] ) )
			.catch(
				( cause ) =>
					live &&
					setError(
						cause.message ||
							__( 'Could not load course links.', 'ohmylms' )
					)
			);
		return () => {
			live = false;
		};
	}, [ courseId ] );
	const edit = ( next ) => {
		setLinks( next );
		setDirty( true );
		setNotice( '' );
	};
	async function run( work, failure ) {
		setBusy( true );
		setError( '' );
		try {
			await work();
		} catch ( cause ) {
			setError( cause.message || failure );
		} finally {
			setBusy( false );
		}
	}
	const find = () =>
		run(
			async () => setMatches( await searchCourses( courseId, search ) ),
			__( 'Could not search courses.', 'ohmylms' )
		);
	const save = () =>
		run(
			async () => {
				const data = await saveLinks( courseId, links );
				setLinks( data.links || [] );
				setDirty( false );
				setNotice( __( 'Course links saved.', 'ohmylms' ) );
			},
			__( 'Could not save course links.', 'ohmylms' )
		);
	return (
		<section
			className="ohmylms-course-links"
			aria-label={ __( 'Related courses', 'ohmylms' ) }
			aria-busy={ busy }
		>
			<div className="ohmylms-learning-editor-toolbar">
				<h3>{ __( 'Related courses', 'ohmylms' ) }</h3>
				<span>{ dirty ? __( 'Unsaved changes', 'ohmylms' ) : '' }</span>
				<Button
					variant="primary"
					disabled={ busy || ! dirty || ! links }
					onClick={ save }
				>
					{ __( 'Save course links', 'ohmylms' ) }
				</Button>
			</div>
			<p className="description">
				{ __(
					'Links are advice only: they appear on the course page and never block enrollment. A sub-course is part of this course, a prerequisite is worth finishing first, and “Get ready for” suggests preparation.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ ! links && ! error && <Spinner /> }
			{ links && ! links.length && (
				<p>{ __( 'No related courses yet.', 'ohmylms' ) }</p>
			) }
			{ links && links.length > 0 && (
				<ul className="ohmylms-course-links-list">
					{ links.map( ( link ) => {
						const key = linkKey( link );
						const name =
							link.title || sprintf( '#%d', link.course_id );
						return (
							<li key={ key }>
								<span>
									<strong>{ name }</strong>
									<small> · { typeLabel( link.type ) }</small>
								</span>
								<Button
									icon="arrow-up-alt2"
									label={ sprintf(
										/* translators: %s: course name. */
										__( 'Move %s up', 'ohmylms' ),
										name
									) }
									disabled={
										busy || ! canMoveLink( links, key, -1 )
									}
									onClick={ () =>
										edit( moveLink( links, key, -1 ) )
									}
								/>
								<Button
									icon="arrow-down-alt2"
									label={ sprintf(
										/* translators: %s: course name. */
										__( 'Move %s down', 'ohmylms' ),
										name
									) }
									disabled={
										busy || ! canMoveLink( links, key, 1 )
									}
									onClick={ () =>
										edit( moveLink( links, key, 1 ) )
									}
								/>
								<Button
									icon="trash"
									isDestructive
									label={ sprintf(
										/* translators: %s: course name. */
										__( 'Remove link to %s', 'ohmylms' ),
										name
									) }
									disabled={ busy }
									onClick={ () =>
										edit( removeLink( links, key ) )
									}
								/>
							</li>
						);
					} ) }
				</ul>
			) }
			<div className="ohmylms-learning-editor-add">
				<SelectControl
					label={ __( 'Link type', 'ohmylms' ) }
					value={ type }
					options={ LINK_TYPES.map( ( value ) => ( {
						label: typeLabel( value ),
						value,
					} ) ) }
					onChange={ setType }
				/>
				<TextControl
					label={ __( 'Search courses', 'ohmylms' ) }
					value={ search }
					onChange={ setSearch }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							find();
						}
					} }
				/>
				<Button variant="secondary" disabled={ busy } onClick={ find }>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</div>
			{ matches && ! matches.length && (
				<p>{ __( 'No matching courses.', 'ohmylms' ) }</p>
			) }
			{ matches &&
				links &&
				matches.map( ( course ) => {
					const candidate = {
						course_id: course.id,
						type,
						title: course.title,
					};
					const linked = links.some(
						( link ) => linkKey( link ) === linkKey( candidate )
					);
					return (
						<p key={ course.id }>
							{ course.title }
							{ course.status
								? ` · ${ course.status }`
								: '' }{ ' ' }
							<Button
								variant="secondary"
								disabled={ busy || linked }
								onClick={ () =>
									edit(
										addLink( links, courseId, candidate )
									)
								}
							>
								{ linked
									? __( 'Already linked', 'ohmylms' )
									: sprintf(
											/* translators: %s: link type. */
											__( 'Add as %s', 'ohmylms' ),
											typeLabel( type ).toLowerCase()
										) }
							</Button>
						</p>
					);
				} ) }
		</section>
	);
}
