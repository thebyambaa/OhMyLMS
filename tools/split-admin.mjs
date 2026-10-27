/** Split concatenated application source without changing its shared lexical scope. */
import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';
import generatorModule from '@babel/generator';
const traverse = traverseModule.default || traverseModule,
	generate = generatorModule.default || generatorModule;
const root = path.resolve( import.meta.dirname, '../assets/src' );
const manifest = JSON.parse(
	fs.readFileSync( path.join( root, 'manifest.json' ), 'utf8' )
);
const asset = manifest.assets.find(
	( a ) => a.output === 'assets/dist/admin/ohmylms.js'
);
const factory = asset.factories.find( ( f ) => f.id === '1841' );
if ( factory.fragments )
	throw new Error(
		'Application is already split. Edit its feature files directly.'
	);
const ast = parse(
	fs.readFileSync( path.join( root, factory.source ), 'utf8' )
);
const fn = ast.program.body[ 0 ].expression;
const names = new Map( [
	[ 'Z8', 'memberships/MembershipList' ],
	[ 'L8', 'memberships/MembershipEditor' ],
] );
const routes = [];
traverse( ast, {
	ObjectExpression( p ) {
		const route = p.node.properties.find(
			( x ) => x.key?.name === 'path' && x.value?.type === 'StringLiteral'
		);
		const element = p.node.properties.find(
			( x ) => x.key?.name === 'element'
		);
		if ( ! route || ! element || element.value.type !== 'Identifier' )
			return;
		const label =
			route.value.value === '/'
				? 'Dashboard'
				: route.value.value
						.split( '/' )
						.filter( ( x ) => x && ! x.startsWith( ':' ) )
						.map( ( x ) =>
							x
								.split( '-' )
								.map(
									( y ) => y[ 0 ].toUpperCase() + y.slice( 1 )
								)
								.join( '' )
						)
						.join( '' );
		if ( label ) {
			names.set( element.value.name, `screens/${ label }` );
			routes.push( {
				route: route.value.value,
				binding: element.value.name,
			} );
		}
	},
} );
// Follow memo wrappers so the component implementation receives a meaningful file name.
const declarations = new Map();
for ( const statement of fn.body.body )
	if ( statement.type === 'VariableDeclaration' )
		for ( const declaration of statement.declarations )
			if ( declaration.id.type === 'Identifier' )
				declarations.set( declaration.id.name, declaration.init );
for ( const [ id, label ] of [ ...names ] ) {
	let current = id;
	for ( let i = 0; i < 4; i++ ) {
		const init = declarations.get( current );
		const next =
			init?.type === 'Identifier'
				? init.name
				: init?.type === 'CallExpression' &&
					  init.arguments[ 0 ]?.type === 'Identifier'
					? init.arguments[ 0 ].name
					: null;
		if ( ! next || ! declarations.has( next ) || names.has( next ) ) break;
		names.set( next, label + 'Component' );
		current = next;
	}
}
const groups = [];
let buffer = [],
	bytes = 0;
const flush = ( label ) => {
	if ( buffer.length ) {
		groups.push( {
			label:
				label ||
				`shared/section-${ String( groups.length ).padStart( 4, '0' ) }`,
			nodes: buffer,
		} );
		buffer = [];
		bytes = 0;
	}
};
for ( const statement of fn.body.body ) {
	const ids =
		statement.type === 'VariableDeclaration'
			? statement.declarations.map( ( d ) => d.id.name )
			: statement.type === 'FunctionDeclaration'
				? [ statement.id.name ]
				: [];
	const label = ids.map( ( id ) => names.get( id ) ).find( Boolean );
	const size = generate( statement ).code.length;
	if ( label ) {
		flush();
		buffer = [ statement ];
		flush( label );
		continue;
	}
	if ( bytes + size > 32000 ) flush();
	buffer.push( statement );
	bytes += size;
}
flush();
factory.fragments = [];
for ( const [ index, group ] of groups.entries() ) {
	const source = `application/${ group.label }-${ String( index ).padStart( 4, '0' ) }.js`;
	const dest = path.join( root, source );
	fs.mkdirSync( path.dirname( dest ), { recursive: true } );
	fs.writeFileSync(
		dest,
		'// Reconstructed application fragment. Assembled in manifest order within factory 1841.\n' +
			group.nodes
				.map( ( n ) => generate( n, { comments: true } ).code )
				.join( '\n\n' ) +
			'\n'
	);
	factory.fragments.push( source );
}
fn.body.body = [];
fs.writeFileSync(
	path.join( root, factory.source ),
	generate( ast ).code + '\n'
);
manifest.application = {
	factory: factory.source,
	fragmentCount: groups.length,
	routes,
};
fs.writeFileSync(
	path.join( root, 'manifest.json' ),
	JSON.stringify( manifest, null, 2 ) + '\n'
);
console.log(
	`Split concatenated application into ${ groups.length } editable fragments, including ${ names.size } named screen/component bindings.`
);
