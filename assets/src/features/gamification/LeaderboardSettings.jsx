import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { FeatureSwitch } from './FeatureSwitch';
export function createLeaderboardSettings( readRuntime ) {
	return function LeaderboardSettings() {
		const {
			I: Controls,
			React,
			T: StoreModule,
			_2,
			b: I18n,
			b2,
			g: ReactHooks,
			g2,
			h2,
			l,
			p2,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = _2(
			( 0, ReactHooks.useState )( {
				enable: ! 1,
				rules: 'completion_rate',
				threshold: '',
				students_number: '',
			} ),
			2
		);
		var r = n[ 0 ];
		var a = n[ 1 ];
		var o = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getNotificationMessage();
		}, [] );
		var i = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationStatus();
		}, [] );
		var c = _2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var u = c[ 0 ];
		var s = c[ 1 ];
		var d = _2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var m = d[ 0 ];
		var p = d[ 1 ];
		var f = ( 0, Notifications.A )();
		var openNotificationWithIcon = f.openNotificationWithIcon;
		var contextHolder = f.contextHolder;
		var _ = ( function () {
			var _tLocal = b2(
				p2().m( function _tLocal2() {
					var _nLocal, _rLocal;
					return p2().w(
						function ( _tLocal3 ) {
							for (;;) {
								switch ( ( _tLocal3.p = _tLocal3.n ) ) {
									case 0:
										{
											_tLocal3.n = 1;
											break;
										}
										return _tLocal3.a( 2 );
									case 1:
										return (
											( _tLocal3.p = 1 ),
											s( ! 0 ),
											( _tLocal3.n = 2 ),
											l()( {
												path: 'ohmylms/v1/engagement/settings/leaderboard',
											} )
										 );
									case 2:
										_nLocal = _tLocal3.v;
										a( function ( _eLocal3 ) {
											return g2(
												g2( {}, _eLocal3 ),
												_nLocal
											);
										} );
										_tLocal3.n = 4;
										break;
									case 3:
										_tLocal3.p = 3;
										_rLocal = _tLocal3.v;
										showRequestError( _rLocal );
									// Falls through to the shared async state cleanup.
									case 4:
										return (
											( _tLocal3.p = 4 ),
											s( ! 1 ),
											_tLocal3.f( 4 )
										 );
									case 5:
										return _tLocal3.a( 2 );
								}
							}
						},
						_tLocal2,
						null,
						[ [ 1, 3, 4, 5 ] ]
					);
				} )
			);
			return function ( ..._args ) {
				return _tLocal.apply( this, _args );
			};
		} )();
		( 0, ReactHooks.useEffect )( function () {
			var _eLocal4 = ! 0;
			return (
				_eLocal4 && _(),
				function () {
					_eLocal4 = ! 1;
				}
			 );
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! u && o ) {
					openNotificationWithIcon( i, o );
				}
			},
			[ o ]
		);
		var w = ( function () {
				var _nLocal2 = b2(
					p2().m( function _nLocal3() {
						var _aLocal;
						return p2().w(
							function ( _nLocal4 ) {
								for (;;) {
									switch ( ( _nLocal4.p = _nLocal4.n ) ) {
										case 0:
											{
												_nLocal4.n = 1;
												break;
											}
											return _nLocal4.a( 2 );
										case 1:
											if (
												'' !== r.students_number &&
												null !== r.students_number
											) {
												_nLocal4.n = 2;
												break;
											}
											return (
												openNotificationWithIcon(
													'error',
													( 0, I18n.__ )(
														'The fields cannot be empty.',
														'ohmylms'
													)
												),
												_nLocal4.a( 2 )
											 );
										case 2:
											if ( 'fastest_time' === r.rules ) {
												_nLocal4.n = 3;
												break;
											}
											if (
												'' !== r.threshold &&
												null !== r.threshold
											) {
												_nLocal4.n = 3;
												break;
											}
											return (
												openNotificationWithIcon(
													'error',
													( 0, I18n.__ )(
														'The fields cannot be empty.',
														'ohmylms'
													)
												),
												_nLocal4.a( 2 )
											 );
										case 3:
											return (
												t.setLoadingSetting( ! 0 ),
												p( ! 0 ),
												( _nLocal4.p = 4 ),
												( _nLocal4.n = 5 ),
												l()( {
													path: '/ohmylms/v1/engagement/settings/leaderboard',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify( r ),
												} )
											 );
										case 5:
											return (
												( _aLocal = _nLocal4.v ),
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Settings saved successfully.',
														'ohmylms'
													)
												),
												_nLocal4.a( 2, _aLocal )
											 );
										case 6:
											_nLocal4.p = 6;
											p( ! 1 );
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'Something went wrong.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 7:
											return (
												( _nLocal4.p = 7 ),
												t.setLoadingSetting( ! 1 ),
												p( ! 1 ),
												_nLocal4.f( 7 )
											 );
										case 8:
											return _nLocal4.a( 2 );
									}
								}
							},
							_nLocal3,
							null,
							[ [ 4, 6, 7, 8 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _nLocal2.apply( this, _args2 );
				};
			} )(),
			E = function ( _tLocal4, _nLocal5 ) {
				{
					var _oLocal = g2(
						g2( {}, r ),
						{},
						h2( {}, _tLocal4, _nLocal5 )
					);
					a( _oLocal );
				}
			},
			S = [
				{
					label: ( 0, I18n.__ )(
						'Course completion rate',
						'ohmylms'
					),
					value: 'completion_rate',
				},
				{
					label: ( 0, I18n.__ )(
						'Highest average quiz score',
						'ohmylms'
					),
					value: 'highest_quiz',
				},
				{
					label: ( 0, I18n.__ )(
						'Fastest completion time',
						'ohmylms'
					),
					value: 'fastest_time',
				},
			];
		if ( u ) {
			return (
				<React.Fragment>
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP marginTop={ 2.5 } padding={ 6 }>
							<Controls.SkeletonWP active={ ! 0 } rows={ 15 } />
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			);
		}
		var R =
			( 'fastest_time' !== r.rules &&
				( '' === r.threshold ||
					null === r.threshold ||
					r.threshold < 0 ) ) ||
			'' === r.students_number ||
			null === r.students_number ||
			r.students_number < 0;
		return (
			<React.Fragment>
				{ contextHolder }
				<FeatureSwitch
					settings={ r }
					setSettings={ a }
					title={ I18n.__( 'Leaderboard', 'ohmylms' ) }
					description={ I18n.__(
						'Highlight learner achievements using the ranking settings below.',
						'ohmylms'
					) }
					label={ I18n.__( 'Enable leaderboard', 'ohmylms' ) }
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
									<Controls.CardWP
										isBorderless={ ! 0 }
										padding={ '24px' }
										fullWidth={ ! 0 }
									>
										<Controls.FlexWP
											align={ 'flex-start' }
											justify={ 'flex-start' }
											gap={ 10 }
											direction={ 'column' }
										>
											<Controls.FlexItemWP
												fullWidth={ ! 0 }
											>
												<Controls.FlexWP
													align={ 'flex-start' }
													justify={ 'space-between' }
												>
													<Controls.FlexItemWP
														style={ {
															width: '50%',
														} }
													>
														<Controls.HeadingWP
															level={ '4' }
														>
															{ ( 0, I18n.__ )(
																'Rank students based on',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														<Controls.SpacerWP
															marginBottom={ 1 }
														/>
														<Controls.TextWP>
															{ ( 0, I18n.__ )(
																'Select a criterion to rank students.',
																'ohmylms'
															) }
														</Controls.TextWP>
													</Controls.FlexItemWP>
													<Controls.FlexItemWP
														style={ {
															width: '40%',
														} }
													>
														<Controls.SelectWP
															placeholder={ ( 0,
															I18n.__ )(
																'Select a criterion',
																'ohmylms'
															) }
															onChange={ function (
																_tLocal5
															) {
																{
																	var _nLocal6 =
																		g2(
																			g2(
																				{},
																				r
																			),
																			{},
																			{
																				rules: _tLocal5,
																			}
																		);
																	a(
																		_nLocal6
																	);
																}
															} }
															value={ r.rules }
															options={ S }
														/>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.FlexItemWP>
											{ 'fastest_time' !== r.rules && (
												<React.Fragment>
													<Controls.FlexItemWP
														fullWidth={ ! 0 }
													>
														<Controls.FlexWP
															align={
																'flex-start'
															}
															justify={
																'space-between'
															}
														>
															<Controls.FlexItemWP
																style={ {
																	width: '50%',
																} }
															>
																<Controls.HeadingWP
																	level={
																		'4'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Set the threshold for the leaderboard',
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
																		'Set the minimum threshold value required for students to be included in the leaderboard.',
																		'ohmylms'
																	) }
																</Controls.TextWP>
															</Controls.FlexItemWP>
															<Controls.FlexItemWP
																style={ {
																	width: '40%',
																} }
															>
																<Controls.InputNumberWP
																	value={
																		r ===
																			null ||
																		r ===
																			void 0
																			? void 0
																			: r.threshold
																	}
																	onChange={ function (
																		_eLocal5
																	) {
																		return E(
																			'threshold',
																			_eLocal5
																		);
																	} }
																	min={ 0 }
																	suffix={
																		'completion_rate' ===
																			( r ===
																				null ||
																			r ===
																				void 0
																				? void 0
																				: r.rules ) ||
																		'highest_quiz' ===
																			( r ===
																				null ||
																			r ===
																				void 0
																				? void 0
																				: r.rules )
																			? '%'
																			: ''
																	}
																/>
															</Controls.FlexItemWP>
														</Controls.FlexWP>
													</Controls.FlexItemWP>
												</React.Fragment>
											) }
										</Controls.FlexWP>
									</Controls.CardWP>
									<Controls.CardWP
										isBorderless={ ! 0 }
										padding={ '24px' }
										fullWidth={ ! 0 }
									>
										<Controls.FlexWP
											align={ 'flex-start' }
											justify={ 'flex-start' }
											gap={ 4 }
											direction={ 'column' }
										>
											<Controls.FlexItemWP
												fullWidth={ ! 0 }
											>
												<Controls.FlexWP
													align={ 'flex-start' }
													justify={ 'space-between' }
												>
													<Controls.FlexItemWP
														style={ {
															width: '50%',
														} }
													>
														<Controls.HeadingWP
															level={ '4' }
														>
															{ ( 0, I18n.__ )(
																'Minimum Students Required',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														<Controls.SpacerWP
															marginBottom={ 1 }
														/>
														<Controls.TextWP>
															{ ( 0, I18n.__ )(
																'Set the minimum number of students required to display the leaderboard.',
																'ohmylms'
															) }
														</Controls.TextWP>
													</Controls.FlexItemWP>
													<Controls.FlexItemWP
														style={ {
															width: '40%',
														} }
													>
														<Controls.InputNumberWP
															value={
																r.students_number
															}
															onChange={ function (
																_eLocal6
															) {
																return E(
																	'students_number',
																	_eLocal6
																);
															} }
															min={ 0 }
														/>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.CardWP>
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
							onClick={ w }
							isBusy={ m }
							disabled={ R }
						>
							{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
						</Controls.ButtonWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
