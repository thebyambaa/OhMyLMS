import test from 'node:test';
import assert from 'node:assert/strict';
import {
	showRequestError,
	confirmAction,
} from '../../assets/src/features/requestFeedback.mjs';

/** Minimal native dialog surface: verifies text output, dismissal and deferred decisions. */
function documentFixture() {
	const saved = globalThis.document;
	class Element extends EventTarget {
		constructor( tag ) {
			super();
			this.tagName = tag;
			this.children = [];
			this.attributes = {};
			this.textContent = '';
		}
		append( ...children ) {
			for ( const child of children ) {
				child.parent = this;
				this.children.push( child );
			}
		}
		setAttribute( key, value ) {
			this.attributes[ key ] = value;
		}
		remove() {
			this.parent.children = this.parent.children.filter(
				( child ) => child !== this
			);
		}
		focus() {
			doc.activeElement = this;
		}
		showModal() {
			this.open = true;
		}
		close() {
			this.open = false;
			this.dispatchEvent( new Event( 'close' ) );
		}
	}
	const body = new Element( 'body' );
	const doc = {
		body,
		activeElement: body,
		createElement: ( tag ) => new Element( tag ),
		getElementById: ( id ) =>
			body.children.find( ( child ) => child.id === id ),
	};
	globalThis.document = doc;
	return {
		doc,
		restore() {
			globalThis.document = saved;
		},
	};
}

test( 'request errors display text without interpreting HTML and can be dismissed', () => {
	const fixture = documentFixture();
	try {
		showRequestError( Error( '<img src=x onerror=attack()>' ) );
		const notice = fixture.doc.body.children[ 0 ];
		assert.equal( notice.attributes.role, 'alert' );
		assert.equal(
			notice.children[ 0 ].textContent,
			'<img src=x onerror=attack()>'
		);
		assert.equal( notice.children[ 0 ].children.length, 0 );
		showRequestError( 'Invalid amount.' );
		assert.equal( fixture.doc.body.children.length, 1 );
		assert.equal(
			fixture.doc.body.children[ 0 ].children[ 0 ].textContent,
			'Invalid amount.'
		);
		fixture.doc.body.children[ 0 ].children[ 1 ].dispatchEvent(
			new Event( 'click' )
		);
		assert.equal( fixture.doc.body.children.length, 0 );
	} finally {
		fixture.restore();
	}
} );

test( 'confirmation resolves only after a choice and restores focus', async () => {
	const fixture = documentFixture();
	try {
		let acted = false;
		const decision = confirmAction( 'Remove selected accounts?' ).then(
			( confirmed ) => {
				acted = confirmed;
				return confirmed;
			}
		);
		const dialog = fixture.doc.body.children[ 0 ];
		assert.equal( dialog.open, true );
		assert.equal( acted, false );
		assert.equal( fixture.doc.activeElement.textContent, 'Cancel' );
		dialog.children[ 2 ].dispatchEvent( new Event( 'click' ) );
		assert.equal( await decision, true );
		assert.equal( fixture.doc.activeElement, fixture.doc.body );
		const cancelled = confirmAction( 'Remove selected accounts?' );
		fixture.doc.body.children[ 0 ].close();
		assert.equal( await cancelled, false );
	} finally {
		fixture.restore();
	}
} );
