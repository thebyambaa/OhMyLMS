import {
	appendInline,
	canSwap,
	findInline,
	inlineBlock,
	removeInline,
	swapInline,
} from './inlineRefs';

const A = inlineBlock( 5, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
const B = inlineBlock( 6, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb' );
const CODE =
	'<!-- wp:paragraph --><p>[ohmylms_question uuid="CCCCCCCC-cccc-4ccc-8ccc-cccccccccccc"]</p><!-- /wp:paragraph -->';

describe( 'inline question references', () => {
	it( 'finds blocks and shortcodes in reading order', () => {
		const refs = findInline( `${ A }\n\n${ CODE }\n\n${ B }` );
		expect(
			refs.map( ( ref ) => [ ref.kind, ref.questionId, ref.uuid ] )
		).toEqual( [
			[ 'block', 5, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' ],
			[ 'shortcode', 0, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc' ],
			[ 'block', 6, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb' ],
		] );
		expect(
			findInline(
				'<!-- wp:ohmylms/shared-multiple-choice {"questionId":"x"} /-->'
			)[ 0 ]
		).toMatchObject( { questionId: 0, uuid: '' } );
	} );
	it( 'swaps only standalone blocks and keeps everything else', () => {
		const content = `<p>x</p>\n${ A }\n<p>y</p>\n${ B }\n<p>z</p>`;
		expect( swapInline( content, 0, 1 ) ).toBe(
			`<p>x</p>\n${ B }\n<p>y</p>\n${ A }\n<p>z</p>`
		);
		expect( swapInline( content, 1, 0 ) ).toBe(
			swapInline( content, 0, 1 )
		);
		const mixed = `${ A }\n${ CODE }`;
		expect( canSwap( findInline( mixed ), 0, 1 ) ).toBe( false );
		expect( swapInline( mixed, 0, 1 ) ).toBe( mixed );
		expect( swapInline( content, 0, 5 ) ).toBe( content );
	} );
	it( 'removes and appends references', () => {
		expect( removeInline( `${ A }\n\n${ B }`, 0 ) ).toBe( B );
		expect( removeInline( CODE, 0 ) ).toBe(
			'<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->'
		);
		expect( removeInline( A, 3 ) ).toBe( A );
		expect(
			appendInline(
				'<p>x</p>\n\n',
				5,
				'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'
			)
		).toBe( `<p>x</p>\n\n${ A }\n` );
		expect(
			appendInline( '', 5, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' )
		).toBe( `${ A }\n` );
		expect( A ).toBe(
			'<!-- wp:ohmylms/shared-multiple-choice {"questionId":5,"uuid":"aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"} /-->'
		);
	} );
} );
