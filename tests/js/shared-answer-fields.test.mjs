import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { moveOption } from '../../assets/src/features/question-editor/model.mjs';
import {
	draftToPayload,
	emptyDraft,
	NEW_QUESTION_TYPES,
	validateDraft,
} from '../../assets/src/features/question-bank/model.mjs';
import { QUESTION_BLOCK_TYPES } from '../../assets/src/features/question-editor/questionBlocks.mjs';
import { isExtendedType } from '../../assets/src/features/question-editor/extendedModel.mjs';

const source = fs
	.readFileSync(
		'assets/src/features/question-editor/BankAnswerFields.jsx',
		'utf8'
	)
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( 'export function', 'function' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [
		[
			'@babel/preset-react',
			{ pragma: 'createElement', pragmaFrag: 'Fragment' },
		],
	],
} );
const scope = {
	Fragment: 'fragment',
	createElement: ( type, props, ...children ) => ( {
		type,
		props,
		children,
	} ),
	useRef: ( value ) => ( { current: value } ),
	__: ( text ) => text,
	sprintf: ( text, n ) => text.replace( '%d', n ),
	Button: 'button',
	TextControl: 'text',
	CheckboxControl: 'check',
	MediaUpload: 'media',
	RichContentControl: 'rich-content',
	NumericalEditor: 'numerical',
	StructuredEditor: 'structured',
	DropdownBlanksEditor: 'dropdown-blanks',
	CategorizeEditor: 'categorize',
	MultiBlankEditor: 'multi-blank',
	BuildExpressionEditor: 'build-expression',
	TextEntryEditor: 'text-entry',
	CrosswordEditor: 'crossword',
	WordsearchEditor: 'wordsearch',
	ExpressionEditor: 'expression',
	NumberLineEditor: 'NumberLine',
	ShadeModelEditor: 'ShadeModel',
	CountBlocksEditor: 'CountBlocks',
	SetClockEditor: 'SetClock',
	MakeAmountEditor: 'MakeAmount',
	FillLevelEditor: 'FillLevel',
	BuildChartEditor: 'BuildChart',
	GridBuildEditor: 'GridBuild',
	ExtendedEditor: 'extended',
	ExtensionBoundary: 'extension-boundary',
	isExtendedType,
	moveOption,
};
const Fields = new Function(
	...Object.keys( scope ),
	code + ';return BankAnswerFields;'
)( ...Object.values( scope ) );
function nodes( tree, type ) {
	return [
		tree,
		...( tree?.children || [] )
			.flat( Infinity )
			.filter( Boolean )
			.flatMap( ( child ) =>
				typeof child === 'object' ? nodes( child, type ) : []
			),
	].filter( ( node ) => node?.type === type );
}

test( 'shared choice fields update correctness without losing images or option metadata', () => {
	const options = [
		{
			id: 1,
			answer: 'A',
			is_correct: true,
			thumbnail_id: 12,
			extension: 'keep',
		},
		{ id: 2, answer: 'B', is_correct: false },
	];
	let saved;
	const tree = Fields( {
		type: 'single-choice',
		options,
		settings: {},
		onChange: ( patch ) => ( saved = patch ),
	} );
	nodes( tree, 'input' )[ 1 ].props.onChange( { target: { checked: true } } );
	assert.deepEqual(
		saved.questions.map( ( o ) => o.is_correct ),
		[ false, true ]
	);
	assert.equal( saved.questions[ 0 ].thumbnail_id, 12 );
	assert.equal( saved.questions[ 0 ].extension, 'keep' );
	const label = 'A [[ohmylms-math:latex:inline]]x^2[[/ohmylms-math]]';
	nodes( tree, 'rich-content' )[ 0 ].props.onChange( label );
	assert.equal( saved.questions[ 0 ].answer, label );
	assert.equal( saved.questions[ 0 ].thumbnail_id, 12 );
	assert.equal( saved.questions[ 0 ].extension, 'keep' );
	assert.equal( saved.questions[ 0 ].is_correct, true );
} );
test( 'shared answer drag reorders and renumbers the persisted option IDs', () => {
	const options = [
		{ id: 1, answer: 'A' },
		{ id: 2, answer: 'B' },
	];
	let saved;
	const tree = Fields( {
		type: 'reorder',
		options,
		settings: {},
		onChange: ( patch ) => ( saved = patch ),
	} );
	nodes( tree, 'button' )
		.find( ( node ) => node.props.draggable )
		.props.onDragStart( { dataTransfer: { setData() {} } } );
	nodes( tree, 'fieldset' )[ 1 ].props.onDrop( { preventDefault() {} } );
	assert.deepEqual(
		saved.questions.map( ( o ) => [ o.id, o.order_number ] ),
		[
			[ 2, 1 ],
			[ 1, 2 ],
		]
	);
} );
test( 'a registered extension editor renders inside its own error boundary', () => {
	const custom = () => null;
	globalThis.window = {
		ohmylms: {
			extensions: {
				get: ( kind, id ) =>
					'question-editor' === kind && 'example-number' === id
						? { id, render: custom }
						: undefined,
			},
		},
	};
	try {
		let saved;
		const tree = Fields( {
			type: 'example-number',
			settings: { type: 'example-number', expected: 1 },
			onChange: ( patch ) => ( saved = patch ),
		} );
		assert.equal( tree.type, 'extension-boundary' );
		assert.equal( tree.props.id, 'example-number' );
		const [ editor ] = tree.children;
		assert.equal( editor.type, custom );
		assert.deepEqual( editor.props.value, {
			type: 'example-number',
			expected: 1,
		} );
		editor.props.onChange( { expected: 2, type: 'other' } );
		assert.deepEqual( saved, {
			settings: { type: 'example-number', expected: 2 },
		} );
		assert.equal(
			Fields( { type: 'single-choice', options: [], settings: {} } ).type,
			'div'
		);
	} finally {
		delete globalThis.window;
	}
} );
test( 'every built-in type is available in standalone creation and matching metadata survives saving', () => {
	assert.deepEqual(
		new Set( NEW_QUESTION_TYPES ),
		new Set( QUESTION_BLOCK_TYPES.map( ( [ type ] ) => type ) )
	);
	const draft = {
		...emptyDraft( 'matching' ),
		name: 'Pair these',
		options: [
			{
				answer: 'A',
				matching_data: { label: 'B', image_id: 3 },
				thumbnail_id: 4,
				image_url: 'image.jpg',
			},
			{ answer: 'C', matching_data: { label: 'D' } },
		],
	};
	assert.deepEqual( validateDraft( draft ), [] );
	const payload = draftToPayload( draft );
	assert.equal( payload.questions[ 0 ].matching_data.image_id, 3 );
	assert.equal( payload.questions[ 0 ].thumbnail_id, 4 );
} );
