import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement, useId } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createTaxSettings( readRuntime ) {
	return function TaxSettings() {
		const _controlId = useId();
		const {
			D1,
			F1,
			I: Controls,
			I1,
			Kt,
			M1,
			N1,
			Nm,
			Pf,
			React,
			T: StoreModule,
			W1,
			b: I18n,
			g: ReactHooks,
			k1,
			l,
			nf,
			y: WordPressData,
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
			m,
			p,
			f = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getTaxSettings();
			}, [] ),
			v = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			h = useNotifications(),
			_ = ( h.openNotificationWithIcon, h.contextHolder ),
			w =
				( f === null ||
				f === void 0 ||
				null === ( t = f.ohmylms_countries ) ||
				void 0 === t
					? void 0
					: t.value ) || [],
			E =
				( f === null ||
				f === void 0 ||
				null === ( n = f.ohmylms_states ) ||
				void 0 === n
					? void 0
					: n.value ) || {},
			S = N1(
				( 0, ReactHooks.useState )( {
					country: '',
					state: '',
					countryWide: ! 1,
					rate: '',
				} ),
				2
			),
			R = ( S[ 0 ], S[ 1 ], N1( ( 0, ReactHooks.useState )( [] ), 2 ) ),
			x = R[ 0 ],
			C = R[ 1 ],
			P = N1( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			O = ( P[ 0 ], P[ 1 ], N1( ( 0, ReactHooks.useState )( [] ), 2 ) ),
			k = O[ 0 ],
			j = O[ 1 ],
			A = function ( e, _tLocal ) {
				v.updateTaxSettings(
					F1( {}, e, {
						value: _tLocal,
					} )
				);
			},
			M = function ( e, _tLocal2, _nLocal ) {
				j( function ( _rLocal ) {
					var _aLocal = _rLocal.map( function ( _rLocal2 ) {
						if ( _rLocal2.id === e ) {
							var _aLocal2 = I1(
								I1( {}, _rLocal2 ),
								{},
								F1( {}, _tLocal2, _nLocal )
							);
							return (
								'country' === _tLocal2 &&
									( ( _aLocal2.state = '' ),
									( _aLocal2.countryWide = ! 0 ) ),
								'state' === _tLocal2 &&
									( _aLocal2.countryWide =
										! _nLocal || '' === _nLocal ),
								'countryWide' === _tLocal2 &&
									! 0 === _nLocal &&
									( _aLocal2.state = '' ),
								_aLocal2
							 );
						}
						return _rLocal2;
					} );
					return (
						v.updateTaxSettings( {
							ohmylms_new_tax_rates: {
								value: _aLocal,
							},
						} ),
						_aLocal
					 );
				} );
			},
			F = ( 0, ReactHooks.useCallback )(
				M1(
					k1().m( function e() {
						var _tLocal3, _nLocal2, _rLocal3, _aLocal3;
						return k1().w(
							function ( _eLocal ) {
								for (;;) {
									switch ( ( _eLocal.p = _eLocal.n ) ) {
										case 0:
											return (
												( _eLocal.p = 0 ),
												v.setLoadingSetting( ! 0 ),
												( _eLocal.n = 1 ),
												l()( {
													path: 'ohmylms/v1/settings/tax',
												} )
											 );
										case 1:
											_tLocal3 = _eLocal.v;
											v.setTaxSettings( _tLocal3 );
											_nLocal2 = _tLocal3.find(
												function ( _eLocal2 ) {
													return (
														'ohmylms_existing_tax_rates' ===
														_eLocal2.id
													);
												}
											);
											_rLocal3 = _tLocal3.find(
												function ( _eLocal3 ) {
													return (
														'ohmylms_new_tax_rates' ===
														_eLocal3.id
													);
												}
											);
											if (
												_nLocal2 !== null &&
												_nLocal2 !== void 0 &&
												_nLocal2.value &&
												Array.isArray( _nLocal2.value )
											) {
												C( _nLocal2.value );
											}
											if (
												_rLocal3 !== null &&
												_rLocal3 !== void 0 &&
												_rLocal3.value &&
												Array.isArray( _rLocal3.value )
											) {
												j( _rLocal3.value );
											}
											v.setLoadingSetting( ! 1 );
											_eLocal.n = 3;
											break;
										case 2:
											_eLocal.p = 2;
											_aLocal3 = _eLocal.v;
											showRequestError(
												'Error fetching tax data:',
												_aLocal3
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal.p = 3 ),
												v.setLoadingSetting( ! 1 ),
												_eLocal.f( 3 )
											 );
										case 4:
											return _eLocal.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				),
				[ v ]
			);
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					F();
				},
				[ F ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					var e, _tLocal4;
					if (
						f !== null &&
						f !== void 0 &&
						null !== ( e = f.ohmylms_existing_tax_rates ) &&
						void 0 !== e &&
						e.value &&
						Array.isArray( f.ohmylms_existing_tax_rates.value )
					) {
						C( f.ohmylms_existing_tax_rates.value );
					}
					if (
						f !== null &&
						f !== void 0 &&
						null !== ( _tLocal4 = f.ohmylms_new_tax_rates ) &&
						void 0 !== _tLocal4 &&
						_tLocal4.value &&
						Array.isArray( f.ohmylms_new_tax_rates.value )
					) {
						j( f.ohmylms_new_tax_rates.value );
					}
				},
				[
					f === null ||
					f === void 0 ||
					null === ( r = f.ohmylms_existing_tax_rates ) ||
					void 0 === r
						? void 0
						: r.value,
					f === null ||
					f === void 0 ||
					null === ( a = f.ohmylms_new_tax_rates ) ||
					void 0 === a
						? void 0
						: a.value,
				]
			),
			(
				<React.Fragment>
					{ _ }
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP
							marginBottom={ 0 }
							marginTop={ 2.5 }
							padding={ 2 }
						>
							<Controls.FlexWP gap={ 4 } direction={ 'column' }>
								<Kt
									title={ ( 0, I18n.__ )(
										'Enable Tax Calculations',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'When taxes are enabled, rates are applied based on the customer’s address entered at checkout.',
										'ohmylms'
									) }
									isChecked={
										'yes' ===
										( f === null ||
										f === void 0 ||
										null ===
											( o = f.ohmylms_tax_enabled ) ||
										void 0 === o
											? void 0
											: o.value )
									}
									onChange={ function ( e ) {
										return A(
											'ohmylms_tax_enabled',
											e ? 'yes' : 'no'
										);
									} }
									customClass={
										'ohmylms-tax-enable-switcher'
									}
									isDefaultStyle={ ! 0 }
									align={ 'flex-start' }
									conditionalChild={
										<React.Fragment>
											<Controls.SpacerWP
												marginBottom={ 4 }
											/>
											<Controls.FlexWP
												direction={ 'column' }
												gap={ 4 }
												justify={ 'flex-start' }
												align={ 'flex-start' }
											>
												<Controls.CardWP
													isBorderless={ ! 0 }
													variant={ 'secondary' }
													padding={ '16px' }
													fullWidth={ ! 0 }
												>
													<Pf
														title={ ( 0, I18n.__ )(
															'Tax Label',
															'ohmylms'
														) }
														description={ ( 0,
														I18n.__ )(
															'Label to display for tax (e.g., "VAT", "GST", "Sales Tax").',
															'ohmylms'
														) }
														value={
															( f === null ||
															f === void 0 ||
															null ===
																( i =
																	f.ohmylms_tax_label ) ||
															void 0 === i
																? void 0
																: i.value ) ||
															'Tax'
														}
														onChange={ function (
															e
														) {
															return A(
																'ohmylms_tax_label',
																e
															);
														} }
														placeholder={ ( 0,
														I18n.__ )(
															'Tax',
															'ohmylms'
														) }
														headerFontSize={
															'16px'
														}
													/>
													<Nm
														title={ ( 0, I18n.__ )(
															'Prices Include Tax',
															'ohmylms'
														) }
														description={ ( 0,
														I18n.__ )(
															'This controls if entered prices include tax or not.',
															'ohmylms'
														) }
														value={
															( f === null ||
															f === void 0 ||
															null ===
																( c =
																	f.ohmylms_prices_include_tax ) ||
															void 0 === c
																? void 0
																: c.value ) ||
															'no'
														}
														onChange={ function (
															e
														) {
															return A(
																'ohmylms_prices_include_tax',
																e
															);
														} }
														staticSearch={ ! 0 }
														headerFontSize={
															'16px'
														}
														options={ [
															{
																label: ( 0,
																I18n.__ )(
																	'Yes, I will enter prices inclusive of tax',
																	'ohmylms'
																),
																value: 'yes',
															},
															{
																label: ( 0,
																I18n.__ )(
																	'No, I will enter prices exclusive of tax',
																	'ohmylms'
																),
																value: 'no',
															},
														] }
													/>
												</Controls.CardWP>
												<Controls.CardWP
													isBorderless={ ! 0 }
													variant={ 'secondary' }
													padding={ '16px' }
													fullWidth={ ! 0 }
												>
													<Controls.SpacerWP
														padding={ 0 }
														marginBottom={ 0 }
													>
														<Kt
															title={ ( 0,
															I18n.__ )(
																'Enable EU VAT',
																'ohmylms'
															) }
															description={ ( 0,
															I18n.__ )(
																'When this is checked, VAT taxes will be calculated for any customers who are located in the European Union. The plugin comes with the current standard VAT rate for each EU country. You can change these as required in the Tax Rates section below.',
																'ohmylms'
															) }
															isChecked={
																'yes' ===
																( f === null ||
																f === void 0 ||
																null ===
																	( u =
																		f.ohmylms_eu_vat_enabled ) ||
																void 0 === u
																	? void 0
																	: u.value )
															}
															onChange={ function (
																e
															) {
																return A(
																	'ohmylms_eu_vat_enabled',
																	e
																		? 'yes'
																		: 'no'
																);
															} }
															headerFontSize={
																'16px'
															}
															isDefaultStyle={
																! 0
															}
															align={
																'flex-start'
															}
															variant={
																'secondary'
															}
														/>
														{ 'yes' ===
															( f === null ||
															f === void 0 ||
															null ===
																( s =
																	f.ohmylms_eu_vat_enabled ) ||
															void 0 === s
																? void 0
																: s.value ) && (
															<React.Fragment>
																<Kt
																	title={ ( 0,
																	I18n.__ )(
																		'Disable VAT Number Validation',
																		'ohmylms'
																	) }
																	description={ ( 0,
																	I18n.__ )(
																		'When this option is checked, the VAT number will not be validated by VIES online service.',
																		'ohmylms'
																	) }
																	isChecked={
																		'yes' ===
																		( f ===
																			null ||
																		f ===
																			void 0 ||
																		null ===
																			( d =
																				f.ohmylms_disable_vat_validation ) ||
																		void 0 ===
																			d
																			? void 0
																			: d.value )
																	}
																	onChange={ function (
																		e
																	) {
																		return A(
																			'ohmylms_disable_vat_validation',
																			e
																				? 'yes'
																				: 'no'
																		);
																	} }
																	headerFontSize={
																		'16px'
																	}
																	isDefaultStyle={
																		! 0
																	}
																	align={
																		'flex-start'
																	}
																	variant={
																		'secondary'
																	}
																/>
																<Pf
																	title={ ( 0,
																	I18n.__ )(
																		'VAT Number Field Label',
																		'ohmylms'
																	) }
																	description={ ( 0,
																	I18n.__ )(
																		'The label that appears at checkout for the VAT number field.',
																		'ohmylms'
																	) }
																	value={
																		( f ===
																			null ||
																		f ===
																			void 0 ||
																		null ===
																			( m =
																				f.ohmylms_vat_number_label ) ||
																		void 0 ===
																			m
																			? void 0
																			: m.value ) ||
																		''
																	}
																	onChange={ function (
																		e
																	) {
																		return A(
																			'ohmylms_vat_number_label',
																			e
																		);
																	} }
																	placeholder={ ( 0,
																	I18n.__ )(
																		'VAT Number',
																		'ohmylms'
																	) }
																	headerFontSize={
																		'16px'
																	}
																/>
															</React.Fragment>
														) }
													</Controls.SpacerWP>
												</Controls.CardWP>
												<Controls.CardWP
													isBorderless={ ! 0 }
													variant={ 'secondary' }
													padding={ '16px' }
													style={ {
														width: '100%',
													} }
												>
													<Controls.SpacerWP
														padding={ 4 }
														marginBottom={ 0 }
													>
														<Controls.HeadingWP
															level={ 4 }
															className={
																'tax-section-title'
															}
															size={ '16px' }
														>
															{ ( 0, I18n.__ )(
																'Set up Tax Rates',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														<Controls.TextWP
															className={
																'tax-description'
															}
															color={ '#687784' }
														>
															{ ( 0, I18n.__ )(
																'Configure tax rates for different countries and regions. You can add multiple tax rates for different locations.',
																'ohmylms'
															) }
														</Controls.TextWP>
														<Controls.SpacerWP
															marginBottom={ 4 }
														/>
														<div
															className={
																'ohmylms-table-wrapper ohmylms-tax-table'
															}
															style={ {
																position:
																	'relative',
															} }
														>
															<table
																className={
																	'ohmylms-table'
																}
															>
																<thead
																	className={
																		'ohmylms-table-thead'
																	}
																>
																	<tr
																		className={
																			'ohmylms-table-header-row'
																		}
																	>
																		<th
																			className={
																				'ohmylms-th tax-country'
																			}
																		>
																			{ ( 0,
																			I18n.__ )(
																				'Country',
																				'ohmylms'
																			) }
																		</th>
																		<th
																			className={
																				'ohmylms-th tax-state-code'
																			}
																		>
																			{ ( 0,
																			I18n.__ )(
																				'State Code',
																				'ohmylms'
																			) }
																		</th>
																		<th
																			className={
																				'ohmylms-th tax-country-wide'
																			}
																		>
																			{ ( 0,
																			I18n.__ )(
																				'Country Wide',
																				'ohmylms'
																			) }
																		</th>
																		<th
																			className={
																				'ohmylms-th tax-rate'
																			}
																		>
																			{ ( 0,
																			I18n.__ )(
																				'Rate',
																				'ohmylms'
																			) }
																		</th>
																		<th
																			className={
																				'ohmylms-th tax-action'
																			}
																		>
																			<div
																				className={
																					'tax-action'
																				}
																			/>
																		</th>
																	</tr>
																</thead>
																<tbody
																	className={
																		'ohmylms-table-tbody'
																	}
																>
																	{ k.map(
																		function (
																			e
																		) {
																			return (
																				<tr
																					key={
																						e.id
																					}
																					className={
																						'ohmylms-tr tax-rate-form'
																					}
																				>
																					<td
																						className={
																							'ohmylms-td tax-country'
																						}
																					>
																						<select
																							value={
																								e.country
																							}
																							onChange={ function (
																								_tLocal5
																							) {
																								return M(
																									e.id,
																									'country',
																									_tLocal5
																										.target
																										.value
																								);
																							} }
																							className={
																								'tax-country'
																							}
																						>
																							<option
																								value={
																									''
																								}
																							>
																								{ ( 0,
																								I18n.__ )(
																									'Select Country',
																									'ohmylms'
																								) }
																							</option>
																							{ w.map(
																								function (
																									_eLocal4
																								) {
																									return (
																										<option
																											key={
																												_eLocal4.value
																											}
																											value={
																												_eLocal4.value
																											}
																										>
																											{
																												_eLocal4.label
																											}
																										</option>
																									);
																								}
																							) }
																						</select>
																					</td>
																					<td
																						className={
																							'ohmylms-td tax-state-code'
																						}
																					>
																						<Controls.TooltipWP
																							text={
																								e !==
																									null &&
																								e !==
																									void 0 &&
																								e.countryWide
																									? ( 0,
																										I18n.__ )(
																											'To enable on specific state, deselect "Apply to whole country"',
																											'ohmylms'
																										)
																									: ( 0,
																										I18n.__ )(
																											'Select a state',
																											'ohmylms'
																										)
																							}
																							className={
																								'tax-state-code'
																							}
																						>
																							<select
																								value={
																									e.state
																								}
																								onChange={ function (
																									_tLocal6
																								) {
																									return M(
																										e.id,
																										'state',
																										_tLocal6
																											.target
																											.value
																									);
																								} }
																								disabled={
																									e.countryWide ||
																									! e.country
																								}
																								style={ {
																									width: '100%',
																								} }
																							>
																								<option
																									value={
																										''
																									}
																								>
																									{ ( 0,
																									I18n.__ )(
																										'——',
																										'ohmylms'
																									) }
																								</option>
																								{ e.country &&
																									E[
																										e
																											.country
																									] &&
																									E[
																										e
																											.country
																									].map(
																										function (
																											_eLocal5
																										) {
																											return (
																												<option
																													key={
																														_eLocal5.value
																													}
																													value={
																														_eLocal5.value
																													}
																												>
																													{
																														_eLocal5.label
																													}
																												</option>
																											);
																										}
																									) }
																							</select>
																						</Controls.TooltipWP>
																					</td>
																					<td
																						className={
																							'ohmylms-td tax-country-wide'
																						}
																					>
																						<label
																							className={
																								'tax-rate-checkbox-label tax-country-wide'
																							}
																							htmlFor={
																								_controlId +
																								'-field-1' +
																								'-' +
																								e.id
																							}
																						>
																							<input
																								type={
																									'checkbox'
																								}
																								checked={
																									e.countryWide
																								}
																								onChange={ function (
																									_tLocal7
																								) {
																									M(
																										e.id,
																										'countryWide',
																										_tLocal7
																											.target
																											.checked
																									);
																									if (
																										_tLocal7
																											.target
																											.checked
																									) {
																										M(
																											e.id,
																											'state',
																											''
																										);
																									}
																								} }
																								disabled={
																									! e.country
																								}
																								id={
																									_controlId +
																									'-field-1' +
																									'-' +
																									e.id
																								}
																							/>
																							<span
																								className={
																									e.country
																										? ''
																										: 'tax-rate-checkbox-disabled'
																								}
																							>
																								{ ( 0,
																								I18n.__ )(
																									'Apply to whole country',
																									'ohmylms'
																								) }
																							</span>
																						</label>
																					</td>
																					<td
																						className={
																							'ohmylms-td tax-rate'
																						}
																					>
																						<Controls.InputNumberWP
																							value={
																								e.rate
																							}
																							onChange={ function (
																								_tLocal8
																							) {
																								M(
																									e.id,
																									'rate',
																									( () => {
																										if (
																											0 >
																											_tLocal8
																										) {
																											return 0;
																										}
																										if (
																											100 >=
																											_tLocal8
																										) {
																											return _tLocal8;
																										}
																										return 100;
																									} )()
																								);
																							} }
																							onKeyDown={ function (
																								_eLocal6
																							) {
																								if ( ! (
																									'-' !==
																										_eLocal6.key &&
																									'+' !==
																										_eLocal6.key &&
																									'e' !==
																										_eLocal6.key &&
																									'E' !==
																										_eLocal6.key
																								) ) {
																									_eLocal6.preventDefault();
																								}
																							} }
																							placeholder={
																								'0.00'
																							}
																							step={
																								'0.01'
																							}
																							min={
																								'0'
																							}
																							max={
																								100
																							}
																							suffix={
																								'%'
																							}
																							className={
																								'tax-rate'
																							}
																						/>
																					</td>
																					<td
																						className={
																							'ohmylms-td tax-action'
																						}
																					>
																						<div
																							className={
																								'tax-rate-remove-container tax-action'
																							}
																						>
																							<Controls.ButtonWP
																								type={
																									'link'
																								}
																								size={
																									'small'
																								}
																								onClick={ function () {
																									return (
																										( _tLocal9 =
																											e.id ),
																										void j(
																											function (
																												_eLocal7
																											) {
																												var _nLocal3 =
																													_eLocal7.filter(
																														function (
																															_eLocal8
																														) {
																															return (
																																_eLocal8.id !==
																																_tLocal9
																															);
																														}
																													);
																												return (
																													v.updateTaxSettings(
																														{
																															ohmylms_new_tax_rates:
																																{
																																	value: _nLocal3,
																																},
																														}
																													),
																													_nLocal3
																												 );
																											}
																										)
																									 );
																									var _tLocal9;
																								} }
																								className={
																									'tax-rate-remove-btn'
																								}
																								title={ ( 0,
																								I18n.__ )(
																									'Cancel',
																									'ohmylms'
																								) }
																							>
																								<svg
																									width={
																										'16'
																									}
																									height={
																										'16'
																									}
																									viewBox={
																										'0 0 24 24'
																									}
																									fill={
																										'currentColor'
																									}
																								>
																									<path
																										d={
																											'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'
																										}
																									/>
																								</svg>
																							</Controls.ButtonWP>
																						</div>
																					</td>
																				</tr>
																			);
																		}
																	) }
																</tbody>
															</table>
															{ 0 === x.length &&
																0 ===
																	k.length && (
																	<div
																		className={
																			'tax-rates-no-data'
																		}
																	>
																		{ ( 0,
																		I18n.__ )(
																			'No rates found.',
																			'ohmylms'
																		) }
																	</div>
																) }
														</div>
														{ x.length > 0 && (
															<div
																className={
																	'existing-tax-rates'
																}
															>
																<Controls.TextWP
																	className={
																		'existing-tax-rates-title'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Current Tax Rates:',
																		'ohmylms'
																	) }
																	{ ' (' }
																	{ x.length }
																	{ ')' }
																</Controls.TextWP>
																{ x.map(
																	function (
																		e
																	) {
																		return (
																			<div
																				key={
																					e.id
																				}
																				className={
																					'existing-tax-rate'
																				}
																			>
																				<div
																					className={
																						'existing-tax-rate-country'
																					}
																				>
																					{
																						( ( _tLocal0 =
																							e.country ),
																						Boolean(
																							( _nLocal4 =
																								w.find(
																									function (
																										_eLocal9
																									) {
																										return (
																											_eLocal9.value ===
																											_tLocal0
																										);
																									}
																								) )
																						)
																							? _nLocal4.label
																							: _tLocal0 )
																					}
																				</div>
																				<div>
																					{ ( function (
																						_eLocal0,
																						_tValue
																					) {
																						var _nValue =
																							E[
																								_eLocal0
																							];
																						if (
																							! _nValue ||
																							! _tValue
																						) {
																							return (
																								_tValue ||
																								'——'
																							);
																						}
																						var _rLocal4 =
																							_nValue.find(
																								function (
																									_eLocal1
																								) {
																									return (
																										_eLocal1.value ===
																										_tValue
																									);
																								}
																							);
																						return _rLocal4
																							? _rLocal4.label
																							: _tValue;
																					} )(
																						e.country,
																						e.state
																					) }
																				</div>
																				<div>
																					<span
																						className={ 'existing-tax-rate-badge '.concat(
																							e.countryWide
																								? 'existing-tax-rate-badge--yes'
																								: 'existing-tax-rate-badge--no'
																						) }
																					>
																						{ e.countryWide
																							? ( 0,
																								I18n.__ )(
																									'Yes',
																									'ohmylms'
																								)
																							: ( 0,
																								I18n.__ )(
																									'No',
																									'ohmylms'
																								) }
																					</span>
																				</div>
																				<div
																					className={
																						'existing-tax-rate-percentage'
																					}
																				>
																					{
																						e.rate
																					}
																					{
																						'%'
																					}
																				</div>
																				<Controls.ButtonWP
																					type={
																						'link'
																					}
																					size={
																						'small'
																					}
																					onClick={ function () {
																						return (
																							( _tValue2 =
																								e.id ),
																							void C(
																								function (
																									_eLocal10
																								) {
																									var _nValue2 =
																										_eLocal10.filter(
																											function (
																												_eLocal11
																											) {
																												return (
																													_eLocal11.id !==
																													_tValue2
																												);
																											}
																										);
																									return (
																										v.updateTaxSettings(
																											{
																												ohmylms_existing_tax_rates:
																													{
																														value: _nValue2,
																													},
																											}
																										),
																										_nValue2
																									 );
																								}
																							)
																						 );
																						var _tValue2;
																					} }
																					className={
																						'tax-rate-remove-btn'
																					}
																					title={ ( 0,
																					I18n.__ )(
																						'Remove this tax rate',
																						'ohmylms'
																					) }
																				>
																					<svg
																						width={
																							'16'
																						}
																						height={
																							'16'
																						}
																						viewBox={
																							'0 0 24 24'
																						}
																						fill={
																							'currentColor'
																						}
																					>
																						<path
																							d={
																								'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'
																							}
																						/>
																					</svg>
																				</Controls.ButtonWP>
																			</div>
																		);
																		var _tLocal0,
																			_nLocal4;
																	}
																) }
															</div>
														) }
														<div
															className={
																'add-tax-rate-container'
															}
														>
															<Controls.ButtonWP
																variant={
																	'secondary'
																}
																onClick={ function () {
																	var e = {
																		id:
																			Date.now() +
																			Math.random(),
																		country:
																			'',
																		state: '',
																		countryWide:
																			! 1,
																		rate: '',
																	};
																	j(
																		function (
																			_tLocal1
																		) {
																			var _nLocal5 =
																				[].concat(
																					( function (
																						_eLocal12
																					) {
																						return (
																							( function (
																								_eLocal13
																							) {
																								if (
																									Array.isArray(
																										_eLocal13
																									)
																								) {
																									return W1(
																										_eLocal13
																									);
																								}
																							} )(
																								_eLocal12
																							) ||
																							( function (
																								_eLocal14
																							) {
																								if (
																									( 'undefined' !==
																										typeof Symbol &&
																										( (
																											_nullableValue
																										) =>
																											_nullableValue !==
																												null &&
																											_nullableValue !==
																												void 0 )(
																											_eLocal14[
																												Symbol
																													.iterator
																											]
																										) ) ||
																									( (
																										_nullableValue2
																									) =>
																										_nullableValue2 !==
																											null &&
																										_nullableValue2 !==
																											void 0 )(
																										_eLocal14[
																											'@@iterator'
																										]
																									)
																								) {
																									return Array.from(
																										_eLocal14
																									);
																								}
																							} )(
																								_eLocal12
																							) ||
																							D1(
																								_eLocal12
																							) ||
																							( function () {
																								throw new TypeError(
																									'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
																								);
																							} )()
																						);
																					} )(
																						_tLocal1
																					),
																					[
																						e,
																					]
																				);
																			return (
																				v.updateTaxSettings(
																					{
																						ohmylms_new_tax_rates:
																							{
																								value: _nLocal5,
																							},
																					}
																				),
																				_nLocal5
																			 );
																		}
																	);
																} }
																className={
																	'add-tax-rate-btn'
																}
															>
																{ React.createElement(
																	nf,
																	null
																) }
																{ ( 0,
																I18n.__ )(
																	'Add Tax Rate',
																	'ohmylms'
																) }
															</Controls.ButtonWP>
														</div>
														<div
															className={
																'fallback-tax-rate-section'
															}
														>
															<Pf
																title={ ( 0,
																I18n.__ )(
																	'Fallback Tax Rate',
																	'ohmylms'
																) }
																description={ ( 0,
																I18n.__ )(
																	'Customers not in a specific rate will be charged this tax rate. Enter a percentage, such as 6.5 for 6.5%.',
																	'ohmylms'
																) }
																inputType={
																	'number'
																}
																value={
																	( f ===
																		null ||
																	f ===
																		void 0 ||
																	null ===
																		( p =
																			f.ohmylms_fallback_tax_rate ) ||
																	void 0 === p
																		? void 0
																		: p.value ) ||
																	''
																}
																onChange={ function (
																	e
																) {
																	return A(
																		'ohmylms_fallback_tax_rate',
																		e
																	);
																} }
																placeholder={
																	'0.00'
																}
																min={ '0' }
																max={ '100' }
																step={ '0.01' }
																suffix={ '%' }
																headerFontSize={
																	'16px'
																}
															/>
														</div>
													</Controls.SpacerWP>
												</Controls.CardWP>
											</Controls.FlexWP>
										</React.Fragment>
									}
								/>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
