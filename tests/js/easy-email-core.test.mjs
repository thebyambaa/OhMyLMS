import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import {
	compareCorpus,
	corpusPath,
	loadCompatibleCore,
	loadRecoveredCore,
	modes,
	render,
} from '../../tools/easy-email-corpus.mjs';
import { installBrowserStubs } from '../../tools/recovered-modules.mjs';
import { createCompatibleCore } from '../../assets/src/shared/easy-email/core.mjs';
import { readHostSettings } from '../../assets/src/shared/easy-email/host-settings.mjs';
import { EASY_EMAIL_CORE_EXPORTS } from '../../assets/src/shared/easy-email/index.mjs';

/**
 * The authored wrapper around the published easy-email-core must behave like the
 * recovered core it replaces. The recovered factory is still in assets/src/modules/dist, so
 * these tests run both and compare. See docs/EASY-EMAIL-AUDIT.md.
 */
const corpus = JSON.parse( fs.readFileSync( corpusPath, 'utf8' ) );
const published = createRequire( import.meta.url )( 'easy-email-core' );
const hostRequire = createRequire( import.meta.url );

/** Both cores under the same page globals. */
function bothCores( options = {} ) {
	const { rtl } = options;
	const vars =
		'vars' in options
			? options.vars
			: { images_url: 'https://assets.test/images/' };
	installBrowserStubs();
	if ( vars ) globalThis.MRM_Vars = vars;
	else delete globalThis.MRM_Vars;
	globalThis.document = {
		querySelector: () => ( {
			getAttribute: () => ( rtl ? 'rtl' : null ),
		} ),
	};
	const recovered = loadRecoveredCore( { stubs: false } );
	const wrapper = createCompatibleCore( published, readHostSettings() );
	return { recovered, wrapper };
}

const ids = ( text ) => text.replace( /con_(\d+)_[\w-]+/g, 'con_$1_ID' );
const everyBlockType = ( core ) => Object.keys( core.BlockManager.blocksMap );

const payloads = [
	undefined,
	{ attributes: { padding: '1px 2px', 'css-class': 'x' } },
	{ data: { value: { content: 'Hello', elements: [ { href: 'a' } ] } } },
	{
		data: {
			value: {
				images: [ { src: 'one' } ],
				links: [ { href: 'l', content: 'L' } ],
			},
		},
		children: [ { type: 'text', data: { value: {} }, attributes: {} } ],
	},
];

const hostScenarios = {
	'no page globals': { vars: undefined },
	'images path only': { vars: { images_url: 'https://assets.test/images/' } },
	'translated and configured': {
		rtl: true,
		vars: {
			images_url: 'https://cdn.test/i/',
			mint_trans: {
				Wrapper: 'Боодол',
				Section: 'Хэсэг',
				Column: 'Багана',
				Text: 'Текст',
				Image: 'Зураг',
				Group: 'Бүлэг',
				Button: 'Товч',
				Divider: 'Хуваагч',
				Spacer: 'Зай',
				Hero: 'Гарчиг',
				Navbar: 'Цэс',
				Social: 'Сошиал',
				Raw: 'HTML',
				Template: 'Загвар',
				Table: 'Хүснэгт',
				text_block_content: 'Сайн уу',
				Drop_a_Wrapper_block_here: 'Боодол энд',
				Drop_a_Section_block_here: 'Хэсэг энд',
				Drop_a_Column_block_here: 'Багана энд',
				Drop_a_content_block_here: 'Агуулга энд',
			},
			business_basic_settings: { business_name: 'Acme & Sons' },
			address: '1 Road',
			business_social_settings: {
				socialMedia: [
					{ url: 'https://facebook.com/acme', icon: 'fb.png' },
					{ url: 'https://x.com/acme', icon: 'x.png' },
					{ url: 'https://example.test/', icon: 'o.png' },
				],
			},
		},
	},
};

test( 'the wrapper renders all 74 recorded outputs identically', async () => {
	const compatible = await loadCompatibleCore();
	const { same, different } = compareCorpus( corpus, compatible );
	assert.deepEqual( { same, different }, { same: 74, different: 0 } );
} );

test( 'the wrapper exports the recovered core names with the same kinds of value', async () => {
	const { recovered } = bothCores();
	const compatible = await loadCompatibleCore();
	assert.equal( Object.keys( recovered ).length, 39 );
	assert.deepEqual(
		[ ...EASY_EMAIL_CORE_EXPORTS ].sort(),
		Object.keys( recovered ).sort()
	);
	for ( const name of Object.keys( recovered ) ) {
		assert.equal(
			typeof compatible[ name ],
			typeof recovered[ name ],
			name
		);
	}
	for ( const name of Object.keys( compatible ) )
		assert.ok( name in recovered, `unexpected export ${ name }` );
	assert.deepEqual( compatible.BasicType, recovered.BasicType );
	assert.deepEqual( compatible.AdvancedType, recovered.AdvancedType );
	assert.deepEqual(
		Object.keys( compatible.components ),
		Object.keys( recovered.components )
	);
} );

for ( const [ label, scenario ] of Object.entries( hostScenarios ) ) {
	test( `block registry, names, defaults and footers match the recovered core (${ label })`, () => {
		const { recovered, wrapper } = bothCores( scenario );
		assert.deepEqual(
			Object.keys( wrapper.standardBlocks ),
			Object.keys( recovered.standardBlocks )
		);
		assert.deepEqual(
			Object.keys( wrapper.advancedBlocks ),
			Object.keys( recovered.advancedBlocks )
		);
		assert.deepEqual(
			everyBlockType( wrapper ),
			everyBlockType( recovered )
		);
		for ( const type of everyBlockType( recovered ) ) {
			const expected = recovered.BlockManager.getBlockByType( type );
			const actual = wrapper.BlockManager.getBlockByType( type );
			assert.equal( actual.name, expected.name, type );
			assert.equal( actual.type, expected.type, type );
			assert.deepEqual(
				actual.validParentType,
				expected.validParentType,
				type
			);
		}
		// The recovered core shared one social element list between all new social blocks, so
		// a payload merged into an advanced block leaked into later defaults. Each payload
		// therefore gets fresh cores, and the wrapper hands out fresh lists.
		for ( const payload of payloads ) {
			const fresh = bothCores( scenario );
			for ( const type of everyBlockType( fresh.recovered ) ) {
				const make = ( core ) =>
					JSON.stringify(
						core.createBlockDataByType(
							type,
							structuredClone( payload )
						)
					);
				assert.equal(
					make( fresh.wrapper ),
					make( fresh.recovered ),
					type
				);
			}
		}
		assert.throws( () => wrapper.createBlockDataByType( 'nope' ), {
			message: 'No match `nope` block',
		} );
	} );

	test( `testing and production renders match the recovered core (${ label })`, () => {
		const { recovered, wrapper } = bothCores( scenario );
		for ( const entry of corpus ) {
			for ( const mode of modes ) {
				assert.equal(
					render( wrapper, entry.data, mode ),
					render( recovered, entry.data, mode ),
					`${ entry.name } [${ mode }]`
				);
			}
		}
	} );
}

test( 'an empty image keeps an empty source in testing mode instead of a demo image', () => {
	const { wrapper } = bothCores();
	const image = wrapper.createBlockDataByType( 'image' );
	const page = wrapper.createBlockDataByType( 'page' );
	const column = wrapper.createBlockDataByType( 'column' );
	const section = wrapper.createBlockDataByType( 'section' );
	column.children = [ image ];
	section.children = [ column ];
	page.children[ 0 ].children = [ section ];
	const output = render( wrapper, page, 'testing' );
	assert.match( output, /<mj-image [^>]*src=""/ );
	assert.doesNotMatch( output, /easy-email-m-ryan\.vercel\.app/ );
} );

test( 'social icons come from the plugin images path', () => {
	const { wrapper } = bothCores( {
		vars: { images_url: 'https://assets.test/images/' },
	} );
	assert.equal(
		wrapper.createBlockDataByType( 'social' ).data.value.elements[ 0 ].src,
		'https://assets.test/images/social-facebook.png'
	);
	const { wrapper: bare } = bothCores( { vars: undefined } );
	assert.equal(
		bare.createBlockDataByType( 'social' ).data.value.elements[ 0 ].src,
		'undefinedsocial-facebook.png'
	);
} );

test( 'new text blocks align right on right-to-left pages', () => {
	assert.equal(
		bothCores( { rtl: true } ).wrapper.createBlockDataByType( 'text' )
			.attributes.align,
		'right'
	);
	assert.equal(
		bothCores( { rtl: false } ).wrapper.createBlockDataByType( 'text' )
			.attributes.align,
		'left'
	);
} );

test( 'footer blocks render a hero in both modes and honour merge tags, iteration and conditions', () => {
	const { recovered, wrapper } = bothCores();
	for ( const type of [ 'footer', 'advanced_footer' ] ) {
		const footer = wrapper.createBlockDataByType( type );
		const page = wrapper.createBlockDataByType( 'page' );
		page.children[ 0 ].children = [ footer ];
		for ( const mode of modes ) {
			assert.match( render( wrapper, page, mode ), /<mj-hero /, type );
		}
		for ( const [ name, value ] of [
			[
				'iteration',
				{
					enabled: true,
					dataSource: 'items',
					itemName: 'item',
					limit: 2,
					mockQuantity: 3,
				},
			],
			[
				'condition',
				{
					enabled: true,
					symbol: 'and',
					groups: [
						{
							symbol: 'or',
							groups: [
								{ left: 'a', operator: 'falsy', right: '' },
								{ left: 'b', operator: '==', right: 4 },
							],
						},
					],
				},
			],
		] ) {
			const copy = structuredClone( page );
			copy.children[ 0 ].children[ 0 ].data.value[ name ] = value;
			for ( const mode of modes ) {
				assert.equal(
					ids( render( wrapper, copy, mode ) ),
					ids( render( recovered, copy, mode ) ),
					`${ type } ${ name } [${ mode }]`
				);
			}
		}
	}
} );

test( 'variants the corpus does not record still render like the recovered core', () => {
	const { recovered, wrapper } = bothCores();
	const visit = ( node, callback ) => {
		callback( node );
		node.children?.forEach( ( child ) => visit( child, callback ) );
	};
	const variants = {
		base: () => {},
		hidden: ( node ) => {
			if ( 'page' !== node.type ) node.data.hidden = true;
		},
		iteration: ( node ) => {
			node.data.value.iteration = {
				enabled: true,
				dataSource: 'items',
				itemName: 'item',
				limit: 3,
				mockQuantity: 2,
			};
		},
		condition: ( node ) => {
			node.data.value.condition = {
				enabled: true,
				symbol: 'or',
				groups: [
					{
						symbol: 'and',
						groups: [
							{ left: 'a', operator: 'truthy', right: '' },
							{ left: 'b', operator: 'falsy', right: '' },
							{ left: 'c', operator: '==', right: 3 },
							{ left: 'd', operator: '!=', right: 'x' },
						],
					},
					{
						symbol: 'or',
						groups: [ { left: 'e', operator: '>', right: '5' } ],
					},
				],
			};
		},
		'classes and boolean attributes': ( node ) => {
			node.attributes[ 'css-class' ] = 'foo';
			node.attributes.padding = undefined;
			node.attributes.flag = true;
			node.attributes.off = false;
			if ( node.type.endsWith( 'image' ) )
				node.attributes.src = '{{img}}';
		},
		'page settings': ( node ) => {
			if ( 'page' !== node.type ) return;
			node.data.value.responsive = false;
			node.data.value.extraHeadContent = '<meta name="x">';
			node.data.value[ 'user-style' ] = {
				content: 'a{b:c}',
				inline: 'inline',
			};
			node.data.value[ 'content-background-color' ] = '#abc';
		},
	};
	for ( const entry of corpus ) {
		for ( const [ name, change ] of Object.entries( variants ) ) {
			const data = structuredClone( entry.data );
			visit( data, change );
			for ( const mode of modes ) {
				for ( const beautify of [ false, true ] ) {
					const run = ( core ) =>
						ids(
							core.JsonToMjml( {
								data: structuredClone( data ),
								mode,
								beautify,
								context: data,
								dataSource: { items: [ 1, 2 ], a: 1, img: 'x' },
							} )
						);
					assert.equal(
						run( wrapper ),
						run( recovered ),
						`${ entry.name } / ${ name } [${ mode }, beautify ${ beautify }]`
					);
				}
			}
		}
	}
} );

test( 'rendering does not modify the data it is given', () => {
	const { wrapper } = bothCores();
	const data = structuredClone( corpus.at( -1 ).data );
	data.children[ 0 ].data.hidden = true;
	data.children[ 0 ].attributes.flag = true;
	const before = JSON.stringify( data );
	wrapper.JsonToMjml( { data, mode: 'testing', context: data } );
	assert.equal( JSON.stringify( data ), before );
} );

test( 'helper functions behave like the recovered ones on a saved template', () => {
	const { recovered, wrapper } = bothCores();
	const data = structuredClone( corpus.at( -1 ).data );
	const content = { content: data };
	const indexes = [
		'content',
		'content.children[0]',
		'content.children[1]',
		'content.children[1].children[0]',
		'content.children[1].children[1].children[0]',
	];
	for ( const idx of indexes ) {
		for ( const [ name, args ] of [
			[ 'getChildIdx', [ idx, 2 ] ],
			[ 'getParentIdx', [ idx ] ],
			[ 'getSiblingIdx', [ idx, 1 ] ],
			[ 'getIndexByIdx', [ idx ] ],
			[ 'getValueByIdx', [ content, idx ] ],
			[ 'getParentByIdx', [ content, idx ] ],
			[ 'getParentByType', [ content, idx, 'section' ] ],
			[ 'getSameParent', [ content, idx, 'column' ] ],
			[ 'getParenRelativeByType', [ content, idx, 'section' ] ],
			[ 'getNodeIdxClassName', [ idx ] ],
			[ 'getPreviewClassName', [ idx, 'text' ] ],
		] ) {
			assert.deepEqual(
				wrapper[ name ]( ...args ),
				recovered[ name ]( ...args ),
				`${ name }(${ idx })`
			);
		}
	}
	assert.equal( wrapper.getPageIdx(), recovered.getPageIdx() );
	const types = everyBlockType( recovered );
	for ( const type of types ) {
		assert.deepEqual(
			wrapper.getValidChildBlocks( type ).map( ( block ) => block.type ),
			recovered
				.getValidChildBlocks( type )
				.map( ( block ) => block.type ),
			type
		);
		assert.equal(
			wrapper.isAdvancedBlock( type ),
			recovered.isAdvancedBlock( type ),
			type
		);
		assert.equal(
			wrapper.getNodeTypeClassName( type ),
			recovered.getNodeTypeClassName( type )
		);
		for ( const target of types ) {
			assert.equal(
				wrapper.ancestorOf( type, target ),
				recovered.ancestorOf( type, target ),
				`${ type } in ${ target }`
			);
			assert.deepEqual(
				wrapper.BlockManager.getAutoCompletePath( type, target ),
				recovered.BlockManager.getAutoCompletePath( type, target ),
				`${ type } path to ${ target }`
			);
		}
	}
	assert.equal(
		wrapper.isValidBlockData( data ),
		recovered.isValidBlockData( data )
	);
	assert.equal(
		wrapper.getNodeIdxFromClassName( 'node-idx-content.children[1]' ),
		recovered.getNodeIdxFromClassName( 'node-idx-content.children[1]' )
	);
	for ( const params of [
		{ data: { attributes: { a: 'x', b: '', c: true, d: 'q"r' } } },
		{
			data: { type: 'text', attributes: { 'css-class': 'k' } },
			idx: 'content.children[0]',
			mode: 'testing',
			keepClassName: true,
		},
	] ) {
		assert.equal(
			wrapper.getAdapterAttributesString( params ),
			recovered.getAdapterAttributesString( params )
		);
	}
} );

test( 'MjmlToJson converts a parsed MJML tree like the recovered core', () => {
	const { recovered, wrapper } = bothCores();
	// A shorthand-aware stand-in for the one CSS feature the converter uses.
	const spacing = ( value ) => {
		const [ top, right = top, bottom = top, left = right ] =
			String( value ).split( /\s+/ );
		return { top, right, bottom, left };
	};
	globalThis.document = {
		querySelector: () => ( { getAttribute: () => null } ),
		createElement: () => {
			const style = {};
			return {
				style: new Proxy( style, {
					set( target, key, value ) {
						target[ key ] = value;
						if ( 'padding' === key ) {
							const box = spacing( value );
							target.paddingTop = box.top;
							target.paddingRight = box.right;
							target.paddingBottom = box.bottom;
							target.paddingLeft = box.left;
						}
						return true;
					},
				} ),
			};
		},
	};
	const mjml = hostRequire( 'mjml-browser' );
	const source = render( wrapper, corpus.at( -1 ).data, 'production' );
	const { json } = mjml( source, { validationLevel: 'soft' } );
	assert.deepEqual(
		wrapper.MjmlToJson( structuredClone( json ) ),
		recovered.MjmlToJson( structuredClone( json ) )
	);
	assert.throws( () => wrapper.MjmlToJson( { tagName: 'mj-unknown' } ), {
		message: 'unknown block no found ',
	} );
} );

test( 'the package versions are pinned exactly', () => {
	const manifest = JSON.parse(
		fs.readFileSync(
			new URL( '../../package.json', import.meta.url ),
			'utf8'
		)
	);
	for ( const name of [ 'easy-email-core', 'js-beautify', 'lodash' ] ) {
		assert.match(
			manifest.dependencies[ name ],
			/^\d+\.\d+\.\d+$/,
			`${ name } must be pinned`
		);
	}
} );
