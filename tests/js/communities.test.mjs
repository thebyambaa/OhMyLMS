import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { transformSync } from '@babel/core';
import { validateCommunity } from '../../assets/src/features/communities/model.mjs';

const generate = generatorModule.default || generatorModule;
const source = 'assets/src/';
const manifest = JSON.parse( fs.readFileSync( source + 'manifest.json' ) );
const factory = manifest.assets
	.find( ( a ) => a.output === 'assets/dist/admin/ohmylms.js' )
	.factories.find( ( f ) => f.id === '1841' );
const ast = parse(
	factory.fragments
		.map( ( file ) => fs.readFileSync( source + file, 'utf8' ) )
		.join( '\n' )
);
const declarations = new Map();
for ( const statement of ast.program.body ) {
	if ( statement.type === 'VariableDeclaration' )
		for ( const declaration of statement.declarations )
			declarations.set( declaration.id.name, declaration.init );
	if ( statement.type === 'FunctionDeclaration' )
		declarations.set( statement.id.name, statement );
}
const rows = JSON.parse(
	fs.readFileSync( 'assets/src/features/communities/components.json' )
);
const compiled = new Map(
	rows.map( ( row ) => {
		const input = fs
			.readFileSync(
				'assets/src/features/communities/' + row.file,
				'utf8'
			)
			.replace( /^import .*;$/gm, '' )
			.replace( 'export function', 'function' );
		return [
			row.name,
			transformSync( input, {
				configFile: false,
				babelrc: false,
				presets: [
					[
						'@babel/preset-react',
						{ runtime: 'classic', pragma: 'React.createElement' },
					],
				],
			} ).code,
		];
	} )
);
const plain = ( value ) =>
	JSON.parse(
		JSON.stringify( value, ( key, item ) =>
			typeof item === 'function' ? '[callback]' : item
		)
	);
const find = ( tree, type ) => {
	if ( ! tree || typeof tree !== 'object' ) return undefined;
	if ( tree.type === type ) return tree;
	for ( const child of Array.isArray( tree ) ? tree : tree.children || [] ) {
		const result = find( child, type );
		if ( result ) return result;
	}
};

function harness( states = [] ) {
	let cursor = 0;
	const updates = new Map(),
		effects = [],
		requests = [];
	const React = {
		Fragment: 'Fragment',
		createElement: ( type, props, ...children ) => ( {
			type,
			props: props || {},
			children,
		} ),
	};
	const controls = new Proxy( {}, { get: ( _, name ) => String( name ) } );
	const globals = {
		React,
		h: () => React,
		console: { error() {} },
		validateCommunity,
		showRequestError() {},
		window: {},
		I: controls,
		Controls: controls,
		b: { __: ( text ) => text },
		HG() {},
		Ge: ( text ) => text,
		g: {
			memo: ( fn ) => fn,
			useState( initial ) {
				const index = cursor++;
				return [
					index in states ? states[ index ] : initial,
					( value ) => updates.set( index, value ),
				];
			},
			useRef: ( value ) => ( { current: value } ),
			useEffect: ( fn ) => effects.push( fn ),
			useMemo: ( fn ) => fn(),
			useCallback: ( fn ) => fn,
		},
		l: () => async ( request ) => {
			requests.push( request );
			return request.method === 'POST'
				? { status: 'success' }
				: globals.response;
		},
		sN: { A: 'Table' },
		Table: 'Table',
		Mt: { A: 'InfoIcon' },
		InfoIcon: 'InfoIcon',
		V: { A: 'Tooltip' },
		Tooltip: 'Tooltip',
	};
	Object.assign( globals, {
		T: { default: 'store' },
		M: () => ( { noConflict() {} } ),
		L: { isProActive: true },
		He: { default: 'ProDialog' },
		_: { A: 'Skeleton' },
		Skeleton: 'Skeleton',
		q: { Icon: 'Icon' },
		Ne: { A: 'MenuIcon' },
		community: {
			id: 12,
			title: 'Study Group',
			slug: 'study-group',
			parent_id: 31,
		},
		changes: [],
		saved: [],
		saveResult: { status: 'success' },
	} );
	globals.y = {
		useSelect: ( fn ) =>
			fn( () => ( {
				selectCommunity: () => globals.community,
				getCommunities: () => [ globals.community ],
			} ) ),
		useDispatch: () => ( {
			setCommunity: ( value ) => globals.changes.push( value ),
			fetchCommunities: async ( value ) => requests.push( value ),
			createCommunity: async ( value ) => {
				globals.saved.push( value );
				if ( globals.saveError ) throw Error( 'Failed' );
				return globals.saveResult;
			},
		} ),
	};
	globals.window.open = ( url ) => requests.push( url );
	for ( const name of [
		'YG',
		'G9',
		'N9',
		'h9',
		'k9',
		'Pf',
		'L2',
		'Ea',
		'SB',
		'Br',
		'Rt',
	] )
		globals[ name ] = name;
	for ( const name of [ 'uf', 'df' ] ) globals[ name ] = name;
	const context = vm.createContext( globals );
	for ( const [ name, declaration ] of declarations ) {
		if ( name in globals ) continue;
		Object.defineProperty( globals, name, {
			configurable: true,
			get() {
				const expression =
					declaration.type === 'FunctionDeclaration'
						? {
								...declaration,
								type: 'FunctionExpression',
								id: null,
							}
						: declaration;
				const value = vm.runInContext(
					'(' +
						generate( expression, { comments: false } ).code +
						'\n)',
					context
				);
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
				return value;
			},
			set( value ) {
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
			},
		} );
	}
	return {
		globals,
		updates,
		effects,
		requests,
		render( name, props = {}, original = false ) {
			cursor = 0;
			const row = rows.find( ( row ) => row.name === name );
			if ( original ) return globals[ row.binding ]( props );
			const create = vm.runInContext(
				compiled.get( name ) + '\ncreate' + name,
				context
			);
			return create( () => globals )( props );
		},
	};
}

const all = ( tree, type ) =>
	! tree || typeof tree !== 'object'
		? []
		: [
				...( tree.type === type ? [ tree ] : [] ),
				...( Array.isArray( tree )
					? tree
					: tree.children || []
				).flatMap( ( child ) => all( child, type ) ),
			];
const flush = () => new Promise( ( resolve ) => setImmediate( resolve ) );
test( 'community conversion preserves all five component render trees', () => {
	for ( const row of rows ) {
		const h = harness();
		const props = {
			isOpen: true,
			setIsOpen() {},
			errors: {},
			validate() {},
		};
		assert.deepEqual(
			plain( h.render( row.name, props ) ),
			plain( h.render( row.name, props, true ) ),
			row.name
		);
	}
} );
test( 'community list preserves analytics, detail requests and view actions', async () => {
	const h = harness();
	const tree = h.render( 'CommunityList' );
	h.globals.response = {
		members: { total: 0 },
		posts: { total: 14 },
		engagement: { total: 2 },
	};
	h.effects[ 0 ]();
	await flush();
	assert.deepEqual(
		plain( h.updates.get( 0 ).map( ( card ) => card.value ) ),
		[ 0, 14, 2 ]
	);
	const table = find( tree, 'Table' );
	assert.equal( table.props.rowKey, 'id' );
	const row = { id: 12, url: 'https://example.test/community/group' };
	const menu = table.props.columns.at( -1 ).render( null, row );
	menu.props.controls[ 0 ].onClick();
	assert.equal( h.requests.at( -1 ), row.url );
	h.globals.response = h.globals.community;
	menu.props.controls[ 1 ].onClick();
	await flush();
	assert.equal( h.requests.at( -1 ).path, '/ohmylms/v1/community/spaces/12' );
	assert.deepEqual( h.globals.changes.at( -1 ), h.globals.community );
} );
test( 'community details preserve custom slugs and image attachment payloads', () => {
	const h = harness();
	let tree = h.render( 'CommunityDetails', { errors: {}, validate() {} } );
	all( tree, 'Pf' )[ 0 ].props.onChange( 'New Study Group' );
	assert.equal( h.globals.changes.at( -1 ).slug, 'new-study-group' );
	assert.equal( h.globals.community.title, 'Study Group' );
	h.globals.community.slug = 'custom-slug';
	tree = h.render( 'CommunityDetails', { errors: {}, validate() {} } );
	all( tree, 'Pf' )[ 0 ].props.onChange( 'Another Group' );
	assert.equal( h.globals.changes.at( -1 ).slug, 'custom-slug' );
	all( tree, 'L2' )[ 0 ].props.handleChange( '/image.png', 42 );
	assert.equal( h.globals.changes.at( -1 ).space_photo, 42 );
	all( tree, 'L2' )[ 1 ].props.handleRemove();
	assert.equal( h.globals.changes.at( -1 ).cover_image, null );
} );
test( 'community course assignment preserves filtering and selection contracts', async () => {
	const h = harness();
	h.globals.response = [
		{ id: 1, name: 'Available' },
		{ id: 2, name: 'Assigned', has_community: 'yes' },
	];
	const tree = h.render( 'CommunityCourses' );
	h.effects[ 0 ]();
	await flush();
	assert.equal(
		h.requests[ 0 ].path,
		'/ohmylms/v1/courses?search=&post_status=publish'
	);
	assert.deepEqual( plain( h.updates.get( 0 ) ), [
		{ label: 'Available', value: 1 },
	] );
	const select = find( tree, 'AdvancedSelectWP' );
	select.props.onChange( { label: 'Available', value: 1 } );
	assert.equal( h.globals.changes.at( -1 ).parent_id, 1 );
	select.props.onChange( null );
	assert.equal( h.globals.changes.at( -1 ).parent_id, null );
} );
test( 'community editor validates and retries failed saves without duplicates', async () => {
	assert.ok( validateCommunity( { title: ' ', slug: 'A B' } ).title );
	assert.ok( validateCommunity( { title: 'Group', slug: 'ab' } ).slug );
	assert.deepEqual(
		validateCommunity( { title: 'Group', slug: 'valid-slug' } ),
		{}
	);
	const h = harness( [ false, '2', {}, false ] );
	const closed = [];
	let refreshed = 0;
	const tree = h.render( 'CommunityEditor', {
		isOpen: true,
		setIsOpen: ( value ) => closed.push( value ),
		onSuccess: () => refreshed++,
	} );
	const save = all( tree, 'ButtonWP' ).at( -1 ).props.onClick;
	h.globals.saveResult = { status: 'error' };
	await save();
	assert.equal( closed.length, 0 );
	assert.equal( h.updates.get( 0 ), false );
	h.globals.saveError = true;
	await save();
	assert.equal( closed.length, 0 );
	assert.equal( h.updates.get( 0 ), false );
	h.globals.saveError = false;
	h.globals.saveResult = { status: 'success' };
	await Promise.all( [ save(), save() ] );
	assert.equal( h.globals.saved.length, 3 );
	assert.deepEqual( closed, [ false ] );
	assert.equal( refreshed, 1 );
	h.globals.community.slug = 'bad slug';
	await save();
	assert.equal( h.globals.saved.length, 3 );
	const next = harness();
	await all(
		next.render( 'CommunityEditor', { isOpen: true, setIsOpen() {} } ),
		'ButtonWP'
	)
		.at( -1 )
		.props.onClick();
	assert.equal( next.updates.get( 1 ), '2' );
	assert.equal( next.globals.saved.length, 0 );
} );
