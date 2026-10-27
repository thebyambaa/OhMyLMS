import { isAnswered, mountQuestion } from 'ohmylms/interactivity';
import 'ohmylms/questions';
import { loadMath, mathEnabled } from '../src/features/math/loader.mjs';

const { __, sprintf } = window.wp.i18n;

/**
 * Mount one independent bank-question player using the shared question controls.
 *
 * @param {HTMLElement} root Block instance.
 */
export function mountPlayer( root ) {
	const config = JSON.parse( root.dataset.playerConfig );
	const stage = root.querySelector( '.ohmylms-question-player-stage' );
	const error = root.querySelector( '.ohmylms-question-player-error' );
	const start = root.querySelector( '.ohmylms-question-player-start' );
	let session = '';
	let busy = false;
	let cleanups = [];
	const remember = ( value ) => {
		try {
			if ( value !== undefined ) {
				sessionStorage.setItem( config.storage, value );
			}
			return sessionStorage.getItem( config.storage ) || '';
		} catch {
			return '';
		}
	};
	const message = ( text = '' ) => {
		error.textContent = text;
		error.hidden = ! text;
	};
	const request = async ( operation, form = null, extra = {} ) => {
		if ( busy ) {
			return;
		}
		busy = true;
		const focusedCard = document.activeElement?.dataset?.cardId;
		message();
		root.setAttribute( 'aria-busy', 'true' );
		start.disabled = true;
		if ( form ) {
			form.inert = true;
		}
		try {
			const body = new URLSearchParams(
				form ? new FormData( form ) : undefined
			);
			body.set( 'operation', operation );
			body.set( 'session', session );
			for ( const [ key, value ] of Object.entries( extra ) ) {
				body.set( key, value );
			}
			const response = await fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce },
				body,
			} );
			const data = await response.json();
			if ( ! response.ok ) {
				throw new Error(
					data.message ||
						__( 'Could not load these questions.', 'ohmylms' )
				);
			}
			session = data.session;
			remember( session );
			await render( data );
			if ( focusedCard ) {
				const cards = [ ...stage.querySelectorAll( '[data-card-id]' ) ];
				const target =
					cards.find(
						( card ) =>
							card.dataset.cardId === focusedCard &&
							! card.disabled
					) || cards.find( ( card ) => ! card.disabled );
				(
					target || stage.querySelector( 'button:not(:disabled)' )
				)?.focus();
			}
		} catch ( failure ) {
			message(
				failure.message ||
					__(
						'Could not save your responses. Please retry.',
						'ohmylms'
					)
			);
			if ( operation === 'resume' ) {
				remember( '' );
				start.hidden = false;
			}
		} finally {
			busy = false;
			start.disabled = false;
			if ( form ) {
				form.inert = false;
			}
			root.removeAttribute( 'aria-busy' );
		}
	};
	const element = ( tag, text, className = '' ) => {
		const node = document.createElement( tag );
		node.textContent = text;
		if ( className ) {
			node.className = className;
		}
		return node;
	};
	const button = ( label, action ) => {
		const node = element( 'button', label );
		node.type = 'button';
		node.addEventListener( 'click', action );
		return node;
	};
	const render = async ( data ) => {
		cleanups.forEach( ( cleanup ) => cleanup() );
		cleanups = [];
		stage.replaceChildren();
		start.hidden = true;
		if ( data.preview ) {
			stage.append(
				element(
					'p',
					__(
						'Instructor preview — responses do not affect student grades or skill performance.',
						'ohmylms'
					),
					'ohmylms-question-player-preview'
				)
			);
		}
		if ( data.gameFamily ) {
			const moduleUrl = new URL( './game-player.mjs', import.meta.url );
			moduleUrl.search = new URL( import.meta.url ).search;
			const { renderGame } = await import( moduleUrl.href );
			cleanups.push(
				renderGame(
					stage,
					data,
					( card, value = '' ) =>
						request( 'study', null, {
							card,
							value,
							revision: data.revision,
						} ),
					() => request( 'start' )
				)
			);
			return;
		}
		if ( [ 'hangman', 'spell-word' ].includes( data.activityMode ) ) {
			const { renderWord } = await import( './word-player.js' );
			renderWord(
				stage,
				data,
				( card, value ) =>
					request( 'study', null, {
						card,
						value,
						revision: data.revision,
					} ),
				() => request( 'start' )
			);
			stage.querySelector( 'input, button' )?.focus();
			return;
		}
		if ( data.activityMode === 'image-quiz' ) {
			const { renderImageQuiz } = await import( './image-player.js' );
			cleanups.push(
				renderImageQuiz(
					stage,
					data,
					( card, value = '' ) =>
						request( 'study', null, {
							card,
							value,
							revision: data.revision,
						} ),
					() => request( 'start' )
				)
			);
			return;
		}
		if ( data.activityMode === 'memory-recall' ) {
			const { renderRecall } = await import( './recall-player.js' );
			cleanups.push(
				renderRecall(
					stage,
					data,
					( card, value = '' ) =>
						request( 'study', null, {
							card,
							value,
							revision: data.revision,
						} ),
					() => request( 'start' )
				)
			);
			return;
		}
		if ( data.activityMode ) {
			const { renderStudy } = await import( './study-player.js' );
			renderStudy(
				stage,
				data,
				( card, revision ) =>
					request( 'study', null, { card, revision } ),
				() => request( 'start' )
			);
			return;
		}
		if ( data.status === 'complete' ) {
			const heading = element(
				'h3',
				__( 'Activity complete', 'ohmylms' )
			);
			heading.tabIndex = -1;
			stage.append( heading );
			if ( data.score ) {
				stage.append(
					element(
						'p',
						sprintf(
							/* translators: 1: earned marks, 2: maximum marks. */ __(
								'Score: %1$s / %2$s',
								'ohmylms'
							),
							data.score.earned,
							data.score.maximum
						),
						'ohmylms-question-player-score'
					)
				);
				const list = document.createElement( 'ol' );
				data.results.forEach( ( result ) =>
					list.append(
						element(
							'li',
							result.correct
								? __( 'Correct', 'ohmylms' )
								: sprintf(
										/* translators: %s: percentage credit for a response. */ __(
											'Credit: %s%%',
											'ohmylms'
										),
										Math.round( result.fraction * 100 )
									)
						)
					)
				);
				stage.append( list );
			} else {
				stage.append(
					element(
						'p',
						__(
							'Responses submitted. This activity was not graded.',
							'ohmylms'
						)
					)
				);
			}
			stage.append(
				button( __( 'Play again', 'ohmylms' ), () =>
					request( 'start' )
				)
			);
			heading.focus();
			return;
		}
		const form = document.createElement( 'form' );
		form.noValidate = true;
		const progress = element( 'p', '', 'ohmylms-question-player-progress' );
		progress.setAttribute( 'aria-live', 'polite' );
		const bar = document.createElement( 'progress' );
		bar.max = data.questions.length;
		bar.setAttribute( 'aria-label', __( 'Question progress', 'ohmylms' ) );
		form.append( progress, bar );
		const questions = data.questions.map( ( question, index ) => {
			const article = document.createElement( 'article' );
			article.className = 'ohmylms-question-player-question';
			article.tabIndex = -1;
			article.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %d: question number. */ __(
						'Question %d',
						'ohmylms'
					),
					index + 1
				)
			);
			// HTML comes exclusively from the authorized server's learner-safe question renderer.
			article.innerHTML = question.html;
			form.append( article );
			window.OhMyLMSInteractive?.enhance( article );
			const cleanup = mountQuestion( article, question.type, {} );
			if ( cleanup ) {
				cleanups.push( cleanup );
			}
			return article;
		} );
		let page = 0;
		const navigation = document.createElement( 'div' );
		navigation.className = 'ohmylms-question-player-navigation';
		const previous = button( __( 'Previous', 'ohmylms' ), () =>
			show( page - 1 )
		);
		const next = button( __( 'Next', 'ohmylms' ), () => {
			if ( ! valid( page ) ) {
				return;
			}
			show( page + 1 );
		} );
		const submit = element( 'button', __( 'Submit responses', 'ohmylms' ) );
		submit.type = 'submit';
		const valid = ( index ) => {
			const question = data.questions[ index ];
			if (
				question.required &&
				! isAnswered( questions[ index ], question.type )
			) {
				show( index );
				message(
					__(
						'Answer this required question before continuing.',
						'ohmylms'
					)
				);
				return false;
			}
			message();
			return true;
		};
		const show = ( index, focus = true ) => {
			page = Math.max( 0, Math.min( questions.length - 1, index ) );
			questions.forEach( ( question, position ) => {
				question.hidden = position !== page;
			} );
			previous.disabled = page === 0;
			next.hidden = page === questions.length - 1;
			submit.hidden = ! next.hidden;
			progress.textContent = sprintf(
				/* translators: 1: current question, 2: question count. */ __(
					'Question %1$d of %2$d',
					'ohmylms'
				),
				page + 1,
				questions.length
			);
			bar.value = page + 1;
			if ( focus ) {
				questions[ page ]?.focus();
			}
		};
		navigation.append( previous, next, submit );
		form.append( navigation );
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			if (
				data.questions.every( ( question, index ) => valid( index ) )
			) {
				request( 'submit', form );
			}
		} );
		stage.append( form );
		if ( mathEnabled() ) {
			loadMath()
				.then( ( math ) => {
					const cleanup = math.mount( form );
					if ( typeof cleanup === 'function' ) {
						cleanups.push( cleanup );
					}
				} )
				.catch( () =>
					message(
						__(
							'Math controls could not load. Reload the page to retry.',
							'ohmylms'
						)
					)
				);
		}
		show( 0 );
	};
	start.addEventListener( 'click', () => request( 'start' ) );
	session = remember();
	if ( session ) {
		request( 'resume' );
	}
}

document
	.querySelectorAll( '.ohmylms-question-player[data-player-config]' )
	.forEach( ( root, index ) => {
		// Identical placements on one page still keep separate browser sessions.
		const config = JSON.parse( root.dataset.playerConfig );
		config.storage += '-' + index;
		root.dataset.playerConfig = JSON.stringify( config );
		mountPlayer( root );
	} );
