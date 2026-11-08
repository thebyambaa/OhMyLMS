import { createElement, useEffect, useId, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';
import { editPath, importQuiz } from './api.mjs';
import { downloadText, exampleFile, problemLines } from './quizExchange.mjs';
import { CSV_TEMPLATE, ImportPreview } from './QuizActions.jsx';
import { SkillPicker } from './SkillPicker';
import { request } from '../rest.mjs';

/** @typedef {'questions'|'quiz'|'exam'} ImportTarget */
/** @typedef {import('./api.mjs').QuizImport} QuizImport */
/** @typedef {import('./quizExchange.mjs').RestFailure} RestFailure */
/** @typedef {import('./SkillPicker.jsx').PickedSkill} PickedSkill */

/**
 * @typedef {Object} ImportReport Response of the question file import route.
 * @property {{create?: number, update?: number, unchanged?: number, error?: number}} [counts] Row counts by outcome.
 * @property {Array<{row: number, status: string, id?: number, errors?: string[]}>}   [rows]   Rows.
 */

/** @type {ImportTarget[]} */
export const IMPORT_TARGETS = [ 'questions', 'quiz', 'exam' ];

/** The target offered inside the quiz editor: add the questions to the open quiz or exam. */
export const INTO_TARGET = 'into';

const targetInfo = () => ( {
	questions: {
		label: __( 'Questions', 'ohmylms' ),
		help: __(
			'Add questions to the Question Bank. Use them in any quiz later.',
			'ohmylms'
		),
	},
	quiz: {
		label: __( 'Quiz', 'ohmylms' ),
		help: __( 'Create a draft quiz with its questions.', 'ohmylms' ),
	},
	exam: {
		label: __( 'Exam', 'ohmylms' ),
		help: __(
			'Create a timed exam on the quiz player, with marking and a mark scheme.',
			'ohmylms'
		),
	},
	into: {
		label: __( 'This assessment', 'ohmylms' ),
		help: __(
			'Add the questions to the end of the quiz or exam you are editing.',
			'ohmylms'
		),
	},
} );

/**
 * @param {string}                  path
 * @param {Record<string, unknown>} data
 */
const api = ( path, data ) =>
	request( {
		path: `/ohmylms/v1/${ path }`,
		method: 'POST',
		data,
	} );

/**
 * The kind of file chosen: pdf, csv, json, or text (anything else a question file may be).
 * @param {File} file The chosen file.
 */
const kindOf = ( file ) => {
	const extension = /** @type {string} */ (
		( file?.name || '' ).split( '.' ).pop()
	).toLowerCase();
	return [ 'pdf', 'csv', 'json' ].includes( extension ) ? extension : 'text';
};

/** @param {RestFailure|null} [failure] */
const messages = ( failure ) => {
	const lines = problemLines( failure );
	if ( lines.length ) {
		return lines;
	}
	return [ failure?.message || __( 'Something went wrong.', 'ohmylms' ) ];
};

/** @param {File|null} file */
const baseName = ( file ) => ( file?.name || '' ).replace( /\.[^.]+$/, '' );

/**
 * What a question file check found: counts, then the first problems.
 * @param {Object}       root0        Component properties.
 * @param {ImportReport} root0.report Response of the import route.
 * @param {boolean}      root0.done   Whether the import has been done.
 */
function CheckReport( { report, done } ) {
	const counts = report.counts || {};
	const problems = ( report.rows || [] )
		.filter( ( row ) => row.status === 'error' )
		.slice( 0, 10 );
	return (
		<div role="status">
			<p>
				<strong>
					{ done
						? __( 'Import finished.', 'ohmylms' )
						: __( 'Checked. Nothing is imported yet.', 'ohmylms' ) }
				</strong>{ ' ' }
				{ sprintf(
					/* translators: 1: new questions, 2: updated, 3: unchanged, 4: rows with errors. */
					__(
						'%1$d new, %2$d updated, %3$d unchanged, %4$d with errors.',
						'ohmylms'
					),
					counts.create || 0,
					counts.update || 0,
					counts.unchanged || 0,
					counts.error || 0
				) }
			</p>
			{ problems.length > 0 && (
				<ul className="ohmylms-import-list">
					{ problems.map( ( row ) => (
						<li key={ row.row }>
							{ sprintf(
								/* translators: 1: row number, 2: problems. */
								__( 'Row %1$d: %2$s', 'ohmylms' ),
								row.row,
								( row.errors || [] ).join( ' ' )
							) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * One dialog to import a PDF or a CSV/JSON file as questions, a quiz or an exam.
 *
 * Choose what the file becomes, then the file. PDFs are read and then reviewed for every target
 * (`onJob` is told which import to open). Question files are checked first and nothing is written
 * until Import is pressed. A quiz file makes a draft quiz (`onQuiz` receives it, otherwise its
 * editor opens). With `withSkills`, chosen skills are given to every row that names none.
 * With `onQuestions`, the only target is the open assessment: a question file's questions are passed
 * to it at once, and a PDF's after review. `secure` keeps them exam-only, as an exam import does.
 *
 * @param {Object}                                       root0                 Component properties.
 * @param {ImportTarget}                                 [root0.initialTarget] questions, quiz or exam.
 * @param {ImportTarget[]}                               [root0.targets]       The targets offered.
 * @param {boolean}                                      [root0.withSkills]    Offer a skill choice.
 * @param {PickedSkill[]}                                [root0.initialSkills] Skills chosen when the dialog opens.
 * @param {() => void}                                   root0.onClose         Close the dialog.
 * @param {(jobId: number) => void}                      root0.onJob           An import job to review was made.
 * @param {((ids: number[]) => void|Promise<void>)|null} [root0.onQuestions]   Imported question IDs.
 * @param {((quiz: QuizImport) => void)|null}            [root0.onQuiz]        A quiz was made from a quiz file.
 * @param {boolean}                                      [root0.secure]        Mark imported questions exam-only.
 */
export function ImportDialog( {
	initialTarget = 'questions',
	targets = IMPORT_TARGETS,
	withSkills = false,
	initialSkills = [],
	onClose,
	onJob,
	onQuiz = null,
	onQuestions = null,
	secure = false,
} ) {
	const offered = onQuestions ? [ INTO_TARGET ] : targets;
	const [ target, setTarget ] = useState(
		offered.includes( initialTarget ) ? initialTarget : offered[ 0 ]
	);
	// The job target that creates bank questions without making a quiz or an exam.
	const jobTarget = secure ? 'exam' : 'questions';
	const [ file, setFile ] = useState( /** @type {File|null} */ ( null ) );
	const [ scheme, setScheme ] = useState( /** @type {File|null} */ ( null ) );
	const [ text, setText ] = useState( '' );
	const [ name, setName ] = useState( '' );
	const [ approve, setApprove ] = useState( false );
	const [ skipInvalid, setSkipInvalid ] = useState( false );
	const [ skills, setSkills ] = useState( initialSkills );
	const [ picking, setPicking ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ problems, setProblems ] = useState(
		/** @type {string[]} */ ( [] )
	);
	const [ report, setReport ] = useState(
		/** @type {ImportReport|null} */ ( null )
	);
	const [ quizPreview, setQuizPreview ] = useState(
		/** @type {QuizImport|null} */ ( null )
	);
	const [ quizResult, setQuizResult ] = useState(
		/** @type {QuizImport|null} */ ( null )
	);
	const [ finished, setFinished ] = useState( false );
	const radioId = useId();
	const fileId = useId();
	const schemeId = useId();
	const nameId = useId();
	const info = targetInfo();
	const infoById =
		/** @type {Record<string, {label: string, help: string}>} */ ( info );
	const kind = file ? kindOf( file ) : '';
	const format = kind === 'csv' || kind === 'json' ? kind : 'auto';
	const defaultSkills = skills
		.map( ( skill ) => `#${ skill.id }` )
		.join( ';' );

	// Read a question file and check it whenever the file, its target or the skills change.
	useEffect( () => {
		let current = true;
		setText( '' );
		setReport( null );
		setQuizPreview( null );
		setProblems( [] );
		setFinished( false );
		if ( ! file || kind === 'pdf' ) {
			return undefined;
		}
		setBusy( true );
		file.text()
			.then( async ( content ) => {
				if ( ! current ) {
					return;
				}
				setText( content );
				if ( target === 'quiz' ) {
					setQuizPreview(
						await importQuiz( '', content, undefined, {
							dryRun: true,
						} )
					);
				} else {
					setReport(
						/** @type {ImportReport} */ (
							await api( 'question-bank/import', {
								text: content,
								format,
								dry_run: true,
								default_skills: defaultSkills,
							} )
						)
					);
				}
			} )
			.catch(
				( failure ) => current && setProblems( messages( failure ) )
			)
			.finally( () => current && setBusy( false ) );
		return () => {
			current = false;
		};
	}, [ file, target, kind, format, defaultSkills ] );

	const failed = report && ( report.counts?.error ?? 0 ) > 0;
	const ready =
		!! file &&
		! busy &&
		( kind === 'pdf' ||
			( target === 'quiz' ? !! quizPreview : !! report ) ) &&
		( ! failed || skipInvalid || target === 'quiz' );

	/** @param {QuizImport} quiz */
	const finishQuiz = ( quiz ) => {
		if ( onQuiz ) {
			onQuiz( quiz );
			onClose();
		} else {
			window.location.hash = `#${ editPath( 'quiz', quiz.id ) }`;
			onClose();
		}
	};

	/** @param {import('react').FormEvent} event */
	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setProblems( [] );
		try {
			if ( kind === 'pdf' ) {
				const body = new FormData();
				body.append( 'file', /** @type {File} */ ( file ) );
				body.append(
					'target',
					target === INTO_TARGET ? jobTarget : target
				);
				body.append( 'default_skills', defaultSkills );
				if ( scheme ) {
					body.append( 'mark_scheme', scheme );
				}
				const job = /** @type {{id: number}} */ (
					await request( {
						path: '/ohmylms/v1/exam-imports',
						method: 'POST',
						body,
					} )
				);
				onJob( job.id );
			} else if ( target === 'quiz' ) {
				const quiz = await importQuiz( name.trim(), text );
				if ( quiz.warnings?.length ) {
					setQuizResult( quiz );
				} else {
					finishQuiz( quiz );
				}
			} else if ( target === INTO_TARGET ) {
				const job = await api( 'exam-imports/rows', {
					text,
					format,
					target: jobTarget,
					name: baseName( file ),
					skip_invalid: skipInvalid,
					default_skills: defaultSkills,
				} );
				onQuestions?.(
					/** @type {{question_ids?: number[]}} */ ( job )
						.question_ids || []
				);
				onClose();
			} else if ( target === 'exam' ) {
				const job = /** @type {{id: number}} */ (
					await api( 'exam-imports/rows', {
						text,
						format,
						target: 'exam',
						name: baseName( file ),
						skip_invalid: skipInvalid,
						default_skills: defaultSkills,
					} )
				);
				onJob( job.id );
			} else {
				const imported = /** @type {ImportReport} */ (
					await api( 'question-bank/import', {
						text,
						format,
						dry_run: false,
						approve,
						skip_invalid: skipInvalid,
						default_skills: defaultSkills,
					} )
				);
				setReport( imported );
				if ( onQuestions ) {
					await onQuestions(
						( imported.rows || [] )
							.filter(
								( row ) => row.status !== 'error' && row.id
							)
							.map( ( row ) => /** @type {number} */ ( row.id ) )
					);
					onClose();
				}
				setFinished( true );
			}
		} catch ( failure ) {
			setProblems( messages( /** @type {RestFailure} */ ( failure ) ) );
		}
		setBusy( false );
	};

	if ( quizResult ) {
		return (
			<Modal
				title={ __( 'Import', 'ohmylms' ) }
				onRequestClose={ onClose }
			>
				<p>
					{ sprintf(
						/* translators: 1: quiz name, 2: number of questions */
						_n(
							'"%1$s" was imported as a draft with %2$d question.',
							'"%1$s" was imported as a draft with %2$d questions.',
							quizResult.summary.questions,
							'ohmylms'
						),
						quizResult.name,
						quizResult.summary.questions
					) }
				</p>
				<Notice status="warning" isDismissible={ false }>
					<ul className="ohmylms-import-list">
						{ quizResult.warnings.map( ( warning ) => (
							<li key={ warning }>{ warning }</li>
						) ) }
					</ul>
				</Notice>
				<Button
					variant="primary"
					onClick={ () => finishQuiz( quizResult ) }
				>
					{ onQuiz
						? __( 'Done', 'ohmylms' )
						: __( 'Open quiz', 'ohmylms' ) }
				</Button>
			</Modal>
		);
	}

	return (
		<Modal
			title={ __( 'Import', 'ohmylms' ) }
			onRequestClose={ busy ? () => {} : onClose }
			className="ohmylms-exams ohmylms-import"
		>
			<p>
				{ __(
					'Bring in a PDF or a CSV or JSON file. Choose what it becomes, then choose the file.',
					'ohmylms'
				) }
			</p>
			{ problems.length > 0 && (
				<Notice status="error" isDismissible={ false }>
					<ul className="ohmylms-import-list">
						{ problems.map( ( line, index ) => (
							<li key={ index }>{ line }</li>
						) ) }
					</ul>
				</Notice>
			) }
			<form onSubmit={ submit }>
				<fieldset>
					<legend>{ __( 'Import as', 'ohmylms' ) }</legend>
					{ offered.map( ( id ) => (
						<label
							key={ id }
							htmlFor={ `${ radioId }-${ id }` }
							className="ohmylms-import-target"
						>
							<input
								id={ `${ radioId }-${ id }` }
								type="radio"
								name={ `${ radioId }-target` }
								value={ id }
								checked={ target === id }
								onChange={ () => setTarget( id ) }
							/>{ ' ' }
							<strong>{ infoById[ id ].label }</strong> —{ ' ' }
							{ infoById[ id ].help }
						</label>
					) ) }
				</fieldset>
				{ withSkills && (
					<p>
						<Button
							variant="secondary"
							onClick={ () => setPicking( true ) }
						>
							{ skills.length
								? sprintf(
										/* translators: %d: number of chosen skills. */
										_n(
											'%d skill chosen',
											'%d skills chosen',
											skills.length,
											'ohmylms'
										),
										skills.length
									)
								: __( 'Choose skills', 'ohmylms' ) }
						</Button>{ ' ' }
						{ skills.length > 0 && (
							<span>
								{ skills
									.map( ( skill ) => skill.name )
									.join( ', ' ) }
							</span>
						) }
						<br />
						<small>
							{ __(
								'Questions in the file that name no skill get these skills.',
								'ohmylms'
							) }
						</small>
					</p>
				) }
				<p>
					<label htmlFor={ fileId }>
						{ target === 'exam'
							? __( 'Exam paper or question file', 'ohmylms' )
							: __( 'PDF or question file', 'ohmylms' ) }
						<br />
						<input
							id={ fileId }
							type="file"
							accept=".pdf,.csv,.json,.txt,application/pdf,text/csv,application/json,text/plain"
							onChange={ ( event ) =>
								setFile(
									/** @type {FileList} */ (
										event.target.files
									)[ 0 ] || null
								)
							}
						/>
					</label>
				</p>
				{ target === 'quiz' && ! file && (
					<p>
						<Button
							variant="link"
							onClick={ () =>
								downloadText(
									'example.ohmylms.json',
									'application/json',
									exampleFile()
								)
							}
						>
							{ __( 'Download an example quiz file', 'ohmylms' ) }
						</Button>
						{ ' · ' }
						<Button
							variant="link"
							onClick={ () =>
								downloadText(
									'quiz-template.csv',
									'text/csv',
									CSV_TEMPLATE
								)
							}
						>
							{ __( 'Simple CSV template', 'ohmylms' ) }
						</Button>
					</p>
				) }
				{ kind === 'pdf' && (
					<>
						<p>
							<label htmlFor={ schemeId }>
								{ __(
									'Mark scheme PDF (optional)',
									'ohmylms'
								) }
								<br />
								<input
									id={ schemeId }
									type="file"
									accept="application/pdf,.pdf"
									onChange={ ( event ) =>
										setScheme(
											/** @type {FileList} */ (
												event.target.files
											)[ 0 ] || null
										)
									}
								/>
							</label>
						</p>
						<p>
							{ __(
								'The PDF is read by your configured AI provider, then you review every question before anything is saved.',
								'ohmylms'
							) }
						</p>
					</>
				) }
				{ target === 'quiz' && kind && kind !== 'pdf' && (
					<>
						<p>
							<label htmlFor={ nameId }>
								{ __( 'Quiz title (optional)', 'ohmylms' ) }
								<br />
								<input
									id={ nameId }
									value={ name }
									onChange={ ( event ) =>
										setName( event.target.value )
									}
								/>
							</label>
						</p>
						{ quizPreview && (
							<ImportPreview preview={ quizPreview } />
						) }
					</>
				) }
				{ target !== 'quiz' && report && (
					<CheckReport report={ report } done={ finished } />
				) }
				{ target !== 'quiz' && kind && kind !== 'pdf' && (
					<>
						{ target === 'questions' && (
							<CheckboxControl
								label={ __(
									'Approve the questions so they can be used straight away',
									'ohmylms'
								) }
								checked={ approve }
								onChange={ setApprove }
							/>
						) }
						<CheckboxControl
							label={ __(
								'Skip rows with errors and import the rest',
								'ohmylms'
							) }
							checked={ skipInvalid }
							onChange={ setSkipInvalid }
						/>
					</>
				) }
				<p>
					{ finished ? (
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Done', 'ohmylms' ) }
						</Button>
					) : (
						<>
							<Button variant="tertiary" onClick={ onClose }>
								{ __( 'Cancel', 'ohmylms' ) }
							</Button>
							<Button
								variant="primary"
								type="submit"
								disabled={ ! ready }
							>
								{ kind === 'pdf'
									? __( 'Upload and read PDF', 'ohmylms' )
									: __( 'Import', 'ohmylms' ) }
							</Button>
						</>
					) }
					{ busy && <Spinner /> }
				</p>
			</form>
			{ picking && (
				<SkillPicker
					title={ __( 'Skills for the imported items', 'ohmylms' ) }
					confirmLabel={ __( 'Use these skills', 'ohmylms' ) }
					initial={ skills }
					onConfirm={ ( chosen ) => {
						setSkills( chosen );
						setPicking( false );
					} }
					onClose={ () => setPicking( false ) }
				/>
			) }
		</Modal>
	);
}
