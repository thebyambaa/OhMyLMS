import test from 'node:test';
import assert from 'node:assert/strict';
import {
	createRegistry,
	KINDS,
} from '../../assets/src/extensions/registry.mjs';
const item = { label: 'Example', render: () => null, action: () => {} };
test( 'all extension categories register, unregister and enforce duplicate IDs', () => {
	const r = createRegistry();
	for ( const kind of KINDS ) {
		const remove = r.register( kind, 'example', item );
		assert.equal( r.list( kind ).length, 1 );
		assert.throws( () => r.register( kind, 'example', item ), /Duplicate/ );
		remove();
		assert.equal( r.list( kind ).length, 0 );
	}
} );
test( 'priority and ID order is deterministic; disabled definitions never render', () => {
	const r = createRegistry();
	r.registerSlot( 'z', item );
	r.registerSlot( 'a', item );
	r.registerSlot( 'first', { ...item, priority: 0 } );
	r.registerSlot( 'disabled', { ...item, enabled: false } );
	assert.deepEqual(
		r.list( 'slot' ).map( ( e ) => e.id ),
		[ 'first', 'a', 'z' ]
	);
} );
test( 'reject invalid definitions and incompatible interface versions', () => {
	const r = createRegistry();
	for ( const [ kind, id, definition ] of [
		[ 'missing', 'ok', item ],
		[ 'slot', 'BAD', item ],
		[ 'slot', 'ok', {} ],
		[ 'slot', 'ok', { ...item, apiVersion: 2 } ],
		[ 'slot', 'ok', { ...item, priority: NaN } ],
	] )
		assert.throws( () => r.register( kind, id, definition ) );
} );
test( 'subscriptions notify on actual changes and can unsubscribe', () => {
	const r = createRegistry();
	let calls = 0;
	const unsubscribe = r.subscribe( () => calls++ );
	const remove = r.registerSlot( 'example', item );
	assert.equal( calls, 1 );
	unsubscribe();
	remove();
	assert.equal( calls, 1 );
	remove();
	assert.equal( r.getRevision(), 2 );
} );
