import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as ai from './aiTutor';
import * as design from './designSystem';
import * as gateways from './gateways';
import * as hub from './hubIcons';
import * as mcp from './mcp';
import SettingsPage from './SettingsPage';
import {
	aiRaw,
	designRaw,
	hubIconsRaw,
	mcpRaw,
	offlineRaw,
	stripeRaw,
	xapiRaw,
} from './tabFixtures';
import * as xapi from './xapi';

vi.mock( './api', () => ( {
	getSettings: vi.fn( () => new Promise( () => undefined ) ),
	saveSettings: vi.fn(),
	searchPages: vi.fn(),
	listCurrencies: vi.fn(),
	listCountries: vi.fn(),
	listStates: vi.fn(),
	restoreDefaultPages: vi.fn(),
	clearCache: vi.fn(),
} ) );
vi.mock( './gateways', async ( original ) => ( {
	...( await original< typeof import( './gateways' ) >() ),
	listGateways: vi.fn(),
	saveGateway: vi.fn(),
} ) );
vi.mock( './designSystem', async ( original ) => ( {
	...( await original< typeof import( './designSystem' ) >() ),
	getDesign: vi.fn(),
	saveDesign: vi.fn(),
	resetDesign: vi.fn(),
} ) );
vi.mock( './xapi', async ( original ) => ( {
	...( await original< typeof import( './xapi' ) >() ),
	getXapi: vi.fn(),
	saveXapi: vi.fn(),
	runXapiAction: vi.fn(),
} ) );
vi.mock( './aiTutor', async ( original ) => ( {
	...( await original< typeof import( './aiTutor' ) >() ),
	getAi: vi.fn(),
	saveAi: vi.fn(),
	listAiModels: vi.fn(),
	testAi: vi.fn(),
} ) );
vi.mock( './mcp', async ( original ) => ( {
	...( await original< typeof import( './mcp' ) >() ),
	getMcp: vi.fn(),
	manageMcp: vi.fn(),
} ) );
vi.mock( './hubIcons', async ( original ) => ( {
	...( await original< typeof import( './hubIcons' ) >() ),
	getHubIcons: vi.fn(),
	saveHubIcons: vi.fn(),
	listImages: vi.fn(),
} ) );

const stripe = gateways.parseGateway( stripeRaw );
const offline = gateways.parseGateway( offlineRaw );
const designSnapshot = design.parseDesign( designRaw );
const xapiSnapshot = xapi.parseXapi( xapiRaw );
const aiSnapshot = ai.parseAi( aiRaw );
const mcpSnapshot = mcp.parseMcp( mcpRaw );
const hubSlots = hub.parseHubIcons( hubIconsRaw );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( gateways.listGateways ).mockResolvedValue( [ stripe, offline ] );
	vi.mocked( design.getDesign ).mockResolvedValue( designSnapshot );
	vi.mocked( xapi.getXapi ).mockResolvedValue( xapiSnapshot );
	vi.mocked( ai.getAi ).mockResolvedValue( aiSnapshot );
	vi.mocked( mcp.getMcp ).mockResolvedValue( mcpSnapshot );
	vi.mocked( hub.getHubIcons ).mockResolvedValue( hubSlots );
} );

function renderTab( tab: string ) {
	return render(
		<MemoryRouter initialEntries={ [ `/settings?tab=${ tab }` ] }>
			<SettingsPage />
		</MemoryRouter>
	);
}

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

describe( 'Payment gateways tab', () => {
	it( 'never shows a saved credential and sends only what changed', async () => {
		const user = userEvent.setup();
		renderTab( 'payment-gateways' );
		const form = await screen.findByRole( 'form', { name: 'Stripe' } );
		const secret = within( form ).getByLabelText( 'Sandbox Secret Key' );
		expect( secret ).toHaveValue( '' );
		expect( secret ).toHaveAttribute( 'type', 'password' );
		expect( secret ).toHaveAccessibleDescription(
			/Saved\. Leave empty to keep it\./
		);
		expect(
			within( form ).queryByLabelText( 'Live Secret Key' )
		).not.toBeInTheDocument();

		const title = within( form ).getByLabelText( 'Title' );
		await user.clear( title );
		await user.type( title, 'Pay by card' );
		await user.type( secret, 'sk_test_new' );
		expect(
			screen.getByRole( 'tab', { name: 'Payment gateways (unsaved)' } )
		).toBeInTheDocument();
		vi.mocked( gateways.saveGateway ).mockResolvedValue( {
			gateway: {
				...stripe,
				values: { ...stripe.values, title: 'Pay by card' },
			},
			changed: [ 'title', 'sandbox_secret_key' ],
		} );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Settings saved.' );
		expect( gateways.saveGateway ).toHaveBeenCalledWith(
			'stripe',
			{
				values: { title: 'Pay by card' },
				secrets: { sandbox_secret_key: 'sk_test_new' },
			},
			expect.any( AbortSignal )
		);
		expect(
			within( form ).getByLabelText( 'Sandbox Secret Key' )
		).toHaveValue( '' );
	} );

	it( 'removes a saved credential with the explicit checkbox', async () => {
		const user = userEvent.setup();
		renderTab( 'payment-gateways' );
		const form = await screen.findByRole( 'form', { name: 'Stripe' } );
		await user.click(
			within( form ).getByLabelText(
				'Remove the saved Sandbox Secret Key'
			)
		);
		expect(
			within( form ).getByLabelText( 'Sandbox Secret Key' )
		).toBeDisabled();
		vi.mocked( gateways.saveGateway ).mockResolvedValue( {
			gateway: {
				...stripe,
				secrets: {
					...stripe.secrets,
					sandbox_secret_key: { hasValue: false },
				},
			},
			changed: [ 'sandbox_secret_key' ],
		} );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( gateways.saveGateway ).toHaveBeenCalledWith(
				'stripe',
				{ clear_secrets: [ 'sandbox_secret_key' ] },
				expect.any( AbortSignal )
			)
		);
		expect(
			await within( form ).findByLabelText( 'Sandbox Secret Key' )
		).toHaveAccessibleDescription( /Not set\./ );
	} );

	it( 'switches to live mode, warns about missing credentials and shows server errors', async () => {
		const user = userEvent.setup();
		renderTab( 'payment-gateways' );
		const form = await screen.findByRole( 'form', { name: 'Stripe' } );
		await user.selectOptions(
			within( form ).getByLabelText( 'Mode' ),
			'live'
		);
		await user.click(
			within( form ).getByRole( 'checkbox', {
				name: 'Offer Stripe at checkout',
			} )
		);
		expect(
			within( form ).getByLabelText( 'Live Secret Key' )
		).toBeVisible();
		expect( form ).toHaveTextContent(
			/will fail until these credentials are set: Live Secret Key/
		);
		vi.mocked( gateways.saveGateway ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_settings_invalid_value',
				message: 'Some settings are not valid.',
				details: {
					params: { 'secrets.secret_key': 'Rejected by the server.' },
				},
			} )
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Some settings are not valid.' );
		expect(
			within( form ).getByLabelText( 'Live Secret Key' )
		).toHaveAccessibleDescription( 'Rejected by the server.' );
		expect( gateways.saveGateway ).toHaveBeenCalledWith(
			'stripe',
			{ enabled: true, mode: 'live' },
			expect.any( AbortSignal )
		);
	} );

	it( 'blocks invalid input before the request', async () => {
		const user = userEvent.setup();
		renderTab( 'payment-gateways' );
		const form = await screen.findByRole( 'form', { name: 'Offline' } );
		await user.clear( within( form ).getByLabelText( 'Title' ) );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect( gateways.saveGateway ).not.toHaveBeenCalled();
		expect(
			within( form ).getByLabelText( 'Title' )
		).toHaveAccessibleDescription( 'This field cannot be empty.' );
	} );

	it( 'reports a permission error without a retry', async () => {
		vi.mocked( gateways.listGateways ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_settings_cannot_view',
				message: 'Sorry, you are not allowed to view settings.',
			} )
		);
		renderTab( 'payment-gateways' );
		await notice( 'not allowed' );
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'Design system tab', () => {
	it( 'applies a preset, previews contrast and saves the full design', async () => {
		const user = userEvent.setup();
		renderTab( 'design-system' );
		const form = await screen.findByRole( 'form', {
			name: 'Design system',
		} );
		expect( within( form ).getByLabelText( 'Preset' ) ).toHaveValue(
			'wordpress'
		);
		expect( form ).toHaveTextContent(
			/follow each user’s WordPress admin colour scheme \(yours: ocean, #567958\)/
		);
		await user.selectOptions(
			within( form ).getByLabelText( 'Preset' ),
			'khan'
		);
		expect( form ).not.toHaveTextContent( /yours: ocean/ );
		await user.selectOptions(
			within( form ).getByLabelText( 'Preset' ),
			'classic'
		);
		expect( within( form ).getByLabelText( 'Primary' ) ).toHaveValue(
			'#6e42d3'
		);
		const primary = within( form ).getByLabelText( 'Primary' );
		await user.clear( primary );
		await user.type( primary, '#ffb100' );
		expect( within( form ).getByLabelText( 'Preset' ) ).toHaveValue(
			'custom'
		);
		expect( form ).toHaveTextContent( /primary colour is black/ );
		expect( form ).toHaveTextContent( /buttons use black text/ );
		vi.mocked( design.saveDesign ).mockResolvedValue( designSnapshot );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () => expect( design.saveDesign ).toHaveBeenCalled() );
		const [ sent ] = vi.mocked( design.saveDesign ).mock.calls[ 0 ] ?? [];
		expect( sent ).toMatchObject( {
			preset: 'custom',
			button_radius: 4,
			density: 'comfortable',
			colors: { primary: '#ffb100', danger: '#cc1818' },
		} );
	} );

	it( 'flags an invalid colour and resets after confirmation', async () => {
		const user = userEvent.setup();
		renderTab( 'design-system' );
		const form = await screen.findByRole( 'form', {
			name: 'Design system',
		} );
		const link = within( form ).getByLabelText( 'Link' );
		await user.clear( link );
		await user.type( link, 'blue' );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect( design.saveDesign ).not.toHaveBeenCalled();
		expect( link ).toHaveAttribute( 'aria-invalid', 'true' );

		vi.mocked( design.resetDesign ).mockResolvedValue( designSnapshot );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Reset to defaults' } )
		);
		expect( design.resetDesign ).not.toHaveBeenCalled();
		await user.click(
			within( form ).getByRole( 'button', { name: 'Confirm reset' } )
		);
		expect(
			await within( form ).findByText( /default design was restored/ )
		).toBeInTheDocument();
		expect( within( form ).getByLabelText( 'Link' ) ).toHaveValue(
			'#1865f2'
		);
	} );

	it( 'sets pill buttons', async () => {
		const user = userEvent.setup();
		renderTab( 'design-system' );
		const form = await screen.findByRole( 'form', {
			name: 'Design system',
		} );
		await user.click(
			within( form ).getByRole( 'checkbox', {
				name: 'Pill-shaped buttons',
			} )
		);
		expect(
			within( form ).getByLabelText( 'Button corner radius (px)' )
		).toBeDisabled();
		vi.mocked( design.saveDesign ).mockResolvedValue( designSnapshot );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( design.saveDesign ).toHaveBeenCalledWith(
				expect.objectContaining( { button_radius: 999 } ),
				expect.any( AbortSignal )
			)
		);
	} );
} );

describe( 'Hub icons tab', () => {
	it( 'picks an image and falls back to the built-in icon', async () => {
		const user = userEvent.setup();
		vi.mocked( hub.listImages ).mockResolvedValue( {
			items: [
				{
					id: 31,
					title: 'Book',
					thumbnail: 'https://site.test/book.png',
				},
			],
			total: 1,
			totalPages: 1,
		} );
		renderTab( 'hub-icons' );
		const form = await screen.findByRole( 'form', { name: 'Hub icons' } );
		await user.click(
			within( form ).getByRole( 'button', {
				name: 'Choose an image for Lesson',
			} )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Use image Book' } )
		);
		await user.click(
			within( form ).getByRole( 'button', {
				name: 'Use the built-in icon for Quiz',
			} )
		);
		vi.mocked( hub.saveHubIcons ).mockResolvedValue( hubSlots );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( hub.saveHubIcons ).toHaveBeenCalledWith(
				{
					lesson: { id: 31, url: 'https://site.test/book.png' },
					quiz: { id: 0, url: '' },
					logo: { id: 0, url: '' },
				},
				expect.any( AbortSignal )
			)
		);
	} );
} );

describe( 'xAPI tab', () => {
	it( 'keeps the LRS secret write-only and can clear it', async () => {
		const user = userEvent.setup();
		renderTab( 'xapi' );
		const form = await screen.findByRole( 'form', { name: 'xAPI' } );
		const secret = within( form ).getByLabelText( 'LRS secret' );
		expect( secret ).toHaveValue( '' );
		expect( form ).toHaveTextContent(
			'Queue: 2 pending, 5 sent, 1 failed'
		);
		await user.click(
			within( form ).getByRole( 'checkbox', {
				name: 'Lesson completion',
			} )
		);
		await user.click(
			within( form ).getByLabelText( 'Remove the saved LRS secret' )
		);
		vi.mocked( xapi.saveXapi ).mockResolvedValue( {
			...xapiSnapshot,
			hasSecret: false,
		} );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await notice( 'Settings saved.' );
		expect( xapi.saveXapi ).toHaveBeenCalledWith(
			expect.objectContaining( {
				clearSecret: true,
				groups: { enrolled: true, lessons: true },
			} ),
			expect.any( AbortSignal )
		);
	} );

	it( 'validates the endpoint and runs a connection test', async () => {
		const user = userEvent.setup();
		renderTab( 'xapi' );
		const form = await screen.findByRole( 'form', { name: 'xAPI' } );
		vi.mocked( xapi.runXapiAction ).mockResolvedValue( {
			ok: false,
			message: 'The LRS answered 401.',
			snapshot: xapiSnapshot,
		} );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Test connection' } )
		);
		expect( await within( form ).findByRole( 'alert' ) ).toHaveTextContent(
			'The LRS answered 401.'
		);
		const endpoint = within( form ).getByLabelText( 'LRS endpoint' );
		await user.clear( endpoint );
		await user.type( endpoint, 'ftp://lrs' );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect( xapi.saveXapi ).not.toHaveBeenCalled();
		expect( endpoint ).toHaveAccessibleDescription( /valid HTTP or HTTPS/ );
	} );
} );

describe( 'AI tutor tab', () => {
	it( 'shows only a key hint, loads models and saves a new key', async () => {
		const user = userEvent.setup();
		vi.mocked( ai.listAiModels ).mockResolvedValue( {
			provider: 'anthropic',
			models: [ 'claude-a', 'claude-b' ],
			truncated: false,
		} );
		renderTab( 'ai-tutor' );
		const form = await screen.findByRole( 'form', { name: 'AI tutor' } );
		const key = within( form ).getByLabelText( 'API key' );
		expect( key ).toHaveValue( '' );
		expect( key ).toHaveAccessibleDescription( /Saved \(…a1b2\)/ );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Load models' } )
		);
		await user.selectOptions(
			await within( form ).findByRole( 'combobox', { name: 'Model' } ),
			'claude-b'
		);
		await user.type( key, 'sk-ant-new' );
		vi.mocked( ai.saveAi ).mockResolvedValue( aiSnapshot );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( ai.saveAi ).toHaveBeenCalledWith(
				expect.objectContaining( {
					model: 'claude-b',
					key: 'sk-ant-new',
					clearKey: false,
				} ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'refuses to turn the tutor on after removing the key', async () => {
		const user = userEvent.setup();
		renderTab( 'ai-tutor' );
		const form = await screen.findByRole( 'form', { name: 'AI tutor' } );
		await user.click(
			within( form ).getByRole( 'checkbox', { name: 'Practice tutor' } )
		);
		await user.click(
			within( form ).getByLabelText( 'Remove the saved API key' )
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect( ai.saveAi ).not.toHaveBeenCalled();
		expect(
			within( form ).getByLabelText( 'API key' )
		).toHaveAccessibleDescription( /Save an API key before/ );
	} );

	it( 'reports the connection test', async () => {
		const user = userEvent.setup();
		vi.mocked( ai.testAi ).mockResolvedValue( {
			ok: true,
			message: 'Connected in 0.4 s.',
		} );
		renderTab( 'ai-tutor' );
		const form = await screen.findByRole( 'form', { name: 'AI tutor' } );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Test connection' } )
		);
		expect( await within( form ).findByRole( 'status' ) ).toHaveTextContent(
			'Connected in 0.4 s.'
		);
	} );
} );

describe( 'MCP tab', () => {
	it( 'shows a new token once and revokes another', async () => {
		const user = userEvent.setup();
		vi.mocked( mcp.manageMcp ).mockResolvedValueOnce( {
			snapshot: mcpSnapshot,
			token: 'oml_mcp_secret',
		} );
		renderTab( 'mcp' );
		const section = await screen.findByRole( 'region', { name: 'MCP' } );
		expect( within( section ).getByLabelText( 'Server URL' ) ).toHaveValue(
			mcpRaw.server_url
		);
		await user.click(
			within( section ).getByRole( 'button', {
				name: 'Generate a Anthropic token',
			} )
		);
		expect(
			await within( section ).findByLabelText( 'Access token' )
		).toHaveValue( 'oml_mcp_secret' );
		vi.mocked( mcp.manageMcp ).mockResolvedValueOnce( {
			snapshot: mcpSnapshot,
			token: null,
		} );
		await user.click(
			within( section ).getByRole( 'button', {
				name: 'Revoke the OpenAI token',
			} )
		);
		await waitFor( () =>
			expect( mcp.manageMcp ).toHaveBeenLastCalledWith(
				'openai',
				'revoke'
			)
		);
		expect(
			within( section ).queryByLabelText( 'Access token' )
		).not.toBeInTheDocument();
	} );
} );
