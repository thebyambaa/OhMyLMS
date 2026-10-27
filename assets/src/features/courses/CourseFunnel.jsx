import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseFunnel( readRuntime ) {
	return function CourseFunnel() {
		const {
			$e,
			BH,
			DH,
			FH,
			Ge,
			HH,
			I: Controls,
			Jt,
			LH,
			NH,
			React,
			T: StoreModule,
			VH,
			We,
			b: I18n,
			df,
			g: ReactHooks,
			l,
			q,
			y: WordPressData,
		} = readRuntime();
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			n = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourse();
			}, [] ),
			r = true,
			a = LH(
				( 0, ReactHooks.useState )( [
					{
						label: ( 0, I18n.__ )( 'Select type', 'ohmylms' ),
						value: '',
					},
					{
						label: ( 0, I18n.__ )( 'Upsell', 'ohmylms' ),
						value: 'upsell',
					},
					{
						label: ( 0, I18n.__ )( 'Downsell', 'ohmylms' ),
						value: 'downsell',
					},
				] ),
				1
			)[ 0 ],
			o = LH(
				( 0, ReactHooks.useState )( [
					{
						label: ( 0, I18n.__ )( 'Select action', 'ohmylms' ),
						value: '',
					},
					{
						label: ( 0, I18n.__ )( 'Next Step', 'ohmylms' ),
						value: 'next_step',
					},
					{
						label: ( 0, I18n.__ )(
							'Final Thank You Page',
							'ohmylms'
						),
						value: 'thank_you_page',
					},
				] ),
				1
			)[ 0 ],
			i = LH(
				( 0, ReactHooks.useState )( [
					{
						label: ( 0, I18n.__ )( 'No discount', 'ohmylms' ),
						value: 'no_discount',
						disabled: ! r,
					},
					{
						label: ( 0, I18n.__ )(
							'Discount percentage',
							'ohmylms'
						),
						value: 'percentage',
						disabled: ! r,
					},
					{
						label: ( 0, I18n.__ )( 'Discount amount', 'ohmylms' ),
						value: 'amount',
						disabled: ! r,
					},
				] ),
				1
			)[ 0 ],
			c = LH(
				( 0, ReactHooks.useState )( [
					{
						label: ( 0, I18n.__ )( 'Select offer type', 'ohmylms' ),
						value: '',
					},
					{
						label: ( 0, I18n.__ )( 'Course', 'ohmylms' ),
						value: 'course',
					},
					{
						label: ( 0, I18n.__ )( 'Membership', 'ohmylms' ),
						value: 'membership',
					},
				] ),
				1
			)[ 0 ],
			u = LH(
				( 0, ReactHooks.useState )(
					( n === null || n === void 0 ? void 0 : n.funnel_steps ) ||
						[]
				),
				2
			),
			s = u[ 0 ],
			d = u[ 1 ],
			m = LH( ( 0, ReactHooks.useState )( null ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ];
		( 0, ReactHooks.useEffect )(
			function () {
				if ( s.length > 0 && ! p ) {
					f( s[ 0 ].step_id );
				} else if ( 0 === s.length ) {
					f( null );
				}
			},
			[ s, p ]
		);
		var v = ( function () {
				var e = BH(
					DH().m( function _eLocal( _tLocal ) {
						var _nLocal, _rLocal, _aLocal;
						return DH().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											return (
												( _eLocal2.p = 0 ),
												( _eLocal2.n = 1 ),
												l()( {
													path: '/ohmylms/v1/page/search?value='.concat(
														_tLocal
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											return (
												( _nLocal = _eLocal2.v ),
												( _rLocal = Object.entries(
													_nLocal || {}
												).map( function ( _eLocal3 ) {
													var _tLocal2 = LH(
														_eLocal3,
														2
													);
													return {
														value: _tLocal2[ 0 ],
														label: _tLocal2[ 1 ],
													};
												} ) ),
												_eLocal2.a( 2, _rLocal )
											 );
										case 2:
											return (
												( _eLocal2.p = 2 ),
												( _aLocal = _eLocal2.v ),
												showRequestError(
													'Error fetching pages:',
													_aLocal
												),
												_eLocal2.a( 2, [] )
											 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			_ = ( function () {
				var e = BH(
					DH().m( function _eLocal4( _tLocal4 ) {
						var _nLocal2, _rLocal2;
						return DH().w( function ( _eLocal5 ) {
							for (;;) {
								switch ( _eLocal5.n ) {
									case 0:
										if ( ! ( _tLocal4.length >= 3 ) ) {
											_eLocal5.n = 4;
											break;
										}
										return (
											( _eLocal5.n = 1 ),
											v( _tLocal4 || '' )
										 );
									case 1:
										if (
											0 !==
											( _nLocal2 = _eLocal5.v ).length
										) {
											_eLocal5.n = 2;
											break;
										}
										return _eLocal5.a( 2, [] );
									case 2:
										return (
											( _rLocal2 =
												_nLocal2 === null ||
												_nLocal2 === void 0
													? void 0
													: _nLocal2.map(
															function (
																_eLocal6
															) {
																return {
																	label: Ge(
																		_eLocal6 ===
																			null ||
																			_eLocal6 ===
																				void 0
																			? void 0
																			: _eLocal6.label
																	),
																	value:
																		_eLocal6 ===
																			null ||
																		_eLocal6 ===
																			void 0
																			? void 0
																			: _eLocal6.value,
																};
															}
														) ),
											_eLocal5.a( 2, _rLocal2 )
										 );
									case 3:
										_eLocal5.n = 5;
										break;
									case 4:
										return _eLocal5.a( 2, [] );
									case 5:
										return _eLocal5.a( 2 );
								}
							}
						}, _eLocal4 );
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			w = ( function () {
				var e = BH(
					DH().m( function _eLocal7( _tLocal6 ) {
						var _nLocal3, _rLocal3, _aLocal2;
						return DH().w(
							function ( _eLocal8 ) {
								for (;;) {
									switch ( ( _eLocal8.p = _eLocal8.n ) ) {
										case 0:
											return (
												( _eLocal8.p = 0 ),
												( _eLocal8.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses?search='.concat(
														_tLocal6
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											return (
												( _nLocal3 = _eLocal8.v ),
												( _rLocal3 = _nLocal3.map(
													function ( _eLocal9 ) {
														return {
															value:
																_eLocal9 ===
																	null ||
																_eLocal9 ===
																	void 0
																	? void 0
																	: _eLocal9.id,
															label:
																_eLocal9 ===
																	null ||
																_eLocal9 ===
																	void 0
																	? void 0
																	: _eLocal9.name,
														};
													}
												) ),
												_eLocal8.a( 2, _rLocal3 )
											 );
										case 2:
											return (
												( _eLocal8.p = 2 ),
												( _aLocal2 = _eLocal8.v ),
												showRequestError(
													'Error fetching courses:',
													_aLocal2
												),
												_eLocal8.a( 2, [] )
											 );
									}
								}
							},
							_eLocal7,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )(),
			E = ( function () {
				var e = BH(
					DH().m( function _eLocal0( _tLocal8 ) {
						var _nLocal4, _rLocal4;
						return DH().w( function ( _eLocal1 ) {
							for (;;) {
								switch ( _eLocal1.n ) {
									case 0:
										if ( ! ( _tLocal8.length >= 3 ) ) {
											_eLocal1.n = 4;
											break;
										}
										return (
											( _eLocal1.n = 1 ),
											w( _tLocal8 )
										 );
									case 1:
										if (
											0 !==
											( _nLocal4 = _eLocal1.v ).length
										) {
											_eLocal1.n = 2;
											break;
										}
										return _eLocal1.a( 2, [] );
									case 2:
										return (
											( _rLocal4 =
												_nLocal4 === null ||
												_nLocal4 === void 0
													? void 0
													: _nLocal4.map(
															function (
																_eLocal10
															) {
																return {
																	label: Ge(
																		_eLocal10 ===
																			null ||
																			_eLocal10 ===
																				void 0
																			? void 0
																			: _eLocal10.label
																	),
																	value:
																		_eLocal10 ===
																			null ||
																		_eLocal10 ===
																			void 0
																			? void 0
																			: _eLocal10.value,
																};
															}
														) ),
											_eLocal1.a( 2, _rLocal4 )
										 );
									case 3:
										_eLocal1.n = 5;
										break;
									case 4:
										return _eLocal1.a( 2, [] );
									case 5:
										return _eLocal1.a( 2 );
								}
							}
						}, _eLocal0 );
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			S = ( function () {
				var e = BH(
					DH().m( function _eLocal11( _tLocal0 ) {
						var _nLocal5, _rLocal5, _aLocal3;
						return DH().w(
							function ( _eLocal12 ) {
								for (;;) {
									switch ( ( _eLocal12.p = _eLocal12.n ) ) {
										case 0:
											return (
												( _eLocal12.p = 0 ),
												( _eLocal12.n = 1 ),
												l()( {
													path: '/ohmylms/v1/membership?search='.concat(
														_tLocal0
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											return (
												( _nLocal5 = _eLocal12.v ),
												( _rLocal5 = _nLocal5.map(
													function ( _eLocal13 ) {
														return {
															value:
																_eLocal13 ===
																	null ||
																_eLocal13 ===
																	void 0
																	? void 0
																	: _eLocal13.id,
															label:
																_eLocal13 ===
																	null ||
																_eLocal13 ===
																	void 0
																	? void 0
																	: _eLocal13.name,
														};
													}
												) ),
												_eLocal12.a( 2, _rLocal5 )
											 );
										case 2:
											return (
												( _eLocal12.p = 2 ),
												( _aLocal3 = _eLocal12.v ),
												showRequestError(
													'Error fetching memberships:',
													_aLocal3
												),
												_eLocal12.a( 2, [] )
											 );
									}
								}
							},
							_eLocal11,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args5 ) {
					return e.apply( this, _args5 );
				};
			} )(),
			R = ( function () {
				var e = BH(
					DH().m( function _eLocal14( _tLocal10 ) {
						var _nLocal6, _rLocal6;
						return DH().w( function ( _eLocal15 ) {
							for (;;) {
								switch ( _eLocal15.n ) {
									case 0:
										if ( ! ( _tLocal10.length >= 3 ) ) {
											_eLocal15.n = 4;
											break;
										}
										return (
											( _eLocal15.n = 1 ),
											S( _tLocal10 )
										 );
									case 1:
										if (
											0 !==
											( _nLocal6 = _eLocal15.v ).length
										) {
											_eLocal15.n = 2;
											break;
										}
										return _eLocal15.a( 2, [] );
									case 2:
										return (
											( _rLocal6 =
												_nLocal6 === null ||
												_nLocal6 === void 0
													? void 0
													: _nLocal6.map(
															function (
																_eLocal16
															) {
																return {
																	label: Ge(
																		_eLocal16 ===
																			null ||
																			_eLocal16 ===
																				void 0
																			? void 0
																			: _eLocal16.label
																	),
																	value:
																		_eLocal16 ===
																			null ||
																		_eLocal16 ===
																			void 0
																			? void 0
																			: _eLocal16.value,
																};
															}
														) ),
											_eLocal15.a( 2, _rLocal6 )
										 );
									case 3:
										_eLocal15.n = 5;
										break;
									case 4:
										return _eLocal15.a( 2, [] );
									case 5:
										return _eLocal15.a( 2 );
								}
							}
						}, _eLocal14 );
					} )
				);
				return function ( ..._args6 ) {
					return e.apply( this, _args6 );
				};
			} )(),
			x = function () {
				return ( 0, I18n.__ )(
					'Please enter 3 or more characters...',
					'ohmylms'
				);
			},
			C = function () {
				return ( 0, I18n.__ )(
					'Please enter 3 or more characters...',
					'ohmylms'
				);
			},
			P = function () {
				return ( 0, I18n.__ )(
					'Please enter 3 or more characters...',
					'ohmylms'
				);
			},
			O = function () {
				{
					var e = {
							step_id: 'step_'.concat( s.length + 1 ),
							step_type: '',
							offer_type: '',
							course_id: '',
							membership_id: '',
							discount_type: 'no_discount',
							discount_value: '',
							offer_page_id: '',
							condition: {
								accepted: {
									action: '',
								},
								declined: {
									action: '',
								},
							},
						},
						_tLocal12 = [].concat(
							( function ( _eLocal17 ) {
								return (
									( function ( _eLocal18 ) {
										if ( Array.isArray( _eLocal18 ) ) {
											return HH( _eLocal18 );
										}
									} )( _eLocal17 ) ||
									( function ( _eLocal19 ) {
										if (
											( 'undefined' !== typeof Symbol &&
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													_eLocal19[ Symbol.iterator ]
												) ) ||
											( ( _nullableValue2 ) =>
												_nullableValue2 !== null &&
												_nullableValue2 !== void 0 )(
												_eLocal19[ '@@iterator' ]
											)
										) {
											return Array.from( _eLocal19 );
										}
									} )( _eLocal17 ) ||
									VH( _eLocal17 ) ||
									( function () {
										throw new TypeError(
											'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
										);
									} )()
								);
							} )( s ),
							[ e ]
						);
					d( _tLocal12 );
					j( _tLocal12 );
					f( e.step_id );
				}
			},
			k = function ( e, _tLocal13, _nLocal7 ) {
				{
					var _aLocal4 = s.map( function ( _rLocal7 ) {
						return _rLocal7.step_id === e
							? FH(
									FH( {}, _rLocal7 ),
									{},
									( () => {
										if ( 'acceptedAction' === _tLocal13 ) {
											return {
												condition: FH(
													FH(
														{},
														_rLocal7.condition
													),
													{},
													{
														accepted: {
															action: _nLocal7,
														},
													}
												),
											};
										}
										if ( 'declinedAction' === _tLocal13 ) {
											return {
												condition: FH(
													FH(
														{},
														_rLocal7.condition
													),
													{},
													{
														declined: {
															action: _nLocal7,
														},
													}
												),
											};
										}
										return ( () => {
											if ( 'course_id' === _tLocal13 ) {
												return NH(
													NH(
														{},
														_tLocal13,
														( _nLocal7 === null ||
														_nLocal7 === void 0
															? void 0
															: _nLocal7.value ) ||
															_nLocal7
													),
													'course_name',
													( _nLocal7 === null ||
													_nLocal7 === void 0
														? void 0
														: _nLocal7.label ) || ''
												);
											}
											if (
												'membership_id' === _tLocal13
											) {
												return NH(
													NH(
														{},
														_tLocal13,
														( _nLocal7 === null ||
														_nLocal7 === void 0
															? void 0
															: _nLocal7.value ) ||
															_nLocal7
													),
													'membership_name',
													( _nLocal7 === null ||
													_nLocal7 === void 0
														? void 0
														: _nLocal7.label ) || ''
												);
											}
											return ( () => {
												if (
													'offer_page_id' ===
													_tLocal13
												) {
													return NH(
														NH(
															{},
															_tLocal13,
															( _nLocal7 ===
																null ||
															_nLocal7 === void 0
																? void 0
																: _nLocal7.value ) ||
																_nLocal7
														),
														'page_name',
														( _nLocal7 === null ||
														_nLocal7 === void 0
															? void 0
															: _nLocal7.label ) ||
															''
													);
												}
												if (
													'offer_type' === _tLocal13
												) {
													return NH(
														NH(
															NH(
																NH(
																	NH(
																		{},
																		_tLocal13,
																		_nLocal7
																	),
																	'course_id',
																	''
																),
																'course_name',
																''
															),
															'membership_id',
															''
														),
														'membership_name',
														''
													);
												}
												return NH(
													{},
													_tLocal13,
													_nLocal7
												);
											} )();
										} )();
									} )()
								)
							: _rLocal7;
					} );
					d( _aLocal4 );
					j( _aLocal4 );
				}
			},
			j = function ( e ) {
				t.setCourse(
					FH(
						FH( {}, n ),
						{},
						{
							funnel_steps: e,
						}
					)
				);
			},
			A = function () {
				return s.find( function ( e ) {
					return e.step_id === p;
				} );
			};
		return (
			<Controls.ContainerWP>
				<Controls.SpacerWP marginBottom={ 0 } paddingY={ 10 }>
					<Controls.CardWP
						variant={ 'secondary' }
						isBorderless={ ! 0 }
						minHeight={ 'calc(100vh - 200px)' }
					>
						<Controls.SpacerWP padding={ 10 } marginBottom={ 0 }>
							<Controls.FlexWP
								justify={ 'space-between' }
								align={ 'center' }
								style={ {
									marginBottom: '24px',
								} }
							>
								<Controls.FlexItemWP>
									<Controls.HeadingWP
										level={ 3 }
										style={ {
											margin: 0,
										} }
									>
										{ ( 0, I18n.__ )(
											'One-Click Offer Settings',
											'ohmylms'
										) }
									</Controls.HeadingWP>
									<Controls.TextWP
										style={ {
											color: '#666',
											margin: '4px 0 0 0',
											fontSize: '14px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Configure your funnel sequence to show additional offers after checkout',
											'ohmylms'
										) }
									</Controls.TextWP>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
							{ 0 === s.length ? (
								<Controls.CardWP
									variant={ 'outline' }
									className={ 'funnel-empty-state' }
									isBorderless={ ! 0 }
								>
									<Controls.SpacerWP padding={ 8 }>
										<Controls.FlexWP
											direction={ 'column' }
											align={ 'center' }
											gap={ 3 }
										>
											<Controls.FlexItemWP>
												<div
													className={
														'funnel-empty-icon'
													}
												>
													{ React.createElement(
														df,
														null
													) }
												</div>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP>
												<Controls.HeadingWP
													level={ 4 }
													style={ {
														margin: 0,
														textAlign: 'center',
													} }
												>
													{ ( 0, I18n.__ )(
														'No funnel steps yet!',
														'ohmylms'
													) }
												</Controls.HeadingWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP>
												<Controls.TextWP
													style={ {
														color: '#666',
														textAlign: 'center',
														margin: 0,
													} }
												>
													{ ( 0, I18n.__ )(
														'Boost revenue with upsells and downsells after course purchase.',
														'ohmylms'
													) }
												</Controls.TextWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP>
												<Controls.ButtonWP
													variant={ 'primary' }
													icon={
														<q.Icon
															icon={ $e.A }
															width={ '24px' }
															height={ '24px' }
														/>
													}
													onClick={ O }
													disabled={ ! r }
												>
													{ ( 0, I18n.__ )(
														'Add New Step',
														'ohmylms'
													) }
												</Controls.ButtonWP>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							) : (
								<Controls.FlexWP
									gap={ 6 }
									align={ 'flex-start' }
								>
									<Controls.FlexItemWP
										style={ {
											minWidth: '350px',
											maxWidth: '400px',
										} }
									>
										<Controls.CardWP
											variant={ 'outline' }
											isBorderless={ ! 0 }
										>
											<Controls.SpacerWP padding={ 4 }>
												<Controls.HeadingWP
													level={ 4 }
													style={ {
														margin: '0 0 16px 0',
													} }
												>
													{ ( 0, I18n.__ )(
														'Active Steps',
														'ohmylms'
													) }
													{ ' (' }
													{ s.length }
													{ ')' }
												</Controls.HeadingWP>
												<Controls.FlexWP
													direction={ 'column' }
													gap={ 2 }
												>
													{ s.map(
														function (
															e,
															_tLocal14
														) {
															var _nLocal8 =
																	( function (
																		_eLocal20,
																		_tLocal15
																	) {
																		var _nLocal9,
																			_rValue =
																				_eLocal20.step_type
																					? ( null ===
																							( _nLocal9 =
																								a.find(
																									function (
																										_tLocal16
																									) {
																										return (
																											_tLocal16.value ===
																											_eLocal20.step_type
																										);
																									}
																								) ) ||
																						void 0 ===
																							_nLocal9
																							? void 0
																							: _nLocal9.label ) ||
																						_eLocal20.step_type
																					: ( 0,
																						I18n.__ )(
																							'No step selected',
																							'ohmylms'
																						),
																			_oLocal =
																				( 0,
																				I18n.__ )(
																					'No offer selected',
																					'ohmylms'
																				);
																		return (
																			'course' ===
																				_eLocal20.offer_type &&
																			_eLocal20.course_name
																				? ( _oLocal =
																						_eLocal20.course_name )
																				: 'membership' ===
																						_eLocal20.offer_type &&
																					_eLocal20.membership_name &&
																					( _oLocal =
																						_eLocal20.membership_name ),
																			{
																				stepNumber:
																					_tLocal15 +
																					1,
																				stepType:
																					_rValue,
																				courseName:
																					_oLocal,
																			}
																		 );
																	} )(
																		e,
																		_tLocal14
																	),
																_rLocal8 =
																	p ===
																	e.step_id;
															return (
																<Controls.FlexItemWP
																	key={
																		e.step_id
																	}
																>
																	<Controls.CardWP
																		variant={
																			_rLocal8
																				? 'primary'
																				: 'outline'
																		}
																		className={ 'funnel-step-summary '.concat(
																			_rLocal8
																				? 'selected'
																				: ''
																		) }
																		style={ {
																			cursor: 'pointer',
																			transition:
																				'all 0.2s ease',
																		} }
																		onClick={ function () {
																			f(
																				e.step_id
																			);
																		} }
																	>
																		<Controls.SpacerWP
																			padding={
																				3
																			}
																		>
																			<Controls.FlexWP
																				direction={
																					'column'
																				}
																				gap={
																					1
																				}
																			>
																				<Controls.FlexWP
																					justify={
																						'space-between'
																					}
																					align={
																						'center'
																					}
																				>
																					<Controls.HeadingWP
																						level={
																							6
																						}
																						style={ {
																							margin: 0,
																							fontSize:
																								'14px',
																							fontWeight:
																								'600',
																						} }
																					>
																						{ ( 0,
																						I18n.__ )(
																							'Step',
																							'ohmylms'
																						) }{ ' ' }
																						{
																							_nLocal8.stepNumber
																						}
																					</Controls.HeadingWP>
																					{ s.length >
																						0 && (
																						<Controls.ButtonWP
																							variant={
																								'text'
																							}
																							size={
																								'small'
																							}
																							icon={
																								<We />
																							}
																							onClick={ function (
																								_tLocal17
																							) {
																								var _nLocal0,
																									_rLocal9,
																									_aLocal5;
																								_tLocal17.stopPropagation();
																								_nLocal0 =
																									e.step_id;
																								_rLocal9 =
																									s.filter(
																										function (
																											_eLocal21
																										) {
																											return (
																												_eLocal21.step_id !==
																												_nLocal0
																											);
																										}
																									);
																								_aLocal5 =
																									_rLocal9.map(
																										function (
																											_eLocal22,
																											_tLocal18
																										) {
																											return FH(
																												FH(
																													{},
																													_eLocal22
																												),
																												{},
																												{
																													step_id:
																														'step_'.concat(
																															_tLocal18 +
																																1
																														),
																												}
																											);
																										}
																									);
																								d(
																									_aLocal5
																								);
																								j(
																									_aLocal5
																								);
																								if (
																									p ===
																									_nLocal0
																								) {
																									if (
																										_aLocal5.length >
																										0
																									) {
																										f(
																											_aLocal5[ 0 ]
																												.step_id
																										);
																									} else {
																										f(
																											null
																										);
																									}
																								}
																							} }
																							style={ {
																								minWidth:
																									'auto',
																								padding:
																									'2px 4px',
																								height: '20px',
																								color: '#dc2626',
																							} }
																						/>
																					) }
																				</Controls.FlexWP>
																				<Controls.TextWP
																					style={ {
																						margin: 0,
																						fontSize:
																							'12px',
																						color: '#666',
																						fontWeight:
																							'500',
																					} }
																				>
																					{
																						_nLocal8.stepType
																					}
																				</Controls.TextWP>
																				<Controls.TextWP
																					style={ {
																						margin: 0,
																						fontSize:
																							'11px',
																						color: '#888',
																						overflow:
																							'hidden',
																						textOverflow:
																							'ellipsis',
																						whiteSpace:
																							'nowrap',
																					} }
																				>
																					{
																						_nLocal8.courseName
																					}
																				</Controls.TextWP>
																			</Controls.FlexWP>
																		</Controls.SpacerWP>
																	</Controls.CardWP>
																</Controls.FlexItemWP>
															);
														}
													) }
													<Controls.FlexItemWP>
														<Controls.ButtonWP
															variant={
																'outline'
															}
															icon={
																<q.Icon
																	icon={
																		$e.A
																	}
																	width={
																		'24px'
																	}
																	height={
																		'24px'
																	}
																/>
															}
															onClick={ O }
															style={ {
																width: '100%',
																justifyContent:
																	'center',
																padding: '12px',
																borderStyle:
																	'dashed',
																color: 'var(--wp-components-color-accent)',
																borderColor:
																	'#C8D2E9',
															} }
															disabled={ ! r }
														>
															{ ( 0, I18n.__ )(
																'Add New Step',
																'ohmylms'
															) }
														</Controls.ButtonWP>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.SpacerWP>
										</Controls.CardWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP flex={ '1' }>
										{ p && A() ? (
											<Controls.CardWP
												variant={ 'outline' }
												isBorderless={ ! 0 }
											>
												<Controls.SpacerWP
													padding={ 4 }
												>
													{ ( function (
														e,
														_tLocal19
													) {
														var _nLocal1 = A(),
															_lLocal =
																s.findIndex(
																	function (
																		_eLocal23
																	) {
																		return (
																			_eLocal23.step_id ===
																			p
																		);
																	}
																);
														return (
															<Controls.FlexWP
																direction={
																	'column'
																}
																gap={ 4 }
															>
																<Controls.FlexItemWP>
																	<Controls.HeadingWP
																		level={
																			4
																		}
																		style={ {
																			margin: 0,
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Step',
																			'ohmylms'
																		) }{ ' ' }
																		{ _lLocal +
																			1 }{ ' ' }
																		{ ( 0,
																		I18n.__ )(
																			'Settings',
																			'ohmylms'
																		) }
																	</Controls.HeadingWP>
																</Controls.FlexItemWP>
																<Controls.CardWP
																	isBorderless={
																		! 0
																	}
																	padding={
																		'16px'
																	}
																	variant={
																		'secondary'
																	}
																>
																	<Controls.FlexItemWP>
																		<Controls.FlexWP
																			gap={
																				8
																			}
																			align={
																				'flex-start'
																			}
																		>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.HeadingWP
																					level={
																						4
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Step Type',
																						'ohmylms'
																					) }
																				</Controls.HeadingWP>
																				<Controls.SpacerWP
																					marginBottom={
																						1
																					}
																				/>
																				<Controls.TextWP>
																					{ ( 0,
																					I18n.__ )(
																						'Choose the type of offer for this funnel step.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.SelectWP
																					value={
																						_nLocal1.step_type
																					}
																					onChange={ function (
																						_eLocal24
																					) {
																						return k(
																							_nLocal1.step_id,
																							'step_type',
																							_eLocal24
																						);
																					} }
																					options={
																						a
																					}
																					placeholder={ ( 0,
																					I18n.__ )(
																						'Select type',
																						'ohmylms'
																					) }
																					disabled={
																						! r
																					}
																				/>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																	<Controls.SpacerWP
																		marginBottom={
																			4
																		}
																	/>
																	<Controls.FlexItemWP>
																		<Controls.FlexWP
																			gap={
																				8
																			}
																			align={
																				'flex-start'
																			}
																		>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.HeadingWP
																					level={
																						4
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Offer Page',
																						'ohmylms'
																					) }
																				</Controls.HeadingWP>
																				<Controls.SpacerWP
																					marginBottom={
																						1
																					}
																				/>
																				<Controls.TextWP>
																					{ ( 0,
																					I18n.__ )(
																						'Choose the page template for this offer.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Jt.A
																					className={
																						'ohmylms-single-select ohmylms-search-select auto-height'
																					}
																					classNamePrefix={
																						'ohmylms-react-select'
																					}
																					placeholder={ ( 0,
																					I18n.__ )(
																						'Type to search pages...',
																						'ohmylms'
																					) }
																					value={
																						_nLocal1.offer_page_id &&
																						_nLocal1.page_name
																							? {
																									value: _nLocal1.offer_page_id,
																									label: _nLocal1.page_name,
																								}
																							: null
																					}
																					onChange={ function (
																						_eLocal25
																					) {
																						return k(
																							_nLocal1.step_id,
																							'offer_page_id',
																							_eLocal25
																						);
																					} }
																					loadOptions={
																						_
																					}
																					noOptionsMessage={
																						C
																					}
																					isClearable={
																						! 0
																					}
																					key={ 'page-'.concat(
																						_nLocal1.step_id
																					) }
																					isDisabled={
																						! r
																					}
																				/>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																</Controls.CardWP>
																<Controls.CardWP
																	isBorderless={
																		! 0
																	}
																	padding={
																		'16px'
																	}
																	variant={
																		'secondary'
																	}
																>
																	<Controls.FlexItemWP>
																		<Controls.FlexWP
																			gap={
																				8
																			}
																			align={
																				'flex-start'
																			}
																		>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.HeadingWP
																					level={
																						4
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Offer Type',
																						'ohmylms'
																					) }
																				</Controls.HeadingWP>
																				<Controls.SpacerWP
																					marginBottom={
																						1
																					}
																				/>
																				<Controls.TextWP>
																					{ ( 0,
																					I18n.__ )(
																						'Choose what type of product to offer.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.SelectWP
																					value={
																						_nLocal1.offer_type ||
																						''
																					}
																					onChange={ function (
																						_eLocal26
																					) {
																						return k(
																							_nLocal1.step_id,
																							'offer_type',
																							_eLocal26
																						);
																					} }
																					options={
																						c
																					}
																					placeholder={ ( 0,
																					I18n.__ )(
																						'Select offer type',
																						'ohmylms'
																					) }
																					disabled={
																						! r
																					}
																				/>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																	{ 'course' ===
																		_nLocal1.offer_type && (
																		<Controls.SpacerWP
																			marginBottom={
																				0
																			}
																			marginTop={
																				4
																			}
																		>
																			<Controls.FlexItemWP>
																				<Controls.FlexWP
																					gap={
																						8
																					}
																					align={
																						'flex-start'
																					}
																				>
																					<Controls.FlexItemWP
																						flex={
																							'2'
																						}
																					>
																						<Controls.HeadingWP
																							level={
																								4
																							}
																						>
																							{ ( 0,
																							I18n.__ )(
																								'Course',
																								'ohmylms'
																							) }
																						</Controls.HeadingWP>
																						<Controls.SpacerWP
																							marginBottom={
																								1
																							}
																						/>
																						<Controls.TextWP>
																							{ ( 0,
																							I18n.__ )(
																								'Select the course to offer in this step.',
																								'ohmylms'
																							) }
																						</Controls.TextWP>
																					</Controls.FlexItemWP>
																					<Controls.FlexItemWP
																						flex={
																							'2'
																						}
																					>
																						<Jt.A
																							className={
																								'ohmylms-single-select ohmylms-search-select auto-height'
																							}
																							classNamePrefix={
																								'ohmylms-react-select'
																							}
																							placeholder={ ( 0,
																							I18n.__ )(
																								'Type to search courses...',
																								'ohmylms'
																							) }
																							value={
																								_nLocal1.course_id &&
																								_nLocal1.course_name
																									? {
																											value: _nLocal1.course_id,
																											label: _nLocal1.course_name,
																										}
																									: null
																							}
																							onChange={ function (
																								_eLocal27
																							) {
																								return k(
																									_nLocal1.step_id,
																									'course_id',
																									_eLocal27
																								);
																							} }
																							loadOptions={
																								E
																							}
																							noOptionsMessage={
																								x
																							}
																							isClearable={
																								! 0
																							}
																							key={ 'course-'.concat(
																								_nLocal1.step_id
																							) }
																							disabled={
																								! r
																							}
																						/>
																					</Controls.FlexItemWP>
																				</Controls.FlexWP>
																			</Controls.FlexItemWP>
																		</Controls.SpacerWP>
																	) }
																	{ 'membership' ===
																		_nLocal1.offer_type && (
																		<Controls.SpacerWP
																			marginBottom={
																				0
																			}
																			marginTop={
																				4
																			}
																		>
																			<Controls.FlexItemWP>
																				<Controls.FlexWP
																					gap={
																						8
																					}
																					align={
																						'flex-start'
																					}
																				>
																					<Controls.FlexItemWP
																						flex={
																							'2'
																						}
																					>
																						<Controls.HeadingWP
																							level={
																								4
																							}
																						>
																							{ ( 0,
																							I18n.__ )(
																								'Membership',
																								'ohmylms'
																							) }
																						</Controls.HeadingWP>
																						<Controls.SpacerWP
																							marginBottom={
																								1
																							}
																						/>
																						<Controls.TextWP>
																							{ ( 0,
																							I18n.__ )(
																								'Select the membership plan to offer in this step.',
																								'ohmylms'
																							) }
																						</Controls.TextWP>
																					</Controls.FlexItemWP>
																					<Controls.FlexItemWP
																						flex={
																							'2'
																						}
																					>
																						<Jt.A
																							className={
																								'ohmylms-single-select ohmylms-search-select auto-height'
																							}
																							classNamePrefix={
																								'ohmylms-react-select'
																							}
																							placeholder={ ( 0,
																							I18n.__ )(
																								'Type to search memberships...',
																								'ohmylms'
																							) }
																							value={
																								_nLocal1.membership_id &&
																								_nLocal1.membership_name
																									? {
																											value: _nLocal1.membership_id,
																											label: _nLocal1.membership_name,
																										}
																									: null
																							}
																							onChange={ function (
																								_eLocal28
																							) {
																								return k(
																									_nLocal1.step_id,
																									'membership_id',
																									_eLocal28
																								);
																							} }
																							loadOptions={
																								R
																							}
																							noOptionsMessage={
																								P
																							}
																							isClearable={
																								! 0
																							}
																							key={ 'membership-'.concat(
																								_nLocal1.step_id
																							) }
																							isDisabled={
																								! r
																							}
																						/>
																					</Controls.FlexItemWP>
																				</Controls.FlexWP>
																			</Controls.FlexItemWP>
																		</Controls.SpacerWP>
																	) }
																</Controls.CardWP>
																<Controls.CardWP
																	isBorderless={
																		! 0
																	}
																	padding={
																		'16px'
																	}
																	variant={
																		'secondary'
																	}
																	className={
																		'ohmylms-funnel-discount-section'
																	}
																>
																	<Controls.FlexItemWP>
																		<Controls.FlexWP
																			gap={
																				1
																			}
																			align={
																				'flex-start'
																			}
																		>
																			<Controls.FlexItemWP
																				flex={
																					'1'
																				}
																			>
																				<Controls.HeadingWP
																					level={
																						4
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Discount',
																						'ohmylms'
																					) }
																				</Controls.HeadingWP>
																				<Controls.SpacerWP
																					marginBottom={
																						1
																					}
																				/>
																				<Controls.TextWP>
																					{ ( 0,
																					I18n.__ )(
																						'Configure discount options for this offer.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP
																				flex={
																					'2'
																				}
																			>
																				<Controls.FlexWP
																					direction={
																						'column'
																					}
																					gap={
																						3
																					}
																				>
																					<Controls.RadioGroupWP
																						options={
																							i
																						}
																						value={
																							_nLocal1.discount_type ||
																							'no_discount'
																						}
																						onChange={ function (
																							_eLocal29
																						) {
																							return k(
																								_nLocal1.step_id,
																								'discount_type',
																								_eLocal29
																							);
																						} }
																						isBlock={
																							! 0
																						}
																						optionType={
																							'button'
																						}
																						buttonStyle={
																							'outline'
																						}
																					/>
																					{ ( 'percentage' ===
																						_nLocal1.discount_type ||
																						'amount' ===
																							_nLocal1.discount_type ) && (
																						<Controls.FlexWP
																							justify={
																								'flex-end'
																							}
																							align={
																								'center'
																							}
																						>
																							<Controls.FlexWP
																								align={
																									'center'
																								}
																								gap={
																									1
																								}
																								style={ {
																									maxWidth:
																										'120px',
																								} }
																							>
																								<Controls.FlexItemWP
																									flex={
																										'1'
																									}
																								>
																									<Controls.InputNumberWP
																										value={
																											_nLocal1.discount_value ||
																											''
																										}
																										onChange={ function (
																											_eLocal30
																										) {
																											return k(
																												_nLocal1.step_id,
																												'discount_value',
																												_eLocal30
																											);
																										} }
																										placeholder={
																											'0'
																										}
																										min={
																											0
																										}
																										max={
																											'percentage' ===
																											_nLocal1.discount_type
																												? 100
																												: void 0
																										}
																										suffix={
																											'percentage' ===
																											( _nLocal1 ===
																												null ||
																											_nLocal1 ===
																												void 0
																												? void 0
																												: _nLocal1.discount_type )
																												? '%'
																												: ''
																										}
																										disabled={
																											! r
																										}
																									/>
																								</Controls.FlexItemWP>
																							</Controls.FlexWP>
																						</Controls.FlexWP>
																					) }
																				</Controls.FlexWP>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																</Controls.CardWP>
																<Controls.FlexItemWP>
																	<Controls.CardWP
																		variant={
																			'secondary'
																		}
																		isBorderless={
																			! 0
																		}
																		className={
																			'condition-settings'
																		}
																	>
																		<Controls.SpacerWP
																			padding={
																				3
																			}
																		>
																			<Controls.FlexWP
																				direction={
																					'column'
																				}
																				gap={
																					3
																				}
																			>
																				<Controls.FlexItemWP>
																					<Controls.HeadingWP
																						level={
																							4
																						}
																					>
																						{ ( 0,
																						I18n.__ )(
																							'Conditional Step Actions',
																							'ohmylms'
																						) }
																					</Controls.HeadingWP>
																				</Controls.FlexItemWP>
																				<Controls.FlexItemWP>
																					<Controls.FlexWP
																						gap={
																							4
																						}
																						wrap={
																							! 0
																						}
																					>
																						<Controls.FlexItemWP
																							flex={
																								'1'
																							}
																						>
																							<Controls.FlexWP
																								direction={
																									'column'
																								}
																								gap={
																									1
																								}
																							>
																								<Controls.TextWP>
																									{ ( 0,
																									I18n.__ )(
																										'When the Offer is Accepted',
																										'ohmylms'
																									) }
																								</Controls.TextWP>
																								<Controls.SelectWP
																									value={
																										( null ===
																											( e =
																												_nLocal1.condition ) ||
																										void 0 ===
																											e ||
																										null ===
																											( e =
																												e.accepted ) ||
																										void 0 ===
																											e
																											? void 0
																											: e.action ) ||
																										''
																									}
																									onChange={ function (
																										_eLocal31
																									) {
																										return k(
																											_nLocal1.step_id,
																											'acceptedAction',
																											_eLocal31
																										);
																									} }
																									options={
																										o
																									}
																									placeholder={ ( 0,
																									I18n.__ )(
																										'Select action',
																										'ohmylms'
																									) }
																									disabled={
																										! r
																									}
																								/>
																							</Controls.FlexWP>
																						</Controls.FlexItemWP>
																						<Controls.FlexItemWP
																							flex={
																								'1'
																							}
																						>
																							<Controls.FlexWP
																								direction={
																									'column'
																								}
																								gap={
																									1
																								}
																							>
																								<Controls.TextWP>
																									{ ( 0,
																									I18n.__ )(
																										'When the Offer is Declined',
																										'ohmylms'
																									) }
																								</Controls.TextWP>
																								<Controls.SelectWP
																									value={
																										( null ===
																											( _tLocal19 =
																												_nLocal1.condition ) ||
																										void 0 ===
																											_tLocal19 ||
																										null ===
																											( _tLocal19 =
																												_tLocal19.declined ) ||
																										void 0 ===
																											_tLocal19
																											? void 0
																											: _tLocal19.action ) ||
																										''
																									}
																									onChange={ function (
																										_eLocal32
																									) {
																										return k(
																											_nLocal1.step_id,
																											'declinedAction',
																											_eLocal32
																										);
																									} }
																									options={
																										o
																									}
																									placeholder={ ( 0,
																									I18n.__ )(
																										'Select action',
																										'ohmylms'
																									) }
																									disabled={
																										! r
																									}
																								/>
																							</Controls.FlexWP>
																						</Controls.FlexItemWP>
																					</Controls.FlexWP>
																				</Controls.FlexItemWP>
																			</Controls.FlexWP>
																		</Controls.SpacerWP>
																	</Controls.CardWP>
																</Controls.FlexItemWP>
															</Controls.FlexWP>
														);
													} )() }
												</Controls.SpacerWP>
											</Controls.CardWP>
										) : (
											<Controls.CardWP
												variant={ 'outline' }
												isBorderless={ ! 0 }
											>
												<Controls.SpacerWP
													padding={ 8 }
												>
													<Controls.FlexWP
														direction={ 'column' }
														align={ 'center' }
														gap={ 3 }
													>
														<Controls.FlexItemWP>
															<Controls.HeadingWP
																level={ 5 }
																style={ {
																	margin: 0,
																	textAlign:
																		'center',
																	color: '#666',
																} }
															>
																{ ( 0,
																I18n.__ )(
																	'Select a step to edit',
																	'ohmylms'
																) }
															</Controls.HeadingWP>
														</Controls.FlexItemWP>
														<Controls.FlexItemWP>
															<Controls.TextWP
																style={ {
																	color: '#888',
																	textAlign:
																		'center',
																	margin: 0,
																} }
															>
																{ ( 0,
																I18n.__ )(
																	'Choose a step from the left panel to configure its settings',
																	'ohmylms'
																) }
															</Controls.TextWP>
														</Controls.FlexItemWP>
													</Controls.FlexWP>
												</Controls.SpacerWP>
											</Controls.CardWP>
										) }
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							) }
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.SpacerWP>
			</Controls.ContainerWP>
		);
	};
}
