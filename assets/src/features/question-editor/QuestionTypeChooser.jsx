import { createElement, useState } from '@wordpress/element';
import { Button, Modal, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { QUESTION_BLOCK_TYPES } from './questionBlocks.mjs';

const ACTIVITIES = [
	[ 'find-match', __( 'Find the match', 'ohmylms' ), 'matching' ],
	[ 'pair-or-no-pair', __( 'Pair or No Pair', 'ohmylms' ), 'matching' ],
	[ 'balloon-pop', __( 'Balloon pop', 'ohmylms' ), 'matching' ],
	[ 'win-or-lose', __( 'Win or lose quiz', 'ohmylms' ), 'single-choice' ],
	[ 'maze-chase', __( 'Maze chase', 'ohmylms' ), 'single-choice' ],
	[ 'airplane', __( 'Airplane', 'ohmylms' ), 'single-choice' ],
	[ 'speed-sorting', __( 'Speed sorting', 'ohmylms' ), 'categorize' ],
	[ 'word-magnets', __( 'Word magnets', 'ohmylms' ), 'build-expression' ],
	[ 'image-quiz', __( 'Image quiz', 'ohmylms' ), 'single-choice' ],
	[ 'spell-word', __( 'Spell the word', 'ohmylms' ), 'text-entry' ],
	[ 'memory-recall', __( 'Watch and memorize', 'ohmylms' ), 'matching' ],
	[ 'memory-pairs', __( 'Matching Pairs', 'ohmylms' ), 'matching' ],
];

/**
 * Choose a response format before inserting an independent question draft.
 * @param root0
 * @param root0.onSelect
 * @param root0.onClose
 */
export function QuestionTypeChooser( { onSelect, onClose } ) {
	const [ search, setSearch ] = useState( '' );
	const formats = ( ids ) =>
		QUESTION_BLOCK_TYPES.filter(
			( [ id ] ) => ids.includes( id ) && id !== 'multiple-choice'
		);
	const groups = [
		[
			__( 'Basic', 'ohmylms' ),
			formats( [
				'single-choice',
				'multiple-choice',
				'true-false',
				'short-text',
				'text-entry',
				'long-text',
				'fill-in-the-blank',
				'statement',
				'poll',
				'word-cloud',
			] ),
		],
		[
			__( 'Interactive and responses', 'ohmylms' ),
			formats( [
				'reorder',
				'build-expression',
				'crossword',
				'wordsearch',
				'matching',
				'dropdown-blanks',
				'categorize',
				'multi-blank',
				'hot-text',
				'match-table-grid',
				'audio-response',
				'video-response',
				'discussion-board',
			] ),
		],
		[
			__( 'Activities and games', 'ohmylms' ),
			formats( [
				'spin-wheel',
				'gameshow',
				'hangman',
				'open-box',
				'speaking-cards',
				'flip-tiles',
				'flashcards',
				'timed-true-false',
				'whack-a-mole',
				'flying-fruit',
			] ),
		],
		[
			__( 'Mathematics', 'ohmylms' ),
			formats( [ 'numerical', 'structured', 'expression', 'graphing' ] ),
		],
		[
			__( 'Visual learning', 'ohmylms' ),
			formats( [
				'number-line',
				'shade-model',
				'count-blocks',
				'set-clock',
				'make-amount',
				'fill-level',
				'build-chart',
				'grid-build',
				'labeling',
				'hotspot',
				'draw',
			] ),
		],
		[
			__( 'Content and multimedia', 'ohmylms' ),
			formats( [ 'passage', 'interactive-video' ] ),
		],
	];
	const labels = Object.fromEntries( QUESTION_BLOCK_TYPES );
	const activities = ACTIVITIES.filter( ( [ , label, type ] ) =>
		( label + ' ' + labels[ type ] + ' activity game wordwall' )
			.toLowerCase()
			.includes( search.toLowerCase().trim() )
	);
	const matches = ( [ id, label ] ) => {
		const keywords =
			{
				'build-expression':
					' anagram letters spelling sentence words tiles',
				labeling: ' labelled diagram labeled diagram pins labels drag',
				numerical:
					' maths generator arithmetic fractions percentages algebra',
			}[ id ] || '';
		return ( label + keywords )
			.toLowerCase()
			.includes( search.toLowerCase().trim() );
	};
	return createElement(
		Modal,
		{
			title: __( 'Choose a question type', 'ohmylms' ),
			onRequestClose: onClose,
			size: 'large',
			className: 'ohmylms-question-type-modal',
		},
		<>
			<p>
				{ __(
					'Choose how learners will respond. Then write the question and configure its answers.',
					'ohmylms'
				) }
			</p>
			<TextControl
				label={ __( 'Search question types', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
			/>
			{ groups.map(
				( [ heading, types ] ) =>
					types.some( matches ) && (
						<section key={ heading } aria-label={ heading }>
							<h2>{ heading }</h2>
							<div className="ohmylms-question-type-grid">
								{ types
									.filter( matches )
									.map( ( [ type, label ] ) => (
										<Button
											key={ type }
											variant="secondary"
											onClick={ () => onSelect( type ) }
										>
											{ label }
										</Button>
									) ) }
							</div>
						</section>
					)
			) }
			{ activities.length > 0 && (
				<section aria-label={ __( 'More activities', 'ohmylms' ) }>
					<h2>{ __( 'More activities', 'ohmylms' ) }</h2>
					<p>
						{ __(
							'Each activity is played from a question of the type shown. Write the question here, then choose the activity in a Question Player block.',
							'ohmylms'
						) }
					</p>
					<div className="ohmylms-question-type-grid">
						{ activities.map( ( [ activity, label, type ] ) => (
							<Button
								key={ activity }
								variant="secondary"
								onClick={ () => onSelect( type, activity ) }
							>
								{ label }
								<small>{ ' — ' + labels[ type ] }</small>
							</Button>
						) ) }
					</div>
				</section>
			) }
			{ ! QUESTION_BLOCK_TYPES.some( matches ) && ! activities.length && (
				<p role="status">
					{ __( 'No question types match your search.', 'ohmylms' ) }
				</p>
			) }
		</>
	);
}
