// @ts-check
/**
 * Course review form for the `ohmylms/reviews` store (batch L5): star rating and the "rating and
 * review both are required" check. The form still posts to wp-comments-post.php, and the stars
 * are radio inputs, so it works without JavaScript; `CourseComment::add_comment_rating()` stores
 * the rating.
 */

/** @typedef {(event: Event) => void} EventHandler */

export const MAX_RATING = 5;

/**
 * A rating from a radio, select or `data-rating` value: 1 to 5, 0 when missing or out of range.
 *
 * @param {unknown} value Value.
 * @return {number} Rating.
 */
export function parseRating( value ) {
	const rating = Number.parseInt( String( value ?? '' ), 10 );
	return Number.isInteger( rating ) && rating >= 1 && rating <= MAX_RATING
		? rating
		: 0;
}

/**
 * What is missing from a review.
 *
 * @param {{rating: unknown, comment: unknown}} review Fields.
 * @return {{rating: boolean, comment: boolean, valid: boolean}} Missing parts.
 */
export function reviewProblems( { rating, comment } ) {
	const missingRating = 0 === parseRating( rating );
	const missingComment = '' === String( comment ?? '' ).trim();
	return {
		rating: missingRating,
		comment: missingComment,
		valid: ! missingRating && ! missingComment,
	};
}

/**
 * Rating and comment of the review form that contains an element.
 *
 * @param {HTMLFormElement|null|undefined} form Form.
 * @return {{rating: number, comment: string}} Current values.
 */
export function readReview( form ) {
	const elements = form?.elements;
	const rating = /** @type {RadioNodeList|HTMLSelectElement|null} */ (
		elements?.namedItem?.( 'rating' ) ?? null
	);
	const comment = /** @type {HTMLTextAreaElement|null} */ (
		elements?.namedItem?.( 'comment' ) ?? null
	);
	return {
		rating: parseRating( rating?.value ),
		comment: String( comment?.value ?? '' ),
	};
}

/**
 * Store definitions for `ohmylms/reviews`. Root context: `{ rating, hasComment, attempted,
 * submitting }`; each star adds `{ star }`.
 *
 * @param {Object}                                                                                api               Runtime and SDK.
 * @param {() => Object}                                                                          api.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement}}                                                              api.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: EventHandler) => EventHandler}                                              api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {(target: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @return {{state: Object, actions: Object, callbacks: Object}} Definitions.
 */
export function createReviews( {
	getContext,
	getElement,
	withSyncEvent,
	emit,
} ) {
	/**
	 * @param {Object}                            context Root context.
	 * @param {{rating: number, comment: string}} review  Values.
	 */
	const sync = ( context, review ) => {
		context.rating = review.rating;
		context.hasComment = '' !== review.comment.trim();
	};

	/**
	 * @param {EventTarget|null} target Event target.
	 * @return {HTMLFormElement|null} Review form.
	 */
	const formOf = ( target ) => {
		const element = /** @type {HTMLElement|null} */ ( target );
		return (
			/** @type {HTMLFormElement|null} */ (
				element?.closest?.( 'form' )
			) ?? null
		);
	};

	return {
		state: {
			get rated() {
				return getContext().rating > 0;
			},
			get isStar() {
				const context = getContext();
				return context.rating > 0 && context.rating === context.star;
			},
			get noticeDisplay() {
				const context = getContext();
				return context.attempted &&
					( ! context.rating || ! context.hasComment )
					? 'block'
					: 'none';
			},
		},
		actions: {
			change( event ) {
				const form = formOf( event.target );
				if ( form ) {
					sync( getContext(), readReview( form ) );
				}
			},
			pick: withSyncEvent( ( event ) => {
				event.preventDefault();
				const star = getElement().ref;
				const rating = parseRating( star.dataset?.rating );
				const select = /** @type {HTMLSelectElement|null} */ (
					formOf( star )?.querySelector( 'select[name="rating"]' ) ??
						null
				);
				if ( select ) {
					select.value = String( rating );
				}
				getContext().rating = rating;
				emit( star, 'review-rated', { rating } );
			} ),
			validate( event ) {
				const context = getContext();
				const review = readReview( formOf( event.target ) );
				sync( context, review );
				if ( ! reviewProblems( review ).valid ) {
					context.attempted = true;
				}
			},
			submit: withSyncEvent( ( event ) => {
				const form = /** @type {HTMLFormElement} */ ( event.target );
				const context = getContext();
				const review = readReview( form );
				sync( context, review );
				if ( ! reviewProblems( review ).valid ) {
					event.preventDefault();
					context.attempted = true;
					return;
				}
				if (
					context.submitting ||
					! emit(
						form,
						'review-before-submit',
						{ rating: review.rating },
						true
					)
				) {
					event.preventDefault();
					return;
				}
				context.submitting = true;
			} ),
			reset() {
				getContext().submitting = false;
			},
		},
		callbacks: {
			restore() {
				const form = getElement().ref.querySelector( 'form' );
				if ( form ) {
					sync( getContext(), readReview( form ) );
				}
			},
		},
	};
}
