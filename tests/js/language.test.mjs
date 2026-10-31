import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {
	QUERY_VAR,
	ROUTE,
	chosenLocale,
	createLanguage,
	localeTag,
	matchLocale,
	negotiateLocale,
	parseAcceptLanguage,
	switchUrl,
} from '../../assets/interactivity/language/model.mjs';

const cases = JSON.parse(
	fs.readFileSync(
		new URL( '../fixtures/accept-language.json', import.meta.url ),
		'utf8'
	)
);

test( 'parseAcceptLanguage orders ranges by quality and drops q=0 and malformed ranges', () => {
	for ( const { header, ranges } of cases.parse ) {
		assert.deepEqual( parseAcceptLanguage( header ), ranges, header );
	}
	assert.deepEqual( parseAcceptLanguage( undefined ), [] );
	assert.equal(
		parseAcceptLanguage( Array( 50 ).fill( 'mn' ).join( ',' ) ).length,
		32,
		'reads at most 32 ranges'
	);
} );

test( 'negotiateLocale maps mn, mn-MN and any mn-* to mn, the wildcard and the rest to English', () => {
	for ( const { header, locale } of cases.match ) {
		assert.equal(
			negotiateLocale( header, cases.locales, cases.fallback ),
			locale,
			header
		);
	}
} );

test( 'matchLocale prefers a whole-tag match and keeps the locale order for languages', () => {
	assert.equal(
		matchLocale( [ { tag: 'en-gb', q: 1 } ], [ 'en_GB', 'en_US' ], 'x' ),
		'en_GB'
	);
	assert.equal(
		matchLocale( [ { tag: 'en-au', q: 1 } ], [ 'en_GB', 'en_US' ], 'x' ),
		'en_GB'
	);
	assert.equal( matchLocale( [], [ 'mn' ], 'x' ), 'x' );
	assert.equal( localeTag( 'en_US' ), 'en-us' );
} );

test( 'chosenLocale reads only the switcher buttons', () => {
	assert.equal( chosenLocale( { name: QUERY_VAR, value: 'mn' } ), 'mn' );
	assert.equal( chosenLocale( { name: 'other', value: 'mn' } ), '' );
	assert.equal( chosenLocale( null ), '' );
	assert.equal( chosenLocale( undefined ), '' );
} );

test( 'switchUrl replaces the query with the submitted fields and drops the fragment', () => {
	const params = new URLSearchParams( [
		[ 'course_search', 'math' ],
		[ 'course_level[]', 'beginner' ],
		[ QUERY_VAR, 'mn' ],
	] );
	assert.equal(
		switchUrl( 'http://800.local/courses/?old=1#top', params ),
		'http://800.local/courses/?course_search=math&course_level%5B%5D=beginner&ohmylms_locale=mn'
	);
} );

/**
 * Store double: one form context, recorded events and navigation.
 *
 * @param {Object} reply Result `request()` resolves with.
 */
function harness( reply ) {
	const context = { current: 'en_US', busy: false };
	const calls = { requests: [], events: [], reloads: 0, navigations: [] };
	const definitions = createLanguage( {
		getContext: () => context,
		withSyncEvent: ( callback ) => callback,
		request: ( options ) => {
			calls.requests.push( options );
			return Promise.resolve( reply );
		},
		emit: ( target, name, detail, cancelable ) => {
			calls.events.push( { name, detail, cancelable } );
			return ! ( cancelable && calls.cancel );
		},
		serializeForm: ( form, options ) =>
			new URLSearchParams( [
				[ 'paged', '2' ],
				[ options.submitter.name, options.submitter.value ],
			] ),
		reload: () => {
			calls.reloads++;
		},
		navigate: ( url ) => calls.navigations.push( url ),
	} );
	return { context, calls, actions: definitions.actions };
}

function submitEvent( value, name = QUERY_VAR ) {
	return {
		prevented: false,
		target: { action: 'http://800.local/dashboard/' },
		submitter: { name, value },
		preventDefault() {
			this.prevented = true;
		},
	};
}

test( 'choosing a language posts it and reloads the page', async () => {
	const { context, calls, actions } = harness( {
		ok: true,
		data: { locale: 'mn', source: 'user' },
	} );
	const event = submitEvent( 'mn' );
	await actions.choose( event );
	assert.equal( event.prevented, true );
	assert.deepEqual( calls.requests, [
		{ path: ROUTE, method: 'POST', data: { locale: 'mn' } },
	] );
	assert.equal( context.busy, true );
	assert.equal( calls.reloads, 1 );
	assert.deepEqual(
		calls.events.map( ( item ) => item.name ),
		[ 'language-before-change', 'language-changed' ]
	);
	assert.deepEqual( calls.events[ 1 ].detail, {
		locale: 'mn',
		previous: 'en_US',
		source: 'user',
	} );
} );

test( 'a failed request falls back to the form GET URL', async () => {
	const { calls, actions } = harness( {
		ok: false,
		data: null,
		error: { code: 'rest_no_route', message: 'No route' },
	} );
	await actions.choose( submitEvent( 'mn' ) );
	assert.equal( calls.reloads, 0 );
	assert.deepEqual( calls.navigations, [
		'http://800.local/dashboard/?paged=2&ohmylms_locale=mn',
	] );
	assert.equal( calls.events[ 1 ].name, 'language-error' );
	assert.equal( calls.events[ 1 ].detail.code, 'rest_no_route' );
} );

test( 'the current language, a busy form, a cancelled event and other buttons do nothing', async () => {
	const same = harness( { ok: true, data: {} } );
	const current = submitEvent( 'en_US' );
	await same.actions.choose( current );
	assert.equal( current.prevented, true );
	assert.equal( same.calls.requests.length, 0 );

	const busy = harness( { ok: true, data: {} } );
	busy.context.busy = true;
	await busy.actions.choose( submitEvent( 'mn' ) );
	assert.equal( busy.calls.requests.length, 0 );

	const cancelled = harness( { ok: true, data: {} } );
	cancelled.calls.cancel = true;
	await cancelled.actions.choose( submitEvent( 'mn' ) );
	assert.equal( cancelled.calls.requests.length, 0 );
	assert.equal( cancelled.context.busy, false );

	const other = harness( { ok: true, data: {} } );
	const foreign = submitEvent( 'x', 'search' );
	other.actions.choose( foreign );
	assert.equal( foreign.prevented, false, 'other submitters submit normally' );

	busy.actions.reset();
	assert.equal( busy.context.busy, false, 'pageshow resets the form' );
} );
