import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderShell } from '../../test/render';
import * as api from './api';
import { contentHub } from './index';
import SkillsPage from './SkillsPage';

vi.mock( './api', () => ( {
	listSkills: vi.fn(),
	getSkillSummary: vi.fn(),
	saveSkill: vi.fn(),
	changeArchive: vi.fn(),
	listTargets: vi.fn(),
	changeLink: vi.fn(),
} ) );
const skill = {
	id: 7,
	name: 'Algebra',
	code: 'M1',
	description: 'Notes',
	parent: 0,
	prerequisites: [],
	archived: false,
};
const feature = { ...contentHub, component: SkillsPage };
function open( capabilities = { edit_posts: true, manage_options: true } ) {
	return renderShell( [ feature ], {
		route: '/content-hub/skills',
		capabilities,
	} );
}
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listSkills ).mockResolvedValue( {
		items: [ skill ],
		total: 21,
		totalPages: 2,
	} );
	vi.mocked( api.getSkillSummary ).mockResolvedValue( skill );
	vi.mocked( api.listTargets ).mockResolvedValue( {
		items: [ { id: 9, title: 'Lesson A', linked: true } ],
		total: 1,
		totalPages: 1,
	} );
} );
it( 'hides Skills and sends no requests without author capability', () => {
	open( { edit_posts: false, manage_options: false } );
	expect( api.listSkills ).not.toHaveBeenCalled();
	expect(
		screen.queryByRole( 'link', { name: 'Content Hub' } )
	).not.toBeInTheDocument();
} );
it( 'shows the shared accessible table, pages and repeats a search', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	expect(
		screen.getByRole( 'region', { name: 'Skills library' } )
	).toHaveAttribute( 'tabindex', '0' );
	await user.click( screen.getByRole( 'button', { name: 'Next page' } ) );
	await waitFor( () =>
		expect( api.listSkills ).toHaveBeenLastCalledWith(
			2,
			'',
			expect.any( AbortSignal )
		)
	);
	await user.type(
		screen.getByLabelText( 'Search skills by name' ),
		'Algebra'
	);
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await waitFor( () =>
		expect( api.listSkills ).toHaveBeenLastCalledWith(
			1,
			'Algebra',
			expect.any( AbortSignal )
		)
	);
	const count = vi.mocked( api.listSkills ).mock.calls.length;
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await waitFor( () =>
		expect( vi.mocked( api.listSkills ).mock.calls.length ).toBeGreaterThan(
			count
		)
	);
} );
it( 'preserves edits when save fails and retries without closing the form', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	vi.mocked( api.saveSkill )
		.mockRejectedValueOnce( new Error( 'Cycle detected' ) )
		.mockResolvedValueOnce( 8 );
	await user.click( screen.getByRole( 'button', { name: 'Add skill' } ) );
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Add skill' } )
	);
	await user.type(
		dialog.getByLabelText( 'Name', { exact: true } ),
		'Geometry'
	);
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await dialog.findByText( 'Cycle detected' );
	expect( dialog.getByLabelText( 'Name', { exact: true } ) ).toHaveValue(
		'Geometry'
	);
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await within( screen.getByRole( 'main' ) ).findByText( 'Skill saved.' );
	expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
} );
it( 'confirms before archiving', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	await user.click( screen.getByRole( 'button', { name: 'Archive' } ) );
	expect( api.changeArchive ).not.toHaveBeenCalled();
	await user.click(
		within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
			name: 'Confirm',
		} )
	);
	await waitFor( () =>
		expect( api.changeArchive ).toHaveBeenCalledWith(
			7,
			true,
			expect.any( AbortSignal )
		)
	);
} );
it( 'authors can edit skills without archive controls', async () => {
	open( { edit_posts: true, manage_options: false } );
	await screen.findByText( 'Algebra' );
	expect(
		screen.getByRole( 'button', { name: 'Edit skill: Algebra' } )
	).toBeEnabled();
	expect(
		screen.queryByRole( 'button', { name: 'Archive' } )
	).not.toBeInTheDocument();
} );
it( 'updates one relationship and retains errors in the links dialog', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	await user.click( screen.getByRole( 'button', { name: 'Links' } ) );
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Lesson and course links' } )
	);
	await dialog.findByText( 'Lesson A' );
	vi.mocked( api.changeLink ).mockRejectedValueOnce(
		new Error( 'Permission changed' )
	);
	await user.click( dialog.getByRole( 'button', { name: 'Unlink' } ) );
	await dialog.findByText( 'Permission changed' );
	expect( api.changeLink ).toHaveBeenCalledWith(
		7,
		'lessons',
		{ id: 9, title: 'Lesson A', linked: true },
		expect.any( AbortSignal )
	);
} );
it( 'aborts obsolete catalogue requests on navigation away', async () => {
	const view = open();
	await screen.findByText( 'Algebra' );
	const signal = vi.mocked( api.listSkills ).mock.calls[ 0 ]?.[ 2 ];
	view.unmount();
	expect( signal?.aborted ).toBe( true );
} );
