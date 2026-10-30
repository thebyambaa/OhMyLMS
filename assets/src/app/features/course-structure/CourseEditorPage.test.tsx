import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { OutlineWorkspace } from './CourseEditorPage';
import { outline } from './fixtures';

vi.mock( './api', () => ( {
	readOutline: vi.fn(),
	saveOutline: vi.fn(),
	findLessons: vi.fn(),
} ) );
vi.mock( '../curriculum', () => ( { CoursePlacement: () => null } ) );
beforeEach( () => vi.resetAllMocks() );
function open() {
	const reload = vi.fn();
	const result = render(
		<MemoryRouter>
			<OutlineWorkspace initial={ outline } reload={ reload } />
		</MemoryRouter>
	);
	return { ...result, reload };
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
	vi.mocked( api.findLessons ).mockResolvedValue( {
		pages: 1,
		items: [ { id: 30, title: 'Reusable lesson' } ],
	} );
	await user.click(
		screen.getAllByRole( 'button', { name: 'Attach lesson to unit' } )[ 0 ]!
	);
	await user.click(
		await screen.findByRole( 'button', { name: 'Reusable lesson' } )
	);
	vi.mocked( api.saveOutline ).mockResolvedValue( outline );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await waitFor( () => expect( api.saveOutline ).toHaveBeenCalledOnce() );
	const payload = vi.mocked( api.saveOutline ).mock.calls[ 0 ]![ 1 ];
	expect( payload ).toMatchObject( {
		program: {
			items: [
				outline.program.items[ 0 ],
				{ type: 'lesson', content_id: 30, chapter_id: 8 },
			],
		},
	} );
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
	await user.click( screen.getByRole( 'button', { name: 'Detach lesson' } ) );
	expect(
		screen.getByText( /The reusable lesson and learner history remain/ )
	).toBeInTheDocument();
	expect( api.saveOutline ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Detach placement' } )
	);
	vi.mocked( api.saveOutline ).mockResolvedValue( outline );
	await user.click(
		screen.getByRole( 'button', { name: 'Save outline draft' } )
	);
	await waitFor( () => expect( api.saveOutline ).toHaveBeenCalledOnce() );
	expect( vi.mocked( api.saveOutline ).mock.calls[ 0 ]![ 1 ] ).toMatchObject(
		{ program: { items: [], outcomes: outline.program.outcomes } }
	);
} );
