import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { loadCourse, saveCourseWithChapters, updateCourse } from './api.mjs';
import {
	prepareCoursePayload,
	orderedChapters,
	completedCourseSteps,
	mergeSavedCourse,
} from './model.mjs';
export function useCourseEditor( {
	store,
	courseId,
	enableSpin,
	manageStructure = false,
} ) {
	const state = useSelect(
		( select ) => {
			const data = select( store );
			return {
				course: data.getCourse(),
				chapters: data.getCourseChapters(),
				validSettings: data.isValidCourseSettings(),
				integrations: data.getAllIntegrations(),
				notice: data.getNotificationMessage(),
				noticeStatus: data.getNotificationStatus(),
			};
		},
		[ store ]
	);
	const actions = useDispatch( store );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ autoSave, setAutoSave ] = useState( false );
	const [ savedKey, setSavedKey ] = useState( null );
	const [ failedKey, setFailedKey ] = useState( null );
	const [ savedAt, setSavedAt ] = useState( null );
	const inFlight = useRef( false );
	const generation = useRef( 0 );
	// What a save would write: the title, the description and the units with their content.
	const currentKey = useMemo(
		() =>
			JSON.stringify( [
				state.course?.name,
				state.course?.description,
				manageStructure ? null : orderedChapters( state.chapters ),
			] ),
		[
			state.course?.name,
			state.course?.description,
			state.chapters,
			manageStructure,
		]
	);
	const dirty = ! loading && savedKey !== null && savedKey !== currentKey;
	const latestKey = useRef( currentKey );
	latestKey.current = currentKey;
	const saveLatest = useRef( null );
	const latestCourse = useRef( state.course );
	latestCourse.current = state.course;
	useEffect( () => {
		const current = ++generation.current;
		let active = true;
		setError( null );
		setSaving( false );
		inFlight.current = false;
		if ( courseId && ! enableSpin ) {
			setLoading( true );
			actions.resetCourseState();
			actions.setCourseLoading( true );
			loadCourse( courseId )
				.then( ( course ) => {
					if ( ! active || generation.current !== current ) {
						return;
					}
					actions.setCourse( course );
					for ( const chapter of course.chapters || [] ) {
						actions.addChapterToCourse( courseId, chapter );
						actions.setContentsToChapter(
							chapter.id,
							chapter.contents || []
						);
					}
					actions.getTags( '' );
					actions.getCategories( '' );
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setError(
							cause.message ||
								__( 'Could not load course.', 'ohmylms' )
						);
					}
				} )
				.finally( () => {
					if ( active ) {
						setLoading( false );
						actions.setCourseLoading( false );
					}
				} );
		}
		return () => {
			active = false;
			generation.current = current + 1;
			actions.resetCourseState();
		};
	}, [ store, courseId, enableSpin, actions ] );
	async function save( status, date, section, { silent = false } = {} ) {
		if ( inFlight.current || loading || ! state.course ) {
			return false;
		}
		const current = generation.current;
		const submittedKey = latestKey.current;
		inFlight.current = true;
		setSaving( true );
		setError( null );
		try {
			const saved = manageStructure
				? await updateCourse(
						courseId,
						prepareCoursePayload( state.course, { status, date } )
					)
				: await saveCourseWithChapters(
						courseId,
						prepareCoursePayload( state.course, {
							status,
							date,
						} ),
						orderedChapters( state.chapters )
					);
			if ( current !== generation.current ) {
				return false;
			}
			actions.setCourse(
				mergeSavedCourse( saved, state.course, latestCourse.current )
			);
			setSavedKey( submittedKey );
			setFailedKey( null );
			setSavedAt( new Date() );
			if ( ! silent ) {
				const message =
					section === 'settings'
						? __(
								'Course settings have been saved successfully',
								'ohmylms'
							)
						: ( () => {
								if ( status === 'publish' ) {
									if ( state.course.status === 'publish' ) {
										return __(
											'Course has been updated successfully',
											'ohmylms'
										);
									}
									return __(
										'Course has been published successfully',
										'ohmylms'
									);
								}
								return ( () => {
									if ( status === 'draft' ) {
										return __(
											'Course has been saved as draft successfully',
											'ohmylms'
										);
									}
									if ( status === 'future' ) {
										return __(
											'Course has been scheduled successfully',
											'ohmylms'
										);
									}
									return __(
										'Course has been saved successfully',
										'ohmylms'
									);
								} )();
							} )();
				actions.showNotification( message, 'success' );
			}
			return true;
		} catch ( cause ) {
			if ( current === generation.current ) {
				setFailedKey( submittedKey );
				setError(
					cause.message || __( 'Could not save course.', 'ohmylms' )
				);
			}
			return false;
		} finally {
			if ( current === generation.current ) {
				inFlight.current = false;
				setSaving( false );
				setAutoSave( false );
			}
		}
	}
	saveLatest.current = save;
	// The baseline is whatever was loaded, so only later edits count as unsaved.
	useEffect( () => {
		if ( ! loading ) {
			setSavedKey( latestKey.current );
		}
	}, [ loading, courseId ] );
	// Drafts save themselves shortly after an edit; a live course is never changed silently.
	useEffect( () => {
		const editable = [ 'draft', 'auto-draft', 'pending' ].includes(
			state.course?.status
		);
		if (
			! dirty ||
			saving ||
			! editable ||
			failedKey === currentKey ||
			enableSpin
		) {
			return undefined;
		}
		const timer = setTimeout( () => {
			saveLatest.current( 'draft', undefined, undefined, {
				silent: true,
			} );
		}, 800 );
		return () => clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Restart the timer on each edit.
	}, [ dirty, currentKey, saving, failedKey, state.course?.status ] );
	// Never lose edits to a tab close or a full-page jump (the quiz and lesson editors are separate pages).
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );
	useEffect( () => {
		if ( autoSave && ! loading ) {
			saveLatest.current( undefined, undefined, undefined, {
				silent: true,
			} );
		}
	}, [ autoSave, state.course, state.chapters, loading ] );
	async function toggleCommunity( enabled ) {
		try {
			await updateCourse( courseId, {
				has_community: enabled ? 'yes' : 'no',
				name: state.course.name || 'Untitled',
			} );
			actions.setCourse( {
				has_community: enabled ? 'yes' : 'no',
			} );
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not update community.', 'ohmylms' )
			);
		}
	}
	return {
		...state,
		actions,
		loading,
		saving,
		dirty,
		savedAt,
		error,
		save,
		refreshStructure: async () => {
			const fresh = await loadCourse( courseId );
			for ( const chapter of orderedChapters( state.chapters ) ) {
				actions.removeChapterFromCourse( chapter.id );
			}
			for ( const chapter of fresh.chapters || [] ) {
				actions.addChapterToCourse( courseId, chapter );
				actions.setContentsToChapter(
					chapter.id,
					chapter.contents || []
				);
			}
			actions.setCourse( {
				...fresh,
				...latestCourse.current,
				chapters: fresh.chapters,
			} );
		},
		setAutoSave,
		toggleCommunity,
		completedSteps: completedCourseSteps(
			state.course,
			state.chapters,
			state.validSettings
		),
		updateTitle: ( name ) => {
			if ( name.length <= 150 ) {
				actions.setCourse( {
					name,
				} );
			}
		},
		updateDescription: ( description ) =>
			actions.setCourse( {
				description,
			} ),
	};
}
