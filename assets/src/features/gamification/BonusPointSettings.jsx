/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { FeatureSwitch } from './FeatureSwitch';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createBonusPointSettings( readRuntime ) {
	return function BonusPointSettings() {
		const {
			A2,
			I2,
			M2,
			O2,
			React,
			T: StoreModule,
			T2,
			b: I18n,
			g: ReactHooks,
			l,
			lf,
			x2,
			y: WordPressData,
		} = readRuntime();
		var e;
		var t;
		var n;
		var r;
		var a;
		var o;
		var c = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var u = M2(
			( 0, ReactHooks.useState )( {
				enable: ! 1,
				rules: [
					{
						label: ( 0, I18n.__ )( 'Course Completion', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user completes an entire course.',
							'ohmylms'
						),
						slug: 'course_completion_rate',
						value: ! 1,
						threshold: 100,
						point: 10,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Lesson Completion', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a learner finishes a lesson successfully.',
							'ohmylms'
						),
						slug: 'lesson_complete',
						value: ! 1,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Quiz Achievement', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a learner scores at or above the defined pass mark on a quiz.',
							'ohmylms'
						),
						slug: 'quiz_passing_mark',
						value: ! 1,
						threshold: 30,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )(
							'Assignment Achievement',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Award points when a learner meets the minimum passing score on an assignment.',
							'ohmylms'
						),
						slug: 'assignment_passing_mark',
						value: ! 1,
						threshold: 30,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Quiz Submission', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points whenever a quiz is submitted, regardless of the score.',
							'ohmylms'
						),
						slug: 'submit_quiz',
						value: ! 1,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )(
							'Assignment Submission',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Award points when a learner submits an assignment for review.',
							'ohmylms'
						),
						slug: 'submit_assignment',
						value: ! 1,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )(
							'First Comment on a Course',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Award points when a learner comments on a course for the first time.',
							'ohmylms'
						),
						slug: 'comment_on_course',
						value: ! 1,
						point: 2,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'User Registration', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a new user registers on the site.',
							'ohmylms'
						),
						slug: 'user_registration',
						value: ! 1,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Course Enrollment', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user enrolls in a course.',
							'ohmylms'
						),
						slug: 'course_enrollment',
						value: ! 1,
						point: 5,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Purchase', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user makes a purchase on the site.',
							'ohmylms'
						),
						slug: 'purchase',
						value: ! 1,
						point: 10,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )( 'Post on Community', 'ohmylms' ),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user creates a new post in the community.',
							'ohmylms'
						),
						slug: 'community_post_create',
						value: ! 1,
						point: 8,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )(
							'Comment on Community Post',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user comments on a community post.',
							'ohmylms'
						),
						slug: 'community_post_comment',
						value: ! 1,
						point: 3,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
					{
						label: ( 0, I18n.__ )(
							'Reaction on Community',
							'ohmylms'
						),
						tooltip: ( 0, I18n.__ )(
							'Award points when a user reacts to community content (likes, loves, etc.).',
							'ohmylms'
						),
						slug: 'community_reaction',
						value: ! 1,
						point: 1,
						email: {
							enable: ! 1,
							subject: '',
							body: '',
						},
					},
				],
			} ),
			2
		);
		var s = u[ 0 ];
		var d = u[ 1 ];
		var m = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getNotificationMessage();
		}, [] );
		var p = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationStatus();
		}, [] );
		var f = M2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var v = f[ 0 ];
		var h = f[ 1 ];
		var _ = M2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var w = _[ 0 ];
		var E = _[ 1 ];
		var S = M2( ( 0, ReactHooks.useState )( null ), 2 );
		var R = S[ 0 ];
		var x = S[ 1 ];
		var C = M2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var P = C[ 0 ];
		var O = C[ 1 ];
		var k = M2( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var j = k[ 0 ];
		var A = k[ 1 ];
		var M = useNotifications();
		var openNotificationWithIcon = M.openNotificationWithIcon;
		var contextHolder = M.contextHolder;
		var D = ( 0, ReactHooks.useRef )( null );
		( 0, ReactHooks.useEffect )( function () {
			var _eLocal3 = ( function () {
				var _eLocal4 = A2(
					x2().m( function _eLocal5() {
						var _tLocal, _nLocal, _rLocal;
						return x2().w( function ( _eLocal6 ) {
							for (;;) {
								switch ( _eLocal6.n ) {
									case 0:
										return (
											h( ! 0 ),
											( _eLocal6.n = 1 ),
											l()( {
												path: 'ohmylms/v1/engagement/settings/point',
											} )
										 );
									case 1:
										_tLocal = _eLocal6.v;
										d( _tLocal || s );
										h( ! 1 );
										if ( _tLocal ) {
											{
												_nLocal = W(
													_tLocal.rules || [],
													q
												);
												d(
													O2(
														O2( {}, _tLocal ),
														{},
														{
															rules: _nLocal,
														}
													)
												);
												_rLocal = _nLocal.find(
													function ( _eLocal7 ) {
														return _eLocal7.value;
													}
												);
												x( _rLocal );
											}
										}
									// Falls through to the shared async state cleanup.
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
			_eLocal3();
		}, [] );
		var W = function ( _eLocal8, _tLocal2 ) {
			var _nLocal2 = ( function ( _eLocal9 ) {
				return (
					( function ( _eLocal0 ) {
						if ( Array.isArray( _eLocal0 ) ) {
							return I2( _eLocal0 );
						}
					} )( _eLocal9 ) ||
					( function ( _eLocal1 ) {
						if (
							( 'undefined' !== typeof Symbol &&
								( ( _nullableValue ) =>
									_nullableValue !== null &&
									_nullableValue !== void 0 )(
									_eLocal1[ Symbol.iterator ]
								) ) ||
							( ( _nullableValue2 ) =>
								_nullableValue2 !== null &&
								_nullableValue2 !== void 0 )(
								_eLocal1[ '@@iterator' ]
							)
						) {
							return Array.from( _eLocal1 );
						}
					} )( _eLocal9 ) ||
					T2( _eLocal9 ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( _eLocal8 );
			return (
				_tLocal2.forEach( function ( _tLocal3 ) {
					if (
						! _eLocal8.some( function ( _eLocal10 ) {
							return _eLocal10.slug === _tLocal3.slug;
						} )
					) {
						_nLocal2.push( _tLocal3 );
					}
				} ),
				_nLocal2
			 );
		};
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! v && m ) {
					openNotificationWithIcon( p, m );
				}
			},
			[ m ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( R ) {
					var _eLocal11 = s.rules.findIndex( function ( _eLocal12 ) {
						return _eLocal12.slug === R.slug;
					} );
					d(
						O2(
							O2( {}, s ),
							{},
							{
								rules: s.rules.map(
									function ( _tLocal4, _nLocal3 ) {
										return _nLocal3 === _eLocal11
											? R
											: _tLocal4;
									}
								),
							}
						)
					);
				}
			},
			[ R ]
		);
		var B = ( function () {
				var _eLocal13 = A2(
					x2().m( function _eLocal14() {
						var _tLocal5;
						return x2().w(
							function ( _eLocal15 ) {
								for (;;) {
									switch ( ( _eLocal15.p = _eLocal15.n ) ) {
										case 0:
											{
												_eLocal15.n = 1;
												break;
											}
											return _eLocal15.a( 2 );
										case 1:
											return (
												c.setLoadingSetting( ! 0 ),
												E( ! 0 ),
												( _eLocal15.p = 2 ),
												( _eLocal15.n = 3 ),
												l()( {
													path: '/ohmylms/v1/engagement/settings/point',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify( s ),
												} )
											 );
										case 3:
											return (
												( _tLocal5 = _eLocal15.v ),
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Settings saved successfully.',
														'ohmylms'
													)
												),
												_eLocal15.a( 2, _tLocal5 )
											 );
										case 4:
											_eLocal15.p = 4;
											E( ! 1 );
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
												( _eLocal15.p = 5 ),
												c.setLoadingSetting( ! 1 ),
												E( ! 1 ),
												_eLocal15.f( 5 )
											 );
										case 6:
											return _eLocal15.a( 2 );
									}
								}
							},
							_eLocal14,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal13.apply( this, _args2 );
				};
			} )(),
			V = ( function () {
				var _eLocal16 = A2(
					x2().m( function _eLocal17( _tLocal6, _nLocal4 ) {
						var _rLocal2, _aLocal;
						return x2().w(
							function ( _eLocal18 ) {
								for (;;) {
									switch ( ( _eLocal18.p = _eLocal18.n ) ) {
										case 0:
											return (
												d( function ( _eLocal19 ) {
													var _rLocal3 =
														_eLocal19.rules.map(
															function (
																_eLocal20,
																_rLocal4
															) {
																return _rLocal4 ===
																	_tLocal6
																	? O2(
																			O2(
																				{},
																				_eLocal20
																			),
																			{},
																			{
																				value: _nLocal4,
																			}
																		)
																	: _eLocal20;
															}
														);
													return O2(
														O2( {}, _eLocal19 ),
														{},
														{
															rules: _rLocal3,
														}
													);
												} ),
												A( ! 1 ),
												( _rLocal2 = O2(
													O2( {}, s ),
													{},
													{
														rules: s.rules.map(
															function (
																_eLocal21,
																_rLocal5
															) {
																return _rLocal5 ===
																	_tLocal6
																	? O2(
																			O2(
																				{},
																				_eLocal21
																			),
																			{},
																			{
																				value: _nLocal4,
																			}
																		)
																	: _eLocal21;
															}
														),
													}
												) ),
												( _aLocal =
													_rLocal2.rules.filter(
														function ( _eLocal22 ) {
															return _eLocal22 ===
																null ||
																_eLocal22 ===
																	void 0
																? void 0
																: _eLocal22.value;
														}
													) ),
												( () => {
													if ( _nLocal4 ) {
														return x(
															_rLocal2.rules[
																_tLocal6
															]
														);
													}
													if ( _aLocal.length > 0 ) {
														return x(
															_aLocal[ 0 ]
														);
													}
													return x( null );
												} )(),
												( _eLocal18.p = 1 ),
												( _eLocal18.n = 2 ),
												l()( {
													path: '/ohmylms/v1/engagement/settings/point',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_rLocal2
													),
												} )
											 );
										case 2:
											openNotificationWithIcon(
												'success',
												( 0, I18n.__ )(
													'Rule updated successfully.',
													'ohmylms'
												)
											);
											_eLocal18.n = 4;
											break;
										case 3:
											_eLocal18.p = 3;
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'Failed to update rule.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return _eLocal18.a( 2 );
									}
								}
							},
							_eLocal17,
							null,
							[ [ 1, 3 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return _eLocal16.apply( this, _args4 );
				};
			} )(),
			H = function () {
				x( null );
				O( ! 1 );
			},
			G = ( function () {
				var _eLocal23 = A2(
					x2().m( function _eLocal24() {
						var _tLocal8;
						return x2().w(
							function ( _eLocal25 ) {
								for (;;) {
									switch ( ( _eLocal25.p = _eLocal25.n ) ) {
										case 0:
											if ( R ) {
												_eLocal25.n = 1;
												break;
											}
											return _eLocal25.a( 2 );
										case 1:
											return (
												E( ! 0 ),
												( _eLocal25.p = 2 ),
												( _tLocal8 = O2(
													O2( {}, s ),
													{},
													{
														rules: s.rules.map(
															function (
																_eLocal26,
																_tLocal9
															) {
																return _tLocal9 ===
																	R.index
																	? R
																	: _eLocal26;
															}
														),
													}
												) ),
												( _eLocal25.n = 3 ),
												l()( {
													path: '/ohmylms/v1/engagement/settings/point',
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_tLocal8
													),
												} )
											 );
										case 3:
											d( _tLocal8 );
											openNotificationWithIcon(
												'success',
												( 0, I18n.__ )(
													'Settings saved successfully.',
													'ohmylms'
												)
											);
											H();
											_eLocal25.n = 5;
											break;
										case 4:
											_eLocal25.p = 4;
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'Failed to save settings.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal25.p = 5 ),
												E( ! 1 ),
												_eLocal25.f( 5 )
											 );
										case 6:
											return _eLocal25.a( 2 );
									}
								}
							},
							_eLocal24,
							null,
							[ [ 2, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return _eLocal23.apply( this, _args3 );
				};
			} )(),
			U = function () {
				return s.rules.filter( function ( _eLocal27 ) {
					return _eLocal27 === null || _eLocal27 === void 0
						? void 0
						: _eLocal27.value;
				} );
			},
			q = [
				{
					label: ( 0, I18n.__ )( 'Course Completion', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user completes an entire course.',
						'ohmylms'
					),
					slug: 'course_completion_rate',
					value: ! 1,
					threshold: 100,
					point: 10,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Lesson Completion', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a learner finishes a lesson successfully.',
						'ohmylms'
					),
					slug: 'lesson_complete',
					value: ! 1,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Quiz Achievement', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a learner scores at or above the defined pass mark on a quiz.',
						'ohmylms'
					),
					slug: 'quiz_passing_mark',
					value: ! 1,
					threshold: 30,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )(
						'Assignment Achievement',
						'ohmylms'
					),
					tooltip: ( 0, I18n.__ )(
						'Award points when a learner meets the minimum passing score on an assignment.',
						'ohmylms'
					),
					slug: 'assignment_passing_mark',
					value: ! 1,
					threshold: 30,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Quiz Submission', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points whenever a quiz is submitted, regardless of the score.',
						'ohmylms'
					),
					slug: 'submit_quiz',
					value: ! 1,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Assignment Submission', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a learner submits an assignment for review.',
						'ohmylms'
					),
					slug: 'submit_assignment',
					value: ! 1,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )(
						'First Comment on a Course',
						'ohmylms'
					),
					tooltip: ( 0, I18n.__ )(
						'Award points when a learner comments on a course for the first time.',
						'ohmylms'
					),
					slug: 'comment_on_course',
					value: ! 1,
					point: 2,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'User Registration', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a new user registers on the site.',
						'ohmylms'
					),
					slug: 'user_registration',
					value: ! 1,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Course Enrollment', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user enrolls in a course.',
						'ohmylms'
					),
					slug: 'course_enrollment',
					value: ! 1,
					point: 5,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Purchase', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user makes a purchase on the site.',
						'ohmylms'
					),
					slug: 'purchase',
					value: ! 1,
					point: 10,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Post on Community', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user creates a new post in the community.',
						'ohmylms'
					),
					slug: 'community_post_create',
					value: ! 1,
					point: 8,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )(
						'Comment on Community Post',
						'ohmylms'
					),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user comments on a community post.',
						'ohmylms'
					),
					slug: 'community_post_comment',
					value: ! 1,
					point: 3,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
				{
					label: ( 0, I18n.__ )( 'Reaction on Community', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Award points when a user reacts to community content (likes, loves, etc.).',
						'ohmylms'
					),
					slug: 'community_reaction',
					value: ! 1,
					point: 1,
					email: {
						enable: ! 1,
						subject: '',
						body: '',
					},
				},
			],
			Y = function ( _eLocal28 ) {
				x( function ( _tLocal0 ) {
					return O2(
						O2( {}, _tLocal0 ),
						{},
						{
							email: O2(
								O2( {}, _tLocal0.email ),
								{},
								{
									body: _eLocal28,
								}
							),
						}
					);
				} );
			},
			Q = function ( _eLocal29 ) {
				if ( ! _eLocal29 ) {
					return ! 1;
				}
				var _tLocal1 =
						null === _eLocal29.point ||
						'' === _eLocal29.point ||
						_eLocal29.point < 0,
					_nLocal6 =
						void 0 !== _eLocal29.threshold &&
						( null === _eLocal29.threshold ||
							'' === _eLocal29.threshold ||
							_eLocal29.threshold < 0 );
				return _tLocal1 || _nLocal6;
			};
		return v ? (
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
					settings={ s }
					setSettings={ d }
					title={ I18n.__( 'Bonus points', 'ohmylms' ) }
					description={ I18n.__(
						'Reward learner activity with points using the rules below.',
						'ohmylms'
					) }
					label={ I18n.__( 'Enable bonus points', 'ohmylms' ) }
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
							gap={ 2 }
						>
							{
								<React.Fragment>
									<Controls.CardWP
										isBorderless={ ! 0 }
										padding={ '24px' }
										fullWidth={ ! 0 }
									>
										<Controls.FlexWP
											justify={ 'space-between' }
											align={ 'center' }
										>
											<Controls.FlexItemWP>
												<Controls.HeadingWP
													level={ '4' }
													color={ '#000D25' }
													size={ '18px' }
												>
													{ ( 0, I18n.__ )(
														'Add Bonus Point Rules',
														'ohmylms'
													) }
												</Controls.HeadingWP>
												<Controls.SpacerWP
													marginBottom={ 1 }
												/>
												<Controls.TextWP
													style={ {
														maxWidth: '500px',
													} }
													as={ 'p' }
													color={ '#687784' }
													size={ '14px' }
												>
													{ ( 0, I18n.__ )(
														'Configure how and when learners earn points based on their actions within the course. Use this to boost motivation and track progress.',
														'ohmylms'
													) }
												</Controls.TextWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP>
												{ React.createElement( lf, {
													ref: D,
													label: ( 0, I18n.__ )(
														'Add Rules',
														'ohmylms'
													),
													onClick() {
														return A( ! j );
													},
												} ) }
												{ j && (
													<Controls.PopoverWP
														onClose={ function () {
															return A( ! 1 );
														} }
														anchor={ D.current }
														onFocusOutside={ function () {
															return A( ! 1 );
														} }
														placement={
															'bottom-end'
														}
														style={ {
															marginTop: '8px',
														} }
													>
														<div
															style={ {
																padding: '16px',
																width: '400px',
																maxHeight:
																	'60%',
															} }
														>
															<Controls.FlexWP
																direction={
																	'column'
																}
																gap={ '3' }
															>
																<div
																	style={ {
																		padding:
																			'12px',
																		cursor: 'pointer',
																		borderBottom:
																			'1px solid #eee',
																	} }
																>
																	<Controls.HeadingWP
																		level={
																			'4'
																		}
																		style={ {
																			fontWeight: 500,
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Select Rules to Enable'
																		) }
																	</Controls.HeadingWP>
																</div>
																{ s.rules
																	.filter(
																		function (
																			_eLocal30
																		) {
																			return ! _eLocal30.value;
																		}
																	)
																	.map(
																		function (
																			_eLocal31,
																			_tLocal10
																		) {
																			var _nLocal7 =
																				s.rules.findIndex(
																					function (
																						_tLocal11
																					) {
																						return (
																							_tLocal11.slug ===
																							_eLocal31.slug
																						);
																					}
																				);
																			return (
																				<div
																					key={
																						_eLocal31.slug
																					}
																					style={ {
																						padding:
																							'12px',
																						cursor: 'pointer',
																						borderTop:
																							''.concat(
																								0 ===
																									_tLocal10
																									? 'none'
																									: '1px solid #eee'
																							),
																					} }
																					onClick={ function () {
																						if (
																							Q(
																								R
																							)
																						) {
																							openNotificationWithIcon(
																								'error',
																								( 0,
																								I18n.__ )(
																									'Please fix the errors in the current rule before adding a new one.',
																									'ohmylms'
																								)
																							);
																						} else {
																							{
																								V(
																									_nLocal7,
																									! 0
																								);
																								A(
																									! 1
																								);
																							}
																						}
																					} }
																					onMouseEnter={ function (
																						_eLocal32
																					) {
																						return ( _eLocal32.currentTarget.style.backgroundColor =
																							'#f0f0f0' );
																					} }
																					onMouseLeave={ function (
																						_eLocal33
																					) {
																						return ( _eLocal33.currentTarget.style.backgroundColor =
																							'transparent' );
																					} }
																					role="button"
																					tabIndex={
																						0
																					}
																					onKeyDown={ (
																						event
																					) => {
																						if (
																							event.key ===
																								'Enter' ||
																							event.key ===
																								' '
																						) {
																							event.preventDefault();
																							event.currentTarget.click();
																						}
																					} }
																				>
																					<Controls.TextWP
																						style={ {
																							fontWeight: 500,
																						} }
																					>
																						{
																							_eLocal31.label
																						}
																					</Controls.TextWP>
																					<Controls.SpacerWP
																						marginBottom={
																							1
																						}
																					/>
																					<Controls.TextWP
																						style={ {
																							fontSize:
																								'12px',
																							color: '#666',
																						} }
																					>
																						{
																							_eLocal31.tooltip
																						}
																					</Controls.TextWP>
																				</div>
																			);
																		}
																	) }
																{ 0 ===
																	s.rules.filter(
																		function (
																			_eLocal34
																		) {
																			return ! (
																				_eLocal34 !==
																					null &&
																				_eLocal34 !==
																					void 0 &&
																				_eLocal34.value
																			);
																		}
																	)
																		.length && (
																	<div
																		style={ {
																			padding:
																				'12px',
																			cursor: 'pointer',
																		} }
																		onMouseEnter={ function (
																			_eLocal35
																		) {
																			return ( _eLocal35.currentTarget.style.backgroundColor =
																				'#f0f0f0' );
																		} }
																		onMouseLeave={ function (
																			_eLocal36
																		) {
																			return ( _eLocal36.currentTarget.style.backgroundColor =
																				'transparent' );
																		} }
																	>
																		<Controls.TextWP
																			style={ {
																				fontWeight: 500,
																			} }
																		>
																			{ ( 0,
																			I18n.__ )(
																				'All rules are enabled'
																			) }
																		</Controls.TextWP>
																	</div>
																) }
															</Controls.FlexWP>
														</div>
													</Controls.PopoverWP>
												) }
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.CardWP>
									<Controls.FlexWP
										gap={ 5 }
										style={ {
											marginTop: '24px',
										} }
										align={ 'flex-start' }
									>
										<Controls.FlexItemWP
											style={ {
												flex: '0 0 300px',
											} }
										>
											<div
												style={ {
													display: 'flex',
													flexDirection: 'column',
													gap: '8px',
													maxHeight: '400px',
													overflowY: 'auto',
													marginBottom: '16px',
												} }
											>
												{ U().length > 0 && (
													<Controls.TextWP
														style={ {
															fontWeight: '500',
														} }
													>
														{ ( 0, I18n.__ )(
															'Active Rules',
															'ohmylms'
														) }
														{ ( 0, I18n.__ )(
															'(' +
																U().length +
																')',
															'ohmylms'
														) }
													</Controls.TextWP>
												) }
												{ U().map(
													function ( _eLocal37 ) {
														var _nLocal8 =
															s.rules.findIndex(
																function (
																	_tLocal13
																) {
																	return (
																		_tLocal13.slug ===
																		_eLocal37.slug
																	);
																}
															);
														return (
															<Controls.CardWP
																key={
																	_eLocal37.slug
																}
																isBorderless={
																	! 0
																}
																padding={
																	'12px'
																}
																onClick={ function () {
																	if (
																		Q( R )
																	) {
																		openNotificationWithIcon(
																			'error',
																			( 0,
																			I18n.__ )(
																				'Please fix the errors in the current rule before switching.',
																				'ohmylms'
																			)
																		);
																	} else {
																		x(
																			O2(
																				O2(
																					{},
																					_eLocal37
																				),
																				{},
																				{
																					index: _nLocal8,
																				}
																			)
																		);
																	}
																} }
																style={ {
																	cursor: 'pointer',
																	backgroundColor:
																		'#fff',
																	border:
																		( R ===
																			null ||
																		R ===
																			void 0
																			? void 0
																			: R.slug ) ===
																		_eLocal37.slug
																			? '1px solid #6e42d3'
																			: '1px solid #e0e0e0',
																	borderRadius:
																		'6px',
																} }
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
																			'4'
																		}
																		style={ {
																			fontWeight:
																				'500',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			_eLocal37.label,
																			'ohmylms'
																		) }
																	</Controls.HeadingWP>
																	<Controls.ButtonWP
																		variant={
																			'ghost'
																		}
																		size={
																			'sm'
																		}
																		onClick={ function (
																			_eLocal38
																		) {
																			_eLocal38.stopPropagation();
																			V(
																				_nLocal8,
																				! 1
																			);
																		} }
																		style={ {
																			padding:
																				'4px',
																		} }
																	>
																		{ '✕' }
																	</Controls.ButtonWP>
																</Controls.FlexWP>
																<Controls.FlexWP
																	justify={
																		'flex'
																	}
																	align={
																		'center'
																	}
																>
																	<Controls.TextWP
																		style={ {
																			fontWeight:
																				'500',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			_eLocal37.point,
																			'ohmylms'
																		) }
																		{ ( 0,
																		I18n.__ )(
																			' Points',
																			'ohmylms'
																		) }
																	</Controls.TextWP>
																</Controls.FlexWP>
															</Controls.CardWP>
														);
													}
												) }
											</div>
										</Controls.FlexItemWP>
										<Controls.FlexItemWP
											style={ {
												flex: '1',
											} }
										>
											{ R && (
												<Controls.CardWP
													isBorderless={ ! 0 }
													padding={ '24px' }
													style={ {
														backgroundColor: '#fff',
														borderRadius: '8px',
													} }
												>
													<Controls.FlexWP
														direction={ 'column' }
														gap={ 4 }
													>
														<div>
															<Controls.HeadingWP
																level={ '4' }
																style={ {
																	marginBottom:
																		'8px',
																} }
															>
																{ ( 0,
																I18n.__ )(
																	R.label,
																	'ohmylms'
																) }
															</Controls.HeadingWP>
															<Controls.TextWP
																style={ {
																	color: '#666',
																} }
															>
																{ ( 0,
																I18n.__ )(
																	R.tooltip,
																	'ohmylms'
																) }
															</Controls.TextWP>
														</div>
														<div
															style={ {
																borderTop:
																	'1px solid #eee',
																paddingTop:
																	'16px',
															} }
														>
															{ void 0 !==
																R.threshold && (
																<div
																	style={ {
																		marginBottom:
																			'20px',
																	} }
																>
																	<Controls.HeadingWP
																		level={
																			'5'
																		}
																		style={ {
																			marginBottom:
																				'8px',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Minimum Threshold',
																			'ohmylms'
																		) }
																		<span
																			style={ {
																				color: '#FF4955',
																			} }
																		>
																			{
																				'*'
																			}
																		</span>
																	</Controls.HeadingWP>
																	<Controls.TextWP
																		style={ {
																			fontSize:
																				'13px',
																			color: '#666',
																			marginBottom:
																				'12px',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Set the minimum percentage learners must achieve to earn points.',
																			'ohmylms'
																		) }
																	</Controls.TextWP>
																	<Controls.InputNumberWP
																		value={
																			R.threshold
																		}
																		onChange={ function (
																			_eLocal39
																		) {
																			return x(
																				O2(
																					O2(
																						{},
																						R
																					),
																					{},
																					{
																						threshold:
																							_eLocal39,
																					}
																				)
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
																</div>
															) }
															{ void 0 !==
																R.point && (
																<div>
																	<Controls.HeadingWP
																		level={
																			'5'
																		}
																		style={ {
																			marginBottom:
																				'8px',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Points Awarded',
																			'ohmylms'
																		) }
																		<span
																			style={ {
																				color: '#FF4955',
																			} }
																		>
																			{
																				'*'
																			}
																		</span>
																	</Controls.HeadingWP>
																	<Controls.TextWP
																		style={ {
																			fontSize:
																				'13px',
																			color: '#666',
																			marginBottom:
																				'12px',
																		} }
																	>
																		{ ( 0,
																		I18n.__ )(
																			'Number of points learners will earn when this rule is triggered.',
																			'ohmylms'
																		) }
																	</Controls.TextWP>
																	<Controls.InputNumberWP
																		value={
																			R.point
																		}
																		onChange={ function (
																			_eLocal40
																		) {
																			return x(
																				O2(
																					O2(
																						{},
																						R
																					),
																					{},
																					{
																						point: _eLocal40,
																					}
																				)
																			);
																		} }
																		min={
																			0
																		}
																	/>
																</div>
															) }
														</div>
														<Controls.CardWP
															isBorderless={ ! 0 }
															padding={ '20px' }
															style={ {
																backgroundColor:
																	'#f8f9fa',
																borderRadius:
																	'6px',
															} }
														>
															<Controls.FlexWP
																direction={
																	'column'
																}
																gap={ 3 }
															>
																{ void 0 !==
																	( null ===
																		( e =
																			R.email ) ||
																	void 0 === e
																		? void 0
																		: e.enable ) && (
																	<Controls.FlexItemWP>
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
																					style={ {
																						marginBottom:
																							'4px',
																					} }
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Send Email',
																						'ohmylms'
																					) }
																				</Controls.HeadingWP>
																				<Controls.TextWP
																					size={
																						'13px'
																					}
																					variant={
																						'muted'
																					}
																				>
																					{ ( 0,
																					I18n.__ )(
																						'Notify learners via email when they earn points.',
																						'ohmylms'
																					) }
																				</Controls.TextWP>
																			</Controls.FlexItemWP>
																			<Controls.FlexItemWP>
																				<Controls.SwitchWP
																					checked={
																						R
																							.email
																							.enable
																					}
																					onChange={ function (
																						_eLocal41
																					) {
																						return x(
																							function (
																								_tLocal14
																							) {
																								return O2(
																									O2(
																										{},
																										_tLocal14
																									),
																									{},
																									{
																										email: O2(
																											O2(
																												{},
																												_tLocal14.email
																											),
																											{},
																											{
																												enable: _eLocal41,
																											}
																										),
																									}
																								);
																							}
																						);
																					} }
																				/>
																			</Controls.FlexItemWP>
																		</Controls.FlexWP>
																	</Controls.FlexItemWP>
																) }
																{ ( null ===
																	( t =
																		R.email ) ||
																void 0 === t
																	? void 0
																	: t.enable ) && (
																	<React.Fragment>
																		<Controls.FlexItemWP>
																			<Controls.HeadingWP
																				level={
																					'5'
																				}
																				style={ {
																					marginBottom:
																						'8px',
																				} }
																			>
																				{ ( 0,
																				I18n.__ )(
																					'Email Subject',
																					'ohmylms'
																				) }
																			</Controls.HeadingWP>
																			<Controls.InputWP
																				value={
																					R
																						.email
																						.subject
																				}
																				onChange={ function (
																					_eLocal42
																				) {
																					return x(
																						function (
																							_tLocal15
																						) {
																							return O2(
																								O2(
																									{},
																									_tLocal15
																								),
																								{},
																								{
																									email: O2(
																										O2(
																											{},
																											_tLocal15.email
																										),
																										{},
																										{
																											subject:
																												_eLocal42,
																										}
																									),
																								}
																							);
																						}
																					);
																				} }
																			/>
																		</Controls.FlexItemWP>
																		<Controls.FlexItemWP>
																			<Controls.HeadingWP
																				level={
																					'5'
																				}
																				style={ {
																					marginBottom:
																						'8px',
																				} }
																			>
																				{ ( 0,
																				I18n.__ )(
																					'Email Body',
																					'ohmylms'
																				) }
																			</Controls.HeadingWP>
																			<Controls.TextareaWP
																				className={
																					'ohmylms-text-generate-prompt-input'
																				}
																				placeholder={ ( 0,
																				I18n.__ )(
																					'Write here...',
																					'ohmylms'
																				) }
																				value={
																					R
																						.email
																						.body
																				}
																				onChange={
																					Y
																				}
																			/>
																		</Controls.FlexItemWP>
																	</React.Fragment>
																) }
															</Controls.FlexWP>
														</Controls.CardWP>
													</Controls.FlexWP>
												</Controls.CardWP>
											) }
										</Controls.FlexItemWP>
									</Controls.FlexWP>
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
							onClick={ B }
							isBusy={ w }
							disabled={ Q( R ) }
						>
							{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
						</Controls.ButtonWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
				{ P && R.length > 0 && (
					<div
						style={ {
							position: 'fixed',
							top: 0,
							right: 0,
							width: '600px',
							height: '100vh',
							backgroundColor: '#f8f9fa',
							borderLeft: '1px solid #e0e0e0',
							boxShadow: '-2px 0 8px rgba(0,0,0,0.1)',
							zIndex: 1e3,
							overflowY: 'auto',
						} }
					>
						<div
							style={ {
								padding: '20px',
								paddingTop: '40px',
							} }
						>
							<Controls.FlexWP direction={ 'column' } gap={ 3 }>
								<Controls.FlexWP
									justify={ 'space-between' }
									align={ 'center' }
								>
									<Controls.HeadingWP
										level={ '3' }
										style={ {
											fontSize: '20px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Rule Configuration',
											'ohmylms'
										) }
									</Controls.HeadingWP>
									<Controls.ButtonWP
										variant={ 'ghost' }
										size={ 'sm' }
										onClick={ H }
										style={ {
											padding: '8px',
										} }
									>
										{ '✕' }
									</Controls.ButtonWP>
								</Controls.FlexWP>
								<Controls.CardWP
									isBorderless={ ! 0 }
									padding={ '20px' }
									style={ {
										backgroundColor: '#fff',
										borderRadius: '8px',
									} }
								>
									<Controls.FlexWP
										direction={ 'column' }
										gap={ 3 }
									>
										<div>
											<Controls.HeadingWP
												level={ '4' }
												style={ {
													marginBottom: '8px',
													fontSize: '18px',
												} }
											>
												{ ( 0, I18n.__ )(
													R.label,
													'ohmylms'
												) }
											</Controls.HeadingWP>
											<Controls.TextWP
												style={ {
													color: '#666',
													fontSize: '14px',
												} }
											>
												{ ( 0, I18n.__ )(
													R.tooltip,
													'ohmylms'
												) }
											</Controls.TextWP>
										</div>
										<div
											style={ {
												borderTop: '1px solid #eee',
												paddingTop: '16px',
											} }
										>
											{ void 0 !==
												( R === null || R === void 0
													? void 0
													: R.threshold ) && (
												<div
													style={ {
														marginBottom: '20px',
													} }
												>
													<Controls.HeadingWP
														level={ '5' }
														style={ {
															marginBottom: '8px',
															fontSize: '16px',
														} }
													>
														{ ( 0, I18n.__ )(
															'Minimum Threshold',
															'ohmylms'
														) }
													</Controls.HeadingWP>
													<Controls.TextWP
														style={ {
															fontSize: '13px',
															color: '#666',
															marginBottom:
																'12px',
														} }
													>
														{ ( 0, I18n.__ )(
															'Set the minimum percentage learners must achieve to earn points.',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Controls.InputNumberWP
														value={ R.threshold }
														onChange={ function (
															_eLocal43
														) {
															return x(
																O2(
																	O2( {}, R ),
																	{},
																	{
																		threshold:
																			_eLocal43,
																	}
																)
															);
														} }
														min={ 0 }
														max={ 100 }
														suffix={ '%' }
													/>
												</div>
											) }
											{ void 0 !==
												( R === null || R === void 0
													? void 0
													: R.point ) && (
												<div>
													<Controls.HeadingWP
														level={ '5' }
														style={ {
															marginBottom: '8px',
															fontSize: '16px',
														} }
													>
														{ ( 0, I18n.__ )(
															'Points Awarded',
															'ohmylms'
														) }
													</Controls.HeadingWP>
													<Controls.TextWP
														style={ {
															fontSize: '13px',
															color: '#666',
															marginBottom:
																'12px',
														} }
													>
														{ ( 0, I18n.__ )(
															'Number of points learners will earn when this rule is triggered.',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Controls.InputNumberWP
														value={ R.point }
														onChange={ function (
															_eLocal44
														) {
															return x(
																O2(
																	O2( {}, R ),
																	{},
																	{
																		point: _eLocal44,
																	}
																)
															);
														} }
														min={ 0 }
														max={ 100 }
													/>
												</div>
											) }
										</div>
									</Controls.FlexWP>
								</Controls.CardWP>
								<Controls.CardWP
									isBorderless={ ! 0 }
									padding={ '20px' }
									style={ {
										backgroundColor: '#fff',
										borderRadius: '8px',
									} }
								>
									<Controls.FlexWP
										direction={ 'column' }
										gap={ 3 }
									>
										{ void 0 !==
											( R === null ||
											R === void 0 ||
											null === ( n = R.email ) ||
											void 0 === n
												? void 0
												: n.enable ) && (
											<Controls.FlexItemWP
												style={ {
													marginBottom: '20px',
												} }
											>
												<Controls.FlexWP
													align={ 'flex-start' }
													justify={ 'space-between' }
												>
													<Controls.FlexItemWP>
														<Controls.HeadingWP
															level={ '5' }
															style={ {
																marginBottom:
																	'8px',
																fontSize:
																	'16px',
															} }
														>
															{ ( 0, I18n.__ )(
																'Send Email',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														<Controls.TextWP
															size={ '13px' }
															variant={ 'muted' }
														>
															{ ( 0, I18n.__ )(
																'Enable to notify learners via email when they earn points',
																'ohmylms'
															) }
														</Controls.TextWP>
													</Controls.FlexItemWP>
													<Controls.FlexItemWP>
														<Controls.SwitchWP
															checked={
																R.email.enable
															}
															onChange={ function (
																_eLocal45
															) {
																return x(
																	function (
																		_tLocal16
																	) {
																		return O2(
																			O2(
																				{},
																				_tLocal16
																			),
																			{},
																			{
																				email: O2(
																					O2(
																						{},
																						_tLocal16.email
																					),
																					{},
																					{
																						enable: _eLocal45,
																					}
																				),
																			}
																		);
																	}
																);
															} }
														/>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.FlexItemWP>
										) }
										{ ( R === null ||
										R === void 0 ||
										null === ( r = R.email ) ||
										void 0 === r
											? void 0
											: r.enable ) && (
											<div>
												<Controls.HeadingWP
													level={ '5' }
													style={ {
														marginBottom: '8px',
														fontSize: '16px',
													} }
												>
													{ ( 0, I18n.__ )(
														'Email Subject',
														'ohmylms'
													) }
												</Controls.HeadingWP>
												<Controls.TextWP
													size={ '13px' }
													variant={ 'muted' }
												>
													{ ( 0, I18n.__ )(
														'Enter the subject line for the email notification',
														'ohmylms'
													) }
												</Controls.TextWP>
												<Controls.InputWP
													value={
														R === null ||
														R === void 0 ||
														null ===
															( a = R.email ) ||
														void 0 === a
															? void 0
															: a.subject
													}
													onChange={ function (
														_eLocal46
													) {
														return x(
															function (
																_tLocal17
															) {
																return O2(
																	O2(
																		{},
																		_tLocal17
																	),
																	{},
																	{
																		email: O2(
																			O2(
																				{},
																				_tLocal17.email
																			),
																			{},
																			{
																				subject:
																					_eLocal46,
																			}
																		),
																	}
																);
															}
														);
													} }
												/>
											</div>
										) }
										{ ( R === null ||
										R === void 0 ||
										null === ( o = R.email ) ||
										void 0 === o
											? void 0
											: o.enable ) && (
											<Controls.FlexItemWP>
												<Controls.HeadingWP
													level={ '5' }
													style={ {
														marginBottom: '8px',
														fontSize: '16px',
													} }
												>
													{ ( 0, I18n.__ )(
														'Email body',
														'ohmylms'
													) }
												</Controls.HeadingWP>
												<Controls.TextareaWP
													className={
														'ohmylms-text-generate-prompt-input'
													}
													placeholder={ ( 0,
													I18n.__ )(
														'Write here...',
														'ohmylms'
													) }
													value={ R.email.body }
													onChange={ Y }
												/>
											</Controls.FlexItemWP>
										) }
									</Controls.FlexWP>
								</Controls.CardWP>
								<div
									style={ {
										borderTop: '1px solid #eee',
										paddingTop: '16px',
									} }
								>
									<Controls.FlexWP
										justify={ 'flex-end' }
										gap={ 2 }
									>
										<Controls.ButtonWP
											variant={ 'ghost' }
											onClick={ H }
										>
											{ ( 0, I18n.__ )(
												'Cancel',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'primary' }
											onClick={ G }
											isBusy={ w }
										>
											{ ( 0, I18n.__ )(
												'Save Configuration',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								</div>
							</Controls.FlexWP>
						</div>
					</div>
				) }
			</React.Fragment>
		);
	};
}
