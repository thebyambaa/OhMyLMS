import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * IDs of the built-in question types that have an editor component, in registration order.
 */
export const CORE_QUESTION_EDITOR_IDS = Object.freeze( [
	'numerical',
	'structured',
	'dropdown-blanks',
	'categorize',
	'multi-blank',
	'build-expression',
	'expression',
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
] );

/**
 * Register the built-in question editors in the public extension registry. An editor that is
 * already registered (an add-on may register its own editor for a built-in type first) is kept.
 *
 * @param {Object} registry   Extension registry (API v1).
 * @param {Object} components Editor components by export name (`NumericalEditor`, …); `BankAnswerFields`
 *                            renders each part of a structured question.
 * @return {string[]} IDs registered by this call.
 */
export function registerCoreQuestionEditors( registry, components ) {
	const {
		NumericalEditor,
		StructuredEditor,
		BankAnswerFields,
		DropdownBlanksEditor,
		CategorizeEditor,
		MultiBlankEditor,
		BuildExpressionEditor,
		ExpressionEditor,
		NumberLineEditor,
		ShadeModelEditor,
		CountBlocksEditor,
		SetClockEditor,
		MakeAmountEditor,
		FillLevelEditor,
		BuildChartEditor,
		GridBuildEditor,
	} = components;
	const editors = [
		[
			'numerical',
			__( 'Numerical', 'ohmylms' ),
			__( 'A number checked with a tolerance', 'ohmylms' ),
			NumericalEditor,
		],
		[
			'structured',
			__( 'Structured (multi-part)', 'ohmylms' ),
			__( 'A shared stem with separately scored parts', 'ohmylms' ),
			( props ) =>
				createElement( StructuredEditor, {
					...props,
					renderQuestion: BankAnswerFields,
				} ),
		],
		[
			'dropdown-blanks',
			__( 'Dropdown in a sentence', 'ohmylms' ),
			__( 'Choose the right word from a menu in a sentence', 'ohmylms' ),
			DropdownBlanksEditor,
		],
		[
			'categorize',
			__( 'Sort into groups', 'ohmylms' ),
			__( 'Place each item in the group it belongs to', 'ohmylms' ),
			CategorizeEditor,
		],
		[
			'multi-blank',
			__( 'Multi-blank / table', 'ohmylms' ),
			__( 'Several blanks in a sentence or a table', 'ohmylms' ),
			MultiBlankEditor,
		],
		[
			'build-expression',
			__( 'Build from tiles', 'ohmylms' ),
			__( 'Tap tiles into the right order', 'ohmylms' ),
			BuildExpressionEditor,
		],
		[
			'expression',
			__( 'Math expression', 'ohmylms' ),
			__(
				'A typed expression graded by algebraic equivalence',
				'ohmylms'
			),
			ExpressionEditor,
		],
		[
			'number-line',
			__( 'Number line', 'ohmylms' ),
			__( 'Drag a point to the right place on a number line', 'ohmylms' ),
			NumberLineEditor,
		],
		[
			'shade-model',
			__( 'Shade a model', 'ohmylms' ),
			__( 'Shade parts of a bar, grid or circle', 'ohmylms' ),
			ShadeModelEditor,
		],
		[
			'count-blocks',
			__( 'Count with blocks', 'ohmylms' ),
			__( 'Build a number with base-ten blocks', 'ohmylms' ),
			CountBlocksEditor,
		],
		[
			'set-clock',
			__( 'Set the clock', 'ohmylms' ),
			__( 'Set the hands of an analogue clock', 'ohmylms' ),
			SetClockEditor,
		],
		[
			'make-amount',
			__( 'Make an amount', 'ohmylms' ),
			__( 'Pay an amount with bills and coins', 'ohmylms' ),
			MakeAmountEditor,
		],
		[
			'fill-level',
			__( 'Fill to a level', 'ohmylms' ),
			__( 'Fill a jug to a marked level', 'ohmylms' ),
			FillLevelEditor,
		],
		[
			'build-chart',
			__( 'Build a chart', 'ohmylms' ),
			__( 'Drag bars to the right heights', 'ohmylms' ),
			BuildChartEditor,
		],
		[
			'grid-build',
			__( 'Build on a grid', 'ohmylms' ),
			__(
				'Choose squares to meet area and perimeter conditions',
				'ohmylms'
			),
			GridBuildEditor,
		],
	];
	const registered = [];
	for ( const [ id, label, description, render ] of editors ) {
		if ( ! registry.get?.( 'question-editor', id ) ) {
			registry.registerQuestionEditor( id, {
				label,
				description,
				render,
			} );
			registered.push( id );
		}
	}
	return registered;
}
