import { source } from './fixtures';
import {
	documentUrl,
	pageImageUrl,
	parseRenderer,
	parseSource,
	rendererMessage,
	type RendererStatus,
} from './sourceModel';

const missing: RendererStatus = {
	...source.renderer,
	available: false,
	engine: '',
	version: '',
	reason: 'not_configured',
	crops: false,
};

describe( 'source model', () => {
	it( 'parses the source descriptor and builds page addresses', () => {
		const parsed = parseSource( { ...source, extra: 1 } );
		expect( parsed ).toEqual( source );
		expect( pageImageUrl( parsed, 3 ) ).toBe(
			'https://example.test/wp-json/ohmylms/v1/exam-imports/jobs/7/pages/3?_wpnonce=abc'
		);
		expect( pageImageUrl( parsed, 0 ) ).toContain( '/pages/1?' );
		expect( documentUrl( parsed, 2 ) ).toBe(
			`${ source.document }#page=2`
		);
		expect(
			parseSource( { ...source, mark_scheme: '' } ).mark_scheme
		).toBe( '' );
	} );

	it( 'rejects descriptors outside the contract', () => {
		for ( const bad of [
			{ ...source, page_url: source.document },
			{ ...source, document: 'javascript:alert(1)' },
			{ ...source, document: '' },
			{ ...source, mark_scheme: '/relative.pdf' },
			{ ...source, page_count: -1 },
			{ ...source, renderer: { ...source.renderer, reason: 'other' } },
			{ ...source, renderer: { ...source.renderer, crops: 'yes' } },
		] ) {
			expect( () => parseSource( bad ) ).toThrow( /invalid exam data/ );
		}
		expect( () => parseRenderer( null ) ).toThrow();
	} );

	it( 'describes an available renderer', () => {
		expect( rendererMessage( source.renderer ) ).toEqual( {
			tone: 'success',
			text: expect.stringContaining(
				'Poppler 24.08.0 (up to 2400 pixels)'
			),
		} );
		expect(
			rendererMessage( { ...source.renderer, crops: false } ).text
		).toContain( 'crops cannot be made' );
	} );

	it( 'gives guidance for every missing renderer', () => {
		const texts = (
			[
				'not_configured',
				'process_disabled',
				'binary_unusable',
				'storage',
				'failed',
			] as const
		 ).map( ( reason ) => {
			const message = rendererMessage( { ...missing, reason } );
			expect( message.tone ).toBe( 'warning' );
			expect( message.text ).toContain( "browser's PDF viewer" );
			return message.text;
		} );
		expect( new Set( texts ).size ).toBe( 5 );
		expect( texts[ 0 ] ).toContain( 'OHMYLMS_PDFTOPPM_BINARY' );
		expect( texts[ 1 ] ).toContain( 'proc_open' );
		expect( texts[ 3 ] ).toContain( 'OHMYLMS_IMPORT_PRIVATE_DIR' );
		expect(
			rendererMessage( { ...missing, imagick: true } ).text
		).toContain( 'Imagick is installed' );
	} );
} );
