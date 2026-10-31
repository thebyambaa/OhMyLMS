import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import CertificatesPage from './CertificatesPage';
import { certificate } from './fixtures';
import * as issuedApi from './issuedApi';
import { issued, revoked } from './issuedFixtures';

vi.mock( './api', () => ( {
	listCertificates: vi.fn(),
	createCertificate: vi.fn(),
	duplicateCertificate: vi.fn(),
	deleteCertificate: vi.fn(),
} ) );
vi.mock( './issuedApi', async ( original ) => ( {
	...( await original< typeof import( './issuedApi' ) >() ),
	listIssued: vi.fn(),
	issueCertificate: vi.fn(),
	revokeIssued: vi.fn(),
	reissueIssued: vi.fn(),
	searchLearners: vi.fn(),
	listCourseOptions: vi.fn(),
	listTemplateOptions: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };

function Search() {
	return <p data-testid="search">{ useLocation().search }</p>;
}

function renderPage( entry = '/certificates?list=issued' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route
					path="/certificates"
					element={
						<>
							<CertificatesPage />
							<Search />
						</>
					}
				/>
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listCertificates ).mockResolvedValue( {
		items: [ certificate ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( issuedApi.listIssued ).mockResolvedValue( {
		items: [ issued, revoked ],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( issuedApi.listCourseOptions ).mockResolvedValue( [
		{ id: 12, title: 'Algebra I', status: 'publish' },
	] );
	vi.mocked( issuedApi.listTemplateOptions ).mockResolvedValue( [
		{ id: 7, name: 'Completion', status: 'publish' },
	] );
} );

describe( 'Issued certificates', () => {
	it( 'is reached from the template list', async () => {
		renderPage( '/certificates' );
		expect(
			await screen.findByRole( 'link', { name: 'Issued certificates' } )
		).toHaveAttribute( 'href', '/certificates?list=issued' );
		expect( issuedApi.listIssued ).not.toHaveBeenCalled();
	} );

	it( 'lists learner, course, template, date, code and status with download and verification links', async () => {
		renderPage();
		const table = await screen.findByRole( 'table', {
			name: 'Issued certificates',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		const first = within( rows[ 1 ]! );
		expect( first.getByText( 'Ada Lovelace' ) ).toBeInTheDocument();
		expect( first.getByText( 'ada@example.test' ) ).toBeInTheDocument();
		expect( first.getAllByText( 'Algebra I' )[ 0 ] ).toBeInTheDocument();
		expect( first.getByText( 'Completion' ) ).toBeInTheDocument();
		expect( first.getByText( 'ABCD-EFGH-JKMN-PQRS' ) ).toBeInTheDocument();
		expect( first.getByText( 'Valid' ) ).toBeInTheDocument();
		expect(
			first.getByRole( 'link', {
				name: 'Download the PDF of Ada Lovelace, Algebra I',
			} )
		).toHaveAttribute( 'href', issued.links.pdf );
		const second = within( rows[ 2 ]! );
		expect( second.getByText( /^Revoked/ ) ).toBeInTheDocument();
		expect(
			second.queryByRole( 'link', { name: /Download the PDF/ } )
		).toBeNull();
		expect(
			second.queryByRole( 'button', { name: /^Revoke/ } )
		).toBeNull();
		expect(
			second.getByRole( 'link', { name: 'Verification page' } )
		).toHaveAttribute( 'href', revoked.links.verify );
	} );

	it( 'keeps search and filters in the address', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Issued certificates' } );
		await user.type(
			screen.getByRole( 'searchbox', {
				name: 'Search issued certificates',
			} ),
			'ABCD'
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await waitFor( () =>
			expect(
				vi.mocked( issuedApi.listIssued ).mock.lastCall?.[ 0 ]
			).toMatchObject( { search: 'ABCD', page: 1 } )
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Course' } ),
			'12'
		);
		await waitFor( () =>
			expect(
				vi.mocked( issuedApi.listIssued ).mock.lastCall?.[ 0 ]
			).toMatchObject( { course: '12', search: 'ABCD' } )
		);
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent(
			'list=issued'
		);
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent( 's=ABCD' );
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent(
			'course=12'
		);
	} );

	it( 'revokes after confirmation and reloads', async () => {
		vi.mocked( issuedApi.revokeIssued ).mockResolvedValue( {
			...issued,
			status: 'revoked',
		} );
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Revoke the certificate of Ada Lovelace, Algebra I',
			} )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Revoke certificate',
		} );
		expect( dialog ).toHaveTextContent( 'Revoke ABCD-EFGH-JKMN-PQRS' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Revoke certificate',
			} )
		);
		expect( issuedApi.revokeIssued ).toHaveBeenCalledWith(
			41,
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Revoked ABCD-EFGH-JKMN-PQRS (Ada Lovelace, Algebra I).',
			notice
		);
		expect( issuedApi.listIssued ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shows a re-issue refusal in the dialog', async () => {
		vi.mocked( issuedApi.reissueIssued ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_certificate_already_issued',
				message:
					'This learner already has a valid certificate for the course.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen
				.findAllByRole( 'button', {
					name: 'Re-issue the certificate of Ada Lovelace, Algebra I',
				} )
				.then( ( buttons ) => buttons[ 1 ]! )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Re-issue certificate',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Re-issue certificate',
			} )
		);
		expect(
			await within( dialog ).findByText(
				'This learner already has a valid certificate for the course.'
			)
		).toBeInTheDocument();
		expect( issuedApi.reissueIssued ).toHaveBeenCalledWith(
			42,
			expect.any( AbortSignal )
		);
	} );

	it( 'issues a certificate by hand for a chosen learner and course', async () => {
		vi.mocked( issuedApi.searchLearners ).mockResolvedValue( [
			{ id: 3, name: 'Ada Lovelace', email: 'ada@example.test' },
		] );
		vi.mocked( issuedApi.issueCertificate ).mockResolvedValue( {
			...issued,
			source: 'manual',
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Issued certificates' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Issue certificate' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Issue certificate',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Issue certificate',
			} )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'Choose a learner and a course.'
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Search: Learner',
			} ),
			'ada'
		);
		await user.click(
			within( dialog ).getAllByRole( 'button', { name: 'Search' } )[ 0 ]!
		);
		await user.click(
			await within( dialog ).findByRole( 'radio', {
				name: 'Ada Lovelace (ada@example.test)',
			} )
		);
		await user.click(
			await within( dialog ).findByRole( 'radio', { name: 'Algebra I' } )
		);
		await user.selectOptions(
			within( dialog ).getByRole( 'combobox', { name: 'Template' } ),
			'7'
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Issue certificate',
			} )
		);
		expect( issuedApi.issueCertificate ).toHaveBeenCalledWith(
			{ user_id: 3, course_id: 12, certificate_id: 7 },
			expect.any( AbortSignal )
		);
		await screen.findByText(
			'Issued ABCD-EFGH-JKMN-PQRS (Ada Lovelace, Algebra I).',
			notice
		);
	} );
} );
