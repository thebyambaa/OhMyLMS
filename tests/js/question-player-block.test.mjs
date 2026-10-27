import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { fromPlayer } from '../../assets/src/features/question-editor/questionSource.mjs';
import {
	addSelection,
	moveSelection,
} from '../../assets/src/blocks/question-player/model.mjs';

test( 'question selection rejects duplicates and invalid IDs and bounds the set', () => {
	assert.deepEqual( addSelection( [ 3 ], '5' ), [ 3, 5 ] );
	for ( const value of [ 3, 0, -1, 1.5, 'not-an-id', null ] ) {
		assert.deepEqual( addSelection( [ 3 ], value ), [ 3 ] );
	}
	const full = Array.from( { length: 30 }, ( _, index ) => index + 1 );
	assert.deepEqual( addSelection( full, 100 ), full );
} );

test( 'moving selected questions keeps their stable identities and order', () => {
	assert.deepEqual( moveSelection( [ 3, 5, 7 ], 1, -1 ), [ 5, 3, 7 ] );
	assert.deepEqual( moveSelection( [ 3, 5, 7 ], 1, 1 ), [ 3, 7, 5 ] );
	assert.deepEqual( moveSelection( [ 3, 5, 7 ], 0, -1 ), [ 3, 5, 7 ] );
} );

test( 'the saved block contains references and outcome switches without assessment keys', () => {
	const block = JSON.parse(
		fs.readFileSync(
			'assets/src/blocks/question-player/block.json',
			'utf8'
		)
	);
	assert.deepEqual( Object.keys( block.attributes ), [
		'source',
		'questionIds',
		'skillIds',
		'count',
		'graded',
		'affectsSkills',
		'activityMode',
		'rememberCount',
		'studySeconds',
		'revealSeconds',
		'roundSeconds',
	] );
	assert.equal( block.attributes.graded.default, true );
	assert.equal( block.attributes.affectsSkills.default, false );
} );

test( 'authoring switches cannot leave skill effects enabled when grading is turned off', () => {
	const source = fs
		.readFileSync( 'assets/src/blocks/question-player/index.jsx', 'utf8' )
		.replace( /^import [\s\S]*?;$/gm, '' )
		.replace( /export /g, '' )
		.replace( /registerBlockType\( metadata,[\s\S]*$/, '' );
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
	const state = [];
	let cursor = 0;
	const scope = {
		window: { ohmylmsNativeLesson: true },
		createElement: ( type, props, ...children ) => ( {
			type,
			props,
			children,
		} ),
		useEffect: () => {},
		useState: ( value ) => {
			const index = cursor++;
			if ( ! ( index in state ) ) {
				state[ index ] = value;
			}
			return [
				state[ index ],
				( next ) => {
					state[ index ] = next;
				},
			];
		},
		useBlockProps: () => ( {} ),
		useDispatch: () => ( { replaceBlocks: () => {} } ),
		createBlock: ( name, attributes ) => ( { name, attributes } ),
		QuestionSourceSwitch: 'source-switch',
		allowedSources: () => [ 'write', 'bank', 'skills' ],
		editorDeliveries: () => [ 'quiz', 'inline', 'practice' ],
		fromPlayer,
		addSelection,
		moveSelection,
		playerSource: ( attributes ) =>
			attributes.source === 'random' ? 'skills' : 'bank',
		__: ( value ) => value,
		sprintf: ( value ) => value,
		Button: 'button',
		ComboboxControl: 'combo',
		Modal: 'modal',
		Notice: 'notice',
		Placeholder: 'placeholder',
		RangeControl: 'range',
		SelectControl: 'select',
		Spinner: 'spinner',
		ToggleControl: 'toggle',
		Fragment: 'fragment',
	};
	const Edit = new Function(
		...Object.keys( scope ),
		code + '; return QuestionPlayerEdit;'
	)( ...Object.values( scope ) );
	let saved;
	const props = {
		attributes: {
			source: 'random',
			questionIds: [ 3 ],
			skillIds: [ 8 ],
			count: 5,
			graded: true,
			affectsSkills: true,
		},
		setAttributes: ( patch ) => {
			saved = patch;
			Object.assign( props.attributes, patch );
		},
	};
	const render = () => {
		cursor = 0;
		return Edit( props );
	};
	const tree = render();
	const nodes = ( node ) => [
		node,
		...( node?.children || [] )
			.flat( Infinity )
			.filter( ( child ) => child && typeof child === 'object' )
			.flatMap( nodes ),
	];
	const toggle = nodes( tree ).find(
		( node ) =>
			node.type === 'toggle' && node.props.label === 'Grade responses'
	);
	toggle.props.onChange( false );
	assert.deepEqual( saved, { graded: false, affectsSkills: false } );
	assert.ok(
		nodes( tree ).some(
			( node ) =>
				node.type === 'button' &&
				node.children.includes( 'Edit selection' )
		)
	);
	// The one source switch replaces the separate "Question source" select.
	const sourceSwitch = nodes( tree ).find(
		( node ) => node.type === 'source-switch'
	);
	assert.equal( sourceSwitch.props.source, 'skills' );
	assert.deepEqual( sourceSwitch.props.allowed, [
		'write',
		'bank',
		'skills',
	] );
	assert.ok(
		! nodes( tree ).some(
			( node ) =>
				node.type === 'select' && node.props.label === 'Question source'
		)
	);
	assert.ok( ! nodes( tree ).some( ( node ) => node.type === 'modal' ) );
	assert.equal( sourceSwitch.props.allowReselect, true );
	for ( const [ chosen, title, label, key, original ] of [
		[
			'bank',
			'Pick from bank',
			'Add a question from the bank',
			'questionIds',
			3,
		],
		[ 'skills', 'From skills', 'Add a skill', 'skillIds', 8 ],
	] ) {
		sourceSwitch.props.onChange( chosen );
		let current = nodes( render() );
		let modal = current.find( ( node ) => node.type === 'modal' );
		assert.equal( modal.props.title, title );
		const picker = nodes( modal ).find( ( node ) => node.type === 'combo' );
		assert.equal( picker.props.label, label );
		picker.props.onChange( '12' );
		assert.deepEqual( props.attributes[ key ], [ original, 12 ] );
		modal = nodes( render() ).find( ( node ) => node.type === 'modal' );
		const range = nodes( modal ).find( ( node ) => node.type === 'range' );
		assert.equal( Boolean( range ), chosen === 'skills' );
		if ( range ) {
			range.props.onChange( 7 );
			assert.equal( props.attributes.count, 7 );
		}
		modal.props.onRequestClose();
		current = nodes( render() );
		assert.ok( ! current.some( ( node ) => node.type === 'combo' ) );
		current
			.find( ( node ) => node.type === 'source-switch' )
			.props.onChange( chosen );
		modal = nodes( render() ).find( ( node ) => node.type === 'modal' );
		assert.ok( modal, 'the current source can reopen its dialog' );
		nodes( modal )
			.find(
				( node ) =>
					node.type === 'button' && node.children.includes( 'Done' )
			)
			.props.onClick();
		assert.ok(
			! nodes( render() ).some( ( node ) => node.type === 'modal' )
		);
	}
} );

test( 'the source switch can reopen the active picker without changing other consumers', () => {
	const source = fs
		.readFileSync(
			'assets/src/features/question-editor/QuestionSourceSwitch.jsx',
			'utf8'
		)
		.replace( /^import [\s\S]*?;$/gm, '' )
		.replace( /export /g, '' );
	const { code } = transformSync( source, {
		configFile: false,
		babelrc: false,
		presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
	} );
	const Switch = new Function(
		'createElement',
		'Button',
		'__',
		code + '; return QuestionSourceSwitch;'
	)(
		( type, props, ...children ) => ( { type, props, children } ),
		'button',
		( value ) => value
	);
	for ( const allowReselect of [ false, true ] ) {
		const selected = [];
		const tree = Switch( {
			source: 'skills',
			allowed: [ 'bank', 'skills' ],
			allowReselect,
			onChange: ( value ) => selected.push( value ),
		} );
		const buttons = tree.children.flat();
		buttons[ 1 ].props.onClick();
		assert.deepEqual( selected, allowReselect ? [ 'skills' ] : [] );
		buttons[ 0 ].props.onClick();
		assert.equal( selected.at( -1 ), 'bank' );
	}
} );
