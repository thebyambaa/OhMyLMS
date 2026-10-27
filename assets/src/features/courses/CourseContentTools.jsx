/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { searchTargets } from '../content-hub/api.mjs';
import { ImportButton } from '../content-hub/ImportButton';
import { loadCourse } from './api.mjs';

const request = ( options ) => window.wp.apiFetch( options );

/**
 * What a unit holds, in the shape the chapter content route stores.
 * @param {Array} contents Chapter contents as the course route returns them.
 */
const asStored = ( contents ) =>
	( contents || [] ).map( ( item, index ) => ( {
		id: Number( item.id ),
		name: item.name,
		type: item.type,
		order_number: Number( item.order_number ) || index + 1,
		...( item.prerequisites ? { prerequisites: item.prerequisites } : {} ),
	} ) );

/**
 * Add lessons, quizzes and assignments that already exist to a unit, or import a quiz straight into it.
 * The unit's own Add Content dialog can only create new content.
 *
 * @param {Object} root0          Component properties.
 * @param {number} root0.courseId The course being edited.
 * @param {Array}  root0.units    The units: `{ id, name }`.
 * @param {Object} root0.actions  Course store actions.
 */
export function CourseContentTools( { courseId, units, actions } ) {
	const [ unitId, setUnitId ] = useState( '' );
	const [ type, setType ] = useState( 'lesson' );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ busy, setBusy ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const latest = useRef( 0 );
	const chosen = unitId || ( units[ 0 ] ? String( units[ 0 ].id ) : '' );

	useEffect( () => {
		const id = ++latest.current;
		setResults( null );
		const timer = setTimeout( () => {
			searchTargets( { type, search, course: courseId } )
				.then( ( data ) => {
					if ( id === latest.current ) {
						setResults( data.items || [] );
					}
				} )
				.catch( ( cause ) => {
					if ( id === latest.current ) {
						setResults( [] );
						setError(
							cause.message ||
								__( 'Could not load content.', 'ohmylms' )
						);
					}
				} );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ type, search, courseId, notice ] );

	async function attach( item, kind ) {
		if ( ! chosen ) {
			setError( __( 'Add a unit first.', 'ohmylms' ) );
			return;
		}
		setBusy( ( count ) => count + 1 );
		setError( '' );
		setNotice( '' );
		try {
			let storedType = kind;
			if ( kind === 'lesson' ) {
				const lesson = await request( {
					path: `/ohmylms/v1/lessons/${ item.id }`,
				} );
				storedType = lesson.type || 'text';
			}
			const course = await loadCourse( courseId );
			const unit = ( course.chapters || [] ).find(
				( row ) => String( row.id ) === String( chosen )
			);
			const existing = asStored( unit?.contents );
			if ( existing.some( ( row ) => row.id === Number( item.id ) ) ) {
				setNotice( __( 'That is already in this unit.', 'ohmylms' ) );
				return;
			}
			const list = [
				...existing,
				{
					id: Number( item.id ),
					name: item.title || item.name || '',
					type: storedType,
					order_number:
						Math.max(
							0,
							...existing.map( ( row ) => row.order_number )
						) + 1,
				},
			];
			await request( {
				path: `/ohmylms/v1/chapters/${ chosen }/content`,
				method: 'POST',
				data: list,
			} );
			const fresh = await loadCourse( courseId );
			const updated = ( fresh.chapters || [] ).find(
				( row ) => String( row.id ) === String( chosen )
			);
			actions.setContentsToChapter(
				String( chosen ),
				updated?.contents || []
			);
			setNotice(
				sprintf(
					/* translators: 1: content title, 2: unit name. */
					__( '“%1$s” was added to %2$s.', 'ohmylms' ),
					item.title || item.name || '',
					units.find(
						( row ) => String( row.id ) === String( chosen )
					)?.name || ''
				)
			);
		} catch ( cause ) {
			setError(
				cause.message ||
					__( 'The content could not be added.', 'ohmylms' )
			);
		} finally {
			setBusy( ( count ) => count - 1 );
		}
	}

	return (
		<details className="ohmylms-course-content-tools">
			<summary>
				{ __( 'Add existing content or import a quiz', 'ohmylms' ) }
			</summary>
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
			<div className="ohmylms-course-content-fields">
				<SelectControl
					label={ __( 'Unit', 'ohmylms' ) }
					value={ chosen }
					options={ units.map( ( unit ) => ( {
						value: String( unit.id ),
						label: unit.name || __( 'Untitled unit', 'ohmylms' ),
					} ) ) }
					onChange={ setUnitId }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Type', 'ohmylms' ) }
					value={ type }
					options={ [
						{ value: 'lesson', label: __( 'Lesson', 'ohmylms' ) },
						{ value: 'quiz', label: __( 'Quiz', 'ohmylms' ) },
						{
							value: 'assignment',
							label: __( 'Assignment', 'ohmylms' ),
						},
					] }
					onChange={ setType }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Search existing content', 'ohmylms' ) }
					type="search"
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
			</div>
			<div
				className="ohmylms-course-content-results"
				role="group"
				aria-label={ __( 'Existing content', 'ohmylms' ) }
			>
				{ results === null && <Spinner /> }
				{ results !== null && ! results.length && (
					<p>
						{ __(
							'Nothing matches. Content already in this course is left out.',
							'ohmylms'
						) }
					</p>
				) }
				{ ( results || [] ).map( ( item ) => (
					<div key={ item.id } className="ohmylms-course-content-row">
						<span>
							{ item.title }
							{ item.status && item.status !== 'publish'
								? ` (${ item.status })`
								: '' }
						</span>
						<Button
							variant="secondary"
							size="small"
							disabled={ busy > 0 || ! chosen }
							onClick={ () => attach( item, type ) }
						>
							{ __( 'Add to unit', 'ohmylms' ) }
						</Button>
					</div>
				) ) }
			</div>
			<p>
				<ImportButton
					target="quiz"
					targets={ [ 'quiz' ] }
					onQuiz={ ( quiz ) =>
						attach( { id: quiz.id, title: quiz.name }, 'quiz' )
					}
				/>{ ' ' }
				<span className="description">
					{ __(
						'A quiz file is added to the unit chosen above. A PDF is reviewed first; add the finished quiz here afterwards.',
						'ohmylms'
					) }
				</span>
			</p>
		</details>
	);
}
