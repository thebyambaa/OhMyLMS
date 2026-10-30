import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { member, options, plan } from './fixtures';
import MembershipEditorPage from './MembershipEditorPage';

vi.mock( './api', () => ( {
	getMembership: vi.fn(),
	getOptions: vi.fn(),
	updateMembership: vi.fn(),
	listCourses: vi.fn(),
	listMembers: vi.fn(),
} ) );

function renderEditor( route = '/memberships/7' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/memberships/:id"
					element={ <MembershipEditorPage /> }
				/>
				<Route
					path="/memberships"
					element={ <p>List route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

async function loaded() {
	await screen.findByRole( 'textbox', { name: 'Name' } );
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
	vi.mocked( api.getMembership ).mockResolvedValue( plan );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [
			{ id: 11, title: 'Algebra', status: 'publish' },
			{ id: 13, title: 'Calculus', status: 'publish' },
			{ id: 14, title: 'Old course', status: 'draft' },
		],
		total: 3,
		totalPages: 1,
	} );
	vi.mocked( api.listMembers ).mockResolvedValue( {
		items: [ member ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.updateMembership ).mockImplementation(
		async ( id, input ) => ( {
			...plan,
			id,
			name: input.name,
			status: input.status,
			description: input.description,
			regular_price: input.regular_price,
			sale_price: input.sale_price,
			sale_start_date: input.sale_start_date,
			sale_end_date: input.sale_end_date,
			sign_up_fee: input.sign_up_fee,
			billing_period: input.billing_period,
			billing_interval: input.billing_interval,
			all_learning_content: input.all_learning_content,
			course_ids: input.course_ids,
			courses: input.course_ids.map( ( courseId ) => ( {
				id: courseId,
				title: `Course ${ courseId }`,
				status: 'publish',
			} ) ),
			excluded_course_ids: input.excluded_course_ids,
			excluded_courses: input.excluded_course_ids.map( ( courseId ) => ( {
				id: courseId,
				title: `Course ${ courseId }`,
				status: 'publish',
			} ) ),
		} )
	);
} );

describe( 'MembershipEditorPage', () => {
	it( 'rejects an invalid route id without requesting anything', () => {
		renderEditor( '/memberships/abc' );
		expect(
			screen.getByText( 'This membership plan link is invalid.', notice )
		).toBeInTheDocument();
		expect( api.getMembership ).not.toHaveBeenCalled();
	} );

	it( 'loads a plan into labelled fields', async () => {
		renderEditor();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading membership plan'
		);
		await loaded();
		expect(
			screen.getByRole( 'heading', {
				name: 'Edit membership plan: All access',
			} )
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'All access'
		);
		expect( screen.getByLabelText( 'Regular price (USD)' ) ).toHaveValue(
			'29.50'
		);
		expect( screen.getByLabelText( 'Sale price (USD)' ) ).toHaveValue(
			'19.00'
		);
		expect( screen.getByLabelText( 'Sale starts' ) ).toHaveValue(
			'2026-11-01'
		);
		expect( screen.getByLabelText( 'Sale ends' ) ).toHaveValue(
			'2026-11-30'
		);
		expect( screen.getByLabelText( 'Billing period' ) ).toHaveValue(
			'month'
		);
		expect( screen.getByLabelText( 'Description' ) ).toHaveValue(
			'Everything.'
		);
		const included = screen.getByRole( 'list', {
			name: 'Selected: Included courses',
		} );
		expect( within( included ).getByText( /Algebra/ ) ).toBeInTheDocument();
		expect(
			within( included ).getByText( '(not published)' )
		).toBeInTheDocument();
		expect(
			await screen.findByRole( 'table', { name: 'Plan members' } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Alex Morgan' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save plan' } )
		).toBeDisabled();
	} );

	it( 'explains a missing plan without a retry button', async () => {
		vi.mocked( api.getMembership ).mockRejectedValue(
			rejection(
				404,
				'ohmylms_membership_not_found',
				'Membership plan not found.'
			)
		);
		renderEditor();
		expect(
			await screen.findByText( 'Membership plan not found.', notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to membership plans' } )
		).toBeInTheDocument();
	} );

	it( 'shows a permission error on load', async () => {
		vi.mocked( api.getMembership ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_membership_cannot_view',
				'Sorry, you are not allowed to view membership plans.'
			)
		);
		renderEditor();
		expect(
			await screen.findByText(
				/not allowed to view membership plans/,
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'textbox', { name: 'Name' } )
		).not.toBeInTheDocument();
	} );

	it( 'retries after a server error', async () => {
		vi.mocked( api.getMembership ).mockRejectedValueOnce(
			rejection( 500, 'rest_error', 'The server broke.' )
		);
		const user = userEvent.setup();
		renderEditor();
		expect(
			await screen.findByText( 'The server broke.', notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await loaded();
	} );

	it( 'shows inline validation errors and sends nothing', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Sale price (USD)', '40' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		const message =
			'The sale price cannot be higher than the regular price.';
		expect(
			await screen.findByText(
				'Fix the highlighted fields before saving.',
				notice
			)
		).toBeInTheDocument();
		const field = screen.getByLabelText( 'Sale price (USD)' );
		expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( screen.getByText( message ) ).toHaveAttribute(
			'role',
			'alert'
		);
		expect( field ).toHaveAccessibleDescription(
			expect.stringContaining( message )
		);
		expect( api.updateMembership ).not.toHaveBeenCalled();
		await waitFor( () =>
			expect(
				screen
					.getByText(
						'Fix the highlighted fields before saving.',
						notice
					)
					.closest( '[tabindex="-1"]' )
			).toHaveFocus()
		);
	} );

	it( 'validates required name and price format', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', '' );
		await setField( user, 'Regular price (USD)', '-3' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		expect(
			await screen.findByText( 'Enter a name of 1 to 200 characters.' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Enter an amount of zero or more with at most 2 decimal places.'
			)
		).toBeInTheDocument();
		expect( api.updateMembership ).not.toHaveBeenCalled();
	} );

	it( 'saves the typed payload and shows what the server stored', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', 'Gold' );
		await setField( user, 'Regular price (USD)', '50' );
		await setField( user, 'Sale price (USD)', '' );
		await setField( user, 'Sale starts', '' );
		await setField( user, 'Sale ends', '' );
		await user.selectOptions( screen.getByLabelText( 'Status' ), 'draft' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		expect(
			await screen.findByText( 'Plan saved.', notice )
		).toBeInTheDocument();
		expect( api.updateMembership ).toHaveBeenCalledWith(
			7,
			{
				name: 'Gold',
				status: 'draft',
				description: 'Everything.',
				regular_price: '50.00',
				sale_price: null,
				sale_start_date: null,
				sale_end_date: null,
				sign_up_fee: null,
				billing_period: 'month',
				billing_interval: 1,
				all_learning_content: false,
				course_ids: [ 11, 12 ],
				excluded_course_ids: [],
				curriculum_item_ids: [],
			},
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'heading', {
				name: 'Edit membership plan: Gold',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save plan' } )
		).toBeDisabled();
	} );

	it( 'saves from the keyboard with Enter in a field', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.clear( screen.getByRole( 'textbox', { name: 'Name' } ) );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Name' } ),
			'Keyboard plan{Enter}'
		);
		expect(
			await screen.findByText( 'Plan saved.', notice )
		).toBeInTheDocument();
		expect(
			vi.mocked( api.updateMembership ).mock.calls[ 0 ]?.[ 1 ].name
		).toBe( 'Keyboard plan' );
	} );

	it( 'maps a server rejection to its field and keeps the edits', async () => {
		vi.mocked( api.updateMembership ).mockRejectedValue(
			rejection(
				422,
				'ohmylms_membership_free_needs_end',
				'A free promotion (sale price 0) needs an end date.',
				{ status: 422 }
			)
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', 'Edited' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		const messages = await screen.findAllByText(
			'A free promotion (sale price 0) needs an end date.'
		);
		expect( messages.length ).toBeGreaterThan( 0 );
		expect( screen.getByLabelText( 'Sale ends' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Edited'
		);
		expect(
			screen.getByRole( 'button', { name: 'Save plan' } )
		).toBeEnabled();
	} );

	it( 'shows a permission error on save and keeps the edits', async () => {
		vi.mocked( api.updateMembership ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_membership_cannot_edit',
				'Sorry, you are not allowed to edit membership plans.'
			)
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', 'Edited' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		expect(
			await screen.findByText(
				/not allowed to edit membership plans/,
				notice
			)
		).toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Edited'
		);
		expect( screen.queryByText( 'Plan saved.' ) ).not.toBeInTheDocument();
	} );

	it( 'prevents a second submission while saving', async () => {
		let finish!: () => void;
		vi.mocked( api.updateMembership ).mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					finish = () => resolve( plan );
				} )
		);
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', 'Once' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Save plan' } )
			).toBeDisabled()
		);
		await user.keyboard( '{Enter}' );
		finish();
		await screen.findByText( 'Plan saved.', notice );
		expect( api.updateMembership ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'courses', () => {
		it( 'adds a course found by search with the keyboard', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			const search = await screen.findByRole( 'textbox', {
				name: 'Search courses for Included courses',
			} );
			await user.type( search, 'calc{Enter}' );
			await waitFor( () =>
				expect( api.listCourses ).toHaveBeenLastCalledWith(
					'calc',
					1,
					expect.any( AbortSignal )
				)
			);
			const group = await screen.findByRole( 'group', {
				name: 'Courses available for Included courses',
			} );
			const box = within( group ).getByRole( 'checkbox', {
				name: 'Calculus',
			} );
			box.focus();
			await user.keyboard( ' ' );
			expect( box ).toBeChecked();
			await user.click(
				screen.getByRole( 'button', { name: 'Save plan' } )
			);
			await screen.findByText( 'Plan saved.', notice );
			expect(
				vi.mocked( api.updateMembership ).mock.calls[ 0 ]?.[ 1 ]
					.course_ids
			).toEqual( [ 11, 12, 13 ] );
		} );

		it( 'removes a selected course', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'button', {
					name: 'Remove Algebra from Included courses',
				} )
			);
			expect(
				screen.queryByRole( 'button', {
					name: 'Remove Algebra from Included courses',
				} )
			).not.toBeInTheDocument();
			await user.click(
				screen.getByRole( 'button', { name: 'Save plan' } )
			);
			await screen.findByText( 'Plan saved.', notice );
			expect(
				vi.mocked( api.updateMembership ).mock.calls[ 0 ]?.[ 1 ]
					.course_ids
			).toEqual( [ 12 ] );
		} );

		it( 'refuses a course that is both included and excluded', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			const groups = await screen.findAllByRole( 'group', {
				name: /Courses available for/,
			} );
			const excluded = groups.find( ( group ) =>
				/Excluded/.test( group.getAttribute( 'aria-label' ) ?? '' )
			)!;
			await user.click(
				within( excluded ).getByRole( 'checkbox', { name: 'Algebra' } )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Save plan' } )
			);
			expect(
				await screen.findByText(
					'A course cannot be both included and excluded.'
				)
			).toBeInTheDocument();
			expect( api.updateMembership ).not.toHaveBeenCalled();
		} );

		it( 'reports a course search failure with a retry', async () => {
			vi.mocked( api.listCourses ).mockRejectedValueOnce(
				rejection( 500, 'rest_error', 'Search failed.' )
			);
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			expect(
				await screen.findAllByText( /Search failed\./ )
			).not.toHaveLength( 0 );
			await user.click(
				screen.getAllByRole( 'button', { name: 'Try again' } )[ 0 ]!
			);
			expect(
				(
					await screen.findAllByRole( 'checkbox', {
						name: 'Calculus',
					} )
				).length
			).toBeGreaterThan( 0 );
		} );

		it( 'sends all learning content when ticked', async () => {
			const user = userEvent.setup();
			renderEditor();
			await loaded();
			await user.click(
				screen.getByRole( 'checkbox', { name: 'All learning content' } )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Save plan' } )
			);
			await screen.findByText( 'Plan saved.', notice );
			expect(
				vi.mocked( api.updateMembership ).mock.calls[ 0 ]?.[ 1 ]
					.all_learning_content
			).toBe( true );
		} );
	} );

	it( 'keeps curriculum rules it does not edit', async () => {
		vi.mocked( api.getMembership ).mockResolvedValue( {
			...plan,
			curriculum_item_ids: [ 4, 9 ],
		} );
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		expect(
			screen.getByText(
				'2 curriculum items are also included. They are kept as they are.'
			)
		).toBeInTheDocument();
		await setField( user, 'Name', 'Same rules' );
		await user.click( screen.getByRole( 'button', { name: 'Save plan' } ) );
		await screen.findByText( 'Plan saved.', notice );
		expect(
			vi.mocked( api.updateMembership ).mock.calls[ 0 ]?.[ 1 ]
				.curriculum_item_ids
		).toEqual( [ 4, 9 ] );
	} );

	it( 'asks before leaving with unsaved changes', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await setField( user, 'Name', 'Changed' );
		await user.click(
			screen.getByRole( 'link', { name: 'Back to membership plans' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect( dialog ).toHaveTextContent( 'Discard unsaved changes?' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( screen.getByRole( 'textbox', { name: 'Name' } ) ).toHaveValue(
			'Changed'
		);
		await user.click(
			screen.getByRole( 'link', { name: 'Back to membership plans' } )
		);
		await user.click(
			within( await screen.findByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Discard changes',
			} )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );

	it( 'leaves without asking when nothing changed', async () => {
		const user = userEvent.setup();
		renderEditor();
		await loaded();
		await user.click(
			screen.getByRole( 'link', { name: 'Back to membership plans' } )
		);
		expect(
			await screen.findByText( 'List route reached' )
		).toBeInTheDocument();
	} );
} );
