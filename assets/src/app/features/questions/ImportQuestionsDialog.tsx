import { Table } from '../../../ui/Table';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import {
	applyImport,
	canImport,
	checkImport,
	importProblem,
	isReviewStale,
	MAX_EXCHANGE_ROWS,
	rowStatusLabel,
	type ImportFormat,
	type ImportReport,
} from './exchange';

/**
 * Question import: check a CSV or JSON file (nothing is written), review every row, then import
 * exactly the checked file. Rows are matched to earlier imports by exam, paper, year, section,
 * number and language, so importing the same file again leaves the questions unchanged.
 *
 * @param props            Component props.
 * @param props.onClose    Close the dialog.
 * @param props.onImported Called after questions were written.
 */
export function ImportQuestionsDialog( {
	onClose,
	onImported,
}: {
	onClose: () => void;
	onImported: ( report: ImportReport ) => void;
} ) {
	const [ text, setText ] = useState( '' );
	const [ format, setFormat ] = useState< ImportFormat >( 'auto' );
	const [ problem, setProblem ] = useState< string | null >( null );
	const [ checked, setChecked ] = useState< {
		text: string;
		format: ImportFormat;
		report: ImportReport;
	} | null >( null );
	const [ result, setResult ] = useState< ImportReport | null >( null );
	const [ skipInvalid, setSkipInvalid ] = useState( false );
	const [ approve, setApprove ] = useState( false );
	const [ stale, setStale ] = useState( false );
	const operation = useOperation();
	const current =
		checked && checked.text === text && checked.format === format
			? checked.report
			: null;
	const shown = result ?? current;

	function check() {
		const found = importProblem( text );
		setProblem( found );
		setResult( null );
		setStale( false );
		if ( found ) {
			return;
		}
		void operation.run(
			( signal ) => checkImport( text, format, signal ),
			( report ) => setChecked( { text, format, report } )
		);
	}

	function apply() {
		if ( ! current?.review ) {
			return;
		}
		const review = current.review;
		void operation.run(
			async ( signal ) => {
				try {
					return await applyImport(
						text,
						format,
						review,
						{ skip_invalid: skipInvalid, approve },
						signal
					);
				} catch ( failure ) {
					if ( isReviewStale( failure ) ) {
						setChecked( null );
						setStale( true );
					}
					throw failure;
				}
			},
			( report ) => {
				setChecked( null );
				setResult( report );
				if ( report.committed ) {
					onImported( report );
				}
			}
		);
	}

	return (
		<Modal
			title={ __( 'Import questions', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ sprintf(
					/* translators: %d: maximum rows. */
					__(
						'CSV with a header row, or JSON rows (up to %d). Each row needs a question, a type and at least one skill. Check the file first; nothing is saved until you import the checked file.',
						'ohmylms'
					),
					MAX_EXCHANGE_ROWS
				) }
			</p>
			<TextareaControl
				label={ __( 'Rows', 'ohmylms' ) }
				value={ text }
				rows={ 8 }
				help={ problem ?? undefined }
				onChange={ ( value ) => {
					setText( value );
					setResult( null );
				} }
			/>
			<p>
				<label htmlFor="oml-question-import-file">
					{ __( 'Or choose a file', 'ohmylms' ) }
				</label>{ ' ' }
				<input
					id="oml-question-import-file"
					type="file"
					accept=".csv,.json,text/csv,application/json"
					onChange={ ( event ) => {
						const file = event.target.files?.[ 0 ];
						if ( file ) {
							void file.text().then( ( value ) => {
								setText( value );
								setResult( null );
							} );
						}
					} }
				/>
			</p>
			<SelectControl< ImportFormat >
				label={ __( 'Format', 'ohmylms' ) }
				value={ format }
				options={ [
					{
						value: 'auto',
						label: __( 'Detect automatically', 'ohmylms' ),
					},
					{ value: 'csv', label: 'CSV' },
					{ value: 'json', label: 'JSON' },
				] }
				onChange={ setFormat }
			/>
			<Button
				variant="secondary"
				disabled={ operation.busy }
				isBusy={ operation.busy && ! current }
				onClick={ check }
			>
				{ __( 'Check file', 'ohmylms' ) }
			</Button>
			{ stale && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The questions or the file changed since the check. Check the file again before importing.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ operation.error && ! stale && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ checked && ! current && ! result && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'The rows changed after the check. Check them again.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ shown && (
				<>
					<p role="status">
						{ shown.dry_run
							? sprintf(
									/* translators: 1: new, 2: to update, 3: unchanged, 4: errors. */
									__(
										'Check: %1$d new, %2$d to update, %3$d unchanged, %4$d with errors. Nothing was saved.',
										'ohmylms'
									),
									shown.counts.create,
									shown.counts.update,
									shown.counts.unchanged,
									shown.counts.error
								)
							: sprintf(
									/* translators: 1: new, 2: updated, 3: unchanged, 4: errors. */
									__(
										'Import: %1$d new, %2$d updated, %3$d unchanged, %4$d with errors.',
										'ohmylms'
									),
									shown.counts.create,
									shown.counts.update,
									shown.counts.unchanged,
									shown.counts.error
								) }
						{ ! shown.dry_run &&
							shown.approved > 0 &&
							' ' +
								sprintf(
									/* translators: %d: approved questions. */
									__( '%d approved.', 'ohmylms' ),
									shown.approved
								) }
					</p>
					{ shown.stopped && (
						<Notice status="warning" isDismissible={ false }>
							{ shown.stopped }
						</Notice>
					) }
					<Table
						caption={
							shown.dry_run
								? __( 'Checked rows', 'ohmylms' )
								: __( 'Imported rows', 'ohmylms' )
						}
					>
						<thead>
							<tr>
								<th scope="col">{ __( 'Row', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Result', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Problems and notes', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ shown.rows.map( ( row ) => (
								<tr key={ row.row }>
									<th scope="row">{ row.row }</th>
									<td>{ rowStatusLabel( row.status ) }</td>
									<td>
										{ [
											...row.errors,
											...row.warnings,
										].join( ' ' ) || '—' }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
				</>
			) }
			{ current && (
				<>
					{ current.counts.error > 0 && (
						<CheckboxControl
							label={ __(
								'Import the valid rows and skip rows with errors',
								'ohmylms'
							) }
							checked={ skipInvalid }
							onChange={ setSkipInvalid }
						/>
					) }
					<CheckboxControl
						label={ __(
							'Approve the imported questions for shared use',
							'ohmylms'
						) }
						checked={ approve }
						onChange={ setApprove }
					/>
					<Button
						variant="primary"
						disabled={
							operation.busy ||
							! canImport( current, skipInvalid )
						}
						isBusy={ operation.busy }
						onClick={ apply }
					>
						{ __( 'Import checked rows', 'ohmylms' ) }
					</Button>{ ' ' }
				</>
			) }
			<Button
				variant="tertiary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ result
					? __( 'Close', 'ohmylms' )
					: __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
