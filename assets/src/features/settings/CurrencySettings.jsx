import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCurrencySettings( readRuntime ) {
	return function CurrencySettings() {
		const {
			I: Controls,
			Nm,
			Pf,
			R1,
			React,
			S1,
			T: StoreModule,
			_1,
			b: I18n,
			b1,
			g: ReactHooks,
			l,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			c,
			u,
			s,
			d,
			m = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCurrencySettings();
			}, [] ),
			p = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			f = ( 0, Notifications.A )(),
			v = ( f.openNotificationWithIcon, f.contextHolder );
		function h( e ) {
			return e
				? Object.entries( e ).map( function ( _eLocal ) {
						var _tLocal = R1( _eLocal, 2 ),
							_nLocal = _tLocal[ 0 ],
							_rLocal = _tLocal[ 1 ];
						return {
							label: (
								<span
									dangerouslySetInnerHTML={ {
										__html: _rLocal,
									} }
								/>
							),
							value: _nLocal,
						};
					} )
				: [];
		}
		var _ = function ( e, _tLocal2 ) {
				p.updateCurrencySettings(
					( function ( _eLocal2, _tLocal3, _nLocal2 ) {
						return (
							( _tLocal3 = ( function ( _eLocal3 ) {
								var _tLocal4 = ( function ( _eLocal4 ) {
									if (
										'object' !== b1( _eLocal4 ) ||
										! _eLocal4
									) {
										return _eLocal4;
									}
									var _tLocal5 =
										_eLocal4[ Symbol.toPrimitive ];
									if ( void 0 !== _tLocal5 ) {
										var _nLocal3 = _tLocal5.call(
											_eLocal4,
											'string'
										);
										if ( 'object' !== b1( _nLocal3 ) ) {
											return _nLocal3;
										}
										throw new TypeError(
											'@@toPrimitive must return a primitive value.'
										);
									}
									return String( _eLocal4 );
								} )( _eLocal3 );
								return 'symbol' === b1( _tLocal4 )
									? _tLocal4
									: _tLocal4 + '';
							} )( _tLocal3 ) ) in _eLocal2
								? Object.defineProperty( _eLocal2, _tLocal3, {
										value: _nLocal2,
										enumerable: ! 0,
										configurable: ! 0,
										writable: ! 0,
									} )
								: ( _eLocal2[ _tLocal3 ] = _nLocal2 ),
							_eLocal2
						 );
					} )( {}, e, {
						value: _tLocal2,
					} )
				);
			},
			w = ( 0, ReactHooks.useCallback )(
				S1(
					_1().m( function e() {
						var _tLocal6, _nLocal4, _rLocal2;
						return _1().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											return (
												( _eLocal5.p = 0 ),
												p.setLoadingSetting( ! 0 ),
												( _eLocal5.n = 1 ),
												l()( {
													path: 'ohmylms/v1/settings/currency',
												} )
											 );
										case 1:
											_tLocal6 = _eLocal5.v;
											p.setCurrencySettings( _tLocal6 );
											_nLocal4 = {};
											if ( ! (
												_tLocal6 === null ||
												_tLocal6 === void 0
											) ) {
												_tLocal6.forEach(
													function ( _eLocal6 ) {
														_nLocal4[
															_eLocal6 === null ||
															_eLocal6 === void 0
																? void 0
																: _eLocal6.id
														] =
															_eLocal6 === null ||
															_eLocal6 === void 0
																? void 0
																: _eLocal6.value;
													}
												);
											}
											p.setGlobalDataViaKey(
												'currency_settings',
												_nLocal4
											);
											p.setLoadingSetting( ! 1 );
											_eLocal5.n = 3;
											break;
										case 2:
											_eLocal5.p = 2;
											_rLocal2 = _eLocal5.v;
											showRequestError(
												'Error fetching currency data:',
												_rLocal2
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal5.p = 3 ),
												p.setLoadingSetting( ! 1 ),
												_eLocal5.f( 3 )
											 );
										case 4:
											return _eLocal5.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				),
				[]
			),
			E = ( function () {
				var e = S1(
					_1().m( function _eLocal7( _tLocal7 ) {
						var _nLocal5, _rLocal3, _aLocal, _oLocal;
						return _1().w( function ( _eLocal8 ) {
							for (;;) {
								if ( 0 === _eLocal8.n ) {
									return (
										( _rLocal3 = Object.entries(
											m === null ||
												m === void 0 ||
												null ===
													( _nLocal5 =
														m.ohmylms_currency ) ||
												void 0 === _nLocal5
												? void 0
												: _nLocal5.options
										).map( function ( _eLocal9 ) {
											var _tLocal8 = R1( _eLocal9, 2 ),
												_nLocal6 = _tLocal8[ 0 ];
											return {
												label: _tLocal8[ 1 ],
												value: _nLocal6,
											};
										} ) ),
										( _aLocal = _rLocal3.filter(
											function ( _eLocal0 ) {
												return _eLocal0.label
													.toLowerCase()
													.includes(
														_tLocal7.toLowerCase()
													);
											}
										) ),
										( _oLocal = _aLocal.map(
											function ( _eLocal1 ) {
												return {
													label: (
														<span
															dangerouslySetInnerHTML={ {
																__html: _eLocal1.label,
															} }
														/>
													),
													value: _eLocal1.value,
												};
											}
										) ),
										_eLocal8.a( 2, _oLocal )
									 );
								}
							}
						}, _eLocal7 );
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )( function () {
				w();
			}, [] ),
			(
				<React.Fragment>
					{ v }
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP
							padding={ 2 }
							marginTop={ 2.5 }
							marginBottom={ 0 }
						>
							<Nm
								customClass={ 'currency-single-settings' }
								title={ ( 0, I18n.__ )(
									'Currency',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									"Select your preferred currency. It's listed with associated countries for easy selection",
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Currency',
									'ohmylms'
								) }
								data={ h(
									m === null ||
										m === void 0 ||
										null === ( t = m.ohmylms_currency ) ||
										void 0 === t
										? void 0
										: t.options
								) }
								notFoundMessage={ ( 0, I18n.__ )(
									'Nothing Found',
									'ohmylms'
								) }
								onChange={ function ( e ) {
									return _( 'ohmylms_currency', e.value );
								} }
								staticSearch={ ! 1 }
								value={ [
									{
										label: (
											<span
												dangerouslySetInnerHTML={ {
													__html:
														m === null ||
														m === void 0 ||
														null ===
															( n =
																m.ohmylms_currency ) ||
														void 0 === n
															? void 0
															: n.options[
																	m ===
																		null ||
																	m ===
																		void 0 ||
																	null ===
																		( r =
																			m.ohmylms_currency ) ||
																	void 0 === r
																		? void 0
																		: r.value
																],
												} }
											/>
										),
										value:
											m === null ||
											m === void 0 ||
											null ===
												( a = m.ohmylms_currency ) ||
											void 0 === a
												? void 0
												: a.value,
									},
								] }
								defaultOptions={ h(
									m === null ||
										m === void 0 ||
										null === ( o = m.ohmylms_currency ) ||
										void 0 === o
										? void 0
										: o.options
								) }
								loadOptions={ E }
								isClearable={ ! 1 }
								isSearchable={ ! 0 }
								isMulti={ ! 1 }
								headerFontSize={ '16px' }
							/>
							<Nm
								className={ 'currency-single-settings' }
								title={ ( 0, I18n.__ )(
									'Currency Position',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									'Choose where the currency symbol appears relative to the price.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Position',
									'ohmylms'
								) }
								data={ h(
									m === null ||
										m === void 0 ||
										null ===
											( i = m.ohmylms_currency_pos ) ||
										void 0 === i
										? void 0
										: i.options
								) }
								notFoundMessage={ ( 0, I18n.__ )(
									'Nothing Found',
									'ohmylms'
								) }
								isMultiple={ ! 1 }
								onChange={ function ( e ) {
									return _( 'ohmylms_currency_pos', e );
								} }
								value={
									m === null ||
									m === void 0 ||
									null === ( c = m.ohmylms_currency_pos ) ||
									void 0 === c
										? void 0
										: c.value
								}
								staticSearch={ ! 0 }
								showSearch={ ! 1 }
								headerFontSize={ '16px' }
							/>
							<Pf
								title={ ( 0, I18n.__ )(
									'Thousand Separator',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									'This sets the thousands separator of displayed prices.',
									'ohmylms'
								) }
								inputType={ 'text' }
								placeholder={ ( 0, I18n.__ )(
									'Write Thousand Separator',
									'ohmylms'
								) }
								value={
									( m === null ||
									m === void 0 ||
									null ===
										( u = m.ohmylms_price_thousand_sep ) ||
									void 0 === u
										? void 0
										: u.value ) || ''
								}
								className={
									'currency-single-settings ohmylms-separator-input-card'
								}
								onChange={ function ( e ) {
									return _( 'ohmylms_price_thousand_sep', e );
								} }
								headerFontSize={ '16px' }
							/>
							<Pf
								title={ ( 0, I18n.__ )(
									'Decimal Separator',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Write Decimal Separator',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									'This sets the decimal separator of displayed prices.',
									'ohmylms'
								) }
								inputType={ 'text' }
								value={
									( m === null ||
									m === void 0 ||
									null ===
										( s = m.ohmylms_price_decimal_sep ) ||
									void 0 === s
										? void 0
										: s.value ) || ''
								}
								className={
									'currency-single-settings ohmylms-separator-input-card'
								}
								onChange={ function ( e ) {
									return _( 'ohmylms_price_decimal_sep', e );
								} }
								headerFontSize={ '16px' }
							/>
							<Pf
								title={ ( 0, I18n.__ )(
									'Number of Decimals',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									'This sets the number of decimal points shown in the displayed prices.',
									'ohmylms'
								) }
								className={ 'currency-single-settings' }
								placeholder={ '2' }
								inputType={ 'number' }
								showDivider={ ! 1 }
								value={
									( m === null ||
									m === void 0 ||
									null ===
										( d = m.ohmylms_price_num_decimals ) ||
									void 0 === d
										? void 0
										: d.value ) || ''
								}
								onChange={ function ( e ) {
									return _( 'ohmylms_price_num_decimals', e );
								} }
								spacerMarginBottom={ 0 }
								headerFontSize={ '16px' }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
