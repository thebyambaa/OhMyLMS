/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { FeatureSwitch } from './FeatureSwitch';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createBadgeSettings( readRuntime ) {
	return function BadgeSettings() {
		const {
			He,
			React,
			T: StoreModule,
			b: I18n,
			b3,
			f3: BadgeList,
			g: ReactHooks,
			l,
			v3,
			y: WordPressData,
			y3,
		} = readRuntime();
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = b3(
			( 0, ReactHooks.useState )( {
				enable: ! 1,
				rules: [
					{
						badgeId: 0,
						settings: [
							{
								dataLabel: 'Points',
								dataValue: 'points',
								dataFieldType: 'select',
								compareSign: '>=',
								compareData: 100,
								compareDataFieldType: 'input',
							},
						],
					},
				],
			} ),
			2
		);
		var r = n[ 0 ];
		var a = n[ 1 ];
		var o = b3( ( 0, ReactHooks.useState )( [] ), 2 );
		var i = ( o[ 0 ], o[ 1 ], b3( ( 0, ReactHooks.useState )( ! 1 ), 2 ) );
		var c =
			( i[ 0 ],
			i[ 1 ],
			b3(
				( 0, ReactHooks.useState )( {
					name: '',
					slug: '',
					description: '',
					image: null,
				} ),
				2
			) );
		var u = ( c[ 0 ], c[ 1 ], useNotifications() );
		var openNotificationWithIcon = u.openNotificationWithIcon;
		var contextHolder = u.contextHolder;
		var m = b3( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var p =
			( m[ 0 ],
			m[ 1 ],
			( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ) );
		var f = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationStatus();
		}, [] );
		var v = b3( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var h = v[ 0 ];
		var _ = v[ 1 ];
		var w = b3( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var R = b3( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var x = R[ 0 ];
		var C = R[ 1 ];
		b3( ( 0, ReactHooks.useState )( null ), 2 );
		( 0, ReactHooks.useEffect )( function () {
			var _tLocal = ( function () {
				var _eLocal3 = y3(
					v3().m( function _eLocal4() {
						var _tLocal2;
						return v3().w( function ( _eLocal5 ) {
							for (;;) {
								switch ( _eLocal5.n ) {
									case 0:
										return (
											_( ! 0 ),
											( _eLocal5.n = 1 ),
											l()( {
												path: 'ohmylms/v1/engagement/settings/badge',
											} )
										 );
									case 1:
										_tLocal2 = _eLocal5.v;
										a( _tLocal2 || r );
										_( ! 1 );
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal5.a( 2 );
								}
							}
						}, _eLocal4 );
					} )
				);
				return function ( ..._args ) {
					return _eLocal3.apply( this, _args );
				};
			} )();
			_tLocal();
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! h && p ) {
					openNotificationWithIcon( f, p );
				}
			},
			[ p ]
		);
		var O = ( function () {
			var _nLocal = y3(
				v3().m( function _nLocal2() {
					return v3().w(
						function ( _nLocal3 ) {
							for (;;) {
								switch ( ( _nLocal3.p = _nLocal3.n ) ) {
									case 0:
										{
											_nLocal3.n = 1;
											break;
										}
										return _nLocal3.a( 2 );
									case 1:
										return (
											t.setLoadingSetting( ! 0 ),
											S( ! 0 ),
											( _nLocal3.p = 2 ),
											( _nLocal3.n = 3 ),
											l()( {
												path: '/ohmylms/v1/engagement/settings/badge',
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
												},
												body: JSON.stringify( r ),
											} )
										 );
									case 3:
										openNotificationWithIcon(
											'success',
											( 0, I18n.__ )(
												'Settings saved successfully.',
												'ohmylms'
											)
										);
										_nLocal3.n = 5;
										break;
									case 4:
										_nLocal3.p = 4;
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
											( _nLocal3.p = 5 ),
											t.setLoadingSetting( ! 1 ),
											S( ! 1 ),
											_nLocal3.f( 5 )
										 );
									case 6:
										return _nLocal3.a( 2 );
								}
							}
						},
						_nLocal2,
						null,
						[ [ 2, 4, 5, 6 ] ]
					);
				} )
			);
			return function ( ..._args2 ) {
				return _nLocal.apply( this, _args2 );
			};
		} )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! h && p ) {
						openNotificationWithIcon( f, p );
					}
				},
				[ p ]
			),
			h ? (
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP marginTop={ 2.5 } padding={ 6 }>
						<Controls.SkeletonWP active={ ! 0 } rows={ 15 } />
					</Controls.SpacerWP>
				</Controls.CardWP>
			) : (
				<React.Fragment>
					{ contextHolder }
					<FeatureSwitch
						settings={ r }
						setSettings={ a }
						title={ I18n.__( 'Achievement badges', 'ohmylms' ) }
						description={ I18n.__(
							'Celebrate learning achievements with badges and earning rules.',
							'ohmylms'
						) }
						label={ I18n.__(
							'Enable achievement badges',
							'ohmylms'
						) }
					/>

					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
					>
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
								<BadgeList />
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.SpacerWP paddingTop={ 6 } paddingBottom={ 25 }>
						<Controls.FlexWP justify={ 'flex-end' }>
							<Controls.ButtonWP
								variant={ 'primary' }
								size={ 'md' }
								onClick={ O }
								isBusy={ E }
							>
								{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					{ x && (
						<React.Fragment>
							<He.default isOpen={ x } onClose={ C } />
						</React.Fragment>
					) }
				</React.Fragment>
			)
		 );
	};
}
