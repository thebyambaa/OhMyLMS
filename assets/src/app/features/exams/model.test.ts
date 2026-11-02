import { exam, job, results, triangle } from './fixtures';
import {
	parseReleaseOutcome,
	parseResults,
	parseRevokeOutcome,
	readFigureSpec,
	skipReasonLabel,
	withObjectLabel,
	addQuestions,
	compositionMarks,
	compositionProblem,
	formFromExam,
	fromLocalInput,
	isFormDirty,
	isWorking,
	moveItem,
	parseDryRun,
	parseExam,
	parseExamDetail,
	parseFigure,
	parseJobDetail,
	toCompositionInput,
	toLocalInput,
	toSettingsInput,
	validateSettings,
	withSkill,
} from './model';

const invalid = expect.objectContaining( { code: 'invalid_response' } );

describe( 'exam model', () => {
	it( 'parses the contract and rejects drift', () => {
		expect( parseExamDetail( exam ) ).toEqual( exam );
		expect( () => parseExam( { ...exam, revision: 'stale' } ) ).toThrow(
			invalid
		);
		expect( () => parseExam( { ...exam, status: 'trash' } ) ).toThrow(
			invalid
		);
		expect( () =>
			parseExamDetail( {
				...exam,
				links: { ...exam.links, player: 'javascript:alert(1)' },
			} )
		).toThrow( invalid );
		expect( () =>
			parseExamDetail( {
				...exam,
				composition: [ { kind: 'page' } ],
			} )
		).toThrow( invalid );
	} );
	it( 'parses import jobs, crops and dry-run reports', () => {
		expect( parseJobDetail( job ) ).toEqual( job );
		expect( () => parseJobDetail( { ...job, state: 'done' } ) ).toThrow(
			invalid
		);
		expect( () =>
			parseJobDetail( {
				...job,
				rows: [
					{ ...job.rows[ 0 ], parts: [ { id: 'p1', marks: '2' } ] },
				],
			} )
		).toThrow( invalid );
		expect(
			parseFigure( {
				id: 'f',
				state: 'ready',
				attempts: 1,
				error: '',
				page: 2,
				bounds: [ 0, 0, 1, 1 ],
				width: 10,
				height: 10,
				url: 'https://example.test/wp-json/ohmylms/v1/exam-imports/jobs/7/figures/f',
			} ).state
		).toBe( 'ready' );
		expect(
			parseDryRun( {
				dry_run: true,
				counts: { create: 1, error: 1 },
				rows: [
					{
						row: 2,
						status: 'error',
						errors: [ 'Bad' ],
						warnings: [],
					},
				],
			} ).rows[ 0 ]?.errors
		).toEqual( [ 'Bad' ] );
		expect( () => parseDryRun( { counts: {}, rows: [] } ) ).toThrow(
			invalid
		);
	} );
	it( 'parses the results release contract', () => {
		expect( parseResults( results ) ).toEqual( results );
		expect( () =>
			parseResults( {
				...results,
				policy: { mode: 'after_close', release_at: '' },
			} )
		).toThrow( invalid );
		expect( () =>
			parseResults( {
				...results,
				items: [ { ...results.items[ 0 ], state: 'hidden' } ],
			} )
		).toThrow( invalid );
		expect(
			parseReleaseOutcome( {
				released: [ 31 ],
				skipped: [ { id: 32, reason: 'awaiting_marking' } ],
				notified: 1,
				remaining: 0,
				counts: results.counts,
			} ).skipped
		).toEqual( [ { id: 32, reason: 'awaiting_marking' } ] );
		expect( () =>
			parseRevokeOutcome( { revoked: [ 0 ], skipped: [], counts: {} } )
		).toThrow( invalid );
		expect( () =>
			parseExam( { ...exam, results: { mode: 'manual' } } )
		).toThrow( invalid );
		expect( skipReasonLabel( 'viewed' ) ).toMatch( /already seen/ );
	} );
	it( 'parses figure proposals and reads authored configurations', () => {
		const withProposal = {
			...job,
			rows: [
				{
					...job.rows[ 0 ],
					figures: [
						{
							id: 'f1',
							page: 1,
							bounds: [ 0, 0, 1, 1 ],
							part_ids: [],
							asset_id: 'a',
							alt: 'Triangle',
							not_to_scale: false,
							selected: 'both',
							approved: false,
							spec: triangle,
							proposal: {
								status: 'accepted',
								origin: 'edited',
								display: 'accompany',
								spec: triangle,
							},
						},
					],
				},
				job.rows[ 1 ],
			],
		};
		expect( parseJobDetail( withProposal ) ).toEqual( withProposal );
		expect( () =>
			parseJobDetail( {
				...withProposal,
				rows: [
					{
						...withProposal.rows[ 0 ],
						figures: [
							{
								...withProposal.rows[ 0 ]!.figures[ 0 ],
								proposal: {
									status: 'approved',
									origin: 'authored',
									display: 'replace',
									spec: triangle,
								},
							},
						],
					},
				],
			} )
		).toThrow( invalid );
		expect( readFigureSpec( JSON.stringify( triangle ) ) ).toEqual( {
			spec: triangle,
		} );
		expect( readFigureSpec( '{' ) ).toEqual( {
			error: expect.stringMatching( /not valid JSON/ ),
		} );
		expect(
			readFigureSpec( JSON.stringify( { ...triangle, version: 2 } ) )
		).toEqual( { error: expect.stringMatching( /version/ ) } );
		expect(
			withObjectLabel( triangle, 'B', 'B′' ).objects[ 1 ]?.label
		).toBe( 'B′' );
		expect( triangle.objects[ 1 ]?.label ).toBe( 'B' );
	} );
	it( 'validates settings with the server limits', () => {
		const form = formFromExam( exam );
		expect( validateSettings( form ) ).toEqual( {} );
		expect(
			validateSettings( {
				...form,
				title: ' ',
				duration_minutes: '0',
				grace_seconds: '601',
				available_from: '2026-11-02T10:00',
				available_until: '2026-11-01T10:00',
			} )
		).toEqual( {
			title: expect.any( String ),
			duration_minutes: expect.any( String ),
			grace_seconds: expect.any( String ),
			timing: expect.any( String ),
		} );
		expect( isFormDirty( form, formFromExam( exam ) ) ).toBe( false );
		expect( isFormDirty( { ...form, title: 'x' }, form ) ).toBe( true );
	} );
	it( 'builds the settings payload with UTC times', () => {
		const form = {
			...formFromExam( exam ),
			title: '  Mock  ',
			available_from: '2026-11-01T09:30',
		};
		const input = toSettingsInput( form, exam.revision );
		expect( input ).toMatchObject( {
			revision: exam.revision,
			title: 'Mock',
			duration_minutes: 90,
			timing: {
				available_from: '2026-11-01T09:30:00Z',
				available_until: '',
				grace_seconds: 30,
			},
		} );
		expect( input.timing ).not.toHaveProperty( 'feedback_release' );
		expect( toLocalInput( '2026-11-01T09:30:00Z' ) ).toBe(
			'2026-11-01T09:30'
		);
		expect( fromLocalInput( 'nonsense' ) ).toBe( '' );
	} );
	it( 'edits a composition', () => {
		const items = exam.composition;
		expect( moveItem( items, 0, -1 ) ).toEqual( items );
		expect( moveItem( items, 2, -1 ).map( ( item ) => item.kind ) ).toEqual(
			[ 'section', 'question', 'question' ]
		);
		expect( moveItem( items, 0, 1 )[ 1 ] ).toEqual( items[ 0 ] );
		const added = addQuestions( items, [
			{
				id: 101,
				name: 'dup',
				type: 'x',
				marks: 1,
				approved: true,
				available: true,
			},
			{
				id: 103,
				name: 'New',
				type: 'x',
				marks: 4,
				approved: true,
				available: true,
			},
		] );
		expect( added ).toHaveLength( 4 );
		expect( compositionMarks( added ) ).toBe( 9 );
		expect( toCompositionInput( added ) ).toEqual( [
			{ kind: 'section', title: 'Section A', new_page: false },
			{ kind: 'question', question_id: 101 },
			{ kind: 'question', question_id: 102 },
			{ kind: 'question', question_id: 103 },
		] );
		expect( compositionProblem( items ) ).toBeNull();
		expect(
			compositionProblem( [
				{ kind: 'section', title: ' ', new_page: false },
			] )
		).toEqual( expect.any( String ) );
	} );
	it( 'adds skill references once and knows when to poll', () => {
		expect( withSkill( '', 5 ) ).toBe( '#5' );
		expect( withSkill( '#5; A.1', 5 ) ).toBe( '#5;A.1' );
		expect( withSkill( '#5', 9 ) ).toBe( '#5;#9' );
		expect( isWorking( 'extracting' ) ).toBe( true );
		expect( isWorking( 'ready_for_review' ) ).toBe( false );
	} );
} );
