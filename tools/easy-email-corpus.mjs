import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { createLoader, installBrowserStubs } from './recovered-modules.mjs';

/** Golden corpus for `easy-email-core`'s JSON-to-MJML step.
 *
 *   node tools/easy-email-corpus.mjs --write            record the recovered core's output
 *   node tools/easy-email-corpus.mjs --compare [ver]    run a published core over the corpus
 *   node tools/easy-email-corpus.mjs --wrapper          run the authored wrapper (assets/src/shared/easy-email)
 *                                                       over the corpus
 *
 * Templates are stored as JSON, so they are independent of whichever core builds them. The
 * recorded output is what existing emails render from today; a replacement core must match it
 * (see docs/EASY-EMAIL-AUDIT.md). MJML-to-HTML rendering is covered separately.
 */
const root = path.resolve( import.meta.dirname, '..' );
export const corpusPath = path.join(
	root,
	'tests/fixtures/easy-email-corpus.json'
);
export const modes = [ 'production', 'testing' ];

/**
 * Recovered core, loaded without the browser. Pass `{ stubs: false }` to keep the globals
 * the caller has set up (for example a different `MRM_Vars`) instead of the defaults.
 */
export function loadRecoveredCore( { stubs = true } = {} ) {
	const hostRequire = stubs
		? installBrowserStubs()
		: createRequire( path.join( root, 'package.json' ) );
	const load = createLoader( {
		// react-dom/server wrapper; the rest of the chain is not needed for static markup.
		65848: { qV: hostRequire( 'react-dom/server' ).renderToStaticMarkup },
		// MJML itself is not used by JsonToMjml.
		44098: () => {
			throw new Error( 'MJML renderer is not available in the corpus' );
		},
	} );
	return load( 49050 );
}

/** The authored wrapper around the installed `easy-email-core`, loaded as the bundle loads it. */
export async function loadCompatibleCore() {
	installBrowserStubs();
	const { createEasyEmailCore } =
		await import( '../assets/src/shared/easy-email/index.mjs' );
	return createEasyEmailCore();
}

/** Build one template per block type, plus hand-written compositions. */
export function buildTemplates( core ) {
	const types = [
		...Object.values( core.BasicType ),
		...Object.values( core.AdvancedType ),
	];
	const parentOf = ( type ) => {
		const parents =
			core.BlockManager.getBlockByType( type )?.validParentType || [];
		// Prefer the shallowest known parent, so chains stay short.
		return (
			[ 'column', 'section', 'wrapper', 'group', 'hero', 'footer' ].find(
				( candidate ) => parents.includes( candidate )
			) || parents[ 0 ]
		);
	};
	const wrap = ( type ) => {
		let node = core.createBlockDataByType( type );
		let parent = parentOf( type );
		while ( parent && parent !== 'page' ) {
			const outer = core.createBlockDataByType( parent );
			outer.children = [ node ];
			node = outer;
			parent = parentOf( parent );
		}
		const page = core.createBlockDataByType( 'page' );
		page.children = [ node ];
		return page;
	};
	const templates = [];
	templates.push( {
		name: 'empty page',
		data: core.createBlockDataByType( 'page' ),
	} );
	for ( const type of types ) {
		if ( [ 'page', 'template' ].includes( type ) ) continue;
		templates.push( { name: `block: ${ type }`, data: wrap( type ) } );
	}
	// Composition with merge tags, Unicode, links, rtl text and page-level settings.
	const text = ( content, extra = {} ) => {
		const block = core.createBlockDataByType( 'text' );
		block.data.value.content = content;
		Object.assign( block.attributes, extra );
		return block;
	};
	const column = ( ...children ) => {
		const block = core.createBlockDataByType( 'column' );
		block.children = children;
		return block;
	};
	const section = ( attributes, ...columns ) => {
		const block = core.createBlockDataByType( 'section' );
		Object.assign( block.attributes, attributes );
		block.children = columns;
		return block;
	};
	const page = core.createBlockDataByType( 'page' );
	page.data.value.breakpoint = '520px';
	page.data.value[ 'font-family' ] = 'Georgia, serif';
	page.data.value.fonts = [
		{ name: 'Inter', href: 'https://fonts.example.test/inter.css' },
	];
	page.data.value.headStyles = [
		{ content: '.lms-note { color: #b00020; }', inline: 'inline' },
	];
	page.attributes[ 'background-color' ] = '#f4f4f4';
	const button = core.createBlockDataByType( 'button' );
	button.data.value.content = 'Үргэлжлүүлэх';
	button.attributes.href =
		'https://example.test/course?id={{course.id}}&ref=mail';
	const image = core.createBlockDataByType( 'image' );
	image.attributes.src = 'https://example.test/hero.png';
	image.attributes.alt = 'Welcome « & » banner';
	page.children = [
		section(
			{ 'background-color': '#ffffff', padding: '20px 0px' },
			column( image, text( 'Hello {{user.first_name}}, сайн уу!' ) )
		),
		section(
			{
				'background-url': 'https://example.test/bg.jpg',
				'background-repeat': 'no-repeat',
			},
			column(
				text(
					'<a href="https://example.test?a=1&amp;b=2">Open</a> <b>bold</b>',
					{
						align: 'right',
						direction: 'rtl',
					}
				),
				button
			),
			column( text( 'Second column', { 'font-size': '18px' } ) )
		),
	];
	templates.push( {
		name: 'composition: merge tags, Unicode, page settings',
		data: page,
	} );
	return templates;
}

export function render( core, data, mode ) {
	return core.JsonToMjml( { data, mode, context: data } );
}

export function record( core ) {
	return buildTemplates( core ).map( ( { name, data } ) => ( {
		name,
		data,
		output: Object.fromEntries(
			modes.map( ( mode ) => [ mode, render( core, data, mode ) ] )
		),
	} ) );
}

/** Core from npm in a scratch directory; the caller removes `dir`. */
function loadPublishedCore( version ) {
	if ( ! /^\d+\.\d+\.\d+$/.test( version ) )
		throw new Error( `Invalid version: ${ version }` );
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'ohmylms-ee-core-' ) );
	fs.writeFileSync( path.join( dir, 'package.json' ), '{"private":true}' );
	execSync(
		`npm install easy-email-core@${ version } react@18 react-dom@18 --no-audit --no-fund --silent`,
		{ cwd: dir }
	);
	installBrowserStubs();
	const scratch = createRequire( path.join( dir, 'package.json' ) );
	globalThis.React = scratch( 'react' );
	return { dir, core: scratch( 'easy-email-core' ) };
}

function firstDifference( a, b ) {
	const left = a.split( /\n/ );
	const right = b.split( /\n/ );
	for ( let i = 0; i < Math.max( left.length, right.length ); i++ ) {
		const x = left[ i ] ?? '';
		const y = right[ i ] ?? '';
		if ( x === y ) continue;
		let at = 0;
		while ( at < x.length && x[ at ] === y[ at ] ) at++;
		const window = ( text ) =>
			text.slice( Math.max( 0, at - 60 ), at + 120 ).trim();
		return [
			`line ${ i + 1 }, column ${ at + 1 }`,
			`    recorded:  ...${ window( x ) }`,
			`    published: ...${ window( y ) }`,
		].join( '\n' );
	}
	return 'none';
}

/** Render every template in both modes with `core` and report each difference from the recording. */
export function compareCorpus( corpus, core ) {
	let same = 0;
	let different = 0;
	for ( const entry of corpus ) {
		for ( const mode of modes ) {
			let actual;
			try {
				actual = render( core, entry.data, mode );
			} catch ( error ) {
				actual = `ERROR ${ error.message }`;
			}
			if ( actual === entry.output[ mode ] ) {
				same++;
			} else {
				different++;
				console.log(
					`DIFF ${ entry.name } [${ mode }]: ${ firstDifference(
						entry.output[ mode ],
						actual
					) }`
				);
			}
		}
	}
	return { same, different };
}

if (
	process.argv[ 1 ] &&
	path.resolve( process.argv[ 1 ] ) === import.meta.filename
) {
	if ( process.argv.includes( '--write' ) ) {
		const corpus = record( loadRecoveredCore() );
		fs.writeFileSync(
			corpusPath,
			JSON.stringify( corpus, null, '\t' ) + '\n'
		);
		console.log(
			`Recorded ${ corpus.length } templates x ${ modes.length } modes`
		);
	} else if ( process.argv.includes( '--wrapper' ) ) {
		const corpus = JSON.parse( fs.readFileSync( corpusPath, 'utf8' ) );
		const { same, different } = compareCorpus(
			corpus,
			await loadCompatibleCore()
		);
		console.log(
			`authored wrapper: ${ same } identical, ${ different } different`
		);
		process.exitCode = different ? 1 : 0;
	} else if ( process.argv.includes( '--compare' ) ) {
		const version =
			process.argv[ process.argv.indexOf( '--compare' ) + 1 ] || '4.17.1';
		const corpus = JSON.parse( fs.readFileSync( corpusPath, 'utf8' ) );
		const { dir, core } = loadPublishedCore( version );
		let result;
		try {
			result = compareCorpus( corpus, core );
		} finally {
			fs.rmSync( dir, { recursive: true, force: true } );
		}
		console.log(
			`easy-email-core@${ version }: ${ result.same } identical, ${ result.different } different`
		);
		process.exitCode = result.different ? 1 : 0;
	} else {
		console.error( 'Use --write, --wrapper or --compare [version]' );
		process.exitCode = 2;
	}
}
