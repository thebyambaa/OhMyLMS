/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Order the courses already placed in a subject or its subgroups.
 *
 * @param {Object} root0      Component properties.
 * @param {Object} root0.item Existing subject curriculum item.
 */
export function SubjectCourseOrder( { item } ) {
	const [ view, setView ] = useState( null );
	const [ courses, setCourses ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ attempt, setAttempt ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setView( null );
		setCourses( [] );
		setError( '' );
		apiFetch( { path: `/ohmylms/v1/subjects/${ item.id }/courses` } )
			.then( ( result ) => {
				if ( active ) {
					setView( result );
					setCourses( result.courses );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setError( cause.message );
				}
			} );
		return () => {
			active = false;
		};
	}, [ item.id, item.links?.course, attempt ] );
	const dirty =
		view &&
		courses.some(
			( course, index ) => course.id !== view.courses[ index ]?.id
		);
	function move( index, offset ) {
		const next = [ ...courses ];
		[ next[ index ], next[ index + offset ] ] = [
			next[ index + offset ],
			next[ index ],
		];
		setCourses( next );
		setNotice( '' );
	}
	async function save() {
		setBusy( true );
		setError( '' );
		try {
			const result = await apiFetch( {
				path: `/ohmylms/v1/subjects/${ item.id }/courses`,
				method: 'PUT',
				data: {
					course_ids: courses.map( ( course ) => course.id ),
					revision: view.revision,
				},
			} );
			setView( result );
			setCourses( result.courses );
			setNotice( __( 'Subject course order saved.', 'ohmylms' ) );
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
		}
	}
	return (
		<section
			className="ohmylms-subject-course-order"
			aria-label={ __( 'Subject course order', 'ohmylms' ) }
		>
			<h3>{ __( 'Subject course order', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Use Linked content to assign courses. This list includes courses in subgroups. Only published courses appear to learners; new placements are added at the end.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ ! view && ! error && <Spinner /> }
			<ol>
				{ courses.map( ( course, index ) => (
					<li key={ course.id }>
						<span>
							{ course.title }{ ' ' }
							{ course.status !== 'publish' &&
								`(${ __( 'Not visible to learners', 'ohmylms' ) })` }
						</span>
						<Button
							disabled={ busy || index === 0 }
							onClick={ () => move( index, -1 ) }
							aria-label={ sprintf(
								/* translators: %s: course title. */ __(
									'Move %s up',
									'ohmylms'
								),
								course.title
							) }
						>
							{ __( 'Up', 'ohmylms' ) }
						</Button>
						<Button
							disabled={ busy || index === courses.length - 1 }
							onClick={ () => move( index, 1 ) }
							aria-label={ sprintf(
								/* translators: %s: course title. */ __(
									'Move %s down',
									'ohmylms'
								),
								course.title
							) }
						>
							{ __( 'Down', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ol>
			{ view && courses.length === 0 && (
				<p>
					{ __(
						'Assign a course to this subject to get started.',
						'ohmylms'
					) }
				</p>
			) }
			<Button
				variant="primary"
				disabled={ busy || ! dirty }
				onClick={ save }
				isBusy={ busy }
			>
				{ __( 'Save course order', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ busy }
				onClick={ () => setAttempt( attempt + 1 ) }
			>
				{ __( 'Reload course order', 'ohmylms' ) }
			</Button>
			{ view && (
				<Button
					variant="link"
					href={ view.url }
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'View subject page', 'ohmylms' ) }
				</Button>
			) }
		</section>
	);
}
