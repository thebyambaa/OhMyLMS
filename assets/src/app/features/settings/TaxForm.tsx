import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { listCountries, listStates } from './api';
import {
	asFlag,
	asNumber,
	asText,
	NumberField,
	SelectField,
	TextField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import type { CodeName, TaxRate } from './types';
import { useReference } from './useReference';

const NO_CHOICE = { value: '', label: __( '— Choose —', 'ohmylms' ) };

function toOptions( items: readonly CodeName[], current: string ) {
	const options = items.map( ( item ) => ( {
		value: item.code,
		label: item.name,
	} ) );
	if ( current && ! options.some( ( option ) => option.value === current ) ) {
		options.unshift( { value: current, label: current } );
	}
	return [ NO_CHOICE, ...options ];
}

function RateRow( {
	position,
	rate,
	countries,
	disabled,
	onChange,
	onRemove,
}: {
	position: number;
	rate: TaxRate;
	countries: readonly CodeName[];
	disabled: boolean;
	onChange: ( next: TaxRate ) => void;
	onRemove: () => void;
} ) {
	const needsStates = ! rate.country_wide && '' !== rate.country;
	const states = useReference(
		needsStates ? rate.country : null,
		( signal ) => listStates( rate.country, signal )
	);
	const suffix = sprintf(
		/* translators: %d: row number. */
		__( '(rate %d)', 'ohmylms' ),
		position
	);

	return (
		<fieldset>
			<legend>
				{ sprintf(
					/* translators: %d: row number. */
					__( 'Tax rate %d', 'ohmylms' ),
					position
				) }
			</legend>
			<SelectField
				label={ `${ __( 'Country', 'ohmylms' ) } ${ suffix }` }
				value={ rate.country }
				options={ toOptions( countries, rate.country ) }
				disabled={ disabled }
				onChange={ ( country ) =>
					onChange( { ...rate, country, state: '' } )
				}
			/>
			<ToggleField
				label={ `${ __( 'Whole country', 'ohmylms' ) } ${ suffix }` }
				checked={ rate.country_wide }
				disabled={ disabled }
				onChange={ ( wide ) =>
					onChange( {
						...rate,
						country_wide: wide,
						state: wide ? '' : rate.state,
					} )
				}
			/>
			{ ! rate.country_wide && (
				<SelectField
					label={ `${ __( 'State', 'ohmylms' ) } ${ suffix }` }
					value={ rate.state }
					options={ toOptions( states.items, rate.state ) }
					error={ states.error ?? undefined }
					disabled={ disabled || states.loading }
					onChange={ ( state ) => onChange( { ...rate, state } ) }
				/>
			) }
			<NumberField
				label={ `${ __( 'Rate (%)', 'ohmylms' ) } ${ suffix }` }
				value={ rate.rate }
				min={ 0 }
				max={ 100 }
				step={ 0.01 }
				disabled={ disabled }
				onChange={ ( value ) => onChange( { ...rate, rate: value } ) }
			/>
			<Button
				variant="link"
				isDestructive
				disabled={ disabled }
				onClick={ onRemove }
			>
				{ sprintf(
					/* translators: %d: row number. */
					__( 'Remove rate %d', 'ohmylms' ),
					position
				) }
			</Button>
		</fieldset>
	);
}

/**
 * Tax tab: switches, labels, the fallback rate and per-country rates.
 *
 * @param props       Component props.
 * @param props.state State of the `tax` group.
 */
export function TaxForm( { state }: { state: SettingsGroupState< 'tax' > } ) {
	const countries = useReference( 'countries', listCountries );
	const draft = state.draft;
	const rates: TaxRate[] =
		draft && Array.isArray( draft.tax_rates ) ? draft.tax_rates : [];
	const setRates = ( next: TaxRate[] ) => state.setField( 'tax_rates', next );

	return (
		<FormFrame
			title={ __( 'Tax', 'ohmylms' ) }
			description={ __(
				'Tax is calculated at checkout from the customer address.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<ToggleField
						label={ __( 'Calculate tax at checkout', 'ohmylms' ) }
						checked={ asFlag( draft.tax_enabled ) }
						error={ state.errors.tax_enabled }
						onChange={ ( value ) =>
							state.setField( 'tax_enabled', value )
						}
					/>
					<TextField
						label={ __( 'Tax label', 'ohmylms' ) }
						value={ asText( draft.tax_label ) }
						error={ state.errors.tax_label }
						maxLength={ 50 }
						onChange={ ( value ) =>
							state.setField( 'tax_label', value )
						}
					/>
					<ToggleField
						label={ __( 'Prices include tax', 'ohmylms' ) }
						help={ __(
							'Turn on if the prices you enter already contain tax.',
							'ohmylms'
						) }
						checked={ asFlag( draft.prices_include_tax ) }
						error={ state.errors.prices_include_tax }
						onChange={ ( value ) =>
							state.setField( 'prices_include_tax', value )
						}
					/>
					<ToggleField
						label={ __( 'Apply EU VAT', 'ohmylms' ) }
						help={ __(
							'Customers in the European Union are charged their country’s standard VAT rate unless a rate below overrides it.',
							'ohmylms'
						) }
						checked={ asFlag( draft.eu_vat_enabled ) }
						error={ state.errors.eu_vat_enabled }
						onChange={ ( value ) =>
							state.setField( 'eu_vat_enabled', value )
						}
					/>
					<ToggleField
						label={ __( 'Skip VAT number validation', 'ohmylms' ) }
						checked={ asFlag( draft.disable_vat_validation ) }
						error={ state.errors.disable_vat_validation }
						onChange={ ( value ) =>
							state.setField( 'disable_vat_validation', value )
						}
					/>
					<TextField
						label={ __( 'VAT number label', 'ohmylms' ) }
						value={ asText( draft.vat_number_label ) }
						error={ state.errors.vat_number_label }
						maxLength={ 50 }
						onChange={ ( value ) =>
							state.setField( 'vat_number_label', value )
						}
					/>
					<NumberField
						label={ __( 'Fallback rate (%)', 'ohmylms' ) }
						help={ __(
							'Charged where no rate below matches. For example 6.5 means 6.5%.',
							'ohmylms'
						) }
						value={ asNumber( draft.fallback_tax_rate ) }
						min={ 0 }
						max={ 100 }
						step={ 0.01 }
						error={ state.errors.fallback_tax_rate }
						onChange={ ( value ) =>
							state.setField( 'fallback_tax_rate', value )
						}
					/>
					<h4>{ __( 'Tax rates', 'ohmylms' ) }</h4>
					{ countries.error && (
						<p role="alert">{ countries.error }</p>
					) }
					{ state.errors.tax_rates && (
						<p role="alert">{ state.errors.tax_rates }</p>
					) }
					{ 0 === rates.length && (
						<p>{ __( 'No tax rates yet.', 'ohmylms' ) }</p>
					) }
					{ rates.map( ( rate, index ) => (
						<RateRow
							// Rows have no id; position is their identity (they are only added, edited or removed).
							key={ index }
							position={ index + 1 }
							rate={ rate }
							countries={ countries.items }
							disabled={ state.saving }
							onChange={ ( next ) =>
								setRates(
									rates.map( ( row, at ) =>
										at === index ? next : row
									)
								)
							}
							onRemove={ () =>
								setRates(
									rates.filter( ( _row, at ) => at !== index )
								)
							}
						/>
					) ) }
					<Button
						variant="secondary"
						disabled={ state.saving || rates.length >= 500 }
						accessibleWhenDisabled
						onClick={ () =>
							setRates( [
								...rates,
								{
									country: '',
									state: '',
									rate: 0,
									country_wide: true,
								},
							] )
						}
					>
						{ __( 'Add tax rate', 'ohmylms' ) }
					</Button>
				</>
			) }
		</FormFrame>
	);
}
