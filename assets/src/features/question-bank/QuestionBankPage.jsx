import { nativeLessonHref } from '../content-hub/editorNavigation.mjs';
import {
	createElement,
	Fragment,
	useContext,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import {
	searchBank,
	loadBankQuestion,
	approveQuestion,
	archiveQuestion,
	restoreQuestion,
	duplicateQuestion,
	saveQuestionAttributes,
	listBanks,
	createBank,
	listGrants,
	grantBank,
	revokeBank,
	listSkills,
} from './api.mjs';
import {
	cleanFilters,
	versionLabel,
	DIFFICULTIES,
	flattenTree,
	skillTree,
} from './model.mjs';
import { SkillMapEditor } from './SkillMapEditor';
import { NewQuestionModal } from './NewQuestionModal';
import { ImportButton } from '../content-hub/ImportButton';
import { useHubAction } from '../content-hub/useHubAction.js';
import { ExportRowsButtons } from '../content-hub/ExportRowsButtons';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import { HubContext } from '../content-hub/context';
import { QuestionForm } from '../question-editor/QuestionForm';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../question-editor/questionBlocks.mjs';
const TYPES = QUESTION_BLOCK_TYPES.map( ( [ type ] ) => type );

/** Question bank management: search, approve, archive, duplicate, metadata and sharing. */
export function QuestionBankPage() {
	const hub = useContext( HubContext );
	return (
		<AdminPage
			className="ohmylms-question-bank"
			headingLevel={ hub ? 2 : 1 }
			title={ __( 'Question bank', 'ohmylms' ) }
			description={ __(
				'Every saved question lives here with its versions. Approve questions to share them, use them in practice and draw them into random pools.',
				'ohmylms'
			) }
		>
			<TabPanel
				tabs={ [
					{
						name: 'questions',
						title: __( 'Questions', 'ohmylms' ),
					},
					{
						name: 'banks',
						title: __( 'Banks & sharing', 'ohmylms' ),
					},
				] }
			>
				{ ( tab ) =>
					tab.name === 'questions' ? <QuestionsTab /> : <BanksTab />
				}
			</TabPanel>
		</AdminPage>
	);
}
function QuestionsTab() {
	const [ filters, setFilters ] = useState( {
		search: '',
		type: '',
		difficulty: '',
		status: '',
		skill: '',
		bank: '',
	} );
	const [ page, setPage ] = useState( 1 );
	const [ result, setResult ] = useState( {
		items: [],
		total: 0,
		per_page: 20,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ skills, setSkills ] = useState( [] );
	const [ banks, setBanks ] = useState( [] );
	const [ organisationViews, setOrganisationViews ] = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ creating, setCreating ] = useState( false );
	const [ reload, setReload ] = useState( 0 );
	const newQuestion = () =>
		window.ohmylmsAssessment?.formAuthoring
			? setCreating( true )
			: window.location.assign(
					new URL(
						'post-new.php?post_type=ohmylms-question',
						window.location.href
					).href
				);
	useHubAction( { new: newQuestion } );
	useEffect( () => {
		window.wp
			.apiFetch( { path: '/ohmylms/v1/question-bank-organisation' } )
			.then( ( data ) => setOrganisationViews( data.views || [] ) )
			.catch( ( cause ) => setError( cause.message ) );
		listSkills()
			.then( ( data ) => setSkills( data.skills || [] ) )
			.catch( () => {} );
		listBanks()
			.then( ( data ) => setBanks( data.banks || [] ) )
			.catch( () => {} );
	}, [] );
	useEffect( () => {
		let active = true;
		setLoading( true );
		const timer = setTimeout( () => {
			searchBank( {
				...cleanFilters( filters ),
				page,
			} )
				.then( ( data ) => {
					if ( active ) {
						setResult( data );
						setError( '' );
					}
				} )
				.catch(
					( cause ) =>
						active &&
						setError(
							cause.message ||
								__( 'Could not load questions.', 'ohmylms' )
						)
				)
				.finally( () => active && setLoading( false ) );
		}, 250 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ filters, page, reload ] );
	const skillNames = new Map(
		skills.map( ( skill ) => [ skill.id, skill.name ] )
	);
	const update = ( key ) => ( value ) => {
		setPage( 1 );
		setFilters( {
			...filters,
			[ key ]: value,
		} );
	};
	async function run( action ) {
		try {
			await action();
			setReload( ( value ) => value + 1 );
		} catch ( cause ) {
			setError( cause.message || __( 'The action failed.', 'ohmylms' ) );
		}
	}
	const pages = Math.max(
		1,
		Math.ceil( result.total / ( result.per_page || 20 ) )
	);
	return (
		<Fragment>
			{ creating && (
				<NewQuestionModal
					onClose={ () => setCreating( false ) }
					onCreated={ ( id ) => {
						setCreating( false );
						setReload( ( value ) => value + 1 );
						setSelected( id );
					} }
				/>
			) }
			<AdminCard className="ohmylms-bank-card">
				<div className="ohmylms-bank-filters ohmylms-ext-toolbar">
					<SelectControl
						label={ __( 'Organise question bank by', 'ohmylms' ) }
						value={ filters.organisation || '' }
						options={ [
							{
								value: '',
								label: __( 'All questions', 'ohmylms' ),
							},
							...organisationViews.map( ( view ) => ( {
								value: view.id,
								label: view.name,
							} ) ),
						] }
						onChange={ ( value ) => {
							setPage( 1 );
							setFilters( {
								...filters,
								organisation: value,
								scope: '',
							} );
						} }
					/>
					{ filters.organisation && (
						<SelectControl
							label={ __(
								'Curriculum, roadmap or course',
								'ohmylms'
							) }
							value={ filters.scope || '' }
							options={ [
								{
									value: '',
									label: __( 'Choose a location', 'ohmylms' ),
								},
								...(
									organisationViews.find(
										( view ) =>
											view.id === filters.organisation
									)?.scopes || []
								).map( ( scope, index, scopes ) => {
									const names = [ scope.name ];
									const seen = new Set( [ scope.id ] );
									let parent = scopes.find(
										( item ) => item.id === scope.parent
									);
									while (
										parent &&
										! seen.has( parent.id )
									) {
										seen.add( parent.id );
										names.unshift( parent.name );
										parent = scopes.find(
											( item ) =>
												item.id === parent.parent
										);
									}
									return {
										value: String( scope.id ),
										label: names.join( ' / ' ),
									};
								} ),
							] }
							onChange={ update( 'scope' ) }
						/>
					) }
					<p>
						{ __(
							'These views follow existing curriculum skills, roadmap skills, course targets and quiz placements. Questions stay reusable; organising them grants no additional access. Use the Skill filter below to narrow a branch.',
							'ohmylms'
						) }
					</p>
				</div>
				<div className="ohmylms-bank-filters ohmylms-ext-toolbar">
					<TextControl
						label={ __( 'Search', 'ohmylms' ) }
						value={ filters.search }
						onChange={ update( 'search' ) }
					/>
					<SelectControl
						label={ __( 'Type', 'ohmylms' ) }
						value={ filters.type }
						options={ [
							{
								value: '',
								label: __( 'All types', 'ohmylms' ),
							},
							...TYPES.map( ( type ) => ( {
								value: type,
								label: type,
							} ) ),
						] }
						onChange={ update( 'type' ) }
					/>
					<SelectControl
						label={ __( 'Difficulty', 'ohmylms' ) }
						value={ filters.difficulty }
						options={ [
							{
								value: '',
								label: __( 'Any', 'ohmylms' ),
							},
							...DIFFICULTIES.map( ( value ) => ( {
								value,
								label: value,
							} ) ),
						] }
						onChange={ update( 'difficulty' ) }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ filters.status }
						options={ [
							{
								value: '',
								label: __( 'Active', 'ohmylms' ),
							},
							{
								value: 'draft',
								label: __( 'Not approved', 'ohmylms' ),
							},
							{
								value: 'approved',
								label: __( 'Approved', 'ohmylms' ),
							},
							{
								value: 'archived',
								label: __( 'Archived', 'ohmylms' ),
							},
						] }
						onChange={ update( 'status' ) }
					/>
					<SelectControl
						label={ __( 'Skill', 'ohmylms' ) }
						value={ filters.skill }
						options={ [
							{
								value: '',
								label: __( 'Any skill', 'ohmylms' ),
							},
							...flattenTree( skillTree( skills ) ).map(
								( skill ) => ( {
									value: String( skill.id ),
									label: `${ '— '.repeat( skill.depth ) }${ skill.name }`,
								} )
							),
						] }
						onChange={ update( 'skill' ) }
					/>
					<SelectControl
						label={ __( 'Bank', 'ohmylms' ) }
						value={ filters.bank }
						options={ [
							{
								value: '',
								label: __( 'All visible', 'ohmylms' ),
							},
							{
								value: '0',
								label: __(
									'My questions (no bank)',
									'ohmylms'
								),
							},
							...banks.map( ( bank ) => ( {
								value: String( bank.id ),
								label: bank.name,
							} ) ),
						] }
						onChange={ update( 'bank' ) }
					/>
					<div
						style={ {
							marginLeft: 'auto',
						} }
					>
						<Button variant="primary" onClick={ newQuestion }>
							{ __( 'New question', 'ohmylms' ) }
						</Button>{ ' ' }
						<ImportButton target="questions" />
						<ExportRowsButtons
							ids={ result.items.map( ( item ) => item.id ) }
							label={ __( 'Question bank', 'ohmylms' ) }
						/>
					</div>
				</div>
				{ error && (
					<Notice status="error" onRemove={ () => setError( '' ) }>
						{ error }
					</Notice>
				) }
				{ ( () => {
					if ( loading ) {
						return <Spinner />;
					}
					if ( result.items.length === 0 ) {
						return (
							<p>
								{ __(
									'No questions match these filters.',
									'ohmylms'
								) }
							</p>
						);
					}
					return (
						<div className="ohmylms-ext-table-scroll">
							<table className="widefat striped ohmylms-bank-table">
								<thead>
									<tr>
										<th>{ __( 'Question', 'ohmylms' ) }</th>
										<th>{ __( 'Type', 'ohmylms' ) }</th>
										<th>{ __( 'Version', 'ohmylms' ) }</th>
										<th>
											{ __( 'Difficulty', 'ohmylms' ) }
										</th>
										<th>{ __( 'Skills', 'ohmylms' ) }</th>
										<th>{ __( 'Used in', 'ohmylms' ) }</th>
										<th>{ __( 'Actions', 'ohmylms' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ result.items.map( ( item ) => (
										<tr key={ item.id }>
											<td>
												<Button
													variant="link"
													onClick={ () =>
														item.can_edit &&
														! window
															.ohmylmsAssessment
															?.formAuthoring
															? window.location.assign(
																	item.native_editor_url ||
																		nativeLessonHref(
																			item.id,
																			'/question-bank'
																		)
																)
															: setSelected(
																	item.id
																)
													}
												>
													{ item.name ||
														__(
															'(untitled)',
															'ohmylms'
														) }
												</Button>
												{ item.secure && (
													<span className="ohmylms-badge">
														{ __(
															'Exam only',
															'ohmylms'
														) }
													</span>
												) }
											</td>
											<td>{ item.type }</td>
											<td>{ versionLabel( item ) }</td>
											<td>{ item.difficulty }</td>
											<td>
												{ item.skills
													.map( ( id ) =>
														skillNames.get( id )
													)
													.filter( Boolean )
													.join( ', ' ) }
											</td>
											<td>
												{ sprintf(
													/* translators: 1: quizzes, 2: learner responses */
													__(
														'%1$d quizzes · %2$d responses',
														'ohmylms'
													),
													item.usage.quizzes.length,
													item.usage.responses
												) }
											</td>
											<td
												style={ {
													whiteSpace: 'nowrap',
												} }
											>
												{ item.can_approve &&
													! item.approved_is_current &&
													item.post_status !==
														'ohmylms_archived' && (
														<Button
															variant="secondary"
															size="small"
															onClick={ () =>
																run( () =>
																	approveQuestion(
																		item.id
																	)
																)
															}
														>
															{ __(
																'Approve',
																'ohmylms'
															) }
														</Button>
													) }{ ' ' }
												<Button
													variant="tertiary"
													size="small"
													onClick={ () =>
														run( () =>
															duplicateQuestion(
																item.id
															).then(
																( copy ) => {
																	if (
																		! window
																			.ohmylmsAssessment
																			?.formAuthoring
																	) {
																		window.location.assign(
																			nativeLessonHref(
																				copy.id,
																				'/question-bank'
																			)
																		);
																	}
																}
															)
														)
													}
												>
													{ __(
														'Duplicate',
														'ohmylms'
													) }
												</Button>{ ' ' }
												{ item.can_edit &&
													( item.post_status ===
													'ohmylms_archived' ? (
														<Button
															variant="tertiary"
															size="small"
															onClick={ () =>
																run( () =>
																	restoreQuestion(
																		item.id
																	)
																)
															}
														>
															{ __(
																'Restore',
																'ohmylms'
															) }
														</Button>
													) : (
														<Button
															variant="tertiary"
															size="small"
															isDestructive
															onClick={ () =>
																run( () =>
																	archiveQuestion(
																		item.id
																	)
																)
															}
														>
															{ __(
																'Archive',
																'ohmylms'
															) }
														</Button>
													) ) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					);
				} )() }
				<div
					className="ohmylms-bank-pagination ohmylms-ext-toolbar"
					style={ {
						marginTop: 16,
						marginBottom: 0,
					} }
				>
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>{ ' ' }
					<span>
						{ sprintf(
							/* translators: %1$d: current page number; %2$d: total number of pages. */ __(
								'Page %1$d of %2$d',
								'ohmylms'
							),
							page,
							pages
						) }
					</span>{ ' ' }
					<Button
						variant="secondary"
						disabled={ page >= pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</div>
			</AdminCard>
			{ selected && (
				<QuestionDetail
					id={ selected }
					skills={ skills }
					banks={ banks }
					onClose={ () => setSelected( null ) }
					onChanged={ () => setReload( ( value ) => value + 1 ) }
				/>
			) }
		</Fragment>
	);
}
function QuestionDetail( { id, skills, banks, onClose, onChanged } ) {
	const [ detail, setDetail ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ draft, setDraft ] = useState( null );
	useEffect( () => {
		let active = true;
		loadBankQuestion( id )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setDetail( data );
				setDraft( {
					name: data.name,
					description: data.description || '',
					questions: data.options || [],
					settings: data.settings || {},
					skills: data.skill_map || {},
					bank: {
						difficulty: data.difficulty,
						source: data.source,
						family_id: data.family_id,
						secure: data.secure,
						bank_id: data.bank_id,
					},
				} );
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ id ] );
	async function save() {
		setSaving( true );
		setError( '' );
		try {
			await saveQuestionAttributes( id, {
				name: draft.name,
				description: draft.description,
				questions: draft.questions,
				settings: draft.settings,
				skills: draft.skills,
				bank: draft.bank,
				base_modified: detail.modified,
			} );
			const fresh = await loadBankQuestion( id );
			setDetail( fresh );
			setDraft( ( value ) => ( {
				...value,
				questions: fresh.options || [],
				settings: fresh.settings || {},
			} ) );
			onChanged();
		} catch ( cause ) {
			setError( cause.message || __( 'Could not save.', 'ohmylms' ) );
		} finally {
			setSaving( false );
		}
	}
	const setBank = ( key ) => ( value ) =>
		setDraft( {
			...draft,
			bank: {
				...draft.bank,
				[ key ]: value,
			},
		} );
	return (
		<Modal
			title={ detail?.name || __( 'Question', 'ohmylms' ) }
			onRequestClose={ onClose }
			size="large"
			className="ohmylms-question-block-modal"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! detail || ! draft ? (
				<Spinner />
			) : (
				<QuestionForm
					key={ id }
					question={ {
						id,
						...draft,
					} }
					types={ QUESTION_BLOCK_TYPES.map(
						( [ value, label ] ) => ( {
							value,
							label,
						} )
					) }
					onTypeChange={ ( type ) =>
						detail.can_edit &&
						setDraft( ( value ) => ( {
							...value,
							...questionTypePatch( value, type ),
						} ) )
					}
					onChange={ ( patch ) =>
						detail.can_edit &&
						setDraft( ( value ) => ( {
							...value,
							...patch,
						} ) )
					}
					toolbarActions={
						detail.can_edit && (
							<Button
								variant="primary"
								isBusy={ saving }
								disabled={ saving }
								onClick={ save }
							>
								{ __( 'Save question', 'ohmylms' ) }
							</Button>
						)
					}
					label={ __( 'Question', 'ohmylms' ) }
					titleLabel={ __( 'Question title', 'ohmylms' ) }
					workspaceLabel={ __( 'Question form editor', 'ohmylms' ) }
					readOnly={ ! detail.can_edit }
					readOnlyContent={
						detail.options?.length > 0 && (
							<section>
								<h3>{ __( 'Answers', 'ohmylms' ) }</h3>
								<ul>
									{ detail.options.map( ( option ) => (
										<li key={ option.id }>
											{ option.answer }
										</li>
									) ) }
								</ul>
							</section>
						)
					}
					extraSettings={
						<Fragment>
							<p>
								<strong>{ __( 'UUID', 'ohmylms' ) }:</strong>{ ' ' }
								<code>{ detail.uuid }</code>
							</p>
							<h3>{ __( 'Placement', 'ohmylms' ) }</h3>
							<ul>
								{ detail.quizzes.length === 0 && (
									<li>
										{ __(
											'Not used in any quiz.',
											'ohmylms'
										) }
									</li>
								) }
								{ detail.quizzes.map( ( quiz ) => (
									<li key={ quiz.id }>
										{ quiz.can_edit ? (
											<a
												href={ `#/quiz-edit/${ quiz.id }` }
											>
												{ quiz.name }
											</a>
										) : (
											quiz.name
										) }
									</li>
								) ) }
							</ul>
							<h3>{ __( 'Classification', 'ohmylms' ) }</h3>
							<fieldset
								disabled={ ! detail.can_edit }
								style={ {
									display: 'flex',
									flexWrap: 'wrap',
									gap: 12,
								} }
							>
								<SelectControl
									label={ __( 'Difficulty', 'ohmylms' ) }
									value={ draft.bank.difficulty }
									options={ DIFFICULTIES.map( ( value ) => ( {
										value,
										label: value,
									} ) ) }
									onChange={ setBank( 'difficulty' ) }
								/>
								<TextControl
									label={ __( 'Source', 'ohmylms' ) }
									value={ draft.bank.source }
									onChange={ setBank( 'source' ) }
								/>
								<TextControl
									label={ __( 'Family', 'ohmylms' ) }
									help={ __(
										'Questions in one family count as the same evidence.',
										'ohmylms'
									) }
									value={ draft.bank.family_id }
									onChange={ setBank( 'family_id' ) }
								/>
								<SelectControl
									label={ __( 'Bank', 'ohmylms' ) }
									value={ String( draft.bank.bank_id || 0 ) }
									options={ [
										{
											value: '0',
											label: __(
												'No bank (personal)',
												'ohmylms'
											),
										},
										...banks.map( ( bank ) => ( {
											value: String( bank.id ),
											label: bank.name,
										} ) ),
									] }
									onChange={ ( value ) =>
										setBank( 'bank_id' )( Number( value ) )
									}
								/>
								<CheckboxControl
									label={ __(
										'Exam only (never used for practice)',
										'ohmylms'
									) }
									checked={ !! draft.bank.secure }
									onChange={ setBank( 'secure' ) }
								/>
							</fieldset>
							<h3>{ __( 'Skills assessed', 'ohmylms' ) }</h3>
							<SkillMapEditor
								skills={ skills }
								value={ draft.skills }
								parts={ (
									draft.settings?.parts || [
										{
											id: 'p1',
										},
									]
								).map( ( part ) => part.id ) }
								disabled={ ! detail.can_edit }
								onChange={ ( skillsMap ) =>
									setDraft( {
										...draft,
										skills: skillsMap,
									} )
								}
							/>
							{ detail.can_edit && (
								<Button
									variant="primary"
									isBusy={ saving }
									onClick={ save }
								>
									{ __(
										'Save (creates a new version if changed)',
										'ohmylms'
									) }
								</Button>
							) }
							<h3>{ __( 'Versions', 'ohmylms' ) }</h3>
							<table className="widefat striped">
								<thead>
									<tr>
										<th>#</th>
										<th>{ __( 'Title', 'ohmylms' ) }</th>
										<th>{ __( 'Created', 'ohmylms' ) }</th>
										<th />
									</tr>
								</thead>
								<tbody>
									{ detail.versions.map( ( version ) => (
										<tr key={ version.id }>
											<td>v{ version.version_no }</td>
											<td>{ version.title }</td>
											<td>
												{ version.created_at } UTC
												{ Number(
													version.is_migration_snapshot
												) === 1 &&
													` · ${ __( 'captured at migration', 'ohmylms' ) }` }
											</td>
											<td>
												{ Number( version.id ) ===
												Number(
													detail.approved_version_id
												) ? (
													<strong>
														{ __(
															'Approved',
															'ohmylms'
														) }
													</strong>
												) : (
													detail.can_approve && (
														<Button
															variant="link"
															onClick={ async () => {
																try {
																	await approveQuestion(
																		id,
																		version.id
																	);
																	setDetail(
																		await loadBankQuestion(
																			id
																		)
																	);
																	onChanged();
																} catch ( cause ) {
																	setError(
																		cause.message
																	);
																}
															} }
														>
															{ __(
																'Approve this version',
																'ohmylms'
															) }
														</Button>
													)
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</Fragment>
					}
				/>
			) }
		</Modal>
	);
}
function BanksTab() {
	const [ banks, setBanks ] = useState( null );
	const [ name, setName ] = useState( '' );
	const [ visibility, setVisibility ] = useState( 'private' );
	const [ error, setError ] = useState( '' );
	const [ open, setOpen ] = useState( null );
	const load = () =>
		listBanks()
			.then( ( data ) => setBanks( data.banks || [] ) )
			.catch( ( cause ) => setError( cause.message ) );
	useEffect( () => {
		load();
	}, [] );
	async function create() {
		try {
			await createBank( {
				name,
				visibility,
			} );
			setName( '' );
			load();
		} catch ( cause ) {
			setError( cause.message );
		}
	}
	return (
		<Fragment>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div
				style={ {
					display: 'flex',
					gap: 12,
					alignItems: 'end',
				} }
			>
				<TextControl
					label={ __( 'New bank name', 'ohmylms' ) }
					value={ name }
					onChange={ setName }
				/>
				<SelectControl
					label={ __( 'Visibility', 'ohmylms' ) }
					value={ visibility }
					options={ [
						{
							value: 'private',
							label: __( 'Private', 'ohmylms' ),
						},
						{
							value: 'shared',
							label: __(
								'Shared with granted authors',
								'ohmylms'
							),
						},
						{
							value: 'site',
							label: __( 'Usable by all authors', 'ohmylms' ),
						},
					] }
					onChange={ setVisibility }
				/>
				<Button
					variant="primary"
					disabled={ ! name.trim() }
					onClick={ create }
				>
					{ __( 'Create bank', 'ohmylms' ) }
				</Button>
			</div>
			{ ! banks ? (
				<Spinner />
			) : (
				<ul>
					{ banks.map( ( bank ) => (
						<li key={ bank.id }>
							<strong>{ bank.name }</strong> · { bank.visibility }{ ' ' }
							<Button
								variant="link"
								onClick={ () => setOpen( bank ) }
							>
								{ __( 'Sharing', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			{ open && (
				<GrantsModal bank={ open } onClose={ () => setOpen( null ) } />
			) }
		</Fragment>
	);
}
function GrantsModal( { bank, onClose } ) {
	const [ grants, setGrants ] = useState( null );
	const [ userId, setUserId ] = useState( '' );
	const [ permission, setPermission ] = useState( 'use' );
	const [ error, setError ] = useState( '' );
	const load = useCallback(
		() =>
			listGrants( bank.id )
				.then( ( data ) => setGrants( data.grants || [] ) )
				.catch( ( cause ) => setError( cause.message ) ),
		[ bank.id ]
	);
	useEffect( () => {
		load();
	}, [ load ] );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: sharing mode label. */ __(
					'Sharing: %s',
					'ohmylms'
				),
				bank.name
			) }
			onRequestClose={ onClose }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				{ __(
					'use: place questions in quizzes · edit: change questions · approve: approve versions for shared use and practice',
					'ohmylms'
				) }
			</p>
			{ ! grants ? (
				<Spinner />
			) : (
				<ul>
					{ grants.map( ( grant ) => (
						<li key={ `${ grant.user_id }-${ grant.permission }` }>
							{ sprintf(
								/* translators: %1$s: user identifier; %2$s: user sharing role. */ __(
									'User #%1$s: %2$s',
									'ohmylms'
								),
								grant.user_id,
								grant.permission
							) }{ ' ' }
							<Button
								variant="link"
								isDestructive
								onClick={ () =>
									revokeBank( bank.id, {
										user_id: grant.user_id,
										permission: grant.permission,
									} ).then( load, ( cause ) =>
										setError( cause.message )
									)
								}
							>
								{ __( 'Revoke', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<div
				style={ {
					display: 'flex',
					gap: 12,
					alignItems: 'end',
				} }
			>
				<TextControl
					label={ __( 'Author user ID', 'ohmylms' ) }
					type="number"
					value={ userId }
					onChange={ setUserId }
				/>
				<SelectControl
					label={ __( 'Permission', 'ohmylms' ) }
					value={ permission }
					options={ [ 'use', 'edit', 'approve' ].map( ( value ) => ( {
						value,
						label: value,
					} ) ) }
					onChange={ setPermission }
				/>
				<Button
					variant="primary"
					disabled={ ! userId }
					onClick={ () =>
						grantBank( bank.id, {
							user_id: Number( userId ),
							permission,
						} ).then( load, ( cause ) => setError( cause.message ) )
					}
				>
					{ __( 'Grant', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}
