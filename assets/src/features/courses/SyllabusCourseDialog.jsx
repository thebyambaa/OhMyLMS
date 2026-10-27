/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';

/**
 * Select syllabus skills and review the independent draft before creation.
 *
 * @param {Object}                   root0            Properties.
 * @param {number}                   root0.syllabusId Source ID.
 * @param {() => void}               root0.onClose    Close dialog.
 * @param {(course: Object) => void} root0.onCreated  Open the resulting course.
 */
export function SyllabusCourseDialog( { syllabusId, onClose, onCreated } ) {
	const [ source, setSource ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ title, setTitle ] = useState( '' );
	const [ review, setReview ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ attempt, setAttempt ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setSource( null );
		setError( '' );
		apiFetch( {
			path: `/ohmylms/v1/courses/from-syllabus/${ syllabusId }`,
		} )
			.then( ( data ) => {
				if ( active ) {
					setSource( data );
					setTitle( data.name );
					setSelected( [
						...new Set(
							data.topics.flatMap( ( topic ) =>
								topic.lessons.flatMap( ( lesson ) =>
									lesson.skills.map(
										( skill ) => skill.term_id
									)
								)
							)
						),
					] );
					setReview( false );
				}
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ syllabusId, attempt ] );
	const topics =
		source?.topics
			.map( ( topic ) => ( {
				...topic,
				lessons: topic.lessons
					.map( ( lesson ) => ( {
						...lesson,
						skills: lesson.skills.filter( ( skill ) =>
							selected.includes( skill.term_id )
						),
					} ) )
					.filter( ( lesson ) => lesson.skills.length ),
			} ) )
			.filter( ( topic ) => topic.lessons.length ) || [];
	const toggle = ( ids, checked ) =>
		setSelected( ( previous ) =>
			checked
				? [ ...new Set( [ ...previous, ...ids ] ) ]
				: previous.filter( ( id ) => ! ids.includes( id ) )
		);
	const lessonCount = topics.reduce(
		( sum, topic ) => sum + topic.lessons.length,
		0
	);
	async function create() {
		setBusy( true );
		setError( '' );
		try {
			const made = await apiFetch( {
				path: '/ohmylms/v1/courses/from-syllabus',
				method: 'POST',
				data: {
					syllabus_id: syllabusId,
					title,
					skill_ids: selected,
					revision: source.revision,
				},
			} );
			onCreated( made );
		} catch ( cause ) {
			setError( cause.message );
			setBusy( false );
		}
	}
	return (
		<Modal
			className="ohmylms-syllabus-course-dialog"
			title={ __( 'Create course from syllabus', 'ohmylms' ) }
			onRequestClose={ busy ? () => {} : onClose }
			size="large"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						disabled={ busy }
						onClick={ () => setAttempt( attempt + 1 ) }
					>
						{ __( 'Reload selection', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! source && ! error && <Spinner /> }
			{ source && (
				<>
					<p>
						{ __(
							'Create an independent draft. Topics become units, skill groups become lessons, and selected skills stay linked. Add activities in the course editor before publishing.',
							'ohmylms'
						) }
					</p>
					<TextControl
						label={ __( 'Course title', 'ohmylms' ) }
						value={ title }
						onChange={ setTitle }
						disabled={ busy || review }
						maxLength={ 200 }
					/>
					{ ! review && (
						<>
							<p>
								{ __(
									'Choose up to 200 skills. Skills used in several lessons remain linked to every lesson. Each skill has one course completion target.',
									'ohmylms'
								) }
							</p>
							<Button onClick={ () => setSelected( [] ) }>
								{ __( 'Clear selection', 'ohmylms' ) }
							</Button>
							{ source.topics.map( ( topic ) => {
								const ids = [
									...new Set(
										topic.lessons.flatMap( ( lesson ) =>
											lesson.skills.map(
												( skill ) => skill.term_id
											)
										)
									),
								];
								return (
									<fieldset key={ topic.id }>
										<legend>{ topic.name }</legend>
										<CheckboxControl
											label={ sprintf(
												/* translators: %s: topic name. */ __(
													'All skills in %s',
													'ohmylms'
												),
												topic.name
											) }
											checked={ ids.every( ( id ) =>
												selected.includes( id )
											) }
											indeterminate={
												ids.some( ( id ) =>
													selected.includes( id )
												) &&
												! ids.every( ( id ) =>
													selected.includes( id )
												)
											}
											onChange={ ( checked ) =>
												toggle( ids, checked )
											}
										/>
										{ topic.lessons.map( ( lesson ) => (
											<div key={ lesson.id }>
												<h3>{ lesson.title }</h3>
												{ lesson.skills.map(
													( skill ) => (
														<CheckboxControl
															key={
																skill.term_id
															}
															label={ [
																skill.code,
																skill.name,
															]
																.filter(
																	Boolean
																)
																.join( ' · ' ) }
															help={
																skill.objective
															}
															checked={ selected.includes(
																skill.term_id
															) }
															onChange={ (
																checked
															) =>
																toggle(
																	[
																		skill.term_id,
																	],
																	checked
																)
															}
														/>
													)
												) }
											</div>
										) ) }
									</fieldset>
								);
							} ) }
						</>
					) }
					<p>
						{ [
							sprintf(
								/* translators: %d: unit count. */ _n(
									'%d unit',
									'%d units',
									topics.length,
									'ohmylms'
								),
								topics.length
							),
							sprintf(
								/* translators: %d: lesson count. */ _n(
									'%d lesson',
									'%d lessons',
									lessonCount,
									'ohmylms'
								),
								lessonCount
							),
							sprintf(
								/* translators: %d: skill count. */ _n(
									'%d skill',
									'%d skills',
									selected.length,
									'ohmylms'
								),
								selected.length
							),
						].join( ' · ' ) }
					</p>
					{ selected.length > 200 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Choose no more than 200 skills.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ review && (
						<ul>
							{ topics.map( ( topic ) => (
								<li key={ topic.id }>
									<strong>{ topic.name }</strong>
									<ul>
										{ topic.lessons.map( ( lesson ) => (
											<li key={ lesson.id }>
												{ lesson.title }
												<ul>
													{ lesson.skills.map(
														( skill ) => (
															<li
																key={
																	skill.term_id
																}
															>
																{ skill.name }
															</li>
														)
													) }
												</ul>
											</li>
										) ) }
									</ul>
								</li>
							) ) }
						</ul>
					) }
					{ review ? (
						<>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => setReview( false ) }
							>
								{ __( 'Back to selection', 'ohmylms' ) }
							</Button>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={ busy }
								onClick={ create }
							>
								{ __( 'Create draft course', 'ohmylms' ) }
							</Button>
						</>
					) : (
						<Button
							variant="primary"
							disabled={
								! title.trim() ||
								! selected.length ||
								selected.length > 200
							}
							onClick={ () => setReview( true ) }
						>
							{ __( 'Review course outline', 'ohmylms' ) }
						</Button>
					) }
				</>
			) }
		</Modal>
	);
}
