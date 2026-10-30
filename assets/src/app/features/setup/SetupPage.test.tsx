import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { currencies, everyStepDone, noResult, rawState } from './fixtures';
import { setup } from './index';
import { parseState } from './model';
import SetupPage from './SetupPage';

vi.mock( './api', () => ( {
	getSetup: vi.fn(),
	saveStep: vi.fn(),
	skipStep: vi.fn(),
	completeSetup: vi.fn(),
	dismissSetup: vi.fn(),
	resetSetup: vi.fn(),
	listCurrencies: vi.fn(),
} ) );

function Where() {
	const location = useLocation();
	return (
		<output aria-label="location">
			{ location.pathname + location.search }
		</output>
	);
}

function renderAt( url = '/setup' ) {
	return render(
		<MemoryRouter initialEntries={ [ url ] }>
			<Routes>
				<Route path="/setup" element={ <SetupPage /> } />
				<Route path="/" element={ <h2>Dashboard</h2> } />
				<Route path="/settings" element={ <h2>Settings</h2> } />
			</Routes>
			<Where />
		</MemoryRouter>
	);
}

const state = ( ...args: Parameters< typeof rawState > ) =>
	parseState( rawState( ...args ) );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getSetup ).mockResolvedValue( state() );
	vi.mocked( api.listCurrencies ).mockResolvedValue( currencies );
} );

// Notices are also announced in a hidden live region, so look inside the visible notice only.
const notice = ( text: string | RegExp ) =>
	waitFor( () => {
		const found = Array.from(
			document.querySelectorAll( '.components-notice__content' )
		).some( ( node ) =>
			'string' === typeof text
				? ( node.textContent ?? '' ).includes( text )
				: text.test( node.textContent ?? '' )
		);
		expect( found ).toBe( true );
	} );

const location = () => screen.getByLabelText( 'location' ).textContent;

describe( 'setup feature', () => {
	it( 'registers one admin-only entry that stays in the navigation', () => {
		expect( setup ).toMatchObject( {
			id: 'setup',
			path: '/setup',
			capability: 'manage_options',
		} );
		expect( setup.hidden ).toBeUndefined();
	} );
} );

describe( 'loading', () => {
	it( 'shows a loading status, then the first step with a live announcement', async () => {
		renderAt();
		expect( screen.getAllByRole( 'status' )[ 0 ] ).toHaveTextContent(
			'Loading setup'
		);
		const heading = await screen.findByRole( 'heading', {
			name: 'Welcome to OhMyLMS',
		} );
		await waitFor( () => expect( heading ).toHaveFocus() );
		expect(
			screen.getByText( 'Step 1 of 7: Welcome' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Welcome' } )
		).toHaveAttribute( 'aria-current', 'step' );
	} );

	it( 'shows a load error with a retry that works', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getSetup ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Server broke.' } )
		);
		renderAt();
		expect(
			await screen.findByText( 'Server broke.' )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'heading', { name: 'Welcome to OhMyLMS' } )
		).toBeInTheDocument();
	} );

	it( 'does not offer a retry for a permission error', async () => {
		vi.mocked( api.getSetup ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_setup_cannot_view',
				message: 'Sorry, you are not allowed to view the setup.',
			} )
		);
		renderAt();
		await notice( /not allowed to view/ );
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).toBeNull();
	} );
} );

describe( 'steps', () => {
	it( 'saves a step and moves to the next one', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockResolvedValue( {
			state: state( { welcome: 'done' } ),
			result: noResult,
		} );
		renderAt();
		await user.click(
			await screen.findByRole( 'button', { name: 'Start setup' } )
		);
		expect( api.saveStep ).toHaveBeenCalledWith(
			'welcome',
			{},
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByRole( 'heading', {
				name: 'How familiar are you with running an LMS?',
			} )
		).toBeInTheDocument();
		expect( location() ).toBe( '/setup?step=experience' );
		expect(
			screen.getByRole( 'button', { name: /Welcome/ } ).parentElement
		).toHaveTextContent( 'done' );
	} );

	it( 'shows a validation error without calling the server', async () => {
		const user = userEvent.setup();
		renderAt( '/setup?step=experience' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Continue' } )
		);
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Choose an option.'
		);
		expect( api.saveStep ).not.toHaveBeenCalled();
	} );

	it( 'works with the keyboard: radio choice and Enter submit', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockResolvedValue( {
			state: state( { welcome: 'done', experience: 'done' } ),
			result: noResult,
		} );
		renderAt( '/setup?step=experience' );
		await screen.findByRole( 'heading', {
			name: 'How familiar are you with running an LMS?',
		} );
		await user.tab();
		await user.keyboard( '{ArrowDown}' );
		expect(
			screen.getByRole( 'radio', { name: 'I have some experience' } )
		).toBeChecked();
		await user.keyboard( '{Enter}' );
		await waitFor( () =>
			expect( api.saveStep ).toHaveBeenCalledWith(
				'experience',
				{ experience: 'intermediate' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'validates preferences and sends the typed input', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockResolvedValue( {
			state: state( {
				welcome: 'done',
				experience: 'done',
				preferences: 'done',
			} ),
			result: { changed: [ 'currency' ], pages: [] },
		} );
		renderAt( '/setup?step=preferences' );
		const decimals = await screen.findByLabelText( 'Number of decimals' );
		await user.clear( decimals );
		await user.type( decimals, '9' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save and continue' } )
		);
		expect(
			await screen.findByText( 'Enter a whole number from 0 to 8.' )
		).toBeInTheDocument();
		expect( api.saveStep ).not.toHaveBeenCalled();
		await user.clear( decimals );
		await user.type( decimals, '0' );
		await waitFor( () =>
			expect( screen.getByLabelText( 'Currency' ) ).toBeInTheDocument()
		);
		await user.selectOptions( screen.getByLabelText( 'Currency' ), 'EUR' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save and continue' } )
		);
		await waitFor( () =>
			expect( api.saveStep ).toHaveBeenCalledWith(
				'preferences',
				{
					currency: 'EUR',
					currency_pos: 'left',
					price_num_decimals: 0,
				},
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByRole( 'heading', {
				name: 'What will you teach?',
			} )
		).toBeInTheDocument();
	} );

	it( 'shows server field errors and keeps what was typed', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_setup_invalid_value',
				message: 'Some values are not valid.',
				details: { params: { currency: 'Unknown currency code.' } },
			} )
		);
		renderAt( '/setup?step=preferences' );
		await screen.findByLabelText( 'Currency' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save and continue' } )
		);
		await notice( 'Some values are not valid.' );
		expect(
			screen.getByText( 'Unknown currency code.' )
		).toBeInTheDocument();
		expect( location() ).toBe( '/setup?step=preferences' );
		expect(
			screen.getByRole( 'button', { name: 'Save and continue' } )
		).toBeEnabled();
	} );

	it( 'shows a permission error from the server', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_setup_cannot_publish_pages',
				message: 'Sorry, you are not allowed to publish pages.',
			} )
		);
		renderAt( '/setup?step=pages' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Set up the pages' } )
		);
		await notice( /not allowed to publish pages/ );
	} );

	it( 'previews starter pages and sends the niche with parsed topics', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveStep ).mockResolvedValue( {
			state: state( { niche: 'done' } ),
			result: noResult,
		} );
		renderAt( '/setup?step=pages' );
		const list = await screen.findByRole( 'list', {
			name: 'Starter pages',
		} );
		expect(
			within( list ).getByText( /will be created/ )
		).toBeInTheDocument();
		expect(
			within( list ).getByText( /my-dashboard/ )
		).toBeInTheDocument();
		expect(
			within( list ).getByText( /already set up/ )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Back' } ) );
		await user.click(
			await screen.findByRole( 'radio', { name: 'Tech skills' } )
		);
		await user.type(
			screen.getByLabelText( /Starter topics/ ),
			'Algebra, algebra{Enter}Geometry'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save and continue' } )
		);
		await waitFor( () =>
			expect( api.saveStep ).toHaveBeenCalledWith(
				'niche',
				{ niche: 'tech-skills', topics: [ 'Algebra', 'Geometry' ] },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'protects against double submit while saving', async () => {
		const user = userEvent.setup();
		let resolve: ( value: never ) => void = () => undefined;
		vi.mocked( api.saveStep ).mockReturnValue(
			new Promise( ( r ) => {
				resolve = r as never;
			} )
		);
		renderAt();
		const button = await screen.findByRole( 'button', {
			name: 'Start setup',
		} );
		await user.click( button );
		await user.click( screen.getByRole( 'button', { name: 'Saving…' } ) );
		await user.keyboard( '{Enter}' );
		expect( api.saveStep ).toHaveBeenCalledTimes( 1 );
		resolve( {
			state: state( { welcome: 'done' } ),
			result: noResult,
		} as never );
		await screen.findByRole( 'heading', { name: /How familiar/ } );
	} );

	it( 'skips a skippable step and cannot skip a required one', async () => {
		const user = userEvent.setup();
		vi.mocked( api.skipStep ).mockResolvedValue( {
			state: state( { pages: 'skipped' } ),
			result: noResult,
		} );
		renderAt( '/setup?step=pages' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Skip this step' } )
		);
		expect( api.skipStep ).toHaveBeenCalledWith(
			'pages',
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByRole( 'heading', { name: 'Import courses' } )
		).toBeInTheDocument();
	} );

	it( 'hides Skip on required steps and Back on the first', async () => {
		renderAt( '/setup?step=experience' );
		await screen.findByRole( 'heading', { name: /How familiar/ } );
		expect(
			screen.queryByRole( 'button', { name: 'Skip this step' } )
		).toBeNull();
		expect(
			screen.getByRole( 'button', { name: 'Back' } )
		).toBeInTheDocument();
	} );

	it( 'explains that importing is a later feature and continues by skipping', async () => {
		const user = userEvent.setup();
		vi.mocked( api.skipStep ).mockResolvedValue( {
			state: state( { import: 'skipped' } ),
			result: noResult,
		} );
		renderAt( '/setup?step=import' );
		expect(
			await screen.findByText( /not available in this version yet/ )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Continue without importing' } )
		);
		expect( api.saveStep ).not.toHaveBeenCalled();
		expect( api.skipStep ).toHaveBeenCalledWith(
			'import',
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByRole( 'heading', { name: 'Finish setup' } )
		).toBeInTheDocument();
	} );
} );

describe( 'navigation', () => {
	it( 'opens any step from the progress list', async () => {
		const user = userEvent.setup();
		renderAt();
		await screen.findByRole( 'heading', { name: 'Welcome to OhMyLMS' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Starter pages' } )
		);
		expect( location() ).toBe( '/setup?step=pages' );
		expect(
			await screen.findByRole( 'heading', { name: 'Starter pages' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Step 5 of 7: Starter pages' )
		).toBeInTheDocument();
	} );

	it( 'exits the setup and returns to the dashboard', async () => {
		const user = userEvent.setup();
		vi.mocked( api.dismissSetup ).mockResolvedValue( state() );
		renderAt( '/setup?step=niche' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Exit setup' } )
		);
		expect( api.dismissSetup ).toHaveBeenCalledWith(
			'niche',
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByRole( 'heading', { name: 'Dashboard' } )
		).toBeInTheDocument();
	} );
} );

describe( 'finish and done state', () => {
	it( 'lists open steps and blocks completion until they are resolved', async () => {
		const user = userEvent.setup();
		renderAt( '/setup?step=finish' );
		const list = await screen.findByRole( 'list', { name: 'Open steps' } );
		await user.click(
			within( list ).getByRole( 'button', { name: 'Welcome' } )
		);
		expect( location() ).toBe( '/setup?step=welcome' );
	} );

	it( 'completes explicitly and then shows the done state', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getSetup ).mockResolvedValue( state( everyStepDone ) );
		vi.mocked( api.completeSetup ).mockResolvedValue(
			state( everyStepDone, {
				status: 'completed',
				completed_at: '2026-10-30T06:00:00Z',
			} )
		);
		renderAt();
		expect(
			await screen.findByRole( 'heading', { name: 'Finish setup' } )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Finish setup' } )
		);
		expect( api.completeSetup ).toHaveBeenCalledTimes( 1 );
		expect(
			await screen.findByRole( 'heading', { name: 'Setup complete' } )
		).toBeInTheDocument();
	} );

	it( 'shows the done state for a completed wizard and can run it again', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getSetup ).mockResolvedValue(
			state( everyStepDone, { status: 'completed' } )
		);
		vi.mocked( api.resetSetup ).mockResolvedValue( state() );
		renderAt();
		expect(
			await screen.findByRole( 'heading', { name: 'Setup complete' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Open settings' } )
		).toHaveAttribute( 'href', '/settings' );
		await user.click(
			screen.getByRole( 'button', { name: 'Run setup again' } )
		);
		expect(
			await screen.findByRole( 'heading', { name: 'Welcome to OhMyLMS' } )
		).toBeInTheDocument();
		expect( location() ).toBe( '/setup?step=welcome' );
	} );

	it( 'lets a completed wizard re-open a step', async () => {
		vi.mocked( api.getSetup ).mockResolvedValue(
			state( everyStepDone, { status: 'completed' } )
		);
		renderAt( '/setup?step=preferences' );
		expect(
			await screen.findByRole( 'heading', { name: 'Site preferences' } )
		).toBeInTheDocument();
	} );
} );
