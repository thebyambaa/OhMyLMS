import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { detail, draftFree, summary } from './fixtures';
import CoursesPage from './CoursesPage';

vi.mock( './api', () => ( {
	listCourses: vi.fn(),
	createCourse: vi.fn(),
	duplicateCourse: vi.fn(),
	trashCourse: vi.fn(),
	restoreCourse: vi.fn(),
	deleteCourse: vi.fn(),
} ) );

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/courses' ] }>
			<Routes>
				<Route path="/courses" element={ <CoursesPage /> } />
				<Route
					path="/courses/:id"
					element={ <p>Editor route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

function rejection( status: number, code: string, message: string ) {
	return new ApiError( { status, code, message } );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [ summary, draftFree ],
		total: 2,
		totalPages: 1,
	} );
} );

describe( 'CoursesPage', () => {
	it( 'lists courses with status, price, students and author', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading courses'
		);
		const table = await screen.findByRole( 'table', { name: 'Courses' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		const first = within( rows[ 1 ]! );
		expect(
			first.getByRole( 'link', { name: 'Algebra I' } )
		).toHaveAttribute( 'href', '/courses/7' );
		expect( first.getByText( 'Published' ) ).toBeInTheDocument();
		expect( first.getByText( 'Beginner' ) ).toBeInTheDocument();
		expect( first.getByText( /29\.00/ ) ).toBeInTheDocument();
		expect( first.getByText( /49\.50/ ).tagName ).toBe( 'S' );
		expect( first.getByText( 'Ada Admin' ) ).toBeInTheDocument();
		expect( first.getByText( '4' ) ).toBeInTheDocument();
		const second = within( rows[ 2 ]! );
		expect( second.getByText( 'Draft' ) ).toBeInTheDocument();
		expect( second.getByText( 'Free' ) ).toBeInTheDocument();
		expect( screen.getAllByText( '2 courses found.' )[ 0 ] ).toHaveClass(
			'displaying-num'
		);
	} );
	it( 'shows an empty state', async () => {
		vi.mocked( api.listCourses ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		renderPage();
		expect(
			await screen.findByText( 'No courses found.' )
		).toBeInTheDocument();
	} );
	it( 'shows a server error with a retry that reloads', async () => {
		vi.mocked( api.listCourses ).mockRejectedValueOnce(
			rejection( 500, 'rest_error', 'The server broke.' )
		);
		const user = userEvent.setup();
		renderPage();
		expect(
			await screen.findByText( /The server broke\./, notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'Courses' } )
		).toBeInTheDocument();
	} );
	it( 'shows a permission error', async () => {
		vi.mocked( api.listCourses ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_course_cannot_view',
				'Sorry, you are not allowed to view courses.'
			)
		);
		renderPage();
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to view courses.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'applies search, status, price and date filters together', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Courses' } );
		await user.type( screen.getByLabelText( 'Search courses' ), ' alg ' );
		await user.click( screen.getByRole( 'link', { name: 'Draft' } ) );
		await screen.findByRole( 'table', { name: 'Courses' } );
		await user.selectOptions( screen.getByLabelText( 'Price' ), 'paid' );
		await user.type(
			screen.getByLabelText( 'Created from' ),
			'2026-01-01'
		);
		await user.type(
			screen.getByLabelText( 'Created until' ),
			'2026-02-01'
		);
		await user.click( screen.getByRole( 'button', { name: 'Filter' } ) );
		await waitFor( () =>
			expect( api.listCourses ).toHaveBeenLastCalledWith(
				1,
				{
					search: 'alg',
					status: 'draft',
					price_type: 'paid',
					created_after: '2026-01-01',
					created_before: '2026-02-01',
				},
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'applies the filters with the keyboard', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Courses' } );
		await user.click( screen.getByLabelText( 'Search courses' ) );
		await user.keyboard( 'geo{Enter}' );
		await waitFor( () =>
			expect( api.listCourses ).toHaveBeenLastCalledWith(
				1,
				expect.objectContaining( { search: 'geo' } ),
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'pages through the results', async () => {
		vi.mocked( api.listCourses ).mockResolvedValue( {
			items: [ summary ],
			total: 40,
			totalPages: 2,
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Courses' } );
		const next = screen.getAllByRole( 'link', { name: 'Next page' } )[ 0 ]!;
		expect( next ).toHaveAttribute( 'href', '/courses?page=2' );
		await user.click( next );
		await waitFor( () =>
			expect( api.listCourses ).toHaveBeenLastCalledWith(
				2,
				expect.anything(),
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'reads the list state from the address and links views to addresses', async () => {
		render(
			<MemoryRouter
				initialEntries={ [ '/courses?status=draft&s=geo&page=3' ] }
			>
				<Routes>
					<Route path="/courses" element={ <CoursesPage /> } />
				</Routes>
			</MemoryRouter>
		);
		await screen.findByRole( 'table', { name: 'Courses' } );
		expect( api.listCourses ).toHaveBeenLastCalledWith(
			3,
			expect.objectContaining( { search: 'geo', status: 'draft' } ),
			expect.any( AbortSignal )
		);
		expect( screen.getByLabelText( 'Search courses' ) ).toHaveValue(
			'geo'
		);
		expect(
			screen.getByRole( 'link', { name: 'Published' } )
		).toHaveAttribute( 'href', '/courses?status=publish&s=geo' );
		expect(
			screen.getByRole( 'link', { name: /^Draft/ } )
		).toHaveAttribute( 'aria-current', 'page' );
	} );

	describe( 'create', () => {
		it( 'creates a draft and opens the editor', async () => {
			vi.mocked( api.createCourse ).mockResolvedValue( {
				...detail,
				id: 31,
			} );
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', { name: 'Add course' } )
			);
			await user.type(
				screen.getByLabelText( /Course title/ ),
				'  Geometry '
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Create course' } )
			);
			expect(
				await screen.findByText( 'Editor route reached' )
			).toBeInTheDocument();
			expect( api.createCourse ).toHaveBeenCalledWith(
				'Geometry',
				expect.any( AbortSignal )
			);
		} );
		it( 'validates the title without calling the server', async () => {
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', { name: 'Add course' } )
			);
			await user.type( screen.getByLabelText( /Course title/ ), '   ' );
			await user.keyboard( '{Enter}' );
			expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
				'Enter a title of 1 to 200 characters.'
			);
			expect( screen.getByLabelText( /Course title/ ) ).toHaveAttribute(
				'aria-invalid',
				'true'
			);
			expect( api.createCourse ).not.toHaveBeenCalled();
		} );
		it( 'shows a server rejection in the dialog and keeps it open', async () => {
			vi.mocked( api.createCourse ).mockRejectedValue(
				rejection(
					403,
					'ohmylms_course_cannot_create',
					'Not allowed to create.'
				)
			);
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', { name: 'Add course' } )
			);
			await user.type( screen.getByLabelText( /Course title/ ), 'X' );
			await user.click(
				screen.getByRole( 'button', { name: 'Create course' } )
			);
			expect(
				await screen.findByText( 'Not allowed to create.', notice )
			).toBeInTheDocument();
			expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
			expect( screen.getByLabelText( /Course title/ ) ).toHaveValue(
				'X'
			);
		} );
	} );

	describe( 'duplicate', () => {
		it( 'duplicates as a draft and reloads', async () => {
			vi.mocked( api.duplicateCourse ).mockResolvedValue( detail );
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', { name: 'Duplicate Algebra I' } )
			);
			expect(
				await screen.findByText(
					'Duplicated Algebra I as a draft.',
					notice
				)
			).toBeInTheDocument();
			expect( api.duplicateCourse ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			);
			await waitFor( () =>
				expect( api.listCourses ).toHaveBeenCalledTimes( 2 )
			);
		} );
		it( 'shows the failure', async () => {
			vi.mocked( api.duplicateCourse ).mockRejectedValue(
				rejection(
					500,
					'ohmylms_course_duplicate_failed',
					'Copy failed.'
				)
			);
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', { name: 'Duplicate Algebra I' } )
			);
			expect(
				await screen.findByText( 'Copy failed.', notice )
			).toBeInTheDocument();
		} );
	} );

	describe( 'trash, restore and delete', () => {
		it( 'asks before moving to the trash and can be cancelled with Escape', async () => {
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', {
					name: 'Move Algebra I to the trash',
				} )
			);
			expect( screen.getByRole( 'dialog' ) ).toHaveTextContent(
				'Move Algebra I to the trash?'
			);
			await user.keyboard( '{Escape}' );
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
			expect( api.trashCourse ).not.toHaveBeenCalled();
		} );
		it( 'trashes after confirmation and reloads', async () => {
			vi.mocked( api.trashCourse ).mockResolvedValue( {
				...detail,
				status: 'trash',
			} );
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', {
					name: 'Move Algebra I to the trash',
				} )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Move to trash' } )
			);
			expect(
				await screen.findByText( 'Course moved to the trash.', notice )
			).toBeInTheDocument();
			expect( api.trashCourse ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			);
			await waitFor( () =>
				expect( api.listCourses ).toHaveBeenCalledTimes( 2 )
			);
		} );
		it( 'shows a failure inside the dialog', async () => {
			vi.mocked( api.trashCourse ).mockRejectedValue(
				rejection(
					403,
					'ohmylms_course_cannot_delete',
					'Not yours to delete.'
				)
			);
			const user = userEvent.setup();
			renderPage();
			await screen.findByRole( 'table', { name: 'Courses' } );
			await user.click(
				screen.getByRole( 'button', {
					name: 'Move Algebra I to the trash',
				} )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Move to trash' } )
			);
			expect(
				await within( screen.getByRole( 'dialog' ) ).findByText(
					'Not yours to delete.',
					notice
				)
			).toBeInTheDocument();
		} );
		it( 'offers restore and permanent delete in the trash', async () => {
			vi.mocked( api.listCourses ).mockResolvedValue( {
				items: [ { ...summary, status: 'trash' } ],
				total: 1,
				totalPages: 1,
			} );
			vi.mocked( api.restoreCourse ).mockResolvedValue( detail );
			vi.mocked( api.deleteCourse ).mockResolvedValue( undefined );
			const user = userEvent.setup();
			renderPage();
			await user.click(
				await screen.findByRole( 'link', { name: 'Trash' } )
			);
			const table = await screen.findByRole( 'table', {
				name: 'Courses',
			} );
			expect(
				within( table ).queryByRole( 'link', { name: /Algebra I/ } )
			).not.toBeInTheDocument();
			expect(
				within( table ).queryByRole( 'button', {
					name: /to the trash/,
				} )
			).not.toBeInTheDocument();

			await user.click(
				within( table ).getByRole( 'button', {
					name: 'Restore Algebra I',
				} )
			);
			expect(
				await screen.findByText(
					'Restored Algebra I as a draft.',
					notice
				)
			).toBeInTheDocument();
			expect( api.restoreCourse ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			);

			await user.click(
				await screen.findByRole( 'button', {
					name: 'Delete Algebra I permanently',
				} )
			);
			expect( screen.getByRole( 'dialog' ) ).toHaveTextContent(
				/cannot be undone/
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Delete course' } )
			);
			expect(
				await screen.findByText( 'Course deleted permanently.', notice )
			).toBeInTheDocument();
			expect( api.deleteCourse ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			);
		} );
		it( 'explains why a course with students cannot be deleted', async () => {
			vi.mocked( api.listCourses ).mockResolvedValue( {
				items: [ { ...summary, status: 'trash' } ],
				total: 1,
				totalPages: 1,
			} );
			vi.mocked( api.deleteCourse ).mockRejectedValue(
				rejection(
					409,
					'ohmylms_course_has_students',
					'This course has enrolled students.'
				)
			);
			const user = userEvent.setup();
			renderPage();
			await user.click(
				await screen.findByRole( 'link', { name: 'Trash' } )
			);
			await user.click(
				await screen.findByRole( 'button', {
					name: 'Delete Algebra I permanently',
				} )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Delete course' } )
			);
			expect(
				await within( screen.getByRole( 'dialog' ) ).findByText(
					'This course has enrolled students.',
					notice
				)
			).toBeInTheDocument();
		} );
	} );
} );
