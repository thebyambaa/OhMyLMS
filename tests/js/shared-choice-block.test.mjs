import {
	rememberQuestion,
	stepQuestionHistory,
} from '../../assets/src/features/learning/questionHistory.mjs';
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import {
	questionPrompt,
	questionPromptPatch,
} from '../../assets/src/features/question-editor/questionPrompt.mjs';

import {
	choiceReadyToSave,
	reconcileChoiceSave,
} from '../../assets/src/features/learning/choiceAutosave.mjs';
import { nativeQuestionTypes } from '../../assets/src/features/learning/nativeAuthoring.mjs';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';
import { defaultTemplate } from '../../assets/src/features/question-editor/templateModel.mjs';
import { choiceModePatch } from '../../assets/src/features/question-editor/choiceModel.mjs';
const element = ( type, props, ...children ) => ( { type, props, children } );
function compile( file, scope, expression ) {
	const source = fs
		.readFileSync( file, 'utf8' )
		.replace( /^import [\s\S]*?;$/gm, '' )
		.replace( /export /g, '' );
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
	return new Function(
		...Object.keys( {
			nativeQuestionTypes,
			QUESTION_BLOCK_TYPES,
			NativeQuestionDetails: 'details',
			NativeSaveGuard: 'guard',
			QuestionBlockPreview: 'student-frame',
			BlockControls: 'block-controls',
			ToolbarGroup: 'toolbar',
			ToolbarButton: 'toolbar-button',
			rememberQuestion,
			stepQuestionHistory,
			useSelect: () => false,
			usePaperView: () => false,
			usePaperQuestion: () => ( { view: false } ),
			VariableChips: 'variable-chips',
			variableNames: () => [],
			insertIntoStatement: ( html ) => html,
			...scope,
		} ),
		`${ code }; return ${ expression };`
	)(
		...Object.values( {
			nativeQuestionTypes,
			QUESTION_BLOCK_TYPES,
			NativeQuestionDetails: 'details',
			NativeSaveGuard: 'guard',
			QuestionBlockPreview: 'student-frame',
			BlockControls: 'block-controls',
			ToolbarGroup: 'toolbar',
			ToolbarButton: 'toolbar-button',
			rememberQuestion,
			stepQuestionHistory,
			useSelect: () => false,
			usePaperView: () => false,
			usePaperQuestion: () => ( { view: false } ),
			VariableChips: 'variable-chips',
			variableNames: () => [],
			insertIntoStatement: ( html ) => html,
			...scope,
		} )
	);
}
const nodes = ( tree ) => [
	tree,
	...( tree?.children || [] )
		.flat( Infinity )
		.filter( ( child ) => child && typeof child === 'object' )
		.flatMap( nodes ),
];
const marker = '[[ohmylms-math:latex:inline]]x^2[[/ohmylms-math]]';

test( 'shared block preserves equations without showing prompt or answer format dropdowns', () => {
	let definition;
	let opened = false;
	const scope = {
		createElement: element,
		Fragment: 'fragment',
		createContext: () => ( {} ),
		useState: () => [
			true,
			( value ) => {
				opened = value;
			},
		],
		useRef: () => ( {} ),
		useEffect: () => {},
		__: ( text ) => text,
		RichContentControl: 'rich',
		BankAnswerFields: 'answers',
		SelectControl: 'select',
		QuestionTypeMenu: 'type-menu',
		ToggleControl: 'toggle',
		Dropdown: 'dropdown',
		Modal: 'modal',
		QuestionOptionsBar: 'settings',
		MathVariables: { Provider: 'variables-provider' },
		TemplatePanel: 'template-panel',
		defaultTemplate,
		Button: 'button',
		questionPrompt,
		questionPromptPatch,
		questionTypePatch,
		choiceModePatch,
		getBlockType: () => false,
		registerBlockType: ( name, settings ) => {
			definition = settings;
		},
	};
	const { MultipleChoiceFields, registerMultipleChoiceBlock } = compile(
		'assets/src/features/question-editor/MultipleChoiceBlock.jsx',
		scope,
		'({ MultipleChoiceFields, registerMultipleChoiceBlock })'
	);
	let patch;
	const question = {
		name: 'Legacy prompt',
		questions: [ { id: 9, answer: marker, is_correct: true } ],
		settings: { type: 'multiple-choice', partial_credit: true },
	};
	const tree = MultipleChoiceFields( {
		question,
		onChange: ( value ) => {
			patch = value;
		},
	} );
	const toggle = nodes( tree ).find(
		( node ) =>
			node.type === 'toggle' && node.props[ 'aria-label' ] === 'Variables'
	);
	toggle.props.onChange( true );
	assert.equal( opened, true );
	assert.deepEqual( patch.settings.template, defaultTemplate() );
	assert.equal( patch.settings.partial_credit, true );
	const randomized = { ...question, settings: patch.settings };
	const enabledTree = MultipleChoiceFields( {
		question: randomized,
		onChange: ( value ) => {
			patch = value;
		},
	} );
	assert.deepEqual( enabledTree.props.value, defaultTemplate().variables );
	const modal = nodes( enabledTree ).find(
		( node ) => node.type === 'modal'
	);
	assert.equal( modal.props.isFullScreen, true );
	assert.equal(
		nodes( modal ).find( ( node ) => node.type === 'template-panel' ).props
			.question,
		randomized
	);
	modal.props.onRequestClose();
	assert.equal( opened, false );
	nodes( enabledTree )
		.find( ( node ) => node.props?.[ 'aria-haspopup' ] === 'dialog' )
		.props.onClick();
	assert.equal( opened, true );
	nodes( enabledTree )
		.find(
			( node ) =>
				node.type === 'toggle' &&
				node.props[ 'aria-label' ] === 'Variables'
		)
		.props.onChange( false );
	assert.equal( opened, false );
	assert.equal( patch.settings.template, undefined );
	assert.equal( patch.settings.partial_credit, true );
	const prompt = nodes( tree ).find( ( node ) => node.type === 'rich' );
	assert.equal( Boolean( prompt.props.showToolbar ), false );
	prompt.props.onChange( `<p>Solve ${ marker }</p>` );
	assert.equal( patch.description, `<p>Solve ${ marker }</p>` );
	assert.equal( patch.settings.partial_credit, true );
	const answers = nodes( tree ).find( ( node ) => node.type === 'answers' );
	assert.equal( Boolean( answers.props.showAnswerToolbar ), false );
	assert.equal( answers.props.options[ 0 ].answer, marker );
	const selector = nodes( tree ).find(
		( node ) => node.type === 'type-menu'
	);
	assert.deepEqual(
		selector.props.options.map( ( option ) => option.value ),
		QUESTION_BLOCK_TYPES.map( ( [ id ] ) => id )
	);

	for ( const type of [
		'numerical',
		'structured',
		'graphing',
		'number-line',
		'shade-model',
		'count-blocks',
		'set-clock',
		'make-amount',
		'fill-level',
		'build-chart',
		'grid-build',
		'labeling',
		'hotspot',
		'draw',
	] ) {
		selector.props.onChange( type );
		assert.equal( patch.settings.type, type );
		assert.deepEqual( patch.questions, [] );
		const visual = MultipleChoiceFields( {
			question: { ...question, ...patch },
			onChange: () => {},
		} );
		assert.equal(
			nodes( visual ).find( ( node ) => node.type === 'answers' ).props
				.type,
			type
		);
	}
	selector.props.onChange( 'short-text' );
	assert.equal( patch.settings.type, 'short-text' );
	assert.equal( patch.questions.length, 1 );
	const written = MultipleChoiceFields( {
		question: { ...question, ...patch },
		onChange: () => {},
	} );
	assert.ok(
		nodes( written ).some(
			( node ) => node.type === 'input' && node.props.disabled
		)
	);
	assert.ok( ! nodes( written ).some( ( node ) => node.type === 'answers' ) );
	selector.props.onChange( 'long-text' );
	const long = MultipleChoiceFields( {
		question: { ...question, ...patch },
		onChange: () => {},
	} );
	assert.ok(
		nodes( long ).some(
			( node ) => node.type === 'textarea' && node.props.rows === 3
		)
	);
	registerMultipleChoiceBlock();
	assert.deepEqual( Object.keys( definition.attributes ), [
		'questionId',
		'uuid',
		'delivery',
		'authoringRevision',
	] );
	assert.equal( definition.save(), null );
} );

function lessonHarness( {
	failure = false,
	approvalFailure = false,
	refreshFailure = false,
	pauseSave = false,
	context = {},
} = {} ) {
	let cursor = 0;
	let refCursor = 0;
	const refs = [];
	const timers = [];
	let resumeSave;
	const saveGate = new Promise( ( resolve ) => {
		resumeSave = resolve;
	} );
	const state = [];
	const effects = [];
	const calls = [];
	const attributes = { questionId: 0, uuid: '' };
	const fresh = {
		id: 42,
		uuid: '12345678-1234-1234-1234-123456789abc',
		name: 'Question 1',
		settings: { type: 'single-choice' },
		options: [
			{ id: 21, answer: marker, is_correct: true },
			{ id: 22, answer: 'Wrong', is_correct: false },
		],
		modified: 'revision-2',
		can_edit: true,
		can_approve: true,
		current_version_id: 7,
	};
	const scope = {
		createElement: element,
		Fragment: 'fragment',
		__: ( text ) => text,
		Button: 'button',
		Notice: 'notice',
		Spinner: 'spinner',
		MultipleChoiceFields: 'fields',
		registerMultipleChoiceBlock: () => {},
		getBlockType: () => true,
		apiFetch: () => {},
		useState: ( initial ) => {
			const index = cursor++;
			if ( ! ( index in state ) ) {
				state[ index ] = initial;
			}
			return [
				state[ index ],
				( value ) => {
					state[ index ] = value;
				},
			];
		},
		useEffect: ( effect ) => {
			effects.push( effect );
		},
		useRef: ( initial ) => {
			const index = refCursor++;
			refs[ index ] ||= { current: initial };
			return refs[ index ];
		},
		useCallback: ( callback ) => callback,
		choiceReadyToSave,
		reconcileChoiceSave,
		window: {
			setTimeout: ( callback ) => {
				timers.push( callback );
				return timers.length;
			},
			clearTimeout: () => {},
		},
		useDispatch: () => ( {
			lockPostSaving: ( key ) => calls.push( [ 'lock', key ] ),
			unlockPostSaving: () => {},
		} ),
		createBankQuestion: async ( payload ) => {
			calls.push( [ 'create', payload ] );
			if ( pauseSave ) {
				await saveGate;
			}
			if ( failure ) {
				throw new Error( 'Save refused' );
			}
			return { id: 42 };
		},
		saveQuestionAttributes: async ( id, payload ) =>
			calls.push( [ 'update', id, payload ] ),
		loadBankQuestion: async () => {
			if ( refreshFailure ) {
				throw new Error( 'Refresh refused' );
			}
			return fresh;
		},
		approveQuestion: async ( id, version ) => {
			calls.push( [ 'approve', id, version ] );
			if ( approvalFailure ) {
				throw new Error( 'Approval refused' );
			}
		},
	};
	const Edit = compile(
		'assets/src/features/learning/LessonChoiceBlock.jsx',
		scope,
		'LessonChoiceEdit'
	);
	const render = ( isSelected = true ) => {
		cursor = 0;
		refCursor = 0;
		return Edit( {
			attributes,
			clientId: 'placement-a',
			isSelected,
			context,
			setAttributes: ( patch ) => Object.assign( attributes, patch ),
		} );
	};
	render();
	effects.splice( 0 ).forEach( ( effect ) => effect() );
	const fields = nodes( render() ).find( ( node ) => node.type === 'fields' );
	fields.props.onChange( {
		name: 'Question 1',
		description: `<p>${ marker }</p>`,
		questions: [
			{ answer: marker, is_correct: true },
			{ answer: 'Wrong', is_correct: false },
		],
	} );
	const autosave = async () => {
		render();
		// Run only the autosave effect; the load and post-lock effects have their own lifecycle.
		effects
			.splice( 0 )
			.slice( -1 )
			.forEach( ( effect ) => effect() );
		const pending = timers.pop();
		if ( pending ) {
			await pending();
		}
	};
	return { render, attributes, calls, state, effects, autosave, resumeSave };
}

test( 'lesson save writes equations privately and persists only the question reference in content', async () => {
	const harness = lessonHarness();
	await harness.autosave();
	assert.deepEqual( harness.attributes, {
		questionId: 42,
		uuid: '12345678-1234-1234-1234-123456789abc',
		authoringRevision: 1,
	} );
	assert.equal(
		harness.calls.find( ( call ) => call[ 0 ] === 'create' )[ 1 ]
			.questions[ 0 ].answer,
		marker
	);
	assert.equal( harness.state[ 2 ], false );
	await nodes( harness.render() )
		.find( ( node ) => node.type === 'fields' )
		.props.onApprove();
	assert.deepEqual(
		harness.calls.find( ( call ) => call[ 0 ] === 'approve' ),
		[ 'approve', 42, 7 ]
	);
	assert.equal(
		harness.calls.find( ( call ) => call[ 0 ] === 'update' )[ 2 ]
			.base_modified,
		'revision-2'
	);
} );

test( 'failed lesson saves retain the unsaved draft and do not attach an empty question', async () => {
	const harness = lessonHarness( { failure: true } );
	await harness.autosave();
	assert.equal( harness.attributes.questionId, 0 );
	assert.equal( harness.state[ 2 ], true );
	assert.equal( harness.state[ 0 ].questions[ 0 ].answer, marker );
	assert.equal( harness.state[ 4 ], 'Save refused' );
	assert.ok( harness.calls.some( ( call ) => call[ 0 ] === 'lock' ) );
} );

test( 'approval failure preserves the saved reference and retries update rather than creating a duplicate', async () => {
	const harness = lessonHarness( { approvalFailure: true } );
	await harness.autosave();
	await nodes( harness.render() )
		.find( ( node ) => node.type === 'fields' )
		.props.onApprove();
	assert.equal( harness.attributes.questionId, 42 );
	assert.equal( harness.state[ 4 ], 'Approval refused' );
	assert.equal(
		harness.calls.filter( ( call ) => call[ 0 ] === 'create' ).length,
		1
	);
} );

test( 'refresh failure after creation keeps the identity for retry without creating another question', async () => {
	const harness = lessonHarness( { refreshFailure: true } );
	await harness.autosave();
	assert.equal( harness.attributes.questionId, 42 );
	assert.equal( harness.state[ 2 ], true );
	await nodes( harness.render() )
		.find( ( node ) => node.type === 'notice' )
		.props.actions[ 0 ].onClick();
	assert.equal(
		harness.calls.filter( ( call ) => call[ 0 ] === 'create' ).length,
		1
	);
	assert.equal(
		harness.calls.filter( ( call ) => call[ 0 ] === 'update' ).length,
		0
	);
} );

test( 'automatic save keeps newer edits dirty and available for the next save', async () => {
	const harness = lessonHarness( { pauseSave: true } );
	const pending = harness.autosave();
	const fields = nodes( harness.render() ).find(
		( node ) => node.type === 'fields'
	);
	fields.props.onChange( { description: '<p>Newer typing</p>' } );
	harness.resumeSave();
	await pending;
	assert.equal( harness.state[ 0 ].description, '<p>Newer typing</p>' );
	assert.equal( harness.state[ 2 ], true );
	assert.equal( harness.attributes.questionId, 42 );
	await harness.autosave();
	assert.equal( harness.state[ 2 ], false );
	assert.equal(
		harness.calls.filter( ( call ) => call[ 0 ] === 'create' ).length,
		1
	);
	assert.equal(
		harness.calls.find( ( call ) => call[ 0 ] === 'update' )[ 2 ]
			.description,
		'<p>Newer typing</p>'
	);
} );

test( 'native quiz references reopen visual formats and autosave their private settings', async () => {
	for ( const type of [
		'number-line',
		'shade-model',
		'count-blocks',
		'set-clock',
		'make-amount',
		'fill-level',
		'build-chart',
		'grid-build',
		'labeling',
		'hotspot',
		'draw',
	] ) {
		const harness = lessonHarness( {
			context: { postType: 'ohmylms-quiz' },
		} );
		const draft = {
			name: 'Visual question',
			...questionTypePatch( { settings: {} }, type ),
		};
		if ( [ 'labeling', 'hotspot' ].includes( type ) ) {
			draft.settings.image_url = 'https://example.com/diagram.png';
		}
		harness.state[ 0 ] = draft;
		const fields = nodes( harness.render() ).find(
			( node ) => node.type === 'fields'
		);
		assert.ok( fields, type );
		assert.equal( fields.props.allowWritten, true );
		fields.props.onChange( draft );
		await harness.autosave();
		const payload = harness.calls.find(
			( call ) => call[ 0 ] === 'create'
		)[ 1 ];
		assert.deepEqual( payload.settings, draft.settings );
		assert.deepEqual( payload.questions, [] );
		assert.equal( harness.attributes.questionId, 42 );
		assert.equal( 'settings' in harness.attributes, false );
	}
} );

test( 'native mathematics questions reopen and save through the existing private writer', async () => {
	for ( const type of [ 'numerical', 'structured', 'graphing' ] ) {
		const harness = lessonHarness( {
			context: { postType: 'ohmylms-quiz' },
		} );
		const draft = {
			name: 'Math question',
			...questionTypePatch( { settings: {} }, type ),
		};
		if ( type === 'numerical' ) draft.settings.answer = 0;
		harness.state[ 0 ] = draft;
		const fields = nodes( harness.render() ).find(
			( node ) => node.type === 'fields'
		);
		assert.ok( fields, type );
		fields.props.onChange( draft );
		await harness.autosave();
		const payload = harness.calls.find(
			( call ) => call[ 0 ] === 'create'
		)[ 1 ];
		assert.deepEqual( payload.settings, draft.settings );
		assert.deepEqual( payload.questions, [] );
		assert.equal( 'settings' in harness.attributes, false );
	}
} );

test( 'leaving a question previews the latest unsaved state and returning preserves authoring data', () => {
	const harness = lessonHarness();
	const blurred = nodes( harness.render( false ) );
	assert.equal(
		blurred.some( ( node ) => node.type === 'fields' ),
		false
	);
	const frame = blurred.find( ( node ) => node.type === 'student-frame' );
	assert.equal( frame.props.question.questions[ 0 ].answer, marker );
	assert.equal( frame.props.question.description, `<p>${ marker }</p>` );
	const fields = nodes( harness.render( true ) ).find(
		( node ) => node.type === 'fields'
	);
	assert.equal( fields.props.question, frame.props.question );
	assert.equal( harness.state[ 2 ], true );
} );
