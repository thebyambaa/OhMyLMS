import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import {
	registerAnswerValidator,
	registerQuestionMount,
	isAnswered,
	mountQuestion,
} from 'ohmylms/interactivity';
import { createQuestionControls } from './questionControls.js';
import { gridRowsAnswered } from './gridAnswers.js';
import { attachMatchingLines } from './matchingLines.js';
/**
 * Controls by action name; each receives the DOM event of its name (drag events, keydown, click).
 *
 * @typedef {Record<string, (event: Event) => void>} ControlActions
 */
/** @type {WeakMap<Element, ReturnType<typeof createQuestionControls>>} */
const controllers = new WeakMap();
store( 'ohmylms/questions', {
	actions: Object.fromEntries(
		[
			'select',
			'dragstart',
			'dragend',
			'dragover',
			'drop',
			'place',
			'move',
			'unlink',
			'key',
		].map( ( name ) => [
			name,
			withSyncEvent( ( event ) => {
				event.stopPropagation();
				// Actions run inside the island.
				const root = /** @type {HTMLElement} */ (
					/** @type {HTMLElement} */ ( getElement().ref ).closest(
						'[data-wp-interactive="ohmylms/questions"]'
					)
				);
				if ( ! controllers.has( root ) ) {
					controllers.set(
						root,
						createQuestionControls( root, getContext() )
					);
				}
				/** @type {ControlActions} */ (
					/** @type {unknown} */ ( controllers.get( root ) )
				)[ name ]( event );
			} ),
		] )
	),
} );

/**
 * Fetched HTML is inside data-wp-ignore; initialize only this isolated renderer region.
 * @param {Element} container Question root.
 * @return {() => void} Cleanup.
 */
function mountDynamic( container ) {
	const root = /** @type {HTMLElement|null} */ (
		container.querySelector(
			'.quiz-reorder-options,.quiz-matching-options'
		)
	);
	if ( ! root ) {
		return () => {};
	}
	const controller = createQuestionControls( root, {
		selectedId: '',
	} );
	const listeners = {
		dragstart: 'dragstart',
		dragend: 'dragend',
		dragover: 'dragover',
		drop: 'drop',
		keydown: 'key',
		click: 'select',
		dblclick: 'unlink',
	};
	/** @type {Array<[string, (event: Event) => void]>} */
	const registered = [];
	for ( const [ event, action ] of Object.entries( listeners ) ) {
		/** @param {Event} eventObject DOM event. */
		const listener = ( eventObject ) => {
			const target = /** @type {Element} */ ( eventObject.target );
			if ( event === 'click' && target.closest( '.reorder-move' ) ) {
				controller.move( eventObject );
			} else if (
				event === 'click' &&
				target.closest( '.option-drop-box' )
			) {
				controller.place( eventObject );
			} else {
				/** @type {ControlActions} */ (
					/** @type {unknown} */ ( controller )
				)[ action ]( eventObject );
			}
		};
		root.addEventListener( event, listener );
		registered.push( [ event, listener ] );
	}
	const detachLines = root.classList.contains( 'quiz-matching-options' )
		? attachMatchingLines( root )
		: () => {};
	return () => {
		detachLines();
		registered.forEach( ( [ event, listener ] ) =>
			root.removeEventListener( event, listener )
		);
	};
}
const attachStaticMatching = () =>
	/** @type {HTMLElement[]} */ ( [
		...document.querySelectorAll( '.quiz-matching-options' ),
	] ).forEach( attachMatchingLines );
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', attachStaticMatching );
} else {
	attachStaticMatching();
}
registerQuestionMount( 'matching', mountDynamic );
registerQuestionMount( 'reorder', mountDynamic );

// Visual widgets mark themselves touched on the first change (see interactive-visual.js).
[
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
].forEach( ( type ) =>
	registerAnswerValidator( type, ( root ) =>
		Boolean( root.querySelector( '[data-touched]' ) )
	)
);

// Tile answers are hidden inputs added by interactive-controls.js.
registerAnswerValidator( 'build-expression', ( root ) =>
	Boolean( root.querySelector( 'input[data-tile-answer]' ) )
);

// Extended widgets write bounded scalar fields through the same answer collection contract.
registerAnswerValidator( 'match-table-grid', ( root ) => {
	const widget = root.querySelector(
		'[data-ohmylms-interactive="match-table-grid"]'
	);
	if ( ! widget ) {
		return false;
	}
	try {
		const config = JSON.parse(
			/** @type {string} */ ( widget.getAttribute( 'data-config' ) )
		);
		const answers = Array.from(
			/** @type {Iterable<HTMLInputElement>} */ (
				widget.querySelectorAll( 'input[data-answer-key]' )
			)
		).map( ( input ) => ( {
			id: input.getAttribute( 'data-answer-key' ),
			value: input.value,
		} ) );
		return gridRowsAnswered( config.rows || [], answers );
	} catch {
		return false;
	}
} );
[
	'passage',
	'graphing',
	'hot-text',
	'labeling',
	'hotspot',
	'draw',
	'audio-response',
	'video-response',
	'poll',
	'word-cloud',
	'discussion-board',
	'slide',
	'interactive-video',
	'spin-wheel',
	'gameshow',
	'hangman',
	'open-box',
	'speaking-cards',
	'flip-tiles',
	'flashcards',
	'timed-true-false',
	'whack-a-mole',
	'flying-fruit',
].forEach( ( type ) =>
	registerAnswerValidator( type, ( root ) =>
		Array.from(
			/** @type {Iterable<HTMLInputElement>} */ (
				root.querySelectorAll( 'input[data-answer-key]' )
			)
		).some( ( input ) => input.value.trim() !== '' )
	)
);

// Structured parts keep the existing leaf validators and dynamic mount behavior.
registerAnswerValidator( 'structured', ( root ) => {
	const parts = [ ...root.querySelectorAll( '.ohmylms-structured-part' ) ];
	return (
		parts.length > 0 &&
		parts.every( ( part ) => {
			const child = /** @type {HTMLElement|null} */ (
				part.querySelector( '.ohmylms-structured-child' )
			);
			return child
				? isAnswered( child, child.dataset.partType )
				: [
						.../** @type {Iterable<HTMLInputElement|HTMLTextAreaElement>} */ (
							part.querySelectorAll( 'input,textarea' )
						),
					].some( ( field ) => field.value.trim() !== '' );
		} )
	);
} );
registerQuestionMount( 'structured', ( root, detail ) => {
	const cleanups = /** @type {Array<() => void>} */ (
		[
			.../** @type {Iterable<HTMLElement>} */ (
				root.querySelectorAll( '.ohmylms-structured-child' )
			),
		]
			.map( ( child ) =>
				mountQuestion( child, child.dataset.partType, detail )
			)
			.filter( Boolean )
	);
	return () => cleanups.forEach( ( cleanup ) => cleanup() );
} );

for ( const type of [ 'crossword', 'wordsearch' ] ) {
	registerAnswerValidator( type, ( root ) =>
		[
			.../** @type {Iterable<HTMLInputElement>} */ (
				root.querySelectorAll( '[data-answer-key]' )
			),
		].some( ( input ) => input.value.trim() !== '' )
	);
}
