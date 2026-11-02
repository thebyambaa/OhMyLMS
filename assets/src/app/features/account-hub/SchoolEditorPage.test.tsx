import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { page, school, schoolClass } from './fixtures';
import SchoolEditorPage from './SchoolEditorPage';

vi.mock( './api', () => ( {
	getSchool: vi.fn(),
	updateSchool: vi.fn(),
	createYear: vi.fn(),
	listClasses: vi.fn(),
	createClass: vi.fn(),
} ) );

function renderEditor( id = '3' ) {
	return render(
		<MemoryRouter initialEntries={ [ `/account-hub/schools/${ id }` ] }>
			<Routes>
				<Route
					path="/account-hub/schools/:id"
					element={ <SchoolEditorPage /> }
				/>
				<Route
					path="/account-hub/classes/:id"
					element={ <p>Class editor reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getSchool ).mockResolvedValue( school );
	vi.mocked( api.listClasses ).mockResolvedValue( page( [ schoolClass ] ) );
} );

describe( 'SchoolEditorPage', () => {
	it( 'loads the school, its years and classes', async () => {
		renderEditor();
		expect(
			await screen.findByRole( 'heading', { name: 'North School' } )
		).toBeVisible();
		expect(
			screen.getByText( /2026-2027: 2026-09-01 to 2027-06-30/ )
		).toBeVisible();
		const table = await screen.findByRole( 'table', {
			name: 'Classes of this school',
		} );
		expect(
			within( table ).getByRole( 'link', { name: 'Algebra' } )
		).toHaveAttribute( 'href', '/account-hub/classes/9' );
		expect( api.listClasses ).toHaveBeenCalledWith(
			1,
			'',
			'all',
			3,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'button', { name: 'Save school' } )
		).toBeDisabled();
	} );
	it( 'saves with the loaded version', async () => {
		vi.mocked( api.updateSchool ).mockResolvedValue( {
			...school,
			name: 'North Academy',
			version: 'aaaaaaaaaaaaaaaa',
		} );
		const user = userEvent.setup();
		renderEditor();
		const name = await screen.findByRole( 'textbox', {
			name: 'School name',
		} );
		await user.clear( name );
		await user.type( name, 'North Academy' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save school' } )
		);
		expect(
			await screen.findByText( 'School saved.', notice )
		).toBeVisible();
		expect( api.updateSchool ).toHaveBeenCalledWith(
			3,
			{ name: 'North Academy', timezone: 'Asia/Ulaanbaatar' },
			school.version,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'heading', { name: 'North Academy' } )
		).toBeVisible();
	} );
	it( 'explains a stale save and reloads on request', async () => {
		vi.mocked( api.updateSchool ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_account_hub_conflict',
				message: 'This school was changed by someone else.',
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const name = await screen.findByRole( 'textbox', {
			name: 'School name',
		} );
		await user.type( name, ' 2' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save school' } )
		);
		expect(
			await screen.findByText(
				/Someone else changed this record/,
				notice
			)
		).toBeVisible();
		vi.mocked( api.getSchool ).mockResolvedValue( {
			...school,
			name: 'Renamed elsewhere',
			version: 'bbbbbbbbbbbbbbbb',
		} );
		await user.click( screen.getByRole( 'button', { name: 'Reload' } ) );
		expect(
			await screen.findByRole( 'heading', { name: 'Renamed elsewhere' } )
		).toBeVisible();
	} );
	it( 'validates before saving', async () => {
		const user = userEvent.setup();
		renderEditor();
		await user.clear(
			await screen.findByRole( 'textbox', { name: 'Time zone' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save school' } )
		);
		expect( screen.getByText( /Enter an IANA time zone/ ) ).toBeVisible();
		expect( api.updateSchool ).not.toHaveBeenCalled();
	} );
	it( 'adds an academic year after checking the dates', async () => {
		vi.mocked( api.createYear ).mockResolvedValue( {
			...school,
			years: [
				...school.years,
				{
					id: 6,
					label: '2027-2028',
					starts_at: '2027-09-01',
					ends_at: '2028-06-30',
					status: 'active',
				},
			],
		} );
		const user = userEvent.setup();
		renderEditor();
		const form = await screen.findByRole( 'form', {
			name: 'Add academic year',
		} );
		await user.type(
			within( form ).getByRole( 'textbox', { name: 'Year label' } ),
			'2027-2028'
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Add academic year' } )
		);
		expect(
			within( form ).getByText( 'Enter a real start date.' )
		).toBeVisible();
		expect( api.createYear ).not.toHaveBeenCalled();
		await user.type(
			within( form ).getByLabelText( 'Starts on' ),
			'2027-09-01'
		);
		await user.type(
			within( form ).getByLabelText( 'Ends on' ),
			'2028-06-30'
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Add academic year' } )
		);
		expect(
			await screen.findByText( /2027-2028: 2027-09-01/ )
		).toBeVisible();
		expect( api.createYear ).toHaveBeenCalledWith(
			3,
			{
				label: '2027-2028',
				starts_at: '2027-09-01',
				ends_at: '2028-06-30',
			},
			expect.any( AbortSignal )
		);
	} );
	it( 'creates a class in an active year and opens it', async () => {
		vi.mocked( api.createClass ).mockResolvedValue( schoolClass );
		const user = userEvent.setup();
		renderEditor();
		const form = await screen.findByRole( 'form', { name: 'Add class' } );
		await user.type(
			within( form ).getByRole( 'textbox', { name: 'Class name' } ),
			'Algebra'
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Create class' } )
		);
		expect(
			await screen.findByText( 'Class editor reached' )
		).toBeVisible();
		expect( api.createClass ).toHaveBeenCalledWith(
			3,
			{ name: 'Algebra', subject: '', grade: '', academic_year_id: 5 },
			expect.any( AbortSignal )
		);
	} );
	it( 'asks for a year before classes can be created', async () => {
		vi.mocked( api.getSchool ).mockResolvedValue( {
			...school,
			years: [],
		} );
		renderEditor();
		expect(
			await screen.findByText(
				'Add an active academic year to create classes.'
			)
		).toBeVisible();
		expect(
			screen.queryByRole( 'form', { name: 'Add class' } )
		).toBeNull();
	} );
	it( 'shows a not-found error for the school', async () => {
		vi.mocked( api.getSchool ).mockRejectedValue(
			new ApiError( {
				status: 404,
				code: 'ohmylms_account_hub_school_not_found',
				message: 'School not found.',
			} )
		);
		renderEditor( '99' );
		await waitFor( () =>
			expect(
				screen.getByText( /School not found/, notice )
			).toBeVisible()
		);
	} );
} );
