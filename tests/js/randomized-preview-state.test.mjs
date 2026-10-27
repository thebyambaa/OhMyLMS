import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const source = fs
	.readFileSync(
		'assets/src/features/question-editor/QuestionLivePreview.jsx',
		'utf8'
	)
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( 'export function', 'function' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );
function harness( apiFetch ) {
	let state = null;
	let effect;
	const scope = {
		createElement: ( type, props, ...children ) => ( {
			type,
			props,
			children,
		} ),
		useRef: () => ( { current: 123 } ),
		useState: () => [
			state,
			( next ) => {
				state = next;
			},
		],
		useEffect: ( callback ) => {
			effect = callback;
		},
		__: ( text ) => text,
		window: { wp: { apiFetch } },
	};
	const render = new Function(
		...Object.keys( scope ),
		code + ';return QuestionLivePreview;'
	)( ...Object.values( scope ) );
	return {
		render: ( question ) => render( { question } ),
		effect: () => effect(),
	};
}
const draft = {
	name: '{{a}} apples',
	settings: {
		type: 'matching',
		template: { variables: [ { name: 'a', type: 'int', min: 2, max: 9 } ] },
	},
	questions: [],
};
const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

test( 'preview waits for concrete server data without replacing authored template source', async () => {
	let request;
	const h = harness( async ( options ) => {
		request = options;
		return {
			valid: true,
			question: {
				name: '5 apples',
				settings: { type: 'matching' },
				questions: [],
			},
		};
	} );
	assert.equal( h.render( draft ).props.role, 'status' );
	h.effect();
	await tick();
	const rendered = h.render( draft );
	assert.equal( rendered.props.question.name, '5 apples' );
	assert.equal( request.data.seed, 123 );
	assert.equal( request.data.render, true );
	assert.equal( draft.name, '{{a}} apples' );
	assert.ok( draft.settings.template );
} );
test( 'failed and superseded requests never render raw variables', async () => {
	let resolve;
	const h = harness(
		() =>
			new Promise( ( done ) => {
				resolve = done;
			} )
	);
	h.render( draft );
	const cleanup = h.effect();
	cleanup();
	resolve( { valid: true, question: { name: 'obsolete' } } );
	await tick();
	assert.equal(
		h.render( { ...draft, name: '{{a}} pears' } ).props.role,
		'status'
	);
	const failed = harness( async () => ( {
		valid: false,
		message: 'Unknown variable',
	} ) );
	failed.render( draft );
	failed.effect();
	await tick();
	assert.deepEqual( failed.render( draft ).children, [ 'Unknown variable' ] );
} );
test( 'old questions keep their original content and make no template request', () => {
	const h = harness( () => {
		throw new Error( 'Unexpected request' );
	} );
	const plain = { name: 'Literal {{a}}', settings: { type: 'short-text' } };
	assert.equal( h.render( plain ).props.question, plain );
	h.effect();
} );
