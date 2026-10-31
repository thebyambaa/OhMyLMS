import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as hub from '../content-hub';
import ComparePage from './ComparePage';
import {
	curriculumCompare,
	skillWorkspaceAiRuns,
	skillWorkspaceContentLinks,
	skillWorkspaceMappings,
	skillWorkspacePlacement,
	skillWorkspaceProvenance,
	skillWorkspaceQuizzes,
} from './index';
import SkillWorkspace from './SkillWorkspace';
import * as mapApi from './skill-map';
import * as api from './workspace';

vi.mock( './workspace', async ( original ) => ( {
	...( await original< typeof import( './workspace' ) >() ),
	readCombined: vi.fn(),
	removeMapping: vi.fn(),
	removeCombinedGroup: vi.fn(),
	listRuns: vi.fn(),
	listSuggestions: vi.fn(),
	reviewSuggestions: vi.fn(),
	runAction: vi.fn(),
	startRun: vi.fn(),
	acceptConfident: vi.fn(),
	searchContent: vi.fn(),
	readContentLinks: vi.fn(),
	updateContentLinks: vi.fn(),
	readPlacement: vi.fn(),
	savePlacement: vi.fn(),
	readSkillQuizzes: vi.fn(),
	linkQuiz: vi.fn(),
	generateQuiz: vi.fn(),
	readQueue: vi.fn(),
	readProvenance: vi.fn(),
	reviewSkills: vi.fn(),
} ) );
vi.mock( './compare', async ( original ) => ( {
	...( await original< typeof import( './compare' ) >() ),
	readGroups: vi.fn().mockResolvedValue( {
		items: [
			{
				id: 9,
				name: 'IGCSE',
				code: '',
				version: '',
				path: [],
				item_type: 'syllabus',
			},
		],
		selected: { Maths: [ 9 ] },
		revision: 'abcdef0123456789',
	} ),
} ) );
vi.mock( './skill-map', async ( original ) => ( {
	...( await original< typeof import( './skill-map' ) >() ),
	readSubjects: vi.fn(),
} ) );
vi.mock( '../content-hub', async ( original ) => ( {
	...( await original< typeof import( '../content-hub' ) >() ),
	listSkills: vi.fn(),
} ) );

const sha = 'a'.repeat( 64 );
const conflict = () =>
	new ApiError( { status: 409, code: 'stale', message: 'Changed.' } );
const features = [
	{ ...curriculumCompare, component: ComparePage },
	...[
		skillWorkspaceMappings,
		skillWorkspaceAiRuns,
		skillWorkspaceContentLinks,
		skillWorkspacePlacement,
		skillWorkspaceQuizzes,
		skillWorkspaceProvenance,
	].map( ( feature ) => ( { ...feature, component: SkillWorkspace } ) ),
];
function open( route: string ) {
	renderShell( features, {
		route,
		capabilities: { manage_options: true },
	} );
}
const mapping: api.MappingRow = {
	id: 1,
	specific: { id: 3, name: 'Solve equations', code: 'A.1' },
	shared: { id: 7, name: 'Linear equations', code: '' },
	relation: 'equivalent',
	note: '',
	source: 'ai',
	confidence: 0.91,
	groupUuid: '',
	revision: sha,
	syllabuses: [ { id: 9, name: 'IGCSE' } ],
};
const view: api.CombinedView = {
	skill: {
		id: 7,
		name: 'Linear equations',
		code: '',
		shared: true,
		subject: 'Maths',
		source: 'manual',
		review: 'approved',
	},
	placements: [],
	mappings: [ mapping ],
	groups: [],
	courses: { items: [], total: 0 },
	content: {
		lesson: { items: [], total: 0 },
		quiz: { items: [], total: 0 },
		assignment: { items: [], total: 0 },
	},
	questions: { items: [], total: 0 },
};
beforeEach( () => {
	vi.mocked( api.readCombined ).mockReset();
	vi.mocked( api.removeMapping ).mockReset();
	vi.mocked( api.reviewSkills ).mockReset();
	vi.mocked( api.updateContentLinks ).mockReset();
	vi.mocked( api.reviewSuggestions ).mockReset();
	vi.mocked( api.savePlacement ).mockReset();
} );

describe( 'Skill workspace', () => {
	it( 'is a Content Hub › Skills section, also opened by the former comparison tab', async () => {
		vi.mocked( api.readQueue ).mockResolvedValue( {
			items: [],
			total: 0,
			counts: { pending: 0, rejected: 0 },
			sources: [ 'manual' ],
		} );
		open( '/content-hub/curriculum/compare?tab=provenance' );
		expect(
			await screen.findByRole( 'heading', { name: 'Skill workspace' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'navigation', { name: 'Comparison sections' } )
		).toBeNull();
		expect(
			await screen.findByText( 'Nothing to review here.' )
		).toBeInTheDocument();
	} );

	it( 'leaves the workspace sections out of the comparison screen', async () => {
		open( '/content-hub/curriculum/compare' );
		const nav = await screen.findByRole( 'navigation', {
			name: 'Comparison sections',
		} );
		expect(
			within( nav ).queryByRole( 'link', { name: 'Combined mappings' } )
		).toBeNull();
	} );

	it( 'shows a skill’s mappings with their source and removes one after confirmation', async () => {
		const user = userEvent.setup();
		vi.mocked( api.readCombined ).mockResolvedValue( view );
		vi.mocked( api.removeMapping ).mockRejectedValueOnce( conflict() );
		open( '/content-hub/skills/mappings?skill=7' );
		expect( await screen.findByText( 'AI run (91%)' ) ).toBeInTheDocument();
		expect( screen.getByText( 'IGCSE' ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Remove mapping Solve equations to Linear equations',
			} )
		);
		const dialog = screen.getByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Remove' } )
		);
		expect(
			await within( dialog ).findByText( /changed after you opened it/ )
		).toBeInTheDocument();
		expect( api.removeMapping ).toHaveBeenCalledWith(
			mapping,
			expect.any( AbortSignal )
		);
	} );

	it( 'reviews pending skills with the state they were seen in', async () => {
		const user = userEvent.setup();
		const row = {
			id: 3,
			name: 'Proposed skill',
			code: '',
			shared: true,
			subject: 'Maths',
			provenance: {
				source: 'ai',
				ref: 'run:1',
				review: 'pending' as const,
				status: 'active',
				model: 'model-x',
				confidence: 0.7,
				createdBy: { id: 1, name: 'Admin' },
				createdAt: '2026-10-31',
				reviewedBy: { id: 0, name: '' },
			},
			lastAudit: null,
		};
		vi.mocked( api.readQueue ).mockResolvedValue( {
			items: [ row ],
			total: 1,
			counts: { pending: 1, rejected: 0 },
			sources: [ 'manual', 'ai' ],
		} );
		vi.mocked( api.reviewSkills ).mockResolvedValue( [
			{ id: 3, error: 'Someone reviewed it.', stale: true },
		] );
		open( '/content-hub/skills/provenance' );
		expect( await screen.findByText( 'model-x' ) ).toBeInTheDocument();
		await user.type(
			screen.getByLabelText( 'Note for the audit trail (optional)' ),
			'Checked'
		);
		await user.click( screen.getByRole( 'button', { name: 'Approve' } ) );
		expect( api.reviewSkills ).toHaveBeenCalledWith(
			[ row ],
			'approved',
			'Checked',
			expect.any( AbortSignal )
		);
		expect(
			(
				await screen.findAllByText( 'Skill 3: Someone reviewed it.' )
			)[ 0 ]
		).toBeInTheDocument();
	} );

	it( 'stages skills for a lesson and keeps them through a stale reload', async () => {
		const user = userEvent.setup();
		const links: api.ContentLinks = {
			content: {
				id: 5,
				kind: 'lesson',
				title: 'Fractions',
				status: 'draft',
			},
			skills: [],
			revision: sha,
		};
		vi.mocked( api.searchContent ).mockResolvedValue( {
			items: [],
			total: 0,
			pages: 0,
		} );
		vi.mocked( api.readContentLinks ).mockResolvedValue( links );
		vi.mocked( hub.listSkills ).mockResolvedValue( {
			items: [
				{
					id: 3,
					name: 'Solve equations',
					code: 'A.1',
					description: '',
					parent: 0,
					prerequisites: [],
					archived: false,
				},
			],
			total: 1,
			totalPages: 1,
		} as Awaited< ReturnType< typeof hub.listSkills > > );
		vi.mocked( api.updateContentLinks ).mockRejectedValueOnce( conflict() );
		open( '/content-hub/skills/content-links?kind=lesson&content=5' );
		expect(
			await screen.findByRole( 'link', { name: 'Fractions' } )
		).toBeInTheDocument();
		await user.type(
			screen.getByRole( 'textbox', { name: 'Add a skill or objective' } ),
			'solve'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Search skills' } )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'A.1 Solve equations' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save links (+1, −0)' } )
		);
		expect( api.updateContentLinks ).toHaveBeenCalledWith(
			links,
			[ 3 ],
			[],
			expect.any( AbortSignal )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Reload links and keep my changes',
			} )
		);
		expect(
			await screen.findByRole( 'button', { name: 'Save links (+1, −0)' } )
		).toBeEnabled();
	} );

	it( 'rejects a suggestion of a run', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listRuns ).mockResolvedValue( [] );
		vi.mocked( api.listSuggestions ).mockResolvedValue( {
			items: [
				{
					id: 4,
					runId: 1,
					kind: 'mapping',
					status: 'pending',
					relation: 'equivalent',
					confidence: 0.8,
					reason: 'Same ability',
					objective: { id: 3, name: 'Solve', code: '' },
					shared: { id: 7, name: 'Linear equations' },
					proposal: null,
					placement: null,
					weight: null,
				},
			],
			total: 1,
		} );
		vi.mocked( api.reviewSuggestions ).mockResolvedValue( [] );
		// The former comparison address keeps the rest of its query.
		open( '/content-hub/curriculum/compare?tab=ai&run=1' );
		expect( await screen.findByText( 'Same ability' ) ).toBeInTheDocument();
		await user.selectOptions(
			screen.getAllByLabelText( 'Relation' ).at( -1 ) as HTMLElement,
			'related'
		);
		await user.click( screen.getByRole( 'button', { name: 'Accept' } ) );
		expect( api.reviewSuggestions ).toHaveBeenCalledWith(
			[ 4 ],
			'accept',
			{ 4: { relation: 'related' } },
			expect.any( AbortSignal )
		);
	} );

	it( 'saves staged placements as one batch', async () => {
		const user = userEvent.setup();
		vi.mocked( mapApi.readSubjects ).mockResolvedValue( [
			{ subject: 'Maths', skills: 1, syllabuses: [] },
		] );
		const row: api.PlacementRow = {
			id: 7,
			name: 'Linear equations',
			subject: 'Maths',
			topic: '',
			domain: '',
			gradeFrom: 0,
			gradeTo: 0,
			revision: sha,
		};
		vi.mocked( api.readPlacement ).mockResolvedValue( {
			items: [ row ],
			total: 1,
			domains: [ { id: 'algebra', label: 'Algebra' } ],
			levels: [
				{ value: 1, label: 'Grade 1' },
				{ value: 2, label: 'Grade 2' },
			],
		} );
		vi.mocked( api.savePlacement ).mockResolvedValue( 1 );
		open( '/content-hub/skills/placement?subject=Maths' );
		await user.selectOptions(
			await screen.findByLabelText( 'Domain of Linear equations' ),
			'algebra'
		);
		await user.selectOptions(
			screen.getByLabelText( 'First level of Linear equations' ),
			'2'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Last level of Linear equations' ),
			'1'
		);
		expect(
			screen.getByRole( 'button', { name: 'Save 1 placements' } )
		).toBeDisabled();
		await user.selectOptions(
			screen.getByLabelText( 'Last level of Linear equations' ),
			'2'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save 1 placements' } )
		);
		await waitFor( () =>
			expect( api.savePlacement ).toHaveBeenCalledWith(
				[ { ...row, domain: 'algebra', gradeFrom: 2, gradeTo: 2 } ],
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'only generates a quiz the question bank can supply', async () => {
		vi.mocked( api.readSkillQuizzes ).mockResolvedValue( {
			skill: { id: 7, name: 'Linear equations' },
			available: 2,
			quizzes: [],
		} );
		open( '/content-hub/skills/skill-quizzes?skill=7' );
		expect(
			await screen.findByText( /2 approved question families/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Generate draft quiz' } )
		).toBeDisabled();
		expect(
			screen.getAllByText( /not have enough approved questions/ )[ 0 ]
		).toBeInTheDocument();
	} );
} );
