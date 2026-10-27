/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { FeatureSwitch } from './FeatureSwitch';
export function createLevelSettings( readRuntime ) {
	return function LevelSettings() {
		const {
			$3: LevelList,
			He,
			I: Controls,
			React,
			T: StoreModule,
			X3,
			a5,
			b: I18n,
			g: ReactHooks,
			l,
			o5,
			t5,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = o5(
			( 0, ReactHooks.useState )( {
				enable: ! 1,
				rules: [
					{
						levelId: 0,
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
		var o = o5( ( 0, ReactHooks.useState )( [] ), 2 );
		var i = o[ 0 ];
		var c = o[ 1 ];
		var u = ( 0, Notifications.A )();
		var openNotificationWithIcon = u.openNotificationWithIcon;
		var contextHolder = u.contextHolder;
		var m = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getNotificationMessage();
		}, [] );
		var p = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getNotificationStatus();
		}, [] );
		var f = o5( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var v = f[ 0 ];
		var h = f[ 1 ];
		var _ = o5( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var w = _[ 0 ];
		var E = _[ 1 ];
		var S = o5( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var R = S[ 0 ];
		var x = S[ 1 ];
		( 0, ReactHooks.useEffect )( function () {
			var _tLocal = ( function () {
				var _eLocal3 = a5(
					t5().m( function _eLocal4() {
						var _tLocal2;
						return t5().w( function ( _eLocal5 ) {
							for (;;) {
								switch ( _eLocal5.n ) {
									case 0:
										return (
											h( ! 0 ),
											( _eLocal5.n = 1 ),
											l()( {
												path: 'ohmylms/v1/engagement/settings/level',
											} )
										 );
									case 1:
										_tLocal2 = _eLocal5.v;
										a( _tLocal2 || r );
										h( ! 1 ); // Falls through to the shared async state cleanup.
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
				if ( ! v && m ) {
					openNotificationWithIcon( p, m );
				}
			},
			[ m ]
		);
		var C = ( function () {
			var _nLocal = a5(
				t5().m( function _nLocal2() {
					var _aLocal;
					return t5().w(
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
											E( ! 0 ),
											( _nLocal3.p = 2 ),
											( _aLocal = X3( {}, r ) ),
											0 < i.length &&
												( _aLocal.levels = i ),
											( _nLocal3.n = 3 ),
											l()( {
												path: '/ohmylms/v1/engagement/settings/level',
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
												},
												body: JSON.stringify( _aLocal ),
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
											E( ! 1 ),
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
					if ( ! v && m ) {
						openNotificationWithIcon( p, m );
					}
				},
				[ m ]
			),
			v ? (
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
						title={ I18n.__( 'Learner levels', 'ohmylms' ) }
						description={ I18n.__(
							'Recognize learner progress with levels and achievement rules.',
							'ohmylms'
						) }
						label={ I18n.__( 'Enable learner levels', 'ohmylms' ) }
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
								<LevelList setLevelList={ c } />
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.SpacerWP paddingTop={ 6 } paddingBottom={ 25 }>
						<Controls.FlexWP justify={ 'flex-end' }>
							<Controls.ButtonWP
								variant={ 'primary' }
								size={ 'md' }
								onClick={ C }
								isBusy={ w }
							>
								{ ( 0, I18n.__ )( 'Save', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					{ R && (
						<React.Fragment>
							<He.default isOpen={ R } onClose={ x } />
						</React.Fragment>
					) }
				</React.Fragment>
			)
		 );
	};
}
