import {
	createElement,
	Fragment,
	useEffect,
	useState,
} from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { CourseLinks } from './CourseLinks';
import { LessonGroups } from './LessonGroups';
import { moveItemToUnit, prepareGroups } from './groups.mjs';
import { UnitNumbers } from './UnitNumbers';
const request = ( course, suffix = '', options = {} ) =>
	window.wp.apiFetch( {
		path: `/ohmylms/v1/courses/${ course }/learning${ suffix }`,
		...options,
	} );
const targets = [
	{
		label: __( 'Proficient', 'ohmylms' ),
		value: 'proficient',
	},
	{
		label: __( 'Mastered', 'ohmylms' ),
		value: 'mastered',
	},
];
const identity = () => {
	if ( window.crypto.randomUUID ) {
		return window.crypto.randomUUID();
	}
	const bytes = window.crypto.getRandomValues( new Uint8Array( 16 ) );
	// Set the RFC 4122 version and variant digits in the random byte array.
	bytes[ 6 ] = ( bytes[ 6 ] % 16 ) + 64;
	bytes[ 8 ] = ( bytes[ 8 ] % 64 ) + 128;
	const hex = Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	return `${ hex.slice( 0, 8 ) }-${ hex.slice( 8, 12 ) }-${ hex.slice( 12, 16 ) }-${ hex.slice( 16, 20 ) }-${ hex.slice( 20 ) }`;
};
export function CourseLearning( { courseId } ) {
	const [ loaded, setLoaded ] = useState( null );
	const [ program, setProgram ] = useState( null );
	const [ skills, setSkills ] = useState( [] );
	const [ banks, setBanks ] = useState( [] );
	const [ skillId, setSkillId ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ resourceType, setResourceType ] = useState( 'lesson' );
	const [ resources, setResources ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const [ applyExisting, setApplyExisting ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ report, setReport ] = useState( null );
	const [ reportPage, setReportPage ] = useState( 1 );
	const load = async () => {
		const data = await request( courseId );
		setLoaded( data );
		setProgram( data.draft );
	};
	useEffect( () => {
		let live = true;
		request( courseId )
			.then( async ( data ) => {
				if ( ! live ) {
					return;
				}
				setLoaded( data );
				setProgram( data.draft );
				const [ catalog, availableBanks ] = await Promise.all( [
					window.wp.apiFetch( {
						path: '/ohmylms/v1/skills',
					} ),
					window.wp.apiFetch( {
						path: '/ohmylms/v1/question-banks',
					} ),
				] );
				if ( live ) {
					setSkills( catalog.skills || [] );
					setBanks( availableBanks.banks || availableBanks || [] );
				}
			} )
			.catch( ( cause ) => live && setError( cause.message ) );
		return () => {
			live = false;
		};
	}, [ courseId ] );
	useEffect( () => {
		const handler = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );
	const update = ( patch ) => {
		setProgram( ( value ) => ( {
			...value,
			...patch,
		} ) );
		setDirty( true );
		setPreview( null );
	};
	const replaceProgram = ( next ) => {
		setProgram( next );
		setDirty( true );
		setPreview( null );
	};
	const outcome = ( index, patch ) =>
		update( {
			outcomes: program.outcomes.map( ( item, i ) =>
				i === index
					? {
							...item,
							...patch,
						}
					: item
			),
		} );
	const placement = ( index, patch ) =>
		update( {
			items: program.items.map( ( item, i ) =>
				i === index
					? {
							...item,
							...patch,
						}
					: item
			),
		} );
	async function run( work ) {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			await work();
		} catch ( cause ) {
			setError(
				[ cause.message, ...( cause.data?.errors || [] ) ].join( ' ' )
			);
		} finally {
			setBusy( false );
		}
	}
	async function save( publish ) {
		await run( async () => {
			const { program: clean, invalid } = prepareGroups( program );
			if ( invalid.length ) {
				throw new Error( __( 'Name each lesson group.', 'ohmylms' ) );
			}
			await request( courseId, publish ? '/publish' : '', {
				method: publish ? 'POST' : 'PUT',
				data: {
					...clean,
					apply_existing: applyExisting,
					expected_program_id: loaded.published?.id || 0,
				},
			} );
			await load();
			setDirty( false );
			setApplyExisting( false );
			setNotice(
				publish
					? __( 'Learning program published.', 'ohmylms' )
					: __( 'Learning draft saved.', 'ohmylms' )
			);
		} );
	}
	function move( index, direction ) {
		const items = [ ...program.items ];
		const other = index + direction;
		if ( other < 0 || other >= items.length ) {
			return;
		}
		[ items[ index ], items[ other ] ] = [ items[ other ], items[ index ] ];
		update( {
			items,
		} );
	}
	function addResource( resource ) {
		update( {
			items: [
				...program.items,
				{
					id: identity(),
					type: resourceType,
					content_id: Number( resource.id ),
					name: resource.name,
					chapter_id: 0,
					required: program.mode !== 'skill-based',
					...( resourceType === 'quiz'
						? {
								pass_percent: 80,
							}
						: {} ),
				},
			],
		} );
	}
	async function showReport( page ) {
		await run( async () => {
			setReport( await request( courseId, `/report?page=${ page }` ) );
			setReportPage( page );
		} );
	}
	if ( ! loaded || ! program ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}
	return (
		<div className="ohmylms-learning-editor">
			<div className="ohmylms-learning-editor-toolbar">
				<h2>{ __( 'Learning program', 'ohmylms' ) }</h2>
				<span>
					{ loaded.published
						? `${ __( 'Published version', 'ohmylms' ) } ${ loaded.published.version }`
						: __( 'No published program', 'ohmylms' ) }
					{ dirty
						? ` · ${ __( 'Unsaved changes', 'ohmylms' ) }`
						: '' }
				</span>
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => save( false ) }
				>
					{ __( 'Save learning draft', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ busy }
					onClick={ () => save( true ) }
				>
					{ __( 'Publish learning program', 'ohmylms' ) }
				</Button>
			</div>
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
			<section>
				<CheckboxControl
					label={ __(
						'Apply this publication to currently enrolled learners who have not completed the course',
						'ohmylms'
					) }
					checked={ applyExisting }
					onChange={ setApplyExisting }
				/>
			</section>
			<section>
				<h3>{ __( 'Skill outcomes', 'ohmylms' ) }</h3>
				<div className="ohmylms-learning-editor-add">
					<SelectControl
						label={ __( 'Select skill', 'ohmylms' ) }
						value={ skillId }
						onChange={ setSkillId }
						options={ [
							{
								label: __( 'Choose a skill', 'ohmylms' ),
								value: '',
							},
							...skills
								.filter(
									( skill ) =>
										! program.outcomes.some(
											( item ) =>
												Number( item.term_id ) ===
												Number( skill.id )
										)
								)
								.map( ( skill ) => ( {
									label: `${ skill.code ? `${ skill.code } · ` : '' }${ skill.name }`,
									value: String( skill.id ),
								} ) ),
						] }
					/>
					<Button
						variant="secondary"
						disabled={ ! skillId || busy }
						onClick={ () => {
							update( {
								outcomes: [
									...program.outcomes,
									{
										term_id: Number( skillId ),
										target: 'proficient',
										required:
											program.mode !== 'traditional',
									},
								],
							} );
							setSkillId( '' );
						} }
					>
						{ __( 'Add outcome', 'ohmylms' ) }
					</Button>
				</div>
				<div className="ohmylms-learning-table-scroll">
					<table className="widefat">
						<thead>
							<tr>
								<th>{ __( 'Skill', 'ohmylms' ) }</th>
								<th>{ __( 'Target', 'ohmylms' ) }</th>
								<th>{ __( 'Requirement', 'ohmylms' ) }</th>
								<th>{ __( 'Actions', 'ohmylms' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ program.outcomes.map( ( item, index ) => (
								<tr key={ item.term_id }>
									<td>
										{ skills.find(
											( skill ) =>
												Number( skill.id ) ===
												Number( item.term_id )
										)?.name || `#${ item.term_id }` }
									</td>
									<td>
										<SelectControl
											label={ __( 'Target', 'ohmylms' ) }
											hideLabelFromVision
											value={ item.target }
											options={ targets }
											onChange={ ( target ) =>
												outcome( index, {
													target,
												} )
											}
										/>
									</td>
									<td>
										<CheckboxControl
											label={ __(
												'Required',
												'ohmylms'
											) }
											checked={ item.required }
											onChange={ ( required ) =>
												outcome( index, {
													required,
												} )
											}
										/>
									</td>
									<td>
										<Button
											variant="secondary"
											disabled={ program.items.some(
												( row ) =>
													row.type === 'practice' &&
													row.content_id ===
														item.term_id
											) }
											onClick={ () =>
												update( {
													items: [
														...program.items,
														{
															id: identity(),
															type: 'practice',
															content_id:
																item.term_id,
															chapter_id: 0,
															required: false,
															name: skills.find(
																( skill ) =>
																	Number(
																		skill.id
																	) ===
																	Number(
																		item.term_id
																	)
															)?.name,
														},
													],
												} )
											}
										>
											{ __(
												'Add practice step',
												'ohmylms'
											) }
										</Button>{ ' ' }
										<Button
											icon="trash"
											label={ __(
												'Remove outcome',
												'ohmylms'
											) }
											onClick={ () =>
												update( {
													outcomes:
														program.outcomes.filter(
															( _, i ) =>
																i !== index
														),
													items: program.items.filter(
														( row ) =>
															row.type !==
																'practice' ||
															row.content_id !==
																item.term_id
													),
												} )
											}
										/>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
				<CheckboxControl
					label={ __(
						'Recognize existing skill evidence from other courses',
						'ohmylms'
					) }
					checked={ program.recognize_prior }
					onChange={ ( _recognizePrior ) =>
						update( {
							recognize_prior: _recognizePrior,
						} )
					}
				/>
				<TextControl
					label={ __( 'Evidence age limit in days', 'ohmylms' ) }
					type="number"
					min={ 0 }
					max={ 3650 }
					value={ program.evidence_days }
					onChange={ ( value ) =>
						update( {
							evidence_days: Number( value ),
						} )
					}
				/>
				<SelectControl
					multiple
					label={ __( 'Allowed question banks', 'ohmylms' ) }
					value={ program.bank_ids.map( String ) }
					onChange={ ( values ) =>
						update( {
							bank_ids: values.map( Number ),
						} )
					}
					options={ banks.map( ( bank ) => ( {
						label: bank.name,
						value: String( bank.id ),
					} ) ) }
				/>
			</section>
			<UnitNumbers
				courseId={ courseId }
				chapters={ loaded.chapters || [] }
				onSaved={ ( saved ) =>
					setLoaded( ( value ) => ( {
						...value,
						chapters: ( value.chapters || [] ).map( ( chapter ) => {
							const change = saved.find(
								( row ) => row.id === chapter.id
							);
							return change
								? { ...chapter, number: change.number }
								: chapter;
						} ),
					} ) )
				}
			/>
			<section>
				<h3>{ __( 'Lesson groups', 'ohmylms' ) }</h3>
				<LessonGroups
					program={ program }
					chapters={ loaded.chapters || [] }
					onChange={ replaceProgram }
					newId={ identity }
					disabled={ busy }
				/>
			</section>
			<section>
				<h3>{ __( 'Learning path', 'ohmylms' ) }</h3>
				<div className="ohmylms-learning-table-scroll">
					<table className="widefat">
						<thead>
							<tr>
								<th>{ __( 'Resource', 'ohmylms' ) }</th>
								<th>{ __( 'Unit', 'ohmylms' ) }</th>
								<th>{ __( 'Requirement', 'ohmylms' ) }</th>
								<th>{ __( 'Order', 'ohmylms' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ program.items.map( ( item, index ) => (
								<tr key={ item.id }>
									<td>
										<strong>
											{ item.name ||
												`#${ item.content_id }` }
										</strong>
										<br />
										{ item.type }
									</td>
									<td>
										<SelectControl
											label={ __( 'Unit', 'ohmylms' ) }
											hideLabelFromVision
											value={ String( item.chapter_id ) }
											onChange={ ( id ) =>
												replaceProgram(
													moveItemToUnit(
														program,
														item.id,
														Number( id )
													)
												)
											}
											options={ [
												{
													label: __(
														'Course',
														'ohmylms'
													),
													value: '0',
												},
												...(
													loaded.chapters || []
												).map( ( chapter ) => ( {
													label: chapter.name,
													value: String( chapter.id ),
												} ) ),
											] }
										/>
									</td>
									<td>
										{ item.type === 'practice' ? (
											__( 'Uses skill target', 'ohmylms' )
										) : (
											<CheckboxControl
												label={ __(
													'Required',
													'ohmylms'
												) }
												checked={ item.required }
												onChange={ ( required ) =>
													placement( index, {
														required,
													} )
												}
											/>
										) }
										{ item.type === 'quiz' && (
											<TextControl
												label={ __(
													'Pass percentage',
													'ohmylms'
												) }
												type="number"
												min={ 0 }
												max={ 100 }
												value={
													item.pass_percent ?? 80
												}
												onChange={ ( value ) =>
													placement( index, {
														pass_percent:
															Number( value ),
													} )
												}
											/>
										) }
									</td>
									<td>
										<Button
											icon="arrow-up-alt2"
											label={ __( 'Move up', 'ohmylms' ) }
											disabled={ ! index }
											onClick={ () => move( index, -1 ) }
										/>
										<Button
											icon="arrow-down-alt2"
											label={ __(
												'Move down',
												'ohmylms'
											) }
											disabled={
												index ===
												program.items.length - 1
											}
											onClick={ () => move( index, 1 ) }
										/>
										<Button
											icon="trash"
											label={ __(
												'Remove placement',
												'ohmylms'
											) }
											onClick={ () =>
												update( {
													items: program.items.filter(
														( _, i ) => i !== index
													),
												} )
											}
										/>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
				<div className="ohmylms-learning-editor-add">
					<SelectControl
						label={ __( 'Resource type', 'ohmylms' ) }
						value={ resourceType }
						options={ [
							{
								label: __( 'Shared lesson', 'ohmylms' ),
								value: 'lesson',
							},
							{
								label: __( 'Course quiz', 'ohmylms' ),
								value: 'quiz',
							},
						] }
						onChange={ ( value ) => {
							setResourceType( value );
							setResources( [] );
						} }
					/>
					<TextControl
						label={ __( 'Search resources', 'ohmylms' ) }
						value={ search }
						onChange={ setSearch }
					/>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () =>
							run( async () => {
								const data = await request(
									courseId,
									`/resources?type=${ resourceType }&search=${ encodeURIComponent( search ) }`
								);
								setResources( data.items );
							} )
						}
					>
						{ __( 'Search', 'ohmylms' ) }
					</Button>
				</div>
				{ resources.map( ( resource ) => (
					<p key={ resource.id }>
						{ resource.name } · { resource.status }{ ' ' }
						<Button
							variant="secondary"
							disabled={ program.items.some(
								( item ) =>
									item.type !== 'practice' &&
									item.content_id === Number( resource.id )
							) }
							onClick={ () => addResource( resource ) }
						>
							{ __( 'Add to path', 'ohmylms' ) }
						</Button>
					</p>
				) ) }
			</section>
			<section>
				<div className="ohmylms-learning-editor-add">
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () =>
							run( async () =>
								setPreview(
									await request( courseId, '/preview', {
										method: 'POST',
										data: program,
									} )
								)
							)
						}
					>
						{ __( 'Preview completion rules', 'ohmylms' ) }
					</Button>
					{ loaded.published && (
						<Fragment>
							<Button
								href={ loaded.url }
								target="_blank"
								variant="secondary"
							>
								{ __( 'Open learner view', 'ohmylms' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => showReport( 1 ) }
							>
								{ __( 'Learner progress', 'ohmylms' ) }
							</Button>
						</Fragment>
					) }
				</div>
				{ preview && (
					<Fragment>
						<h3>{ __( 'Completion preview', 'ohmylms' ) }</h3>
						<ul>
							{ [
								[
									'new_learner',
									__( 'New learner', 'ohmylms' ),
								],
								[
									'prior_skills',
									__(
										'Learner with prior skill evidence',
										'ohmylms'
									),
								],
								[
									'all_requirements_met',
									__(
										'All requirements satisfied',
										'ohmylms'
									),
								],
							].map( ( [ key, label ] ) => (
								<li key={ key }>
									{ label }:{ ' ' }
									{ preview[ key ].eligible
										? __( 'Eligible', 'ohmylms' )
										: `${ preview[ key ].missing.length } ${ __( 'requirements remaining', 'ohmylms' ) }` }
								</li>
							) ) }
						</ul>
						{ preview.readiness.map( ( message ) => (
							<Notice
								key={ message }
								status="warning"
								isDismissible={ false }
							>
								{ message }
							</Notice>
						) ) }
					</Fragment>
				) }
				{ report && (
					<Fragment>
						<h3>{ __( 'Learner progress', 'ohmylms' ) }</h3>
						<div className="ohmylms-learning-table-scroll">
							<table className="widefat">
								<thead>
									<tr>
										<th>{ __( 'Learner', 'ohmylms' ) }</th>
										<th>
											{ __( 'Activities', 'ohmylms' ) }
										</th>
										<th>{ __( 'Skills', 'ohmylms' ) }</th>
										<th>
											{ __( 'Checkpoints', 'ohmylms' ) }
										</th>
										<th>{ __( 'Status', 'ohmylms' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ report.students.map( ( student ) => (
										<tr key={ student.student_id }>
											<td>{ student.name }</td>
											{ [
												'activities',
												'outcomes',
												'assessments',
											].map( ( kind ) => (
												<td key={ kind }>
													{ student.progress
														? `${ student.progress.dimensions[ kind ].met }/${ student.progress.dimensions[ kind ].total }`
														: '-' }
													{ kind === 'outcomes' &&
														student.progress
															?.program.outcomes
															.length > 0 && (
															<details>
																<summary>
																	{ __(
																		'Skill details',
																		'ohmylms'
																	) }
																</summary>
																<ul>
																	{ student.progress.program.outcomes.map(
																		(
																			skill
																		) => (
																			<li
																				key={
																					skill.term_id
																				}
																			>
																				{
																					skill.name
																				}

																				:{ ' ' }
																				{
																					skill
																						.state
																						.level
																				}{ ' ' }
																				/{ ' ' }
																				{
																					skill.target
																				}{ ' ' }
																				(
																				{ ( () => {
																					if (
																						skill.met
																					) {
																						return __(
																							'Target met',
																							'ohmylms'
																						);
																					}
																					if (
																						skill.required
																					) {
																						return __(
																							'Required',
																							'ohmylms'
																						);
																					}
																					return __(
																						'Optional',
																						'ohmylms'
																					);
																				} )() }

																				)
																			</li>
																		)
																	) }
																</ul>
															</details>
														) }
												</td>
											) ) }
											<td>
												{ ! student.progress
													? __(
															'Original curriculum',
															'ohmylms'
														)
													: ( () => {
															if (
																student.progress
																	.completed
															) {
																return __(
																	'Completed',
																	'ohmylms'
																);
															}
															if (
																student.progress
																	.pending
															) {
																return __(
																	'Pending evaluation',
																	'ohmylms'
																);
															}
															return `${ student.progress.missing.length } ${ __( 'remaining', 'ohmylms' ) }`;
														} )() }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
						<Button
							disabled={ busy || reportPage <= 1 }
							onClick={ () => showReport( reportPage - 1 ) }
						>
							{ __( 'Previous', 'ohmylms' ) }
						</Button>
						<Button
							disabled={ busy || reportPage * 25 >= report.total }
							onClick={ () => showReport( reportPage + 1 ) }
						>
							{ __( 'Next', 'ohmylms' ) }
						</Button>
					</Fragment>
				) }
			</section>
			<CourseLinks courseId={ courseId } />
		</div>
	);
}
