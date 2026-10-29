/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCoursePricing( readRuntime ) {
	return function CoursePricing() {
		const {
			Kt,
			NW,
			React,
			T: StoreModule,
			b: I18n,
			cz,
			g: ReactHooks,
			lo,
			sn,
			sz,
			uz,
			y: WordPressData,
		} = readRuntime();
		var e,
			t,
			n,
			r,
			a,
			o = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).isGamificationEnable();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2(
					StoreModule.default
				).getGamificationSettings();
			}, [] );
		sn().extend( NW() );
		sn().extend( lo() );
		var l = ( 0, WordPressData.useDispatch )( 'ohmylms/store' ),
			c = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getCourse();
			}, [] ),
			u = sz(
				( 0, ReactHooks.useState )(
					( c === null ||
					c === void 0 ||
					null === ( e = c.sale_price_dates_from ) ||
					void 0 === e
						? void 0
						: e.date ) &&
						( c === null ||
						c === void 0 ||
						null === ( t = c.sale_price_dates_to ) ||
						void 0 === t
							? void 0
							: t.date )
				),
				2
			),
			s = u[ 0 ],
			d = u[ 1 ],
			m = sz( ( 0, ReactHooks.useState )( '' ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = sz( ( 0, ReactHooks.useState )( {} ), 2 ),
			_ = v[ 0 ],
			w = v[ 1 ],
			E = function ( _eLocal4 ) {
				return _eLocal4
					? sn()( _eLocal4 ).format( 'YYYY-MM-DDTHH:mm:ss' )
					: '';
			},
			_priceType = c.price_type,
			_regularPrice = c.regular_price,
			_salePrice = c.sale_price,
			_purchasePoint = c.purchase_point,
			P = function ( _eLocal5, _tLocal ) {
				if ( p ) {
					{
						l.setCourse(
							cz(
								cz( {}, c ),
								{},
								uz( {}, _tLocal, _[ _tLocal ] )
							)
						);
						if (
							Number( _regularPrice ) === 0 &&
							'paid' === _priceType
						) {
							{
								l.setIsValidCourseSettings( ! 1 );
								f(
									( 0, I18n.__ )(
										'Regular price should be greater than 0',
										'ohmylms'
									)
								);
							}
						} else {
							{
								f( '' );
								l.setIsValidCourseSettings( ! 0 );
							}
						}
					}
				}
				w( {} );
			},
			O = function ( _eLocal6, _tLocal2 ) {
				var _nLocal = _eLocal6.target.value;
				if ( Number( _nLocal ) === 0 ) {
					l.setCourse(
						cz( cz( {}, c ), {}, uz( {}, _tLocal2, '' ) )
					);
				}
				if ( 'sale_price' !== _tLocal2 ) {
					w( cz( cz( {}, _ ), {}, uz( {}, _tLocal2, _nLocal ) ) );
				}
			};
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'flex-start' }
					className={ 'ohmylms-single-settings settings-price ohmylms-'.concat(
						_priceType
					) }
				>
					<Controls.FlexItemWP
						style={ {
							flex: '5',
						} }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )( 'Pricing', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Select if learners will pay or access for free.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							flex: '3',
						} }
					>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'flex-end' }
						>
							<Controls.RadioGroupWP
								onChange={ function ( _eLocal7 ) {
									if ( 'free' === _eLocal7 ) {
										{
											l.setIsValidCourseSettings( ! 0 );
											f( '' );
											w( {} );
										}
									}
									l.setCourse(
										cz(
											cz( {}, c ),
											{},
											{
												price_type: _eLocal7,
												sale_price:
													'free' === _eLocal7
														? ''
														: c.sale_price,
												regular_price:
													'free' === _eLocal7
														? 0
														: c.regular_price,
											}
										)
									);
									if (
										Number( c.regular_price ) === 0 &&
										'free' !== _eLocal7
									) {
										{
											l.setIsValidCourseSettings( ! 1 );
											f(
												( 0, I18n.__ )(
													'Regular price should be greater than 0',
													'ohmylms'
												)
											);
										}
									}
								} }
								value={
									'' === _priceType ? 'free' : _priceType
								}
								options={ [
									{
										value: 'free',
										label: ( 0, I18n.__ )(
											'Free',
											'ohmylms'
										),
									},
									{
										value: 'paid',
										label: ( 0, I18n.__ )(
											'Paid',
											'ohmylms'
										),
									},
								] }
								isBlock={ ! 1 }
							/>
						</Controls.FlexWP>
						{ 'paid' === _priceType && (
							<Controls.CardWP
								isBorderless={ ! 0 }
								variant={ 'secondary' }
							>
								<Controls.SpacerWP
									padding={ 4 }
									marginBottom={ 0 }
									marginTop={ 4 }
								>
									<Controls.FlexWP
										direction={ 'column' }
										justify={ 'flex-end' }
										align={ 'flex-end' }
										className={ 'ohmylms-settings-right' }
									>
										<div
											className={ 'ohmylms-price-range' }
											style={ {
												width: '100%',
											} }
										>
											<Controls.FlexWP gap={ 4 }>
												<Controls.FlexBlockWP
													className={
														'ohmylms-single-price'
													}
												>
													<Controls.TextWP
														as={ 'span' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Regular Price',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
													<Controls.InputNumberWP
														value={
															_regularPrice
																? parseFloat(
																		_regularPrice
																	).toFixed(
																		2
																	)
																: ''
														}
														onFocus={ function (
															_eLocal8
														) {
															return O(
																_eLocal8,
																'regular_price'
															);
														} }
														onBlur={ function () {
															return P(
																0,
																'regular_price'
															);
														} }
														placeholder={ ( 0,
														I18n.__ )(
															'0.00',
															'ohmylms'
														) }
														className={
															'ohmylms-course-settings-pricing-input-regular ohmylms-price-input'
														}
														onChange={ function (
															_eLocal0
														) {
															if (
																/^\d*\.?\d*$/.test(
																	_eLocal0
																)
															) {
																( function (
																	_eLocal1
																) {
																	if (
																		Number(
																			_salePrice
																		) >
																			Number(
																				_eLocal1
																			) &&
																		'paid' ===
																			_priceType
																	) {
																		{
																			l.setIsValidCourseSettings(
																				! 1
																			);
																			f(
																				( 0,
																				I18n.__ )(
																					'Discount price should be less than regular price',
																					'ohmylms'
																				)
																			);
																		}
																	} else if (
																		Number(
																			_eLocal1
																		) === 0
																	) {
																		{
																			l.setIsValidCourseSettings(
																				! 1
																			);
																			f(
																				( 0,
																				I18n.__ )(
																					'Regular price should be greater than 0',
																					'ohmylms'
																				)
																			);
																		}
																	} else if (
																		'' !==
																			_eLocal1 &&
																		_eLocal1
																	) {
																		{
																			l.setIsValidCourseSettings(
																				! 0
																			);
																			f(
																				''
																			);
																		}
																	} else {
																		{
																			l.setIsValidCourseSettings(
																				! 1
																			);
																			f(
																				( 0,
																				I18n.__ )(
																					'Regular price can not be empty',
																					'ohmylms'
																				)
																			);
																		}
																	}
																	l.setCourse(
																		cz(
																			cz(
																				{},
																				c
																			),
																			{},
																			{
																				regular_price:
																					_eLocal1,
																			}
																		)
																	);
																} )( _eLocal0 );
															}
														} }
														onKeyDown={ function (
															_eLocal10
														) {
															if (
																[
																	'e',
																	'E',
																	'+',
																	'-',
																	'/',
																	'\\',
																].includes(
																	_eLocal10.key
																) ||
																( /[a-zA-Z]/.test(
																	_eLocal10.key
																) &&
																	! [
																		'Backspace',
																		'Tab',
																		'ArrowLeft',
																		'ArrowRight',
																		'Delete',
																		'Enter',
																		'.',
																	].includes(
																		_eLocal10.key
																	) )
															) {
																_eLocal10.preventDefault();
															}
														} }
													/>
												</Controls.FlexBlockWP>
												<Controls.FlexBlockWP
													className={
														'ohmylms-single-price'
													}
												>
													<Controls.TextWP
														as={ 'span' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Sale Price',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
													<Controls.InputNumberWP
														onFocus={ function (
															_eLocal11
														) {
															return O(
																_eLocal11,
																'sale_price'
															);
														} }
														value={
															_salePrice
																? parseFloat(
																		_salePrice
																	).toFixed(
																		2
																	)
																: ''
														}
														onBlur={ function () {
															return P(
																0,
																'sale_price'
															);
														} }
														placeholder={ ( 0,
														I18n.__ )(
															'0.00',
															'ohmylms'
														) }
														className={
															'ohmylms-course-settings-pricing-input-discount ohmylms-price-input'
														}
														onChange={ function (
															_eLocal13
														) {
															if (
																/^\d*\.?\d*$/.test(
																	_eLocal13
																) ||
																'' ===
																	_eLocal13 ||
																null ===
																	_eLocal13
															) {
																( function (
																	_eLocal14
																) {
																	var _tLocal3,
																		_nLocal2,
																		_rLocal,
																		_aLocal;
																	if (
																		Number(
																			_eLocal14
																		) >=
																			Number(
																				_regularPrice
																			) &&
																		'paid' ===
																			_priceType
																	) {
																		{
																			l.setIsValidCourseSettings(
																				! 1
																			);
																			f(
																				( 0,
																				I18n.__ )(
																					'Discount price should be less than regular price',
																					'ohmylms'
																				)
																			);
																		}
																	} else if (
																		Number(
																			_regularPrice
																		) ===
																			0 &&
																		'paid' ===
																			_priceType
																	) {
																		{
																			l.setIsValidCourseSettings(
																				! 1
																			);
																			f(
																				( 0,
																				I18n.__ )(
																					'Regular price should be greater than 0',
																					'ohmylms'
																				)
																			);
																		}
																	} else {
																		{
																			l.setIsValidCourseSettings(
																				! 0
																			);
																			f(
																				''
																			);
																		}
																	}
																	if (
																		null ===
																		_eLocal14
																	) {
																		l.setCourse(
																			cz(
																				cz(
																					{},
																					c
																				),
																				{},
																				{
																					sale_price:
																						_eLocal14 !==
																							null &&
																						_eLocal14 !==
																							void 0
																							? _eLocal14
																							: '',
																					sale_price_dates_from:
																						{
																							date: '',
																							timezone_type:
																								c ===
																									null ||
																								c ===
																									void 0 ||
																								null ===
																									( _tLocal3 =
																										c.sale_price_dates_from ) ||
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
																										c.sale_price_dates_from ) ||
																								void 0 ===
																									_nLocal2
																									? void 0
																									: _nLocal2.timezone,
																						},
																					sale_price_dates_to:
																						{
																							date: '',
																							timezone_type:
																								c ===
																									null ||
																								c ===
																									void 0 ||
																								null ===
																									( _rLocal =
																										c.sale_price_dates_to ) ||
																								void 0 ===
																									_rLocal
																									? void 0
																									: _rLocal.timezone_type,
																							timezone:
																								c ===
																									null ||
																								c ===
																									void 0 ||
																								null ===
																									( _aLocal =
																										c.sale_price_dates_to ) ||
																								void 0 ===
																									_aLocal
																									? void 0
																									: _aLocal.timezone,
																						},
																				}
																			)
																		);
																	} else {
																		l.setCourse(
																			cz(
																				cz(
																					{},
																					c
																				),
																				{},
																				{
																					sale_price:
																						_eLocal14 !==
																							null &&
																						_eLocal14 !==
																							void 0
																							? _eLocal14
																							: '',
																				}
																			)
																		);
																	}
																} )(
																	_eLocal13
																);
															}
														} }
														onKeyDown={ function (
															_eLocal15
														) {
															if (
																[
																	'e',
																	'E',
																	'+',
																	'-',
																	'/',
																	'\\',
																].includes(
																	_eLocal15.key
																) ||
																( /[a-zA-Z]/.test(
																	_eLocal15.key
																) &&
																	! [
																		'Backspace',
																		'Tab',
																		'ArrowLeft',
																		'ArrowRight',
																		'Delete',
																		'Enter',
																	].includes(
																		_eLocal15.key
																	) )
															) {
																_eLocal15.preventDefault();
															}
														} }
													/>
												</Controls.FlexBlockWP>
											</Controls.FlexWP>
											<Controls.SpacerWP
												marginBottom={ 1 }
											/>
											{ p && (
												<Controls.TextWP
													color={ '#FF4955' }
												>
													{ p }
												</Controls.TextWP>
											) }
											<Controls.SpacerWP
												marginBottom={ 3 }
											/>
											{ '' !== _salePrice && (
												<React.Fragment>
													<Controls.CardWP>
														<Kt
															title={ ( 0,
															I18n.__ )(
																'Sale Schedule',
																'ohmylms'
															) }
															customClass={
																'schedule-price-handler'
															}
															onChange={ function () {
																var _eLocal16,
																	_tLocal4,
																	_nLocal3,
																	_rLocal2;
																d( ! s );
																if ( s ) {
																	l.setCourse(
																		cz(
																			cz(
																				{},
																				c
																			),
																			{},
																			{
																				sale_price_dates_from:
																					{
																						date: '',
																						timezone_type:
																							c ===
																								null ||
																							c ===
																								void 0 ||
																							null ===
																								( _eLocal16 =
																									c.post_date ) ||
																							void 0 ===
																								_eLocal16
																								? void 0
																								: _eLocal16.timezone_type,
																						timezone:
																							c ===
																								null ||
																							c ===
																								void 0 ||
																							null ===
																								( _tLocal4 =
																									c.post_date ) ||
																							void 0 ===
																								_tLocal4
																								? void 0
																								: _tLocal4.timezone,
																					},
																				sale_price_dates_to:
																					{
																						date: '',
																						timezone_type:
																							c ===
																								null ||
																							c ===
																								void 0 ||
																							null ===
																								( _nLocal3 =
																									c.post_date ) ||
																							void 0 ===
																								_nLocal3
																								? void 0
																								: _nLocal3.timezone_type,
																						timezone:
																							c ===
																								null ||
																							c ===
																								void 0 ||
																							null ===
																								( _rLocal2 =
																									c.post_date ) ||
																							void 0 ===
																								_rLocal2
																								? void 0
																								: _rLocal2.timezone,
																					},
																			}
																		)
																	);
																}
															} }
															isChecked={ s }
															showDivider={ ! 1 }
															conditionalChild={
																<React.Fragment>
																	<Controls.SpacerWP
																		marginBottom={
																			3
																		}
																	/>
																	<div
																		style={ {
																			border: '1px solid #c8d2e9',
																			height: '40px',
																			display:
																				'inline-block',
																		} }
																	>
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
																							( n =
																								c.sale_price_dates_from ) ||
																						void 0 ===
																							n
																					) {
																						return void 0;
																					}
																					return n.date;
																				}
																				return null;
																			} )() }
																			initialEndDate={ ( () => {
																				if (
																					c !==
																						null &&
																					c !==
																						void 0 &&
																					c.sale_price_dates_to
																				) {
																					if (
																						c ===
																							null ||
																						c ===
																							void 0 ||
																						null ===
																							( r =
																								c.sale_price_dates_to ) ||
																						void 0 ===
																							r
																					) {
																						return void 0;
																					}
																					return r.date;
																				}
																				return null;
																			} )() }
																			onChange={ function (
																				_eLocal17
																			) {
																				var _tLocal5,
																					_nLocal4,
																					_rLocal3,
																					_aLocal2;
																				l.setCourse(
																					cz(
																						cz(
																							{},
																							c
																						),
																						{},
																						{
																							sale_price_dates_from:
																								{
																									date: E(
																										_eLocal17[ 0 ]
																									),
																									timezone_type:
																										c ===
																											null ||
																										c ===
																											void 0 ||
																										null ===
																											( _tLocal5 =
																												c.post_date ) ||
																										void 0 ===
																											_tLocal5
																											? void 0
																											: _tLocal5.timezone_type,
																									timezone:
																										c ===
																											null ||
																										c ===
																											void 0 ||
																										null ===
																											( _nLocal4 =
																												c.post_date ) ||
																										void 0 ===
																											_nLocal4
																											? void 0
																											: _nLocal4.timezone,
																								},
																							sale_price_dates_to:
																								{
																									date: E(
																										_eLocal17[ 1 ]
																									),
																									timezone_type:
																										c ===
																											null ||
																										c ===
																											void 0 ||
																										null ===
																											( _rLocal3 =
																												c.post_date ) ||
																										void 0 ===
																											_rLocal3
																											? void 0
																											: _rLocal3.timezone_type,
																									timezone:
																										c ===
																											null ||
																										c ===
																											void 0 ||
																										null ===
																											( _aLocal2 =
																												c.post_date ) ||
																										void 0 ===
																											_aLocal2
																											? void 0
																											: _aLocal2.timezone,
																								},
																						}
																					)
																				);
																			} }
																			disablePastDates={
																				! 0
																			}
																		/>
																	</div>
																</React.Fragment>
															}
														/>
													</Controls.CardWP>
												</React.Fragment>
											) }
											{ ( ! 0 === o || 1 === o ) &&
												( i === null ||
												i === void 0 ||
												null ===
													( a = i.reward_settings ) ||
												void 0 === a ||
												null === ( a = a.rules ) ||
												void 0 === a
													? void 0
													: a.find(
															function (
																_eLocal18
															) {
																return (
																	'purchase_course' ===
																	_eLocal18.slug
																);
															}
														).value ) && (
													<React.Fragment>
														<Controls.FlexWP
															gap={ 4 }
														>
															<Controls.FlexBlockWP
																className={
																	'ohmylms-single-price'
																}
															>
																<Controls.SpacerWP
																	marginBottom={
																		1
																	}
																/>
																<Controls.TextWP
																	as={
																		'span'
																	}
																	variant={
																		'muted'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Purchase with Points',
																		'ohmylms'
																	) }
																</Controls.TextWP>
																<Controls.SpacerWP
																	marginBottom={
																		1
																	}
																/>
																<Controls.InputNumberWP
																	onFocus={ function (
																		_eLocal19
																	) {
																		return O(
																			_eLocal19,
																			'purchase_point'
																		);
																	} }
																	value={
																		_purchasePoint ||
																		''
																	}
																	onBlur={ function () {
																		return P(
																			0,
																			'purchase_point'
																		);
																	} }
																	placeholder={ ( 0,
																	I18n.__ )(
																		'0',
																		'ohmylms'
																	) }
																	className={
																		'ohmylms-course-settings-pricing-input-discount ohmylms-price-input'
																	}
																	onChange={ function (
																		_eLocal21
																	) {
																		if (
																			/^\d*\.?\d*$/.test(
																				_eLocal21
																			) ||
																			'' ===
																				_eLocal21 ||
																			null ===
																				_eLocal21
																		) {
																			( function (
																				_eLocal22
																			) {
																				if (
																					o
																				) {
																					l.setCourse(
																						cz(
																							cz(
																								{},
																								c
																							),
																							{},
																							{
																								purchase_point:
																									_eLocal22,
																							}
																						)
																					);
																				} else {
																					l.setCourse(
																						cz(
																							cz(
																								{},
																								c
																							),
																							{},
																							{
																								purchase_point:
																									'',
																							}
																						)
																					);
																				}
																			} )(
																				_eLocal21
																			);
																		}
																	} }
																	onKeyDown={ function (
																		_eLocal23
																	) {
																		if (
																			[
																				'e',
																				'E',
																				'+',
																				'-',
																				'/',
																				'\\',
																			].includes(
																				_eLocal23.key
																			) ||
																			( /[a-zA-Z]/.test(
																				_eLocal23.key
																			) &&
																				! [
																					'Backspace',
																					'Tab',
																					'ArrowLeft',
																					'ArrowRight',
																					'Delete',
																					'Enter',
																				].includes(
																					_eLocal23.key
																				) )
																		) {
																			_eLocal23.preventDefault();
																		}
																	} }
																/>
																<Controls.TextWP
																	variant={
																		'muted'
																	}
																	style={ {
																		marginTop:
																			'10px',
																	} }
																>
																	{ ( 0,
																	I18n.__ )(
																		'Learners can purchase this course using either money or points, based on the selected method.',
																		'ohmylms'
																	) }
																</Controls.TextWP>
															</Controls.FlexBlockWP>
														</Controls.FlexWP>
													</React.Fragment>
												) }
										</div>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</Controls.CardWP>
						) }
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
