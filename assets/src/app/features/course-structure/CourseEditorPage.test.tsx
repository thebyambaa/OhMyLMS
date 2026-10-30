import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { OutlineWorkspace } from './CourseEditorPage';
import { outline } from './fixtures';
import type { Outline } from './model';

vi.mock( './api', () => ( {
	readOutline: vi.fn(),
	saveOutline: vi.fn(),
	deleteUnit: vi.fn(),
	findResources: vi.fn(),
	createResource: vi.fn(),
} ) );
vi.mock( '../curriculum', () => ( { CoursePlacement: () => null } ) );
beforeEach( () => vi.resetAllMocks() );
function open( initial: Outline = outline ) {
	const reload = vi.fn();
	const result = render(
		<MemoryRouter>
			<OutlineWorkspace initial={ initial } reload={ reload } />
		</MemoryRouter>
	);
	return { ...result, reload };
}
async function saveAndReadPayload(
	user: ReturnType< typeof userEvent.setup >
) {
	vi.mocked( api.saveOutline ).mockResolvedValue( outline );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await waitFor( () => expect( api.saveOutline ).toHaveBeenCalledOnce() );
	return vi.mocked( api.saveOutline ).mock.calls[ 0 ]![ 1 ] as Outline;
}
it( 'saves learning controls and targets while preserving placement and bank settings', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.saveOutline ).mockResolvedValue( {
		...outline,
		revision: 'next',
	} );
	await user.selectOptions(
		screen.getByLabelText( 'Learning mode' ),
		'skill-based'
	);
	await user.selectOptions(
		screen.getByLabelText( 'Mastery target' ),
		'mastered'
	);
	await user.click( screen.getByLabelText( 'Required for completion' ) );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await screen.findAllByText(
		'Outline draft saved. Learner publication is unchanged.'
	);
	expect( vi.mocked( api.saveOutline ).mock.calls[ 0 ]![ 1 ] ).toMatchObject(
		{
			program: {
				...outline.program,
				mode: 'skill-based',
				outcomes: [
					{
						term_id: 7,
						chapter_id: 8,
						required: true,
						target: 'mastered',
					},
				],
			},
		}
	);
} );
it( 'keeps all draft fields and new unit UUIDs when saving', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.saveOutline ).mockResolvedValue( {
		...outline,
		revision: 'next',
	} );
	await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await screen.findAllByText(
		'Outline draft saved. Learner publication is unchanged.'
	);
	const [ , payload ] = vi.mocked( api.saveOutline ).mock.calls[ 0 ]!;
	expect( payload ).toMatchObject( {
		revision: 'read-token',
		program: outline.program,
	} );
	expect( payload ).not.toHaveProperty( 'activate' );
	expect(
		( payload as { units: { id: unknown }[] } ).units[ 2 ]!.id
	).toMatch( /^[a-f0-9-]{36}$/ );
} );
it( 'retains edits after a stale save and confirms before reloading', async () => {
	const user = userEvent.setup();
	const { reload } = open();
	vi.mocked( api.saveOutline ).mockRejectedValue(
		new ApiError( { status: 409, code: 'stale', message: 'stale' } )
	);
	const title = screen.getAllByLabelText( 'Unit title' )[ 0 ]!;
	await user.clear( title );
	await user.type( title, 'My local edits' );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await screen.findAllByText( /This course changed in another session/ );
	expect( title ).toHaveValue( 'My local edits' );
	await user.click(
		screen.getByRole( 'button', { name: 'Reload saved outline' } )
	);
	expect( reload ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Discard and reload' } )
	);
	expect( reload ).toHaveBeenCalledOnce();
} );
it( 'attaches an existing lesson without changing its shared post or other placements', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.findResources ).mockResolvedValue( {
		pages: 1,
		items: [ { id: 30, title: 'Reusable lesson' } ],
	} );
	await user.click(
		screen.getAllByRole( 'button', { name: 'Attach lesson to unit' } )[ 0 ]!
	);
	await user.click(
		await screen.findByRole( 'button', { name: 'Reusable lesson' } )
	);
	expect( api.findResources ).toHaveBeenCalledWith(
		12,
		'lesson',
		'',
		1,
		expect.any( AbortSignal )
	);
	const payload = await saveAndReadPayload( user );
	expect( payload ).toMatchObject( {
		program: {
			items: [
				outline.program.items[ 0 ],
				{ type: 'lesson', content_id: 30, chapter_id: 8 },
			],
		},
	} );
} );
it( 'adds an existing assignment and a newly created draft quiz to units', async () => {
	const user = userEvent.setup();
	open();
	vi.mocked( api.findResources ).mockResolvedValue( {
		pages: 1,
		items: [ { id: 41, title: 'Essay' } ],
	} );
	vi.mocked( api.createResource ).mockResolvedValue( {
		id: 50,
		title: 'Unit check',
	} );
	await user.click(
		screen.getAllByRole( 'button', {
			name: 'Add assignment to unit',
		} )[ 1 ]!
	);
	await user.click( await screen.findByRole( 'button', { name: 'Essay' } ) );
	await user.click(
		screen.getAllByRole( 'button', { name: 'Add quiz to unit' } )[ 0 ]!
	);
	const dialog = await screen.findByRole( 'dialog', { name: 'Add a quiz' } );
	await user.type(
		within( dialog ).getByLabelText( 'New title' ),
		'Unit check'
	);
	await user.click(
		within( dialog ).getByRole( 'button', {
			name: 'Create draft and add',
		} )
	);
	await waitFor( () =>
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
	);
	expect( api.createResource ).toHaveBeenCalledWith(
		'quiz',
		'Unit check',
		expect.any( AbortSignal )
	);
	const payload = await saveAndReadPayload( user );
	expect( payload.program.items ).toMatchObject( [
		outline.program.items[ 0 ],
		{ type: 'quiz', content_id: 50, chapter_id: 8, name: 'Unit check' },
		{ type: 'assignment', content_id: 41, chapter_id: 9 },
	] );
} );
it( 'moves a lesson to another unit and reorders activities within a unit', async () => {
	const user = userEvent.setup();
	const second = {
		id: 'activity-2',
		type: 'quiz',
		content_id: 21,
		chapter_id: 8,
		required: true,
		name: 'Check',
	};
	open( {
		...outline,
		program: {
			...outline.program,
			items: [ ...outline.program.items, second ],
		},
	} );
	await user.click( screen.getByRole( 'button', { name: 'Move Check up' } ) );
	await user.selectOptions(
		screen.getByLabelText( 'Unit for First lesson' ),
		'9'
	);
	const payload = await saveAndReadPayload( user );
	expect( payload.program.items.map( ( item ) => item.id ) ).toEqual( [
		'activity-2',
		'activity-1',
	] );
	expect( payload.program.items[ 1 ] ).toMatchObject( {
		chapter_id: 9,
		content_id: 20,
	} );
	expect( payload.program.items[ 1 ] ).not.toHaveProperty( 'group_id' );
} );
it( 'cancels pending writes on unmount and prevents duplicate saves', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	vi.mocked( api.saveOutline ).mockImplementation(
		() => new Promise( () => {} )
	);
	await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
	await user.dblClick(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	expect( api.saveOutline ).toHaveBeenCalledOnce();
	const signal = vi.mocked( api.saveOutline ).mock.calls[ 0 ]![ 2 ]!;
	unmount();
	expect( signal.aborted ).toBe( true );
} );

it( 'confirms detachment and removes only the selected placement from the draft', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getByRole( 'button', { name: 'Detach First lesson' } )
	);
	expect(
		screen.getByText( /The reusable item and learner history remain/ )
	).toBeInTheDocument();
	expect( api.saveOutline ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Detach placement' } )
	);
	const payload = await saveAndReadPayload( user );
	expect( payload ).toMatchObject( {
		program: { items: [], outcomes: outline.program.outcomes },
	} );
} );

describe( 'deleting units', () => {
	it( 'confirms, deletes a saved unit on the server and shows the fresh outline', async () => {
		const user = userEvent.setup();
		open();
		vi.mocked( api.deleteUnit ).mockResolvedValue( {
			...outline,
			revision: 'after-delete',
			units: [ outline.units[ 0 ]! ],
		} );
		const geometry = screen.getByRole( 'region', { name: 'Geometry' } );
		await user.click(
			within( geometry ).getByRole( 'button', { name: 'Delete unit' } )
		);
		expect( api.deleteUnit ).not.toHaveBeenCalled();
		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete this unit?',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Delete unit' } )
		);
		await screen.findAllByText( /Unit deleted/ );
		expect( api.deleteUnit ).toHaveBeenCalledWith(
			outline,
			9,
			expect.any( AbortSignal )
		);
		expect(
			screen.queryByRole( 'region', { name: 'Geometry' } )
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Saved draft' ) ).toBeInTheDocument();
	} );
	it( 'shows the refusal for a unit that is still in use and keeps it', async () => {
		const user = userEvent.setup();
		open();
		vi.mocked( api.deleteUnit ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_chapter_published',
				message: 'This chapter is part of the published program.',
			} )
		);
		await user.click(
			within(
				screen.getByRole( 'region', { name: 'Algebra' } )
			).getByRole( 'button', { name: 'Delete unit' } )
		);
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Delete unit',
			} )
		);
		await screen.findAllByText(
			'This chapter is part of the published program.'
		);
		expect(
			screen.getByRole( 'region', { name: 'Algebra' } )
		).toBeInTheDocument();
	} );
	it( 'requires a saved outline before deleting a saved unit', async () => {
		const user = userEvent.setup();
		open();
		await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
		await user.click(
			within(
				screen.getByRole( 'region', { name: 'Geometry' } )
			).getByRole( 'button', { name: 'Delete unit' } )
		);
		const dialog = screen.getByRole( 'dialog' );
		expect(
			within( dialog ).getByText( /Save or reload the outline/ )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'button', { name: 'Delete unit' } )
		).toBeDisabled();
	} );
	it( 'removes an unsaved unit locally', async () => {
		const user = userEvent.setup();
		open();
		await user.click( screen.getByRole( 'button', { name: 'Add unit' } ) );
		await user.click(
			within(
				screen.getByRole( 'region', { name: 'New unit' } )
			).getByRole( 'button', { name: 'Delete unit' } )
		);
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Delete unit',
			} )
		);
		expect(
			screen.queryByRole( 'region', { name: 'New unit' } )
		).not.toBeInTheDocument();
		expect( api.deleteUnit ).not.toHaveBeenCalled();
		expect( screen.getByText( 'Saved draft' ) ).toBeInTheDocument();
	} );
} );
