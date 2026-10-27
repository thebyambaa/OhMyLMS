import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';

const source = fs
	.readFileSync(
		'assets/src/features/curriculum/SkillConnections.jsx',
		'utf8'
	)
	.replace( /^import .*;$/gm, '' )
	.replace( /export /g, '' );
const { code } = transformSync( source, {
	configFile: false,
	babelrc: false,
	presets: [ [ '@babel/preset-react', { pragma: 'createElement' } ] ],
} );
const SkillConnections = new Function(
	'createElement',
	'__',
	'RelationshipBadge',
	code + ';return SkillConnections;'
)(
	( type, props, ...children ) => ( { type, props, children } ),
	( text ) => text,
	'badge'
);
const nodes = ( tree ) =>
	! tree || typeof tree !== 'object'
		? []
		: Array.isArray( tree )
			? tree.flatMap( nodes )
			: [ tree, ...nodes( tree.children ) ];
const mapping = {
	id: 4,
	specific_id: 12,
	shared_id: 20,
	specific: { id: 12, name: 'Source objective', code: 'C1.1' },
	shared: { id: 20, name: 'Shared ability' },
	note: 'Scope limits',
	relation: 'broader',
};

test( 'incoming and outgoing connections show the counterpart with the source-oriented relation unchanged', () => {
	for ( const relation of [
		'equivalent',
		'broader',
		'narrower',
		'related',
		'combined',
	] ) {
		const saved = { ...mapping, relation };
		const before = JSON.stringify( saved );
		const incoming = SkillConnections( {
			skill: { term_id: 20, connections: [ saved ] },
		} );
		const outgoing = SkillConnections( {
			skill: { term_id: '12', connections: [ saved ] },
		} );
		assert.match(
			JSON.stringify( incoming ),
			/Source skill:.*C1.1.*Source objective/
		);
		assert.match(
			JSON.stringify( outgoing ),
			/Shared skill:.*Shared ability/
		);
		assert.equal(
			nodes( incoming ).find( ( node ) => node.type === 'badge' ).props
				.relation,
			relation
		);
		assert.equal( JSON.stringify( saved ), before );
	}
} );

test( 'spreadsheet columns retain pair numbering and missing targets do not crash the view', () => {
	const skill = {
		term_id: 20,
		connections: [ mapping, { ...mapping, id: 5, specific: null } ],
	};
	const names = SkillConnections( { skill, part: 'names' } );
	const types = SkillConnections( { skill, part: 'types' } );
	assert.equal(
		nodes( names ).filter( ( node ) => node.type === 'badge' ).length,
		0
	);
	assert.match( JSON.stringify( names ), /Unavailable skill/ );
	assert.equal(
		nodes( types ).filter( ( node ) => node.type === 'badge' ).length,
		2
	);
	assert.equal( SkillConnections( { skill: {} } ), null );
} );
