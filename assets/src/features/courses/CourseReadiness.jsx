/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { loadCatalog } from '../content-hub/api.mjs';
import { ImportButton } from '../content-hub/ImportButton';
import { LessonReadiness } from './LessonReadiness';
import { missingOutlineSkills } from './outline.mjs';

/**
 * Saved publication checks alongside current local lesson guidance.
 * @param {Object}                                    props                 Component properties.
 * @param {number}                                    props.courseId        Course ID.
 * @param {Object}                                    props.draft           Current outline.
 * @param {boolean}                                   props.dirty           Whether the outline has unsaved edits.
 * @param {(type: string, id: string|number) => void} props.onSelect        Select the repair destination.
 * @param {() => void}                                props.onRemoveMissing Remove skills that no longer exist.
 * @param {(summary: Object|null) => void}            props.onSummary       Report the blocker count to the editor header.
 */
export function CourseReadiness( {
	courseId,
	draft,
	dirty,
	onSelect,
	onRemoveMissing,
	onSummary,
} ) {
	const [ report, setReport ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ attempt, setAttempt ] = useState( 0 );
	const revision = draft.revision;
	useEffect( () => {
		let active = true;
		setReport( null );
		setError( '' );
		loadCatalog( courseId )
			.then( ( catalog ) => {
				if ( active ) {
					setReport( {
						...catalog.readiness,
						attachments: catalog.attachments,
					} );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setError(
						cause.message ||
							__( 'Could not check readiness.', 'ohmylms' )
					);
				}
			} );
		return () => {
			active = false;
		};
	}, [ courseId, revision, attempt ] );
	const missing = missingOutlineSkills( draft.program, draft.skill_names );
	const skills = ( report?.practice_skills || report?.skills || [] ).filter(
		( skill ) => ! skill.ready && ! missing.includes( skill.term_id )
	);
	const blocked =
		( report?.completion.length || 0 ) +
		( report?.resources.length || 0 ) +
		( report?.skills.filter( ( skill ) => ! skill.ready ).length || 0 );
	const ready = !! report?.ready;
	useEffect( () => {
		onSummary( dirty || ! report ? null : { ready, blocked } );
	}, [ dirty, report, ready, blocked, onSummary ] );
	function selectSkill( skill ) {
		const outcome = draft.program.outcomes.find(
			( row ) => row.term_id === skill.term_id
		);
		onSelect(
			outcome?.group_id ? 'lesson' : 'course',
			outcome?.group_id || 0
		);
	}
	return (
		<section
			className="ohmylms-outline-readiness"
			aria-label={ __( 'Course readiness report', 'ohmylms' ) }
		>
			<h2>{ __( 'Course readiness', 'ohmylms' ) }</h2>
			{ dirty ? (
				<p role="status">
					{ __(
						'Save outline draft to check publishing requirements for your latest changes. Lesson guidance below reflects your current edits.',
						'ohmylms'
					) }
				</p>
			) : (
				<>
					{ error && <p role="alert">{ error }</p> }
					{ ! report && ! error && <Spinner /> }
					{ report && (
						<>
							<h3>
								{ report.ready
									? __(
											'Saved draft passes publishing checks',
											'ohmylms'
										)
									: sprintf(
											/* translators: %d: number of publishing blockers. */ __(
												'Publishing blockers: %d',
												'ohmylms'
											),
											blocked
										) }
							</h3>
							{ missing.length > 0 && (
								<div className="ohmylms-outline-missing">
									<p>
										{ sprintf(
											/* translators: %d: number of missing skills. */ __(
												'%d linked skills no longer exist in the skill library. Remove them before saving the outline.',
												'ohmylms'
											),
											missing.length
										) }
									</p>
									<Button
										variant="secondary"
										onClick={ onRemoveMissing }
									>
										{ __(
											'Remove missing skills',
											'ohmylms'
										) }
									</Button>
								</div>
							) }
							{ report.completion.length > 0 && (
								<div>
									<ul>
										{ report.completion.map( ( line ) => (
											<li key={ line }>{ line }</li>
										) ) }
									</ul>
									<Button
										variant="link"
										onClick={ () =>
											onSelect( 'course', 0 )
										}
									>
										{ __(
											'Edit completion requirements',
											'ohmylms'
										) }
									</Button>
								</div>
							) }
							{ report.resources.length > 0 && (
								<>
									<h4>
										{ __(
											'Unpublished resources — publishing blockers',
											'ohmylms'
										) }
									</h4>
									<ul>
										{ report.resources.map(
											( resource ) => {
												const item =
													draft.program.items.find(
														( row ) =>
															row.type ===
																resource.type &&
															row.content_id ===
																resource.content_id
													);
												return (
													<li
														key={
															resource.type +
															':' +
															resource.content_id
														}
													>
														<Button
															variant="link"
															onClick={ () =>
																onSelect(
																	'activity',
																	item?.id
																)
															}
														>
															{ item?.title ||
																resource.title ||
																__(
																	'Unavailable resource',
																	'ohmylms'
																) }
														</Button>
														{ ' (' +
															( resource.status ||
																__(
																	'missing',
																	'ohmylms'
																) ) +
															') ' }
														{ item?.edit_url && (
															<Button
																variant="link"
																href={
																	item.edit_url
																}
																target="_blank"
																rel="noreferrer"
															>
																{ __(
																	'Edit and publish resource',
																	'ohmylms'
																) }
															</Button>
														) }
													</li>
												);
											}
										) }
									</ul>
								</>
							) }
							{ skills.length > 0 && (
								<>
									<h4>
										{ __(
											'Skills needing approved questions',
											'ohmylms'
										) }
									</h4>
									<p>
										{ __(
											'Required skill gaps block publishing. Optional skill gaps are guidance. Counts use this course’s allowed question pool; importing questions alone does not approve them or add them to a restricted pool.',
											'ohmylms'
										) }
									</p>
									<ul>
										{ skills.map( ( skill ) => (
											<li key={ skill.term_id }>
												<Button
													variant="link"
													onClick={ () =>
														selectSkill( skill )
													}
												>
													{ skill.name }
												</Button>
												<p>
													{ skill.required === false
														? __(
																'Optional skill — guidance',
																'ohmylms'
															)
														: __(
																'Required skill — publishing blocker',
																'ohmylms'
															) }
													{ ' · ' }
													{ sprintf(
														/* translators: 1: available questions, 2: needed questions, 3: available families, 4: needed families. */ __(
															'Approved questions: %1$d / %2$d. Families: %3$d / %4$d.',
															'ohmylms'
														),
														skill.questions,
														skill.questions_need,
														skill.families,
														skill.families_need
													) }
												</p>
												<Button
													variant="link"
													href="#/content-hub/question-bank"
													target="_blank"
													rel="noreferrer"
												>
													{ __(
														'Review and approve questions',
														'ohmylms'
													) }
												</Button>
												<ImportButton
													target="questions"
													targets={ [ 'questions' ] }
													withSkills
													initialSkills={ [
														{
															id: skill.term_id,
															name: skill.name,
															code: skill.code,
														},
													] }
												/>
											</li>
										) ) }
									</ul>
								</>
							) }
						</>
					) }
				</>
			) }
			<Button
				variant="secondary"
				disabled={ dirty }
				onClick={ () => setAttempt( ( value ) => value + 1 ) }
			>
				{ __( 'Check readiness again', 'ohmylms' ) }
			</Button>
			<h3>{ __( 'Lesson guidance', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Coverage suggestions help you build complete lessons. They do not add publishing requirements.',
					'ohmylms'
				) }
			</p>
			{ ! ( draft.program.groups || [] ).length && (
				<p>
					{ draft.units.length
						? __(
								'Your units have no lessons yet. Select a unit in the outline and choose Add lesson.',
								'ohmylms'
							)
						: __(
								'Add a unit to the outline, then add lessons to it.',
								'ohmylms'
							) }
				</p>
			) }
			<LessonReadiness
				program={
					dirty || ! report
						? draft.program
						: {
								...draft.program,
								items: draft.program.items.map( ( item ) => ( {
									...item,
									status:
										report.attachments.find(
											( row ) => row.id === item.id
										)?.status || item.status,
								} ) ),
							}
				}
				names={ draft.skill_names }
				onSelect={ ( id ) => onSelect( 'lesson', id ) }
			/>
		</section>
	);
}
