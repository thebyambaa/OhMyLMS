import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	modelPrice,
	pricedModels,
	pricingChecked,
	pricingSources,
} from './modelPricing.mjs';

const dollars = ( value ) => `$${ value.toFixed( 2 ) }`;
const range = ( low, high ) =>
	low === high ? dollars( low ) : `${ dollars( low ) }–${ dollars( high ) }`;

/**
 * Saved-key model discovery and a dated public pricing reference.
 * @param {Object}                  props              Component properties.
 * @param {string}                  props.provider     Selected provider.
 * @param {string}                  props.model        Selected model identifier.
 * @param {(value: string) => void} props.onChange     Model setter.
 * @param {boolean}                 props.canLoad      Saved credentials match the current provider.
 * @param {number}                  props.refreshToken Successful settings save counter.
 */
export function AiModelPicker( {
	provider,
	model,
	onChange,
	canLoad,
	refreshToken,
} ) {
	const [ catalogue, setCatalogue ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ reload, setReload ] = useState( 0 );
	useEffect( () => {
		let active = true;
		setCatalogue( null );
		setError( '' );
		if ( ! canLoad ) {
			setLoading( false );
			return undefined;
		}
		setLoading( true );
		window.wp
			.apiFetch( { path: '/ohmylms/v1/ai/models', method: 'POST' } )
			.then( ( result ) => {
				if ( active ) {
					setCatalogue( result );
				}
			} )
			.catch( ( failure ) => {
				if ( active ) {
					setError( failure.message );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ provider, canLoad, refreshToken, reload ] );
	const models = catalogue?.provider === provider ? catalogue.models : [];
	const price = modelPrice( provider, model );
	return (
		<div>
			<TextControl
				label={ __( 'Model', 'ohmylms' ) }
				autoComplete="off"
				value={ model }
				onChange={ onChange }
				help={ __(
					'Choose a model below or enter its exact identifier. PDF conversion requires PDF input support; appearing in this list does not guarantee compatibility with both tools.',
					'ohmylms'
				) }
			/>
			<Button
				variant="secondary"
				disabled={ ! canLoad || loading }
				isBusy={ loading }
				onClick={ () => setReload( ( value ) => value + 1 ) }
			>
				{ __( 'Refresh available models', 'ohmylms' ) }
			</Button>
			<p>
				{ __(
					'Save your provider and API key to load its model list automatically. This lists models without sending a prompt or PDF. Select a model, then save again.',
					'ohmylms'
				) }
			</p>
			{ loading && (
				<p role="status">
					{ __( 'Loading models from your provider…', 'ohmylms' ) }
				</p>
			) }
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ models.length > 0 && (
				<SelectControl
					label={ __( 'Models returned for your key', 'ohmylms' ) }
					value={ models.includes( model ) ? model : '' }
					options={ [
						{
							value: '',
							label: __( 'Choose an available model', 'ohmylms' ),
						},
						...models.map( ( id ) => ( { value: id, label: id } ) ),
					] }
					onChange={ ( value ) => {
						if ( value ) {
							onChange( value );
						}
					} }
				/>
			) }
			{ catalogue && models.length === 0 && (
				<p>
					{ __(
						'No generation models were returned. Check your provider permissions or enter a model identifier manually.',
						'ohmylms'
					) }
				</p>
			) }
			{ catalogue?.truncated && (
				<p>
					{ __(
						'This is the first 1,000 models. You can enter another model identifier manually.',
						'ohmylms'
					) }
				</p>
			) }
			<h4>{ __( 'Model cost reference', 'ohmylms' ) }</h4>
			<p>
				{ sprintf(
					/* translators: %s: date when published rates were checked. */ __(
						'USD per 1 million tokens. Published standard rates checked %s; your account tier and bill may differ.',
						'ohmylms'
					),
					pricingChecked
				) }
			</p>
			{ model && (
				<p role="status">
					{ price
						? sprintf(
								/* translators: 1: input USD rate, 2: output USD rate. */ __(
									'Selected model: %1$s input · %2$s output per million tokens.',
									'ohmylms'
								),
								range( price[ 0 ], price[ 1 ] ),
								range( price[ 2 ], price[ 3 ] )
							)
						: __(
								'Selected model: price not verified in this reference. Check the official pricing page before use.',
								'ohmylms'
							) }
				</p>
			) }
			<table className="widefat striped">
				<caption>
					{ __(
						'Published prices; model availability is checked separately with your saved key.',
						'ohmylms'
					) }
				</caption>
				<thead>
					<tr>
						<th scope="col">{ __( 'Model', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Input', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Output', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ pricedModels( provider ).map( ( id ) => {
						const rate = modelPrice( provider, id );
						return rate ? (
							<tr key={ id }>
								<th scope="row">{ id }</th>
								<td>{ range( rate[ 0 ], rate[ 1 ] ) }</td>
								<td>{ range( rate[ 2 ], rate[ 3 ] ) }</td>
							</tr>
						) : null;
					} ) }
				</tbody>
			</table>
			<p>
				{ __(
					'Ranges reflect context-length tiers. Haiku 5.5 uses the higher rate above 100,000 input tokens. Gemini 3.8 Flash has promotional rates through December 31, 2026. Free tiers, caching, taxes and discounts are excluded.',
					'ohmylms'
				) }
			</p>
			<p>
				{ __(
					'PDFs include document/image tokens and may be sent across multiple extraction batches. There is no fixed per-paper price; total cost depends on input, generated output and retries.',
					'ohmylms'
				) }
			</p>
			<a
				href={ pricingSources[ provider ] }
				target="_blank"
				rel="noreferrer"
			>
				{ __( 'Official provider pricing', 'ohmylms' ) }
			</a>
		</div>
	);
}
