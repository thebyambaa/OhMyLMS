import { ApiError } from '../../lib/api';
import { countPhrases, parseOverview, recentPath } from './model';

const valid = {
	areas: [
		{
			id: 'courses',
			total: 3,
			counts: { published: 2, drafts: 1, extra: 'ignored' },
		},
		{ id: 'quizzes', total: 1, counts: { to_grade: 4 } },
		{ id: 'skills', total: 5, counts: { archived: 1 } },
	],
	recent: [
		{
			area: 'quizzes',
			id: 12,
			title: 'Fractions',
			status: 'draft',
			updated_at: '2026-10-30T09:00:00Z',
		},
		{
			area: 'question-bank',
			id: 4,
			title: 'Q',
			status: 'publish',
			updated_at: null,
		},
	],
};

describe( 'parseOverview', () => {
	it( 'keeps only the figures each area may report', () => {
		const overview = parseOverview( valid );
		expect( overview.areas[ 0 ] ).toEqual( {
			id: 'courses',
			total: 3,
			counts: { published: 2, drafts: 1 },
		} );
		expect( overview.areas[ 1 ]?.counts ).toEqual( { to_grade: 4 } );
		expect( overview.recent ).toHaveLength( 2 );
	} );

	it.each( [
		[ 'unknown area', { areas: [ { id: 'x', total: 1, counts: {} } ] } ],
		[
			'duplicate area',
			{
				areas: [
					{ id: 'skills', total: 1, counts: {} },
					{ id: 'skills', total: 1, counts: {} },
				],
			},
		],
		[
			'negative total',
			{ areas: [ { id: 'skills', total: -1, counts: {} } ] },
		],
		[
			'fractional count',
			{
				areas: [
					{ id: 'courses', total: 1, counts: { published: 0.5 } },
				],
			},
		],
		[ 'missing counts', { areas: [ { id: 'skills', total: 1 } ] } ],
		[
			'recent of an area without an editor',
			{
				areas: [],
				recent: [
					{
						area: 'skills',
						id: 1,
						title: 'x',
						status: 'draft',
						updated_at: null,
					},
				],
			},
		],
		[
			'bad recent date',
			{
				areas: [],
				recent: [
					{
						area: 'courses',
						id: 1,
						title: 'x',
						status: 'draft',
						updated_at: 'yesterday',
					},
				],
			},
		],
		[ 'areas that are not a list', { areas: {} } ],
	] )( 'rejects %s', ( _name, body ) => {
		expect( () =>
			parseOverview( { areas: [], recent: [], ...( body as object ) } )
		).toThrow( ApiError );
	} );

	it( 'rejects a body that is not an object', () => {
		expect( () => parseOverview( [] ) ).toThrow( ApiError );
		expect( () => parseOverview( null ) ).toThrow( ApiError );
	} );
} );

describe( 'helpers', () => {
	it( 'describes counts in display order', () => {
		const [ courses, quizzes ] = parseOverview( valid ).areas;
		expect( countPhrases( courses! ) ).toEqual( [
			'2 published',
			'1 draft',
		] );
		expect( countPhrases( quizzes! ) ).toEqual( [ '4 attempts to grade' ] );
	} );

	it( 'links recent items to their editors', () => {
		const [ quiz, question ] = parseOverview( valid ).recent;
		expect( recentPath( quiz! ) ).toBe( '/quizzes/12' );
		expect( recentPath( question! ) ).toBe(
			'/content-hub/question-bank/4'
		);
	} );
} );
