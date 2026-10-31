import {
	createElement,
	Fragment,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	loadAssessmentSettings,
	saveAssessmentSettings,
	loadQuizQuestions,
	loadRevisions,
} from './api.mjs';
import {
	accommodationMap,
	accommodationRows,
	assignQuestion,
	localInputToUtc,
	quizIdFromHash,
	sectionOf,
	setSlotMarks,
	utcToLocalInput,
} from './model.mjs';
import { PoolsEditor } from './PoolsEditor';
import { useNativeAssessmentSettings } from '../learning/NativeAssessmentSettings';

/**
 * Assessment settings for the quiz editor: quiz/exam preset, feedback release,
 * availability window, sections with slot marks, extra time, and published revisions.
 * Stored separately from the legacy quiz settings, then frozen into each revision.
 * @param props
 */
export function AssessmentSettingsPanel( props ) {
	const quizId =
		props.quizId || quizIdFromHash( props.hash || window.location.hash );
	const [ localSettings, setLocalSettings ] = useState( null );
	const native = useNativeAssessmentSettings( Boolean( props.native ) );
	const settings = props.native ? native.settings : localSettings;
	const setSettings = props.native ? native.setSettings : setLocalSettings;
	const [ questions, setQuestions ] = useState( [] );
	const [ revisions, setRevisions ] = useState( [] );
	const [ accommodations, updateAccommodations ] = useState( () =>
		accommodationRows( native.settings?.accommodations || {} )
	);
	const accommodationSource = JSON.stringify(
		native.settings?.accommodations || {}
	);
	const lastAccommodations = useRef( accommodationSource );
	useEffect( () => {
		if (
			props.native &&
			accommodationSource !== lastAccommodations.current
		) {
			lastAccommodations.current = accommodationSource;
			updateAccommodations(
				accommodationRows( JSON.parse( accommodationSource ) )
			);
		}
	}, [ props.native, accommodationSource ] );
	const setAccommodations = ( rows ) => {
		updateAccommodations( rows );
		if ( props.native ) {
			const next = accommodationMap( rows );
			lastAccommodations.current = JSON.stringify( next );
			setSettings( {
				...settings,
				accommodations: next,
			} );
		}
	};
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	useEffect( () => {
		if ( ! quizId ) {
			return;
		}
		let active = true;
		Promise.all( [
			props.native
				? Promise.resolve( null )
				: loadAssessmentSettings( quizId ),
			loadQuizQuestions( quizId ),
			loadRevisions( quizId ),
		] )
			.then( ( [ loaded, content, history ] ) => {
				if ( ! active ) {
					return;
				}
				if ( loaded ) {
					setLocalSettings( loaded.settings );
					updateAccommodations(
						accommodationRows( loaded.settings.accommodations )
					);
				}
				setQuestions( content );
				setRevisions( history.revisions || [] );
			} )
			.catch(
				( cause ) =>
					active &&
					setMessage( {
						status: 'error',
						text: cause.message,
					} )
			);
		return () => {
			active = false;
		};
	}, [ quizId, props.native ] );
	if ( ! quizId ) {
		return null;
	}
	const set = ( key ) => ( value ) =>
		setSettings( {
			...settings,
			[ key ]: value,
		} );
	async function save( extra = {} ) {
		setSaving( true );
		setMessage( null );
		try {
			const saved = await saveAssessmentSettings( quizId, {
				...settings,
				accommodations: accommodationMap( accommodations ),
				...extra,
			} );
			setSettings( saved.settings );

			setAccommodations(
				accommodationRows( saved.settings.accommodations )
			);
			setMessage( {
				status: 'success',
				text: __(
					'Assessment settings saved. Publish the quiz to apply them to new attempts.',
					'ohmylms'
				),
			} );
		} catch ( cause ) {
			setMessage( {
				status: 'error',
				text: cause.message || __( 'Could not save.', 'ohmylms' ),
			} );
		} finally {
			setSaving( false );
		}
	}
	return (
		<div
			className="ohmylms-assessment-settings"
			style={
				props.native
					? undefined
					: {
							margin: '16px 24px',
							background: '#fff',
						}
			}
		>
			<fieldset disabled={ saving }>
				<PanelBody
					title={ __( 'Schedule, results and sections', 'ohmylms' ) }
					initialOpen={ Boolean( props.native ) }
				>
					{ message && (
						<Notice
							status={ message.status }
							onRemove={ () => setMessage( null ) }
						>
							{ message.text }
						</Notice>
					) }
					{ ! settings ? (
						<Spinner />
					) : (
						<Fragment>
							<div
								style={ {
									display: 'flex',
									flexWrap: 'wrap',
									gap: 16,
									alignItems: 'end',
								} }
							>
								{ ! props.native && (
									<SelectControl
										label={ __( 'Kind', 'ohmylms' ) }
										value={ settings.kind }
										options={ [
											{
												value: 'quiz',
												label: __( 'Quiz', 'ohmylms' ),
											},
											{
												value: 'exam',
												label: __(
													'Exam (fixed paper)',
													'ohmylms'
												),
											},
											{
												value: 'practice',
												label: __(
													'Practice',
													'ohmylms'
												),
											},
										] }
										onChange={ set( 'kind' ) }
									/>
								) }
								{ ! props.native && (
									<Button
										variant="secondary"
										onClick={ () =>
											save( {
												preset: 'exam',
											} )
										}
										disabled={ saving }
									>
										{ __( 'Apply exam preset', 'ohmylms' ) }
									</Button>
								) }
								<SelectControl
									label={ __(
										'Show results to learners',
										'ohmylms'
									) }
									value={ settings.feedback_release }
									options={ [
										{
											value: 'immediate',
											label: __(
												'Immediately',
												'ohmylms'
											),
										},
										{
											value: 'after_close',
											label: __(
												'After the assessment closes',
												'ohmylms'
											),
										},
										{
											value: 'manual',
											label: __(
												'When I release them',
												'ohmylms'
											),
										},
									] }
									onChange={ set( 'feedback_release' ) }
								/>
								{ settings.feedback_release !== 'immediate' && (
									<CheckboxControl
										label={ __(
											'Results released',
											'ohmylms'
										) }
										checked={ !! settings.released }
										onChange={ set( 'released' ) }
									/>
								) }
							</div>
							<div
								style={ {
									display: 'flex',
									flexWrap: 'wrap',
									gap: 16,
								} }
							>
								<TextControl
									type="datetime-local"
									label={ __(
										'Opens (your local time)',
										'ohmylms'
									) }
									value={ utcToLocalInput(
										settings.available_from
									) }
									onChange={ ( value ) =>
										set( 'available_from' )(
											localInputToUtc( value )
										)
									}
								/>
								<TextControl
									type="datetime-local"
									label={ __(
										'Closes (your local time)',
										'ohmylms'
									) }
									value={ utcToLocalInput(
										settings.available_until
									) }
									onChange={ ( value ) =>
										set( 'available_until' )(
											localInputToUtc( value )
										)
									}
								/>
								<TextControl
									type="number"
									min={ 0 }
									max={ 600 }
									label={ __(
										'Grace period (seconds)',
										'ohmylms'
									) }
									help={ __(
										'Answers sent just before time runs out are still accepted for this long.',
										'ohmylms'
									) }
									value={ String( settings.grace_seconds ) }
									onChange={ ( value ) =>
										set( 'grace_seconds' )(
											Number( value )
										)
									}
								/>
							</div>
							<h3>{ __( 'Sections and marks', 'ohmylms' ) }</h3>
							<p>
								{ __(
									'Questions without a section come first. Slot marks override the question score for this assessment only. Page breaks apply to newly published revisions.',
									'ohmylms'
								) }
							</p>
							{ settings.sections.map( ( section, index ) => (
								<div
									key={ index }
									style={ {
										display: 'flex',
										gap: 8,
										alignItems: 'end',
									} }
								>
									<TextControl
										label={ sprintf(
											/* translators: %d: section number. */ __(
												'Section %d title',
												'ohmylms'
											),
											index + 1
										) }
										value={ section.title }
										onChange={ ( title ) =>
											set( 'sections' )(
												settings.sections.map(
													( item, position ) =>
														position === index
															? {
																	...item,
																	title,
																}
															: item
												)
											)
										}
									/>
									<CheckboxControl
										label={ __(
											'Start on a new page',
											'ohmylms'
										) }
										help={ __(
											'Splits the paper into pages when learners take it.',
											'ohmylms'
										) }
										checked={ !! section.new_page }
										onChange={ ( newPage ) =>
											set( 'sections' )(
												settings.sections.map(
													( item, position ) =>
														position === index
															? {
																	...item,
																	new_page:
																		newPage,
																}
															: item
												)
											)
										}
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											set( 'sections' )(
												settings.sections.filter(
													( _, position ) =>
														position !== index
												)
											)
										}
									>
										{ __( 'Remove section', 'ohmylms' ) }
									</Button>
								</div>
							) ) }
							<Button
								variant="secondary"
								onClick={ () =>
									set( 'sections' )( [
										...settings.sections,
										{
											title: '',
											questions: [],
											marks: {},
											new_page: false,
										},
									] )
								}
							>
								{ __( 'Add section', 'ohmylms' ) }
							</Button>
							<table
								className="widefat striped"
								style={ {
									marginTop: 12,
								} }
							>
								<thead>
									<tr>
										<th>{ __( 'Question', 'ohmylms' ) }</th>
										<th>{ __( 'Section', 'ohmylms' ) }</th>
										<th>
											{ __( 'Slot marks', 'ohmylms' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ questions.map( ( question ) => {
										const index = sectionOf(
											settings.sections,
											question.id
										);
										return (
											<tr key={ question.id }>
												<td>{ question.name }</td>
												<td>
													<SelectControl
														label={ __(
															'Section',
															'ohmylms'
														) }
														hideLabelFromVision
														value={ String(
															index
														) }
														options={ [
															{
																value: '-1',
																label: __(
																	'— none —',
																	'ohmylms'
																),
															},
															...settings.sections.map(
																(
																	section,
																	position
																) => ( {
																	value: String(
																		position
																	),
																	label:
																		section.title ||
																		sprintf(
																			/* translators: %d: section number. */ __(
																				'Section %d',
																				'ohmylms'
																			),
																			position +
																				1
																		),
																} )
															),
														] }
														onChange={ ( value ) =>
															set( 'sections' )(
																assignQuestion(
																	settings.sections,
																	question.id,
																	Number(
																		value
																	)
																)
															)
														}
													/>
												</td>
												<td>
													<TextControl
														type="number"
														min={ 0 }
														step="0.25"
														label={ __(
															'Slot marks',
															'ohmylms'
														) }
														hideLabelFromVision
														disabled={ index < 0 }
														placeholder={ String(
															question.marks ?? ''
														) }
														value={
															index < 0
																? ''
																: String(
																		settings
																			.sections[
																			index
																		]
																			.marks?.[
																			question
																				.id
																		] ?? ''
																	)
														}
														onChange={ ( value ) =>
															set( 'sections' )(
																setSlotMarks(
																	settings.sections,
																	question.id,
																	value
																)
															)
														}
													/>
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
							<h3>
								{ __(
									'Extra time (accommodations)',
									'ohmylms'
								) }
							</h3>
							{ accommodations.map( ( row, index ) => (
								<div
									key={ index }
									style={ {
										display: 'flex',
										gap: 8,
										alignItems: 'end',
									} }
								>
									<TextControl
										type="number"
										label={ __(
											'Learner user ID',
											'ohmylms'
										) }
										value={ row.userId }
										onChange={ ( userId ) =>
											setAccommodations(
												accommodations.map(
													( item, position ) =>
														position === index
															? {
																	...item,
																	userId,
																}
															: item
												)
											)
										}
									/>
									<TextControl
										type="number"
										label={ __(
											'Extra minutes',
											'ohmylms'
										) }
										value={ row.minutes }
										onChange={ ( minutes ) =>
											setAccommodations(
												accommodations.map(
													( item, position ) =>
														position === index
															? {
																	...item,
																	minutes,
																}
															: item
												)
											)
										}
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () =>
											setAccommodations(
												accommodations.filter(
													( _, position ) =>
														position !== index
												)
											)
										}
									>
										{ __( 'Remove', 'ohmylms' ) }
									</Button>
								</div>
							) ) }
							<Button
								variant="secondary"
								onClick={ () =>
									setAccommodations( [
										...accommodations,
										{
											userId: '',
											minutes: '',
										},
									] )
								}
							>
								{ __( 'Add extra time', 'ohmylms' ) }
							</Button>
							<div
								style={ {
									marginTop: 16,
								} }
							>
								{ ! props.native && (
									<Button
										variant="primary"
										isBusy={ saving }
										onClick={ () => save() }
									>
										{ __(
											'Save assessment settings',
											'ohmylms'
										) }
									</Button>
								) }
							</div>
							<PoolsEditor
								quizId={ quizId }
								native={ props.native }
							/>
							<h3>{ __( 'Published revisions', 'ohmylms' ) }</h3>
							{ revisions.length === 0 ? (
								<p>
									{ __(
										'Not published yet. The first attempt or the Publish button creates revision 1.',
										'ohmylms'
									) }
								</p>
							) : (
								<ul>
									{ revisions.map( ( revision ) => (
										<li key={ revision.id }>
											{ sprintf(
												/* translators: 1: revision number, 2: total marks, 3: attempts, 4: date */
												__(
													'Revision %1$d · %2$s marks · %3$d attempts · %4$s UTC',
													'ohmylms'
												),
												revision.revision_no,
												Number( revision.total_marks ),
												revision.attempts,
												revision.created_at
											) }
											{ revision.status === 'published' &&
												` · ${ __( 'current', 'ohmylms' ) }` }
										</li>
									) ) }
								</ul>
							) }
						</Fragment>
					) }
				</PanelBody>
			</fieldset>
		</div>
	);
}
