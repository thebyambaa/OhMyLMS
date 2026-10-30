import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import { getEngagement, saveEngagement } from './engagementApi';
import {
	describeEngagementFailure,
	engagementPayload,
	toEngagementDraft,
	validateEngagement,
} from './engagementModel';
import { isDirty } from './model';
import type {
	EngagementDraftMap,
	EngagementGroup,
	EngagementMap,
	FailureView,
	FieldErrors,
} from './types';

/** State and actions of one streak, XP, score or mastery group; fits `SectionFrame`. */
export interface EngagementState< G extends EngagementGroup > {
	saved: EngagementMap[ G ] | null;
	draft: EngagementDraftMap[ G ] | null;
	loading: boolean;
	loaded: boolean;
	saving: boolean;
	loadError: FailureView | null;
	saveError: FailureView | null;
	errors: FieldErrors;
	dirty: boolean;
	justSaved: boolean;
	update: (
		change: ( draft: EngagementDraftMap[ G ] ) => EngagementDraftMap[ G ]
	) => void;
	save: () => Promise< boolean >;
	reset: () => void;
	reload: () => void;
}

/**
 * Loads a group the first time `active` is true, keeps a draft, validates it like the server
 * and saves it. Requests are cancelled on unmount, late responses are ignored and a second save
 * while one is pending is ignored.
 *
 * @param group  Group id.
 * @param active Whether its tab is selected; nothing is requested before the first time.
 * @return State and actions.
 */
export function useEngagement< G extends EngagementGroup >(
	group: G,
	active: boolean
): EngagementState< G > {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ saved, setSaved ] = useState< EngagementMap[ G ] | null >( null );
	const [ draft, setDraft ] = useState< EngagementDraftMap[ G ] | null >(
		null
	);
	const [ loading, setLoading ] = useState( active );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ saveError, setSaveError ] = useState< FailureView | null >( null );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ justSaved, setJustSaved ] = useState( false );
	const pending = useRef< AbortController | null >( null );
	const busy = useRef( false );

	useEffect( () => {
		if ( active ) {
			setRequested( true );
		}
	}, [ active ] );

	useEffect( () => {
		if ( ! requested ) {
			return undefined;
		}
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		getEngagement( group, controller.signal ).then(
			( next ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setSaved( next );
				setDraft( toEngagementDraft( group, next ) );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setLoadError(
					describeEngagementFailure( normalizeApiError( failure ) )
				);
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ group, requested, version ] );

	useEffect( () => () => pending.current?.abort(), [] );

	const update = useCallback(
		(
			change: (
				current: EngagementDraftMap[ G ]
			) => EngagementDraftMap[ G ]
		) => {
			setDraft( ( current ) =>
				current ? change( current ) : current
			);
			setErrors( {} );
			setSaveError( null );
			setJustSaved( false );
		},
		[]
	);

	const save = useCallback( async (): Promise< boolean > => {
		if ( busy.current || ! draft ) {
			return false;
		}
		const found = validateEngagement( group, draft );
		if ( Object.keys( found ).length ) {
			setErrors( found );
			return false;
		}
		busy.current = true;
		const controller = new AbortController();
		pending.current = controller;
		setSaving( true );
		setSaveError( null );
		try {
			const next = await saveEngagement(
				group,
				draft,
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return false;
			}
			setSaved( next );
			setDraft( toEngagementDraft( group, next ) );
			setJustSaved( true );
			return true;
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const view = describeEngagementFailure(
					normalizeApiError( failure )
				);
				setSaveError( view );
				setErrors( view.fieldErrors );
			}
			return false;
		} finally {
			busy.current = false;
			if ( ! controller.signal.aborted ) {
				setSaving( false );
			}
		}
	}, [ draft, group ] );

	const reset = useCallback( () => {
		setDraft( saved ? toEngagementDraft( group, saved ) : null );
		setErrors( {} );
		setSaveError( null );
		setJustSaved( false );
	}, [ group, saved ] );

	return {
		saved,
		draft,
		loading,
		loaded: null !== saved,
		saving,
		loadError,
		saveError,
		errors,
		dirty: isDirty(
			saved &&
				engagementPayload( group, toEngagementDraft( group, saved ) ),
			draft && engagementPayload( group, draft )
		),
		justSaved,
		update,
		save,
		reset,
		reload: () => setVersion( ( current ) => current + 1 ),
	};
}
