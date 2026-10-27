import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	loadCatalog,
	publishCatalog,
	saveCatalog,
} from '../content-hub/api.mjs';
import {
	addAttachments,
	removeAttachment,
	toSavePayload,
	updateAttachment,
} from '../content-hub/catalogModel.mjs';

/**
 * The catalog of the course a syllabus is: its chapters (the syllabus's skill groups), its skills, and the
 * lessons, quizzes and assignments attached to a chapter. The syllabus owns the chapters and skills, so this
 * only ever saves the attachments (never the skills). A change shows at once and the server's answer settles
 * it (a refusal puts back what the server has). Saves run one after another so two quick changes cannot cross.
 *
 * `revision` changes whenever the syllabus changed, because the course followed it and must be read again.
 * @param courseId
 * @param revision
 */
export function useCourseCatalog( courseId, revision ) {
	const [ catalog, setCatalog ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ publishErrors, setPublishErrors ] = useState( [] );
	const latest = useRef( null );
	const queue = useRef( Promise.resolve() );
	const waiting = useRef( 0 );
	const live = useRef( true );

	useEffect( () => {
		live.current = true;
		return () => {
			live.current = false;
		};
	}, [] );

	useEffect( () => {
		if ( ! courseId ) {
			latest.current = null;
			setCatalog( null );
			return undefined;
		}
		let current = true;
		queue.current = queue.current.then( () =>
			loadCatalog( courseId )
				.then( ( data ) => {
					if ( ! current || ! live.current ) {
						return;
					}
					latest.current = data;
					setCatalog( data );
					setError( '' );
				} )
				.catch( ( cause ) => {
					if ( current && live.current ) {
						setError(
							cause?.message ||
								__( 'Could not load the course.', 'ohmylms' )
						);
					}
				} )
		);
		return () => {
			current = false;
		};
	}, [ courseId, revision ] );

	/**
	 * Queue a server change; its answer is the fresh catalog. Returns whether it worked. An `optimistic` change
	 * is already on screen: the answer replaces it unless a newer one is queued behind it (which will have its
	 * own answer), and a refusal puts back what the server has.
	 */
	const enqueue = useCallback(
		( work, { optimistic = false } = {} ) => {
			if ( optimistic ) {
				waiting.current += 1;
			}
			const task = queue.current.then( async () => {
				if ( live.current ) {
					setBusy( true );
					setError( '' );
				}
				try {
					const next = await work();
					if ( optimistic ) {
						waiting.current -= 1;
					}
					if (
						next &&
						live.current &&
						! ( optimistic && waiting.current > 0 )
					) {
						latest.current = next;
						setCatalog( next );
					}
					return true;
				} catch ( cause ) {
					if ( optimistic ) {
						waiting.current -= 1;
					}
					if ( live.current ) {
						setError(
							cause?.message ||
								__(
									'That did not work. Please try again.',
									'ohmylms'
								)
						);
						if ( optimistic && waiting.current === 0 ) {
							loadCatalog( courseId )
								.then( ( data ) => {
									if ( ! live.current ) {
										return;
									}
									latest.current = data;
									setCatalog( data );
								} )
								.catch( () => null );
						}
					}
					return false;
				} finally {
					if ( live.current ) {
						setBusy( false );
					}
				}
			} );
			queue.current = task.catch( () => false );
			return task;
		},
		[ courseId ]
	);

	const saveAttachments = useCallback(
		( edit ) => {
			const base = latest.current;
			if ( ! base ) {
				return Promise.resolve( false );
			}
			// The screen follows at once; the server's answer then settles the details (identifiers, publish state).
			const next = { ...base, attachments: edit( base.attachments ) };
			latest.current = next;
			setCatalog( next );
			return enqueue(
				() =>
					saveCatalog( courseId, {
						attachments: toSavePayload( next ).attachments,
					} ),
				{ optimistic: true }
			);
		},
		[ courseId, enqueue ]
	);

	return {
		catalog,
		error,
		setError,
		busy,
		publishErrors,
		attach: ( picks, scope, options ) =>
			saveAttachments( ( current ) =>
				addAttachments( current, picks, scope, options )
			),
		updateAttachment: ( id, patch ) =>
			saveAttachments( ( current ) =>
				updateAttachment( current, id, patch )
			),
		removeAttachment: ( id ) =>
			saveAttachments( ( current ) => removeAttachment( current, id ) ),
		publish: async ( applyExisting ) => {
			setPublishErrors( [] );
			const ok = await enqueue( async () => {
				try {
					return await publishCatalog( courseId, {
						apply_existing: Boolean( applyExisting ),
					} );
				} catch ( cause ) {
					if ( live.current ) {
						setPublishErrors( cause?.data?.errors || [] );
					}
					throw cause;
				}
			} );
			return ok;
		},
	};
}
