import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { renderShell } from '../../test/render';
import * as api from './api';
import { outline } from './fixtures';
import { syllabusEditor } from './index';
import SyllabusPage, { SyllabusWorkspace } from './SyllabusPage';

vi.mock( './api', () => ( {
	command: vi.fn(),
	readSyllabus: vi.fn(),
	readMappings: vi.fn(),
	sharedSkills: vi.fn(),
} ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.command ).mockResolvedValue( outline );
} );
function open() {
	return render(
		<MemoryRouter>
			<SyllabusWorkspace initial={ outline } />
		</MemoryRouter>
	);
}
it( 'makes no syllabus requests without administrator capability', () => {
	renderShell( [ { ...syllabusEditor, component: SyllabusPage } ], {
		route: '/content-hub/curriculum/syllabus/5',
		capabilities: {},
	} );
	expect( api.readSyllabus ).not.toHaveBeenCalled();
} );
it( 'sends group ordering without altering skill identities', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getAllByRole( 'button', { name: 'Move skill down' } )[ 0 ]!
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'move_skill',
		{ group_id: 7, term_id: 10, destination: 7, position: 1 },
		expect.any( AbortSignal )
	);
} );
it( 'keeps edited details after a rejected write and sends only writable fields', async () => {
	const user = userEvent.setup();
	open();
	await user.click( screen.getByRole( 'button', { name: 'Edit group' } ) );
	const dialog = within( screen.getByRole( 'dialog' ) );
	await user.clear( dialog.getByLabelText( 'Name' ) );
	await user.type( dialog.getByLabelText( 'Name' ), 'New group name' );
	vi.mocked( api.command ).mockRejectedValue( new Error( 'Save rejected' ) );
	await user.click( dialog.getByRole( 'button', { name: 'Save details' } ) );
	await dialog.findAllByText( 'Save rejected' );
	expect( dialog.getByLabelText( 'Name' ) ).toHaveValue( 'New group name' );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'edit_group',
		{ group_id: 7, name: 'New group name', code: 'A', description: '' },
		expect.any( AbortSignal )
	);
} );
it( 'confirms detachment before removing only the chosen placement', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		screen.getAllByRole( 'button', { name: 'Detach skill' } )[ 0 ]!
	);
	expect( api.command ).not.toHaveBeenCalled();
	await user.click(
		screen.getByRole( 'button', { name: 'Detach placement' } )
	);
	await waitFor( () => expect( api.command ).toHaveBeenCalledOnce() );
	expect( api.command ).toHaveBeenCalledWith(
		5,
		'detach_skill',
		{ group_id: 7, term_id: 10 },
		expect.any( AbortSignal )
	);
} );
it( 'creates/synchronizes the same course through an explicit command', async () => {
	const user = userEvent.setup();
	const { unmount } = open();
	vi.mocked( api.command ).mockImplementation(
		() => new Promise( () => {} )
	);
	await user.dblClick(
		screen.getByRole( 'button', { name: 'Update course from syllabus' } )
	);
	expect( api.command ).toHaveBeenCalledOnce();
	const signal = vi.mocked( api.command ).mock.calls[ 0 ]![ 3 ]!;
	unmount();
	expect( signal.aborted ).toBe( true );
} );
