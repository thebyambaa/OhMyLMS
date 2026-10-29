const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { parse } = require( '@babel/parser' );
const t = require( '@babel/types' );
const generate = require( '@babel/generator' ).default;
const root = path.resolve( __dirname, '../../..' );

// Evaluate the authored ES modules against WordPress's real React runtime in the browser.
// Only module syntax is adapted; no component or class-name implementation is duplicated.
function modules(
	names = [ 'controls.mjs', 'Field.jsx', 'Input.jsx', 'Select.jsx' ]
) {
	return Object.fromEntries(
		names.map( ( name ) => {
			const ast = parse(
				fs.readFileSync(
					path.join( root, 'assets/src/ui', name ),
					'utf8'
				),
				{ sourceType: 'module' }
			);
			const body = [];
			for ( const node of ast.program.body ) {
				if ( node.type === 'ImportDeclaration' ) {
					body.push(
						t.variableDeclaration( 'const', [
							t.variableDeclarator(
								t.objectPattern(
									node.specifiers.map( ( specifier ) =>
										t.objectProperty(
											specifier.imported,
											specifier.local,
											false,
											specifier.imported.name ===
												specifier.local.name
										)
									)
								),
								t.callExpression( t.identifier( 'require' ), [
									node.source,
								] )
							),
						] )
					);
				} else if ( node.type === 'ExportNamedDeclaration' ) {
					body.push( node.declaration );
					const names =
						node.declaration.type === 'VariableDeclaration'
							? node.declaration.declarations.map(
									( declaration ) => declaration.id.name
								)
							: [ node.declaration.id.name ];
					for ( const exported of names )
						body.push(
							t.expressionStatement(
								t.assignmentExpression(
									'=',
									t.memberExpression(
										t.identifier( 'exports' ),
										t.identifier( exported )
									),
									t.identifier( exported )
								)
							)
						);
				} else body.push( node );
			}
			return [
				name.replace( /\.(jsx|mjs)$/, '' ),
				generate( t.program( body ) ).code,
			];
		} )
	);
}

async function mountControls( page ) {
	await page.addScriptTag( {
		path: path.resolve(
			root,
			'../../../wp-includes/js/dist/vendor/react.js'
		),
	} );
	await page.addScriptTag( {
		path: path.resolve(
			root,
			'../../../wp-includes/js/dist/vendor/react-dom.js'
		),
	} );
	await page.evaluate( ( sources ) => {
		const cache = {};
		function require( name ) {
			if ( name === '@wordpress/element' ) return window.React;
			const key = name
				.replace( /^\.\//, '' )
				.replace( /\.(mjs|jsx)$/, '' );
			if ( ! cache[ key ] ) {
				cache[ key ] = {};
				new Function( 'require', 'exports', sources[ key ] )(
					require,
					cache[ key ]
				);
			}
			return cache[ key ];
		}
		const { Input } = require( 'Input' ),
			{ Select } = require( 'Select' );
		const { createElement: el, useState } = window.React;
		window.controlRef = window.React.createRef();
		function Demo() {
			const [ email, setEmail ] = useState( '' );
			const [ course, setCourse ] = useState( '' );
			const options = [
				{ value: 'math', label: 'Mathematics' },
				{ value: 'science', label: 'Science' },
				{ value: 'closed', label: 'Closed', disabled: true },
			];
			return el(
				'form',
				{
					id: 'demo',
					onSubmit: ( event ) => {
						event.preventDefault();
						window.controlSubmission = Array.from(
							new FormData( event.target )
						);
					},
				},
				el( 'p', { id: 'extra-description' }, 'Additional guidance.' ),
				el( Input, {
					label: 'Email',
					name: 'email',
					type: 'email',
					ref: window.controlRef,
					required: true,
					value: email,
					onChange: ( event ) => setEmail( event.target.value ),
					help: 'School email address.',
					error:
						email && ! email.includes( '@' )
							? 'Enter a valid email.'
							: '',
					'aria-describedby': 'extra-description',
				} ),
				el( Select, {
					label: 'Course',
					name: 'course',
					required: true,
					placeholder: 'Choose a course',
					options,
					value: course,
					onChange: ( event ) => setCourse( event.target.value ),
				} ),
				el( Select, {
					label: 'Subjects',
					name: 'subjects[]',
					multiple: true,
					options,
					defaultValue: [ 'math' ],
				} ),
				el( Input, {
					label: 'Reference',
					name: 'reference',
					readOnly: true,
					defaultValue: '1024',
				} ),
				el( Input, {
					label: 'Unavailable',
					name: 'disabled',
					disabled: true,
					defaultValue: 'hidden',
				} ),
				el( Input, {
					type: 'date',
					label: 'Due date',
					name: 'due',
					defaultValue: '2026-11-01',
				} ),
				el( 'button', { type: 'submit' }, 'Submit' )
			);
		}
		window.ReactDOM.createRoot(
			document.getElementById( 'react-root' )
		).render( el( Demo ) );
	}, modules() );
	await page.getByRole( 'textbox', { name: 'Email', exact: true } ).waitFor();
}
module.exports = { mountControls, modules };
