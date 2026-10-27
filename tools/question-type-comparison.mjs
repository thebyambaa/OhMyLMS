import fs from 'node:fs';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../assets/src/features/question-editor/questionBlocks.mjs';
const base = 'http://800.local/wp-content/plugins/OhMyLMS/';
const content = QUESTION_BLOCK_TYPES.map( ( [ type, label ], index ) => {
	const patch = questionTypePatch( {}, type, 10000 + index * 100 );
	const question = {
		name: 'Comparison: ' + label,
		description:
			'<p>' + label + ' example: answer using the controls below.</p>',
		...patch,
	};
	question.questions = ( question.questions || [] ).map( ( row, i ) => ( {
		...row,
		answer: [ 'single-choice', 'multiple-choice' ].includes( type )
			? [ '2', '4', '3', '5' ][ i ]
			: type === 'matching'
				? [ '2 + 2', '3 + 3' ][ i ]
				: type === 'reorder'
					? [ '1', '2' ][ i ]
					: type === 'statement'
						? '4'
						: row.answer,
		is_correct: type === 'multiple-choice' ? true : i === 0,
		matching_data:
			type === 'matching'
				? { label: i === 0 ? '4' : '6' }
				: row.matching_data,
	} ) );
	for ( const row of question.questions ) {
		delete row.id;
		delete row.temp;
	}
	if ( type === 'fill-in-the-blank' ) {
		question.name = '2 + 2 = {4}';
		question.description = '';
	}
	if ( type === 'numerical' ) question.settings.answer = 4;
	if ( type === 'structured' )
		question.settings.parts = [
			{
				id: 'a',
				label: '(a)',
				kind: 'numerical',
				marks: 1,
				prompt: 'Find 2 + 2.',
				answer: 4,
			},
		];
	if ( type === 'categorize' ) {
		question.settings.buckets = [
			{ id: 'b1', label: 'Even' },
			{ id: 'b2', label: 'Odd' },
		];
		question.settings.items = [
			{ id: 'i1', text: '2' },
			{ id: 'i2', text: '3' },
		];
	}
	if ( type === 'build-expression' ) {
		question.settings.correct = [ '2', '+', '2' ];
		question.settings.distractors = [ '3', '−' ];
	}
	if ( [ 'labeling', 'hotspot' ].includes( type ) )
		question.settings.image_url =
			base + 'assets/images/question-type-diagram.svg';
	if ( type === 'labeling' )
		question.settings.targets = [
			{ id: 'a', x: 25, y: 50, answer: 'Circle' },
			{ id: 'b', x: 75, y: 50, answer: 'Square' },
		];
	if ( type === 'hotspot' )
		question.settings.zones = [ { x: 25, y: 50, radius: 10 } ];
	if ( type === 'interactive-video' ) {
		question.settings.video_url =
			'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
		question.settings.checkpoints = [
			{
				id: 'a',
				at: 3,
				prompt: 'What objects are shown? (one word)',
				answer: 'flowers',
			},
		];
	}
	return question;
} );
fs.writeFileSync(
	'build/question-type-comparison.json',
	JSON.stringify(
		{
			name: 'OhMyLMS — every question type comparison',
			status: 'draft',
			description:
				'Compare authoring and learner output for all 38 supported formats.',
			content,
		},
		null,
		2
	)
);
console.log( 'Prepared ' + content.length + ' complete question examples.' );
