import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderShell } from '../../../test/render';
import { memberships } from '../../memberships';
import * as api from './api';
import { cohortRow } from './fixtures';
import { parseCohort } from './model';

vi.mock( './api', () => ( {
	listCohorts: vi.fn(),
	getCohort: vi.fn(),
	createCohort: vi.fn(),
	updateCohort: vi.fn(),
	deleteCohort: vi.fn(),
	listMembers: vi.fn(),
	addMember: vi.fn(),
	removeMember: vi.fn(),
	syncCohort: vi.fn(),
	searchTargets: vi.fn(),
	searchCandidates: vi.fn(),
} ) );

const cohort = parseCohort( cohortRow );
const notice = { selector: '.components-notice__content' };
const page = < T, >( items: T[] ) => ( {
	items,
	total: items.length,
	totalPages: 1,
} );
const show = ( route: string ) =>
	renderShell( [ memberships ], {
		route,
		capabilities: { manage_options: true },
	} );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.listCohorts ).mockResolvedValue( page( [ cohort ] ) );
	vi.mocked( api.getCohort ).mockResolvedValue( cohort );
	vi.mocked( api.listMembers ).mockResolvedValue(
		page( [
			{
				user_id: 12,
				name: 'Ada Lovelace',
				login: 'ada',
				source: 'purchase',
				joined_at: '2026-10-31T10:00:00',
			},
		] )
	);
	vi.mocked( api.searchTargets ).mockResolvedValue( [] );
	vi.mocked( api.searchCandidates ).mockResolvedValue( [] );
} );

describe( 'cohorts panel', () => {
	it( 'is a panel of Commerce › Memberships with its own list', async () => {
		show( '/memberships?panel=cohorts' );
		const table = await screen.findByRole( 'table', { name: 'Cohorts' } );
		expect(
			within( table ).getByText( 'Spring batch' )
		).toBeInTheDocument();
		expect( within( table ).getByText( '3 of 20' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Cohorts' } )
		).toHaveAttribute( 'aria-current', 'page' );
		expect( api.listCohorts ).toHaveBeenCalledWith(
			{ page: 1, search: '', status: 'any' },
			expect.anything()
		);
	} );

	it( 'validates a new cohort before sending anything', async () => {
		const user = userEvent.setup();
		show( '/memberships?panel=cohorts' );
		await screen.findByRole( 'table', { name: 'Cohorts' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Add cohort' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add cohort',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create cohort' } )
		);
		expect(
			within( dialog ).getByText( 'Enter the start date.' )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByText( 'Choose a course.' )
		).toBeInTheDocument();
		expect( api.createCohort ).not.toHaveBeenCalled();
	} );

	it( 'opens the members of a cohort and removes one after confirmation', async () => {
		vi.mocked( api.removeMember ).mockResolvedValue( {
			...cohort,
			member_count: 2,
		} );
		const user = userEvent.setup();
		show( '/memberships?panel=cohorts&cohort=5' );
		const table = await screen.findByRole( 'table', {
			name: 'Cohort members',
		} );
		expect(
			within( table ).getByText( 'Ada Lovelace' )
		).toBeInTheDocument();
		expect( within( table ).getByText( 'Purchase' ) ).toBeInTheDocument();
		expect( api.getCohort ).toHaveBeenCalledWith( 5, expect.anything() );
		await user.click(
			within( table ).getByRole( 'button', {
				name: 'Remove Ada Lovelace from the cohort',
			} )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Remove member',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Remove' } )
		);
		expect( api.removeMember ).toHaveBeenCalledWith(
			5,
			12,
			expect.anything()
		);
		expect(
			await screen.findByText( 'Removed Ada Lovelace.', notice )
		).toBeInTheDocument();
	} );
} );
