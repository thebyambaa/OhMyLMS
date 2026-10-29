import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@babel/parser';
import traverseModule from '@babel/traverse';

const traverse = traverseModule.default || traverseModule;

/** Inventory of everything authored features still receive from the recovered runtime.
 * Reads factory 1841 (assembled from its fragments), every feature components.json and
 * the module manifest. Each binding gets a kind, an owner and a roadmap step; nothing is
 * rewritten. See docs/RUNTIME-DEPENDENCY-INVENTORY.md (tools/runtime-inventory-report.mjs) for the report.
 */

export const roadmapSteps = {
	2: 'Migrate Easy Email and remaining libraries',
	3: 'Extract shared application infrastructure',
	4: 'Separate state and API access',
	5: 'Finish feature independence',
	6: 'Replace the recovered application entry point',
};

// Fragment-name prefixes for bindings that no authored feature consumes yet.
const fragmentOwners = [
	[ /^automation-|^integration|^integrations-/, 'integrations' ],
	[ /^email-/, 'emails' ],
	[ /^webhook/, 'webhooks' ],
	[ /^certificate/, 'certificates' ],
	[ /^gamification/, 'gamification' ],
	[ /^membership/, 'memberships' ],
	[
		/^(order|subscription|coupon|payment|checkout|currency|monetization)/,
		'commerce',
	],
	[ /^(setup-wizard|import-options|migration|setup-complete)/, 'setup' ],
	[ /^(students|enroll-student)/, 'students' ],
	[ /^community/, 'communities' ],
	[ /^(dashboard|earnings|course-report)/, 'analytics' ],
	[
		/^(quiz-report|quiz-grading|assignment-report|assignment-grading)/,
		'quiz-reports',
	],
	[ /^(quiz-|quiz_)/, 'quiz-editor' ],
	[ /^(category|taxonomy)/, 'taxonomies' ],
	[
		/^(general-settings|design-settings|advanced-settings|course-archive|course-card-preview|course-details-layout|course-per-row|video-player)/,
		'settings',
	],
	[ /^(lesson|assignment|live-session|course-builder)/, 'learning' ],
	[ /^course-/, 'courses' ],
	[ /^schedule-date-picker/, 'shared-infrastructure' ],
	[ /^routes$/, 'bootstrap' ],
];

// Signatures for recovered third-party code. First match wins; checked on the root
// factory and then on its private closure.
const librarySignatures = [
	[
		'@wordpress/icons (bundled copy)',
		/viewBox: "0 0 24 24",\s*children: \(0, \w\.jsxs?\)\(\w\.wA/,
	],
	[ 'classnames', /hasOwnProperty;\s*function \w\(\) \{\s*for \(var e = ""/ ],
	[
		'React JSX runtime (bundled copy)',
		/react\.transitional\.element|react\.element"\)[\s\S]*jsxs/,
	],
	[ 'Easy Email', /easy-email|EmailEditorProvider|BlockManager/ ],
	[ 'html2canvas', /html2canvas/ ],
	[ 'Arco Design React', /arco-|ConfigProvider/ ],
	[ 'MJML', /mjml/ ],
	[ 'Tiptap / ProseMirror', /ProseMirror|tiptap/i ],
	[ 'CodeMirror', /CodeMirror|codemirror|@codemirror|lezer/ ],
	[ 'Lowlight / Highlight.js', /hljs|highlightAuto|lowlight/ ],
	[ 'Lucide icons', /lucide/ ],
	[ 'Chart.js', /chart\.js|Chart\.register|chartjs/i ],
	[ 'Emotion', /emotion|stylis/i ],
	[ 'React Flow', /react-flow|xyflow|ReactFlow/ ],
	[ 'dnd-kit', /dnd-kit|useSortable|DndContext/ ],
	[ 'React Router', /useNavigate|createBrowserRouter|HashRouter|useParams/ ],
	[ 'react-remove-scroll', /RemoveScroll/ ],
	[ 'Floating UI / Popper', /floating-ui|computePosition|popper/i ],
	[
		'Day.js',
		/dayjs|\$isDayjsObject|return function \(\w, \w, \w\) \{\s*var \w = \w\.prototype/,
	],
	[ 'Moment', /moment\b|isMoment/ ],
	[ 'date-fns', /date-fns|differenceInCalendar/ ],
	[ 'React Datepicker', /react-datepicker/ ],
	[ 'Lodash', /__lodash_|lodash/ ],
	[ 'DOMPurify', /DOMPurify|dompurify/ ],
	[ 'Axios', /axios/i ],
	[ 'classnames', /classNames|classnames/ ],
	[ 'React Toastify / toasts', /toastify|react-hot-toast|Toaster/ ],
	[
		'Babel runtime helpers',
		/__esModule|_typeof|asyncToGenerator|regeneratorRuntime/,
	],
];

const externalPatterns = [
	[
		/e\.exports\s*=\s*(?:window\.)?wp\.([A-Za-z0-9]+)/,
		( m ) => `@wordpress/${ kebab( m[ 1 ] ) }`,
	],
	[ /e\.exports\s*=\s*(?:window\.)?React\b/, () => 'react (wp.element)' ],
	[
		/e\.exports\s*=\s*(?:window\.)?ReactDOM\b/,
		() => 'react-dom (wp.element)',
	],
	[ /e\.exports\s*=\s*(?:window\.)?jQuery\b/, () => 'jquery' ],
	[ /e\.exports\s*=\s*(?:window\.)?lodash\b/, () => 'lodash (WordPress)' ],
	[
		/e\.exports\s*=\s*(?:window\.)?([A-Za-z_$][\w$]*)\s*;?\s*\}?\)?\s*$/,
		( m ) => `window.${ m[ 1 ] }`,
	],
];

function kebab( name ) {
	return name.replace( /[A-Z]/g, ( c ) => '-' + c.toLowerCase() );
}

function moduleRefs( node ) {
	const refs = new Set();
	const visit = ( value ) => {
		if ( ! value || typeof value !== 'object' ) return;
		if ( Array.isArray( value ) ) return value.forEach( visit );
		if (
			value.type === 'CallExpression' &&
			value.callee.type === 'Identifier' &&
			value.callee.name === 'n' &&
			value.arguments.length === 1 &&
			value.arguments[ 0 ].type === 'NumericLiteral'
		)
			refs.add( String( value.arguments[ 0 ].value ) );
		for ( const key of Object.keys( value ) ) {
			if ( key !== 'loc' && key !== 'start' && key !== 'end' )
				visit( value[ key ] );
		}
	};
	visit( node );
	return [ ...refs ];
}

function aliasOf( init ) {
	// X = n.n(Y) or X = Y: the binding is an interop view of another binding.
	if (
		init?.type === 'CallExpression' &&
		init.callee.type === 'MemberExpression' &&
		init.callee.object.name === 'n' &&
		init.callee.property.name === 'n' &&
		init.arguments[ 0 ]?.type === 'Identifier'
	)
		return init.arguments[ 0 ].name;
	// X = (0, g.memo)(Y) / forwardRef(Y): the wrapper is the same component.
	if (
		init?.type === 'CallExpression' &&
		init.arguments.length === 1 &&
		init.arguments[ 0 ].type === 'Identifier' &&
		/^(?:memo|forwardRef)$/.test(
			init.callee.expressions?.[ 1 ]?.property?.name ||
				init.callee.property?.name ||
				''
		)
	)
		return init.arguments[ 0 ].name;
	return init?.type === 'Identifier' ? init.name : null;
}

function definitionKind( node ) {
	if ( ! node ) return 'declaration';
	if (
		[
			'FunctionExpression',
			'ArrowFunctionExpression',
			'FunctionDeclaration',
		].includes( node.type )
	)
		return 'function';
	if ( [ 'ClassExpression', 'ClassDeclaration' ].includes( node.type ) )
		return 'class';
	if ( node.type === 'ObjectExpression' ) return 'object';
	if ( node.type === 'ArrayExpression' ) return 'array';
	if (
		[
			'StringLiteral',
			'NumericLiteral',
			'BooleanLiteral',
			'TemplateLiteral',
		].includes( node.type )
	)
		return 'constant';
	if ( node.type === 'CallExpression' ) return 'computed';
	return 'value';
}

// Babel helpers were inlined once per original module; authored JSX does not need them.
const babelHelpers = [
	[
		'regeneratorRuntime',
		/@@iterator[\s\S]*@@toStringTag|regeneratorRuntime/,
	],
	[
		'asyncToGenerator',
		/new Promise\(function \(\w, \w\) \{\s*var \w = \w\.apply\(/,
	],
	[ 'toPropertyKey/defineProperty', /Symbol\.toPrimitive/ ],
	[
		'arrayLikeToArray',
		/\(null == t \|\| t > e\.length\) && \(t = e\.length\)/,
	],
	[ 'slicedToArray', /if \(Array\.isArray\(e\)\) return e;/ ],
	[
		'toConsumableArray',
		/if \(Array\.isArray\(e\)\) return \w+\(e\)|Array\.from\(e\)/,
	],
	[ 'extends', /Object\.assign \? Object\.assign\.bind\(\)/ ],
	[ 'objectSpread', /Object\.getOwnPropertySymbols/ ],
	[
		'objectWithoutProperties',
		/indexOf\(\w\) >= 0|-1 === \w\.indexOf\(\w\)|\.includes\(\w\)\) continue/,
	],
	[ 'typeof', /"symbol" == typeof Symbol\.iterator/ ],
	[
		'createForOfIteratorHelper',
		/e\[Symbol\.iterator\] \|\| e\["@@iterator"\]/,
	],
	[
		'asyncGeneratorStep',
		/try \{ var \w = \w\[\w\]\(\w\), \w = \w\.value; \}/,
	],
	[ 'objectSpread2', /for \(var t = 1; t < arguments\.length; t\+\+\)/ ],
	[ 'objectWithoutProperties keys', /^\w+ = \[(?:"[^"]*",? ?)+\]$/ ],
	[ 'unsupportedIterableToArray', /"Object" === \w && \w\.constructor/ ],
];

/** Purpose hint for a recovered local, from its body. Used to pick step 3/4/5. */
function purpose( source ) {
	const code = source.replace( /\s+/g, ' ' );
	if ( code.length < 3200 ) {
		const helper = babelHelpers.find( ( [ , pattern ] ) =>
			pattern.test( code )
		);
		if ( helper ) return `babel:${ helper[ 0 ] }`;
	}
	if (
		/\b(?:apiFetch|fetch)\)?\(|\/wp-json\/|ohmylms\/v\d|\bpath:\s*["'`]\//.test(
			code
		)
	)
		return 'api';
	if (
		/createReduxStore|registerStore|combineReducers|createContext\(/.test(
			code
		)
	)
		return 'state';
	if ( /ohmylms_params|window\.ohmylms/.test( code ) && code.length < 600 )
		return 'config';
	if ( /createElement|React\.Fragment|\.lazy\)\(/.test( code ) )
		return 'component';
	if ( /^\w+ = \(0, \w+\.(?:memo|forwardRef)\)\(/.test( code ) )
		return 'component';
	if ( /^\w+ = [[{]/.test( code ) && ! /function|=>/.test( code ) )
		return 'data';
	return 'helper';
}

const knownModules = {
	38093: 'Shared controls kit (`*WP` wrappers)',
	86169: 'Application data store and REST client',
};

/** Short human label for an LMS-owned recovered module; heuristic. */
function moduleHint( id, source ) {
	if ( knownModules[ id ] ) return knownModules[ id ];
	const code = source.replace( /\s+/g, ' ' );
	if (
		/^[^{]*\{ "use strict"; n\.r\(t\); \}\);?\s*$/.test(
			code.replace( /^\/\/[^(]*/, '' )
		)
	)
		return 'Stylesheet side-effect module';
	const rest = code.match( /path: "(\/ohmylms\/v\d\/[^"]*)"/ );
	if ( rest ) return `REST client (${ rest[ 1 ] })`;
	const className = code.match( /className: "(ohmylms-[a-z0-9-]+)"/ );
	if ( className ) return `Component (.${ className[ 1 ] })`;
	const control = code.match(
		/\.((?:Text|Textarea|Radio|Select|Toggle|Checkbox|Range)Control|Modal|Popover|Dropdown|Tooltip|Notice)\b/
	);
	if ( control )
		return `Wrapper around @wordpress/components ${ control[ 1 ] }`;
	if ( /"svg"|viewBox/.test( code ) ) return 'SVG icon';
	if ( /createContext/.test( code ) ) return 'React context';
	return 'Unlabelled module';
}

export function buildInventory( root ) {
	const sourceRoot = path.join( root, 'assets/src' );
	const manifest = JSON.parse(
		fs.readFileSync( path.join( sourceRoot, 'manifest.json' ), 'utf8' )
	);
	const sources = new Map();
	const read = ( name ) => {
		if ( ! sources.has( name ) )
			sources.set(
				name,
				fs.readFileSync( path.join( sourceRoot, name ), 'utf8' )
			);
		return sources.get( name );
	};

	// Assemble factory 1841 in manifest order and remember each fragment's line span.
	const application = manifest.assets
		.flatMap( ( asset ) => asset.factories || [] )
		.find( ( factory ) => factory.id === '1841' );
	const spans = [];
	let code = '(function (e, t, n) {\n';
	for ( const fragment of application.fragments ) {
		const start = code.split( '\n' ).length;
		code += read( fragment ) + '\n';
		spans.push( { fragment, start, end: code.split( '\n' ).length } );
	}
	code += '\n});';
	const fragmentAt = ( line ) =>
		spans.find( ( span ) => line >= span.start && line < span.end )
			?.fragment;

	const ast = parse( code, { sourceType: 'script', errorRecovery: true } );
	const bindings = new Map();
	traverse( ast, {
		Program( programPath ) {
			const body = programPath.get( 'body.0.expression.body' );
			const scope = body.scope;
			for ( const [ name, binding ] of Object.entries(
				scope.bindings
			) ) {
				if ( [ 'e', 't', 'n' ].includes( name ) ) continue;
				const node = binding.path.node;
				const init =
					node.type === 'VariableDeclarator' ? node.init : node;
				const start = binding.path.node.start;
				const end = binding.path.node.end;
				bindings.set( name, {
					name,
					fragment: fragmentAt( binding.path.node.loc.start.line ),
					definition: definitionKind( init ),
					alias: aliasOf( init ),
					modules: init ? moduleRefs( init ) : [],
					source: code.slice( start, end ),
				} );
			}
			programPath.stop();
		},
	} );

	// Resolve Webpack modules referenced from the application factory.
	const modulesById = new Map(
		manifest.modules.map( ( module ) => [ module.id, module ] )
	);
	const adapterSources = fs
		.readdirSync( path.join( root, 'tools' ) )
		.filter( ( file ) =>
			/adapters?\.mjs$|manifest\.mjs$|vendor-chunks\.mjs$/.test( file )
		)
		.map( ( file ) => [
			file,
			fs.readFileSync( path.join( root, 'tools', file ), 'utf8' ),
		] );
	const childRefs = ( module ) =>
		[ ...read( module.source ).matchAll( /\bn\((\d+)\)/g ) ].map(
			( match ) => match[ 1 ]
		);
	const closure = ( id ) => {
		const seen = new Set();
		const stack = [ id ];
		while ( stack.length ) {
			const next = stack.pop();
			if ( seen.has( next ) || ! modulesById.has( next ) ) continue;
			seen.add( next );
			stack.push( ...childRefs( modulesById.get( next ) ) );
		}
		return seen;
	};
	const moduleCache = new Map();
	const describeModule = ( id ) => {
		if ( moduleCache.has( id ) ) return moduleCache.get( id );
		const module = modulesById.get( id );
		let result;
		if ( ! module ) {
			result = { id, kind: 'unresolved' };
		} else {
			const source = read( module.source );
			const bridged = adapterSources
				.filter( ( [ , text ] ) =>
					new RegExp( `\\b${ id }\\b` ).test( text )
				)
				.map( ( [ file ] ) => file );
			const external =
				module.bytes < 400
					? externalPatterns
							.map( ( [ pattern, label ] ) => {
								const match = source.match( pattern );
								return match && label( match );
							} )
							.find( Boolean )
					: null;
			const exports = [
				...(
					source.match( /n\.d\(t,\s*\{([\s\S]*?)\}\)/ )?.[ 1 ] || ''
				).matchAll( /([A-Za-z_$][\w$]*):\s*\(\)/g ),
			].map( ( match ) => match[ 1 ] );
			if ( external ) {
				result = { id, kind: 'platform', package: external };
			} else if (
				module.kind === 'third-party' ||
				/chunks\/355/.test( module.bundle )
			) {
				const members = closure( id );
				const library =
					librarySignatures.find( ( [ , pattern ] ) =>
						pattern.test( source )
					)?.[ 0 ] ||
					librarySignatures.find( ( [ , pattern ] ) =>
						[ ...members ].some( ( member ) =>
							pattern.test(
								read( modulesById.get( member ).source )
							)
						)
					)?.[ 0 ] ||
					'Unidentified vendor';
				result = {
					id,
					kind: 'vendor',
					library,
					bundle: module.bundle,
					bytes: [ ...members ].reduce(
						( sum, member ) =>
							sum + modulesById.get( member ).bytes,
						0
					),
					closure: members.size,
					bridged,
				};
			} else {
				result = {
					id,
					kind: 'lms-module',
					hint: moduleHint( id, source ),
					bundle: module.bundle,
					bytes: module.bytes,
					exports,
					bridged,
				};
			}
		}
		moduleCache.set( id, result );
		return result;
	};

	// Authored feature components and who owns which binding.
	// Only contracts that the extension loader imports are wired into the build; other
	// folders with a components.json are copies and must not take over attribution.
	const featuresRoot = path.join( sourceRoot, 'features' );
	const loaderText = fs
		.readdirSync( path.join( sourceRoot, 'extensions' ) )
		.filter( ( file ) => /\.(?:js|jsx|mjs)$/.test( file ) )
		.map( ( file ) =>
			fs.readFileSync(
				path.join( sourceRoot, 'extensions', file ),
				'utf8'
			)
		)
		.join( '\n' );
	const components = [];
	const unwired = [];
	for ( const feature of fs.readdirSync( featuresRoot ).sort() ) {
		const contract = path.join( featuresRoot, feature, 'components.json' );
		if ( ! fs.existsSync( contract ) ) continue;
		if (
			! loaderText.includes( `features/${ feature }/components.json` )
		) {
			unwired.push( {
				feature,
				components: JSON.parse( fs.readFileSync( contract, 'utf8' ) )
					.length,
			} );
			continue;
		}
		for ( const component of JSON.parse(
			fs.readFileSync( contract, 'utf8' )
		) ) {
			const file = path.join( featuresRoot, feature, component.file );
			const text = fs.existsSync( file )
				? fs.readFileSync( file, 'utf8' )
				: '';
			const destructured = new Set(
				[
					...text.matchAll(
						/const\s*\{([^}]*)\}\s*=\s*readRuntime\(\)/g
					),
				]
					.flatMap( ( match ) => match[ 1 ].split( ',' ) )
					.map( ( entry ) => entry.split( ':' )[ 0 ].trim() )
					.filter( Boolean )
			);
			components.push( {
				feature,
				...component,
				readsRuntime: /readRuntime\(\)/.test( text ),
				undeclared: [ ...destructured ].filter(
					( name ) => ! component.dependencies.includes( name )
				),
			} );
		}
	}
	const authoredBy = new Map(
		components.map( ( component ) => [ component.binding, component ] )
	);
	const consumers = new Map();
	for ( const component of components ) {
		for ( const dependency of component.dependencies ) {
			if ( ! consumers.has( dependency ) )
				consumers.set( dependency, new Set() );
			consumers.get( dependency ).add( component.feature );
		}
	}

	const resolve = ( name, depth = 0 ) => {
		const binding = bindings.get( name );
		if ( name === 'React' )
			return { kind: 'platform', package: 'react (wp.element)' };
		if ( authoredBy.has( name ) )
			return {
				kind: 'feature-component',
				feature: authoredBy.get( name ).feature,
				component: authoredBy.get( name ).name,
			};
		if ( ! binding ) return { kind: 'unresolved' };
		if ( binding.alias && depth < 4 && bindings.has( binding.alias ) )
			return {
				...resolve( binding.alias, depth + 1 ),
				via: binding.alias,
			};
		if (
			binding.modules.length === 1 &&
			[ 'computed', 'value' ].includes( binding.definition ) &&
			binding.source.length < 200
		)
			return describeModule( binding.modules[ 0 ] );
		return {
			kind: 'recovered-local',
			definition: binding.definition,
			purpose: purpose( binding.source ),
			fragment: binding.fragment,
			bytes: binding.source.length,
			preview: binding.source.slice( 0, 160 ),
			modules: binding.modules.map( describeModule ),
		};
	};

	const ownerOf = ( name, resolved ) => {
		switch ( resolved.kind ) {
			case 'platform':
				return { owner: 'WordPress platform', step: 3 };
			case 'feature-component':
				return { owner: resolved.feature, step: 5 };
			case 'vendor':
				return { owner: 'vendor-packages', step: 2 };
			case 'lms-module':
				return {
					owner: 'shared-infrastructure',
					step: /REST client|data store/.test( resolved.hint )
						? 4
						: 3,
				};
			case 'recovered-local': {
				if ( resolved.purpose.startsWith( 'babel:' ) )
					return { owner: 'build tooling', step: 5 };
				const users = [ ...( consumers.get( name ) || [] ) ];
				const fragment = path.basename(
					resolved.fragment || '',
					'.js'
				);
				const byFragment = fragmentOwners.find( ( [ pattern ] ) =>
					pattern.test( fragment )
				)?.[ 1 ];
				const owner =
					users.length === 1
						? users[ 0 ]
						: users.length > 1
							? 'shared-infrastructure'
							: byFragment || 'shared-infrastructure';
				const step = [ 'api', 'state' ].includes( resolved.purpose )
					? 4
					: owner === 'shared-infrastructure'
						? 3
						: owner === 'bootstrap'
							? 6
							: 5;
				return { owner, step };
			}
			default:
				return { owner: 'unassigned', step: null };
		}
	};

	const dependencies = [ ...consumers.keys() ].sort().map( ( name ) => {
		const resolved = resolve( name );
		return {
			binding: name,
			consumers: [ ...consumers.get( name ) ].sort(),
			usedBy: components
				.filter( ( component ) =>
					component.dependencies.includes( name )
				)
				.map(
					( component ) =>
						`${ component.feature }/${ component.name }`
				),
			...resolved,
			...ownerOf( name, resolved ),
		};
	} );

	// Recovered library roots still registered by any shipping asset, consumer or not.
	const libraries = new Map();
	for ( const module of manifest.modules ) {
		if (
			module.kind !== 'third-party' &&
			! /chunks\/355/.test( module.bundle )
		)
			continue;
		const described = describeModule( module.id );
		if ( described.kind !== 'vendor' ) continue;
		const entry = libraries.get( described.library ) || {
			library: described.library,
			factories: 0,
			bytes: 0,
			bundles: new Set(),
			bridged: new Set(),
		};
		entry.factories++;
		entry.bytes += module.bytes;
		entry.bundles.add( module.bundle );
		described.bridged.forEach( ( file ) => entry.bridged.add( file ) );
		libraries.set( described.library, entry );
	}

	return {
		application: {
			fragments: application.fragments.length,
			bindings: bindings.size,
			routes: manifest.application.routes.length,
		},
		components,
		unwired,
		dependencies,
		// Step 6: what the recovered router mounts for each route today.
		routes: manifest.application.routes.map( ( { route, binding } ) => {
			const resolved = resolve( binding );
			return {
				route,
				binding,
				kind: resolved.kind,
				feature: resolved.feature,
				component: resolved.component,
				fragment: resolved.fragment,
			};
		} ),
		libraries: [ ...libraries.values() ]
			.map( ( entry ) => ( {
				...entry,
				bundles: [ ...entry.bundles ].sort(),
				bridged: [ ...entry.bridged ].sort(),
			} ) )
			.sort( ( a, b ) => b.bytes - a.bytes ),
	};
}
