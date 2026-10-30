import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import { contentHubOverview } from './index';
import * as api from './overviewApi';
import OverviewPage from './OverviewPage';
import type { Overview } from './types';

vi.mock( './overviewApi', async ( original ) => ( {
	...( await original< typeof import( './overviewApi' ) >() ),
	getOverview: vi.fn(),
} ) );

const overview: Overview = {
	areas: [
		{ id: 'courses', total: 3, counts: { published: 2, drafts: 1 } },
		{ id: 'lessons', total: 2, counts: { published: 2, drafts: 0 } },
		{
			id: 'quizzes',
			total: 4,
			counts: { published: 3, drafts: 1, to_grade: 2 },
		},
		{ id: 'skills', total: 9, counts: { archived: 1 } },
		{ id: 'bundles', total: 1, counts: { published: 1, drafts: 0 } },
	],
	recent: [
		{
			area: 'quizzes',
			id: 12,
			title: 'Fractions check',
			status: 'draft',
			updated_at: '2026-10-30T09:00:00Z',
		},
		{
			area: 'lessons',
			id: 5,
			title: 'Intro lesson',
			status: 'publish',
			updated_at: '2026-10-29T09:00:00Z',
		},
	],
};
const feature = { ...contentHubOverview, component: OverviewPage };

function open( capabilities = { edit_posts: true, manage_options: true } ) {
	return renderShell( [ feature ], { route: '/content-hub', capabilities } );
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getOverview ).mockResolvedValue( overview );
} );

it( 'is not routed and sends nothing without author capability', () => {
	open( { edit_posts: false, manage_options: false } );
	expect( api.getOverview ).not.toHaveBeenCalled();
} );

it( 'lists every area the server sent with counts and links', async () => {
	open();
	const table = await screen.findByRole( 'table', {
		name: 'Content areas',
	} );
	const rows = within( table ).getAllByRole( 'row' ).slice( 1 );
	expect(
		rows.map(
			( row ) => within( row ).getByRole( 'rowheader' ).textContent
		)
	).toEqual( [
		'Courses',
		'Lessons',
		'Quizzes',
		'Skills',
		'Course bundles',
	] );
	expect(
		within( table ).getByRole( 'link', { name: 'Courses' } )
	).toHaveAttribute( 'href', '/courses' );
	expect(
		within( table ).getByRole( 'link', { name: 'Course bundles' } )
	).toHaveAttribute( 'href', '/content-hub/bundles' );
	const quizzes = rows[ 2 ]!;
	expect(
		within( quizzes ).getByText(
			'3 published, 1 draft, 2 attempts to grade'
		)
	).toBeInTheDocument();
	expect(
		within( quizzes ).getByRole( 'link', { name: 'Quiz reports' } )
	).toHaveAttribute( 'href', '/reports/quizzes' );
	expect(
		within( rows[ 3 ]! ).getByText( '1 archived' )
	).toBeInTheDocument();
} );

it( 'links recent items to their editors', async () => {
	open();
	const table = await screen.findByRole( 'table', {
		name: 'Recently changed content',
	} );
	expect(
		within( table ).getByRole( 'link', { name: 'Fractions check' } )
	).toHaveAttribute( 'href', '/quizzes/12' );
	expect(
		within( table ).getByRole( 'link', { name: 'Intro lesson' } )
	).toHaveAttribute( 'href', '/content-hub/lessons/5' );
	expect( within( table ).getByText( 'Draft' ) ).toBeInTheDocument();
} );

it( 'hides administrator areas and their recent items from authors', async () => {
	open( { edit_posts: true, manage_options: false } );
	const table = await screen.findByRole( 'table', {
		name: 'Content areas',
	} );
	expect(
		within( table ).queryByRole( 'link', { name: 'Lessons' } )
	).not.toBeInTheDocument();
	expect(
		within( table ).queryByRole( 'link', { name: 'Course bundles' } )
	).not.toBeInTheDocument();
	expect(
		screen.queryByRole( 'link', { name: 'Intro lesson' } )
	).not.toBeInTheDocument();
	expect(
		screen.getByRole( 'link', { name: 'Fractions check' } )
	).toBeInTheDocument();
} );

it( 'says when nothing changed yet', async () => {
	vi.mocked( api.getOverview ).mockResolvedValue( {
		areas: overview.areas,
		recent: [],
	} );
	open();
	expect(
		await screen.findByText( 'Nothing has been changed yet.' )
	).toBeInTheDocument();
} );

it( 'shows an error and retries', async () => {
	const user = userEvent.setup();
	vi.mocked( api.getOverview )
		.mockRejectedValueOnce(
			new ApiError( { code: 'x', status: 500, message: 'Server down.' } )
		)
		.mockResolvedValueOnce( overview );
	open();
	expect( await screen.findByText( /Server down\./ ) ).toBeInTheDocument();
	await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
	expect(
		await screen.findByRole( 'table', { name: 'Content areas' } )
	).toBeInTheDocument();
	expect( api.getOverview ).toHaveBeenCalledTimes( 2 );
} );
