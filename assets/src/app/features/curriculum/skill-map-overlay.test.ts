import {
	map,
	rawClassOverlay,
	rawLearnerOverlay,
	rawTargets,
} from './skill-map-fixtures';
import {
	domainSummary,
	isTarget,
	levelRange,
	parseOverlay,
	parseTargets,
	skillLevel,
} from './skill-map-overlay';

type Raw = ReturnType< typeof rawLearnerOverlay >;

describe( 'skill map overlay model', () => {
	it( 'accepts only learner, class and school targets', () => {
		expect( isTarget( 'learner:12' ) ).toBe( true );
		expect( isTarget( 'class:9' ) ).toBe( true );
		expect( isTarget( 'school:3' ) ).toBe( true );
		for ( const bad of [ '', 'all', 'course:5', 'learner:0', 'class:x' ] ) {
			expect( isTarget( bad ) ).toBe( false );
		}
	} );

	it( 'parses a learner overlay', () => {
		const overlay = parseOverlay( rawLearnerOverlay() );
		expect( overlay.target ).toMatchObject( {
			key: 'learner:12',
			type: 'learner',
			label: 'Ana Example',
		} );
		expect( overlay.skills.map( ( skill ) => skill.level ) ).toEqual( [
			'mastered',
			'attempted',
			'proficient',
		] );
		expect( overlay.counts[ 'not-started' ] ).toBe( 1 );
		expect( overlay.links.report ).toBe( '/students/12/report' );
		expect( overlay.levels[ 0 ] ).toEqual( {
			id: 'mastered',
			label: 'Mastered',
			min: 95,
		} );
	} );

	it( 'rejects malformed overlays', () => {
		const cases: Array< ( raw: Raw ) => unknown > = [
			( raw ) => ( {
				...raw,
				target: { ...raw.target, type: 'course' },
			} ),
			( raw ) => ( { ...raw, target: { ...raw.target, key: 'all' } } ),
			( raw ) => ( {
				...raw,
				skills: [ { ...raw.skills[ 0 ], level: 'expert' } ],
			} ),
			( raw ) => ( {
				...raw,
				skills: [ { ...raw.skills[ 0 ], percent: 101 } ],
			} ),
			( raw ) => ( { ...raw, counts: { ...raw.counts, mastered: -1 } } ),
			( raw ) => ( {
				...raw,
				links: { ...raw.links, report: 'https://example.test/' },
			} ),
			( raw ) => ( {
				...raw,
				links: {
					...raw.links,
					transcript: '/content-hub/gradebook/student?x=<b>',
				},
			} ),
		];
		for ( const change of cases ) {
			expect( () =>
				parseOverlay( change( rawLearnerOverlay() ) )
			).toThrow( 'The server returned invalid authoring data.' );
		}
	} );

	it( 'parses targets and rejects groups whose key does not match their type', () => {
		const raw = rawTargets();
		expect( parseTargets( raw ) ).toEqual( {
			groups: raw.groups,
			groupsTotal: 2,
			learners: raw.learners,
			limit: 20,
			minSearch: 2,
		} );
		expect( () =>
			parseTargets( {
				...raw,
				groups: [ { ...raw.groups[ 0 ], type: 'class' } ],
			} )
		).toThrow();
		expect( () =>
			parseTargets( { ...raw, learners: [ { id: 0, name: 'x' } ] } )
		).toThrow();
	} );

	it( 'treats skills the server did not list as not started by every counted learner', () => {
		const overlay = parseOverlay( rawClassOverlay() );
		expect( skillLevel( overlay, 1 ).percent ).toBe( 64 );
		expect( skillLevel( overlay, 4 ) ).toEqual( {
			id: 4,
			percent: 0,
			level: 'not-started',
			assessed: 0,
			distribution: {
				mastered: 0,
				proficient: 0,
				familiar: 0,
				attempted: 0,
				'not-started': 6,
			},
		} );
	} );

	it( 'counts the skills of each domain at Proficient or above', () => {
		const summary = domainSummary(
			map,
			parseOverlay( rawLearnerOverlay() )
		);
		expect( summary.get( 'number' ) ).toEqual( { total: 2, secure: 1 } );
		expect( summary.get( 'algebra' ) ).toEqual( { total: 2, secure: 1 } );
	} );

	it( 'describes the range of each level', () => {
		const legend = parseOverlay( rawLearnerOverlay() ).levels;
		expect(
			legend.map( ( _, index ) => levelRange( legend, index ) )
		).toEqual( [
			'95% and above',
			'80–94%',
			'50–79%',
			'below 50%',
			'no evidence yet',
		] );
	} );
} );
