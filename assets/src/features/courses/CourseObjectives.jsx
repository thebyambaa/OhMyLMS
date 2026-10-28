/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { objectiveRequest, syllabusLabel } from './objectives.mjs';

/**
 * Choose the one syllabus that supplies this course's lesson and quiz skills.
 * @param {Object}     props                 Component properties.
 * @param {Element}    props.subjectControl  Subject profile connections.
 * @param {number}     props.courseId        Course identifier.
 * @param {string}     props.outlineRevision Saved outline revision.
 * @param {() => void} props.onSourceChanged Refresh inline skill suggestions.
 * @return {Element} Single syllabus alignment control.
 */
export function CourseObjectives( {
	courseId,
	subjectControl,
	outlineRevision,
	onSourceChanged,
} ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ attempt, setAttempt ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setData( null );
		setError( '' );
		objectiveRequest( courseId )
			.then( ( result ) => active && setData( result ) )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ courseId, attempt, outlineRevision ] );
	async function choose( value ) {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			const result = await objectiveRequest( courseId, {
				method: 'PUT',
				data: { syllabus_id: Number( value ), revision: data.revision },
			} );
			setData( result );
			setNotice(
				__(
					'Syllabus saved. Existing course, lesson and quiz skill connections have been kept.',
					'ohmylms'
				)
			);
			onSourceChanged?.();
		} catch ( cause ) {
			setError(
				cause.message ||
					__( 'Could not save the course syllabus.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	return (
		<section
			className="ohmylms-course-objectives"
			aria-label={ __( 'Syllabus alignment', 'ohmylms' ) }
			aria-busy={ busy }
		>
			{ subjectControl }
			<h2>{ __( 'Course syllabus', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Choose one syllabus for this course. Its skills will be available when connecting lessons and quizzes in the outline.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						onClick={ () => setAttempt( attempt + 1 ) }
						disabled={ busy }
					>
						{ __( 'Reload', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! data && ! error && <Spinner /> }
			{ data && (
				<>
					<SelectControl
						label={ __( 'Syllabus', 'ohmylms' ) }
						value={ String( data.syllabus_id || 0 ) }
						disabled={ busy }
						options={ [
							{
								value: '0',
								label: __( 'Choose a syllabus…', 'ohmylms' ),
							},
							...data.syllabuses
								.filter(
									( row ) => row.item_type !== 'roadmap'
								)
								.map( ( row ) => ( {
									value: String( row.id ),
									label: syllabusLabel( row ),
								} ) ),
						] }
						onChange={ choose }
					/>
					{ !! data.syllabus_id && (
						<Button
							variant="link"
							href={ `#/content-hub/curriculum/syllabus/${ data.syllabus_id }` }
						>
							{ __( 'Open syllabus', 'ohmylms' ) }
						</Button>
					) }
				</>
			) }
			{ notice && <p role="status">{ notice }</p> }
			<p>
				{ __(
					'Roadmaps measure and compare skills across syllabuses. Manage those relationships in Curriculum.',
					'ohmylms'
				) }
			</p>
		</section>
	);
}
