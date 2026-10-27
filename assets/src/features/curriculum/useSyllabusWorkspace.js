import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import * as api from './api.mjs';
import { buildOutline } from './workspace.mjs';

const pick = ( response ) => ( {
	syllabus: response.syllabus,
	contents: response.contents,
	totals: response.totals,
	root_skill: response.root_skill,
	course: response.course ?? null,
	settings: response.settings ?? {},
	roadmap_sources: response.roadmap_sources ?? [],
} );

/**
 * Load a syllabus (its outline and the curriculum items) and run guarded changes against it. Every change
 * is saved on the server first and the screen follows what the server answers, so what is shown is what is
 * stored. A syllabus is also a course: a change that touches the course bumps `revision`, which the course
 * catalog watches to reload.
 * @param syllabusId
 */
export function useSyllabusWorkspace( syllabusId ) {
	const [ outline, setOutline ] = useState( null );
	const [ items, setItems ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ pending, setPending ] = useState( false );
	const [ revision, setRevision ] = useState( 0 );
	const busy = useRef( false );
	const live = useRef( true );

	const say = useCallback(
		( kind, text ) =>
			setNotice( { id: Date.now() + Math.random(), kind, text } ),
		[]
	);

	/** Take the outline, the item tree and the course summary a response carries. */
	const adopt = useCallback(
		( response ) => {
			if ( ! live.current || ! response ) {
				return;
			}
			if ( response.contents ) {
				setOutline( pick( response ) );
			}
			if ( response.items ) {
				setItems( response.items );
			}
			if ( response.course !== undefined ) {
				setRevision( ( value ) => value + 1 );
			}
			if ( response.course_error ) {
				say( 'error', response.course_error );
			}
		},
		[ say ]
	);

	const load = useCallback( async () => {
		setError( '' );
		try {
			const [ loaded, tree ] = await Promise.all( [
				api.loadSyllabus( syllabusId ),
				api.loadTree(),
			] );
			if ( ! live.current ) {
				return;
			}
			setItems( tree.items || [] );
			// Every syllabus has a course. One that predates this gets its course the first time it is opened.
			const ready = loaded.course
				? loaded
				: await api.ensureCourse( syllabusId );
			if ( live.current ) {
				adopt( { ...ready, items: ready.items || tree.items } );
			}
		} catch ( cause ) {
			if ( live.current ) {
				setError(
					cause?.message ||
						__( 'Could not load the syllabus.', 'ohmylms' )
				);
			}
		}
	}, [ syllabusId, adopt ] );

	useEffect( () => {
		live.current = true;
		setOutline( null );
		load();
		return () => {
			live.current = false;
		};
	}, [ load ] );

	useEffect( () => {
		if ( ! notice || notice.kind !== 'success' ) {
			return undefined;
		}
		const timer = setTimeout(
			() =>
				setNotice( ( current ) =>
					current === notice ? null : current
				),
			6000
		);
		return () => clearTimeout( timer );
	}, [ notice ] );

	/**
	 * Run one guarded change; returns the response, or null after showing the error. With `refresh` the outline
	 * is read again afterwards, for changes made through the item API (which answers with the item tree only).
	 */
	const run = useCallback(
		async ( work, success, { refresh = false } = {} ) => {
			if ( busy.current ) {
				return null;
			}
			busy.current = true;
			setPending( true );
			try {
				let response = ( await work() ) || {};
				if ( refresh ) {
					response = {
						...response,
						...( await api.loadSyllabus( syllabusId ) ),
					};
				}
				adopt( response );
				if ( success ) {
					say(
						'success',
						typeof success === 'function'
							? success( response )
							: success
					);
				}
				return response;
			} catch ( cause ) {
				say(
					'error',
					cause?.message ||
						__(
							'The change could not be saved. Nothing was changed.',
							'ohmylms'
						)
				);
				return null;
			} finally {
				busy.current = false;
				if ( live.current ) {
					setPending( false );
				}
			}
		},
		[ syllabusId, adopt, say ]
	);

	const tree = useMemo( () => buildOutline( outline ), [ outline ] );

	return {
		outline,
		items,
		tree,
		error,
		notice,
		dismiss: () => setNotice( null ),
		say,
		pending,
		run,
		adopt,
		reload: load,
		revision,
	};
}
