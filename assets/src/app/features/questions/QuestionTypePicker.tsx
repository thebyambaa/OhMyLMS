import { useId, useMemo, useState } from '@wordpress/element';
import { SearchControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { QuestionType } from './types';
import './QuestionTypePicker.css';

interface TypeGroup {
	id: string;
	label: string;
	types: readonly string[];
}

/**
 * How the built-in question types are grouped, most used first. A type that is not listed (one an
 * extension registers) is shown under "More types", so nothing registered is ever hidden.
 */
function typeGroups(): TypeGroup[] {
	return [
		{
			id: 'common',
			label: __( 'Most used', 'ohmylms' ),
			types: [
				'single-choice',
				'multiple-choice',
				'true-false',
				'text-entry',
				'fill-in-the-blank',
				'numerical',
			],
		},
		{
			id: 'written',
			label: __( 'Written and spoken, marked by a teacher', 'ohmylms' ),
			types: [
				'short-text',
				'long-text',
				'audio-response',
				'video-response',
				'draw',
				'discussion-board',
			],
		},
		{
			id: 'arrange',
			label: __( 'Match, sort and label', 'ohmylms' ),
			types: [
				'matching',
				'match-table-grid',
				'categorize',
				'reorder',
				'dropdown-blanks',
				'multi-blank',
				'hot-text',
				'hotspot',
				'labeling',
			],
		},
		{
			id: 'maths',
			label: __( 'Maths and graphs', 'ohmylms' ),
			types: [
				'expression',
				'graphing',
				'number-line',
				'build-chart',
				'build-expression',
				'grid-build',
				'count-blocks',
				'fill-level',
				'make-amount',
				'set-clock',
				'shade-model',
				'structured',
			],
		},
		{
			id: 'games',
			label: __( 'Games', 'ohmylms' ),
			types: [
				'flashcards',
				'crossword',
				'wordsearch',
				'hangman',
				'flip-tiles',
				'open-box',
				'spin-wheel',
				'gameshow',
				'timed-true-false',
				'flying-fruit',
				'whack-a-mole',
				'speaking-cards',
			],
		},
		{
			id: 'content',
			label: __( 'Content and opinion, not scored', 'ohmylms' ),
			types: [
				'passage',
				'statement',
				'slide',
				'interactive-video',
				'poll',
				'word-cloud',
			],
		},
	];
}

/** One line on what the most used types ask of a learner. */
function typeHints(): Record< string, string > {
	return {
		'single-choice': __( 'One correct answer from a list.', 'ohmylms' ),
		'multiple-choice': __(
			'Several correct answers from a list.',
			'ohmylms'
		),
		'true-false': __( 'A statement that is true or false.', 'ohmylms' ),
		'text-entry': __( 'A typed answer, marked automatically.', 'ohmylms' ),
		'fill-in-the-blank': __( 'Missing words in a sentence.', 'ohmylms' ),
		numerical: __( 'A number as the answer.', 'ohmylms' ),
	};
}

export interface TypeSection {
	id: string;
	label: string;
	types: QuestionType[];
}

/**
 * Arrange the registered types into the picker's sections.
 *
 * @param types  Registered types.
 * @param search Text the label must contain; `''` for every type.
 * @return Sections that have at least one type, in display order.
 */
export function groupQuestionTypes(
	types: readonly QuestionType[],
	search = ''
): TypeSection[] {
	const needle = search.trim().toLowerCase();
	const byId = new Map( types.map( ( type ) => [ type.id, type ] ) );
	const placed = new Set< string >();
	const matches = ( type: QuestionType ) =>
		'' === needle || type.label.toLowerCase().includes( needle );
	const sections: TypeSection[] = typeGroups().map( ( group ) => ( {
		id: group.id,
		label: group.label,
		types: group.types.flatMap( ( id ) => {
			const type = byId.get( id );
			if ( ! type ) {
				return [];
			}
			placed.add( id );
			return matches( type ) ? [ type ] : [];
		} ),
	} ) );
	sections.push( {
		id: 'more',
		label: __( 'More types', 'ohmylms' ),
		types: types.filter(
			( type ) => ! placed.has( type.id ) && matches( type )
		),
	} );
	return sections.filter( ( section ) => section.types.length > 0 );
}

/**
 * Choose a question type from grouped tiles, the most used first, with a search box.
 *
 * @param props          Props.
 * @param props.types    Registered types.
 * @param props.value    Chosen type id.
 * @param props.disabled Disables every tile.
 * @param props.help     Text under the tiles.
 * @param props.onChange Called with the chosen type id.
 */
export function QuestionTypePicker( {
	types,
	value,
	disabled = false,
	help,
	onChange,
}: {
	types: readonly QuestionType[];
	value: string;
	disabled?: boolean;
	help?: string;
	onChange: ( id: string ) => void;
} ) {
	const [ search, setSearch ] = useState( '' );
	const name = useId();
	const sections = useMemo(
		() => groupQuestionTypes( types, search ),
		[ types, search ]
	);
	const hints = useMemo( typeHints, [] );

	return (
		<fieldset className="oml-type-picker">
			<legend className="oml-type-picker-legend">
				{ __( 'Type', 'ohmylms' ) }
			</legend>
			<SearchControl
				__nextHasNoMarginBottom
				label={ __( 'Search question types', 'ohmylms' ) }
				placeholder={ __( 'Search question types', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
			/>
			{ sections.map( ( section ) => (
				<div className="oml-type-picker-group" key={ section.id }>
					<h3 className="oml-type-picker-heading">
						{ section.label }
					</h3>
					<div className="oml-type-picker-grid">
						{ section.types.map( ( type ) => (
							<label
								key={ type.id }
								className="oml-type-picker-tile"
								htmlFor={ `${ name }-${ type.id }` }
							>
								<input
									type="radio"
									id={ `${ name }-${ type.id }` }
									className="screen-reader-text"
									name={ name }
									value={ type.id }
									checked={ type.id === value }
									disabled={ disabled }
									onChange={ () => onChange( type.id ) }
								/>
								<span className="oml-type-picker-name">
									{ type.label }
								</span>
								{ hints[ type.id ] && (
									<span className="oml-type-picker-hint">
										{ hints[ type.id ] }
									</span>
								) }
							</label>
						) ) }
					</div>
				</div>
			) ) }
			{ 0 === sections.length && (
				<p role="status">
					{ __( 'No question type matches your search.', 'ohmylms' ) }
				</p>
			) }
			{ help && <p className="description">{ help }</p> }
		</fieldset>
	);
}
