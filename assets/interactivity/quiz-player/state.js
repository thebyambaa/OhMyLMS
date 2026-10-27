/**
 * Reactive player presentation state, independent of store registration.
 * @param getContext
 */
export function createPlayerState( getContext ) {
	return {
		get pageLabel() {
			const c = getContext();
			if ( c.layout === 'one_question_per_page' && c.questionOfLabel ) {
				return c.questionOfLabel
					.replace( '%1$d', c.page )
					.replace( '%2$d', c.totalPages );
			}
			return `${ c.page } / ${ c.totalPages }`;
		},
		get pageWidth() {
			const c = getContext();
			return `${ Math.min( 100, Math.max( 0, ( c.page / Math.max( 1, c.totalPages ) ) * 100 ) ) }%`;
		},
		get isPage() {
			const c = getContext();
			return c.page === c.questionPage;
		},
		get isQuestion() {
			const c = getContext();
			return (
				c.layout !== 'one_question_per_page' ||
				c.page === c.questionNumber
			);
		},
		get previousDisabled() {
			const c = getContext();
			return c.page <= 1 || c.submitting;
		},
		get nextDisplay() {
			const c = getContext();
			return c.page < c.totalPages ? '' : 'none';
		},
		get submitDisplay() {
			const c = getContext();
			return c.page >= c.totalPages || c.error ? '' : 'none';
		},
		get requiredDisplay() {
			const c = getContext();
			return c.errors[ c.questionNumber ] ? 'block' : 'none';
		},
		// An unanswered question offers Skip; once answered the same button reads Next.
		get isSkip() {
			const c = getContext();
			return (
				c.layout === 'one_question_per_page' && ! c.answered?.[ c.page ]
			);
		},
		get nextLabel() {
			const c = getContext();
			return c.layout === 'one_question_per_page' &&
				! c.answered?.[ c.page ]
				? c.skipText
				: c.nextText;
		},
		get exitDisplay() {
			return getContext().exitOpen ? 'block' : 'none';
		},
		get errorDisplay() {
			return getContext().error ? 'block' : 'none';
		},
		get timerWidth() {
			const c = getContext();
			return `${ c.duration ? Math.max( 0, ( c.remaining / c.duration ) * 100 ) : 0 }%`;
		},
		// Exam palette: one button per question, each with its own `questionNumber` context.
		get isCurrentQuestion() {
			const c = getContext();
			return c.page === c.questionNumber;
		},
		get ariaCurrent() {
			const c = getContext();
			return c.page === c.questionNumber ? 'step' : undefined;
		},
		get isQuestionAnswered() {
			const c = getContext();
			return !! c.answered?.[ c.questionNumber ];
		},
		get isQuestionMarked() {
			const c = getContext();
			return !! c.marked?.[ c.questionNumber ];
		},
		get isQuestionVisited() {
			const c = getContext();
			return !! (
				c.visited?.[ c.questionNumber ] ||
				c.answered?.[ c.questionNumber ]
			);
		},
		get isCurrentMarked() {
			const c = getContext();
			return !! c.marked?.[ c.page ];
		},
		get currentMarkLabel() {
			const c = getContext();
			return c.marked?.[ c.page ] ? c.markedText : c.markText;
		},
		get answeredCount() {
			return Object.values( getContext().answered || {} ).filter(
				Boolean
			).length;
		},
		get markedCount() {
			return Object.values( getContext().marked || {} ).filter( Boolean )
				.length;
		},
		get visitedCount() {
			const c = getContext();
			return new Set( [
				...Object.keys( c.visited || {} ).filter(
					( key ) => c.visited[ key ]
				),
				...Object.keys( c.answered || {} ).filter(
					( key ) => c.answered[ key ]
				),
			] ).size;
		},
		get notVisitedCount() {
			const c = getContext();
			return Math.max( 0, c.totalPages - this.visitedCount );
		},
		get notAnsweredCount() {
			return Math.max( 0, this.visitedCount - this.answeredCount );
		},
		get clockHours() {
			const seconds = getContext().remaining;
			return String( Math.floor( seconds / 3600 ) ).padStart( 2, '0' );
		},
		get clockMinutes() {
			const seconds = getContext().remaining;
			return String( Math.floor( ( seconds % 3600 ) / 60 ) ).padStart(
				2,
				'0'
			);
		},
		get clockSeconds() {
			return String( getContext().remaining % 60 ).padStart( 2, '0' );
		},
		get timeLabel() {
			const seconds = getContext().remaining;
			const pad = ( value ) => String( value ).padStart( 2, '0' );
			return `${ seconds >= 3600 ? `${ pad( Math.floor( seconds / 3600 ) ) }h ` : '' }${ pad( Math.floor( ( seconds % 3600 ) / 60 ) ) }m ${ pad( seconds % 60 ) }s`;
		},
	};
}
