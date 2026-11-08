import test from 'node:test';
import assert from 'node:assert/strict';
import {
	leavesPage,
	shouldAutosave,
} from '../../assets/src/features/learning/autosave.mjs';

const base = {
	type: 'ohmylms-lesson',
	status: 'draft',
	title: 'Fractions',
	dirty: true,
	saving: false,
	locked: false,
	signature: '{"title":"a"}',
	failedSignature: '',
};

test( 'unsaved edits of a lesson, quiz or question are saved', () => {
	for ( const type of [
		'ohmylms-lesson',
		'ohmylms-quiz',
		'ohmylms-question',
	] ) {
		assert.equal( shouldAutosave( { ...base, type } ), true, type );
	}
} );

test( 'nothing is saved when there is nothing to save, a save runs or saving is locked', () => {
	assert.equal( shouldAutosave( { ...base, dirty: false } ), false );
	assert.equal( shouldAutosave( { ...base, saving: true } ), false );
	assert.equal( shouldAutosave( { ...base, locked: true } ), false );
	assert.equal( shouldAutosave( { ...base, type: 'post' } ), false );
} );

test( 'a new item needs a title, so leaving it blank makes no draft', () => {
	assert.equal(
		shouldAutosave( { ...base, status: 'auto-draft', title: '  ' } ),
		false
	);
	assert.equal(
		shouldAutosave( { ...base, status: 'auto-draft', title: 'Unit 1' } ),
		true
	);
} );

test( 'a failed save is not repeated until the edits change', () => {
	const failed = { ...base, failedSignature: base.signature };
	assert.equal( shouldAutosave( failed ), false );
	assert.equal(
		shouldAutosave( { ...failed, signature: '{"title":"b"}' } ),
		true
	);
} );

test( 'only plain same-tab links to another page wait for a save', () => {
	const here = 'https://example.test/wp-admin/post.php?post=5&action=edit';
	const link = {
		href: 'https://example.test/wp-admin/admin.php?page=ohmylms-bank',
		target: '',
		download: false,
		modified: false,
	};
	assert.equal( leavesPage( link, here ), true );
	assert.equal( leavesPage( { ...link, target: '_blank' }, here ), false );
	assert.equal( leavesPage( { ...link, modified: true }, here ), false );
	assert.equal( leavesPage( { ...link, download: true }, here ), false );
	assert.equal(
		leavesPage( { ...link, href: `${ here }#panel` }, here ),
		false
	);
	assert.equal(
		leavesPage( { ...link, href: 'mailto:a@b.test' }, here ),
		false
	);
} );
