import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { previewQuestion } from './api.mjs';

/**
 * A preview is a whole student page in a frame. A long quiz would start dozens at once and
 * overwhelm the server, so only a few load at a time; the rest wait their turn.
 */
const MAX_LOADING = 2;
let loading = 0;
/** @type {Array<() => void>} */
const waiting = [];

/**
 * Wait for a loading slot.
 *
 * @return {Promise<() => void>} Resolves with the function that gives the slot back (safe to call twice).
 */
export function previewSlot() {
	return new Promise( ( resolve ) => {
		const grant = () => {
			loading++;
			let held = true;
			resolve( () => {
				if ( held ) {
					held = false;
					loading--;
					waiting.shift()?.();
				}
			} );
		};
		if ( loading < MAX_LOADING ) {
			grant();
		} else {
			waiting.push( grant );
		}
	} );
}

/**
 * Exact student rendering of an unselected, unsaved question in an isolated frame. The frame is
 * requested when the block comes near the screen; until then the block shows the question's text.
 *
 * @param {Object}                                     props          Properties.
 * @param {import('./choiceAutosave.mjs').ChoiceDraft} props.question Current question, including unsaved edits.
 * @param {string}                                     props.clientId Native block ID.
 * @return {React.ReactElement} Student document with an editing action over it.
 */
export function QuestionBlockPreview( { question, clientId } ) {
	const { selectBlock } = useDispatch( 'core/block-editor' );
	const documentSettings = useSelect(
		( store ) => {
			const editor = store( 'core/editor' );
			const quiz = editor.getEditedPostAttribute( 'ohmylms_quiz' ) || {};
			/**
			 * @param {import('@wordpress/blocks').BlockInstance[]} blocks Blocks.
			 * @return {string[]} Client IDs of the question blocks among them.
			 */
			const collect = ( blocks ) =>
				blocks.flatMap( ( block ) => [
					...( [
						'ohmylms/shared-multiple-choice',
						'ohmylms/quiz-question-reference',
					].includes( block.name )
						? [ block.clientId ]
						: [] ),
					...collect( block.innerBlocks || [] ),
				] );
			const blocks = collect( store( 'core/block-editor' ).getBlocks() );
			return {
				post_id: editor.getCurrentPostId(),
				ordinal: Math.max( 1, blocks.indexOf( clientId ) + 1 ),
				question_count: Math.max( 1, blocks.length ),
				settings: quiz.settings || {},
				kind: quiz.assessment?.kind || 'quiz',
			};
		},
		[ clientId ]
	);
	const source = JSON.stringify( { ...documentSettings, question } );
	const seed = useRef( Math.floor( Math.random() * 2147483647 ) );
	const [ preview, setPreview ] = useState(
		/** @type {string|null} */ ( null )
	);
	const [ error, setError ] = useState( '' );
	const [ height, setHeight ] = useState( 160 );
	const observer = useRef( /** @type {ResizeObserver|null} */ ( null ) );
	const holder = useRef( /** @type {HTMLDivElement|null} */ ( null ) );
	const slot = useRef( /** @type {(() => void)|null} */ ( null ) );
	const [ near, setNear ] = useState( false );
	useEffect( () => {
		const node = holder.current;
		const view = node?.ownerDocument.defaultView;
		if ( near || ! node ) {
			return undefined;
		}
		if ( ! view?.IntersectionObserver ) {
			setNear( true );
			return undefined;
		}
		const watcher = new view.IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					setNear( true );
				}
			},
			{ rootMargin: '400px 0px' }
		);
		watcher.observe( node );
		return () => watcher.disconnect();
	}, [ near ] );
	useEffect( () => {
		if ( ! near ) {
			return undefined;
		}
		let active = true;
		const controller = new AbortController();
		/** @type {(() => void)|null} */
		let release = null;
		setPreview( null );
		setError( '' );
		const timer = setTimeout( () => {
			previewSlot().then( ( free ) => {
				if ( ! active ) {
					free();
					return;
				}
				release = free;
				slot.current = free;
				// A frame that never reports its load must not hold the slot for good.
				setTimeout( free, 10000 );
				previewQuestion(
					{ ...JSON.parse( source ), seed: seed.current },
					controller.signal
				)
					.then( ( result ) => {
						if ( active ) {
							setPreview( result.url );
						} else {
							free();
						}
					} )
					.catch( ( cause ) => {
						free();
						if ( active ) {
							setError(
								cause.message ||
									__(
										'Could not load the student preview. Open the question to try again.',
										'ohmylms'
									)
							);
						}
					} );
			} );
		}, 150 );
		return () => {
			active = false;
			clearTimeout( timer );
			controller.abort();
			release?.();
		};
	}, [ source, near ] );
	useEffect( () => () => observer.current?.disconnect(), [] );
	/** @param {import('react').SyntheticEvent<HTMLIFrameElement>} event Frame load. */
	const resize = ( event ) => {
		slot.current?.();
		observer.current?.disconnect();
		const frame = event.currentTarget;
		const content = frame.contentDocument?.querySelector(
			'.ohmylms-question-preview-content'
		);
		if ( ! content ) {
			setError(
				__(
					'The student preview expired. Open the question to refresh it.',
					'ohmylms'
				)
			);
			return;
		}
		const measure = () =>
			setHeight(
				Math.max(
					80,
					Math.ceil( content.getBoundingClientRect().height )
				)
			);
		observer.current = new ResizeObserver( measure );
		observer.current.observe( content );
		measure();
	};
	const edit = () => selectBlock( clientId, 0 );
	const summary = String( question?.name || question?.description || '' )
		.replace( /<[^>]*>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, 200 );
	return (
		<div
			className={ `ohmylms-question-block-preview${ near ? '' : ' is-waiting' }` }
			ref={ holder }
		>
			{ error && <p role="status">{ error }</p> }
			{ ! near && (
				<p className="ohmylms-question-block-summary">
					{ summary || __( 'Question', 'ohmylms' ) }
				</p>
			) }
			{ near && ! error && ! preview && <Spinner /> }
			{ preview && (
				<div inert="" aria-hidden="true">
					<iframe
						className="ohmylms-question-student-frame"
						title={ __( 'Student question preview', 'ohmylms' ) }
						src={ preview }
						tabIndex={ -1 }
						height={ height }
						onLoad={ resize }
					/>
				</div>
			) }
			<button
				type="button"
				className="ohmylms-question-block-edit"
				onClick={ edit }
				onFocus={ edit }
			>
				<span className="ohmylms-question-block-edit-label">
					{ __( 'Edit question', 'ohmylms' ) }
				</span>
			</button>
		</div>
	);
}
