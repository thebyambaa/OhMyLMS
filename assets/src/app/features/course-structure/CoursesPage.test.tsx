import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderShell } from '../../test/render';
import * as api from './api';
import CoursesPage from './CoursesPage';
import { courses } from './index';

vi.mock( './api', () => ( { listCourses: vi.fn(), createCourse: vi.fn() } ) );
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [ { id: 12, title: 'Math', status: 'draft' } ],
		pages: 2,
		total: 21,
	} );
} );
function open( allowed = true ) {
	return renderShell( [ { ...courses, component: CoursesPage } ], {
		route: '/content-hub/courses',
		capabilities: { manage_options: allowed },
	} );
}
it( 'does not request private course summaries without the preview capability', () => {
	open( false );
	expect( api.listCourses ).not.toHaveBeenCalled();
} );
it( 'pages course summaries and resets to the first page for a search', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByRole( 'link', { name: 'Math' } );
	await user.click( screen.getByRole( 'button', { name: 'Next' } ) );
	await screen.findByText( '2 / 2' );
	await user.type( screen.getByLabelText( 'Search courses' ), 'Geometry' );
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	expect( api.listCourses ).toHaveBeenLastCalledWith(
		1,
		'Geometry',
		expect.any( AbortSignal )
	);
} );
it( 'keeps the title when creating a draft fails', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByRole( 'link', { name: 'Math' } );
	vi.mocked( api.createCourse ).mockRejectedValue(
		new Error( 'Please retry' )
	);
	await user.type( screen.getByLabelText( 'Course title' ), 'New course' );
	await user.click( screen.getByRole( 'button', { name: 'Create draft' } ) );
	await screen.findAllByText( 'Please retry' );
	expect( screen.getByLabelText( 'Course title' ) ).toHaveValue(
		'New course'
	);
} );
