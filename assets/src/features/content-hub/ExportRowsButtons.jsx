import { createElement, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { exportQuestionRows } from './api.mjs';
import { downloadText } from './quizExchange.mjs';

/**
 * Download questions as import rows, CSV or JSON, for every question type. The file imports back through
 * the Question Bank Import button.
 *
 * @param {Object}              root0              Component properties.
 * @param {number}              root0.quizId       Export this quiz's questions in order, with sections.
 * @param {number[]}            root0.ids          Or export these questions.
 * @param {string}              root0.label        Exam name written into every row.
 * @param {() => Promise<void>} root0.beforeExport Called first, for example to save unsaved edits.
 */
export function ExportRowsButtons( {
	quizId = 0,
	ids = [],
	label = '',
	beforeExport = null,
} ) {
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ done, setDone ] = useState( null );
	const run = async ( format ) => {
		setBusy( format );
		setError( '' );
		setDone( null );
		try {
			if ( beforeExport ) {
				await beforeExport();
			}
			const file = await exportQuestionRows( {
				quizId,
				ids,
				format,
				label,
			} );
			downloadText( file.filename, file.mime, file.content );
			setDone( file );
		} catch ( failure ) {
			setError(
				failure.message ||
					__( 'The questions could not be exported.', 'ohmylms' )
			);
		} finally {
			setBusy( '' );
		}
	};
	const empty = ! quizId && ! ids.length;
	return (
		<>
			{ [ 'csv', 'json' ].map( ( format ) => (
				<Button
					key={ format }
					variant="tertiary"
					isBusy={ busy === format }
					disabled={ !! busy || empty }
					onClick={ () => run( format ) }
				>
					{ format === 'csv'
						? __( 'Export questions (CSV)', 'ohmylms' )
						: __( 'Export questions (JSON)', 'ohmylms' ) }
				</Button>
			) ) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ done && (
				<Notice
					status={ done.warnings.length ? 'warning' : 'success' }
					onRemove={ () => setDone( null ) }
				>
					{ sprintf(
						/* translators: %d: number of questions exported. */
						_n(
							'%d question exported.',
							'%d questions exported.',
							done.count,
							'ohmylms'
						),
						done.count
					) }{ ' ' }
					{ done.warnings.join( ' ' ) }
				</Notice>
			) }
		</>
	);
}
