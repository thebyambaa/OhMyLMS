// One-time, lossless migration of the recovered admin stylesheet into authored modules.
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';

const root = path.resolve( import.meta.dirname, '..' );
const directory = path.join( root, 'assets/src/admin-styles' );
if ( fs.existsSync( directory ) )
	throw new Error( 'Admin style sources already exist.' );
fs.mkdirSync( directory, { recursive: true } );
const features = {
	courses: /\.ohmylms-(?:course|chapter|lesson|lcm|rec-)[\w-]*/,
	quizzes: /\.ohmylms-(?:quiz|question|option|answer)[\w-]*/,
	communities: /\.ohmylms-community[\w-]*/,
	integrations: /\.ohmylms-integration[\w-]*/,
	setup: /\.ohmylms-(?:setup|wizard)[\w-]*/,
	settings: /\.ohmylms-(?:page-feature|page-features|settings)[\w-]*/,
	emails: /\.ohmylms-(?:email|automation)[\w-]*/,
	commerce: /\.ohmylms-(?:tax|order|coupon|subscription)[\w-]*/,
	students: /\.ohmylms-(?:student|enrollment)[\w-]*/,
};
function owner( node ) {
	const rules = [];
	if ( node.type === 'rule' ) rules.push( node );
	else if (
		node.type === 'atrule' &&
		/^(media|supports|container)$/.test( node.name )
	)
		node.walkRules( ( rule ) => rules.push( rule ) );
	if ( ! rules.length ) return 'shared';
	for ( const [ feature, pattern ] of Object.entries( features ) ) {
		if (
			rules.every( ( rule ) =>
				postcss.list
					.comma( rule.selector )
					.every( ( selector ) => pattern.test( selector ) )
			)
		)
			return feature;
	}
	return 'shared';
}
const manifest = [];
for ( const variant of [ 'main', 'rtl' ] ) {
	const filename = variant === 'main' ? 'ohmylms.css' : 'ohmylms-rtl.css';
	const css = fs.readFileSync(
		path.join( root, 'assets/src/static/dist/css/admin', filename ),
		'utf8'
	);
	const nodes = postcss.parse( css ).nodes;
	const runs = [];
	for ( let index = 0; index < nodes.length; index++ ) {
		const node = nodes[ index ];
		let group = owner( node );
		// These complete recovered vendor sections include their variables, fonts,
		// animations and responsive rules, rather than only their named selectors.
		if ( variant === 'main' ) {
			if ( index >= 1127 && index < 2878 ) group = 'block-editor';
			if ( index >= 2878 && index < 3263 ) group = 'email-shell';
			if ( index >= 3263 && index < 8070 ) group = 'email-editor';
			if ( index >= 8070 && index < 9484 ) group = 'automations';
		}
		let run = runs.at( -1 );
		if ( ! run || run.group !== group ) {
			run = {
				group,
				start: index === 0 ? 0 : node.source.start.offset,
				end: css.length,
			};
			if ( runs.length ) runs.at( -1 ).end = run.start;
			runs.push( run );
		}
	}
	for ( const [ index, run ] of runs.entries() ) {
		const name = `${ variant }-${ String( index ).padStart( 3, '0' ) }-${ run.group }.css`;
		fs.writeFileSync(
			path.join( directory, name ),
			css.slice( run.start, run.end )
		);
		manifest.push( {
			file: name,
			group: run.group,
			rtl: variant === 'rtl',
		} );
	}
}
fs.writeFileSync(
	path.join( directory, 'manifest.json' ),
	JSON.stringify( manifest, null, '\t' ) + '\n'
);
console.log( `Created ${ manifest.length } ordered style modules.` );
