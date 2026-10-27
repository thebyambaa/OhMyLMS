import { createElement } from '@wordpress/element';
import { BankAnswerFields } from '../question-editor/BankAnswerFields';
import { __ } from '@wordpress/i18n';

/**
 * Core admin pages, registered through the public extension registry before app bootstrap.
 * @param registry
 * @param components
 */
export function registerQuestionBankPages( registry, components ) {
	const {
		QuestionBankPage,
		SkillsPage,
		PerformancePage,
		AssessmentSettingsPanel,
		InlineCheckPanel,
		NumericalEditor,
		StructuredEditor,
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
	const flags = window.ohmylmsAssessment || {};
	const enabled = ( value, fallback = true ) =>
		value === undefined
			? fallback
			: value === true || value === 1 || value === '1';
	if ( ! enabled( flags.bankUi ) ) {
		return;
	}
	registry.registerAdminPage( 'question-bank', {
		label: __( 'Question bank', 'ohmylms' ),
		render: QuestionBankPage,
		priority: 1,
	} );
	registry.registerEditorPanel( 'assessment-settings', {
		label: __( 'Assessment settings', 'ohmylms' ),
		slot: '/quiz-edit/:id',
		render: AssessmentSettingsPanel,
		priority: 1,
	} );
	if ( enabled( flags.practice ) ) {
		registry.registerEditorPanel( 'inline-question-checks', {
			label: __( 'Inline question checks', 'ohmylms' ),
			slot: '/lesson-edit/:id',
			render: InlineCheckPanel,
			priority: 1,
		} );
	}
	registry.registerQuestionEditor( 'numerical', {
		label: __( 'Numerical', 'ohmylms' ),
		description: __( 'A number checked with a tolerance', 'ohmylms' ),
		render: NumericalEditor,
	} );
	registry.registerQuestionEditor( 'structured', {
		label: __( 'Structured (multi-part)', 'ohmylms' ),
		description: __(
			'A shared stem with separately scored parts',
			'ohmylms'
		),
		render: ( props ) =>
			createElement( StructuredEditor, {
				...props,
				renderQuestion: BankAnswerFields,
			} ),
	} );
	[
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
	].forEach( ( [ type, label, description, render ] ) =>
		registry.registerQuestionEditor( type, { label, description, render } )
	);
	registry.registerAdminPage( 'performance', {
		label: __( 'Skill performance', 'ohmylms' ),
		render: PerformancePage,
		priority: 1,
	} );
	registry.registerAdminPage( 'skills', {
		label: __( 'Skills', 'ohmylms' ),
		render: SkillsPage,
		priority: 1,
	} );
}
