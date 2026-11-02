import { Button, Notice, Spinner } from '@wordpress/components';
import {
	lazy,
	Suspense,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import {
	asFlag,
	asText,
	SecretField,
	SelectField,
	TextAreaField,
	TextField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import {
	buildGatewayPatch,
	gatewayDirty,
	gatewayDraft,
	listGateways,
	saveGateway,
	validateGateway,
	visibleFields,
	type Gateway,
	type GatewayDraft,
	type GatewayField,
	type GatewayMode,
} from './gateways';
import { describeFailure } from './model';
import type { FailureView } from './types';
import { useResource } from './useResource';

const CheckoutPanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-settings-checkout" */ './CheckoutPanel'
	).then( ( module ) => ( { default: module.CheckoutPanel } ) )
);

/**
 * Payment gateways tab: one form per registered gateway. Credentials are write-only; the server
 * only says whether each one is saved.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports whether any gateway has unsaved edits.
 */
export function PaymentGatewaysForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const [ requested, setRequested ] = useState( active );
	const [ version, setVersion ] = useState( 0 );
	const [ gateways, setGateways ] = useState< Gateway[] | null >( null );
	const [ failure, setFailure ] = useState< FailureView | null >( null );
	const [ dirty, setDirty ] = useState< Record< string, boolean > >( {} );

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
		setFailure( null );
		setGateways( null );
		listGateways( controller.signal ).then(
			( list ) => {
				if ( ! controller.signal.aborted ) {
					setGateways( list );
				}
			},
			( error: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( describeFailure( normalizeApiError( error ) ) );
				}
			}
		);
		return () => controller.abort();
	}, [ requested, version ] );

	const anyDirty = Object.values( dirty ).some( Boolean );
	useEffect( () => onDirty( anyDirty ), [ anyDirty, onDirty ] );

	const report = useCallback( ( id: string, value: boolean ) => {
		setDirty( ( current ) =>
			current[ id ] === value ? current : { ...current, [ id ]: value }
		);
	}, [] );
	const reportCheckout = useCallback(
		( value: boolean ) => report( 'checkout:settings', value ),
		[ report ]
	);

	if ( failure ) {
		return (
			<Notice status="error" isDismissible={ false }>
				<p>{ failure.message }</p>
				{ ! failure.permission && (
					<Button
						variant="secondary"
						onClick={ () => setVersion( ( n ) => n + 1 ) }
					>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				) }
			</Notice>
		);
	}
	if ( null === gateways ) {
		return (
			<p role="status">
				<Spinner /> { __( 'Loading payment gateways…', 'ohmylms' ) }
			</p>
		);
	}
	return (
		<>
			<Suspense fallback={ null }>
				<CheckoutPanel active={ active } onDirty={ reportCheckout } />
			</Suspense>
			<h3>{ __( 'Payment gateways', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Turn gateways on for checkout and enter their credentials. Saved credentials are never shown again; leave a credential empty to keep it.',
					'ohmylms'
				) }
			</p>
			{ ! gateways.length && (
				<p>
					{ __( 'No payment gateways are registered.', 'ohmylms' ) }
				</p>
			) }
			{ gateways.map( ( gateway ) => (
				<GatewayPanel
					key={ gateway.id }
					gateway={ gateway }
					onDirty={ report }
				/>
			) ) }
		</>
	);
}

const MODE_OPTIONS: ReadonlyArray< { value: GatewayMode; label: string } > = [
	{ value: 'test', label: __( 'Test (sandbox)', 'ohmylms' ) },
	{ value: 'live', label: __( 'Live', 'ohmylms' ) },
];

function GatewayPanel( {
	gateway,
	onDirty,
}: {
	gateway: Gateway;
	onDirty: ( id: string, dirty: boolean ) => void;
} ) {
	const state = useResource< Gateway, GatewayDraft >(
		{
			load: () => Promise.resolve( gateway ),
			toDraft: gatewayDraft,
			isDirty: gatewayDirty,
			validate: ( draft, stored ) => validateGateway( stored, draft ),
			save: async ( stored, draft, signal ) => {
				const result = await saveGateway(
					stored.id,
					buildGatewayPatch( stored, draft ),
					signal
				);
				return { snapshot: result.gateway, changed: result.changed };
			},
		},
		true
	);
	useEffect(
		() => onDirty( gateway.id, state.dirty ),
		[ gateway.id, state.dirty, onDirty ]
	);

	const stored = state.snapshot;
	const draft = state.draft;
	const missing =
		stored && draft && draft.enabled
			? visibleFields( stored, draft.mode ).filter(
					( item ) =>
						'secret' === item.type &&
						( ! stored.secrets[ item.name ]?.hasValue ||
							draft.clear.includes( item.name ) ) &&
						! draft.secrets[ item.name ]
				)
			: [];

	function field( definition: GatewayField ) {
		if ( ! stored || ! draft ) {
			return null;
		}
		const name = definition.name;
		if ( 'secret' === definition.type ) {
			const key = `secrets.${ name }`;
			return (
				<SecretField
					key={ name }
					label={ definition.label }
					help={ definition.help }
					error={ state.errors[ key ] }
					value={ draft.secrets[ name ] ?? '' }
					hasValue={ Boolean( stored.secrets[ name ]?.hasValue ) }
					cleared={ draft.clear.includes( name ) }
					onChange={ ( value ) =>
						state.update(
							( current ) => ( {
								...current,
								secrets: {
									...current.secrets,
									[ name ]: value,
								},
							} ),
							key
						)
					}
					onClear={ ( cleared ) =>
						state.update(
							( current ) => ( {
								...current,
								clear: cleared
									? [ ...current.clear, name ]
									: current.clear.filter(
											( item ) => item !== name
										),
							} ),
							key
						)
					}
				/>
			);
		}
		const key = `values.${ name }`;
		const set = ( value: string | boolean ) =>
			state.update(
				( current ) => ( {
					...current,
					values: { ...current.values, [ name ]: value },
				} ),
				key
			);
		const common = {
			label: definition.label,
			help: definition.help || undefined,
			error: state.errors[ key ],
		};
		if ( 'toggle' === definition.type ) {
			return (
				<ToggleField
					key={ name }
					{ ...common }
					checked={ asFlag( draft.values[ name ] ) }
					onChange={ set }
				/>
			);
		}
		if ( 'textarea' === definition.type ) {
			return (
				<TextAreaField
					key={ name }
					{ ...common }
					value={ asText( draft.values[ name ] ) }
					onChange={ set }
				/>
			);
		}
		return (
			<TextField
				key={ name }
				{ ...common }
				value={ asText( draft.values[ name ] ) }
				maxLength={ definition.maxLength ?? undefined }
				onChange={ set }
			/>
		);
	}

	return (
		<FormFrame
			title={ gateway.title }
			description={ gateway.description }
			state={ state }
		>
			{ stored && draft && (
				<>
					<ToggleField
						label={ sprintf(
							/* translators: %s: payment gateway name. */
							__( 'Offer %s at checkout', 'ohmylms' ),
							stored.title
						) }
						checked={ draft.enabled }
						error={ state.errors.enabled }
						onChange={ ( enabled ) =>
							state.update(
								( current ) => ( { ...current, enabled } ),
								'enabled'
							)
						}
					/>
					{ null !== draft.mode && (
						<SelectField
							label={ __( 'Mode', 'ohmylms' ) }
							value={ draft.mode }
							options={ MODE_OPTIONS }
							error={ state.errors.mode }
							onChange={ ( mode ) =>
								state.update(
									( current ) => ( {
										...current,
										mode: 'test' === mode ? 'test' : 'live',
									} ),
									'mode'
								)
							}
						/>
					) }
					{ missing.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %s: comma-separated credential names. */
								__(
									'Checkout with this gateway will fail until these credentials are set: %s.',
									'ohmylms'
								),
								missing
									.map( ( item ) => item.label )
									.join( ', ' )
							) }
						</Notice>
					) }
					{ visibleFields( stored, draft.mode ).map( field ) }
				</>
			) }
		</FormFrame>
	);
}
