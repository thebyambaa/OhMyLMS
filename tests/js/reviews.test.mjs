import test from 'node:test';
import assert from 'node:assert/strict';
import {
	createReviews,
	parseRating,
	readReview,
	reviewProblems,
} from '../../assets/interactivity/reviews/rating.js';

const syncEvent = ( callback ) => callback;

function event( extra = {} ) {
	return {
		prevented: false,
		preventDefault() {
			this.prevented = true;
		},
		...extra,
	};
}

/**
 * Review form double: radio/select `rating`, textarea `comment`, optional `select[name=rating]`.
 *
 * @param {Object} values `{ rating, comment }`.
 * @param {Object} [select] Legacy select element.
 */
function fakeForm( values, select = null ) {
	const form = {
		elements: {
			namedItem: ( name ) =>
				name in values ? { value: values[ name ] } : null,
		},
		querySelector: ( selector ) =>
			'select[name="rating"]' === selector ? select : null,
	};
	form.closest = () => form;
	return form;
}

function harness( context, ref = null, cancel = '' ) {
	const events = [];
	let element = { ref };
	const reviews = createReviews( {
		getContext: () => context,
		getElement: () => element,
		withSyncEvent: syncEvent,
		emit: ( target, name, detail ) => {
			events.push( { name, detail } );
			return name !== cancel;
		},
	} );
	return { reviews, events, setElement: ( next ) => ( element = { ref: next } ) };
}

test( 'ratings are integers from 1 to 5', () => {
	assert.equal( parseRating( '4' ), 4 );
	assert.equal( parseRating( 5 ), 5 );
	assert.equal( parseRating( '' ), 0 );
	assert.equal( parseRating( '6' ), 0 );
	assert.equal( parseRating( '0' ), 0 );
	assert.equal( parseRating( 'x' ), 0 );
	assert.equal( parseRating( undefined ), 0 );
} );

test( 'a review needs a rating and a comment', () => {
	assert.deepEqual( reviewProblems( { rating: 0, comment: '' } ), {
		rating: true,
		comment: true,
		valid: false,
	} );
	assert.deepEqual( reviewProblems( { rating: '3', comment: '  ' } ), {
		rating: false,
		comment: true,
		valid: false,
	} );
	assert.equal(
		reviewProblems( { rating: 3, comment: 'Great' } ).valid,
		true
	);
	assert.deepEqual(
		readReview( fakeForm( { rating: '2', comment: 'Ok' } ) ),
		{ rating: 2, comment: 'Ok' }
	);
	assert.deepEqual( readReview( null ), { rating: 0, comment: '' } );
} );

test( 'choosing a star marks the group and that star', () => {
	const context = { rating: 0, hasComment: false, attempted: false };
	const run = harness( context );
	assert.equal( run.reviews.state.rated, false );
	const form = fakeForm( { rating: '4', comment: '' } );
	run.reviews.actions.change( { target: form } );
	assert.equal( context.rating, 4 );
	assert.equal( run.reviews.state.rated, true );
	context.star = 4;
	assert.equal( run.reviews.state.isStar, true );
	context.star = 3;
	assert.equal( run.reviews.state.isStar, false );
} );

test( 'pre-L5 link stars set the legacy select', () => {
	const select = { value: '' };
	const form = fakeForm( { rating: '', comment: '' }, select );
	const star = { dataset: { rating: '5' }, closest: () => form };
	const context = { rating: 0 };
	const run = harness( context, star );
	const click = event();
	run.reviews.actions.pick( click );
	assert.equal( click.prevented, true );
	assert.equal( select.value, '5' );
	assert.equal( context.rating, 5 );
	assert.deepEqual( run.events, [
		{ name: 'review-rated', detail: { rating: 5 } },
	] );
} );

test( 'the notice shows after an incomplete attempt and hides once complete', () => {
	const context = {
		rating: 0,
		hasComment: false,
		attempted: false,
		submitting: false,
	};
	const run = harness( context );
	const values = { rating: '', comment: '' };
	const form = fakeForm( values );
	assert.equal( run.reviews.state.noticeDisplay, 'none' );
	run.reviews.actions.validate( { target: form } );
	assert.equal( context.attempted, true );
	assert.equal( run.reviews.state.noticeDisplay, 'block' );

	const blocked = event( { target: form } );
	run.reviews.actions.submit( blocked );
	assert.equal( blocked.prevented, true );

	values.rating = '3';
	values.comment = 'Clear lessons';
	run.reviews.actions.change( { target: form } );
	assert.equal( run.reviews.state.noticeDisplay, 'none' );

	const sent = event( { target: form } );
	run.reviews.actions.submit( sent );
	assert.equal( sent.prevented, false, 'the form posts natively' );
	assert.equal( context.submitting, true );
	assert.deepEqual( run.events.at( -1 ), {
		name: 'review-before-submit',
		detail: { rating: 3 },
	} );

	const again = event( { target: form } );
	run.reviews.actions.submit( again );
	assert.equal( again.prevented, true, 'no double submission' );
	run.reviews.actions.reset();
	assert.equal( context.submitting, false, 'back/forward cache restores' );
} );

test( 'listeners can cancel a review submission', () => {
	const context = { rating: 0, hasComment: false, submitting: false };
	const run = harness( context, null, 'review-before-submit' );
	const form = fakeForm( { rating: '2', comment: 'x' } );
	const submit = event( { target: form } );
	run.reviews.actions.submit( submit );
	assert.equal( submit.prevented, true );
	assert.equal( context.submitting, false );
} );

test( 'restore reads values the browser kept (autofill, back button)', () => {
	const context = { rating: 0, hasComment: false };
	const form = fakeForm( { rating: '5', comment: 'Kept' } );
	const run = harness( context, { querySelector: () => form } );
	run.reviews.callbacks.restore();
	assert.equal( context.rating, 5 );
	assert.equal( context.hasComment, true );
} );
