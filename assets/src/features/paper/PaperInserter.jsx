import { createElement, useState } from '@wordpress/element';
import { select, dispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { DropdownMenu } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { BankPicker } from '../question-bank/BankPicker';
import { ImportDialog } from '../content-hub/ImportDialog';
import { ExamsPage } from '../content-hub/ExamsPage';

/**
 * Insert at a stable document anchor, including after an asynchronous import.
 * @param {Object} props          Properties.
 * @param {string} props.clientId Block to insert after, or empty for the end.
 * @return {Element} Contextual insertion menu.
 */
export function PaperInserter( { clientId = '' } ) {
	const [ mode, setMode ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const quizId = useSelect(
		( store ) =>
			store( 'core/editor' ).getCurrentPostType() === 'ohmylms-quiz'
				? store( 'core/editor' ).getCurrentPostId()
				: 0,
		[]
	);
	const insert = ( blocks ) => {
		const editor = select( 'core/block-editor' );
		const exists = clientId && editor.getBlock( clientId );
		const root = exists ? editor.getBlockRootClientId( clientId ) : '';
		const index = exists
			? editor.getBlockIndex( clientId, root ) + 1
			: undefined;
		dispatch( 'core/block-editor' ).insertBlocks( blocks, index, root );
	};
	const existingIds = () =>
		select( 'core/block-editor' )
			.getClientIdsWithDescendants()
			.map(
				( id ) =>
					select( 'core/block-editor' ).getBlockAttributes( id )
						?.questionId
			)
			.filter( Boolean );
	const insertQuestions = ( ids ) => {
		const existing = new Set( existingIds().map( Number ) );
		insert(
			[ ...new Set( ids.map( Number ) ) ]
				.filter( ( id ) => id > 0 && ! existing.has( id ) )
				.map( ( questionId ) =>
					createBlock( 'ohmylms/shared-multiple-choice', {
						questionId,
						delivery: 'quiz',
					} )
				)
		);
	};
	return (
		<div className="ohmylms-paper-inserter">
			<DropdownMenu
				icon="plus-alt2"
				label={ __( 'Add to assessment', 'ohmylms' ) }
				controls={ [
					{
						title: __( 'Question', 'ohmylms' ),
						onClick: () =>
							insert( [
								createBlock( 'ohmylms/shared-multiple-choice', {
									delivery: 'quiz',
								} ),
							] ),
					},
					{
						title: __( 'Section', 'ohmylms' ),
						onClick: () =>
							insert( [
								createBlock( 'ohmylms/paper-section' ),
							] ),
					},
					{
						title: __( 'Instructions', 'ohmylms' ),
						onClick: () =>
							insert( [
								createBlock( 'ohmylms/paper-instructions' ),
							] ),
					},
					{
						title: __( 'Question bank', 'ohmylms' ),
						onClick: () => setMode( 'bank' ),
					},
					{
						title: __( 'Import PDF / CSV', 'ohmylms' ),
						onClick: () => setMode( 'import' ),
					},
				] }
			/>
			{ mode === 'bank' && (
				<BankPicker
					quizId={ quizId }
					existingIds={ existingIds() }
					onClose={ () => setMode( '' ) }
					onPick={ insertQuestions }
				/>
			) }
			{ mode === 'import' && (
				<ImportDialog
					initialTarget="questions"
					targets={ [ 'questions' ] }
					withSkills
					onClose={ () => setMode( '' ) }
					onQuestions={ insertQuestions }
					onJob={ ( id ) => {
						setMode( '' );
						setJobId( id );
					} }
				/>
			) }
			{ jobId > 0 && (
				<ExamsPage
					reviewOnly
					jobId={ jobId }
					onQuestions={ insertQuestions }
					onClosed={ () => setJobId( 0 ) }
				/>
			) }
		</div>
	);
}

const withPaperInsertion = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const quiz = useSelect(
			( store ) =>
				store( 'core/editor' )?.getCurrentPostType?.() ===
				'ohmylms-quiz',
			[]
		);
		const eligible =
			props.name === 'ohmylms/quiz-question-reference' ||
			props.name.startsWith( 'ohmylms/paper-' );
		return (
			<>
				<BlockEdit { ...props } />
				{ quiz && eligible && props.isSelected && (
					<PaperInserter clientId={ props.clientId } />
				) }
			</>
		);
	},
	'withPaperInsertion'
);
addFilter( 'editor.BlockEdit', 'ohmylms/paper-insertion', withPaperInsertion );
