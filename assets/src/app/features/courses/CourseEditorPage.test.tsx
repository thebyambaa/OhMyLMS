import { render, screen, waitFor, within } from '@testing-library/react';
import { useEffect } from '@wordpress/element';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ApiError } from '../../lib/api';
import { attachment, mockMediaFrame } from '../../test/mediaFrame';
import * as api from './api';
import { detail, options } from './fixtures';
import CourseEditorPage from './CourseEditorPage';

vi.mock( './api', () => ( {
	getCourse: vi.fn(),
	getOptions: vi.fn(),
	updateCourse: vi.fn(),
} ) );

let media: ReturnType< typeof mockMediaFrame >;

afterEach( () => media.restore() );
const outline = vi.hoisted( () => ( { mounts: 0 } ) );
vi.mock( '../course-structure/public', () => ( {
	CourseOutline: ( { id }: { id: number } ) => {
		useEffect( () => {
			outline.mounts += 1;
		}, [] );
		return <p>Outline of course { id }</p>;
	},
	SkillCoverage: ( {
		course,
		unsaved,
		onChanged,
	}: {
		course: number;
		unsaved: boolean;
		onChanged?: () => void;
	} ) => (
		<>
			<p>
				Coverage of course { course }
				{ unsaved ? ' (unsaved changes)' : '' }
			</p>
			<button onClick={ onChanged }>
				Pretend a fix changed the draft
			</button>
		</>
	),
	PublicationPanel: ( { course }: { course: number } ) => (
		<p>Publication of course { course }</p>
	),
} ) );

const ADMIN = { edit_posts: true, manage_options: true };

function renderEditor(
	route = '/courses/7',
	capabilities: Record< string, boolean > = ADMIN,
	switchCourse = false
) {
	return render(
		<BootstrapProvider
			value={ {
				restRoot: '/wp-json/',
				restNonce: 'nonce',
				locale: 'en_US',
				adminUrl: '/wp-admin/',
				version: 'test',
				user: { id: 1, capabilities },
			} }
		>
			<MemoryRouter initialEntries={ [ route ] }>
				{ switchCourse && (
					<Link to="/courses/8">Open another course</Link>
				) }
				<Routes>
					<Route
						path="/courses/:id/:tab?"
						element={ <CourseEditorPage /> }
					/>
					<Route
						path="/courses"
						element={ <p>List route reached</p> }
					/>
				</Routes>
			</MemoryRouter>
		</BootstrapProvider>
	);
}

const notice = { selector: '.components-notice__content' };

async function loaded() {
	await screen.findByRole( 'heading', { name: /Edit course:/ } );
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

async function save( user: ReturnType< typeof userEvent.setup > ) {
	await user.click( screen.getByRole( 'button', { name: 'Save course' } ) );
}

beforeEach( () => {
	outline.mounts = 0;
	vi.resetAllMocks();
	media = mockMediaFrame();
	vi.mocked( api.getCourse ).mockResolvedValue( detail );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
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

describe( 'CourseEditorPage tabs', () => {
	it( 'clears the previous course while opening another course on the same tab', async () => {
		const user = userEvent.setup();
		let resolveNext!: ( value: typeof detail ) => void;
		const next = new Promise< typeof detail >( ( resolve ) => {
			resolveNext = resolve;
		} );
		vi.mocked( api.getCourse ).mockImplementation( ( id ) =>
			8 === id ? next : Promise.resolve( detail )
		);
		renderEditor( '/courses/7', ADMIN, true );
		await loaded();
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( detail.title );
		await user.click(
			screen.getByRole( 'link', { name: 'Open another course' } )
		);
		expect( screen.queryByLabelText( 'Title' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Save course' } )
		).not.toBeInTheDocument();
		resolveNext( { ...detail, id: 8, title: 'Next course' } );
		await waitFor( () =>
			expect( screen.getByLabelText( 'Title' ) ).toHaveValue(
				'Next course'
			)
		);
	} );
	it( 'links every tab and marks the current one', async () => {
		renderEditor();
		await loaded();
		const nav = screen.getByRole( 'navigation', {
			name: 'Course editor sections',
		} );
		const links = within( nav ).getAllByRole( 'link' );
		expect(
			links.map( ( link ) => [
				link.textContent,
				link.getAttribute( 'href' ),
			] )
		).toEqual( [
			[ 'Basics', '/courses/7' ],
			[ 'Curriculum', '/courses/7/outline' ],
			[ 'Skill coverage', '/courses/7/coverage' ],
		] );
		expect(
			within( nav ).getByRole( 'link', { name: 'Basics' } )
		).toHaveAttribute( 'aria-current', 'page' );
	} );
	it( 'opens Basics from the old settings address', async () => {
		renderEditor( '/courses/7/settings' );
		await loaded();
		expect(
			within(
				screen.getByRole( 'navigation', {
					name: 'Course editor sections',
				} )
			).getByRole( 'link', { name: 'Basics' } )
		).toHaveAttribute( 'aria-current', 'page' );
	} );
	it( 'has no Additional step', async () => {
		renderEditor();
		await loaded();
		expect(
			screen.queryByRole( 'link', { name: 'Additional' } )
		).not.toBeInTheDocument();
	} );
	it( 'renders the outline and publication tabs from the course structure feature', async () => {
		renderEditor( '/courses/7/outline' );
		expect(
			await screen.findByText( 'Outline of course 7' )
		).toBeInTheDocument();
		// Only a light read for the View course / Preview link; no course form or options load.
		expect( api.getCourse ).toHaveBeenCalledTimes( 1 );
		expect( api.getOptions ).not.toHaveBeenCalled();
		expect(
			await screen.findByRole( 'link', { name: /View course/ } )
		).toHaveAttribute( 'href', detail.permalink );
	} );
	it( 'offers Preview instead of View course for an unpublished course', async () => {
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			status: 'draft',
			preview_url: 'https://example.test/?p=7&preview=true',
		} );
		renderEditor( '/courses/7/outline' );
		expect(
			await screen.findByRole( 'link', { name: /Preview/ } )
		).toHaveAttribute( 'href', 'https://example.test/?p=7&preview=true' );
		expect(
			screen.queryByRole( 'link', { name: /View course/ } )
		).not.toBeInTheDocument();
	} );
	it( 'opens the publication panel from the Publish button for administrators', async () => {
		const user = userEvent.setup();
		renderEditor( '/courses/7/outline' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Publish' } )
		);
		expect(
			await screen.findByText( 'Publication of course 7' )
		).toBeInTheDocument();
	} );
	it( 'has one Save in the bar that is enabled only when the open step has changes', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		const bar = screen.getByRole( 'banner' );
		const barSave = within( bar ).getByRole( 'button', { name: 'Save' } );
		expect( barSave ).toBeDisabled();
		await setField( user, 'Title', 'Changed' );
		expect( barSave ).toBeEnabled();
		await user.click( barSave );
		expect( api.updateCourse ).toHaveBeenCalled();
	} );
	it( 'hides publication from authors', async () => {
		renderEditor( '/courses/7', { edit_posts: true } );
		await loaded();
		expect(
			screen.queryByRole( 'button', { name: 'Publish' } )
		).not.toBeInTheDocument();
	} );
	it( 'rejects an unknown tab and an author opening the publication tab', () => {
		const { unmount } = renderEditor( '/courses/7/unknown' );
		expect(
			screen.getByText( 'This course link is invalid.', notice )
		).toBeInTheDocument();
		unmount();
		renderEditor( '/courses/7/publish', { edit_posts: true } );
		expect(
			screen.getByText( 'This course link is invalid.', notice )
		).toBeInTheDocument();
		expect( api.getCourse ).not.toHaveBeenCalled();
	} );
	it( 'moves between steps without asking and keeps what was typed', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await user.click(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			await screen.findByText( 'Outline of course 7' )
		).toBeVisible();
		await user.click( screen.getByRole( 'link', { name: 'Basics' } ) );
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Changed' );
		expect(
			within( screen.getByRole( 'banner' ) ).getByRole( 'button', {
				name: 'Save',
			} )
		).toBeEnabled();
		expect( api.updateCourse ).not.toHaveBeenCalled();
	} );
	it( 'shows only the step that is open', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		expect(
			await screen.findByText( 'Outline of course 7' )
		).toBeVisible();
		expect( screen.getByLabelText( 'Title' ) ).not.toBeVisible();
		await user.click( screen.getByRole( 'link', { name: 'Basics' } ) );
		expect( screen.getByLabelText( 'Title' ) ).toBeVisible();
		expect( screen.getByText( 'Outline of course 7' ) ).not.toBeVisible();
	} );
	it( 'saves the edits of every step from the bar, whichever step is open', async () => {
		const user = userEvent.setup();
		vi.mocked( api.updateCourse ).mockResolvedValue( {
			...detail,
			title: 'Changed',
		} );
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		await user.click(
			within( screen.getByRole( 'banner' ) ).getByRole( 'button', {
				name: 'Save',
			} )
		);
		await waitFor( () =>
			expect( api.updateCourse ).toHaveBeenCalledWith(
				7,
				expect.objectContaining( { title: 'Changed' } ),
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'still asks before leaving the editor with unsaved changes', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		await user.click(
			screen.getByRole( 'link', { name: 'Back to courses' } )
		);
		expect(
			await screen.findByRole( 'dialog', {
				name: 'Discard unsaved changes?',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'List route reached' )
		).not.toBeInTheDocument();
	} );
} );

describe( 'CourseEditorPage details', () => {
	it( 'shows the stored values in labelled fields', async () => {
		renderEditor();
		await loaded();
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Algebra I' );
		expect( screen.getByLabelText( 'Slug' ) ).toHaveValue( 'algebra-i' );
		expect( screen.getByLabelText( 'Status' ) ).toHaveValue( 'publish' );
		expect( screen.getByLabelText( 'Level' ) ).toHaveValue( 'beginner' );
		expect(
			screen.queryByLabelText( 'Price type' )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save course' } )
		).toBeDisabled();
	} );
	it( 'saves a valid edit and shows the stored result', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Algebra II' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		await save( user );
		expect(
			await screen.findByText( 'Course saved.', notice )
		).toBeInTheDocument();
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				title: 'Algebra II',
				regular_price: '49.50',
				sale_price: '29.00',
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
	it( 'lists errors of fields without a control in the summary', async () => {
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			sale_price: '99.00',
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', '' );
		await save( user );
		expect(
			await screen.findByText(
				'Fix the highlighted fields before saving.',
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toHaveAccessibleDescription(
			'Enter a title of 1 to 200 characters.'
		);
		expect( screen.getAllByText( /cannot be higher/ ) ).not.toHaveLength(
			0
		);
		expect( api.updateCourse ).not.toHaveBeenCalled();
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
		await save( user );
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
		await save( user );
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
	it( 'asks for a future time when scheduling', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.selectOptions( screen.getByLabelText( 'Status' ), 'future' );
		await save( user );
		expect(
			await screen.findByText( 'Choose a date and time in the future.' )
		).toBeInTheDocument();
		await setField( user, 'Publish on', '2099-01-01T10:00' );
		await save( user );
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
		it( 'picks an image through the media frame with the keyboard', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			const change = screen.getByRole( 'button', {
				name: 'Change thumbnail',
			} );
			change.focus();
			await user.keyboard( '{Enter}' );
			expect( media.last().options.title ).toBe( 'Choose a thumbnail' );
			media.last().choose( attachment() );
			expect( screen.getByText( 'Image #40' ) ).toBeInTheDocument();
			await waitFor( () => expect( change ).toHaveFocus() );
			await save( user );
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
			await save( user );
			await waitFor( () =>
				expect( api.updateCourse ).toHaveBeenCalled()
			);
			expect( api.updateCourse ).toHaveBeenCalledWith(
				7,
				expect.objectContaining( { thumbnail_id: null } ),
				expect.anything()
			);
		} );
		it( 'rejects a malformed thumbnail size with the course error', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', { name: 'Change thumbnail' } )
			);
			media
				.last()
				.choose( attachment( { sizes: { thumbnail: { url: 5 } } } ) );
			expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
				'The server returned invalid course data.'
			);
			expect( screen.queryByText( 'Image #40' ) ).not.toBeInTheDocument();
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
			await save( user );
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
				screen.getByRole( 'link', { name: 'Back to courses' } )
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
				screen.getByRole( 'link', { name: 'Back to courses' } )
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
				screen.getByRole( 'link', { name: 'Back to courses' } )
			);
			expect(
				await screen.findByText( 'List route reached' )
			).toBeInTheDocument();
		} );
	} );
} );

describe( 'CourseEditorPage basics', () => {
	it( 'has no pricing, access, reviews, capacity or certificate controls', async () => {
		renderEditor();
		await loaded();
		for ( const label of [
			'Price type',
			/Regular price/,
			'Who can open the course',
			'Course password',
			'Allow student reviews',
			'Limit the number of students',
			'Certificate template',
		] ) {
			expect( screen.queryByLabelText( label ) ).not.toBeInTheDocument();
		}
		expect( screen.queryByText( 'Certificate' ) ).not.toBeInTheDocument();
		expect( api.getOptions ).toHaveBeenCalled();
	} );

	it( 'saves a change without altering stored pricing, access, duration or capacity', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await save( user );
		await screen.findByText( 'Course saved.', notice );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				price_type: 'paid',
				regular_price: '49.50',
				sale_price: '29.00',
				access_type: 'public',
				duration: { hours: 3, minutes: 30 },
			} ),
			expect.any( AbortSignal )
		);
	} );

	it( 'preserves stored password protection rather than silently opening an existing course', async () => {
		vi.mocked( api.getCourse ).mockResolvedValue( {
			...detail,
			access_type: 'password_protected',
			has_password: true,
			password: 'stored-pw',
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await save( user );
		await waitFor( () => expect( api.updateCourse ).toHaveBeenCalled() );
		expect( api.updateCourse ).toHaveBeenCalledWith(
			7,
			expect.objectContaining( {
				access_type: 'password_protected',
				password: 'stored-pw',
			} ),
			expect.any( AbortSignal )
		);
	} );
} );

describe( 'the skill coverage step', () => {
	it( 'opens from the step bar and replaces the form while it is open', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		);
		expect(
			await screen.findByText( 'Coverage of course 7' )
		).toBeVisible();
		expect( screen.getByLabelText( 'Title' ) ).not.toBeVisible();
		expect(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		).toHaveAttribute( 'aria-current', 'page' );
	} );
	it( 'is told about unsaved changes and keeps them', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Title', 'Changed' );
		await user.click(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		);
		expect(
			await screen.findByText( 'Coverage of course 7 (unsaved changes)' )
		).toBeVisible();
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'link', { name: 'Basics' } ) );
		expect( screen.getByLabelText( 'Title' ) ).toHaveValue( 'Changed' );
		expect(
			screen.queryByText( /Coverage of course/ )
		).not.toBeInTheDocument();
	} );
	it( 'sits after Curriculum in the Next and Previous links', async () => {
		const user = userEvent.setup();
		renderEditor( '/courses/7/outline' );
		await user.click(
			await screen.findByRole( 'link', { name: 'Next: Skill coverage' } )
		);
		expect(
			await screen.findByText( 'Coverage of course 7' )
		).toBeVisible();
		expect(
			screen.getByRole( 'link', { name: 'Previous: Curriculum' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'link', { name: /^Next:/ } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'a fix made from the coverage step', () => {
	it( 'makes the Curriculum step load the changed outline instead of showing the old one', async () => {
		const user = userEvent.setup();
		renderEditor( '/courses/7/outline' );
		expect(
			await screen.findByText( 'Outline of course 7' )
		).toBeVisible();
		expect( outline.mounts ).toBe( 1 );
		await user.click(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Pretend a fix changed the draft',
			} )
		);
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		expect(
			await screen.findByText( 'Outline of course 7' )
		).toBeVisible();
		expect( outline.mounts ).toBe( 2 );
	} );

	it( 'does not reload the Curriculum step when nothing was fixed', async () => {
		const user = userEvent.setup();
		renderEditor( '/courses/7/outline' );
		await screen.findByText( 'Outline of course 7' );
		await user.click(
			screen.getByRole( 'link', { name: 'Skill coverage' } )
		);
		await screen.findByText( 'Coverage of course 7' );
		await user.click( screen.getByRole( 'link', { name: 'Curriculum' } ) );
		await screen.findByText( 'Outline of course 7' );
		expect( outline.mounts ).toBe( 1 );
	} );
} );
