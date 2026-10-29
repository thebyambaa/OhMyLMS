import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createAdvancedSettings( readRuntime ) {
	return function AdvancedSettings( props ) {
		const {
			Ea,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			b: I18n,
			cJ,
			dJ,
			g: ReactHooks,
			l,
			mJ,
			y: WordPressData,
		} = readRuntime();
		var t = props.activeTab,
			n = props.handleSave,
			r = props.handleMigration,
			a = props.selectedCourses,
			o = props.isSaving,
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = useNotifications(),
			u = c.openNotificationWithIcon,
			s = c.contextHolder,
			d = mJ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			f = mJ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			v = f[ 0 ],
			h = f[ 1 ];
		( 0, ReactHooks.useEffect )( function () {
			var e = ( function () {
				var _eLocal = dJ(
					cJ().m( function _eLocal2() {
						var _tLocal, _nLocal;
						return cJ().w(
							function ( _eLocal3 ) {
								for (;;) {
									switch ( ( _eLocal3.p = _eLocal3.n ) ) {
										case 0:
											return (
												i.setLoadingSetting( ! 0 ),
												( _eLocal3.p = 1 ),
												( _eLocal3.n = 2 ),
												l()( {
													path: 'ohmylms/v1/settings/advanced',
												} )
											 );
										case 2:
											_tLocal = _eLocal3.v;
											i.setAdvancedSettings( _tLocal );
											_eLocal3.n = 4;
											break;
										case 3:
											_eLocal3.p = 3;
											_nLocal = _eLocal3.v;
											showRequestError( _nLocal );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal3.p = 4 ),
												i.setLoadingSetting( ! 1 ),
												_eLocal3.f( 4 )
											 );
										case 5:
											return _eLocal3.a( 2 );
									}
								}
							},
							_eLocal2,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal.apply( this, _args );
				};
			} )();
			e();
		}, [] );
		var _ = ( 0, ReactHooks.useCallback )(
				dJ(
					cJ().m( function e() {
						var _tLocal2,
							_nLocal2,
							_rLocal,
							_aLocal,
							_oLocal,
							_iLocal,
							_lLocal;
						return cJ().w(
							function ( _eLocal4 ) {
								for (;;) {
									switch ( ( _eLocal4.p = _eLocal4.n ) ) {
										case 0:
											return (
												p( ! 0 ),
												( _eLocal4.p = 1 ),
												( _rLocal =
													new URLSearchParams() ).append(
													'action',
													'ohmylms_delete_transient_cache'
												),
												_rLocal.append(
													'nonce',
													( null ===
														( _tLocal2 =
															window.ohmylms_params ) ||
													void 0 === _tLocal2
														? void 0
														: _tLocal2.delete_cache_nonce ) ||
														''
												),
												( _eLocal4.n = 2 ),
												fetch(
													( null ===
														( _nLocal2 =
															window.ohmylms_params ) ||
													void 0 === _nLocal2
														? void 0
														: _nLocal2.ajax_url ) ||
														'/wp-admin/admin-ajax.php',
													{
														method: 'POST',
														headers: {
															'Content-Type':
																'application/x-www-form-urlencoded',
														},
														credentials:
															'same-origin',
														body: _rLocal.toString(),
													}
												)
											 );
										case 2:
											return (
												( _aLocal = _eLocal4.v ),
												( _eLocal4.n = 3 ),
												_aLocal.json()
											 );
										case 3:
											if (
												( _oLocal = _eLocal4.v ).success
											) {
												u(
													'success',
													_oLocal.data.message
												);
											} else {
												u(
													'error',
													( null ===
														( _iLocal =
															_oLocal.data ) ||
													void 0 === _iLocal
														? void 0
														: _iLocal.message ) ||
														( 0, I18n.__ )(
															'Failed to delete cache.',
															'ohmylms'
														)
												);
											}
											_eLocal4.n = 5;
											break;
										case 4:
											_eLocal4.p = 4;
											_lLocal = _eLocal4.v;
											showRequestError( _lLocal );
											u(
												'error',
												( 0, I18n.__ )(
													'An error occurred. Please try again.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal4.p = 5 ),
												p( ! 1 ),
												_eLocal4.f( 5 )
											 );
										case 6:
											return _eLocal4.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 1, 4, 5, 6 ] ]
						);
					} )
				),
				[ u ]
			),
			w = ( 0, ReactHooks.useCallback )(
				dJ(
					cJ().m( function e() {
						var _tLocal3, _nLocal3;
						return cJ().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											return (
												h( ! 0 ),
												( _eLocal5.p = 1 ),
												( _eLocal5.n = 2 ),
												l()( {
													path: '/ohmylms/v1/restore-default-pages',
													method: 'POST',
												} )
											 );
										case 2:
											_tLocal3 = _eLocal5.v;
											u( 'success', _tLocal3.message );
											_eLocal5.n = 4;
											break;
										case 3:
											_eLocal5.p = 3;
											_nLocal3 = _eLocal5.v;
											showRequestError( _nLocal3 );
											u(
												'error',
												( 0, I18n.__ )(
													'Failed to restore pages. Please try again.',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal5.p = 4 ),
												h( ! 1 ),
												_eLocal5.f( 4 )
											 );
										case 5:
											return _eLocal5.a( 2 );
									}
								}
							},
							e,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				),
				[ u ]
			);
		return (
			<React.Fragment>
				{ s }
				<Ea
					isBorderless={ ! 0 }
					variant={ 'secondary' }
					className={ 'ohmylms-full-screen-height' }
				>
					<Controls.SpacerWP
						padding={ 4 }
						marginTop={ 0 }
						marginBottom={ 0 }
					>
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP
								padding={ 6 }
								marginTop={ 0 }
								marginBottom={ 4 }
							>
								<div>
									<h3
										style={ {
											margin: '0px',
											fontSize: '16px',
											fontWeight: 600,
										} }
									>
										{ ( 0, I18n.__ )(
											'Remove OhMyLMS Transient cache',
											'ohmylms'
										) }
									</h3>
									<p
										style={ {
											marginTop: '8px',
											color: '#687784',
											fontSize: '14px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Click the button below to delete all OhMyLMS transient cache. This can help resolve certain issues with cached data.',
											'ohmylms'
										) }
									</p>
									<Controls.ButtonWP
										variant={ 'primary' }
										danger={ ! 0 }
										onClick={ _ }
										loading={ m }
										disabled={ m }
									>
										{ m
											? ( 0, I18n.__ )(
													'Deleting...',
													'ohmylms'
												)
											: ( 0, I18n.__ )(
													'Delete Cache',
													'ohmylms'
												) }
									</Controls.ButtonWP>
								</div>
								<div
									style={ {
										marginTop: '32px',
										paddingTop: '24px',
										borderTop: '1px solid #e0e0e0',
									} }
								>
									<h3
										style={ {
											margin: '0px',
											fontSize: '16px',
											fontWeight: 600,
										} }
									>
										{ ( 0, I18n.__ )(
											'Restore Default Pages',
											'ohmylms'
										) }
									</h3>
									<p
										style={ {
											marginTop: '8px',
											color: '#687784',
											fontSize: '14px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Recreates any missing default pages (Course Archive, Checkout, Student Dashboard). Existing pages are never overwritten or duplicated.',
											'ohmylms'
										) }
									</p>
									<Controls.ButtonWP
										variant={ 'secondary' }
										onClick={ w }
										loading={ v }
										disabled={ v }
									>
										{ v
											? ( 0, I18n.__ )(
													'Restoring...',
													'ohmylms'
												)
											: ( 0, I18n.__ )(
													'Restore Default Pages',
													'ohmylms'
												) }
									</Controls.ButtonWP>
								</div>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<MemoSettingsActionBar
							activeTab={ t }
							handleSave={ n }
							handleMigration={ r }
							selectedCourses={ a }
							isSaving={ o }
						/>
					</Controls.SpacerWP>
				</Ea>
			</React.Fragment>
		);
	};
}
