import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import {
	NumberField,
	SecretField,
	SelectField,
	TextField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import { describeFailure } from './model';
import { useResource } from './useResource';
import {
	getXapi,
	runXapiAction,
	saveXapi,
	validateXapi,
	XAPI_ACTORS,
	xapiDirty,
	xapiDraft,
	type XapiAction,
	type XapiActor,
	type XapiDraft,
	type XapiSnapshot,
} from './xapi';

const ACTOR_LABELS: Record< XapiActor, string > = {
	pseudonym: __( 'Pseudonym (recommended)', 'ohmylms' ),
	user_id: __( 'WordPress user ID', 'ohmylms' ),
	mbox: __( 'Email address', 'ohmylms' ),
};

const ACTION_LABELS: Record< XapiAction, string > = {
	test: __( 'Test connection', 'ohmylms' ),
	send: __( 'Send queued statements now', 'ohmylms' ),
	retry: __( 'Retry failed statements', 'ohmylms' ),
};

/**
 * xAPI tab: LRS connection, privacy, event groups and the delivery queue (`/xapi/settings`).
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function XapiForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< XapiSnapshot, XapiDraft >(
		{
			load: getXapi,
			toDraft: xapiDraft,
			isDirty: xapiDirty,
			validate: validateXapi,
			save: async ( _stored, draft, signal ) => ( {
				snapshot: await saveXapi( draft, signal ),
				changed: [ 'xapi' ],
			} ),
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );

	const [ running, setRunning ] = useState< XapiAction | null >( null );
	const [ outcome, setOutcome ] = useState< {
		ok: boolean;
		message: string;
	} | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	async function run( action: XapiAction ) {
		setRunning( action );
		setOutcome( null );
		try {
			const result = await runXapiAction( action );
			if ( mounted.current ) {
				setOutcome( { ok: result.ok, message: result.message } );
				if ( ! state.dirty ) {
					state.adopt( result.snapshot );
				}
			}
		} catch ( failure ) {
			if ( mounted.current ) {
				setOutcome( {
					ok: false,
					message: describeFailure( normalizeApiError( failure ) )
						.message,
				} );
			}
		} finally {
			if ( mounted.current ) {
				setRunning( null );
			}
		}
	}

	const stored = state.snapshot;
	const draft = state.draft;
	const set = ( change: Partial< XapiDraft >, field: string ) =>
		state.update( ( current ) => ( { ...current, ...change } ), field );
	const lockedHelp = __(
		'Fixed in wp-config.php; change it there.',
		'ohmylms'
	);

	return (
		<FormFrame
			title={ __( 'xAPI', 'ohmylms' ) }
			description={ __(
				'Send learning events to a Learning Record Store. The LRS secret is never shown again; leave it empty to keep it.',
				'ohmylms'
			) }
			state={ state }
			savedNote={ __( 'Delivery runs in the background.', 'ohmylms' ) }
			extra={
				<>
					{ ( [ 'test', 'send', 'retry' ] as const ).map(
						( action ) => (
							<span key={ action }>
								{ ' ' }
								<Button
									variant="secondary"
									onClick={ () => void run( action ) }
									isBusy={ running === action }
									disabled={ null !== running || state.dirty }
									accessibleWhenDisabled
								>
									{ ACTION_LABELS[ action ] }
								</Button>
							</span>
						)
					) }
					{ outcome && (
						<span role={ outcome.ok ? 'status' : 'alert' }>
							{ ' ' }
							{ outcome.message }
						</span>
					) }
				</>
			}
		>
			{ stored && draft && (
				<>
					<ToggleField
						label={ __( 'Send xAPI statements', 'ohmylms' ) }
						checked={ draft.enabled }
						onChange={ ( enabled ) =>
							set( { enabled }, 'enabled' )
						}
					/>
					<TextField
						label={ __( 'LRS endpoint', 'ohmylms' ) }
						help={
							stored.locked.endpoint
								? lockedHelp
								: __(
										'For example https://lrs.example.com/xapi',
										'ohmylms'
									)
						}
						value={ draft.endpoint }
						disabled={ stored.locked.endpoint }
						error={ state.errors.endpoint }
						onChange={ ( endpoint ) =>
							set( { endpoint }, 'endpoint' )
						}
					/>
					<TextField
						label={ __( 'LRS key', 'ohmylms' ) }
						help={ stored.locked.key ? lockedHelp : undefined }
						value={ draft.key }
						disabled={ stored.locked.key }
						maxLength={ 200 }
						error={ state.errors.key }
						onChange={ ( key ) => set( { key }, 'key' ) }
					/>
					<SecretField
						label={ __( 'LRS secret', 'ohmylms' ) }
						help={ stored.locked.secret ? lockedHelp : undefined }
						value={ draft.secret }
						hasValue={ stored.hasSecret }
						cleared={ draft.clearSecret }
						disabled={ stored.locked.secret }
						error={ state.errors.secret }
						onChange={ ( secret ) => set( { secret }, 'secret' ) }
						onClear={ ( clearSecret ) =>
							set( { clearSecret }, 'secret' )
						}
					/>
					<SelectField
						label={ __( 'Identify learners by', 'ohmylms' ) }
						value={ draft.actor }
						options={ XAPI_ACTORS.map( ( actor ) => ( {
							value: actor,
							label: ACTOR_LABELS[ actor ],
						} ) ) }
						error={ state.errors.actor }
						onChange={ ( actor ) =>
							set(
								{
									actor:
										XAPI_ACTORS.find(
											( item ) => item === actor
										) ?? 'pseudonym',
								},
								'actor'
							)
						}
					/>
					<ToggleField
						label={ __( 'Include learner responses', 'ohmylms' ) }
						checked={ draft.responses }
						onChange={ ( responses ) =>
							set( { responses }, 'responses' )
						}
					/>
					<NumberField
						label={ __(
							'Keep delivered statements (days)',
							'ohmylms'
						) }
						value={ draft.retention_days }
						min={ 1 }
						max={ 365 }
						step={ 1 }
						error={ state.errors.retention_days }
						onChange={ ( days ) =>
							set( { retention_days: days }, 'retention_days' )
						}
					/>
					<fieldset>
						<legend>{ __( 'Events to send', 'ohmylms' ) }</legend>
						{ Object.entries( stored.groupLabels ).map(
							( [ group, label ] ) => (
								<ToggleField
									key={ group }
									label={ label }
									checked={ Boolean( draft.groups[ group ] ) }
									onChange={ ( on ) =>
										state.update(
											( current ) => ( {
												...current,
												groups: {
													...current.groups,
													[ group ]: on,
												},
											} ),
											'groups'
										)
									}
								/>
							)
						) }
					</fieldset>
					<p>
						{ sprintf(
							/* translators: 1: pending, 2: sent, 3: failed, 4: skipped statement counts. */
							__(
								'Queue: %1$d pending, %2$d sent, %3$d failed, %4$d skipped.',
								'ohmylms'
							),
							stored.counts.pending,
							stored.counts.sent,
							stored.counts.failed,
							stored.counts.skipped
						) }
						{ stored.lastError &&
							` ${ sprintf(
								/* translators: %s: error message. */
								__( 'Last error: %s', 'ohmylms' ),
								stored.lastError
							) }` }
					</p>
					{ stored.failures.length > 0 && (
						<ul aria-label={ __( 'Recent failures', 'ohmylms' ) }>
							{ stored.failures.map( ( failure ) => (
								<li key={ failure.id }>
									{ `${ failure.createdAt } ${ failure.kind } (${ failure.tries }): ${ failure.lastError }` }
								</li>
							) ) }
						</ul>
					) }
				</>
			) }
		</FormFrame>
	);
}
