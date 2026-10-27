import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import {
	questionPrompt,
	questionPromptPatch,
	promptText,
} from '../../assets/src/features/question-editor/questionPrompt.mjs';

const source = fs
	.readFileSync(
		'assets/src/features/quiz-editor/QuizQuestionCards.jsx',
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
	createElement: ( type, props, ...children ) => ( {
		type,
		props,
		children,
	} ),
	Fragment: 'fragment',
	useState: ( value ) => [
		typeof value === 'function' ? value() : value,
		() => {},
	],
	useEffect: () => {},
	__: ( text ) => text,
	_n: ( one, many, count ) => ( count === 1 ? one : many ),
	sprintf: ( text, value ) => text.replace( /%[ds]/, value ),
	RichContentControl: 'rich',
	Button: 'button',
	Modal: 'modal',
	TextControl: 'text',
	QuestionTypeChooser: 'chooser',
	QuestionLivePreview: 'preview',
	QuizSkillsSummary: 'skills',
	ConnectQuestionSkill: 'connect',
	matchesSkillFilter: () => true,
	isUngradedType: () => false,
	isExtendedType: () => false,
	isInteractiveType: () => false,
	QUESTION_BLOCK_TYPES: [ [ 'fill-in-the-blank', 'Fill in the blank' ] ],
	questionTypePatch: () => ( {} ),
	questionPrompt,
	questionPromptPatch,
	promptText,
};
const Cards = new Function(
	...Object.keys( scope ),
	code + ';return QuizQuestionCards;'
)( ...Object.values( scope ) );
const nodes = ( tree ) => [
	tree,
	...( tree?.children || [] )
		.flat( Infinity )
		.filter( ( node ) => node && typeof node === 'object' )
		.flatMap( nodes ),
];
const question = {
	id: 1,
	name: '{one} as {two}',
	description: '<p>{one} as {two}</p>',
	settings: {
		type: 'fill-in-the-blank',
		question_code: 'Q-1',
		partial_credit: false,
		extension: { keep: true },
	},
	questions: [ { id: 3, answer: 'private key', is_correct: true } ],
};
test( 'quick prompt changes use the canonical patch and double-click opens the full editor', () => {
	let patch;
	let selected;
	const editor = {
		questions: [ question ],
		patchQuestion: ( id, fields ) => {
			patch = { id, fields };
		},
		selectQuestion: ( value ) => {
			selected = value;
		},
	};
	const control = nodes( Cards( { editor } ) ).find(
		( node ) => node.type === 'rich'
	);
	assert.equal( control.props.value, question.description );
	assert.equal( control.props.onClick, undefined );
	assert.equal( selected, undefined );
	const source =
		'<p>{first} with [[ohmylms-math:latex:inline]]\\frac{a}{b}[[/ohmylms-math]]</p>';
	control.props.onChange( source );
	assert.equal( patch.id, 1 );
	assert.equal( patch.fields.description, source );
	assert.deepEqual( patch.fields.settings, {
		...question.settings,
		blank_mode: 'drag',
	} );
	assert.equal( patch.fields.questions, undefined );
	assert.equal( question.questions[ 0 ].answer, 'private key' );
	control.props.onDoubleClick();
	assert.equal( selected, question );
} );
test( 'pinned versions keep a readonly prompt instead of a quick-edit field', () => {
	const tree = Cards( {
		editor: { questions: [ { ...question, readonly: true } ] },
	} );
	assert.equal(
		nodes( tree ).some( ( node ) => node.type === 'rich' ),
		false
	);
} );
