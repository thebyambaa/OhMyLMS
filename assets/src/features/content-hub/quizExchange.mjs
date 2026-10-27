/** File types the import dialog offers. The older CSV layout keeps working. */
export const IMPORT_ACCEPT = '.json,.csv,application/json,text/csv';

/**
 * The messages of a failed import or export, one per line.
 *
 * A refused file carries every problem it has (`data.errors`, each with the question number), so
 * the author can fix them all in one pass instead of one upload per problem.
 *
 * @param {Object} error Error thrown by apiFetch.
 * @return {string[]} Messages.
 */
export function problemLines( error ) {
	const errors = Array.isArray( error?.data?.errors )
		? error.data.errors
		: [];
	const lines = errors
		.map( ( problem ) =>
			problem?.question
				? `Question ${ problem.question }: ${ problem.message }`
				: String( problem?.message || '' )
		)
		.filter( Boolean );
	return lines.length
		? lines
		: [ String( error?.message || '' ) ].filter( Boolean );
}

/**
 * Question types in a file summary, most common first, as readable names.
 *
 * @param {Object} summary `summary` from the import response.
 * @return {{type: string, label: string, count: number}[]} Rows.
 */
export function typeCounts( summary ) {
	return Object.entries( summary?.types || {} )
		.map( ( [ type, count ] ) => ( {
			type,
			count: Number( count ),
			label: type
				.split( '-' )
				.join( ' ' )
				.replace( /^./, ( letter ) => letter.toUpperCase() ),
		} ) )
		.sort(
			( a, b ) => b.count - a.count || a.type.localeCompare( b.type )
		);
}

/**
 * A small file that imports cleanly: a choice question, a fill-in-the-blank and a randomized
 * numerical question. Every other question type is easiest to learn by exporting one.
 *
 * @return {string} File text.
 */
export function exampleFile() {
	return `${ JSON.stringify(
		{
			format: 'ohmylms.quiz',
			version: 1,
			quiz: {
				name: 'Example quiz',
				description: '<p>Made from the example file.</p>',
				settings: {
					allow_attempts: 2,
					time_limit: { value: 10, type: 'minutes' },
					passing_grade: { enabled: true, value: 60 },
				},
			},
			questions: [
				{
					name: 'Question 1',
					description: '<p>What is 2 + 2?</p>',
					settings: {
						type: 'single-choice',
						score: { enabled: true, value: 1 },
						question_code: 'Q-EXAMPLE1',
						hint: 'Count on your fingers.',
						explanation: '<p>Two plus two is four.</p>',
					},
					options: [
						{ answer: '3', is_correct: false },
						{ answer: '4', is_correct: true },
						{ answer: '5', is_correct: false },
					],
				},
				{
					name: '{Paris} is the capital of {France}.',
					description: '<p>{Paris} is the capital of {France}.</p>',
					settings: {
						type: 'fill-in-the-blank',
						score: { enabled: true, value: 2 },
						question_code: 'Q-EXAMPLE2',
						blank_mode: 'drag',
						case_sensitive: false,
					},
					options: [ { answer: '', is_correct: true } ],
				},
				{
					name: 'Question 3',
					description:
						'<p>{{a}} pens cost {{x:2}} each. What is the total?</p>',
					settings: {
						type: 'numerical',
						score: { enabled: true, value: 2 },
						question_code: 'Q-EXAMPLE3',
						answer: 0,
						tolerance: 0.01,
						template: {
							variables: [
								{
									name: 'a',
									type: 'int',
									min: 2,
									max: 9,
									step: 1,
								},
								{
									name: 'x',
									type: 'decimal',
									min: 1,
									max: 5,
									places: 2,
								},
							],
							constraints: [ 'a>2' ],
							set: [ { path: 'answer', expr: 'a*x' } ],
						},
					},
					options: [],
				},
			],
		},
		null,
		2
	) }\n`;
}

/**
 * Save text as a file through the browser.
 *
 * @param {string} filename Suggested name.
 * @param {string} mime     Media type.
 * @param {string} content  File text.
 * @param {Object} env      `document` and `URL`, replaceable in tests.
 */
export function downloadText(
	filename,
	mime,
	content,
	env = { document: window.document, URL: window.URL }
) {
	const blob = new Blob( [ content ], { type: `${ mime };charset=utf-8` } );
	const url = env.URL.createObjectURL( blob );
	const link = env.document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	link.click();
	env.URL.revokeObjectURL( url );
}
