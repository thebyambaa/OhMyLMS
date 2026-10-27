/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMembershipPricing( readRuntime ) {
	return function MembershipPricing( props ) {
		const {
			$7,
			I: Controls,
			React,
			T: StoreModule,
			Z7,
			_n,
			b: I18n,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			errors = props.errors,
			validate = props.validate,
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectMembershipPlanData();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getMemberships();
			}, [] ),
			updateMembershipPlan = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateMembershipPlan,
			c = ( () => {
				if ( 0 < ( i === null || i === void 0 ? void 0 : i.length ) ) {
					if ( null === ( t = i[ 0 ] ) || void 0 === t ) {
						return void 0;
					}
					return t.currency;
				}
				if (
					null === ( n = window ) ||
					void 0 === n ||
					null === ( n = n.ohmylms_params ) ||
					void 0 === n
				) {
					return void 0;
				}
				return n.currency;
			} )(),
			u = function ( e, _tLocal ) {
				updateMembershipPlan( e, _tLocal );
				validate( Z7( Z7( {}, o ), {}, $7( {}, e, _tLocal ) ) );
			},
			s = [
				{
					value: 'year',
					label: ( 0, I18n.__ )( 'Every Year', 'ohmylms' ),
				},
				{
					value: 'month',
					label: ( 0, I18n.__ )( 'Every Month', 'ohmylms' ),
				},
				{
					value: 'week',
					label: ( 0, I18n.__ )( 'Every week', 'ohmylms' ),
				},
				{
					value: 'day',
					label: ( 0, I18n.__ )( 'Every day', 'ohmylms' ),
				},
				{
					value: 'one_time',
					label: ( 0, I18n.__ )( 'One Time', 'ohmylms' ),
				},
			];
		return (
			<React.Fragment>
				<Controls.SpacerWP padding={ 4 }>
					<Controls.FlexWP
						gap={ 8 }
						align={ 'flex-start' }
						justify={ 'flex-start' }
					>
						<Controls.FlexItemWP isBlock={ ! 0 }>
							<Controls.HeadingWP level={ '4' }>
								{ ( 0, I18n.__ )(
									'Subscription price ('.concat( c, ')' ),
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.TextWP as={ 'p' }>
								{ ( 0, I18n.__ )(
									'Choose the subscription price, billing interval and period.',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP isBlock={ ! 0 }>
							<Controls.FlexWP
								direction={ 'column' }
								justify={ 'flex-end' }
								align={ 'flex-end' }
							>
								<Controls.FlexWP
									justify={ 'flex-end' }
									align={ 'flex-start' }
								>
									<Controls.InputNumberWP
										className={
											'ohmylms-subscription-price-input'
										}
										prefix={ c }
										type={ 'number' }
										placeholder={ ( 0, I18n.__ )(
											'e.g. 5.90',
											'ohmylms'
										) }
										value={
											o === null || o === void 0
												? void 0
												: o.regular_price
										}
										onChange={ function ( e ) {
											var _tLocal2 = e;
											if (
												/^\d*\.?\d*$/.test( _tLocal2 )
											) {
												u( 'regular_price', _tLocal2 );
											}
										} }
										onKeyDown={ function ( e ) {
											if ( ! (
												'-' !== e.key &&
												'+' !== e.key &&
												'e' !== e.key
											) ) {
												e.preventDefault();
											}
										} }
										onBlur={ function () {
											if (
												( o === null || o === void 0
													? void 0
													: o.regular_price ) < 0
											) {
												u( 'regular_price', '0' );
											}
										} }
										min={ 0 }
										max={ 99999999 }
									/>
									<_n
										placeholder={ ( 0, I18n.__ )(
											'Select period',
											'ohmylms'
										) }
										options={ s }
										value={
											o === null || o === void 0
												? void 0
												: o.subscription_period
										}
										onChange={ function ( e ) {
											return u(
												'subscription_period',
												e
											);
										} }
										customClass={
											'ohmylms-subscription-period-select'
										}
									/>
								</Controls.FlexWP>
								{ ( errors === null || errors === void 0
									? void 0
									: errors.price ) && (
									<Controls.TextWP
										as={ 'p' }
										size={ '13px' }
										color={ '#FF4955' }
										align={ 'right' }
									>
										{ errors.price }
									</Controls.TextWP>
								) }
							</Controls.FlexWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
