import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { getSettings, saveSettings } from './api';
import {
	buildPatch,
	changedFields,
	describeFailure,
	validateGroup,
} from './model';
import type {
	FailureView,
	FieldErrors,
	FieldName,
	GroupId,
	GroupValues,
	SettingValue,
} from './types';

/** State and actions of one settings tab. */
export interface SettingsGroupState< G extends GroupId > {
	/** Values as last stored; `null` until the first load succeeds. */
	saved: GroupValues< G > | null;
	/** Values being edited. */
	draft: GroupValues< G > | null;
	loading: boolean;
	/** True once values have been loaded. */
	loaded: boolean;
	saving: boolean;
	/** Why loading failed (retry with `reload`). */
	loadError: FailureView | null;
	/** Why the last save failed. */
	saveError: FailureView | null;
	/** Per-field messages, from client validation or the server. */
	errors: FieldErrors;
	/** Names of the fields that differ from the stored values. */
	changed: string[];
	dirty: boolean;
	/** Names the server reported as changed by the last save, or `null` before any save. */
	lastSaved: string[] | null;
	setField: ( name: FieldName< G >, value: SettingValue ) => void;
	save: () => Promise< boolean >;
	/** Drop unsaved edits. */
	reset: () => void;
	reload: () => void;
	/** Adopt values the server returned from another action (for example restoring pages). */
	adopt: ( values: GroupValues< G > ) => void;
}

/**
 * Loads one group when `active` first becomes true, keeps a draft, validates it like the server
 * and saves only the changed fields. Requests are cancelled on unmount, responses that arrive
 * after cancellation are ignored, and a second save while one is pending is ignored.
 *
 * @param group  Group id.
 * @param active Whether the tab is selected; nothing is requested before the first time.
 * @return State and actions.
 */
export function useSettingsGroup< G extends GroupId >(
	group: G,
	active: boolean
): SettingsGroupState< G > {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ saved, setSaved ] = useState< GroupValues< G > | null >( null );
	const [ draft, setDraft ] = useState< GroupValues< G > | null >( null );
	const [ loading, setLoading ] = useState( active );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ saveError, setSaveError ] = useState< FailureView | null >( null );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ lastSaved, setLastSaved ] = useState< string[] | null >( null );
	const pending = useRef< AbortController | null >( null );

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
		getSettings( group, controller.signal ).then(
			( response ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setSaved( response.values );
				setDraft( response.values );
				setLoading( false );
			},
			( failure: unknown ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setLoadError( describeFailure( normalizeApiError( failure ) ) );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ group, requested, version ] );

	useEffect( () => () => pending.current?.abort(), [] );

	const setField = useCallback(
		( name: FieldName< G >, value: SettingValue ) => {
			setDraft( ( current ) =>
				current ? { ...current, [ name ]: value } : current
			);
			setErrors( ( current ) => {
				if ( ! ( name in current ) ) {
					return current;
				}
				const next = { ...current };
				delete next[ name ];
				return next;
			} );
		},
		[]
	);

	const save = useCallback( async (): Promise< boolean > => {
		if ( ! saved || ! draft || pending.current ) {
			return false;
		}
		const invalid = validateGroup( group, draft );
		if ( Object.keys( invalid ).length ) {
			setErrors( invalid );
			setSaveError( {
				message: __( 'Fix the highlighted fields.', 'ohmylms' ),
				permission: false,
				fieldErrors: {},
			} );
			return false;
		}
		const patch = buildPatch( saved, draft );
		if ( ! Object.keys( patch ).length ) {
			return true;
		}
		const controller = new AbortController();
		pending.current = controller;
		setSaving( true );
		setSaveError( null );
		setErrors( {} );
		try {
			const response = await saveSettings(
				group,
				patch,
				controller.signal
			);
			if ( controller.signal.aborted ) {
				return false;
			}
			setSaved( response.values );
			setDraft( response.values );
			setLastSaved( response.changed );
			return true;
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				const view = describeFailure( normalizeApiError( failure ) );
				setSaveError( view );
				setErrors( view.fieldErrors );
			}
			return false;
		} finally {
			if ( ! controller.signal.aborted ) {
				pending.current = null;
				setSaving( false );
			}
		}
	}, [ group, saved, draft ] );

	const changed = saved && draft ? changedFields( saved, draft ) : [];

	return {
		saved,
		draft,
		loading,
		loaded: null !== saved,
		saving,
		loadError,
		saveError,
		errors,
		changed,
		dirty: changed.length > 0,
		lastSaved,
		setField,
		save,
		reset: useCallback( () => {
			setDraft( saved );
			setErrors( {} );
			setSaveError( null );
		}, [ saved ] ),
		reload: useCallback( () => setVersion( ( n ) => n + 1 ), [] ),
		adopt: useCallback(
			( values: GroupValues< G > ) => {
				const edited =
					saved && draft ? changedFields( saved, draft ) : [];
				setSaved( values );
				// Keep what the user typed; only untouched fields take the server's values.
				setDraft( ( current ) => {
					const next = { ...values };
					for ( const name of edited ) {
						const key = name as FieldName< G >;
						if ( current ) {
							next[ key ] = current[ key ];
						}
					}
					return next;
				} );
				setErrors( {} );
			},
			[ saved, draft ]
		),
	};
}
