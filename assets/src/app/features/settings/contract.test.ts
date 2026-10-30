import contract from './contract.json';
import { buildGatewayPatch, parseGateway } from './gateways';
import { SPECS, type FieldSpec } from './model';
import { stripeRaw } from './tabFixtures';
import { FIELD_NAMES, GROUP_IDS } from './types';

interface SchemaFragment {
	type: string;
	minimum?: number;
	maximum?: number;
	minLength?: number;
	maxLength?: number;
	maxItems?: number;
	enum?: string[];
	pattern?: string;
}

const groups: Record<
	string,
	{ fields: Record< string, { schema: SchemaFragment } > }
> = contract.groups;

function expectSpec( spec: FieldSpec, schema: SchemaFragment ) {
	switch ( spec.kind ) {
		case 'boolean':
			expect( schema.type ).toBe( 'boolean' );
			break;
		case 'integer':
		case 'number':
			expect( schema.type ).toBe( spec.kind );
			expect( schema.minimum ).toBe( spec.min );
			expect( schema.maximum ?? spec.max ).toBe( spec.max );
			break;
		case 'rates':
			expect( schema.type ).toBe( 'array' );
			expect( schema.maxItems ).toBe( spec.max );
			break;
		default:
			expect( schema.type ).toBe( 'string' );
			expect( schema.minLength ?? 0 ).toBeLessThanOrEqual( spec.min );
			expect( schema.maxLength ?? spec.max ).toBe( spec.max );
			if ( spec.options ) {
				expect( schema.enum ).toEqual( [ ...spec.options ] );
			}
	}
}

describe( 'settings REST contract', () => {
	it( 'describes every route the client calls', () => {
		const paths = contract.routes.map(
			( route ) =>
				`${ [ route.method ].flat().join( '|' ) } ${ route.path }`
		);
		expect( paths ).toEqual( [
			'GET /settings',
			'GET /settings/{group}',
			'PUT|PATCH|POST /settings/{group}',
			'GET /settings/pages',
			'GET /settings/currencies',
			'GET /settings/countries',
			'GET /settings/countries/{code}/states',
			'POST /settings/general/restore-pages',
			'POST /settings/advanced/clear-cache',
			'GET /settings/payment-gateways',
			'GET /settings/payment-gateways/{gateway}',
			'PUT|PATCH|POST /settings/payment-gateways/{gateway}',
		] );
		expect( contract.capability ).toBe( 'manage_options' );
	} );

	it( 'keeps the gateway write keys and limits the client uses', () => {
		const gateways = contract.paymentGateways;
		expect( gateways.writeKeys ).toEqual( [
			'enabled',
			'mode',
			'values',
			'secrets',
			'clear_secrets',
		] );
		expect( gateways.fieldTypes ).toEqual( [
			'text',
			'textarea',
			'toggle',
			'secret',
		] );
		const stripe = parseGateway( stripeRaw );
		const body = buildGatewayPatch( stripe, {
			enabled: true,
			mode: 'live',
			values: { ...stripe.values, title: 'x' },
			secrets: { secret_key: 'sk' },
			clear: [ 'sandbox_secret_key' ],
		} );
		for ( const key of Object.keys( body ) ) {
			expect( gateways.writeKeys ).toContain( key );
		}
		expect( new RegExp( gateways.secretPattern ).test( 'a b' ) ).toBe(
			false
		);
	} );

	it( 'lists the same groups and fields as the client types', () => {
		expect( contract.groupIds ).toEqual( [ ...GROUP_IDS ] );
		for ( const id of GROUP_IDS ) {
			expect( Object.keys( groups[ id ]?.fields ?? {} ) ).toEqual( [
				...FIELD_NAMES[ id ],
			] );
		}
	} );

	it( 'matches the client validation specs field by field', () => {
		for ( const id of GROUP_IDS ) {
			const specs: Record< string, FieldSpec > = SPECS[ id ];
			for ( const name of FIELD_NAMES[ id ] ) {
				const spec = specs[ name ];
				const field = groups[ id ]?.fields[ name ];
				expect( spec ).toBeDefined();
				expect( field ).toBeDefined();
				if ( spec && field ) {
					expectSpec( spec, field.schema );
				}
			}
		}
	} );

	it( 'keeps the stored option of every field the readers use', () => {
		expect( groups.currency?.fields.currency ).toMatchObject( {
			option: 'ohmylms_currency',
		} );
		expect( groups.tax?.fields.tax_rates ).toMatchObject( {
			option: 'ohmylms_tax_rates',
		} );
		expect( groups.permalink?.fields.course_base ).toMatchObject( {
			option: 'ohmylms_permalink',
			member: 'course_base',
		} );
	} );
} );
