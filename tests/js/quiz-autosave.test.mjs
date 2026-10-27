import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import * as model from '../../assets/src/features/quiz-editor/model.mjs';
import {
	promptText,
	questionPrompt,
} from '../../assets/src/features/question-editor/questionPrompt.mjs';
import {
	promptSnapshots,
	quickEditStatus,
} from '../../assets/src/features/quiz-editor/quickEditStatus.mjs';
import {
	quizEditSnapshot,
	mergeSavedQuestions,
} from '../../assets/src/features/quiz-editor/model.mjs';

test( 'autosave ignores server timestamps, usage and duplicated quiz content', () => {
	const before = quizEditSnapshot(
		{ id: 1, name: 'Quiz', modified: 'a', content: [] },
		[
			{
				id: 2,
				name: 'Question',
				modified: 'a',
				usage: { count: 0 },
				settings: { required: false, type: 'single-choice' },
			},
		]
	);
	const after = quizEditSnapshot(
		{
			name: 'Quiz',
			id: 1,
			modified: 'b',
			content: [ {} ],
			saved_ids: [ 2 ],
		},
		[
			{
				settings: { type: 'single-choice', required: false },
				name: 'Question',
				id: 2,
				modified: 'b',
				usage: { count: 2 },
			},
		]
	);
	assert.equal( after, before );
} );

test( 'an edit during saving stays dirty after server IDs are merged', () => {
	const submitted = [ { id: 99, temp: true, name: 'First', questions: [] } ];
	const saved = [ { id: 3, name: 'First', questions: [], modified: 'b' } ];
	const current = [ { ...submitted[ 0 ], name: 'Still typing' } ];
	const merged = mergeSavedQuestions( saved, submitted, current, [ 3 ] );
	assert.notEqual(
		quizEditSnapshot( { id: 1 }, merged ),
		quizEditSnapshot( { id: 1 }, saved )
	);
	assert.equal( merged[ 0 ].id, 3 );
} );

test( 'answer and option changes trigger a new autosave snapshot', () => {
	const question = {
		id: 3,
		settings: { type: 'single-choice', required: false },
		questions: [ { answer: 'A', is_correct: true } ],
	};
	const before = quizEditSnapshot( { id: 1 }, [ question ] );
	assert.notEqual(
		before,
		quizEditSnapshot( { id: 1 }, [
			{ ...question, questions: [ { answer: 'B', is_correct: true } ] },
		] )
	);
	assert.notEqual(
		before,
		quizEditSnapshot( { id: 1 }, [
			{ ...question, settings: { ...question.settings, required: true } },
		] )
	);
} );

function harness() {
	const slots = [];
	const effects = [];
	const timers = new Map();
	const calls = [];
	let cursor = 0;
	let timerId = 0;
	let question = {
		id: 2,
		name: 'Original',
		settings: { type: 'single-choice' },
		questions: [],
	};
	let quiz = { id: 1, name: 'Quiz', content: [ question ] };
	let questions = [ question ];
	const data = {
		getSelectedQuizId: () => 1,
		getQuiz: () => quiz,
		selectQuestion: () => question,
		getAllQuestions: () => questions,
		getQuizTypes: () => [ 'single-choice' ],
		getNotificationMessage: () => '',
		getNotificationStatus: () => '',
	};
	const actions = {
		setQuiz: ( value ) => {
			quiz = { ...quiz, ...value };
		},
		setAllQuestions: ( value ) => {
			questions = value;
		},
		setQuestion: ( value ) => {
			question = value;
		},
		setSelectedQuestionId: () => {},
		setQuizError: () => {},
	};
	const scope = {
		...model,
		questionPrompt,
		promptSnapshots,
		quickEditStatus,
		promptText,
		useSelect: ( fn ) => fn( () => data ),
		useDispatch: () => actions,
		useState: ( initial ) => {
			const i = cursor++;
			slots[ i ] ??= { value: initial };
			return [
				slots[ i ].value,
				( value ) => {
					slots[ i ].value = value;
				},
			];
		},
		useRef: ( initial ) => {
			const i = cursor++;
			return ( slots[ i ] ??= { current: initial } );
		},
		useEffect: ( fn, deps ) => {
			const i = cursor++;
			if (
				! slots[ i ] ||
				deps.some( ( dep, j ) => dep !== slots[ i ].deps[ j ] )
			) {
				slots[ i ]?.cleanup?.();
				slots[ i ] = { deps };
				effects.push( () => {
					slots[ i ].cleanup = fn();
				} );
			}
		},
		loadQuiz: async () => quiz,
		saveQuiz: async ( id, payload ) => {
			calls.push( payload );
			return {
				...payload,
				modified: String( calls.length ),
				saved_ids: payload.content.map( ( q ) => q.id ),
			};
		},
		removeQuestionFromQuiz: () => {},
		publishRevision: () => {},
		appendLinkedQuestions: () => {},
		__: ( text ) => text,
		sprintf: ( text ) => text,
		setTimeout: ( fn ) => {
			const id = ++timerId;
			timers.set( id, fn );
			return id;
		},
		clearTimeout: ( id ) => timers.delete( id ),
	};
	const source = fs
		.readFileSync(
			'assets/src/features/quiz-editor/useQuizEditor.js',
			'utf8'
		)
		.replace( /^import[\s\S]*?;\r?\n/gm, '' )
		.replace( 'export function', 'function' );
	const hook = new Function(
		...Object.keys( scope ),
		source + ';return useQuizEditor;'
	)( ...Object.values( scope ) );
	const validate = ( q ) => ( { isValid: !! q.name } );
	return {
		calls,
		render() {
			cursor = 0;
			const editor = hook( {
				store: 'test',
				validate,
				registerTypes() {},
			} );
			effects.splice( 0 ).forEach( ( fn ) => fn() );
			return editor;
		},
		edit( name ) {
			question = { ...question, name };
			questions = [ question ];
		},
		async tick() {
			const pending = [ ...timers.values() ];
			timers.clear();
			await Promise.all( pending.map( ( fn ) => fn() ) );
		},
	};
}

test( 'autosave debounces edits, saves valid questions and does not loop after success', async () => {
	const h = harness();
	h.render();
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	h.render();
	await h.tick();
	assert.equal( h.calls.length, 0 );
	h.edit( 'First edit' );
	h.render();
	h.edit( 'Final edit' );
	h.render();
	await h.tick();
	assert.equal( h.calls.length, 1 );
	assert.equal( h.calls[ 0 ].content[ 0 ].name, 'Final edit' );
	assert.equal( h.render().autosaveStatus, 'All changes saved' );
	await h.tick();
	assert.equal( h.calls.length, 1 );
	h.edit( '' );
	h.render();
	await h.tick();
	assert.equal( h.calls.length, 1 );
	h.edit( 'Complete again' );
	h.render();
	await h.tick();
	assert.equal( h.calls.length, 2 );
} );
