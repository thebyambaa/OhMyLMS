import { ApiError, del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	aiDirty,
	aiDraft,
	buildAiBody,
	listAiModels,
	parseAi,
	saveAi,
	validateAi,
} from './aiTutor';
import {
	applyPreset,
	contrastOn,
	designChanged,
	designDraft,
	getDesign,
	matchingPreset,
	parseDesign,
	resetDesign,
	saveDesign,
	validateDesign,
} from './designSystem';
import {
	buildGatewayPatch,
	gatewayDirty,
	gatewayDraft,
	listGateways,
	parseGateway,
	saveGateway,
	validateGateway,
	visibleFields,
} from './gateways';
import {
	hubIconsDirty,
	hubIconsDraft,
	parseHubIcons,
	saveHubIcons,
} from './hubIcons';
import { manageMcp, parseMcp } from './mcp';
import { describeFailure } from './model';
import {
	aiRaw,
	designRaw,
	hubIconsRaw,
	mcpRaw,
	offlineRaw,
	stripeRaw,
	xapiRaw,
} from './tabFixtures';
import {
	buildXapiBody,
	parseXapi,
	runXapiAction,
	saveXapi,
	validateXapi,
	xapiDirty,
	xapiDraft,
} from './xapi';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
	post: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'payment gateways model', () => {
	it( 'parses a gateway and keeps credentials out of the values', () => {
		const gateway = parseGateway( {
			...stripeRaw,
			values: { ...stripeRaw.values, secret_key: 'sk_live_leak' },
		} );
		expect( gateway.values ).not.toHaveProperty( 'secret_key' );
		expect( gateway.secrets ).toEqual( {
			sandbox_secret_key: { hasValue: true },
			secret_key: { hasValue: false },
		} );
		expect( gateway.mode ).toBe( 'test' );
		expect( () => parseGateway( { ...stripeRaw, mode: 'x' } ) ).toThrow();
	} );

	it( 'sends only changes, never an empty secret, and clears only saved ones', () => {
		const gateway = parseGateway( stripeRaw );
		const draft = gatewayDraft( gateway );
		expect( buildGatewayPatch( gateway, draft ) ).toEqual( {} );
		expect( gatewayDirty( gateway, draft ) ).toBe( false );
		draft.secrets.sandbox_secret_key = '';
		draft.clear = [ 'secret_key' ];
		expect( buildGatewayPatch( gateway, draft ) ).toEqual( {} );
		const changed = {
			...draft,
			enabled: true,
			mode: 'live' as const,
			values: { ...draft.values, title: 'Pay' },
			secrets: { secret_key: 'sk_live_new' },
			clear: [ 'sandbox_secret_key' ],
		};
		expect( buildGatewayPatch( gateway, changed ) ).toEqual( {
			enabled: true,
			mode: 'live',
			values: { title: 'Pay' },
			secrets: { secret_key: 'sk_live_new' },
			clear_secrets: [ 'sandbox_secret_key' ],
		} );
	} );

	it( 'validates like the server and shows fields of the chosen mode', () => {
		const gateway = parseGateway( stripeRaw );
		const draft = {
			...gatewayDraft( gateway ),
			values: { ...gateway.values, title: ' ' },
			secrets: { secret_key: 'has space' },
		};
		expect( validateGateway( gateway, draft ) ).toEqual( {
			'values.title': 'This field cannot be empty.',
			'secrets.secret_key': expect.stringContaining( 'printable' ),
		} );
		expect(
			visibleFields( gateway, 'live' ).map( ( field ) => field.name )
		).toEqual( [ 'title', 'secret_key', 'payment_method_card' ] );
	} );

	it( 'calls the gateway routes', async () => {
		vi.mocked( get ).mockResolvedValue( [ stripeRaw, offlineRaw ] );
		expect( ( await listGateways() ).map( ( item ) => item.id ) ).toEqual( [
			'stripe',
			'offline',
		] );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/payment-gateways',
			expect.any( Object )
		);
		vi.mocked( patch ).mockResolvedValue( {
			...stripeRaw,
			enabled: true,
			changed: [ 'enabled' ],
		} );
		const result = await saveGateway( 'stripe', { enabled: true } );
		expect( result.changed ).toEqual( [ 'enabled' ] );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/payment-gateways/stripe',
			{ enabled: true },
			expect.any( Object )
		);
	} );
} );

describe( 'design system model', () => {
	it( 'parses, recognises presets and applies one', () => {
		const snapshot = parseDesign( designRaw );
		const draft = designDraft( snapshot );
		expect( matchingPreset( draft, snapshot.presets ) ).toBe( 'wordpress' );
		expect( snapshot.admin_scheme ).toBe( 'ocean' );
		expect( snapshot.admin_colors.primary ).toBe( '#567958' );
		const khan = applyPreset( draft, snapshot.presets, 'khan' );
		expect( matchingPreset( khan, snapshot.presets ) ).toBe( 'khan' );
		expect( designChanged( draft, khan ) ).toBe( true );
		expect(
			parseDesign( { ...designRaw, admin_colors: undefined } )
				.admin_colors
		).toEqual( snapshot.colors );
		const classic = applyPreset( draft, snapshot.presets, 'classic' );
		expect( classic.colors.primary ).toBe( '#6e42d3' );
		expect( matchingPreset( classic, snapshot.presets ) ).toBe( 'classic' );
		const custom = {
			...classic,
			colors: { ...classic.colors, primary: '#123456' },
		};
		expect( matchingPreset( custom, snapshot.presets ) ).toBe( 'custom' );
		expect( designChanged( draft, custom ) ).toBe( true );
		expect( designChanged( draft, designDraft( snapshot ) ) ).toBe( false );
	} );

	it( 'validates colours, radius and density', () => {
		const draft = designDraft( parseDesign( designRaw ) );
		expect( validateDesign( draft ) ).toEqual( {} );
		expect(
			validateDesign( {
				...draft,
				colors: { ...draft.colors, primary: 'red' },
				button_radius: 30,
			} )
		).toEqual( {
			'colors.primary': expect.any( String ),
			button_radius: expect.any( String ),
		} );
		expect( validateDesign( { ...draft, button_radius: 999 } ) ).toEqual(
			{}
		);
	} );

	it( 'computes contrast like the server', () => {
		expect( contrastOn( '#ffffff' ) ).toMatchObject( {
			text: '#000000',
			passes: true,
		} );
		expect( contrastOn( '#000000' ).text ).toBe( '#ffffff' );
		expect( contrastOn( '#1865f2' ) ).toMatchObject( {
			text: '#ffffff',
			passes: true,
		} );
		expect( contrastOn( '#ffb100' ) ).toMatchObject( {
			text: '#000000',
			passes: true,
		} );
	} );

	it( 'reads design-system field errors', () => {
		const view = describeFailure(
			new ApiError( {
				status: 400,
				code: 'ohmylms_invalid_design',
				message: 'Some design settings are not valid.',
				details: { status: 400, fields: { 'colors.primary': 'Bad' } },
			} )
		);
		expect( view.fieldErrors ).toEqual( { 'colors.primary': 'Bad' } );
	} );

	it( 'reads, saves and resets through /design-system', async () => {
		vi.mocked( get ).mockResolvedValue( designRaw );
		vi.mocked( post ).mockResolvedValue( designRaw );
		vi.mocked( del ).mockResolvedValue( designRaw );
		const snapshot = await getDesign();
		await saveDesign( designDraft( snapshot ) );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/design-system',
			designDraft( snapshot ),
			expect.any( Object )
		);
		await resetDesign();
		expect( del ).toHaveBeenCalledWith(
			'/ohmylms/v1/design-system',
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValue( { ...designRaw, density: 'huge' } );
		await expect( getDesign() ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
} );

describe( 'xAPI model', () => {
	it( 'parses 0/1 switches and never needs the secret', () => {
		const snapshot = parseXapi( xapiRaw );
		expect( snapshot.settings ).toMatchObject( {
			enabled: false,
			responses: true,
			groups: { enrolled: true, lessons: false },
		} );
		expect( snapshot.hasSecret ).toBe( true );
		expect( snapshot.failures[ 0 ] ).toMatchObject( { tries: 5 } );
	} );

	it( 'keeps the secret unless one is typed or cleared', () => {
		const draft = xapiDraft( parseXapi( xapiRaw ) );
		expect( buildXapiBody( draft ) ).not.toHaveProperty( 'secret' );
		expect(
			buildXapiBody( { ...draft, secret: 'new-secret' } )
		).toMatchObject( { secret: 'new-secret' } );
		const cleared = buildXapiBody( {
			...draft,
			secret: 'ignored',
			clearSecret: true,
		} );
		expect( cleared ).toMatchObject( { clear_secret: true } );
		expect( cleared ).not.toHaveProperty( 'secret' );
	} );

	it( 'validates and tracks changes', () => {
		const snapshot = parseXapi( xapiRaw );
		const draft = xapiDraft( snapshot );
		expect( xapiDirty( snapshot, draft ) ).toBe( false );
		expect(
			xapiDirty( snapshot, {
				...draft,
				groups: { ...draft.groups, lessons: true },
			} )
		).toBe( true );
		expect(
			validateXapi( {
				...draft,
				endpoint: 'ftp://x',
				retention_days: 400,
				secret: 'a b',
			} )
		).toEqual( {
			endpoint: expect.any( String ),
			retention_days: expect.any( String ),
			secret: expect.any( String ),
		} );
		expect(
			validateXapi( { ...draft, enabled: true, endpoint: '' } )
		).toHaveProperty( 'endpoint' );
	} );

	it( 'posts settings and actions', async () => {
		vi.mocked( post ).mockResolvedValueOnce( xapiRaw );
		await saveXapi( xapiDraft( parseXapi( xapiRaw ) ) );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/xapi/settings',
			expect.not.objectContaining( { secret: expect.anything() } ),
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( {
			ok: true,
			message: 'Connected.',
			snapshot: xapiRaw,
		} );
		expect( await runXapiAction( 'test' ) ).toMatchObject( {
			ok: true,
			message: 'Connected.',
		} );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/xapi/settings/test',
			undefined,
			expect.any( Object )
		);
	} );
} );

describe( 'AI tutor model', () => {
	it( 'parses the key status without a key', () => {
		const snapshot = parseAi( aiRaw );
		expect( snapshot ).toMatchObject( {
			hasKey: true,
			keyHint: '…a1b2',
			keyFrom: 'stored',
		} );
		expect( JSON.stringify( snapshot ) ).not.toContain( '"key":' );
	} );

	it( 'sends the key only when typed and clear_key only when removing', () => {
		const draft = aiDraft( parseAi( aiRaw ) );
		expect( buildAiBody( draft ) ).not.toHaveProperty( 'key' );
		expect( buildAiBody( { ...draft, key: ' sk-1 ' } ) ).toMatchObject( {
			key: 'sk-1',
		} );
		expect(
			buildAiBody( { ...draft, key: 'sk-1', clearKey: true } )
		).toMatchObject( { clear_key: true } );
		expect( buildAiBody( draft ) ).toMatchObject( {
			practice_style: 'standard',
			daily_limit: 30,
		} );
	} );

	it( 'validates limits, models and tools that need a key', () => {
		const snapshot = parseAi( aiRaw );
		const draft = aiDraft( snapshot );
		expect( aiDirty( snapshot, draft ) ).toBe( false );
		expect( aiDirty( snapshot, { ...draft, key: 'x' } ) ).toBe( true );
		expect( validateAi( draft, snapshot ) ).toEqual( {} );
		expect(
			validateAi(
				{ ...draft, enabled: true, clearKey: true, daily_limit: 0 },
				snapshot
			)
		).toEqual( {
			key: expect.any( String ),
			daily_limit: expect.any( String ),
		} );
		expect(
			validateAi( { ...draft, model: 'bad model!' }, snapshot )
		).toHaveProperty( 'model' );
	} );

	it( 'posts settings and lists models', async () => {
		vi.mocked( post ).mockResolvedValueOnce( aiRaw );
		await saveAi( aiDraft( parseAi( aiRaw ) ) );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/ai',
			expect.any( Object ),
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( {
			provider: 'anthropic',
			models: [ 'a', 'b' ],
			truncated: false,
		} );
		expect( ( await listAiModels() ).models ).toEqual( [ 'a', 'b' ] );
	} );
} );

describe( 'MCP model', () => {
	it( 'parses providers and returns a token only after generate', async () => {
		expect( parseMcp( mcpRaw ).providers[ 0 ] ).toMatchObject( {
			id: 'openai',
			configured: true,
		} );
		expect( () =>
			parseMcp( {
				...mcpRaw,
				providers: [ { id: 'evil', label: 'x', configured: true } ],
			} )
		).toThrow();
		vi.mocked( post ).mockResolvedValueOnce( {
			...mcpRaw,
			token: 'oml_mcp_x',
		} );
		const result = await manageMcp( 'anthropic', 'generate' );
		expect( result.token ).toBe( 'oml_mcp_x' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/mcp/settings/anthropic',
			{ action: 'generate' },
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( mcpRaw );
		expect( ( await manageMcp( 'openai', 'revoke' ) ).token ).toBeNull();
	} );
} );

describe( 'hub icons model', () => {
	it( 'drafts, tracks changes and posts the icon map', async () => {
		const slots = parseHubIcons( hubIconsRaw );
		const draft = hubIconsDraft( slots );
		expect( hubIconsDirty( slots, draft ) ).toBe( false );
		draft.lesson = { id: 5, url: 'https://site.test/l.png' };
		expect( hubIconsDirty( slots, draft ) ).toBe( true );
		vi.mocked( post ).mockResolvedValueOnce( hubIconsRaw );
		await saveHubIcons( draft );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/hub-icons',
			{ icons: { lesson: 5, quiz: 7, logo: 0 } },
			expect.any( Object )
		);
		expect( getCollection ).not.toHaveBeenCalled();
	} );
} );
