import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMembershipSaleSchedule( readRuntime ) {
	return function MembershipSaleSchedule( props ) {
		const {
			I: Controls,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			n8,
			qt,
			r8,
			sn,
			t8,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			errors = props.errors,
			validate = props.validate,
			c = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectMembershipPlanData();
			}, [] ),
			u = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getMemberships();
			}, [] ),
			updateMembershipPlan = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateMembershipPlan,
			d = r8( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			f = r8( ( 0, ReactHooks.useState )( [] ), 2 ),
			v =
				( f[ 0 ],
				f[ 1 ],
				( () => {
					if (
						0 < ( u === null || u === void 0 ? void 0 : u.length )
					) {
						if ( null === ( t = u[ 0 ] ) || void 0 === t ) {
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
				} )() ),
			h = function ( e ) {
				return e && sn()( e ).isValid()
					? sn()( e )
							.startOf( 'day' )
							.format( 'YYYY-MM-DDTHH:mm:ss.SSS' )
					: ( showRequestError( 'Invalid date value provided:', e ),
						null );
			},
			_ = function ( e, _tLocal, _nLocal ) {
				var _rLocal;
				if ( _nLocal ) {
					{
						_rLocal = t8(
							t8( {}, c ),
							{},
							n8(
								{},
								e,
								t8(
									t8( {}, c[ e ] ),
									{},
									n8( {}, _nLocal, _tLocal )
								)
							)
						);
						updateMembershipPlan(
							e,
							t8(
								t8( {}, c[ e ] ),
								{},
								n8( {}, _nLocal, _tLocal )
							)
						);
					}
				} else {
					{
						_rLocal = t8( t8( {}, c ), {}, n8( {}, e, _tLocal ) );
						updateMembershipPlan( e, _tLocal );
					}
				}
				validate( _rLocal );
			},
			w = function () {
				p( ! m );
			},
			E = function () {
				updateMembershipPlan( 'sale_price_dates_from', '' );
				updateMembershipPlan( 'sale_price_dates_to', '' );
			};
		return (
			<React.Fragment>
				<Controls.SpacerWP
					padding={ 4 }
					className={ 'ohmylms-sale-price-section' }
				>
					<Controls.FlexWP
						gap={ 8 }
						align={ 'flex-start' }
						justify={ 'flex-start' }
					>
						<Controls.FlexItemWP isBlock={ ! 0 }>
							<Controls.HeadingWP level={ '4' }>
								{ ( 0, I18n.__ )(
									'Sale price ('.concat( v, ')' ),
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.TextWP as={ 'p' }>
								{ ( 0, I18n.__ )(
									'Add a discounted price on this membership for the upcoming sale campaign',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP isBlock={ ! 0 }>
							<Controls.CardWP
								isBorderless={ ! 0 }
								variant={ 'secondary' }
							>
								<Controls.SpacerWP padding={ 3 }>
									<Controls.FlexWP
										gap={ 2 }
										align={ 'flex-start' }
										justify={ 'flex-start' }
										direction={ 'column' }
									>
										<Controls.HeadingWP level={ '5' }>
											{ ( 0, I18n.__ )(
												'Sale Price',
												'ohmylms'
											) }
										</Controls.HeadingWP>
										<Controls.FlexItemWP
											isBlock={ ! 0 }
											style={ {
												width: '100%',
											} }
										>
											<Controls.InputNumberWP
												onChange={ function ( e ) {
													var _tLocal2 = e;
													if (
														/^\d*\.?\d*$/.test(
															_tLocal2
														)
													) {
														_(
															'sale_price',
															_tLocal2
														);
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
														( c === null ||
														c === void 0
															? void 0
															: c.sale_price ) < 0
													) {
														_( 'sale_price', '0' );
													}
												} }
												type={ 'number' }
												placeholder={ ( 0, I18n.__ )(
													'0',
													'ohmylms'
												) }
												value={
													null !==
														( r =
															c === null ||
															c === void 0
																? void 0
																: c.sale_price ) &&
													void 0 !== r
														? r
														: ''
												}
												max={ 99999999 }
												min={ 0 }
												className={
													'ohmylms-sale-price-input'
												}
											/>
											{ ( errors === null ||
											errors === void 0
												? void 0
												: errors.sale_price ) && (
												<Controls.TextWP
													as={ 'p' }
													size={ '13px' }
													color={ '#FF4955' }
													align={ 'right' }
												>
													{ errors.sale_price }
												</Controls.TextWP>
											) }
										</Controls.FlexItemWP>
										<Controls.FlexItemWP>
											<Controls.FlexWP
												gap={ 2 }
												direction={ 'column' }
											>
												{ Boolean(
													c === null || c === void 0
														? void 0
														: c.sale_price_dates_from
												) ? (
													<Controls.ButtonWP
														variant={ 'link' }
														onClick={ E }
														aria-label={ ( 0,
														I18n.__ )(
															'Clear',
															'ohmylms'
														) }
														tabIndex={ 0 }
														role={ 'button' }
														onKeyDown={ function (
															e
														) {
															if ( ! (
																'Enter' !==
																	e.key &&
																' ' !== e.key
															) ) {
																E();
															}
														} }
														className={
															'ohmylms-sale-price-clear-button'
														}
													>
														{ ( 0, I18n.__ )(
															'Clear',
															'ohmylms'
														) }
													</Controls.ButtonWP>
												) : (
													<Controls.ButtonWP
														variant={ 'link' }
														onClick={ w }
														aria-label={
															m
																? ( 0,
																	I18n.__ )(
																		'Remove Schedule',
																		'ohmylms'
																	)
																: ( 0,
																	I18n.__ )(
																		'Schedule',
																		'ohmylms'
																	)
														}
														tabIndex={ 0 }
														role={ 'button' }
														onKeyDown={ function (
															e
														) {
															if ( ! (
																'Enter' !==
																	e.key &&
																' ' !== e.key
															) ) {
																w();
															}
														} }
														className={
															'ohmylms-sale-price-schedule-button'
														}
													>
														{ m
															? ( 0, I18n.__ )(
																	'Remove Schedule',
																	'ohmylms'
																)
															: ( 0, I18n.__ )(
																	'Schedule',
																	'ohmylms'
																) }
													</Controls.ButtonWP>
												) }
												{ React.createElement(
													qt,
													{
														isVisible:
															m ||
															Boolean(
																c === null ||
																	c === void 0
																	? void 0
																	: c.sale_price_dates_from
															),
														style: {
															border: '1px solid #c8d2e9',
															height: '40px',
														},
													},
													<Controls.DateRangePickerWP
														initialStartDate={ ( () => {
															if (
																Boolean(
																	c ===
																		null ||
																		c ===
																			void 0
																		? void 0
																		: c.sale_price_dates_from
																)
															) {
																if (
																	c ===
																		null ||
																	c ===
																		void 0 ||
																	null ===
																		( a =
																			c.sale_price_dates_from ) ||
																	void 0 === a
																) {
																	return void 0;
																}
																return a.date;
															}
															return null;
														} )() }
														initialEndDate={ ( () => {
															if (
																c !== null &&
																c !== void 0 &&
																c.sale_price_dates_to
															) {
																if (
																	c ===
																		null ||
																	c ===
																		void 0 ||
																	null ===
																		( o =
																			c.sale_price_dates_to ) ||
																	void 0 === o
																) {
																	return void 0;
																}
																return o.date;
															}
															return null;
														} )() }
														onChange={ function (
															e
														) {
															var _tLocal3,
																_nLocal2,
																_rLocal2,
																_aLocal;
															_(
																'sale_price_dates_from',
																{
																	date: h(
																		e[ 0 ]
																	),
																	timezone_type:
																		c ===
																			null ||
																		c ===
																			void 0 ||
																		null ===
																			( _tLocal3 =
																				c.date_created ) ||
																		void 0 ===
																			_tLocal3
																			? void 0
																			: _tLocal3.timezone_type,
																	timezone:
																		c ===
																			null ||
																		c ===
																			void 0 ||
																		null ===
																			( _nLocal2 =
																				c.date_created ) ||
																		void 0 ===
																			_nLocal2
																			? void 0
																			: _nLocal2.timezone,
																}
															);
															_(
																'sale_price_dates_to',
																{
																	date: h(
																		e[ 1 ]
																	),
																	timezone_type:
																		c ===
																			null ||
																		c ===
																			void 0 ||
																		null ===
																			( _rLocal2 =
																				c.date_created ) ||
																		void 0 ===
																			_rLocal2
																			? void 0
																			: _rLocal2.timezone_type,
																	timezone:
																		c ===
																			null ||
																		c ===
																			void 0 ||
																		null ===
																			( _aLocal =
																				c.date_created ) ||
																		void 0 ===
																			_aLocal
																			? void 0
																			: _aLocal.timezone,
																}
															);
														} }
														disablePastDates={ ! 0 }
													/>
												) }
											</Controls.FlexWP>
										</Controls.FlexItemWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
