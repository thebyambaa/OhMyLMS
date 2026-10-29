import beautifier from 'js-beautify/js/lib/beautify-html.js';
import { createElement, Fragment } from '@wordpress/element';
import { getHostVars } from './host-settings.mjs';

/**
 * Rendering differences between the recovered core and the published one that can be
 * restored around the package without changing it.
 */

const PLACEHOLDER_KEYS = {
	'Drop a Wrapper block here': 'Drop_a_Wrapper_block_here',
	'Drop a Section block here': 'Drop_a_Section_block_here',
	'Drop a Column block here': 'Drop_a_Column_block_here',
	'Drop a content block here': 'Drop_a_content_block_here',
};

/**
 * The recovered core ignores hidden blocks' flag and boolean attributes. The published
 * core skips hidden blocks and prints boolean attributes. Return the tree the recovered
 * core would have rendered, copying only the nodes that need it.
 * @param node
 */
export function withoutPublishedOnlyFlags( node ) {
	if ( ! node || 'object' !== typeof node ) {
		return node;
	}
	const children = Array.isArray( node.children )
		? node.children.map( withoutPublishedOnlyFlags )
		: node.children;
	const hidden = node.data?.hidden;
	const attributes = node.attributes;
	const hasBoolean =
		attributes &&
		Object.values( attributes ).some(
			( value ) => 'boolean' === typeof value
		);
	const childrenChanged =
		Array.isArray( children ) &&
		children.some( ( child, index ) => child !== node.children[ index ] );
	if ( ! hidden && ! hasBoolean && ! childrenChanged ) {
		return node;
	}
	const copy = { ...node, children };
	if ( hidden ) {
		copy.data = { ...node.data };
		delete copy.data.hidden;
	}
	if ( hasBoolean ) {
		copy.attributes = Object.fromEntries(
			Object.entries( attributes ).filter(
				( [ , value ] ) => 'boolean' !== typeof value
			)
		);
	}
	return copy;
}

/**
 * Translate the empty-container hints that the designer draws in testing mode.
 * @param mjml
 */
export function translatePlaceholders( mjml ) {
	const translations = getHostVars()?.mint_trans;
	if ( ! translations ) {
		return mjml;
	}
	return mjml.replace(
		/(<text x="-16" y="50" font-size="5px">)(Drop a (?:Wrapper|Section|Column|content) block here)(<\/text>)/g,
		( match, open, english, close ) =>
			open +
			( translations[ PLACEHOLDER_KEYS[ english ] ] || english ) +
			close
	);
}

/**
 * Format markup like the js-beautify release that the recovered core carried.
 * @param mjml
 */
export function beautifyMjml( mjml ) {
	return beautifier.html_beautify( mjml, { indent_size: 2 } );
}

/**
 * Wrap the package's JsonToMjml.
 * @param upstreamJsonToMjml
 */
export function createJsonToMjml( upstreamJsonToMjml ) {
	return function JsonToMjml( options ) {
		const normalized = options.data
			? { ...options, data: withoutPublishedOnlyFlags( options.data ) }
			: options;
		let mjml = upstreamJsonToMjml( { ...normalized, beautify: false } );
		if ( 'testing' === options.mode ) {
			mjml = translatePlaceholders( mjml );
		}
		return options.beautify ? beautifyMjml( mjml ) : mjml;
	};
}

/**
 * Random suffix of the condition variables, in the recovered core's alphabet and length.
 * @param length
 */
function randomSuffix( length = 5 ) {
	return Array.from(
		globalThis.crypto.getRandomValues( new Uint8Array( length ) )
	)
		.map( ( value ) => {
			const index = value % 64;
			if ( index < 36 ) {
				return index.toString( 36 );
			}
			if ( index < 62 ) {
				return ( index - 26 ).toString( 36 ).toUpperCase();
			}
			return index > 62 ? '-' : '_';
		} )
		.join( '' );
}

/**
 * The recovered core's condition tag. Its falsy test is `x == nil`, not the published
 * `x == nil or x == false`, and its variable names use a 5 character suffix.
 * @param root0
 * @param root0.Operator
 * @param root0.components
 */
export function createConditionTag( { Operator, components } ) {
	return function generateConditionTemplate( option, content ) {
		const { symbol, groups } = option;
		const expression = ( condition ) => {
			if ( condition.operator === Operator.TRUTHY ) {
				return condition.left;
			}
			if ( condition.operator === Operator.FALSY ) {
				return condition.left + ' == nil';
			}
			return (
				condition.left +
				' ' +
				condition.operator +
				' ' +
				( 'number' === typeof condition.right
					? condition.right
					: `"${ condition.right }"` )
			);
		};
		const suffix = randomSuffix();
		const names = groups.map(
			( group, index ) => `con_${ index }_${ suffix }`
		);
		const assignments = groups
			.map(
				( group, index ) =>
					`{% assign ${ names[ index ] } = ${ group.groups
						.map( expression )
						.join( ` ${ group.symbol } ` ) } %}`
			)
			.join( '\n' );
		return createElement(
			Fragment,
			null,
			createElement(
				components.Raw,
				null,
				`
        <!-- htmlmin:ignore -->
        ${ assignments }
        {% if ${ names.join( ` ${ symbol } ` ) } %}
        <!-- htmlmin:ignore -->
        `
			),
			content,
			createElement(
				components.Raw,
				null,
				`
        <!-- htmlmin:ignore -->
        {% endif %}
        <!-- htmlmin:ignore -->
        `
			)
		);
	};
}
