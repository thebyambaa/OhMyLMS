import test from 'node:test';
import assert from 'node:assert/strict';
import {
	DEFAULT_DELAY,
	MAX_NOTICES,
	addNotice,
	createNotice,
	removeNotice,
} from '../../assets/src/shared/notices/model.mjs';

test( 'a notice defaults to an empty info message shown for three seconds', () => {
	assert.deepEqual( createNotice( undefined, undefined, 7 ), {
		id: 7,
		status: 'info',
		message: '',
	} );
	assert.equal( DEFAULT_DELAY, 3000 );
} );

test( 'only the newest three notices are kept, oldest dropped first', () => {
	let notices = [];
	for ( let id = 1; id <= 5; id++ ) {
		notices = addNotice(
			notices,
			createNotice( 'success', `m${ id }`, id )
		);
	}
	assert.equal( MAX_NOTICES, 3 );
	assert.deepEqual(
		notices.map( ( notice ) => notice.id ),
		[ 3, 4, 5 ]
	);
} );

test( 'adding and removing never modify the previous list', () => {
	const first = [ createNotice( 'info', 'a', 1 ) ];
	const added = addNotice( first, createNotice( 'error', 'b', 2 ) );
	const removed = removeNotice( added, 1 );
	assert.equal( first.length, 1 );
	assert.equal( added.length, 2 );
	assert.deepEqual(
		removed.map( ( notice ) => notice.id ),
		[ 2 ]
	);
} );

test( 'removing an unknown id leaves the notices unchanged', () => {
	const notices = [ createNotice( 'info', 'a', 1 ) ];
	assert.deepEqual( removeNotice( notices, 99 ), notices );
} );
