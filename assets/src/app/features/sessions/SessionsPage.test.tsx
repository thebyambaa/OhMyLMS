import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import { sessionEditor, sessions } from './index';
import { parseSession } from './model';
import { sessionBody } from './fixtures';

vi.mock( './api', () => ( {
	listSessions: vi.fn(),
	getSession: vi.fn(),
	createSession: vi.fn(),
	updateSession: vi.fn(),
	deleteSession: vi.fn(),
	listAttendees: vi.fn(),
	getProviders: vi.fn(),
	searchCourses: vi.fn(),
} ) );

const as = vi.mocked( api );
const notice = { selector: '.components-notice__content' };
const session = parseSession( sessionBody );

function show( route: string ) {
	return renderShell( [ sessions, sessionEditor ], {
		route,
		capabilities: { edit_posts: true },
	} );
}

beforeEach( () => {
	vi.clearAllMocks();
	as.listSessions.mockResolvedValue( {
		items: [ session ],
		total: 1,
		totalPages: 1,
	} );
	as.getSession.mockResolvedValue( session );
	as.getProviders.mockResolvedValue( [
		{ id: 'zoom', label: 'Zoom', available: true, configured: true },
		{
			id: 'googlemeet',
			label: 'Google Meet',
			available: true,
			configured: false,
		},
	] );
	as.searchCourses.mockResolvedValue( [ { id: 40, title: 'Algebra' } ] );
	as.listAttendees.mockResolvedValue( {
		items: [
			{ user_id: 12, name: 'Ada', status: 'enrolled', completed: true },
		],
		total: 1,
		totalPages: 1,
	} );
} );

describe( 'sessions list', () => {
	it( 'is hidden without edit_posts', () => {
		renderShell( [ sessions ], { route: '/sessions' } );
		expect( as.listSessions ).not.toHaveBeenCalled();
	} );

	it( 'lists sessions with state, provider and join link', async () => {
		show( '/sessions' );
		const table = await screen.findByRole( 'table', {
			name: 'Live sessions',
		} );
		expect(
			within( table ).getByRole( 'link', { name: 'Weekly review' } )
		).toHaveAttribute( 'href', '/sessions/5' );
		expect( within( table ).getByText( 'Upcoming' ) ).toBeVisible();
		expect(
			within( table ).getByRole( 'link', { name: 'Join link' } )
		).toHaveAttribute( 'href', 'https://zoom.us/j/987' );
		await userEvent.selectOptions(
			screen.getByLabelText( 'Provider' ),
			'googlemeet'
		);
		await waitFor( () =>
			expect( as.listSessions ).toHaveBeenLastCalledWith(
				{ page: 1, search: '', provider: 'googlemeet' },
				expect.any( AbortSignal )
			)
		);
	} );
} );

describe( 'session editor', () => {
	it( 'validates before creating, then creates and opens the session', async () => {
		as.createSession.mockResolvedValue( session );
		show( '/sessions/new' );
		await userEvent.click(
			await screen.findByRole( 'button', {
				name: 'Create session and meeting',
			} )
		);
		expect( as.createSession ).not.toHaveBeenCalled();
		expect( screen.getByText( 'Enter a title.' ) ).toBeVisible();
		await userEvent.type(
			screen.getByLabelText( 'Title' ),
			'Weekly review'
		);
		await userEvent.type(
			screen.getByLabelText( 'Starts' ),
			'2030-03-04T10:00'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create session and meeting' } )
		);
		await waitFor( () => expect( as.createSession ).toHaveBeenCalled() );
		expect( as.createSession.mock.calls[ 0 ]?.[ 0 ] ).toMatchObject( {
			title: 'Weekly review',
			start: '2030-03-04T10:00',
			provider: 'zoom',
		} );
		expect(
			await screen.findByRole( 'heading', { name: 'Weekly review' } )
		).toBeVisible();
	} );

	it( 'warns when the provider is not connected', async () => {
		show( '/sessions/new' );
		await userEvent.selectOptions(
			await screen.findByLabelText( 'Provider' ),
			'googlemeet'
		);
		expect(
			await screen.findByText(
				/Google Meet is not connected yet/,
				notice
			)
		).toBeVisible();
		expect( screen.queryByLabelText( 'Passcode' ) ).not.toBeInTheDocument();
	} );

	it( 'edits with a fixed provider, shows attendees and reports provider errors', async () => {
		as.updateSession.mockRejectedValue(
			new ApiError( {
				status: 502,
				code: 'ohmylms_session_provider_failed',
				message: 'Zoom did not create the meeting.',
			} )
		);
		show( '/sessions/5' );
		expect(
			await screen.findByRole( 'heading', { name: 'Weekly review' } )
		).toBeVisible();
		expect( screen.getByLabelText( 'Provider' ) ).toBeDisabled();
		const attendees = await screen.findByRole( 'table', {
			name: 'Attendees',
		} );
		expect( within( attendees ).getByText( 'Ada' ) ).toBeVisible();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save session' } )
		);
		expect(
			await screen.findByText(
				'Zoom did not create the meeting.',
				notice
			)
		).toBeVisible();
		expect( as.updateSession ).toHaveBeenCalledWith(
			5,
			expect.objectContaining( {
				title: 'Weekly review',
				password: 'abc123',
			} ),
			expect.any( AbortSignal )
		);
	} );

	it( 'deletes only after confirmation', async () => {
		as.deleteSession.mockResolvedValue( session );
		show( '/sessions/5' );
		await userEvent.click(
			await screen.findByRole( 'button', { name: 'Delete session' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect( as.deleteSession ).not.toHaveBeenCalled();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Delete session' } )
		);
		await waitFor( () =>
			expect( as.deleteSession ).toHaveBeenCalledWith(
				5,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByRole( 'table', { name: 'Live sessions' } )
		).toBeVisible();
	} );

	it( 'rejects a malformed id', () => {
		show( '/sessions/abc' );
		expect(
			screen.getByText( 'That is not a valid session.', notice )
		).toBeVisible();
		expect( as.getSession ).not.toHaveBeenCalled();
	} );
} );
