import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { normalizeApiError } from '../../lib/api';
import {
	completeSetup,
	dismissSetup,
	getSetup,
	resetSetup,
	saveStep,
	skipStep,
} from './api';
import { describeFailure } from './model';
import type {
	FailureView,
	SetupState,
	StepId,
	StepInput,
	StepResult,
} from './types';

/** State and actions of the wizard. */
export interface SetupController {
	/** Latest server state; `null` until the first load succeeds. */
	setup: SetupState | null;
	loading: boolean;
	loadError: FailureView | null;
	/** True while an action request is pending (further actions are ignored). */
	busy: boolean;
	/** Why the last action failed. */
	actionError: FailureView | null;
	/** Result of the last successful step save. */
	lastResult: StepResult | null;
	clearError: () => void;
	reload: () => void;
	save: ( step: StepId, input: StepInput ) => Promise< boolean >;
	skip: ( step: StepId ) => Promise< boolean >;
	complete: () => Promise< boolean >;
	dismiss: ( step: StepId | null ) => Promise< boolean >;
	reset: () => Promise< boolean >;
}

/**
 * Loads the wizard and runs its actions one at a time. A second action while one is pending is
 * ignored (duplicate-submit protection), requests are cancelled on unmount and responses that
 * arrive after cancellation are discarded.
 *
 * @return State and actions.
 */
export function useSetup(): SetupController {
	const [ version, setVersion ] = useState( 0 );
	const [ setup, setSetup ] = useState< SetupState | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState< FailureView | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ actionError, setActionError ] = useState< FailureView | null >(
		null
	);
	const [ lastResult, setLastResult ] = useState< StepResult | null >( null );
	const pending = useRef< AbortController | null >( null );

	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setLoadError( null );
		getSetup( controller.signal ).then(
			( state ) => {
				if ( ! controller.signal.aborted ) {
					setSetup( state );
					setLoading( false );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setLoadError(
						describeFailure( normalizeApiError( failure ) )
					);
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ version ] );

	useEffect( () => () => pending.current?.abort(), [] );

	const run = useCallback(
		async < T >(
			work: ( signal: AbortSignal ) => Promise< T >,
			apply: ( value: T ) => void
		): Promise< boolean > => {
			if ( pending.current ) {
				return false;
			}
			const controller = new AbortController();
			pending.current = controller;
			setBusy( true );
			setActionError( null );
			try {
				const value = await work( controller.signal );
				if ( controller.signal.aborted ) {
					return false;
				}
				apply( value );
				return true;
			} catch ( failure ) {
				if ( ! controller.signal.aborted ) {
					setActionError(
						describeFailure( normalizeApiError( failure ) )
					);
				}
				return false;
			} finally {
				if ( ! controller.signal.aborted ) {
					pending.current = null;
					setBusy( false );
				}
			}
		},
		[]
	);

	const adopt = useCallback( ( state: SetupState ) => setSetup( state ), [] );

	return {
		setup,
		loading,
		loadError,
		busy,
		actionError,
		lastResult,
		clearError: useCallback( () => setActionError( null ), [] ),
		reload: useCallback( () => setVersion( ( n ) => n + 1 ), [] ),
		save: useCallback(
			( step, input ) =>
				run(
					( signal ) => saveStep( step, input, signal ),
					( response ) => {
						setSetup( response.state );
						setLastResult( response.result );
					}
				),
			[ run ]
		),
		skip: useCallback(
			( step ) =>
				run(
					( signal ) => skipStep( step, signal ),
					( response ) => {
						setSetup( response.state );
						setLastResult( null );
					}
				),
			[ run ]
		),
		complete: useCallback(
			() => run( completeSetup, adopt ),
			[ run, adopt ]
		),
		dismiss: useCallback(
			( step ) =>
				run( ( signal ) => dismissSetup( step, signal ), adopt ),
			[ run, adopt ]
		),
		reset: useCallback( () => run( resetSetup, adopt ), [ run, adopt ] ),
	};
}
