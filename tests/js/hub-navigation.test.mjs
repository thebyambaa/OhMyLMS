import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(
	new URL( '../../assets/js/student-hub.js', import.meta.url ),
	'utf8'
);

function workspace( mobile = true ) {
	const events = {};
	const media = { matches: mobile };
	const document = {
		activeElement: null,
		addEventListener: ( name, callback ) => {
			events[ name ] = callback;
		},
		querySelectorAll: () => [],
	};
	const control = () => ( {
		attributes: {},
		listeners: {},
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		removeAttribute( name ) {
			delete this.attributes[ name ];
		},
		addEventListener( name, callback ) {
			this.listeners[ name ] = callback;
		},
		focus() {
			document.activeElement = this;
		},
		getClientRects: () => [ {} ],
	} );
	const opener = control();
	const close = control();
	const first = control();
	const last = control();
	const content = control();
	const rail = {
		...control(),
		querySelector: () => close,
		querySelectorAll: () => [ first, close, last ],
	};
	const classes = new Set();
	const hub = {
		dataset: { role: 'teacher' },
		classList: {
			toggle: ( name, on ) =>
				on ? classes.add( name ) : classes.delete( name ),
			contains: ( name ) => classes.has( name ),
		},
		querySelector: ( selector ) =>
			( {
				'[data-hub-rail-open]': opener,
				'[data-hub-rail-close]': close,
				'#oml-hub-rail': rail,
				'.oml-hub-main': content,
			} )[ selector ],
		querySelectorAll: () => [],
	};
	document.querySelector = ( selector ) =>
		selector === '.ohmylms-student-hub' ? hub : null;
	media.addEventListener = ( name, callback ) => {
		media.change = callback;
	};
	vm.runInNewContext( source, {
		document,
		window: {
			location: { hash: '' },
			addEventListener() {},
			matchMedia: () => media,
			localStorage: { getItem: () => null },
		},
	} );
	return {
		document,
		events,
		opener,
		close,
		first,
		last,
		content,
		rail,
		media,
	};
}

test( 'mobile navigation moves focus into the rail and Escape restores it', () => {
	const page = workspace();
	assert.equal( page.rail.inert, true );
	assert.equal( page.rail.attributes[ 'aria-hidden' ], 'true' );
	page.opener.listeners.click();
	assert.equal( page.document.activeElement, page.close );
	assert.equal( page.content.inert, true );
	assert.equal( page.rail.inert, false );
	page.events.keydown( { key: 'Escape' } );
	assert.equal( page.document.activeElement, page.opener );
	assert.equal( page.content.inert, false );
	assert.equal( page.rail.inert, true );
	assert.equal( page.opener.attributes[ 'aria-expanded' ], 'false' );
} );

test( 'Tab and Shift+Tab stay within an open mobile rail', () => {
	const page = workspace();
	page.opener.listeners.click();
	let prevented = 0;
	const event = {
		key: 'Tab',
		preventDefault: () => prevented++,
	};
	page.last.focus();
	page.events.keydown( event );
	assert.equal( page.document.activeElement, page.first );
	page.events.keydown( { ...event, shiftKey: true } );
	assert.equal( page.document.activeElement, page.last );
	assert.equal( prevented, 2 );
} );

test( 'resizing to desktop releases the content and keeps the rail accessible', () => {
	const page = workspace();
	page.opener.listeners.click();
	page.media.matches = false;
	page.media.change();
	assert.equal( page.content.inert, false );
	assert.equal( page.rail.inert, false );
	assert.equal( page.rail.attributes[ 'aria-hidden' ], undefined );
	assert.equal( page.opener.attributes[ 'aria-expanded' ], 'false' );
} );
