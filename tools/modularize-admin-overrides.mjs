// One-time migration of the authored admin overrides, preserving source order.
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
const root = path.resolve( import.meta.dirname, '..' );
const source = path.join( root, 'assets/src/admin-styles' );
const manifestPath = path.join( source, 'manifest.json' );
const manifest = JSON.parse( fs.readFileSync( manifestPath, 'utf8' ) );
if ( manifest.some( ( entry ) => entry.layer === 'ui' ) )
	throw new Error( 'Overrides already migrated.' );
const css = fs.readFileSync(
	path.join( root, 'assets/css/admin-ui.css' ),
	'utf8'
);
const nodes = postcss.parse( css ).nodes;
const features = {
	courses: /\.ohmylms-(?:course|chapter|lesson)[\w-]*/,
	quizzes:
		/\.ohmylms-(?:quiz|question|answer|bank|numerical|structured|practice|expression)[\w-]*/,
	curriculum:
		/\.ohmylms-(?:workspace|ws-|unified-workspace|curriculum|syllabus|subject|library|content-hub)[\w-]*/,
	skills: /\.ohmylms-(?:skill|ai-|bkt|mx)[\w-]*/,
	gamification: /\.ohmylms-(?:gamification|streak|badge|certificate)[\w-]*/,
	settings: /\.ohmylms-(?:feature-settings|settings|design)[\w-]*/,
	commerce: /\.ohmylms-(?:membership|order|coupon|subscription)[\w-]*/,
};
const runs = [];
for ( const [ index, node ] of nodes.entries() ) {
	const rules = [];
	if ( node.type === 'rule' ) rules.push( node );
	else if (
		node.type === 'atrule' &&
		/^(media|supports|container)$/.test( node.name )
	)
		node.walkRules( ( rule ) => rules.push( rule ) );
	let group = 'shared';
	if ( node.type === 'atrule' && node.name === 'import' )
		group = 'curriculum';
	for ( const [ feature, pattern ] of Object.entries( features ) ) {
		if (
			rules.length &&
			rules.every( ( rule ) =>
				postcss.list
					.comma( rule.selector )
					.every( ( selector ) => pattern.test( selector ) )
			)
		) {
			group = feature;
			break;
		}
	}
	if ( runs.at( -1 )?.group !== group ) {
		const start = index === 0 ? 0 : node.source.start.offset;
		if ( runs.length ) runs.at( -1 ).end = start;
		runs.push( { group, start, end: css.length } );
	}
}
for ( const [ index, run ] of runs.entries() ) {
	const file = `ui-${ String( index ).padStart( 3, '0' ) }-${ run.group }.css`;
	fs.writeFileSync(
		path.join( source, file ),
		css.slice( run.start, run.end )
	);
	manifest.push( { file, group: run.group, rtl: false, layer: 'ui' } );
}
fs.writeFileSync( manifestPath, JSON.stringify( manifest, null, '\t' ) + '\n' );
console.log( `Created ${ runs.length } admin override modules.` );
