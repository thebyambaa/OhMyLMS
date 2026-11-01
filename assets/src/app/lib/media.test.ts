import { ApiError } from './api';
import { IMAGE_MIME_TYPES, parseMediaImage } from './media';

const MESSAGE = 'The server returned invalid course data.';
const item = {
	id: 9,
	source_url: 'https://example.test/a.png',
	mime_type: 'image/png',
	title: { rendered: 'Logo &amp; mark' },
	media_details: {
		sizes: {
			thumbnail: { source_url: 'https://example.test/a-150.png' },
		},
	},
};

describe( 'parseMediaImage', () => {
	it( 'reads the image, its thumbnail and its rendered title', () => {
		expect( parseMediaImage( item, { invalid: MESSAGE } ) ).toEqual( {
			id: 9,
			url: 'https://example.test/a.png',
			thumbnail_url: 'https://example.test/a-150.png',
			title: 'Logo &amp; mark',
		} );
	} );

	it( 'accepts any type without accept, and only the listed types with it', () => {
		const svg = { ...item, mime_type: 'image/svg+xml' };
		const noType = { ...item, mime_type: undefined };
		expect( parseMediaImage( svg, { invalid: MESSAGE } ).id ).toBe( 9 );
		expect( parseMediaImage( noType, { invalid: MESSAGE } ).id ).toBe( 9 );
		expect( () =>
			parseMediaImage( svg, {
				invalid: MESSAGE,
				accept: IMAGE_MIME_TYPES,
			} )
		).toThrow( ApiError );
		expect( () =>
			parseMediaImage( noType, {
				invalid: MESSAGE,
				accept: IMAGE_MIME_TYPES,
			} )
		).toThrow( MESSAGE );
		for ( const mime of IMAGE_MIME_TYPES ) {
			expect(
				parseMediaImage(
					{ ...item, mime_type: mime },
					{ invalid: MESSAGE, accept: IMAGE_MIME_TYPES }
				).id
			).toBe( 9 );
		}
	} );

	it( 'falls back to the full image for a missing, malformed or empty thumbnail by default', () => {
		for ( const details of [
			undefined,
			{},
			{ sizes: [] },
			{ sizes: { thumbnail: 'x' } },
			{ sizes: { thumbnail: { source_url: '' } } },
			{ sizes: { thumbnail: { source_url: 5 } } },
		] ) {
			expect(
				parseMediaImage(
					{ ...item, media_details: details },
					{ invalid: MESSAGE }
				).thumbnail_url
			).toBe( item.source_url );
		}
	} );

	it( "falls back only for a missing thumbnail with fallback 'missing'", () => {
		const options = { invalid: MESSAGE, fallback: 'missing' } as const;
		expect(
			parseMediaImage( { ...item, media_details: undefined }, options )
				.thumbnail_url
		).toBe( item.source_url );
		expect(
			parseMediaImage(
				{ ...item, media_details: { sizes: {} } },
				options
			).thumbnail_url
		).toBe( item.source_url );
		expect(
			parseMediaImage(
				{
					...item,
					media_details: { sizes: { thumbnail: { source_url: '' } } },
				},
				options
			).thumbnail_url
		).toBe( '' );
		for ( const details of [
			'x',
			{ sizes: [] },
			{ sizes: { thumbnail: 'x' } },
			{ sizes: { thumbnail: { source_url: 5 } } },
		] ) {
			expect( () =>
				parseMediaImage( { ...item, media_details: details }, options )
			).toThrow( MESSAGE );
		}
	} );

	it( 'treats a missing or non-string title as empty', () => {
		expect(
			parseMediaImage(
				{ ...item, title: undefined },
				{ invalid: MESSAGE }
			).title
		).toBe( '' );
		expect(
			parseMediaImage(
				{ ...item, title: { rendered: 3 } },
				{ invalid: MESSAGE }
			).title
		).toBe( '' );
	} );

	it( 'rejects items without a positive id or a source URL, with the feature error', () => {
		const plain = new Error( 'The gradebook sent an unexpected response.' );
		for ( const bad of [
			null,
			[],
			{ ...item, id: 0 },
			{ ...item, id: '9' },
			{ ...item, source_url: undefined },
			{ ...item, title: 'x' },
		] ) {
			expect( () =>
				parseMediaImage( bad, { invalid: () => plain } )
			).toThrow( plain );
		}
	} );
} );
