import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import CurriculumPage from './CurriculumPage';
import { curriculum } from './index';

vi.mock( './api', async ( original ) => ( {
	...( await original< typeof import( './api' ) >() ),
	readWorkspace: vi.fn(),
	saveItem: vi.fn(),
	moveItem: vi.fn(),
	readDeletion: vi.fn(),
	deleteItem: vi.fn(),
} ) );
const item: api.CurriculumItem = {
	id: 5,
	parent_id: 0,
	position: 0,
	name: 'Mathematics',
	item_type: 'subject',
	description: '',
	code: 'M',
	version: '',
	is_syllabus: false,
	course_id: 0,
	icon: '',
	updated_at: '2026-10-30 01:00:00',
};
const dependents: api.Dependents = {
	children: 2,
	descendants: 3,
	links: 4,
	own_links: 1,
	bundles: 1,
	own_bundles: 0,
	groups: 2,
	own_groups: 0,
};
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readWorkspace ).mockResolvedValue( {
		items: [ item ],
		icons: [ { id: 'calculator', label: 'Calculator' } ],
	} );
	vi.mocked( api.readDeletion ).mockResolvedValue( {
		item,
		dependents,
		revision: 'r'.repeat( 64 ),
	} );
} );
function open( allowed = true ) {
	renderShell( [ { ...curriculum, component: CurriculumPage } ], {
		route: '/content-hub/curriculum',
		capabilities: { manage_options: allowed },
	} );
}
it( 'hides the feature and makes no requests without administrator capability', () => {
	open( false );
	expect( api.readWorkspace ).not.toHaveBeenCalled();
} );
it( 'keeps failed edits visible and sends the versioned item to save', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Mathematics' } )
	);
	await user.clear( screen.getByLabelText( 'Name' ) );
	await user.type( screen.getByLabelText( 'Name' ), 'My subject' );
	await user.selectOptions( screen.getByLabelText( 'Icon' ), 'calculator' );
	vi.mocked( api.saveItem ).mockRejectedValue(
		new Error( 'Try again later' )
	);
	await user.click( screen.getByRole( 'button', { name: 'Save item' } ) );
	await screen.findAllByText( 'Try again later' );
	expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'My subject' );
	expect( api.saveItem ).toHaveBeenCalledWith(
		expect.objectContaining( { name: 'My subject', icon: 'calculator' } ),
		item,
		0,
		expect.any( AbortSignal )
	);
} );
it( 'confirms a branch deletion with the previewed revision', async () => {
	const user = userEvent.setup();
	vi.mocked( api.deleteItem ).mockResolvedValue( [ 5, 6, 7, 8 ] );
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Mathematics' } )
	);
	await user.click( screen.getByRole( 'button', { name: 'Delete…' } ) );
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Delete “Mathematics”?' } )
	);
	await dialog.findByText( 'This item has 2 children.' );
	expect( api.deleteItem ).not.toHaveBeenCalled();
	await user.click(
		dialog.getByLabelText(
			'Delete this item and everything under it (4 items in total)'
		)
	);
	expect( dialog.getByText( /4 links to courses/ ) ).toBeVisible();
	await user.click( dialog.getByRole( 'button', { name: 'Delete branch' } ) );
	await screen.findAllByText( 'Curriculum item deleted.' );
	expect( api.deleteItem ).toHaveBeenCalledWith(
		5,
		'delete',
		'r'.repeat( 64 ),
		expect.any( AbortSignal )
	);
} );
it( 'refuses a stale deletion until it is reviewed again', async () => {
	const user = userEvent.setup();
	vi.mocked( api.deleteItem ).mockRejectedValueOnce(
		new ApiError( {
			status: 409,
			code: 'ohmylms_curriculum_stale',
			message: 'The branch changed.',
		} )
	);
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Mathematics' } )
	);
	await user.click( screen.getByRole( 'button', { name: 'Delete…' } ) );
	const dialog = within( screen.getByRole( 'dialog' ) );
	await user.click(
		await dialog.findByRole( 'button', { name: 'Delete item' } )
	);
	await dialog.findByText( /The branch changed/ );
	expect(
		dialog.getByRole( 'button', { name: 'Delete item' } )
	).toBeDisabled();
	await user.click( dialog.getByRole( 'button', { name: 'Review again' } ) );
	await waitFor( () =>
		expect( api.readDeletion ).toHaveBeenCalledTimes( 2 )
	);
	expect(
		await dialog.findByRole( 'button', { name: 'Delete item' } )
	).toBeEnabled();
} );
it( 'excludes descendants from parent choices', () => {
	const child = { ...item, id: 6, parent_id: 5 };
	const grandchild = { ...item, id: 7, parent_id: 6 };
	expect( api.parentChoices( [ item, child, grandchild ], 5 ) ).toEqual( [] );
	expect( api.itemPath( [ item, child ], child ) ).toBe(
		'Mathematics › Mathematics'
	);
} );
it( 'rejects malformed deletion previews', () => {
	expect( () =>
		api.parseDependents( { ...dependents, children: -1 } )
	).toThrow();
	expect( api.parseDependents( dependents ) ).toEqual( dependents );
} );
