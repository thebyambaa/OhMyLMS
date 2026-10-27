import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { choiceReadyToSave } from '../../assets/src/features/learning/choiceAutosave.mjs';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';
import {
	addPart,
	updatePart,
	removePart,
} from '../../assets/src/features/question-bank/model.mjs';

const leafTypes = QUESTION_BLOCK_TYPES.filter(
	( [ type ] ) =>
		! [
			'numerical',
			'structured',
			'passage',
			'short-text',
			'long-text',
			'statement',
			'poll',
			'word-cloud',
			'discussion-board',
			'interactive-video',
		].includes( type )
);

test( 'a nested editor retains statement, stable ID, and marks when its answer type changes', () => {
	const source = fs
		.readFileSync(
			'assets/src/features/question-editor/MathEditors.jsx',
			'utf8'
		)
		.replace( /^import [\s\S]*?;$/gm, '' )
		.replace( /export /g, '' );
	const { code } = transformSync( source, {
		configFile: false,
		babelrc: false,
		presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
	} );
	const scope = {
		createElement: ( type, props, ...children ) => ( {
			type,
			props,
			children,
		} ),
		__: ( text ) => text,
		sprintf: ( text ) => text,
		Button: 'button',
		TextControl: 'text',
		SelectControl: 'select',
		TextareaControl: 'textarea',
		RichContentControl: 'rich',
		QUESTION_BLOCK_TYPES,
		questionTypePatch,
		addPart,
		updatePart,
		removePart,
	};
	const Editor = new Function(
		...Object.keys( scope ),
		code + '; return StructuredEditor;'
	)( ...Object.values( scope ) );
	let saved;
	const part = {
		id: 'a',
		kind: 'written',
		marks: 3,
		prompt: '<p>My statement</p>',
	};
	const nodes = ( tree ) => [
		tree,
		...( tree?.children || [] )
			.flat( Infinity )
			.filter( ( item ) => item && typeof item === 'object' )
			.flatMap( nodes ),
	];
	const tree = Editor( {
		value: { parts: [ part ] },
		onChange: ( patch ) => {
			saved = patch;
		},
		renderQuestion: 'leaf',
	} );
	const selector = nodes( tree ).find( ( node ) => node.type === 'select' );
	assert.equal( selector.props.options.length, leafTypes.length + 3 );
	// Recorded audio and video can be parts, so a recording can sit beside other questions.
	for ( const recording of [ 'audio-response', 'video-response' ] ) {
		assert.ok(
			selector.props.options.some(
				( option ) => option.value === recording
			)
		);
	}
	assert.ok(
		! selector.props.options.some(
			( option ) => option.value === 'structured'
		)
	);
	selector.props.onChange( 'build-chart' );
	assert.equal( saved.parts[ 0 ].id, 'a' );
	assert.equal( saved.parts[ 0 ].prompt, part.prompt );
	assert.equal( saved.parts[ 0 ].marks, 3 );
	assert.equal( saved.parts[ 0 ].question.settings.type, 'build-chart' );
	const edited = nodes(
		Editor( {
			value: saved,
			onChange: ( patch ) => {
				saved = patch;
			},
			renderQuestion: 'leaf',
		} )
	);
	edited
		.find( ( node ) => node.type === 'leaf' )
		.props.onChange( { settings: { type: 'build-chart', max: 20 } } );
	assert.equal( saved.parts[ 0 ].question.settings.max, 20 );
	assert.equal( saved.parts[ 0 ].marks, 3 );
} );

test( 'nested autosave uses each leaf validation and refuses recursive parts', () => {
	const ready = ( child ) =>
		choiceReadyToSave( {
			name: 'Shared stem',
			settings: {
				type: 'structured',
				parts: [
					{
						id: 'a',
						marks: 2,
						kind: 'question',
						prompt: 'Part statement',
						question: child,
					},
				],
			},
		} );
	for ( const [ type ] of leafTypes ) {
		const child = questionTypePatch( {}, type, 100 );
		if (
			[
				'single-choice',
				'multiple-choice',
				'true-false',
				'reorder',
				'matching',
				'fill-in-the-blank',
			].includes( type )
		) {
			child.questions.forEach( ( option, index ) => {
				option.answer = String( index + 1 );
				option.is_correct = index === 0;
				option.matching_data = { label: 'Match ' + index };
			} );
		}
		if ( type === 'categorize' ) {
			child.settings.buckets.forEach( ( item ) => {
				item.label = item.id;
			} );
			child.settings.items.forEach( ( item ) => {
				item.text = item.id;
			} );
		}
		if ( type === 'build-expression' ) {
			child.settings.correct = [ '2', '+', '3' ];
		}
		if ( [ 'labeling', 'hotspot' ].includes( type ) ) {
			child.settings.image_url = 'http://800.local/example.png';
		}
		assert.equal( ready( child ), true, type );
	}
	assert.equal( ready( questionTypePatch( {}, 'structured' ) ), false );
	assert.equal(
		ready( { settings: { type: 'single-choice' }, questions: [] } ),
		false
	);
} );
