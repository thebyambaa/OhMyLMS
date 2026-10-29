import { ApiError } from '../../lib/api';
import { certificate, options } from './fixtures';
import {
	appendPlaceholder,
	applySamples,
	findPlaceholders,
	formFromCertificate,
	isDirty,
	isValid,
	moveBlock,
	newBlock,
	nextBlockId,
	parseCertificate,
	parseCourseChoice,
	parseMedia,
	parseOptions,
	parsePreview,
	serverFieldError,
	toInput,
	toPreviewInput,
	unknownPlaceholders,
	validateForm,
	validateGeometry,
} from './model';

const known = options.placeholders.map( ( item ) => item.value );

describe( 'certificate response boundary', () => {
	it( 'accepts the contract shape and rejects drifted fields', () => {
		expect(
			parseCertificate( { ...certificate, extra: 'ignored' } )
		).toEqual( certificate );
		expect( () =>
			parseCertificate( { ...certificate, id: '7' } )
		).toThrow();
		expect( () =>
			parseCertificate( { ...certificate, status: 'trash' } )
		).toThrow();
		expect( () =>
			parseCertificate( { ...certificate, orientation: 'square' } )
		).toThrow();
		expect( () =>
			parseCertificate( { ...certificate, signature: null } )
		).toThrow();
		expect( () =>
			parseCertificate( {
				...certificate,
				blocks: [ { ...certificate.blocks[ 0 ], align: 'justify' } ],
			} )
		).toThrow();
		expect( () =>
			parseCertificate( { ...certificate, course_ids: [ 0 ] } )
		).toThrow();
		expect( () => parseCertificate( null ) ).toThrow();
		expect( () => parseCertificate( [] ) ).toThrow();
	} );
	it( 'validates options, previews, courses and media', () => {
		expect( parseOptions( options ) ).toEqual( options );
		expect( () => parseOptions( { ...options, sizes: [] } ) ).toThrow();
		expect( () => parseOptions( { ...options, statuses: [] } ) ).toThrow();
		expect( () =>
			parseOptions( { ...options, limits: { max_blocks: 0 } } )
		).toThrow();
		expect(
			parsePreview( { html: '<p>x</p>', width_mm: 297, height_mm: 210 } )
		).toEqual( { html: '<p>x</p>', width_mm: 297, height_mm: 210 } );
		expect( () =>
			parsePreview( { html: '<p>x</p>', width_mm: 0, height_mm: 210 } )
		).toThrow();
		expect(
			parseCourseChoice( { id: 3, title: 'Algebra', status: 'draft' } )
		).toEqual( { id: 3, title: 'Algebra', status: 'draft' } );
		const media = {
			id: 9,
			source_url: 'https://example.test/a.png',
			mime_type: 'image/png',
			title: { rendered: 'Logo' },
			media_details: {
				sizes: {
					thumbnail: { source_url: 'https://example.test/a-150.png' },
				},
			},
		};
		expect( parseMedia( media ).thumbnail_url ).toBe(
			'https://example.test/a-150.png'
		);
		expect(
			parseMedia( { ...media, media_details: undefined } ).thumbnail_url
		).toBe( 'https://example.test/a.png' );
		expect( () =>
			parseMedia( { ...media, mime_type: 'image/svg+xml' } )
		).toThrow();
	} );
} );

describe( 'placeholders', () => {
	it( 'finds distinct tokens and reports the unknown ones', () => {
		expect( findPlaceholders( '{a} and {b} and {a}' ) ).toEqual( [
			'{a}',
			'{b}',
		] );
		expect(
			unknownPlaceholders( 'Dear {student_name} {first_name}', known )
		).toEqual( [ '{first_name}' ] );
		expect( unknownPlaceholders( 'No tokens here', known ) ).toEqual( [] );
		expect( unknownPlaceholders( 'Open { brace', known ) ).toEqual( [] );
	} );
	it( 'appends tokens with a separating space and applies samples', () => {
		expect( appendPlaceholder( '', '{date}' ) ).toBe( '{date}' );
		expect( appendPlaceholder( 'Issued on', '{date}' ) ).toBe(
			'Issued on {date}'
		);
		expect( appendPlaceholder( 'Issued on ', '{date}' ) ).toBe(
			'Issued on {date}'
		);
		expect(
			applySamples( 'Hi {student_name}, {date}', options.placeholders )
		).toBe( 'Hi Alex Morgan, October 30, 2026' );
	} );
} );

describe( 'geometry', () => {
	it.each( [
		[ '10', '20', '80', null ],
		[ '0', '0', '100', null ],
		[ '50', '50', '50', null ],
	] )( 'accepts %s/%s/%s', ( x, y, width, expected ) => {
		expect( validateGeometry( x, y, width ) ).toBe( expected );
	} );
	it.each( [
		[ '', '20', '80' ],
		[ 'abc', '20', '80' ],
		[ '-1', '20', '80' ],
		[ '101', '20', '80' ],
		[ '10', '101', '80' ],
		[ '10', '20', '0' ],
		[ '10', '20', '101' ],
		[ '50', '20', '51' ],
		[ 'Infinity', '20', '80' ],
	] )( 'rejects %s/%s/%s', ( x, y, width ) => {
		expect( validateGeometry( x, y, width ) ).not.toBeNull();
	} );
} );

describe( 'form state', () => {
	const form = formFromCertificate( certificate );

	it( 'round trips a template into an editable form without sharing references', () => {
		expect( form.blocks[ 0 ]?.x ).toBe( '10' );
		expect( form.courses ).toEqual( [ { id: 11, title: 'Algebra' } ] );
		expect( form.courses[ 0 ] ).not.toBe( certificate.courses[ 0 ] );
		expect( isDirty( form, formFromCertificate( certificate ) ) ).toBe(
			false
		);
		expect( isDirty( { ...form, name: 'Other' }, form ) ).toBe( true );
	} );
	it( 'converts the form to a trimmed, typed payload without mutating it', () => {
		const edited = {
			...form,
			name: '  Spaced  ',
			background_color: '#ABCDEF ',
			blocks: [
				{
					...form.blocks[ 0 ]!,
					text: ' Hello ',
					x: '12.5',
					color: '#FF0000',
				},
			],
		};
		const input = toInput( edited );
		expect( input.name ).toBe( 'Spaced' );
		expect( input.background_color ).toBe( '#abcdef' );
		expect( input.blocks[ 0 ] ).toMatchObject( {
			text: 'Hello',
			x: 12.5,
			color: '#ff0000',
		} );
		expect( input.course_ids ).toEqual( [ 11 ] );
		expect( input.signature ).toEqual( {
			image_id: 0,
			x: 65,
			y: 80,
			width: 25,
		} );
		expect( edited.name ).toBe( '  Spaced  ' );
		const preview = toPreviewInput( input );
		expect( preview ).not.toHaveProperty( 'name' );
		expect( preview ).not.toHaveProperty( 'course_ids' );
		expect( preview ).not.toHaveProperty( 'status' );
	} );
	it( 'accepts a valid form', () => {
		expect( validateForm( form, options ) ).toEqual( {} );
		expect( isValid( {} ) ).toBe( true );
	} );
	it( 'reports every kind of problem under a stable field path', () => {
		const block = form.blocks[ 0 ]!;
		const broken = {
			...form,
			name: ' ',
			background_color: 'red',
			signature_x: '90',
			signature_width: '30',
			size: 'a0',
			blocks: [
				{ ...block, text: 'Dear {first_name}' },
				{ ...block, id: 'two', text: '   ' },
				{ ...block, id: 'three', text: 'x'.repeat( 501 ) },
				{ ...block, id: 'four', x: '60', width: '50', font_size: '7' },
				{ ...block, id: 'five', color: 'blue' },
			],
		};
		const errors = validateForm( broken, options );
		expect( Object.keys( errors ).sort() ).toEqual(
			[
				'name',
				'layout',
				'background_color',
				'signature',
				'blocks.title.text',
				'blocks.two.text',
				'blocks.three.text',
				'blocks.four.layout',
				'blocks.four.font_size',
				'blocks.five.color',
			].sort()
		);
		expect( errors[ 'blocks.title.text' ] ).toContain( '{first_name}' );
		expect( isValid( errors ) ).toBe( false );
	} );
	it( 'rejects duplicate and malformed block ids', () => {
		const block = form.blocks[ 0 ]!;
		expect(
			validateForm(
				{ ...form, blocks: [ block, { ...block } ] },
				options
			)[ 'blocks.title.text' ]
		).toBeDefined();
		expect(
			validateForm(
				{ ...form, blocks: [ { ...block, id: 'a b' } ] },
				options
			)[ 'blocks.a b.text' ]
		).toBeDefined();
	} );
	it( 'enforces the block and course limits from the server', () => {
		const block = form.blocks[ 0 ]!;
		const many = Array.from( { length: 21 }, ( _, index ) => ( {
			...block,
			id: `b${ index }`,
		} ) );
		expect(
			validateForm( { ...form, blocks: many }, options ).blocks
		).toBeDefined();
		const courses = Array.from( { length: 201 }, ( _, index ) => ( {
			id: index + 1,
			title: 'c',
		} ) );
		expect(
			validateForm( { ...form, courses }, options ).courses
		).toBeDefined();
	} );
	it( 'creates, orders and numbers blocks', () => {
		const created = newBlock( form.blocks );
		expect( created.id ).toBe( 'block-3' );
		expect( created.y ).toBe( '54' );
		expect(
			validateForm(
				{ ...form, blocks: [ ...form.blocks, created ] },
				options
			)
		).toEqual( {} );
		expect( newBlock( [] ).y ).toBe( '50' );
		expect( nextBlockId( [ { ...created, id: 'block-2' } ] ) ).toBe(
			'block-3'
		);
		expect( moveBlock( [ 1, 2, 3 ], 0, 1 ) ).toEqual( [ 2, 1, 3 ] );
		expect( moveBlock( [ 1, 2, 3 ], 0, -1 ) ).toEqual( [ 1, 2, 3 ] );
		expect( moveBlock( [ 1, 2, 3 ], 2, 1 ) ).toEqual( [ 1, 2, 3 ] );
	} );
} );

describe( 'server field errors', () => {
	const error = ( code: string, details: unknown ) =>
		new ApiError( { status: 422, code, message: 'Problem', details } );
	it( 'maps field and block errors to form paths', () => {
		expect(
			serverFieldError(
				error( 'ohmylms_certificate_invalid_name', { field: 'name' } )
			)
		).toEqual( { path: 'name', message: 'Problem' } );
		expect(
			serverFieldError(
				error( 'ohmylms_certificate_unknown_placeholder', {
					block_id: 'a',
					field: 'a',
				} )
			)
		).toEqual( { path: 'blocks.a.text', message: 'Problem' } );
		expect(
			serverFieldError(
				error( 'ohmylms_certificate_block_out_of_bounds', {
					block_id: 'a',
				} )
			)
		).toEqual( { path: 'blocks.a.layout', message: 'Problem' } );
		expect(
			serverFieldError( error( 'rest_forbidden', undefined ) )
		).toBeNull();
		expect( serverFieldError( error( 'x', { status: 500 } ) ) ).toBeNull();
	} );
} );
