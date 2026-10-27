import { createElement, Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { ImportDialog, IMPORT_TARGETS } from './ImportDialog.jsx';
import { ExamsPage } from './ExamsPage.jsx';
import { ImportIcon } from './QuizActions.jsx';
import { useHubAction } from './useHubAction.js';

/**
 * The Import button of the Quizzes, Question Bank, Exams, Curriculum and Skills screens, and of a
 * course. It opens the import dialog with what that screen makes already chosen. A PDF, or rows
 * made into an exam, continue in the review window that opens when the dialog closes.
 *
 * @param {Object}                      root0               Component properties.
 * @param {string}                      root0.target        questions, quiz or exam.
 * @param {string[]}                    root0.targets       The targets the dialog offers.
 * @param {boolean}                     root0.withSkills    Offer a skill choice.
 * @param {Object[]}                    root0.initialSkills Skills chosen when the dialog opens.
 * @param {(quiz: Object) => void|null} root0.onQuiz        Called with a quiz made from a quiz file.
 * @param {string}                      root0.variant       Button style.
 */
export function ImportButton( {
	target = 'questions',
	targets = IMPORT_TARGETS,
	withSkills = false,
	initialSkills = [],
	onQuiz = null,
	variant = 'secondary',
} ) {
	const [ open, setOpen ] = useState( false );
	const [ jobId, setJobId ] = useState( 0 );
	// Ctrl+K opens the tab's address with `?action=import`.
	useHubAction( { import: () => setOpen( true ) } );
	return (
		<Fragment>
			<Button
				variant={ variant }
				className={ variant === 'secondary' ? 'is-secondary' : '' }
				icon={ <ImportIcon /> }
				onClick={ () => setOpen( true ) }
			>
				{ __( 'Import', 'ohmylms' ) }
			</Button>
			{ open && (
				<ImportDialog
					initialTarget={ target }
					targets={ targets }
					withSkills={ withSkills }
					initialSkills={ initialSkills }
					onQuiz={ onQuiz }
					onClose={ () => setOpen( false ) }
					onJob={ ( id ) => {
						setOpen( false );
						setJobId( id );
					} }
				/>
			) }
			{ jobId > 0 && (
				<ExamsPage
					reviewOnly
					jobId={ jobId }
					onClosed={ () => setJobId( 0 ) }
				/>
			) }
		</Fragment>
	);
}
