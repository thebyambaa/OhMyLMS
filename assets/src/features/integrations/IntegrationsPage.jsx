import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createIntegrationsPage( readRuntime ) {
	return function IntegrationsPage() {
		const {
			B7,
			D7,
			F7,
			G7: IntegrationManifest,
			H7: IntegrationCategories,
			HG,
			He,
			I: Controls,
			L7,
			M7: MemoIntegrationConfig,
			React,
			T: StoreModule,
			W6: MemoIntegrationCard,
			YG,
			b: I18n,
			df,
			g: ReactHooks,
			uf,
			y: WordPressData,
		} = readRuntime();
		HG( 'ohmylms', 'integrations' );
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getIntegrations();
			}, [] ),
			n = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationMessage();
			}, [] ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getNotificationStatus();
			}, [] ),
			a = L7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			l = useNotifications(),
			c = l.openNotificationWithIcon,
			u = l.contextHolder,
			s = L7( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = L7( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = L7( ( 0, ReactHooks.useState )( '' ), 2 ),
			_ = h[ 0 ],
			w = h[ 1 ],
			E = L7( ( 0, ReactHooks.useState )( null ), 2 ),
			S = E[ 0 ],
			R = E[ 1 ],
			x = ( function () {
				var _tLocal = B7(
					D7().m( function _tLocal2() {
						var _nLocal;
						return D7().w(
							function ( _tLocal3 ) {
								for (;;) {
									switch ( ( _tLocal3.p = _tLocal3.n ) ) {
										case 0:
											return (
												( _tLocal3.p = 0 ),
												i( ! 0 ),
												( _tLocal3.n = 1 ),
												e.getIntegrations()
											 );
										case 1:
											_tLocal3.n = 3;
											break;
										case 2:
											_tLocal3.p = 2;
											_nLocal = _tLocal3.v;
											showRequestError(
												'Error fetching integrations:',
												_nLocal
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _tLocal3.p = 3 ),
												i( ! 1 ),
												_tLocal3.f( 3 )
											 );
										case 4:
											return _tLocal3.a( 2 );
									}
								}
							},
							_tLocal2,
							null,
							[ [ 0, 2, 3, 4 ] ]
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
				_eLocal4 && x(),
				function () {
					_eLocal4 = ! 1;
				}
			 );
		}, [] );
		var C = Object.entries( IntegrationManifest ).map(
				function ( _eLocal5 ) {
					var _nLocal2 = L7( _eLocal5, 2 ),
						_rLocal = _nLocal2[ 0 ];
					return F7(
						F7(
							{
								key: _rLocal,
							},
							_nLocal2[ 1 ]
						),
						( t && t[ _rLocal ] ) || {}
					);
				}
			),
			P = Array.from(
				new Set(
					Object.values( IntegrationManifest ).flatMap(
						function ( _eLocal6 ) {
							return _eLocal6.categories || [];
						}
					)
				)
			),
			O = [ 'all' ].concat( P ),
			k = ( function () {
				var _nLocal3 = B7(
					D7().m( function _nLocal4( _rLocal2 ) {
						var _aLocal, _oLocal, _iLocal;
						return D7().w(
							function ( _nLocal5 ) {
								for (;;) {
									switch ( ( _nLocal5.p = _nLocal5.n ) ) {
										case 0:
										case 1:
											if (
												'wpfusion' !== _rLocal2.key ||
												( null !==
													( _aLocal = window ) &&
													void 0 !== _aLocal &&
													null !==
														( _aLocal =
															_aLocal.ohmylms_params ) &&
													void 0 !== _aLocal &&
													_aLocal.is_wpfusion_active )
											) {
												_nLocal5.n = 2;
												break;
											}
											return (
												m( ! 0 ),
												e.updateProModalTitle(
													( 0, I18n.__ )(
														'WP Fusion Required',
														'ohmylms'
													)
												),
												e.updateProModalContent(
													( 0, I18n.__ )(
														'To enable the WP Fusion integration, please ensure that the WP Fusion Lite plugin is installed and activated on your site.',
														'ohmylms'
													)
												),
												e.updateProModalButtonText(
													null
												),
												_nLocal5.a( 2 )
											 );
										case 2:
											return (
												( _nLocal5.p = 2 ),
												( ( _oLocal =
													t && Object.keys( t ).length
														? F7( {}, t )
														: Object.fromEntries(
																Object.entries(
																	IntegrationManifest
																).map(
																	function (
																		_eLocal7
																	) {
																		var _tLocal4 =
																			L7(
																				_eLocal7,
																				2
																			);
																		return [
																			_tLocal4[ 0 ],
																			{
																				is_enable: 0,
																				class: _tLocal4[ 1 ]
																					.class,
																			},
																		];
																	}
																)
															) )[
													_rLocal2.key
												] = F7(
													F7(
														{},
														_oLocal[ _rLocal2.key ]
													),
													{},
													{
														is_enable:
															_rLocal2.is_enable
																? 0
																: 1,
													}
												) ),
												( _nLocal5.n = 3 ),
												e.updateIntegrations( _oLocal )
											 );
										case 3:
											_nLocal5.n = 5;
											break;
										case 4:
											_nLocal5.p = 4;
											_iLocal = _nLocal5.v;
											showRequestError(
												'Error toggling integration:',
												_iLocal
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return _nLocal5.a( 2 );
									}
								}
							},
							_nLocal4,
							null,
							[ [ 2, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _nLocal3.apply( this, _args2 );
				};
			} )(),
			j = function ( _tLocal5 ) {
				if ( _tLocal5.key === 'xapi' ) {
					window.location.hash = '/settings/xapi-settings';
					return;
				}
				R( _tLocal5 );
			},
			A = C.filter( function ( _eLocal9 ) {
				return (
					'all' === f ||
					( _eLocal9.categories && _eLocal9.categories.includes( f ) )
				);
			} ).filter( function ( _eLocal0 ) {
				return _eLocal0.label.toLowerCase().includes( _.toLowerCase() );
			} );
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( n ) {
						c( r, n );
					}
				},
				[ n ]
			),
			(
				<React.Fragment>
					{ u }
					<Controls.ContainerWP>
						<YG
							title={ ( 0, I18n.__ )( 'Addons', 'ohmylms' ) }
							description={ ( 0, I18n.__ )(
								'Enable and manage addons for your LMS to enhance your course experience.',
								'ohmylms'
							) }
							showAddButton={ ! 1 }
						/>
						<Controls.CardWP
							isBorderless={ ! 0 }
							className={ 'integrations-card' }
						>
							<Controls.SpacerWP padding={ 5 } marginBottom={ 0 }>
								{ o && (
									<Controls.SkeletonWP
										active={ ! 0 }
										rows={ 10 }
										style={ {
											position: 'absolute',
											top: '0',
											left: '0',
											zIndex: 3,
											background: '#FFFFFF',
											height: '100%',
											padding: '40px',
											borderRadius: '8px',
										} }
									/>
								) }
								{ S ? (
									<MemoIntegrationConfig
										integration={ S }
										onSave={ function (
											_eLocal1,
											_tLocal6
										) {
											c( _eLocal1, _tLocal6 );
										} }
										onCancel={ function () {
											return R( null );
										} }
										className={
											'ohmylms-integrations-config'
										}
									/>
								) : (
									<React.Fragment>
										<Controls.FlexWP
											gap={ 4 }
											justifyContent={ 'space-between' }
										>
											<div
												className={
													'integrations-filter-buttons'
												}
											>
												{ O.map(
													function ( _eLocal10 ) {
														var _tLocal7;
														return (
															<Controls.ButtonWP
																key={
																	_eLocal10
																}
																isPrimary={
																	f ===
																	_eLocal10
																}
																onClick={ function () {
																	return v(
																		_eLocal10
																	);
																} }
															>
																{ 'all' ===
																_eLocal10
																	? ( 0,
																		I18n.__ )(
																			'All',
																			'ohmylms'
																		)
																	: ( null ===
																			( _tLocal7 =
																				IntegrationCategories[
																					_eLocal10
																				] ) ||
																		void 0 ===
																			_tLocal7
																			? void 0
																			: _tLocal7.label ) ||
																		_eLocal10 }
															</Controls.ButtonWP>
														);
													}
												) }
											</div>
											<div
												className={
													'integrations-search-control'
												}
											>
												<Controls.SearchControlWP
													value={ _ }
													onChange={ w }
													placeholder={ ( 0,
													I18n.__ )(
														'Search integrations…',
														'ohmylms'
													) }
												/>
											</div>
										</Controls.FlexWP>
										<Controls.SpacerWP marginBottom={ 5 } />
										{ 0 < A.length ? (
											<Controls.GridWP
												columns={ 3 }
												gap={ 4 }
											>
												{ A.map(
													function ( _eLocal11 ) {
														return (
															<MemoIntegrationCard
																key={
																	_eLocal11.key
																}
																integration={
																	_eLocal11
																}
																onToggle={ k }
																onManage={ j }
															/>
														);
													}
												) }
											</Controls.GridWP>
										) : (
											<React.Fragment>
												{ React.createElement( uf, {
													icon: React.createElement(
														df,
														null
													),
													title: ( 0, I18n.__ )(
														'No integrations found',
														'ohmylms'
													),
												} ) }
											</React.Fragment>
										) }
									</React.Fragment>
								) }
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.ContainerWP>
					{ d && (
						<React.Fragment>
							<He.default isOpen={ d } onClose={ m } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
