import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createDashboard( readRuntime ) {
	return function Dashboard() {
		const {
			D: Buttons,
			GG,
			HG,
			I: Controls,
			NG: DashboardOverview,
			React,
			T: StoreModule,
			VG: CourseImportDialog,
			YG: PageHeader,
			b: I18n,
			cU,
			dU,
			g: ReactHooks,
			lU: CourseCreateDialog,
			mU,
			nf,
			y: WordPressData,
		} = readRuntime();
		HG( 'ohmylms', 'dashboard' );
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = useNotifications(),
			n = t.openNotificationWithIcon,
			r = t.contextHolder,
			a = true,
			o = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] ),
			l = mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			f = p[ 0 ],
			v = ( p[ 1 ], mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = ( 0, ReactHooks.useRef )( null ),
			x = mU( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			C = x[ 0 ],
			P = x[ 1 ],
			O =
				( ( 0, ReactHooks.useCallback )(
					function () {
						u( ! 0 );
					},
					[ a ]
				),
				( function () {
					var _tLocal = dU(
						cU().m( function _tLocal2( _nLocal ) {
							var _rLocal, _aLocal;
							return cU().w(
								function ( _tLocal3 ) {
									for (;;) {
										switch ( ( _tLocal3.p = _tLocal3.n ) ) {
											case 0:
												if ( _nLocal ) {
													_tLocal3.n = 1;
													break;
												}
												return _tLocal3.a( 2 );
											case 1:
												return (
													( _tLocal3.p = 1 ),
													m( ! 0 ),
													( _tLocal3.n = 2 ),
													e.importCourse( _nLocal )
												 );
											case 2:
												if (
													( ( _nullableValue ) =>
														_nullableValue !==
															null &&
														_nullableValue !==
															void 0 )(
														( _rLocal = _tLocal3.v )
													) &&
													_rLocal.success
												) {
													u( ! 1 );
												}
												_tLocal3.n = 4;
												break;
											case 3:
												_tLocal3.p = 3;
												_aLocal = _tLocal3.v;
												showRequestError(
													'Error exporting course:',
													_aLocal
												);
											// Falls through to the shared async state cleanup.
											case 4:
												return (
													( _tLocal3.p = 4 ),
													m( ! 1 ),
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
					return function ( ..._args2 ) {
						return _tLocal.apply( this, _args2 );
					};
				} )() ),
			k = ( function () {
				var _eLocal4 = dU(
					cU().m( function _eLocal5() {
						return cU().w( function ( _eLocal6 ) {
							for (;;) {
								switch ( _eLocal6.n ) {
									case 0:
										S( ! 0 );
									// Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 1:
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
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( o ) {
						n( i, o );
					}
				},
				[ o ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					var _eLocal7 = function ( _eLocal8 ) {
						if (
							R.current &&
							! R.current.contains( _eLocal8.target )
						) {
							P( ! 1 );
						}
					};
					return (
						C
							? document.addEventListener( 'mousedown', _eLocal7 )
							: document.removeEventListener(
									'mousedown',
									_eLocal7
								),
						function () {
							document.removeEventListener(
								'mousedown',
								_eLocal7
							);
						}
					 );
				},
				[ C ]
			),
			(
				<React.Fragment>
					{ r }
					<Controls.SurfaceWP>
						<Controls.ContainerWP isFullWidth={ ! 0 }>
							{ h && (
								<GG.A
									status={ 'error' }
									isClosable={ ! 0 }
									onRemove={ function () {
										return _( ! 1 );
									} }
								>
									{ ( 0, I18n.__ )(
										'Error creating course. Please try again.',
										'ohmylms'
									) }
								</GG.A>
							) }
							<PageHeader
								title={ ( 0, I18n.__ )(
									'Overview',
									'ohmylms'
								) }
								showAddButton={ ! 1 }
							>
								<Buttons.A
									variant={ 'primary' }
									isBusy={ f }
									onClick={ k }
									icon={ React.createElement( nf, null ) }
									iconPosition={ 'left' }
								>
									{ ( 0, I18n.__ )(
										'Add Course',
										'ohmylms'
									) }
								</Buttons.A>
							</PageHeader>
							<DashboardOverview handleAddCourse={ k } />
							<Controls.SpacerWP
								marginBottom={ 0 }
								paddingBottom={ 5 }
							/>
							{ c && (
								<React.Fragment>
									<CourseImportDialog
										onClose={ function () {
											u( ! 1 );
											m( ! 1 );
										} }
										onAction={ O }
										isOpen={ c }
										cancelBtnText={ ( 0, I18n.__ )(
											'Cancel',
											'ohmylms'
										) }
										actionBtnText={ ( 0, I18n.__ )(
											'Import',
											'ohmylms'
										) }
										loading={ d }
									/>
								</React.Fragment>
							) }
							{ E && (
								<CourseCreateDialog
									isOpen={ E }
									onClose={ function () {
										return S( ! 1 );
									} }
								/>
							) }
						</Controls.ContainerWP>
					</Controls.SurfaceWP>
				</React.Fragment>
			 )
		 );
	};
}
