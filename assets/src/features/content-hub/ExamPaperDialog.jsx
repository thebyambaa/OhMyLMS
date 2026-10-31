import {
	createElement,
	useEffect,
	useRef,
	useState,
	RawHTML,
} from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	approveQuestion,
	loadBankQuestion,
	loadVersion,
	listSkills,
} from '../question-bank/api.mjs';
import { loadMath } from '../math/loader.mjs';

/**
 * Render sanitized question HTML in an isolated region owned by the math runtime.
 * @param {Object}  props       Properties.
 * @param {string}  props.html  Sanitized stored question HTML.
 * @param {boolean} props.plain Whether the field is plain text.
 */
function PaperContent( { html = '', plain = false } ) {
	const host = useRef( null );
	useEffect( () => {
		let active = true;
		let cleanup;
		loadMath()
			.then( ( runtime ) => {
				if ( active ) {
					cleanup = runtime.mount( host.current );
				}
			} )
			.catch( () => {} );
		return () => {
			active = false;
			cleanup?.();
		};
	}, [ html, plain ] );
	return (
		<div ref={ host } data-ohmylms-latex>
			{ plain ? (
				<span key={ html }>{ html }</span>
			) : (
				<RawHTML key={ html }>{ html }</RawHTML>
			) }
		</div>
	);
}

/**
 * Review the exact saved paper versions without leaving Exams.
 * @param {Object}     props         Properties.
 * @param {Object}     props.exam    Saved exam and pinned version IDs.
 * @param {() => void} props.onClose Close and restore focus.
 */
export function ExamPaperDialog( { exam, onClose } ) {
	const [ selected, setSelected ] = useState( 0 );
	const [ entries, setEntries ] = useState( {} );
	const [ skills, setSkills ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ mode, setMode ] = useState( 'review' );
	const [ showSource, setShowSource ] = useState( false );
	const questionId = exam.question_ids[ selected ];
	const versionId = exam.version_ids[ questionId ];
	const entry = entries[ questionId ];
	useEffect( () => {
		let active = true;
		listSkills()
			.then( ( data ) => {
				if ( active ) {
					setSkills( data.skills || [] );
				}
			} )
			.catch( () => {} );
		return () => {
			active = false;
		};
	}, [] );
	useEffect( () => {
		let active = true;
		setError( '' );
		Promise.all( [
			loadVersion( questionId, versionId ),
			loadBankQuestion( questionId ),
		] )
			.then( ( [ snapshot, detail ] ) => {
				if ( active ) {
					setEntries( ( previous ) => ( {
						...previous,
						[ questionId ]: { snapshot, detail },
					} ) );
				}
			} )
			.catch( ( failure ) => {
				if ( active ) {
					setError( failure.message );
				}
			} );
		return () => {
			active = false;
		};
	}, [ questionId, versionId ] );
	const approve = async () => {
		setBusy( true );
		setError( '' );
		try {
			await approveQuestion( questionId, versionId );
			const detail = await loadBankQuestion( questionId );
			setEntries( ( previous ) => ( {
				...previous,
				[ questionId ]: { ...previous[ questionId ], detail },
			} ) );
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusy( false );
		}
	};
	const approved =
		Number( entry?.detail.approved_version_id ) === Number( versionId );
	const page = Object.values( exam.source_pages || {} )[ selected ] || 1;
	const source = `${ window.location.origin }/?ohmylms_import_source=${ exam.job_id }#page=${ page }`;
	return (
		<Modal
			title={ __( 'Question paper', 'ohmylms' ) }
			className="ohmylms-paper-dialog"
			size="fill"
			onRequestClose={ busy ? () => {} : onClose }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				<strong>{ exam.title }</strong> ·{ ' ' }
				{ sprintf(
					/* translators: 1: question count, 2: marks, 3: minutes. */ __(
						'%1$d questions · %2$d marks · %3$d minutes',
						'ohmylms'
					),
					exam.question_ids.length,
					exam.total_marks,
					exam.duration_minutes
				) }
			</p>
			<div className="ohmylms-paper-actions">
				<Button
					variant={ mode === 'review' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'review' ) }
				>
					{ __( 'Review questions', 'ohmylms' ) }
				</Button>
				<Button
					variant={ mode === 'preview' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'preview' ) }
				>
					{ __( 'Try exam', 'ohmylms' ) }
				</Button>
			</div>
			{ mode === 'preview' ? (
				<>
					<p>
						{ __(
							'Preview responses are not recorded. Close this dialog to return to Exams.',
							'ohmylms'
						) }
					</p>
					<iframe
						className="ohmylms-paper-player"
						src={ exam.preview_url }
						title={ __( 'Exam preview', 'ohmylms' ) }
					/>
				</>
			) : (
				<>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<nav
						className="ohmylms-paper-questions"
						aria-label={ __( 'Paper questions', 'ohmylms' ) }
					>
						{ exam.question_ids.map( ( id, index ) => (
							<Button
								key={ id }
								variant={
									index === selected ? 'primary' : 'secondary'
								}
								aria-current={
									index === selected ? 'step' : undefined
								}
								disabled={ busy }
								onClick={ () => setSelected( index ) }
							>
								{ index + 1 }
							</Button>
						) ) }
					</nav>
					<div
						className={ `ohmylms-paper-columns${ showSource ? ' has-source' : '' }` }
					>
						<section
							aria-label={ __(
								'Original question paper',
								'ohmylms'
							) }
						>
							<p>
								{ __(
									'Check diagrams and tables against the original paper.',
									'ohmylms'
								) }{ ' ' }
								<a
									href={ source }
									target="_blank"
									rel="noreferrer"
								>
									{ __( 'Open source PDF', 'ohmylms' ) }
								</a>
							</p>
							<ToggleControl
								label={ __(
									'Show source PDF alongside questions',
									'ohmylms'
								) }
								checked={ showSource }
								onChange={ setShowSource }
								help={ __(
									'If your browser cannot display embedded PDFs, use Open source PDF.',
									'ohmylms'
								) }
							/>
							{ showSource && (
								<iframe
									key={ source }
									src={ source }
									title={ __(
										'Original question paper',
										'ohmylms'
									) }
								/>
							) }
						</section>
						<section
							aria-label={ __(
								'Saved question version',
								'ohmylms'
							) }
							aria-busy={ ! entry }
						>
							{ ! entry ? (
								<Spinner />
							) : (
								<>
									<h2>{ entry.snapshot.title }</h2>
									<p role="status">
										{ sprintf(
											/* translators: 1: saved version, 2: approval status. */ __(
												'Version %1$d · %2$s',
												'ohmylms'
											),
											entry.snapshot.version_no,
											approved
												? __( 'Approved', 'ohmylms' )
												: __(
														'Needs approval',
														'ohmylms'
													)
										) }
									</p>
									<PaperContent
										html={ entry.snapshot.body }
									/>
									{ (
										entry.snapshot.settings?.parts || []
									).map( ( part ) => (
										<section
											className="ohmylms-paper-part"
											key={ part.id }
										>
											<h3>
												{ part.label } ·{ ' ' }
												{ sprintf(
													/* translators: %s: available marks. */ _n(
														'%s mark',
														'%s marks',
														Number( part.marks ),
														'ohmylms'
													),
													part.marks
												) }
											</h3>
											<PaperContent
												plain
												html={ part.prompt }
											/>
											<p>
												{ __( 'Skills:', 'ohmylms' ) }{ ' ' }
												{ (
													entry.snapshot.skills || []
												)
													.filter(
														( skill ) =>
															skill.part_id ===
															part.id
													)
													.map(
														( skill ) =>
															skills.find(
																( term ) =>
																	Number(
																		term.id
																	) ===
																	Number(
																		skill.term_id
																	)
															)?.name ||
															`#${ skill.term_id }`
													)
													.join( ', ' ) ||
													__(
														'No part skills assigned',
														'ohmylms'
													) }
											</p>
											<details>
												<summary>
													{ __(
														'Answer and marking notes',
														'ohmylms'
													) }
												</summary>
												<PaperContent
													plain
													html={ String(
														part.answer ??
															part.answers?.join(
																', '
															) ??
															part.accepted?.join(
																', '
															) ??
															''
													) }
												/>
												<PaperContent
													plain
													html={ part.rubric || '' }
												/>
											</details>
										</section>
									) ) }
									<Button
										variant="primary"
										disabled={
											busy ||
											approved ||
											! entry.detail.can_approve
										}
										isBusy={ busy }
										onClick={ approve }
									>
										{ approved
											? __(
													'Version approved',
													'ohmylms'
												)
											: __(
													'Approve this question',
													'ohmylms'
												) }
									</Button>
									<p>
										{ __(
											'Approval applies to this saved version. Publishing the exam remains a separate action.',
											'ohmylms'
										) }
									</p>
								</>
							) }
						</section>
					</div>
					<div className="ohmylms-paper-actions">
						<Button
							variant="secondary"
							disabled={ busy || selected === 0 }
							onClick={ () => setSelected( selected - 1 ) }
						>
							{ __( 'Previous question', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={
								busy ||
								selected === exam.question_ids.length - 1
							}
							onClick={ () => setSelected( selected + 1 ) }
						>
							{ __( 'Next question', 'ohmylms' ) }
						</Button>
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ onClose }
						>
							{ __( 'Done', 'ohmylms' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
