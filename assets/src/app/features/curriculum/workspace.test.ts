import * as transport from '../../lib/api';
import {
	parseCombined,
	parseContentLinks,
	parsePlacement,
	parseQueue,
	parseSuggestion,
	placementProblem,
	reviewSkills,
	reviewSuggestions,
	savePlacement,
	startRun,
	updateContentLinks,
} from './workspace';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );

const sha = 'a'.repeat( 64 );
const user = { id: 1, name: 'Admin' };

const combined = {
	skill: {
		id: 7,
		name: 'Linear equations',
		code: '',
		shared: true,
		subject: 'Maths',
		provenance: { source: 'ai', review: 'approved' },
	},
	placements: [],
	mappings: [
		{
			id: 1,
			specific: { id: 3, name: 'Solve equations', code: 'A.1' },
			shared: { id: 7, name: 'Linear equations', code: '' },
			relation: 'equivalent',
			note: '',
			source: 'ai',
			confidence: 0.91,
			group_uuid: '',
			revision: sha,
			syllabuses: [ { id: 9, name: 'IGCSE' } ],
		},
	],
	groups: [],
	courses: { items: [], total: 0 },
	content: {
		lesson: {
			items: [ { id: 5, title: 'Lesson', status: 'draft' } ],
			total: 1,
		},
		quiz: { items: [], total: 0 },
		assignment: { items: [], total: 0 },
	},
	questions: { items: [], total: 0 },
};

describe( 'skill workspace model', () => {
	it( 'reads a combined view and rejects malformed mappings', () => {
		const view = parseCombined( combined );
		expect( view.mappings[ 0 ] ).toMatchObject( {
			source: 'ai',
			confidence: 0.91,
			syllabuses: [ { id: 9, name: 'IGCSE' } ],
		} );
		expect( view.content.lesson.total ).toBe( 1 );
		expect( () =>
			parseCombined( {
				...combined,
				mappings: [ { ...combined.mappings[ 0 ], revision: 'x' } ],
			} )
		).toThrow();
		expect( () =>
			parseCombined( {
				...combined,
				mappings: [ { ...combined.mappings[ 0 ], confidence: 2 } ],
			} )
		).toThrow();
	} );

	it( 'reads suggestions of every kind', () => {
		const mapping = parseSuggestion( {
			id: 4,
			run_id: 2,
			kind: 'mapping',
			status: 'pending',
			relation: 'equivalent',
			confidence: 0.8,
			reason: 'Same',
			objective: { id: 3, name: 'Solve', code: 'A.1' },
			shared: { id: 7, name: 'Linear' },
			proposal: null,
			placement: null,
			weight: null,
		} );
		expect( mapping.shared?.name ).toBe( 'Linear' );
		const placement = parseSuggestion( {
			id: 5,
			run_id: 2,
			kind: 'placement',
			status: 'pending',
			relation: '',
			confidence: 0.6,
			reason: '',
			objective: null,
			shared: { id: 7, name: 'Linear' },
			placement: { domain: 'algebra', grade_from: 7, grade_to: 8 },
		} );
		expect( placement.placement ).toEqual( {
			domain: 'algebra',
			gradeFrom: 7,
			gradeTo: 8,
		} );
	} );

	it( 'reads content links with roles and a revision', () => {
		const links = parseContentLinks( {
			content: { id: 5, kind: 'question', title: 'Q', status: 'draft' },
			skills: [
				{
					id: 3,
					name: 'Solve',
					code: '',
					shared: false,
					role: 'primary',
					archived: false,
					syllabuses: [ 'IGCSE' ],
				},
			],
			revision: sha,
		} );
		expect( links.skills[ 0 ]?.role ).toBe( 'primary' );
		expect( () =>
			parseContentLinks( {
				content: { id: 5, kind: 'video', title: 'Q', status: 'draft' },
				skills: [],
				revision: sha,
			} )
		).toThrow();
	} );

	it( 'reads placements with an empty domain map and checks level order', () => {
		const page = parsePlacement( {
			items: [],
			total: 0,
			domains: [],
			levels: [ { value: 1, label: 'Grade 1' } ],
		} );
		expect( page.domains ).toEqual( [] );
		expect(
			placementProblem( { domain: '', gradeFrom: 0, gradeTo: 3 } )
		).toBe( 'first' );
		expect(
			placementProblem( { domain: 'x', gradeFrom: 5, gradeTo: 3 } )
		).toBe( 'order' );
		expect(
			placementProblem( { domain: 'x', gradeFrom: 3, gradeTo: 0 } )
		).toBeNull();
	} );

	it( 'reads the provenance queue', () => {
		const queue = parseQueue( {
			items: [
				{
					id: 3,
					name: 'Proposed',
					code: '',
					shared: true,
					subject: 'Maths',
					provenance: {
						source: 'ai',
						ref: 'run:1',
						review: 'pending',
						status: 'active',
						model: 'm',
						confidence: 0.7,
						created_by: user,
						created_at: '2026-10-31 00:00:00',
						reviewed_by: { id: 0, name: '' },
					},
					last_audit: null,
				},
			],
			total: 1,
			counts: { pending: 1, rejected: 0 },
			sources: [ 'manual', 'ai' ],
		} );
		expect( queue.items[ 0 ]?.provenance.review ).toBe( 'pending' );
		expect( queue.counts.pending ).toBe( 1 );
	} );
} );

describe( 'skill workspace requests', () => {
	it( 'starts only the analysis kinds this screen offers', async () => {
		vi.mocked( transport.post ).mockResolvedValue( {
			id: 1,
			kind: 'syllabus',
			syllabus_id: 9,
			syllabus: 'IGCSE',
			subject: 'Maths',
			status: 'queued',
			total: 3,
			processed: 0,
			suggested: 0,
			failures: 0,
			error: '',
			model: 'm',
			pending: 0,
			accepted: 0,
			rejected: 0,
			created_at: 'now',
		} );
		const run = await startRun( {
			kind: 'syllabus',
			syllabus_id: 9,
			include_mapped: false,
		} );
		expect( run.status ).toBe( 'queued' );
		expect( transport.post ).toHaveBeenCalledWith(
			'/ohmylms/v1/syllabus-comparison/ai/runs',
			{ kind: 'syllabus', syllabus_id: 9, include_mapped: false },
			{ signal: undefined }
		);
	} );

	it( 'sends relation overrides and reports per-suggestion errors', async () => {
		vi.mocked( transport.post ).mockResolvedValue( {
			results: { 4: { id: 4, error: 'Accept the new skill first.' } },
		} );
		const outcome = await reviewSuggestions( [ 4 ], 'accept', {
			4: { relation: 'related' },
		} );
		expect( outcome ).toEqual( [
			{ ok: false, id: 4, error: 'Accept the new skill first.' },
		] );
		expect( transport.post ).toHaveBeenCalledWith(
			'/ohmylms/v1/syllabus-comparison/ai/suggestions/review',
			{
				ids: [ 4 ],
				action: 'accept',
				overrides: { 4: { relation: 'related' } },
			},
			{ signal: undefined }
		);
	} );

	it( 'saves content links against the revision it read', async () => {
		const current = parseContentLinks( {
			content: { id: 5, kind: 'lesson', title: 'L', status: 'draft' },
			skills: [],
			revision: sha,
		} );
		vi.mocked( transport.put ).mockResolvedValue( {
			content: { id: 5, kind: 'lesson', title: 'L', status: 'draft' },
			skills: [],
			revision: 'b'.repeat( 64 ),
		} );
		await updateContentLinks( current, [ 3 ], [] );
		expect( transport.put ).toHaveBeenCalledWith(
			'/ohmylms/v1/skill-workspace/content/lesson/5',
			{ add: [ 3 ], remove: [], expected_revision: sha },
			{ signal: undefined }
		);
	} );

	it( 'saves placements with revisions and a single level as a band of one', async () => {
		vi.mocked( transport.put ).mockResolvedValue( { items: [ {} ] } );
		await savePlacement( [
			{
				id: 7,
				name: 'Linear',
				subject: 'Maths',
				topic: '',
				domain: 'algebra',
				gradeFrom: 7,
				gradeTo: 0,
				revision: sha,
			},
		] );
		expect( transport.put ).toHaveBeenCalledWith(
			'/ohmylms/v1/skill-workspace/placement',
			{
				items: [
					{
						id: 7,
						domain: 'algebra',
						grade_from: 7,
						grade_to: 7,
						expected_revision: sha,
					},
				],
			},
			{ signal: undefined }
		);
		await expect( savePlacement( [] ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );

	it( 'sends the review state each decision was made from', async () => {
		vi.mocked( transport.post ).mockResolvedValue( {
			results: {
				3: { review: 'approved' },
				4: {
					code: 'ohmylms_skill_review_stale',
					error: 'Changed.',
					review: 'rejected',
				},
			},
		} );
		const rows = [ 3, 4 ].map( ( id ) => ( {
			id,
			provenance: { review: 'pending' as const },
		} ) );
		const results = await reviewSkills(
			rows as Parameters< typeof reviewSkills >[ 0 ],
			'approved',
			'ok'
		);
		expect( results ).toEqual( [
			{ id: 3, review: 'approved', stale: false },
			{ id: 4, review: 'rejected', error: 'Changed.', stale: true },
		] );
		expect( transport.post ).toHaveBeenCalledWith(
			'/ohmylms/v1/skill-workspace/provenance/review',
			{
				items: [
					{ id: 3, expected_review: 'pending' },
					{ id: 4, expected_review: 'pending' },
				],
				review: 'approved',
				note: 'ok',
			},
			{ signal: undefined }
		);
	} );
} );
