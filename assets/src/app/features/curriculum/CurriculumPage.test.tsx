import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderShell } from '../../test/render';
import * as api from './api';
import CurriculumPage from './CurriculumPage';
import { curriculum } from './index';

vi.mock( './api', async ( original ) => ( {
	...( await original< typeof import( './api' ) >() ),
	readTree: vi.fn(),
	saveItem: vi.fn(),
	moveItem: vi.fn(),
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
	updated_at: '2026-10-30 01:00:00',
};
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.readTree ).mockResolvedValue( [ item ] );
} );
function open( allowed = true ) {
	renderShell( [ { ...curriculum, component: CurriculumPage } ], {
		route: '/content-hub/curriculum',
		capabilities: { manage_options: allowed },
	} );
}
it( 'hides the feature and makes no requests without administrator capability', () => {
	open( false );
	expect( api.readTree ).not.toHaveBeenCalled();
} );
it( 'keeps failed edits visible and sends the versioned item to save', async () => {
	const user = userEvent.setup();
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Mathematics' } )
	);
	await user.clear( screen.getByLabelText( 'Name' ) );
	await user.type( screen.getByLabelText( 'Name' ), 'My subject' );
	vi.mocked( api.saveItem ).mockRejectedValue(
		new Error( 'Try again later' )
	);
	await user.click( screen.getByRole( 'button', { name: 'Save item' } ) );
	await screen.findAllByText( 'Try again later' );
	expect( screen.getByLabelText( 'Name' ) ).toHaveValue( 'My subject' );
	expect( api.saveItem ).toHaveBeenCalledWith(
		expect.objectContaining( { name: 'My subject' } ),
		item,
		0,
		expect.any( AbortSignal )
	);
} );
it( 'excludes descendants from parent choices', () => {
	const child = { ...item, id: 6, parent_id: 5 };
	const grandchild = { ...item, id: 7, parent_id: 6 };
	expect( api.parentChoices( [ item, child, grandchild ], 5 ) ).toEqual( [] );
	expect( api.itemPath( [ item, child ], child ) ).toBe(
		'Mathematics › Mathematics'
	);
} );
