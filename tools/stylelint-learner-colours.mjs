import fs from 'node:fs';
import stylelint from 'stylelint';

const {
	createPlugin,
	utils: { report, ruleMessages, validateOptions },
} = stylelint;

const ruleName = 'ohmylms/no-raw-svg-colour';
const allowlistPath = new URL( './learner-colour-allowlist.json', import.meta.url );
const namedSource =
	'\\b(?:white|black|red|blue|green|gray|grey|orange|yellow|purple|pink|silver|navy|teal|aqua|fuchsia|maroon|olive|lime|cyan|magenta|brown|gold|indigo|violet|tomato|coral|salmon|crimson|rebeccapurple)\\b';

const messages = ruleMessages( ruleName, {
	rejected: ( value ) =>
		`Unexpected raw colour "${ value }" in an SVG URL. Use var(--oml-*, <previous>) or add the value to tools/learner-colour-allowlist.json.`,
} );

const meta = {
	url: 'https://github.com/ohmylms/ohmylms/blob/main/docs/DESIGN-TOKENS.md',
};

/**
 * @param {string} file
 */
function relativeFile( file ) {
	const normalized = file.replaceAll( '\\', '/' );
	const marker = 'assets/src/frontend/scss/';
	const at = normalized.lastIndexOf( marker );
	return at === -1 ? normalized : normalized.slice( at );
}

function allowlist() {
	const parsed = JSON.parse( fs.readFileSync( allowlistPath, 'utf8' ) );
	const allowed = new Set();
	for ( const entry of parsed.colours || [] ) {
		allowed.add(
			`${ entry.file.replaceAll( '\\', '/' ) } ${ String( entry.value ).toLowerCase() }`
		);
	}
	return allowed;
}

/**
 * @param {string} raw
 */
function svgText( raw ) {
	const base64 = raw.match( /base64,([A-Za-z0-9+/=]+)/ );
	if ( base64 ) {
		return Buffer.from( base64[ 1 ], 'base64' ).toString( 'utf8' );
	}
	const encoded = raw.replace( /^data:image\/svg\+xml;?(?:charset=[^,]*,)?/i, '' );
	try {
		return decodeURIComponent( encoded );
	} catch ( error ) {
		return encoded;
	}
}

/**
 * @param {string} svg
 * @param {boolean} mask
 */
function coloursIn( svg, mask ) {
	const found = new Set();
	for ( const color of svg.match( /#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b/g ) || [] ) {
		found.add( color.toLowerCase() );
	}
	for ( const fn of svg.match( /\b(?:rgba?|hsla?)\([^)]*\)/gi ) || [] ) {
		found.add( fn.toLowerCase().replace( /\s+/g, '' ) );
	}
	for ( const name of svg.match( new RegExp( namedSource, 'gi' ) ) || [] ) {
		found.add( name.toLowerCase() );
	}
	if ( mask ) {
		for ( const silhouette of [ '#fff', '#ffffff', '#000', '#000000', 'white', 'black' ] ) {
			found.delete( silhouette );
		}
	}
	return found;
}

/** @type {import('stylelint').Rule} */
const ruleFunction = ( primary ) => {
	return ( root, result ) => {
		const validOptions = validateOptions( result, ruleName, {
			actual: primary,
			possible: [ true ],
		} );
		if ( ! validOptions ) {
			return;
		}

		const file = root.source?.input?.file || '';
		if ( file.replaceAll( '\\', '/' ).endsWith( 'lesson/_video-player.scss' ) ) {
			return;
		}

		const allowed = allowlist();
		const rel = relativeFile( file );
		root.walkDecls( ( decl ) => {
			if ( ! /data:image\/svg\+xml/i.test( decl.value ) ) {
				return;
			}
			const mask =
				/mask-image$/i.test( decl.prop ) || decl.prop === '--icon-mask';
			const urls = [];
			let from = 0;
			const lower = decl.value.toLowerCase();
			while ( from < decl.value.length ) {
				const at = lower.indexOf( 'url(', from );
				if ( at === -1 ) {
					break;
				}
				let cursor = at + 4;
				while ( decl.value[ cursor ] === ' ' ) {
					cursor++;
				}
				const quote =
					decl.value[ cursor ] === '"' || decl.value[ cursor ] === "'"
						? decl.value[ cursor ]
						: '';
				if ( quote ) {
					cursor++;
				}
				const end = quote
					? decl.value.indexOf( quote, cursor )
					: decl.value.indexOf( ')', cursor );
				if ( end === -1 ) {
					break;
				}
				const raw = decl.value.slice( cursor, end );
				if ( raw.toLowerCase().includes( 'data:image/svg+xml' ) ) {
					urls.push( raw );
				}
				from = end + 1;
			}
			for ( const raw of urls ) {
				for ( const color of coloursIn( svgText( raw ), mask ) ) {
					if ( allowed.has( `${ rel } ${ color }` ) ) {
						continue;
					}
					report( {
						message: messages.rejected( color ),
						messageArgs: [ color ],
						node: decl,
						result,
						ruleName,
						word: decl.prop,
					} );
				}
			}
		} );
	};
};

ruleFunction.ruleName = ruleName;
ruleFunction.messages = messages;
ruleFunction.meta = meta;

export default createPlugin( ruleName, ruleFunction );
