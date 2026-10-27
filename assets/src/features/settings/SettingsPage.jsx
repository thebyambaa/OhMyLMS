import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { MovableTabPanel } from '../navigation/MovableTabPanel';
import { AiTutorSettings } from './AiTutorSettings';
import { XApiSettings } from './XApiSettings';
import { DesignSystemSettings } from './DesignSystemSettings';
export function createSettingsPage( readRuntime ) {
	return function SettingsPage() {
		const {
			C5,
			E6: MemoMigrationSettings,
			HG,
			I: Controls,
			J1: MemoPaymentSettings,
			JK: MemoAccountPrivacySettings,
			O6,
			P4,
			P6,
			R6,
			React,
			S6,
			T: StoreModule,
			V0: MemoDesignSettings,
			YG,
			b: I18n,
			f: Router,
			g: ReactHooks,
			jK: MemoGeneralSettings,
			l,
			lJ: MemoPermalinkSettings,
			o4,
			qK: MemoBrandingSettings,
			vJ: MemoAdvancedSettings,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		HG( 'ohmylms', 'settings' );
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).isSettingsLoading();
		}, [] );
		var r = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
			return _eLocal2( StoreModule.default ).getGeneralSettings();
		}, [] );
		var a = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
			return _eLocal3( StoreModule.default ).getPaymentSettings();
		}, [] );
		var o = ( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
			return _eLocal4( StoreModule.default ).getDesignSettings();
		}, [] );
		var i = ( 0, WordPressData.useSelect )( function ( _eLocal5 ) {
			return _eLocal5( StoreModule.default ).getAccountPrivacySettings();
		}, [] );
		var c = ( 0, WordPressData.useSelect )( function ( _eLocal6 ) {
			return _eLocal6( StoreModule.default ).getAdvancedSettings();
		}, [] );
		var u = ( 0, WordPressData.useSelect )( function ( _eLocal7 ) {
			return _eLocal7( StoreModule.default ).getPermalinkSettings();
		}, [] );
		var s = ( 0, WordPressData.useSelect )( function ( _eLocal8 ) {
			return _eLocal8( StoreModule.default ).getMigrationCourses();
		}, [] );
		var d = ( 0, WordPressData.useSelect )( function ( _eLocal9 ) {
			return _eLocal9( StoreModule.default ).getMigrationTool();
		}, [] );
		var m = ( 0, Notifications.A )();
		var p = m.openNotificationWithIcon;
		var v = m.contextHolder;
		var h = ( 0, Router.Zp )();
		var _ = ( 0, Router.g )();
		var w = _.tab;
		var E = _.subTab;
		var S = _.subPanel;
		var R = O6( ( 0, ReactHooks.useState )( w || 'general-settings' ), 2 );
		var x = R[ 0 ];
		var C = R[ 1 ];
		var P = O6( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var O = P[ 0 ];
		var k = P[ 1 ];
		var j = function ( _eLocal0 ) {
			return Object.keys( _eLocal0 ).reduce( function (
				_tLocal,
				_nLocal
			) {
				return (
					( _tLocal[ _nLocal ] = _eLocal0[ _nLocal ].value ),
					_tLocal
				 );
			}, {} );
		};
		var A = ( function () {
			var _eLocal1 = P6(
				R6().m( function _eLocal10( _nLocal2, _rLocal ) {
					var _aLocal, _oLocal;
					return R6().w(
						function ( _eLocal11 ) {
							for (;;) {
								switch ( ( _eLocal11.p = _eLocal11.n ) ) {
									case 0:
										return (
											t.setLoadingSetting( ! 0 ),
											( _eLocal11.p = 1 ),
											( _eLocal11.n = 2 ),
											l()( {
												path: '/ohmylms/v1/settings/'.concat(
													_nLocal2
												),
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
												},
												body: JSON.stringify( _rLocal ),
											} )
										 );
									case 2:
										return (
											( _aLocal = _eLocal11.v ),
											_eLocal11.a( 2, _aLocal )
										 );
									case 3:
										throw (
											( _eLocal11.p = 3 ),
											( _oLocal = _eLocal11.v ),
											showRequestError( _oLocal ),
											_oLocal
										 );
									case 4:
										return (
											( _eLocal11.p = 4 ),
											t.setLoadingSetting( ! 1 ),
											_eLocal11.f( 4 )
										 );
									case 5:
										return _eLocal11.a( 2 );
								}
							}
						},
						_eLocal10,
						null,
						[ [ 1, 3, 4, 5 ] ]
					);
				} )
			);
			return function ( ..._args3 ) {
				return _eLocal1.apply( this, _args3 );
			};
		} )();
		var N = ( function () {
			var _nLocal4 = P6(
				R6().m( function _nLocal5() {
					var _lLocal, _sLocal, _dLocal, _mLocal, f, _vLocal, g, y;
					return R6().w(
						function ( _nLocal6 ) {
							for (;;) {
								switch ( ( _nLocal6.p = _nLocal6.n ) ) {
									case 0:
										return (
											( _lLocal = j( i ) ),
											( _sLocal = j( r ) ),
											( _dLocal = j( a ) ),
											( _mLocal = j( o ) ),
											( f = j( c ) ),
											( _vLocal = j( u ) ),
											( g = ! 0 ),
											'layout_2' !==
												( _mLocal === null ||
												_mLocal === void 0
													? void 0
													: _mLocal.ohmylms_single_course_page_layout ) ||
												'grid-style1' !==
													( _mLocal === null ||
													_mLocal === void 0
														? void 0
														: _mLocal.ohmylms_archive_page_layout_style ),
											false,
											( _nLocal6.p = 1 ),
											k( ! 0 ),
											( _nLocal6.n = 2 ),
											A( 'account-and-privacy', _lLocal )
										 );
									case 2:
										return (
											( _nLocal6.n = 3 ),
											A( 'general', _sLocal )
										 );
									case 3:
										return (
											( _nLocal6.n = 4 ),
											A( 'payment-gateway', _dLocal )
										 );
									case 4:
										return (
											( _nLocal6.n = 5 ),
											A( 'design', _mLocal )
										 );
									case 5:
										return (
											( _nLocal6.n = 6 ),
											A( 'advanced', f )
										 );
									case 6:
										return (
											( _nLocal6.n = 7 ),
											A( 'permalink', _vLocal )
										 );
									case 7:
										if ( g ) {
											p(
												'success',
												'Saved successfully'
											);
										}
										_nLocal6.n = 9;
										break;
									case 8:
										_nLocal6.p = 8;
										y = _nLocal6.v;
										showRequestError( y );
										p(
											'error',
											( 0, I18n.__ )(
												'Failed to save settings. Please try again.',
												'ohmylms'
											)
										);
									// Falls through to the shared async state cleanup.
									case 9:
										return (
											( _nLocal6.p = 9 ),
											k( ! 1 ),
											_nLocal6.f( 9 )
										 );
									case 10:
										return _nLocal6.a( 2 );
								}
							}
						},
						_nLocal5,
						null,
						[ [ 1, 8, 9, 10 ] ]
					);
				} )
			);
			return function ( ..._args ) {
				return _nLocal4.apply( this, _args );
			};
		} )();
		var D = ( function () {
			var _eLocal12 = P6(
				R6().m( function _eLocal13() {
					var _nLocal7, _rLocal2, _aLocal2;
					return R6().w(
						function ( _eLocal14 ) {
							for (;;) {
								switch ( ( _eLocal14.p = _eLocal14.n ) ) {
									case 0:
										t.setMigrationModalOpen( ! 0 );
										t.setMigrationStatus( 'migrating' );
										_eLocal14.p = 1;
										_nLocal7 = 0;
									// Falls through to the shared async state cleanup.
									case 2:
										if ( ! ( _nLocal7 < s.length ) ) {
											_eLocal14.n = 5;
											break;
										}
										return (
											( _eLocal14.n = 3 ),
											t.migrateSingleCourse(
												d,
												s[ _nLocal7 ]
											)
										 );
									case 3:
										if (
											( ( _rLocal2 = _eLocal14.v ),
											s[ _nLocal7 ] === _rLocal2 )
										) {
											_eLocal14.n = 4;
											break;
										}
										return (
											p(
												'error',
												( 0, I18n.__ )(
													'Failed to migrate course. Please try again.',
													'ohmylms'
												)
											),
											t.setMigrationStatus( null ),
											t.setMigrationModalOpen( ! 1 ),
											_eLocal14.a( 3, 5 )
										 );
									case 4:
										_nLocal7++;
										_eLocal14.n = 2;
										break;
									case 5:
										_eLocal14.n = 7;
										break;
									case 6:
										_eLocal14.p = 6;
										_aLocal2 = _eLocal14.v;
										showRequestError( _aLocal2 );
										p(
											'error',
											( 0, I18n.__ )(
												'Failed to migrate course. Please try again.',
												'ohmylms'
											)
										);
									// Falls through to the shared async state cleanup.
									case 7:
										return (
											( _eLocal14.p = 7 ),
											setTimeout( function () {
												t.setMigrationStatus( null );
											}, 200 ),
											_eLocal14.f( 7 )
										 );
									case 8:
										return _eLocal14.a( 2 );
								}
							}
						},
						_eLocal13,
						null,
						[ [ 1, 6, 7, 8 ] ]
					);
				} )
			);
			return function ( ..._args2 ) {
				return _eLocal12.apply( this, _args2 );
			};
		} )();
		var W = [
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'General', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'general-settings',
				children: (
					<MemoGeneralSettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Design', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'design-settings',
				children: (
					<MemoDesignSettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Branding', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'branding-settings',
				children: (
					<MemoBrandingSettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Account & Privacy', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'account-privacy-settings',
				children: (
					<MemoAccountPrivacySettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Permalink', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'parmalink-settings',
				children: (
					<MemoPermalinkSettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Payments', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'payments-settings',
				children: (
					<MemoPaymentSettings
						activeTab={ x }
						handleSave={ N }
						handleMigration={ D }
						selectedCourses={ s }
						isSaving={ O }
					/>
				),
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Emails', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'emails-settings',
				children: <P4 />,
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Migration', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'migration-settings',
				children: <MemoMigrationSettings />,
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'AI tools', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'ai-tutor-settings',
				children: <AiTutorSettings />,
			},
			{
				label: (
					<React.Fragment>
						{ ( 0, I18n.__ )( 'Design system', 'ohmylms' ) }
					</React.Fragment>
				),
				key: 'design-system-settings',
				children: <DesignSystemSettings />,
			},
			{
				label: 'xAPI',
				key: 'xapi-settings',
				children: <XApiSettings />,
			},
		].concat(
			S6(
				window.ohmylms_params.is_gamification_enabled
					? [
							{
								label: (
									<React.Fragment>
										{ ( 0, I18n.__ )(
											'Gamification',
											'ohmylms'
										) }
									</React.Fragment>
								),
								key: 'gamification-settings',
								children: <C5 />,
							},
						]
					: []
			),
			S6(
				window.ohmylms_params.is_webhook_enabled
					? [
							{
								label: (
									<React.Fragment>
										{ ( 0, I18n.__ )(
											'Webhooks',
											'ohmylms'
										) }
									</React.Fragment>
								),
								key: 'webhooks-settings',
								children: React.createElement( o4, null ),
							},
						]
					: []
			),
			[
				{
					label: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Advanced', 'ohmylms' ) }
						</React.Fragment>
					),
					key: 'advanced-settings',
					children: (
						<MemoAdvancedSettings
							activeTab={ x }
							handleSave={ N }
							handleMigration={ D }
							selectedCourses={ s }
							isSaving={ O }
						/>
					),
				},
			],
			S6( [] )
		);
		return (
			<React.Fragment>
				{ v }
				<Controls.ContainerWP>
					<YG
						title={ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
						showAddButton={ ! 1 }
					/>
					<Controls.CardWP
						isBorderless={ ! 0 }
						className={
							'ohmylms-full-screen-height ohmylms-settings-page'
						}
					>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 7.5 }>
							{ n && ! O && (
								<React.Fragment>
									<Controls.SkeletonWP
										active={ ! 0 }
										rows={ 10 }
										style={ {
											position: 'absolute',
											top: '60px',
											right: '44px',
											zIndex: 3,
											background: '#FFFFFF',
											height: 'calc(100% - 105px)',
											padding: '24px',
											width: 'calc(100% - 272px)',
											borderRadius: '8px',
										} }
									/>
								</React.Fragment>
							) }
							<MovableTabPanel
								scope="settings"
								items={ W }
								onChange={ function ( _eLocal15 ) {
									return ( function ( _eLocal16 ) {
										C( _eLocal16 );
										var _tLocal3 = '/settings/'.concat(
											_eLocal16
										);
										h(
											'payments-settings' === _eLocal16
												? ''.concat(
														_tLocal3,
														'/payments'
													)
												: ( () => {
														if (
															'design-settings' ===
															_eLocal16
														) {
															if (
																'course-details' ===
																E
															) {
																return ''
																	.concat(
																		_tLocal3,
																		'/'
																	)
																	.concat(
																		E,
																		'/'
																	)
																	.concat(
																		S !==
																			null &&
																			S !==
																				void 0
																			? S
																			: 'unenroll-view'
																	);
															}
															return ''
																.concat(
																	_tLocal3,
																	'/'
																)
																.concat(
																	[
																		'course-list',
																		'course-details',
																		'checkout-page',
																	].includes(
																		E
																	)
																		? E
																		: 'course-list'
																);
														}
														return _tLocal3;
													} )()
										);
									} )( _eLocal15 );
								} }
								activekey={ x }
								variant={ 'vertical' }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
