import {
	Button,
	Modal,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import {
	checkQuizFile,
	fileProblems,
	importQuizFile,
	isQuizReviewStale,
	MAX_QUIZ_NAME,
	problemText,
	quizFileProblem,
	type QuizImportResult,
} from './exchange';

/**
 * Quiz import: check an OhMyLMS quiz file (or the older CSV) without creating anything, show what it
 * holds or every problem, then create a new draft quiz from exactly the checked file and name.
 *
 * @param props            Component props.
 * @param props.onClose    Close the dialog.
 * @param props.onImported Receives the new quiz id.
 */
export function ImportQuizDialog( {
	onClose,
	onImported,
}: {
	onClose: () => void;
	onImported: ( id: number ) => void;
} ) {
	const [ content, setContent ] = useState( '' );
	const [ name, setName ] = useState( '' );
	const [ checked, setChecked ] = useState< {
		content: string;
		name: string;
		result: QuizImportResult;
	} | null >( null );
	const [ problems, setProblems ] = useState< string[] >( [] );
	const [ busy, setBusy ] = useState( false );
	const current =
		checked && checked.content === content && checked.name === name
			? checked.result
			: null;

	async function run( work: () => Promise< void > ) {
		setBusy( true );
		setProblems( [] );
		try {
			await work();
		} catch ( failure ) {
			const listed = fileProblems( failure ).map( problemText );
			if ( isQuizReviewStale( failure ) ) {
				setChecked( null );
			}
			setProblems(
				listed.length
					? listed
					: [ normalizeApiError( failure ).message ]
			);
		} finally {
			setBusy( false );
		}
	}

	function check() {
		const found = quizFileProblem( content, name );
		if ( found ) {
			setProblems( [ found ] );
			return;
		}
		void run( async () => {
			const result = await checkQuizFile( content, name );
			setChecked( { content, name, result } );
		} );
	}

	function create() {
		if ( ! current?.review ) {
			return;
		}
		const review = current.review;
		void run( async () => {
			const result = await importQuizFile( content, name, review );
			if ( result.id ) {
				onImported( result.id );
			}
		} );
	}

	return (
		<Modal
			title={ __( 'Import quiz', 'ohmylms' ) }
			onRequestClose={ () => ! busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ __(
					'An OhMyLMS quiz file (*.ohmylms.json) or the older quiz CSV. The file is checked first; importing always creates a new draft quiz and never changes an existing one.',
					'ohmylms'
				) }
			</p>
			<p>
				<label htmlFor="oml-quiz-import-file">
					{ __( 'Quiz file', 'ohmylms' ) }
				</label>{ ' ' }
				<input
					id="oml-quiz-import-file"
					type="file"
					accept=".json,.csv,application/json,text/csv"
					onChange={ ( event ) => {
						const file = event.target.files?.[ 0 ];
						if ( file ) {
							void file.text().then( setContent );
						}
					} }
				/>
			</p>
			<TextareaControl
				label={ __( 'Or paste the file', 'ohmylms' ) }
				value={ content }
				rows={ 6 }
				onChange={ setContent }
			/>
			<TextControl
				label={ __( 'Quiz name (optional)', 'ohmylms' ) }
				value={ name }
				maxLength={ MAX_QUIZ_NAME }
				help={ __(
					'Leave empty to use the name in the file. An existing name gets the import date added.',
					'ohmylms'
				) }
				onChange={ setName }
			/>
			{ problems.length > 0 && (
				<Notice status="error" isDismissible={ false }>
					<ul>
						{ problems.map( ( problem ) => (
							<li key={ problem }>{ problem }</li>
						) ) }
					</ul>
				</Notice>
			) }
			{ checked && ! current && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'The file or name changed after the check. Check it again.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ current && (
				<div role="status">
					<p>
						{ sprintf(
							/* translators: 1: quiz name, 2: questions, 3: points. */
							_n(
								'%1$s: %2$d question, %3$s points. Nothing was created yet.',
								'%1$s: %2$d questions, %3$s points. Nothing was created yet.',
								current.summary.questions,
								'ohmylms'
							),
							current.name,
							current.summary.questions,
							String( current.summary.points )
						) }
					</p>
					{ current.summary.randomized > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: randomized questions. */
								__(
									'%d questions use randomized numbers.',
									'ohmylms'
								),
								current.summary.randomized
							) }
						</p>
					) }
					{ current.warnings.length > 0 && (
						<ul aria-label={ __( 'Warnings', 'ohmylms' ) }>
							{ current.warnings.map( ( warning ) => (
								<li key={ warning }>{ warning }</li>
							) ) }
						</ul>
					) }
				</div>
			) }
			<Button
				variant="secondary"
				disabled={ busy }
				isBusy={ busy && ! current }
				onClick={ check }
			>
				{ __( 'Check file', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="primary"
				disabled={ busy || ! current }
				isBusy={ busy && !! current }
				onClick={ create }
			>
				{ __( 'Import as a new draft quiz', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
