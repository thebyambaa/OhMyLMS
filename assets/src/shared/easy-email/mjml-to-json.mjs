import lodash from 'lodash';
import { BasicType } from './block-types.mjs';

/**
 * MjmlToJson, which the recovered core exported itself. The published packages ship it in
 * easy-email-extensions instead. This is the recovered implementation, written out.
 * It needs a browser: it parses XML with DOMParser and lets a detached element normalise
 * CSS shorthand paddings.
 */

const { identity, isString, pickBy } = lodash;
let parser;

/**
 * Merge `padding-top` style longhands back into one `padding` value.
 * @param attributes
 * @param name
 */
function normaliseSpacing( attributes, name ) {
	const element = document.createElement( 'div' );
	Object.keys( attributes ).forEach( ( key ) => {
		if ( new RegExp( `^${ name }` ).test( key ) ) {
			const property = new RegExp( `^${ name }(.*)` ).exec( key )?.[ 0 ];
			if ( property ) {
				element.style[ property ] = attributes[ key ];
				delete attributes[ key ];
			}
		}
	} );
	const value = [
		element.style.paddingTop,
		element.style.paddingRight,
		element.style.paddingBottom,
		element.style.paddingLeft,
	]
		.filter( Boolean )
		.join( ' ' );
	if ( value ) {
		attributes[ name ] = value;
	}
}

/**
 * Page settings that Easy Email stored in `mj-html-attributes`.
 * @param head
 */
function readPageSettings( head ) {
	const settings = head?.children
		?.filter( ( child ) => 'mj-html-attributes' === child.tagName )
		.map( ( child ) => child.children )
		.flat()
		.filter( ( child ) => child && 'easy-email' === child.attributes.class )
		.reduce( ( result, child ) => {
			if ( ! child ) {
				return result;
			}
			const name = child.attributes[ 'attribute-name' ];
			const multiple = Boolean(
				child.attributes[ 'multiple-attributes' ]
			);
			result[ name ] = multiple
				? pickBy(
						{
							...child.attributes,
							'attribute-name': undefined,
							'multiple-attributes': undefined,
							class: undefined,
						},
						identity
					)
				: child.attributes[ name ];
			return result;
		}, {} );
	return pickBy( settings, identity );
}

/**
 * Build the converter on top of the block registry.
 *
 * @param {Object}        BlockManager The core's block registry.
 * @param {() => unknown} getMjml      Returns the MJML renderer. It is only needed for markup
 *                                     input, and is supplied late because the renderer is a
 *                                     separate, lazily loaded package.
 */
export function createMjmlToJson( BlockManager, getMjml ) {
	const convertNode = ( node ) => {
		const attributes = node.attributes;
		if ( 'mjml' === node.tagName ) {
			const body = node.children?.find(
				( child ) => 'mj-body' === child.tagName
			);
			const head = node.children?.find(
				( child ) => 'mj-head' === child.tagName
			);
			const settings = readPageSettings( head );
			const fonts =
				head?.children
					?.filter( ( child ) => 'mj-font' === child.tagName )
					.map( ( child ) => ( {
						name: child.attributes.name,
						href: child.attributes.href,
					} ) ) || [];
			const defaults =
				head?.children?.find(
					( child ) => 'mj-attributes' === child.tagName
				)?.children || [];
			const headStyles = head?.children
				?.filter( ( child ) => 'mj-style' === child.tagName )
				.map( ( child ) => ( {
					content: child.content,
					inline: child.inline,
				} ) );
			const headAttributes = [
				...new Set(
					defaults
						.filter( ( child ) => {
							const font =
								'mj-all' === child.tagName &&
								child.attributes[ 'font-family' ] ===
									settings[ 'font-family' ];
							const color =
								'mj-text' === child.tagName &&
								child.attributes.color ===
									settings[ 'text-color' ];
							const background =
								[ 'mj-wrapper', 'mj-section' ].includes(
									child.tagName
								) &&
								child.attributes[ 'background-color' ] ===
									settings[ 'content-background-color' ];
							return ! font && ! color && ! background;
						} )
						.map(
							( child ) =>
								`<${ child.tagName } ${ Object.keys(
									child.attributes
								)
									.map(
										( key ) =>
											`${ key }="${ child.attributes[ key ] }"`
									)
									.join( ' ' ) } />`
						)
				),
			].join( '\n' );
			const breakpoint = head?.children?.find(
				( child ) => 'mj-breakpoint' === child.tagName
			);
			return BlockManager.getBlockByType( BasicType.PAGE ).create( {
				attributes: body.attributes,
				children: body.children?.map( convertNode ),
				data: {
					value: {
						headAttributes,
						headStyles,
						fonts,
						breakpoint: breakpoint?.attributes.breakpoint,
						...settings,
					},
				},
			} );
		}
		const type = node.tagName.replace( 'mj-', '' ).toLowerCase();
		const block = BlockManager.getBlockByType( type );
		if ( ! block ) {
			throw new Error( `${ type } block no found ` );
		}
		const data = {
			type: block.type,
			attributes,
			data: { value: {} },
			children: [],
		};
		if ( node.content ) {
			data.data.value.content = node.content;
		}
		if ( block.type === BasicType.CAROUSEL ) {
			data.data.value.images =
				node.children?.map( ( child ) => child.attributes ) || [];
			data.children = [];
		} else if ( block.type === BasicType.NAVBAR ) {
			data.data.value.links =
				node.children?.map( ( child ) => {
					const link = {
						color: '#1890ff',
						'font-size': '13px',
						target: '_blank',
						padding: '15px 10px',
						...child.attributes,
						content: child.content,
					};
					normaliseSpacing( link, 'padding' );
					return link;
				} ) || [];
			data.children = [];
		} else if ( block.type === BasicType.SOCIAL ) {
			data.data.value.elements =
				node.children?.map( ( child ) => ( {
					...child.attributes,
					content: child.content,
				} ) ) || [];
			data.children = [];
		} else if ( node.children ) {
			data.children = node.children.map( convertNode );
		}
		const created = block.create( data );
		normaliseSpacing( created.attributes, 'padding' );
		normaliseSpacing( created.attributes, 'inner-padding' );
		return created;
	};

	const parseMarkup = ( markup ) => {
		parser ??= new DOMParser();
		const document_ = parser.parseFromString( markup, 'text/xml' );
		const root = document_.firstChild;
		if ( ! ( document_.firstChild instanceof Element ) ) {
			throw new Error( 'Invalid content' );
		}
		if ( 'mjml' === root.tagName ) {
			const mjml2html = getMjml();
			if ( 'function' !== typeof mjml2html ) {
				throw new Error( 'OhMyLMS npm MJML package is not ready' );
			}
			const { json } = mjml2html( markup, { validationLevel: 'soft' } );
			return MjmlToJson( json );
		}
		const convertElement = ( element ) => {
			if ( 'parsererror' === element.tagName ) {
				throw new Error( 'Invalid content' );
			}
			const attributes = {};
			element.getAttributeNames().forEach( ( name ) => {
				attributes[ name ] = element.getAttribute( name );
			} );
			const type = element.tagName.replace( 'mj-', '' );
			if ( ! (
				BlockManager.getBlockByType( type ) ||
				( element.parentElement &&
					'mj-text' === element.parentElement.tagName )
			) ) {
				throw new Error( 'Invalid content' );
			}
			const data = {
				type,
				attributes,
				data: { value: { content: element.textContent?.trim() } },
				children: [ ...element.children ]
					.filter( ( child ) => child instanceof Element )
					.map( convertElement ),
			};
			if ( type === BasicType.TEXT ) {
				data.data.value.content = element.innerHTML;
				data.children = [];
			}
			return data;
		};
		return convertElement( root );
	};

	/**
	 * Convert MJML markup, or a parsed MJML tree, to Easy Email block data.
	 *
	 * @param {string|Object} input MJML markup or the `json` of an mjml-browser result.
	 */
	function MjmlToJson( input ) {
		return isString( input ) ? parseMarkup( input ) : convertNode( input );
	}
	return MjmlToJson;
}
