/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { FeatureSwitch } from './FeatureSwitch';
import { useNotifications } from '../../shared/notices';
export function createRewardSettings( readRuntime ) {
	return function RewardSettings() {
		const {
			I: Controls,
			React,
			T: StoreModule,
			b: I18n,
			b5,
			g: ReactHooks,
			g5,
			h5,
			l,
			p5,
			s5,
			y: WordPressData,
			y5,
		} = readRuntime();
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = h5(
			( 0, ReactHooks.useState )( {
				enable: ! 1,
				rules: [
					{
						label: ( 0, I18n.__ )(
							'Allow course purchases using bonus points',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Enable learners to redeem their earned points for course access.',
							'ohmylms'
						),
						slug: 'purchase_course',
						value: ! 1,
					},
				],
			} ),
			2
		);
		var r = n[ 0 ];
		var a = n[ 1 ];
		var o = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getGamificationSettings();
		}, [] );
		var i = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationMessage();
		}, [] );
		var c = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
			return _eLocal3( StoreModule.default ).getNotificationStatus();
		}, [] );
		var u = h5( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var s = u[ 0 ];
		var d = u[ 1 ];
		var m = h5( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var p = m[ 0 ];
		var f = m[ 1 ];
		var v = useNotifications();
		var openNotificationWithIcon = v.openNotificationWithIcon;
		var contextHolder = v.contextHolder;
		( 0, ReactHooks.useEffect )( function () {
			var _tLocal = ( function () {
				var _eLocal4 = g5(
					s5().m( function _eLocal5() {
						var _tLocal2, _nLocal;
						return s5().w( function ( _eLocal6 ) {
							for (;;) {
								switch ( _eLocal6.n ) {
									case 0:
										return (
											d( ! 0 ),
											( _eLocal6.n = 1 ),
											l()( {
												path: 'ohmylms/v1/engagement/settings/reward',
											} )
										 );
									case 1:
										_tLocal2 = _eLocal6.v;
										a( _tLocal2 || r );
										d( ! 1 );
										if ( _tLocal2 ) {
											{
												_nLocal = w(
													_tLocal2.rules || [],
													S
												);
												a(
													p5(
														p5( {}, _tLocal2 ),
														{},
														{
															rules: _nLocal,
														}
													)
												);
											}
										} // Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal6.a( 2 );
								}
							}
						}, _eLocal5 );
					} )
				);
				return function ( ..._args ) {
					return _eLocal4.apply( this, _args );
				};
			} )();
			_tLocal();
		}, [] );
		var w = function ( _eLocal7, _tLocal3 ) {
			var _nLocal2 = ( function ( _eLocal8 ) {
				return (
					( function ( _eLocal9 ) {
						if ( Array.isArray( _eLocal9 ) ) {
							return b5( _eLocal9 );
						}
					} )( _eLocal8 ) ||
					( function ( _eLocal0 ) {
						if (
							( 'undefined' !== typeof Symbol &&
								( ( _nullableValue ) =>
									_nullableValue !== null &&
									_nullableValue !== void 0 )(
									_eLocal0[ Symbol.iterator ]
								) ) ||
							( ( _nullableValue2 ) =>
								_nullableValue2 !== null &&
								_nullableValue2 !== void 0 )(
								_eLocal0[ '@@iterator' ]
							)
						) {
							return Array.from( _eLocal0 );
						}
					} )( _eLocal8 ) ||
					y5( _eLocal8 ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( _eLocal7 );
			return (
				_tLocal3.forEach( function ( _tLocal4 ) {
					if (
						! _eLocal7.some( function ( _eLocal1 ) {
							return _eLocal1.slug === _tLocal4.slug;
						} )
					) {
						_nLocal2.push( _tLocal4 );
					}
				} ),
				_nLocal2
			 );
		};
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! s && i ) {
					openNotificationWithIcon( c, i );
				}
			},
			[ i ]
		);
		var E = ( function () {
				var _nLocal3 = g5(
					s5().m( function _nLocal4() {
						var _aLocal;
						return s5().w(
							function ( _nLocal5 ) {
								for (;;) {
									switch ( ( _nLocal5.p = _nLocal5.n ) ) {
										case 0:
											{
												_nLocal5.n = 1;
												break;
											}
											return _nLocal5.a( 2 );
										case 1:
											return (
												t.setLoadingSetting( ! 0 ),
												f( ! 0 ),
												( _nLocal5.p = 2 ),
												( _nLocal5.n = 3 ),
												l()( {
													path: '/ohmylms/v1/engagement/settings/reward',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify( r ),
												} )
											 );
										case 3:
											return (
												( ( _nullableValue3 ) =>
													_nullableValue3 !== null &&
													_nullableValue3 !==
														void 0 )(
													( _aLocal = _nLocal5.v )
												) &&
													_aLocal.success &&
													t.setGlobalDataViaKey(
														'engagement_settings',
														p5(
															p5( {}, o ),
															{},
															{
																reward_settings:
																	r,
															}
														)
													),
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Settings saved successfully.',
														'ohmylms'
													)
												),
												_nLocal5.a( 2, _aLocal )
											 );
										case 4:
											_nLocal5.p = 4;
											f( ! 1 );
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'Something went wrong.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _nLocal5.p = 5 ),
												t.setLoadingSetting( ! 1 ),
												f( ! 1 ),
												_nLocal5.f( 5 )
											 );
										case 6:
											return _nLocal5.a( 2 );
									}
								}
							},
							_nLocal4,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _nLocal3.apply( this, _args2 );
				};
			} )(),
			S = [
				{
					label: ( 0, I18n.__ )(
						'Allow purchasing course using bonus point',
						'ohmylms'
					),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user completes an entire course.',
						'ohmylms'
					),
					slug: 'purchase_course',
					value: ! 0,
				},
			];
		return s ? (
			<React.Fragment>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP marginTop={ 2.5 } padding={ 6 }>
						<Controls.SkeletonWP active={ ! 0 } rows={ 15 } />
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		) : (
			<React.Fragment>
				{ contextHolder }
				<FeatureSwitch
					settings={ r }
					setSettings={ a }
					title={ I18n.__( 'Point rewards', 'ohmylms' ) }
					description={ I18n.__(
						'Let learners redeem earned points for eligible courses.',
						'ohmylms'
					) }
					label={ I18n.__( 'Enable point rewards', 'ohmylms' ) }
				/>

				<Controls.CardWP isBorderless={ ! 0 } variant={ 'secondary' }>
					<Controls.SpacerWP
						padding={ 0 }
						marginTop={ 2.5 }
						marginBottom={ 0 }
					>
						<Controls.FlexWP
							justify={ 'flex-start' }
							align={ 'flex-start' }
							direction={ 'column' }
							gap={ 3 }
						>
							{
								<React.Fragment>
									{ r.rules.map(
										function ( _eLocal10, _tLocal5 ) {
											return (
												<Controls.CardWP
													key={ _tLocal5 }
													isBorderless={ ! 0 }
													padding={ '24px' }
													fullWidth={ ! 0 }
												>
													<Controls.FlexWP
														align={ 'flex-start' }
														justify={ 'flex-start' }
														direction={ 'column' }
														gap={ 3 }
													>
														<React.Fragment
															key={
																_eLocal10.value
															}
														>
															<Controls.FlexWP
																align={
																	'center'
																}
																justify={
																	'space-between'
																}
																style={ {
																	width: '100%',
																} }
															>
																<Controls.FlexItemWP>
																	<Controls.HeadingWP
																		level={
																			'4'
																		}
																	>
																		{ ( 0,
																		I18n.__ )(
																			_eLocal10.label,
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
																			_eLocal10.tooltip,
																			'ohmylms'
																		) }
																	</Controls.TextWP>
																</Controls.FlexItemWP>
																<Controls.FlexItemWP>
																	<Controls.SwitchWP
																		checked={
																			_eLocal10.value
																		}
																		onChange={ function (
																			_eLocal11
																		) {
																			return ( function (
																				_eLocal12,
																				_tLocal6
																			) {
																				a(
																					function (
																						_nLocal6
																					) {
																						var _rLocal =
																							_nLocal6.rules.map(
																								function (
																									_nLocal7,
																									_rLocal2
																								) {
																									return _rLocal2 ===
																										_eLocal12
																										? p5(
																												p5(
																													{},
																													_nLocal7
																												),
																												{},
																												{
																													value: _tLocal6,
																												}
																											)
																										: _nLocal7;
																								}
																							);
																						return p5(
																							p5(
																								{},
																								_nLocal6
																							),
																							{},
																							{
																								rules: _rLocal,
																							}
																						);
																					}
																				);
																			} )(
																				_tLocal5,
																				_eLocal11
																			);
																		} }
																	/>
																</Controls.FlexItemWP>
															</Controls.FlexWP>
															{ void 0 !==
																( _eLocal10 ===
																	null ||
																_eLocal10 ===
																	void 0
																	? void 0
																	: _eLocal10.threshold ) &&
																_eLocal10.value && (
																	<Controls.FlexItemWP
																		fullWidth={
																			! 0
																		}
																	>
																		<Controls.SpacerWP
																			marginBottom={
																				1
																			}
																		/>
																		<Controls.FlexWP
																			align={
																				'flex-start'
																			}
																			justify={
																				'space-between'
																			}
																		>
																			<Controls.FlexItemWP>
																				<Controls.HeadingWP
																					level={
																						'5'
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Minimum Required Threshold',
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
																						'Set the minimum percentage a learner must achieve to earn bonus points for this activity.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP>
																				<Controls.InputNumberWP
																					value={
																						_eLocal10.threshold
																					}
																					onChange={ function (
																						_eLocal13
																					) {
																						return ( function (
																							_eLocal14,
																							_tLocal7
																						) {
																							a(
																								function (
																									_nLocal8
																								) {
																									var _rLocal3 =
																										_nLocal8.rules.map(
																											function (
																												_nLocal9,
																												_rLocal4
																											) {
																												return _rLocal4 ===
																													_eLocal14
																													? p5(
																															p5(
																																{},
																																_nLocal9
																															),
																															{},
																															{
																																threshold:
																																	_tLocal7,
																															}
																														)
																													: _nLocal9;
																											}
																										);
																									return p5(
																										p5(
																											{},
																											_nLocal8
																										),
																										{},
																										{
																											rules: _rLocal3,
																										}
																									);
																								}
																							);
																						} )(
																							_tLocal5,
																							_eLocal13
																						);
																					} }
																					min={
																						0
																					}
																					max={
																						100
																					}
																					suffix={
																						'%'
																					}
																				/>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																) }
														</React.Fragment>
													</Controls.FlexWP>
												</Controls.CardWP>
											);
										}
									) }
								</React.Fragment>
							}
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.SpacerWP paddingTop={ 6 } paddingBottom={ 25 }>
					<Controls.FlexWP justify={ 'flex-end' }>
						<Controls.ButtonWP
							variant={ 'primary' }
							size={ 'md' }
							onClick={ E }
							isBusy={ p }
						>
							{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
						</Controls.ButtonWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
