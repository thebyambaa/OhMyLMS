import test from 'node:test';
import assert from 'node:assert/strict';
import {
	fitStudioCanvas,
	zoomStudioCanvas,
} from '../../assets/src/features/curriculum/studio-canvas.mjs';
import { focusedStudioObjectives } from '../../assets/src/features/curriculum/skill-studio.mjs';

test( 'switching a shared skill cannot render the previous skill connections', () => {
	const connections = {
		skillId: 10,
		groups: [
			{
				source: { id: 1, name: 'SAT' },
				items: [
					{
						term_id: 5,
						maps_to: [ { shared_id: 10, relation: 'related' } ],
					},
					{ term_id: 6, maps_to: [] },
				],
			},
		],
	};
	assert.deepEqual( focusedStudioObjectives( connections, 20 ), [] );
	assert.deepEqual(
		focusedStudioObjectives( connections, 10 ).map(
			( row ) => row.term_id
		),
		[ 5 ]
	);
} );

test( 'fit includes the lowest cards in a narrow canvas viewport', () => {
	const points = {
		shared: { x: 230, y: 170 },
		first: { x: 20, y: 24 },
		last: { x: 450, y: 459 },
	};
	const view = fitStudioCanvas( points, 500, 340 );
	for ( const point of Object.values( points ) ) {
		assert.ok( point.x * view.zoom + view.pan.x >= 19 );
		assert.ok( point.y * view.zoom + view.pan.y >= 19 );
		assert.ok( ( point.x + 190 ) * view.zoom + view.pan.x <= 481 );
		assert.ok( ( point.y + 134 ) * view.zoom + view.pan.y <= 321 );
	}
} );

test( 'zoom keeps the cursor world point fixed and clamps extreme input', () => {
	const view = { zoom: 0.7, pan: { x: -60, y: 20 } };
	const anchor = { x: 190, y: 110 };
	const next = zoomStudioCanvas( view, 1.2, anchor );
	assert.ok(
		Math.abs(
			( anchor.x - view.pan.x ) / view.zoom -
				( anchor.x - next.pan.x ) / next.zoom
		) < 0.00001
	);
	assert.ok(
		Math.abs(
			( anchor.y - view.pan.y ) / view.zoom -
				( anchor.y - next.pan.y ) / next.zoom
		) < 0.00001
	);
	assert.equal( zoomStudioCanvas( view, 100, anchor ).zoom, 2 );
	assert.equal( zoomStudioCanvas( view, 0.001, anchor ).zoom, 0.2 );
} );
