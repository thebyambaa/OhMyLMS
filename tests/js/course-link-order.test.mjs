import test from 'node:test';
import assert from 'node:assert/strict';
import {
	canMoveLink,
	linkPayload,
	moveLink,
	stripOf,
} from '../../assets/src/features/courses/courseLinks.mjs';

test( 'links move within their hub strip and the saved order is the array order', () => {
	const links = [
		{ course_id: 1, type: 'get_ready' },
		{ course_id: 2, type: 'subcourse' },
		{ course_id: 3, type: 'prerequisite' },
		{ course_id: 4, type: 'get_ready' },
	];
	assert.equal( stripOf( links[ 2 ] ), 'get_ready' );
	assert.equal( canMoveLink( links, '1:get_ready', -1 ), false );
	assert.equal( canMoveLink( links, '2:subcourse', 1 ), false );
	// Moving 3 up skips the sub-course (another strip) and passes course 1.
	const moved = moveLink( links, '3:prerequisite', -1 );
	assert.deepEqual(
		linkPayload( moved ).map( ( l ) => l.course_id ),
		[ 3, 2, 1, 4 ]
	);
	const again = moveLink( moved, '4:get_ready', -1 );
	assert.deepEqual(
		linkPayload( again ).map( ( l ) => l.course_id ),
		[ 3, 2, 4, 1 ]
	);
	assert.equal( moveLink( links, '2:subcourse', -1 ), links );
} );
