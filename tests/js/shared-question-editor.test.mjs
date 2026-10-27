import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { moveOption } from '../../assets/src/features/question-editor/model.mjs';
import {
	draftToPayload,
	emptyDraft,
} from '../../assets/src/features/question-bank/model.mjs';
const scope = {
	Fragment: 'fragment',
	EquationAction: 'equation-action',
	createElement: ( type, props, ...children ) => ( {
		type,
		props,
		children,
	} ),
	useState: ( value ) => [ value, () => {} ],
	useRef: ( value ) => ( { current: value } ),
	useEffect: () => {},
	__: ( text ) => text,
	moveOption,
};
const source = fs
	.readFileSync(
		'assets/src/features/question-editor/ChoiceOptionsEditor.jsx',
		'utf8'
	)
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( /export /g, '' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );
const Fields = new Function(
	...Object.keys( scope ),
	code + ';return ChoiceAnswerFields;'
)( ...Object.values( scope ) );
const options = [
	{ id: 1, answer: 'A', is_correct: true, image_id: 25 },
	{ id: 2, answer: 'B', is_correct: false },
];
function render( type ) {
	let saved;
	const tree = Fields( {
		type,
		options,
		Option: 'option',
		ordering: { I: ( rows ) => rows },
		onChange: ( rows ) => {
			saved = rows;
		},
	} );
	return { row: tree.children[ 0 ][ 1 ].children[ 0 ], saved: () => saved };
}
test( 'the shared quiz choice editor preserves option metadata and single-choice correctness', () => {
	const view = render( 'single' );
	view.row.props.onCheckboxChange( 2 );
	assert.deepEqual(
		view.saved().map( ( row ) => row.is_correct ),
		[ false, true ]
	);
	assert.equal( view.saved()[ 0 ].image_id, 25 );
} );
test( 'multiple choice preserves the other correct answer', () => {
	const view = render( 'multiple' );
	view.row.props.onCheckboxChange( 2 );
	assert.deepEqual(
		view.saved().map( ( row ) => row.is_correct ),
		[ true, true ]
	);
} );
test( 'the shared choice editor saves drag order', () => {
	const view = render( 'single' );
	view.row.props.onDragStart(
		{ currentTarget: { classList: { add() {} } } },
		1
	);
	view.row.props.onDrop( { preventDefault() {} }, 0 );
	assert.deepEqual(
		view.saved().map( ( row ) => row.id ),
		[ 2, 1 ]
	);
} );
test( 'standalone question options preserve Required in the saved payload', () => {
	const draft = emptyDraft();
	draft.settings.required = true;
	assert.equal( draftToPayload( draft ).settings.required, true );
} );
