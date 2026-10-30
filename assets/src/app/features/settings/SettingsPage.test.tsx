import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import {
	countries,
	currencies,
	currency,
	fixtures,
	general,
	pages,
	permalink,
	response,
	tax,
	usStates,
} from './fixtures';
import { settings } from './index';
import SettingsPage from './SettingsPage';
import type { GroupId } from './types';

vi.mock( './api', () => ( {
	getSettings: vi.fn(),
	saveSettings: vi.fn(),
	searchPages: vi.fn(),
	listCurrencies: vi.fn(),
	listCountries: vi.fn(),
	listStates: vi.fn(),
	restoreDefaultPages: vi.fn(),
	clearCache: vi.fn(),
} ) );
vi.mock( './mcp', async ( original ) => ( {
	...( await original< typeof import( './mcp' ) >() ),
	getMcp: vi.fn( () => new Promise( () => undefined ) ),
} ) );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getSettings ).mockImplementation( ( async (
		group: GroupId
	) => response( group, fixtures[ group ] as never ) ) as never );
	vi.mocked( api.searchPages ).mockResolvedValue( pages );
	vi.mocked( api.listCurrencies ).mockResolvedValue( currencies );
	vi.mocked( api.listCountries ).mockResolvedValue( countries );
	vi.mocked( api.listStates ).mockResolvedValue( usStates );
} );

function renderPage( tab?: string ) {
	return render(
		<MemoryRouter
			initialEntries={ [ tab ? `/settings?tab=${ tab }` : '/settings' ] }
		>
			<SettingsPage />
		</MemoryRouter>
	);
}

const forbidden = () =>
	new ApiError( {
		status: 403,
		code: 'ohmylms_settings_cannot_edit',
		message: 'Sorry, you are not allowed to change settings.',
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

describe( 'settings feature', () => {
	it( 'registers one admin-only entry at /settings', () => {
		expect( settings ).toMatchObject( {
			id: 'settings',
			path: '/settings',
			capability: 'manage_options',
		} );
		expect( settings.hidden ).toBeUndefined();
	} );
} );

describe( 'tabs', () => {
	it( 'opens the tab from the query string and loads only that group', async () => {
		renderPage( 'currency' );
		expect(
			await screen.findByRole( 'form', { name: 'Currency' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: 'Currency' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect( api.getSettings ).toHaveBeenCalledTimes( 1 );
		expect( api.getSettings ).toHaveBeenCalledWith(
			'currency',
			expect.any( AbortSignal )
		);
	} );

	it( 'falls back to General for an unknown tab', async () => {
		renderPage( 'nonsense' );
		expect(
			await screen.findByRole( 'form', { name: 'General' } )
		).toBeInTheDocument();
	} );

	it( 'moves between tabs with the arrow, Home and End keys', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'form', { name: 'General' } );
		const firstTab = screen.getByRole( 'tab', { name: 'General' } );
		expect( firstTab ).toHaveAttribute( 'tabindex', '0' );
		expect(
			screen.getByRole( 'tab', { name: 'Branding' } )
		).toHaveAttribute( 'tabindex', '-1' );
		firstTab.focus();
		await user.keyboard( '{ArrowRight}' );
		const branding = screen.getByRole( 'tab', { name: 'Branding' } );
		expect( branding ).toHaveAttribute( 'aria-selected', 'true' );
		expect( branding ).toHaveFocus();
		expect(
			await screen.findByRole( 'form', { name: 'Branding' } )
		).toBeInTheDocument();
		await user.keyboard( '{End}' );
		expect(
			screen.getByRole( 'tab', { name: 'Migration' } )
		).toHaveFocus();
		await user.keyboard( '{ArrowRight}' );
		expect( screen.getByRole( 'tab', { name: 'General' } ) ).toHaveFocus();
		await user.keyboard( '{ArrowLeft}' );
		expect(
			screen.getByRole( 'tab', { name: 'Migration' } )
		).toHaveFocus();
		await user.keyboard( '{Home}' );
		expect( screen.getByRole( 'tab', { name: 'General' } ) ).toHaveFocus();
		expect(
			screen.getByRole( 'tabpanel', { name: 'General' } )
		).toBeVisible();
	} );

	it( 'keeps unsaved edits when switching tabs and marks the tab', async () => {
		const user = userEvent.setup();
		renderPage( 'currency' );
		const decimals = await screen.findByLabelText( 'Number of decimals' );
		await user.clear( decimals );
		await user.type( decimals, '4' );
		expect(
			screen.getByRole( 'tab', { name: 'Currency (unsaved)' } )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'tab', { name: 'Tax' } ) );
		await screen.findByRole( 'form', { name: 'Tax' } );
		await user.click( screen.getByRole( 'tab', { name: /Currency/ } ) );
		expect( screen.getByLabelText( 'Number of decimals' ) ).toHaveValue(
			4
		);
	} );
} );

describe( 'saving', () => {
	it( 'sends only the changed field and confirms', async () => {
		const user = userEvent.setup();
		renderPage( 'currency' );
		const decimals = await screen.findByLabelText( 'Number of decimals' );
		const save = screen.getByRole( 'button', { name: 'Save changes' } );
		expect( save ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.clear( decimals );
		await user.type( decimals, '3' );
		vi.mocked( api.saveSettings ).mockResolvedValue(
			response( 'currency', { ...currency, price_num_decimals: 3 }, [
				'price_num_decimals',
			] ) as never
		);
		await user.click( save );
		await notice( 'Settings saved.' );
		expect( api.saveSettings ).toHaveBeenCalledWith(
			'currency',
			{ price_num_decimals: 3 },
			expect.any( AbortSignal )
		);
		expect( screen.getByLabelText( 'Number of decimals' ) ).toHaveValue(
			3
		);
	} );

	it( 'submits with Enter from a text field', async () => {
		const user = userEvent.setup();
		renderPage( 'permalink' );
		const base = await screen.findByLabelText( 'Course base' );
		await user.clear( base );
		await user.type( base, 'learn{Enter}' );
		vi.mocked( api.saveSettings ).mockResolvedValue(
			response( 'permalink', { ...permalink, course_base: 'learn' }, [
				'course_base',
			] ) as never
		);
		await waitFor( () => expect( api.saveSettings ).toHaveBeenCalled() );
		expect( api.saveSettings ).toHaveBeenCalledWith(
			'permalink',
			{ course_base: 'learn' },
			expect.any( AbortSignal )
		);
		await notice( /URLs refresh the next time a page loads/ );
	} );

	it( 'shows validation errors on the field without calling the server', async () => {
		const user = userEvent.setup();
		renderPage( 'currency' );
		const decimals = await screen.findByLabelText( 'Number of decimals' );
		await user.clear( decimals );
		await user.type( decimals, '99' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( api.saveSettings ).not.toHaveBeenCalled();
		expect( decimals ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( decimals ).toHaveAccessibleDescription(
			/Enter a value from 0 to 8/
		);
		await notice( 'Fix the highlighted fields.' );
		await user.clear( decimals );
		await user.type( decimals, '2' );
		expect( decimals ).not.toHaveAttribute( 'aria-invalid' );
	} );

	it( 'shows server field errors and keeps the draft', async () => {
		const user = userEvent.setup();
		renderPage( 'permalink' );
		const base = await screen.findByLabelText( 'Course base' );
		await user.clear( base );
		await user.type( base, 'taken' );
		vi.mocked( api.saveSettings ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_settings_invalid_value',
				message: 'Some settings are not valid.',
				details: {
					params: { course_base: 'Each URL base must be different.' },
				},
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Some settings are not valid.' );
		expect( base ).toHaveAccessibleDescription(
			'Each URL base must be different.'
		);
		expect( base ).toHaveValue( 'taken' );
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toBeEnabled();
	} );

	it( 'shows a server failure', async () => {
		const user = userEvent.setup();
		renderPage( 'advanced' );
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Use the custom video player',
			} )
		);
		vi.mocked( api.saveSettings ).mockRejectedValue(
			new ApiError( {
				status: 500,
				code: 'ohmylms_settings_save_failed',
				message: 'The settings could not be saved.',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'The settings could not be saved.' );
	} );

	it( 'shows a permission error on save', async () => {
		const user = userEvent.setup();
		renderPage( 'advanced' );
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Use the custom video player',
			} )
		);
		vi.mocked( api.saveSettings ).mockRejectedValue( forbidden() );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Sorry, you are not allowed to change settings.' );
	} );

	it( 'discards edits', async () => {
		const user = userEvent.setup();
		renderPage( 'advanced' );
		const toggle = await screen.findByRole( 'checkbox', {
			name: 'Use the custom video player',
		} );
		await user.click( toggle );
		expect( toggle ).not.toBeChecked();
		await user.click(
			screen.getByRole( 'button', { name: 'Discard changes' } )
		);
		expect( toggle ).toBeChecked();
	} );
} );

describe( 'loading problems', () => {
	it( 'offers a retry after a server error', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getSettings ).mockRejectedValueOnce(
			new ApiError( {
				status: 500,
				code: 'boom',
				message: 'Database down.',
			} )
		);
		renderPage( 'currency' );
		await notice( 'Database down.' );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'form', { name: 'Currency' } )
		).toBeInTheDocument();
	} );

	it( 'does not offer a retry for a permission error', async () => {
		vi.mocked( api.getSettings ).mockRejectedValue( forbidden() );
		renderPage( 'currency' );
		await notice( 'Sorry, you are not allowed to change settings.' );
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'unsaved changes guard', () => {
	it( 'asks the browser before the page unloads only while there are edits', async () => {
		const user = userEvent.setup();
		renderPage( 'advanced' );
		const toggle = await screen.findByRole( 'checkbox', {
			name: 'Use the custom video player',
		} );
		const clean = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( clean );
		expect( clean.defaultPrevented ).toBe( false );
		await user.click( toggle );
		const dirty = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( dirty );
		expect( dirty.defaultPrevented ).toBe( true );
	} );

	it( 'intercepts in-app links while dirty and lets the user stay', async () => {
		const user = userEvent.setup();
		render(
			<MemoryRouter initialEntries={ [ '/settings?tab=advanced' ] }>
				<a href="#/students">Students</a>
				<SettingsPage />
			</MemoryRouter>
		);
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Use the custom video player',
			} )
		);
		await user.click( screen.getByRole( 'link', { name: 'Students' } ) );
		await notice( /You have unsaved settings/ );
		await user.click(
			screen.getByRole( 'button', { name: 'Stay on this page' } )
		);
		expect(
			document.querySelector( '.components-notice__content' )
		).toBeNull();
	} );
} );

describe( 'General tab', () => {
	it( 'labels the saved page choices and sends a changed page id', async () => {
		const user = userEvent.setup();
		renderPage( 'general' );
		const archive = await screen.findByLabelText( 'Course archive page' );
		await waitFor( () =>
			expect( archive ).toHaveDisplayValue( 'All courses' )
		);
		await user.selectOptions(
			screen.getByLabelText( 'Terms and conditions page' ),
			'Thanks'
		);
		vi.mocked( api.saveSettings ).mockResolvedValue(
			response( 'general', { ...general, terms_page_id: 13 }, [
				'terms_page_id',
			] ) as never
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Settings saved.' );
		expect( api.saveSettings ).toHaveBeenCalledWith(
			'general',
			{ terms_page_id: 13 },
			expect.any( AbortSignal )
		);
	} );

	it( 'restores default pages and reports which', async () => {
		const user = userEvent.setup();
		vi.mocked( api.restoreDefaultPages ).mockResolvedValue( {
			restored: [
				{ key: 'checkout', page_id: 40, title: 'OhMy Checkout' },
			],
			values: { ...general, checkout_page_id: 40 },
		} );
		renderPage( 'general' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Restore default pages',
			} )
		);
		expect(
			await screen.findByText( /Restored: OhMy Checkout/ )
		).toBeInTheDocument();
	} );

	it( 'reports a failed restore', async () => {
		const user = userEvent.setup();
		vi.mocked( api.restoreDefaultPages ).mockRejectedValue( forbidden() );
		renderPage( 'general' );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Restore default pages',
			} )
		);
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'not allowed'
		);
	} );
} );

describe( 'Advanced tab', () => {
	it( 'clears the cache and says how much was removed', async () => {
		const user = userEvent.setup();
		vi.mocked( api.clearCache ).mockResolvedValue( 7 );
		renderPage( 'advanced' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Clear OhMyLMS cache' } )
		);
		expect(
			await screen.findByText( /7 entries removed/ )
		).toBeInTheDocument();
	} );
} );

describe( 'Tax tab', () => {
	it( 'adds a state rate, loads that country’s states and saves the row', async () => {
		const user = userEvent.setup();
		renderPage( 'tax' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add tax rate' } )
		);
		const group = screen.getByRole( 'group', { name: 'Tax rate 1' } );
		await user.selectOptions(
			within( group ).getByLabelText( 'Country (rate 1)' ),
			'US'
		);
		await user.click(
			within( group ).getByLabelText( 'Whole country (rate 1)' )
		);
		await waitFor( () =>
			expect( api.listStates ).toHaveBeenCalledWith(
				'US',
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			await within( group ).findByLabelText( 'State (rate 1)' ),
			'CA'
		);
		const rate = within( group ).getByLabelText( 'Rate (%) (rate 1)' );
		await user.clear( rate );
		await user.type( rate, '7.25' );
		const row = {
			country: 'US',
			state: 'CA',
			rate: 7.25,
			country_wide: false,
		};
		vi.mocked( api.saveSettings ).mockResolvedValue(
			response( 'tax', { ...tax, tax_rates: [ row ] }, [
				'tax_rates',
			] ) as never
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Settings saved.' );
		expect( api.saveSettings ).toHaveBeenCalledWith(
			'tax',
			{ tax_rates: [ row ] },
			expect.any( AbortSignal )
		);
	} );

	it( 'flags an incomplete row and can remove it', async () => {
		const user = userEvent.setup();
		renderPage( 'tax' );
		await user.click(
			await screen.findByRole( 'button', { name: 'Add tax rate' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( api.saveSettings ).not.toHaveBeenCalled();
		expect(
			await screen.findAllByText( /Row 1: choose a country/ )
		).not.toHaveLength( 0 );
		await user.click(
			screen.getByRole( 'button', { name: 'Remove rate 1' } )
		);
		expect( screen.getByText( 'No tax rates yet.' ) ).toBeInTheDocument();
	} );
} );
