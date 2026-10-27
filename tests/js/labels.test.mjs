import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { RELABELED, relabel } from '../../assets/src/extensions/labels.mjs';

test( 'category and tag labels are replaced by the curriculum and course bundle wording', () => {
	assert.equal( relabel( 'Category', 'Category' ), 'Curriculum' );
	assert.equal( relabel( 'Categories', 'Categories' ), 'Curriculum' );
	assert.equal( relabel( 'Tags', 'Tags' ), 'Course bundles' );
	assert.equal( relabel( 'Tag: ', 'Tag: ' ), 'Course bundle: ' );
	assert.equal(
		relabel( 'Catégorie', 'Category', ( text ) => `[${ text }]` ),
		'[Curriculum]',
		'goes through translation'
	);
} );

test( 'contact and automation tag labels are not touched', () => {
	for ( const text of [
		'Add Tag',
		'Apply Tags',
		'Remove Tags',
		'ASSIGN TAG',
		'Select Tag',
		'Category Base',
		'Price Type',
	] ) {
		assert.equal(
			relabel( `translated ${ text }`, text ),
			`translated ${ text }`
		);
	}
} );

test( 'every relabeled string is still used by the admin app, so a stale entry is caught', () => {
	const app = fs.readFileSync( 'assets/dist/admin/ohmylms.js', 'utf8' );
	for ( const text of Object.keys( RELABELED ) ) {
		const literal = JSON.stringify( text );
		assert.ok(
			app.includes( `(${ literal }, "ohmylms")` ),
			`${ literal } is no longer a string of the admin app; remove it from RELABELED`
		);
	}
} );
