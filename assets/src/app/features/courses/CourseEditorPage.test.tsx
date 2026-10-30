import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { detail, options } from './fixtures';
import CourseEditorPage from './CourseEditorPage';

vi.mock( './api', () => ( {
	getCourse: vi.fn(),
	getOptions: vi.fn(),
	updateCourse: vi.fn(),
	listMedia: vi.fn(),
} ) );

function renderEditor( route = '/courses/7' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route path="/courses/:id" element={ <CourseEditorPage /> } />
				<Route path="/courses" element={ <p>List route reached</p> } />
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

async function loaded() {
	await screen.findByRole( 'textbox', { name: 'Title' } );
}

function rejection(
	status: number,
	code: string,
	message: string,
	data?: unknown
) {
	return new ApiError( { status, code, message, details: data } );
}

async function setField(
	user: ReturnType< typeof userEvent.setup >,
	name: string | RegExp,
	value: string
) {
	const field = screen.getByLabelText( name );
	await user.clear( field );
	if ( value ) {
		await user.type( field, value );
	}
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getCourse ).mockResolvedValue( detail );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
	vi.mocked( api.listMedia ).mockResolvedValue( {
		items: [
			{
				id: 40,
				url: 'https://example.test/a.png',
				thumbnail_url: 'https://example.test/a-150.png',
				title: 'Cover A',
			},
		],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateCourse ).mockImplementation( async ( id, input ) => ( {
		...detail,
		id,
		title: input.title,
		slug: input.slug,
		status: input.status,
		description: input.description,
		price_type: input.price_type,
		regular_price: input.regular_price,
		sale_price: input.sale_price,
		sale_start_date: input.sale_start_date,
		sale_end_date: input.sale_end_date,
		access_type: input.access_type,
		password: input.password ?? '',
		has_capacity: input.has_capacity,
		capacity: input.capacity,
		thumbnail_id: input.thumbnail_id,
		thumbnail_url: null,
		duration: input.duration,
		available_date: input.available_date,
	} ) );
} );

describe( 'CourseEditorPage', () => {
	it( 'shows the stored values in labelled fields', async () => {
		renderEditor();
		await loaded();
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Algebra I' );
		expect( screen.getByLabelText( 'Slug' ) ).toHaveValue( 'algebra-i' );
		expect( screen.getByLabelText( 'Status' ) ).toHaveValue( 'publish' );
		expect( screen.getByLabelText( 'Level' ) ).toHaveValue( 'beginner' );
		expect( screen.getByLabelText( 'Price type' ) ).toHaveValue( 'paid' );
		expect( screen.getByLabelText( /Regular price/ ) ).toHaveValue(
			'49.50'
		);
		expect( screen.getByLabelText( /Sale price/ ) ).toHaveValue( '29.00' );
		expect( screen.getByLabelText( 'Sale starts' ) ).toHaveValue(
			'2026-11-01'
		);
		expect( screen.getByLabelText( 'Duration (hours)' ) ).toHaveValue(
			'3'
		);
		expect( screen.getByLabelText( 'Maximum students' ) ).toHaveValue(
			'30'
		);
		expect(
			screen.getByText( '4 students are enrolled now.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', {
				name: 'Build the curriculum in the classic course builder',
			} )
		).toHaveAttribute(
			'href',
			'https://example.test/wp-admin/admin.php?page=ohmylms#/courses/7'
		);
		expect(
			screen.getByRole( 'button', { name: 'Save course' } )
		).toBeDisabled();
	} );
	it( 'hides the classic builder link when the server gives none', async () => {
		vi.mocked( api.getOptions ).mockResolvedValue( {
			...options,
			abilities: { ...options.abilities, builder_url: '' },
		} );
		renderEditor();
		await loaded();
		expect(
			screen.queryByRole( 'link', { name: /classic course builder/ } )
		).not.toBeInTheDocument();
	} );
	it( 'saves a valid edit with a typed payload and shows the stored result', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Algebra II' );
		await setField( user, /Regular price/, '60' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText( 'Course saved.', notice )
		).toBeInTheDocument();
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				title: 'Algebra II',
				regular_price: '60.00',
				sale_price: '29.00',
				price_type: 'paid',
				capacity: 30,
				duration: { hours: 3, minutes: 30 },
				thumbnail_id: 21,
			} ),
			expect.any( AbortSignal )
		);
		expect( screen.getByRole( 'heading', { level: 2 } ) ).toHaveTextContent(
			'Edit course: Algebra II'
		);
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
	} );
	it( 'saves with the keyboard from a field', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click( screen.getByLabelText( 'Title' ) );
		await user.keyboard( ' II{Enter}' );
		await waitFor( () =>
			expect( api.updateCourse ).toHaveBeenCalledTimes( 1 )
		);
	} );
	it( 'validates before saving and focuses the error summary', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', '' );
		await setField( user, /Sale price/, '99' );
		await setField( user, 'Maximum students', '0' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText(
				'Fix the highlighted fields before saving.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		expect( screen.getByLabelText( 'Title' ) ).toHaveAccessibleDescription(
			'Enter a title of 1 to 200 characters.'
		);
		expect(
			screen.getByLabelText( /Sale price/ )
		).toHaveAccessibleDescription(
			/cannot be higher than the regular price/
		);
		expect( screen.getByLabelText( 'Maximum students' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		expect( api.updateCourse ).not.toHaveBeenCalled();
		await setField( user, 'Title', 'Fixed' );
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Fixed' );
	} );
	it( 'maps a server rejection to its field and keeps the edits', async () => {
		vi.mocked( api.updateCourse ).mockRejectedValue(
			rejection(
				422,
				'ohmylms_course_sale_price_too_high',
				'The sale price cannot be higher than the regular price.',
				{ status: 422 }
			)
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Edited' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText(
				'The sale price cannot be higher than the regular price.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByLabelText( /Sale price/ ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Edited' );
		expect(
			screen.getByRole( 'button', { name: 'Save course' } )
		).toBeEnabled();
	} );
	it( 'shows a server error without losing the edits', async () => {
		vi.mocked( api.updateCourse ).mockRejectedValue(
			rejection(
				500,
				'ohmylms_course_update_failed',
				'The course could not be updated.'
			)
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Edited' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText(
				'The course could not be updated.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Edited' );
		expect( screen.queryByText( 'Course saved.' ) ).not.toBeInTheDocument();
	} );
	it( 'shows a permission error on save', async () => {
		vi.mocked( api.updateCourse ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_course_cannot_edit',
				'Sorry, you are not allowed to edit this course.'
			)
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Edited' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to edit this course.',
				notice
			)
		).toBeInTheDocument();
	} );
	it( 'does not offer retry for a 403 or 404 on load', async () => {
		vi.mocked( api.getCourse ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_course_cannot_edit',
				'You may not edit this.'
			)
		);
		renderEditor();
		expect(
			await screen.findByText( 'You may not edit this.', notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to courses' } )
		).toHaveAttribute( 'href', '/courses' );
	} );
	it( 'retries a failed load', async () => {
		vi.mocked( api.getCourse ).mockRejectedValueOnce(
			rejection( 500, 'rest_error', 'Temporary failure.' )
		);
		const user = userEvent.setup();
		renderEditor();
		expect(
			await screen.findByText( 'Temporary failure.', notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await loaded();
	} );
	it( 'rejects an invalid route id without requesting anything', () => {
		renderEditor( '/courses/abc' );
		expect(
			screen.getByText( 'This course link is invalid.', notice )
		).toBeInTheDocument();
		expect( api.getCourse ).not.toHaveBeenCalled();
	} );

	it( 'hides sale fields for free courses and sends no sale', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.selectOptions(
			screen.getByLabelText( 'Price type' ),
			'free'
		);
		expect(
			screen.queryByLabelText( /Regular price/ )
		).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText( /Sale price/ )
		).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				price_type: 'free',
				regular_price: '0.00',
				sale_price: null,
				sale_start_date: null,
				sale_end_date: null,
			} ),
			expect.anything()
		);
	} );
	it( 'requires a password for protected courses and sends it', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.selectOptions(
			screen.getByLabelText( 'Who can open the course' ),
			'password_protected'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText( 'Enter a password for this course.' )
		).toBeInTheDocument();
		expect( api.updateCourse ).not.toHaveBeenCalled();
		const field = screen.getByLabelText( 'Course password' );
		expect( field ).toHaveAttribute( 'type', 'password' );
		await user.type( field, 's3cret' );
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Show password' } )
		);
		expect( field ).toHaveAttribute( 'type', 'text' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				access_type: 'password_protected',
				password: 's3cret',
			} ),
			expect.anything()
		);
	} );
	it( 'prefills the stored password for editors and never sends it for public courses', async () => {
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			access_type: 'password_protected',
			has_password: true,
			password: 'stored-pw',
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		expect( screen.getByLabelText( 'Course password' ) ).toHaveValue(
			'stored-pw'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Who can open the course' ),
			'public'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect(
			vi.mocked( api.updateCourse ).mock.calls[ 0 ]![ 1 ]
		).not.toHaveProperty( 'password' );
	} );
	it( 'asks for a future time when scheduling', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.selectOptions( screen.getByLabelText( 'Status' ), 'future' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		expect(
			await screen.findByText( 'Choose a date and time in the future.' )
		).toBeInTheDocument();
		await setField( user, 'Publish on', '2099-01-01T10:00' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				status: 'future',
				scheduled_at: '2099-01-01T10:00',
			} ),
			expect.anything()
		);
	} );
	it( 'limits the status choices to what the user may use', async () => {
		vi.mocked( api.getOptions ).mockResolvedValue( {
			...options,
			statuses: options.statuses.slice( 0, 2 ),
			abilities: { ...options.abilities, publish: false },
		} );
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			status: 'draft',
		} );
		renderEditor();
		await loaded();
		const choices = within( screen.getByLabelText( 'Status' ) )
			.getAllByRole( 'option' )
			.map( ( option ) => option.textContent );
		expect( choices ).toEqual( [ 'Draft', 'Pending review' ] );
		expect(
			screen.getByText( /submit courses for review/ )
		).toBeInTheDocument();
	} );
	it( 'only shows the capacity field when enrolment is limited', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'checkbox', {
				name: 'Limit the number of students',
			} )
		);
		expect(
			screen.queryByLabelText( 'Maximum students' )
		).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save course' } )
		);
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( { has_capacity: false, capacity: null } ),
			expect.anything()
		);
	} );
	it( 'locks the form for trashed courses', async () => {
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			status: 'trash',
		} );
		renderEditor();
		await loaded();
		expect(
			screen.getByText( /This course is in the trash/, notice )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Save course' } )
		).toBeDisabled();
	} );

	describe( 'thumbnail', () => {
		it( 'picks an image through the media picker with the keyboard', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Change thumbnail' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Choose a thumbnail',
			} );
			const pick = await within( dialog ).findByRole( 'button', {
				name: 'Use image Cover A',
			} );
			pick.focus();
			await user.keyboard( '{Enter}' );
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
			expect( screen.getByText( 'Image #40' ) ).toBeInTheDocument();
			await user.click(
				screen.getByRole( 'button', { name: 'Save course' } )
			);
			await waitFor( () =>
				expect( api.updateCourse ).toHaveBeenCalled()
			);
			expect( api.updateCourse ).toHaveBeenCalledWith(
				7,
				expect.objectContaining( { thumbnail_id: 40 } ),
				expect.anything()
			);
		} );
		it( 'removes the thumbnail', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Remove thumbnail' } )
			);
			expect(
				screen.getByText( 'No thumbnail selected.' )
			).toBeInTheDocument();
			await user.click(
				screen.getByRole( 'button', { name: 'Save course' } )
			);
			await waitFor( () =>
				expect( api.updateCourse ).toHaveBeenCalled()
			);
			expect( api.updateCourse ).toHaveBeenCalledWith(
				7,
				expect.objectContaining( { thumbnail_id: null } ),
				expect.anything()
			);
		} );
		it( 'shows a media error and can be cancelled', async () => {
			vi.mocked( api.listMedia ).mockRejectedValue(
				rejection( 500, 'rest_error', 'Media failed.' )
			);
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Change thumbnail' } )
			);
			expect(
				await screen.findByText( 'Media failed.', notice )
			).toBeInTheDocument();
			await user.keyboard( '{Escape}' );
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		} );
		it( 'explains when the user cannot use the media library', async () => {
			vi.mocked( api.getOptions ).mockResolvedValue( {
				...options,
				abilities: { ...options.abilities, upload_files: false },
			} );
			renderEditor();
			await loaded();
			expect(
				screen.queryByRole( 'button', { name: 'Change thumbnail' } )
			).not.toBeInTheDocument();
			expect(
				screen.getByText( /do not have permission to pick images/ )
			).toBeInTheDocument();
		} );
		it( 'maps a thumbnail rejection to the thumbnail section', async () => {
			vi.mocked( api.updateCourse ).mockRejectedValue(
				rejection(
					403,
					'ohmylms_course_thumbnail_forbidden',
					'Sorry, you are not allowed to use this image.'
				)
			);
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Remove thumbnail' } )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Save course' } )
			);
			expect(
				await screen.findAllByText(
					'Sorry, you are not allowed to use this image.'
				)
			).not.toHaveLength( 0 );
			expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		} );
	} );

	describe( 'unsaved changes', () => {
		it( 'warns the browser while dirty only', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			const clean = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( clean );
			expect( clean.defaultPrevented ).toBe( false );
			await setField( user, 'Title', 'Changed' );
			const dirty = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( dirty );
			expect( dirty.defaultPrevented ).toBe( true );
		} );
		it( 'asks before leaving through an in-app link and can stay', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await setField( user, 'Title', 'Changed' );
			await user.click(
				screen.getAllByRole( 'link', { name: 'Back to courses' } )[ 0 ]!
			);
			expect(
				await screen.findByRole( 'dialog', {
					name: 'Discard unsaved changes?',
				} )
			).toBeInTheDocument();
			await user.click(
				screen.getByRole( 'button', { name: 'Keep editing' } )
			);
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
			expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Changed' );
		} );
		it( 'leaves when the user discards', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await setField( user, 'Title', 'Changed' );
			await user.click(
				screen.getAllByRole( 'link', { name: 'Back to courses' } )[ 0 ]!
			);
			await user.click(
				await screen.findByRole( 'button', { name: 'Discard changes' } )
			);
			expect(
				await screen.findByText( 'List route reached' )
			).toBeInTheDocument();
		} );
		it( 'leaves freely when nothing changed', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getAllByRole( 'link', { name: 'Back to courses' } )[ 0 ]!
			);
			expect(
				await screen.findByText( 'List route reached' )
			).toBeInTheDocument();
		} );
	} );
} );
