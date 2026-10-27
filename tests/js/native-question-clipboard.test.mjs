import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const source = fs
	.readFileSync( 'assets/src/features/math/RichContentControl.jsx', 'utf8' )
	.replace( /^import [\s\S]*?;$/gm, '' )
	.replace( 'export function', 'function' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );
const scope = {
	createElement: ( type, props, ...children ) => ( {
		type,
		props,
		children,
	} ),
	useRef: () => ( { current: null } ),
	useEffect: () => {},
	useLayoutEffect: () => {},
	useCallback: ( callback ) => callback,
	__: ( text ) => text,
	EquationAction: 'equation',
	mathEnabled: () => false,
};
const Control = new Function(
	...Object.keys( scope ),
	code + '; return RichContentControl;'
)( ...Object.values( scope ) );

test( 'question clipboard edits stay in the field instead of replacing the Gutenberg block', () => {
	const tree = Control( { value: '', onChange: () => {} } );
	const field = tree.children.find(
		( child ) => child?.props?.contentEditable
	);
	for ( const name of [ 'onPaste', 'onCopy', 'onCut' ] ) {
		let stopped = false;
		field.props[ name ]( {
			stopPropagation: () => {
				stopped = true;
			},
			preventDefault: () =>
				assert.fail( 'HTML clipboard editing remains native' ),
		} );
		assert.equal( stopped, true );
	}
} );
