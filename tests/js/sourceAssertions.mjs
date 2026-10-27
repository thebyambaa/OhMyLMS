import { transformSync } from '@babel/core';

/** Normalize code printing so contract assertions do not depend on formatter whitespace. */
export function codeForAssertions( source ) {
	return transformSync( source, {
		configFile: false,
		babelrc: false,
		parserOpts: { plugins: [ 'jsx' ] },
		generatorOpts: { concise: true, comments: false },
		plugins: [
			() => ( {
				visitor: {
					IfStatement( path ) {
						for ( const key of [ 'consequent', 'alternate' ] ) {
							const branch = path.node[ key ];
							if (
								branch?.type === 'BlockStatement' &&
								branch.body.length === 1 &&
								branch.body[ 0 ].type === 'ReturnStatement'
							) {
								path.node[ key ] = branch.body[ 0 ];
							}
						}
					},
				},
			} ),
		],
	} ).code;
}
