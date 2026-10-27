import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { parseInlineBlankPrompt } from '../../assets/src/features/question-editor/inlineBlanks.mjs';

const source = fs
	.readFileSync(
		'assets/src/features/question-editor/QuestionForm.jsx',
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
	createElement: ( type, props, ...children ) =>
		typeof type === 'function' ? type( props ) : { type, props, children },
	useState: ( value ) => [ value, () => {} ],
	Button: 'button',
	Fragment: 'fragment',
	__: ( text ) => text,
	isUngradedType: () => false,
	MathVariables: { Provider: 'provider' },
	CheckboxControl: 'checkbox',
	SelectControl: 'select',
	TextControl: 'text',
	FormWorkspace: 'workspace',
	BankAnswerFields: 'answers',
	TemplatePanel: 'template',
	QuestionOptionsBar: 'options',
	ChoiceControls: 'choices',
	questionPrompt: ( q ) => q.name,
	questionPromptPatch: ( q, name ) => ( { name } ),
	questionAnswerPatch: ( q, patch ) => patch,
	parseInlineBlankPrompt,
};
const Form = new Function(
	...Object.keys( scope ),
	code + ';return QuestionForm;'
)( ...Object.values( scope ) );
const nodes = ( tree ) => [
	tree,
	...( tree?.children || [] )
		.flat( Infinity )
		.filter( ( child ) => child && typeof child === 'object' )
		.flatMap( nodes ),
];
test( 'blank footer saves explicit grading flags without changing other settings', () => {
	const question = {
		name: '{one} and {two}',
		settings: {
			type: 'fill-in-the-blank',
			case_sensitive: false,
			score: { value: 2 },
			template: { variables: [] },
		},
	};
	let saved;
	const workspace = Form( {
		question,
		onChange: ( patch ) => {
			saved = patch;
		},
	} ).children[ 0 ];
	assert.equal( workspace.props.showLayoutSwitch, false );
	const control = nodes( workspace.props.footerLeading ).find(
		( node ) => node.type === 'checkbox'
	);
	assert.equal( control.props.label, 'Enable partial grading' );
	assert.equal( control.props.checked, true );
	control.props.onChange( false );
	assert.deepEqual( saved.settings, {
		...question.settings,
		partial_credit: false,
	} );
	const reopened = Form( {
		question: {
			...question,
			settings: JSON.parse( JSON.stringify( saved.settings ) ),
		},
		onChange: ( patch ) => {
			saved = patch;
		},
	} ).children[ 0 ];
	const reopenedControl = nodes( reopened.props.footerLeading ).find(
		( node ) => node.type === 'checkbox'
	);
	assert.equal( reopenedControl.props.checked, false );
	reopenedControl.props.onChange( true );
	assert.equal( saved.settings.partial_credit, true );
} );
test( 'only Multi Select retains a layout switch; non-blank types have no partial-grade footer', () => {
	for ( const [ type, visible ] of [
		[ 'short-text', false ],
		[ 'single-choice', true ],
		[ 'multiple-choice', true ],
		[ 'true-false', false ],
		[ 'matching', false ],
		[ 'reorder', false ],
		[ 'build-expression', false ],
		[ 'number-line', false ],
	] ) {
		const workspace = Form( {
			question: { name: 'Prompt', settings: { type } },
			onChange: () => {},
		} ).children[ 0 ];
		assert.equal( workspace.props.showLayoutSwitch, visible );
		assert.equal(
			nodes( workspace.props.footerLeading ).some(
				( node ) => node.type === 'checkbox'
			),
			false
		);
	}
} );
test( 'old separate-answer blanks retain their default all-or-nothing flag and readonly control', () => {
	const workspace = Form( {
		question: {
			name: 'Old prompt',
			settings: { type: 'fill-in-the-blank' },
		},
		readOnly: true,
		onChange: () => {},
	} ).children[ 0 ];
	const control = nodes( workspace.props.footerLeading ).find(
		( node ) => node.type === 'checkbox'
	);
	assert.equal( control.props.checked, false );
	assert.equal( control.props.disabled, true );
} );
