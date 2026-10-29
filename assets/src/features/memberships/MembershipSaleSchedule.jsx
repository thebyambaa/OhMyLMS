import { createElement } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import * as Controls from '../../shared/controls';

/**
 * A sale uses calendar dates; a zero sale price grants access only until its end.
 * @param {() => Object} readRuntime Membership runtime dependencies.
 * @return {(props: Object) => Object} Sale editor.
 */
export function createMembershipSaleSchedule( readRuntime ) {
	return function MembershipSaleSchedule( { errors = {}, validate } ) {
		const { T: StoreModule, b: I18n, y: WordPressData } = readRuntime();
		const plan = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).selectMembershipPlanData(),
			[]
		);
		const { updateMembershipPlan } = WordPressData.useDispatch(
			StoreModule.default
		);
		const set = ( field, value ) => {
			updateMembershipPlan( field, value );
			validate( { ...plan, [ field ]: value } );
		};
		const hasSale =
			plan.sale_price !== '' &&
			plan.sale_price !== null &&
			plan.sale_price !== undefined;
		const free = hasSale && Number( plan.sale_price ) === 0;
		const dateValue = ( value ) =>
			String( value?.date || value || '' ).slice( 0, 10 );
		return (
			<Controls.SpacerWP
				padding={ 4 }
				className="ohmylms-sale-price-section"
			>
				<Controls.HeadingWP level={ 4 }>
					{ I18n.__( 'Sale price', 'ohmylms' ) }
				</Controls.HeadingWP>
				<p>
					{ I18n.__(
						'Set a discounted price, or enter 0 for free access until the selected end date.',
						'ohmylms'
					) }
				</p>
				<TextControl
					label={ I18n.__( 'Sale price', 'ohmylms' ) }
					type="number"
					min="0"
					step="0.01"
					value={ plan.sale_price ?? '' }
					onChange={ ( value ) => set( 'sale_price', value ) }
					help={ errors.sale_price }
					__nextHasNoMarginBottom
				/>
				{ hasSale && (
					<div>
						<TextControl
							label={ I18n.__( 'Sale starts', 'ohmylms' ) }
							type="date"
							value={ dateValue( plan.sale_price_dates_from ) }
							onChange={ ( value ) =>
								set(
									'sale_price_dates_from',
									value ? { date: `${ value }T00:00:00` } : ''
								)
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={
								free
									? I18n.__( 'Free until', 'ohmylms' )
									: I18n.__( 'Sale ends', 'ohmylms' )
							}
							type="date"
							value={ dateValue( plan.sale_price_dates_to ) }
							onChange={ ( value ) =>
								set(
									'sale_price_dates_to',
									value ? { date: `${ value }T00:00:00` } : ''
								)
							}
							help={
								errors.sale_dates ||
								( free
									? I18n.__(
											'Free access ends after this day in the site timezone. No signup fee or automatic billing; the student must purchase a plan to continue.',
											'ohmylms'
										)
									: '' )
							}
							__nextHasNoMarginBottom
						/>
					</div>
				) }
			</Controls.SpacerWP>
		);
	};
}
