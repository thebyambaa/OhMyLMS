import { __, sprintf } from '@wordpress/i18n';
import { listCurrencies } from './api';
import {
	asNumber,
	asText,
	NumberField,
	SelectField,
	TextField,
} from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import { useReference } from './useReference';

/**
 * Currency tab: currency code and price formatting.
 *
 * @param props       Component props.
 * @param props.state State of the `currency` group.
 */
export function CurrencyForm( {
	state,
}: {
	state: SettingsGroupState< 'currency' >;
} ) {
	const currencies = useReference( 'currencies', listCurrencies );
	const draft = state.draft;
	const code = draft ? asText( draft.currency ) : '';
	const options: Array< { value: string; label: string } > =
		currencies.items.map( ( item ) => ( {
			value: item.code,
			label: sprintf(
				/* translators: 1: currency name, 2: currency code, 3: symbol. */
				__( '%1$s (%2$s, %3$s)', 'ohmylms' ),
				item.name,
				item.code,
				item.symbol
			),
		} ) );
	if ( code && ! options.some( ( option ) => option.value === code ) ) {
		options.unshift( { value: code, label: code } );
	}

	return (
		<FormFrame
			title={ __( 'Currency', 'ohmylms' ) }
			description={ __( 'How prices are shown to students.', 'ohmylms' ) }
			state={ state }
		>
			{ draft && (
				<>
					{ currencies.error && (
						<p role="alert">{ currencies.error }</p>
					) }
					<SelectField
						label={ __( 'Currency', 'ohmylms' ) }
						value={ code }
						options={ options }
						error={ state.errors.currency }
						onChange={ ( value ) =>
							state.setField( 'currency', value )
						}
					/>
					<SelectField
						label={ __( 'Currency position', 'ohmylms' ) }
						value={ asText( draft.currency_pos ) }
						options={ [
							{ value: 'left', label: __( 'Left', 'ohmylms' ) },
							{ value: 'right', label: __( 'Right', 'ohmylms' ) },
							{
								value: 'left_space',
								label: __( 'Left with space', 'ohmylms' ),
							},
							{
								value: 'right_space',
								label: __( 'Right with space', 'ohmylms' ),
							},
						] }
						error={ state.errors.currency_pos }
						onChange={ ( value ) =>
							state.setField( 'currency_pos', value )
						}
					/>
					<TextField
						label={ __( 'Thousands separator', 'ohmylms' ) }
						help={ __(
							'One character, or empty for none.',
							'ohmylms'
						) }
						value={ asText( draft.price_thousand_sep ) }
						error={ state.errors.price_thousand_sep }
						maxLength={ 1 }
						onChange={ ( value ) =>
							state.setField( 'price_thousand_sep', value )
						}
					/>
					<TextField
						label={ __( 'Decimal separator', 'ohmylms' ) }
						value={ asText( draft.price_decimal_sep ) }
						error={ state.errors.price_decimal_sep }
						maxLength={ 1 }
						onChange={ ( value ) =>
							state.setField( 'price_decimal_sep', value )
						}
					/>
					<NumberField
						label={ __( 'Number of decimals', 'ohmylms' ) }
						value={ asNumber( draft.price_num_decimals ) }
						min={ 0 }
						max={ 8 }
						step={ 1 }
						error={ state.errors.price_num_decimals }
						onChange={ ( value ) =>
							state.setField( 'price_num_decimals', value )
						}
					/>
				</>
			) }
		</FormFrame>
	);
}
