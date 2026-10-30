import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as compare from './compare';
import ComparePage from './ComparePage';

vi.mock( './compare', async ( original ) => ( {
	...( await original< typeof import( './compare' ) >() ),
	readGroups: vi.fn(),
	saveGroup: vi.fn(),
	listSharedSkills: vi.fn(),
	readSharedSkill: vi.fn(),
	saveSharedSkill: vi.fn(),
	previewRoadmap: vi.fn(),
	createRoadmap: vi.fn(),
	readRoadmap: vi.fn(),
	saveRoadmap: vi.fn(),
	readAudit: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };
const groups: compare.ComparisonGroups = {
	items: [
		{
			id: 11,
			name: 'Cambridge',
			code: '',
			version: '2026',
			path: [ 'Mathematics' ],
			item_type: 'syllabus',
		},
		{
			id: 12,
			name: 'National',
			code: '',
			version: '',
			path: [ 'Mathematics' ],
			item_type: 'syllabus',
		},
		{
			id: 20,
			name: 'Algebra roadmap',
			code: '',
			version: '',
			path: [ 'Mathematics' ],
			item_type: 'roadmap',
		},
	],
	selected: { Mathematics: [ 11 ] },
	revision: '0123456789abcdef',
};
const skill: compare.SharedSkillSummary = {
	id: 7,
	name: 'Solve equations',
	description: '',
	topic: 'Algebra',
	subject: 'Mathematics',
	mapped_from: [
		{ id: 1, specific_id: 3, name: 'Cambridge L1', relation: 'equivalent' },
	],
	revision: 'a'.repeat( 64 ),
};
const roadmap: compare.RoadmapState = {
	id: 20,
	name: 'Algebra roadmap',
	syllabus_ids: [ 11 ],
	skills: [
		{ id: 7, name: 'Solve equations', folder_id: 1 },
		{ id: 8, name: 'Graph lines', folder_id: 1 },
	],
	revision: 'b'.repeat( 64 ),
};
const stale = ( code: string ) =>
	new ApiError( { status: 409, code, message: 'Changed.' } );

function open( route = '/content-hub/curriculum/compare' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/content-hub/curriculum/compare"
					element={ <ComparePage /> }
				/>
				<Route
					path="/content-hub/curriculum/syllabus/:id"
					element={ <p>Syllabus editor reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( compare.readGroups ).mockResolvedValue( groups );
	vi.mocked( compare.listSharedSkills ).mockResolvedValue( {
		items: [ skill ],
		total: 1,
	} );
	vi.mocked( compare.readRoadmap ).mockResolvedValue( roadmap );
} );

describe( 'ComparePage', () => {
	it( 'opens legacy roadmap bookmarks in the syllabus editor', async () => {
		open( '/content-hub/curriculum/compare?roadmap=20' );
		expect(
			await screen.findByText( 'Syllabus editor reached' )
		).toBeVisible();
	} );

	it( 'saves a comparison group with its revision and keeps edits after a stale save', async () => {
		const user = userEvent.setup();
		vi.mocked( compare.saveGroup )
			.mockRejectedValueOnce( stale( 'ohmylms_comparison_stale' ) )
			.mockResolvedValueOnce( {
				...groups,
				selected: { Mathematics: [ 11, 12 ] },
				revision: 'fedcba9876543210',
			} );
		vi.mocked( compare.readGroups )
			.mockResolvedValueOnce( groups )
			.mockResolvedValueOnce( {
				...groups,
				revision: '1111111111111111',
			} );
		open();
		const table = await screen.findByRole( 'table', {
			name: 'Comparison groups',
		} );
		expect( within( table ).getByText( 'Cambridge' ) ).toBeVisible();
		await user.click(
			screen.getByRole( 'button', { name: 'Edit Mathematics' } )
		);
		await user.click(
			screen.getByRole( 'checkbox', { name: 'National' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save group' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Reload groups and keep my edits',
			} )
		);
		expect(
			await screen.findByText( /Your unsaved edits were kept/, notice )
		).toBeVisible();
		expect(
			screen.getByRole( 'checkbox', { name: 'National' } )
		).toBeChecked();
		await user.click(
			screen.getByRole( 'button', { name: 'Save group' } )
		);
		await screen.findByText( 'Comparison group saved.', notice );
		expect( compare.saveGroup ).toHaveBeenNthCalledWith(
			1,
			'Mathematics',
			[ 11, 12 ],
			'0123456789abcdef',
			expect.any( AbortSignal )
		);
		expect( compare.saveGroup ).toHaveBeenLastCalledWith(
			'Mathematics',
			[ 11, 12 ],
			'1111111111111111',
			expect.any( AbortSignal )
		);
	} );

	it( 'previews before creating a roadmap and saves step order with the revision', async () => {
		const user = userEvent.setup();
		vi.mocked( compare.previewRoadmap ).mockResolvedValue( {
			skills: [ { id: 7, name: 'Solve equations' } ],
			sources: [ { id: 11, name: 'Cambridge', version: '' } ],
			unmapped: 2,
		} );
		vi.mocked( compare.createRoadmap ).mockResolvedValue( 20 );
		vi.mocked( compare.saveRoadmap ).mockResolvedValue( {
			...roadmap,
			skills: [ ...roadmap.skills ].reverse(),
		} );
		open( '/content-hub/curriculum/compare?tab=roadmaps' );
		const form = within(
			await screen.findByRole( 'form', { name: 'New roadmap' } )
		);
		await user.type( form.getByLabelText( 'Roadmap name' ), 'Algebra' );
		await user.click( form.getByRole( 'checkbox', { name: /Cambridge/ } ) );
		expect(
			form.getByRole( 'button', { name: 'Create draft roadmap' } )
		).toBeDisabled();
		await user.click(
			form.getByRole( 'button', { name: 'Preview shared skills' } )
		);
		expect( await form.findByText( /1 shared skills/ ) ).toBeVisible();
		await user.click(
			form.getByRole( 'button', { name: 'Create draft roadmap' } )
		);
		await waitFor( () =>
			expect( compare.createRoadmap ).toHaveBeenCalledWith(
				'Algebra',
				[ 11 ],
				expect.any( AbortSignal )
			)
		);
		const editor = within(
			await screen.findByRole( 'region', { name: 'Roadmap steps' } )
		);
		await user.click(
			await editor.findByRole( 'button', {
				name: 'Move Graph lines up',
			} )
		);
		await user.click(
			editor.getByRole( 'button', { name: 'Save roadmap' } )
		);
		await waitFor( () =>
			expect( compare.saveRoadmap ).toHaveBeenCalledWith(
				20,
				{
					name: 'Algebra roadmap',
					syllabus_ids: [ 11 ],
					skill_ids: [ 8, 7 ],
				},
				'b'.repeat( 64 ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'keeps roadmap edits after a stale save', async () => {
		const user = userEvent.setup();
		vi.mocked( compare.saveRoadmap ).mockRejectedValueOnce(
			stale( 'ohmylms_roadmap_stale' )
		);
		open( '/content-hub/curriculum/compare?tab=roadmaps' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Edit steps of Algebra roadmap',
			} )
		);
		const editor = within(
			await screen.findByRole( 'region', { name: 'Roadmap steps' } )
		);
		await user.click(
			await editor.findByRole( 'button', { name: 'Remove Graph lines' } )
		);
		await user.click(
			editor.getByRole( 'button', { name: 'Save roadmap' } )
		);
		await editor.findByRole( 'button', {
			name: 'Reload roadmap and keep my edits',
		} );
		expect(
			editor.getByRole( 'button', { name: 'Save roadmap' } )
		).toBeDisabled();
		expect( editor.queryByText( 'Graph lines' ) ).not.toBeInTheDocument();
	} );

	it( 'edits a shared skill against its revision', async () => {
		const user = userEvent.setup();
		vi.mocked( compare.saveSharedSkill )
			.mockRejectedValueOnce( stale( 'ohmylms_shared_stale' ) )
			.mockResolvedValueOnce( { ...skill, name: 'Solve linear' } );
		vi.mocked( compare.readSharedSkill ).mockResolvedValue( {
			...skill,
			revision: 'c'.repeat( 64 ),
		} );
		open( '/content-hub/curriculum/compare?tab=shared' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Edit Solve equations',
			} )
		);
		const form = within(
			screen.getByRole( 'form', { name: 'Edit shared skill' } )
		);
		expect( form.getByText( /all 1 mapped objectives/ ) ).toBeVisible();
		await user.clear( form.getByLabelText( 'Name' ) );
		await user.type( form.getByLabelText( 'Name' ), 'Solve linear' );
		await user.click(
			form.getByRole( 'button', { name: 'Save shared skill' } )
		);
		await user.click(
			await form.findByRole( 'button', {
				name: 'Reload and keep my edits',
			} )
		);
		expect( form.getByLabelText( 'Name' ) ).toHaveValue( 'Solve linear' );
		await user.click(
			form.getByRole( 'button', { name: 'Save shared skill' } )
		);
		await screen.findByText( 'Saved Solve linear.', notice );
		expect( compare.saveSharedSkill ).toHaveBeenLastCalledWith(
			7,
			expect.objectContaining( { name: 'Solve linear' } ),
			'c'.repeat( 64 ),
			expect.any( AbortSignal )
		);
	} );

	it( 'validates the learner ID and shows the audit', async () => {
		const user = userEvent.setup();
		vi.mocked( compare.readAudit ).mockResolvedValue( [
			{
				subject: 'Mathematics',
				shared: [
					{
						id: 7,
						name: 'Solve equations',
						level: 'Secure',
						evidence: 4,
					},
				],
				syllabuses: [
					{
						id: 11,
						name: 'Cambridge',
						equivalent: 3,
						related_only: 1,
						unmapped: 2,
						unassessed: 5,
						developing: 1,
					},
				],
			},
		] );
		open( '/content-hub/curriculum/compare?tab=audit' );
		const field = await screen.findByLabelText( 'Learner user ID' );
		await user.type( field, 'abc' );
		await user.click(
			screen.getByRole( 'button', { name: 'Show audit' } )
		);
		expect(
			screen.getByText( 'Enter the learner’s user ID.' )
		).toBeVisible();
		expect( compare.readAudit ).not.toHaveBeenCalled();
		await user.clear( field );
		await user.type( field, '42' );
		await user.click(
			screen.getByRole( 'button', { name: 'Show audit' } )
		);
		expect(
			await screen.findByRole( 'table', {
				name: 'Shared mastery in Mathematics',
			} )
		).toBeVisible();
		expect(
			screen.getByText( /3 equivalent, 1 related only/ )
		).toBeVisible();
		expect( compare.readAudit ).toHaveBeenCalledWith(
			42,
			expect.any( AbortSignal )
		);
	} );
} );
