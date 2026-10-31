import test from 'node:test';
import assert from 'node:assert/strict';
import {
	CANCEL_ACTION,
	IMAGE_ACTIONS,
	cancelFields,
	createAccount,
	imageProblem,
	resultMessage,
	rowFragment,
} from '../../assets/interactivity/account/store.js';

const MESSAGES = {
	imageType: 'type',
	imageSize: 'size',
	uploadFailed: 'upload failed',
	coverSaved: 'cover saved',
	avatarSaved: 'avatar saved',
	coverDeleted: 'cover deleted',
	deleteFailed: 'delete failed',
	cancelTitle: 'Cancel?',
	cancelMessage: 'You lose access.',
	cancelConfirm: 'Confirm Cancel',
	cancelKeep: 'Keep',
	cancelled: 'cancelled',
	cancelFailed: 'cancel failed',
};

const ok = ( data = null, message = '' ) => ( {
	ok: true,
	status: 200,
	data,
	message,
	error: null,
} );
const failed = ( message, code = 'ajax_error' ) => ( {
	ok: false,
	status: 200,
	data: null,
	message,
	error: { code, message, status: 200 },
} );

/** A store with fake runtime and SDK; `element` and `context` are what the directive sees. */
function fixture( options = {} ) {
	const log = {
		requests: [],
		toasts: [],
		events: [],
		dialogs: [],
		traps: [],
		timers: [],
		reloads: 0,
	};
	const env = { element: null, context: {} };
	const responses = [ ...( options.responses || [] ) ];
	const account = createAccount( {
		getContext: () => env.context,
		getElement: () => ( { ref: env.element } ),
		withSyncEvent: ( callback ) => callback,
		getState: () => ( {
			messages: MESSAGES,
			nonces: {
				coverUpload: 'n-cover',
				coverDelete: 'n-delete',
				avatarUpload: 'n-avatar',
				cancelMembership: 'n-cancel',
			},
			maxUpload: 1000,
			...options.state,
		} ),
		request: async ( request ) => {
			log.requests.push( request );
			return responses.shift() || ok();
		},
		toast: ( message, toastOptions = {} ) =>
			log.toasts.push( [ message, toastOptions.type || 'success' ] ),
		confirmDialog: async ( dialog ) => {
			log.dialogs.push( dialog );
			return options.confirm ?? true;
		},
		emit: ( root, name, detail, cancelable ) => {
			log.events.push( [ name, detail ] );
			return ! ( cancelable && options.block === name );
		},
		trapFocus: ( container, trap ) => {
			const entry = { container, trap, released: false };
			log.traps.push( entry );
			return () => {
				entry.released = true;
			};
		},
		win: {
			location: {
				reload: () => {
					log.reloads++;
				},
			},
			print: () => log.events.push( [ 'print' ] ),
			addEventListener: ( name, callback ) =>
				log.events.push( [ `listen:${ name }`, callback ] ),
		},
		later: ( callback, ms ) => log.timers.push( [ callback, ms ] ),
	} );
	return { account, env, log };
}

const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

function event( target, extra = {} ) {
	return {
		target,
		defaultPrevented: false,
		preventDefault() {
			this.defaultPrevented = true;
		},
		...extra,
	};
}

function fileInput( file, root ) {
	return {
		files: file ? [ file ] : [],
		value: 'C:\\fakepath\\me.png',
		closest: () => root,
	};
}

test( 'imageProblem accepts images under the limit', () => {
	assert.equal( imageProblem( { type: 'image/png', size: 10 }, 100 ), '' );
	assert.equal( imageProblem( { type: 'image/webp', size: 10 }, 0 ), '' );
	assert.equal(
		imageProblem( { type: 'application/pdf', size: 10 }, 100 ),
		'type'
	);
	assert.equal( imageProblem( { type: '', size: 10 }, 100 ), 'type' );
	assert.equal( imageProblem( { type: 'image/jpeg', size: 101 }, 100 ), 'size' );
	assert.equal( imageProblem( null, 100 ), 'empty' );
} );

test( 'cancelFields sends only the subscription and its order', () => {
	assert.deepEqual(
		cancelFields( { subscriptionId: 12, orderId: 7, busy: false } ),
		{ subscription_id: 12, order_id: 7 }
	);
	assert.deepEqual( cancelFields( { subscriptionId: '12' } ), {
		subscription_id: 12,
	} );
	// Pre-L4 overrides: ids on the inline alert's confirm button; student and plan are ignored.
	assert.deepEqual(
		cancelFields(
			{ busy: false },
			{
				subscriptionId: '44',
				orderId: '3',
				membershipId: '9',
				studentId: '2',
			}
		),
		{ subscription_id: 44, order_id: 3 }
	);
	assert.equal( cancelFields( {}, {} ), null );
	assert.equal( cancelFields( { subscriptionId: -4 } ), null );
	assert.equal( cancelFields( { subscriptionId: 'x' } ), null );
} );

test( 'resultMessage prefers the server message', () => {
	assert.equal( resultMessage( failed( 'Nope' ), 'fallback' ), 'Nope' );
	assert.equal( resultMessage( failed( '  ' ), 'fallback' ), 'fallback' );
	assert.equal( resultMessage( null, 'fallback' ), 'fallback' );
} );

test( 'rowFragment clones <template> content and reads pre-L4 script templates', () => {
	const clone = { cloned: true };
	assert.equal(
		rowFragment( { content: { cloneNode: () => clone } } ),
		clone
	);
	const holder = { innerHTML: '', content: { from: 'holder' } };
	const script = {
		innerHTML: '  <div class="row"></div>  ',
		ownerDocument: { createElement: () => holder },
	};
	assert.deepEqual( rowFragment( script ), { from: 'holder' } );
	assert.equal( holder.innerHTML, '<div class="row"></div>' );
	assert.equal( rowFragment( null ), null );
} );

test( 'uploading a cover posts the file with its nonce and shows it', async () => {
	const { account, env, log } = fixture( {
		responses: [ ok( { url: 'https://x.test/cover.png' } ) ],
	} );
	const root = { querySelector: () => null };
	const file = { type: 'image/png', size: 10, name: 'cover.png' };
	const input = fileInput( file, root );
	env.context = { cover: 'default.png', hasCover: false, busy: '' };
	account.actions.uploadCover( event( input ) );
	assert.equal( env.context.busy, 'cover' );
	assert.equal( account.state.isBusy, true );
	await settle();
	assert.deepEqual( log.requests, [
		{
			action: IMAGE_ACTIONS.cover.action,
			nonce: 'n-cover',
			data: { file },
		},
	] );
	assert.equal( env.context.cover, 'https://x.test/cover.png' );
	assert.equal( env.context.hasCover, true );
	assert.equal( env.context.busy, '' );
	assert.equal( input.value, '' );
	assert.equal( account.state.coverDeleteDisplay, 'flex' );
	assert.deepEqual( log.toasts, [ [ 'cover saved', 'success' ] ] );
	assert.deepEqual(
		log.events.map( ( [ name ] ) => name ),
		[ 'account-before-upload', 'account-image-changed' ]
	);
} );

test( 'a file that is not an image is refused before any request', async () => {
	const { account, env, log } = fixture();
	const input = fileInput(
		{ type: 'application/pdf', size: 10 },
		{ querySelector: () => null }
	);
	env.context = { avatar: '', busy: '' };
	account.actions.uploadAvatar( event( input ) );
	await settle();
	assert.equal( log.requests.length, 0 );
	assert.deepEqual( log.toasts, [ [ 'type', 'danger' ] ] );
	assert.equal( input.value, '' );
	const big = fileInput(
		{ type: 'image/png', size: 5000 },
		{ querySelector: () => null }
	);
	account.actions.uploadAvatar( event( big ) );
	await settle();
	assert.deepEqual( log.toasts[ 1 ], [ 'size', 'danger' ] );
	assert.equal( log.requests.length, 0 );
} );

test( 'a failed upload keeps the old image and shows the server message', async () => {
	const { account, env, log } = fixture( {
		responses: [ failed( 'Sorry, this file type is not permitted.' ) ],
	} );
	env.context = { avatar: 'old.png', busy: '' };
	account.actions.uploadAvatar(
		event(
			fileInput(
				{ type: 'image/png', size: 10 },
				{ querySelector: () => null }
			)
		)
	);
	await settle();
	assert.equal( log.requests[ 0 ].action, IMAGE_ACTIONS.avatar.action );
	assert.equal( log.requests[ 0 ].nonce, 'n-avatar' );
	assert.equal( env.context.avatar, 'old.png' );
	assert.deepEqual( log.toasts, [
		[ 'Sorry, this file type is not permitted.', 'danger' ],
	] );
	assert.deepEqual( log.events.at( -1 ), [
		'account-error',
		{
			operation: 'upload-avatar',
			message: 'Sorry, this file type is not permitted.',
		},
	] );
} );

test( 'a successful answer without a URL counts as a failure', async () => {
	const { account, env, log } = fixture( { responses: [ ok( {} ) ] } );
	env.context = { cover: 'a.png', hasCover: true, busy: '' };
	account.actions.uploadCover(
		event(
			fileInput(
				{ type: 'image/png', size: 10 },
				{ querySelector: () => null }
			)
		)
	);
	await settle();
	assert.equal( env.context.cover, 'a.png' );
	assert.deepEqual( log.toasts, [ [ 'upload failed', 'danger' ] ] );
} );

test( 'an add-on can veto an upload', async () => {
	const { account, env, log } = fixture( {
		block: 'account-before-upload',
	} );
	env.context = { cover: 'a.png', busy: '' };
	account.actions.uploadCover(
		event(
			fileInput(
				{ type: 'image/png', size: 10 },
				{ querySelector: () => null }
			)
		)
	);
	await settle();
	assert.equal( log.requests.length, 0 );
	assert.equal( env.context.busy, '' );
} );

test( 'a pre-L4 avatar without an <img> gets one after the upload', async () => {
	const created = { className: '', id: '', alt: 'x', src: '' };
	let replaced = null;
	const figure = {
		querySelector: () => null,
		ownerDocument: { createElement: () => created },
		replaceChildren: ( child ) => {
			replaced = child;
		},
	};
	const root = {
		querySelector: ( selector ) =>
			'.ohmylms-profile-avatar figure' === selector ? figure : null,
	};
	const { account, env } = fixture( {
		responses: [ ok( { url: 'https://x.test/me.png' } ) ],
	} );
	env.context = { avatar: '', busy: '' };
	assert.equal( account.state.avatarHidden, true );
	assert.equal( account.state.initialsHidden, false );
	account.actions.uploadAvatar(
		event( fileInput( { type: 'image/png', size: 10 }, root ) )
	);
	await settle();
	assert.equal( replaced, created );
	assert.equal( created.src, 'https://x.test/me.png' );
	assert.equal( created.className, 'student-profile-photo' );
	assert.equal( account.state.avatarHidden, false );
	assert.equal( account.state.initialsHidden, true );
} );

test( 'removing the cover shows the default image and hides the button', async () => {
	const { account, env, log } = fixture( {
		responses: [ ok( { url: 'https://x.test/default.svg' } ) ],
	} );
	env.element = { closest: () => ( { root: true } ) };
	env.context = { cover: 'mine.png', hasCover: true, busy: '' };
	const click = event( env.element );
	account.actions.deleteCover( click );
	assert.equal( click.defaultPrevented, true );
	await settle();
	assert.deepEqual( log.requests, [
		{ action: IMAGE_ACTIONS.deleteCover.action, nonce: 'n-delete' },
	] );
	assert.equal( env.context.cover, 'https://x.test/default.svg' );
	assert.equal( env.context.hasCover, false );
	assert.equal( account.state.coverDeleteDisplay, 'none' );
	assert.deepEqual( log.toasts, [ [ 'cover deleted', 'success' ] ] );
} );

test( 'Enter and Space on an upload label open the file picker', () => {
	const { account, env } = fixture();
	let clicks = 0;
	const label = { control: { click: () => clicks++ } };
	env.element = label;
	const enter = event( label, { key: 'Enter' } );
	account.actions.pickKey( enter );
	account.actions.pickKey( event( label, { key: ' ' } ) );
	account.actions.pickKey( event( label, { key: 'a' } ) );
	account.actions.pickKey( event( { child: true }, { key: 'Enter' } ) );
	assert.equal( clicks, 2 );
	assert.equal( enter.defaultPrevented, true );
} );

test( 'repeatable rows: add a row from its template, remove or clear one', () => {
	const { account, env } = fixture();
	const focused = [];
	const field = ( name ) => ( {
		name,
		value: name,
		focus: () => focused.push( name ),
	} );
	const added = field( 'new' );
	const appended = [];
	const fragment = { querySelector: () => added };
	const nodes = {
		'skills-container': { append: ( row ) => appended.push( row ) },
		'skills-template': { content: { cloneNode: () => fragment } },
	};
	env.element = {
		dataset: { target: 'skills-container', template: 'skills-template' },
		ownerDocument: { getElementById: ( id ) => nodes[ id ] || null },
	};
	const add = event( env.element );
	account.actions.addRow( add );
	assert.equal( add.defaultPrevented, true );
	assert.deepEqual( appended, [ fragment ] );
	assert.deepEqual( focused, [ 'new' ] );

	const row = ( name, list ) => {
		const input = field( name );
		const element = {
			parentElement: list,
			removed: false,
			remove() {
				this.removed = true;
			},
			querySelector: () => input,
			querySelectorAll: () => [ input ],
			input,
		};
		return element;
	};
	const list = { children: [] };
	const first = row( 'first', list );
	const second = row( 'second', list );
	list.children.push( first, second );
	const clickRemove = ( target ) => ( {
		closest: ( selector ) =>
			'.ohmylms-remove-field' === selector
				? { closest: () => target }
				: null,
	} );
	account.actions.removeRow( event( clickRemove( second ) ) );
	assert.equal( second.removed, true );
	assert.equal( focused.at( -1 ), 'first', 'focus moves to the previous row' );

	list.children = [ first ];
	const last = event( clickRemove( first ) );
	account.actions.removeRow( last );
	assert.equal( first.removed, false, 'the last row is cleared, not removed' );
	assert.equal( first.input.value, '' );
	assert.equal( last.defaultPrevented, true );

	const elsewhere = event( { closest: () => null } );
	account.actions.removeRow( elsewhere );
	assert.equal( elsewhere.defaultPrevented, false );
} );

test( 'cancelling a membership confirms, then posts only the subscription', async () => {
	const { account, env, log } = fixture( {
		responses: [ ok( { message: 'Cancel membership successfully.' } ) ],
	} );
	const link = {
		dataset: {},
		parentElement: { querySelector: () => null },
	};
	env.element = link;
	env.context = { subscriptionId: 31, orderId: 30, busy: false };
	const click = event( link );
	account.actions.cancelMembership( click );
	assert.equal( click.defaultPrevented, true );
	await settle();
	assert.deepEqual( log.dialogs, [
		{
			title: 'Cancel?',
			message: 'You lose access.',
			confirmLabel: 'Confirm Cancel',
			cancelLabel: 'Keep',
			tone: 'danger',
		},
	] );
	assert.deepEqual( log.requests, [
		{
			action: CANCEL_ACTION,
			nonce: 'n-cancel',
			data: { subscription_id: 31, order_id: 30 },
		},
	] );
	assert.equal( env.context.busy, true );
	assert.deepEqual( log.toasts, [ [ 'cancelled', 'success' ] ] );
	assert.ok(
		log.events.some( ( [ name ] ) => 'membership-cancelled' === name )
	);
	assert.equal( log.timers.length, 1 );
	log.timers[ 0 ][ 0 ]();
	assert.equal( log.reloads, 1 );
} );

test( 'keeping the subscription sends nothing', async () => {
	const { account, env, log } = fixture( { confirm: false } );
	env.element = { dataset: {}, parentElement: { querySelector: () => null } };
	env.context = { subscriptionId: 31, busy: false };
	account.actions.cancelMembership( event( env.element ) );
	await settle();
	assert.equal( log.dialogs.length, 1 );
	assert.equal( log.requests.length, 0 );
	assert.equal( env.context.busy, false );
} );

test( 'a refused cancel (another learner\u2019s subscription) shows the server error', async () => {
	const { account, env, log } = fixture( {
		responses: [ failed( 'You cannot cancel this membership.' ) ],
	} );
	env.element = { dataset: {}, parentElement: { querySelector: () => null } };
	env.context = { subscriptionId: 99, busy: false };
	account.actions.cancelMembership( event( env.element ) );
	await settle();
	assert.deepEqual( log.toasts, [
		[ 'You cannot cancel this membership.', 'danger' ],
	] );
	assert.equal( env.context.busy, false );
	assert.equal( log.timers.length, 0 );
	assert.ok(
		log.events.some( ( [ name ] ) => 'membership-cancel-error' === name )
	);
} );

test( 'a pre-L4 cancel link reads the ids from its inline alert', async () => {
	const { account, env, log } = fixture();
	const confirm = { dataset: { subscriptionId: '8', orderId: '5' } };
	env.element = {
		dataset: {},
		parentElement: {
			querySelector: ( selector ) =>
				'.ohmylms-cancel-membership' === selector ? confirm : null,
		},
	};
	env.context = { busy: false };
	account.actions.cancelMembership( event( env.element ) );
	await settle();
	assert.deepEqual( log.requests[ 0 ].data, {
		subscription_id: 8,
		order_id: 5,
	} );
} );

test( 'the plan dialog opens, traps focus and closes on Escape or the backdrop', () => {
	const { account, env, log } = fixture();
	const inner = { inner: true };
	const modal = { querySelector: () => inner };
	env.context = { planOpen: false };
	assert.equal( account.state.planDisplay, false );
	assert.equal( account.callbacks.planFocus(), undefined );

	env.element = { trigger: true };
	const open = event( env.element );
	account.actions.openPlan( open );
	assert.equal( open.defaultPrevented, true );
	assert.equal( env.context.planOpen, true );
	assert.equal( account.state.planDisplay, 'block' );

	env.element = modal;
	const release = account.callbacks.planFocus();
	assert.equal( log.traps.length, 1 );
	assert.equal( log.traps[ 0 ].container, inner );
	log.traps[ 0 ].trap.onEscape();
	assert.equal( env.context.planOpen, false );
	release();
	assert.equal( log.traps[ 0 ].released, true );

	env.context.planOpen = true;
	account.actions.planBackdrop(
		event( { closest: ( selector ) => ( selector ? inner : null ) } )
	);
	assert.equal( env.context.planOpen, true, 'a click inside the dialog' );
	account.actions.planBackdrop( event( { closest: () => null } ) );
	assert.equal( env.context.planOpen, false, 'a click on the backdrop' );

	env.context.planOpen = true;
	account.actions.closePlan( event( {} ) );
	assert.equal( env.context.planOpen, false );
} );

test( 'printing marks the invoice and the page until afterprint', () => {
	const { account, env, log } = fixture();
	const classes = ( set ) => ( {
		add: ( name ) => set.add( name ),
		remove: ( name ) => set.delete( name ),
	} );
	const html = new Set();
	const area = new Set();
	env.element = {
		ownerDocument: { documentElement: { classList: classes( html ) } },
		closest: ( selector ) =>
			'.membership-invoice-printable-area' === selector
				? { classList: classes( area ) }
				: null,
	};
	account.actions.printInvoice( event( env.element ) );
	assert.ok( html.has( 'ohmylms-printing' ) && area.has( 'ohmylms-print-area' ) );
	const listener = log.events.find( ( [ name ] ) => 'listen:afterprint' === name );
	assert.ok( log.events.some( ( [ name ] ) => 'print' === name ) );
	listener[ 1 ]();
	assert.equal( html.size + area.size, 0 );
} );
