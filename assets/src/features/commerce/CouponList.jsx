import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCouponList( readRuntime ) {
	return function CouponList() {
		const {
			Bre,
			Dre,
			Fre,
			Ge,
			I: Controls,
			Ie,
			Mre,
			Ne,
			OQ,
			React,
			T: StoreModule,
			We,
			Wre,
			aN,
			aY,
			b: I18n,
			df: EmptyIcon,
			fN,
			g: ReactHooks,
			hN,
			jre,
			l,
			lN,
			nf,
			pG,
			q,
			uf: EmptyState,
			v,
			y: WordPressData,
			z: Notifications,
			zre,
		} = readRuntime();
		var e = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] ),
			n = Bre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			r = n[ 0 ],
			a = n[ 1 ],
			o = Bre( ( 0, ReactHooks.useState )( null ), 2 ),
			i = o[ 0 ],
			c = o[ 1 ],
			u = Bre( ( 0, ReactHooks.useState )( [] ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = Bre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			h = Bre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			_ = h[ 0 ],
			w = h[ 1 ],
			E = Bre( ( 0, ReactHooks.useState )( '' ), 2 ),
			S = E[ 0 ],
			R = E[ 1 ],
			x = Bre( ( 0, ReactHooks.useState )( [] ), 2 ),
			C = x[ 0 ],
			P = x[ 1 ],
			O = Bre( ( 0, ReactHooks.useState )( [] ), 2 ),
			k = O[ 0 ],
			j = ( O[ 1 ], Bre( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			A = j[ 0 ],
			M = j[ 1 ],
			F = Bre( ( 0, ReactHooks.useState )( null ), 2 ),
			N = F[ 0 ],
			D = F[ 1 ],
			W = Bre( ( 0, ReactHooks.useState )( 1 ), 2 ),
			B = W[ 0 ],
			L = W[ 1 ],
			V = Bre( ( 0, ReactHooks.useState )( 0 ), 2 ),
			H = V[ 0 ],
			G = V[ 1 ],
			U = Bre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Y = U[ 0 ],
			Q = U[ 1 ],
			Z = ( 0, Notifications.A )(),
			$ = Z.openNotificationWithIcon,
			K = Z.contextHolder,
			J = ( 0, ReactHooks.useCallback )( function ( _eLocal3, _tLocal ) {
				var _nLocal = _eLocal3.target.checked;
				d( function ( _eLocal4 ) {
					return _nLocal
						? [].concat( zre( _eLocal4 ), [ _tLocal ] )
						: _eLocal4.filter( function ( _eLocal5 ) {
								return _eLocal5 !== _tLocal;
							} );
				} );
			}, [] ),
			X = ( 0, ReactHooks.useCallback )(
				function ( _eLocal6 ) {
					var _tLocal2 = _eLocal6.target.checked;
					d(
						_tLocal2
							? C.map( function ( _eLocal7 ) {
									return _eLocal7.id;
								} )
							: []
					);
				},
				[ C ]
			),
			ee = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							title: ( 0, I18n.__ )( 'Name', 'ohmylms' ),
							key: 'title',
							dataIndex: 'title',
							width: '20%',
							render( _eLocal8, _tLocal3 ) {
								return (
									<v.Link
										to={ '#' }
										onClick={ function () {
											return ae( _tLocal3 );
										} }
										className={ 'ohmylms-coupon-title' }
									>
										{ Ge( _eLocal8 ) || 'N/A' }
									</v.Link>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
							key: 'status',
							dataIndex: 'status',
							width: '10%',
							render( _eLocal9, _tLocal4 ) {
								return (
									<Controls.SwitchWP
										checked={ 'publish' === _eLocal9 }
										onChange={ function ( _eLocal0 ) {
											return le(
												Dre(
													Dre( {}, _tLocal4 ),
													{},
													{
														status: _eLocal0
															? 'publish'
															: 'draft',
													}
												)
											);
										} }
									/>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Code', 'ohmylms' ),
							key: 'code',
							dataIndex: 'code',
							width: '15%',
							render( _eLocal1 ) {
								return (
									<Controls.SpacerWP
										marginBottom={ 0 }
										className={ 'ohmylms-coupon-code' }
									>
										<Controls.TagWP
											style={ Wre(
												Wre(
													{
														fontSize: '12px',
														color: '#6E42D3',
														fontWeight: 500,
														lineHeight: '1.2',
														padding: '6px 12px 5px',
														backgroundColor:
															'#F4F5F7',
													},
													'color',
													'#000D25'
												),
												'borderRadius',
												'50px'
											) }
										>
											{ _eLocal1 || 'N/A' }
											<Controls.CopyToClipboard
												textToCopy={ _eLocal1 }
											/>
										</Controls.TagWP>
									</Controls.SpacerWP>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Amount', 'ohmylms' ),
							key: 'amount',
							dataIndex: 'amount',
							width: '10%',
							render( _eLocal10, _tLocal5 ) {
								return ( () => {
									if (
										'percent' === _tLocal5.discount_type
									) {
										return ''.concat(
											Math.round( _eLocal10 ),
											'%'
										);
									}
									if ( void 0 !== _eLocal10 ) {
										return OQ( _eLocal10, ! 0 );
									}
									return 'N/A';
								} )();
							},
						},
						{
							title: ( 0, I18n.__ )( 'Uses/Limit', 'ohmylms' ),
							key: 'uses_limit',
							width: '10%',
							render( _eLocal11, _tLocal6 ) {
								var _nLocal2 =
										void 0 !== _tLocal6.usage_count
											? _tLocal6.usage_count
											: '0',
									_rLocal =
										_tLocal6.usage_limit ||
										( 0, I18n.__ )( '∞', 'ohmylms' );
								return ''
									.concat( _nLocal2, '/' )
									.concat( _rLocal );
							},
						},
						{
							title: ( 0, I18n.__ )( 'Start Date', 'ohmylms' ),
							key: 'date_start',
							dataIndex: 'date_start',
							width: '10%',
							render( _eLocal12 ) {
								return (
									aN()( _eLocal12.date ).format(
										'MMMM DD, YYYY h:mm a'
									) || ( 0, I18n.__ )( 'Never', 'ohmylms' )
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'End Date', 'ohmylms' ),
							key: 'date_expires',
							dataIndex: 'date_expires',
							width: '10%',
							render( _eLocal13 ) {
								return (
									aN()( _eLocal13.date ).format(
										'MMMM DD, YYYY h:mm a'
									) || ( 0, I18n.__ )( 'Never', 'ohmylms' )
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Actions', 'ohmylms' ),
							key: 'actions',
							width: '10%',
							render( _eLocal14, _tLocal7 ) {
								return (
									<React.Fragment>
										<Controls.DropdownMenuWP
											controls={ [
												{
													title: ( 0, I18n.__ )(
														'Edit',
														'ohmylms'
													),
													onClick() {
														return ae( _tLocal7 );
													},
													icon: (
														<span>
															<pG.A />
														</span>
													),
												},
												{
													title: ( 0, I18n.__ )(
														'Delete',
														'ohmylms'
													),
													onClick() {
														return ne(
															_tLocal7 === null ||
																_tLocal7 ===
																	void 0
																? void 0
																: _tLocal7.id
														);
													},
													icon: (
														<span>
															<We />
														</span>
													),
												},
											] }
											icon={ <q.Icon icon={ Ne.A } /> }
										/>
									</React.Fragment>
								);
							},
						},
					];
				},
				[ C, s, J, X, ae, ne ]
			),
			te = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
						value: 'delete',
						action() {
							a( ! 0 );
						},
					},
				];
			}, [] ),
			ne = ( 0, ReactHooks.useCallback )( function ( _eLocal15 ) {
				a( ! 0 );
				c( _eLocal15 );
			}, [] ),
			re = ( 0, ReactHooks.useCallback )( function () {
				a( ! 1 );
			}, [] ),
			ae = ( 0, ReactHooks.useCallback )( function ( _eLocal16 ) {
				D( _eLocal16 );
				f( ! 0 );
			}, [] ),
			oe = ( function () {
				var _eLocal17 = Fre(
					Mre().m( function _eLocal18( _tLocal8 ) {
						var _nLocal3, _rLocal2, _aLocal;
						return Mre().w(
							function ( _eLocal19 ) {
								for (;;) {
									switch ( ( _eLocal19.p = _eLocal19.n ) ) {
										case 0:
											return (
												( _eLocal19.p = 0 ),
												( _eLocal19.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses?search='.concat(
														_tLocal8
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
												( _nLocal3 = _eLocal19.v ),
												( _rLocal2 = _nLocal3.map(
													function ( _eLocal20 ) {
														return {
															value:
																_eLocal20 ===
																	null ||
																_eLocal20 ===
																	void 0
																	? void 0
																	: _eLocal20.id,
															label:
																_eLocal20 ===
																	null ||
																_eLocal20 ===
																	void 0
																	? void 0
																	: _eLocal20.name,
														};
													}
												) ),
												_eLocal19.a( 2, _rLocal2 )
											 );
										case 2:
											_eLocal19.p = 2;
											_aLocal = _eLocal19.v;
											showRequestError( _aLocal );
										// Falls through to the shared async state cleanup.
										case 3:
											return _eLocal19.a( 2 );
									}
								}
							},
							_eLocal18,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal17.apply( this, _args2 );
				};
			} )(),
			ie = async function ( coupon ) {
				try {
					const saved = await l()( {
						path: coupon.id
							? '/ohmylms-commerce/v1/coupons/' + coupon.id
							: '/ohmylms-commerce/v1/coupons',
						method: coupon.id ? 'PUT' : 'POST',
						data: coupon,
					} );
					P( ( current ) =>
						current.map( ( item ) =>
							item.id === saved.id ? saved : item
						)
					);
					$(
						'success',
						coupon.id
							? I18n.__(
									'Coupon updated successfully.',
									'ohmylms'
								)
							: I18n.__(
									'Coupon created successfully.',
									'ohmylms'
								)
					);
					f( false );
					D( null );
					if ( B === 1 ) {
						await ce();
					} else {
						L( 1 );
					}
					return saved;
				} catch ( error ) {
					showRequestError( error );
					$(
						'error',
						error?.code ===
							'ohmylms_rest_coupon_code_already_exists'
							? error.message
							: I18n.__( 'Something went wrong!', 'ohmylms' )
					);
					return false;
				}
			},
			le = ( function () {
				var _eLocal21 = Fre(
					Mre().m( function _eLocal22( _tLocal0 ) {
						var _nLocal4, _rLocal3, _aLocal2, _oLocal;
						return Mre().w(
							function ( _eLocal23 ) {
								for (;;) {
									switch ( ( _eLocal23.p = _eLocal23.n ) ) {
										case 0:
											return (
												( _eLocal23.p = 0 ),
												( _nLocal4 =
													'/ohmylms-commerce/v1/coupons/'.concat(
														_tLocal0.id,
														'/status'
													) ),
												( _eLocal23.n = 1 ),
												l()( {
													path: ( 0,
													lN.addQueryArgs )(
														_nLocal4
													),
													method: 'PUT',
													data: {
														status:
															( _tLocal0 ===
																null ||
															_tLocal0 === void 0
																? void 0
																: _tLocal0.status ) ||
															'draft',
													},
													status:
														( _tLocal0 === null ||
														_tLocal0 === void 0
															? void 0
															: _tLocal0.status ) ||
														'draft',
												} )
											 );
										case 1:
											_rLocal3 = _eLocal23.v;
											_aLocal2 = C.map(
												function ( _eLocal24 ) {
													return ( _eLocal24 ===
														null ||
													_eLocal24 === void 0
														? void 0
														: _eLocal24.id ) ===
														( _rLocal3 === null ||
														_rLocal3 === void 0
															? void 0
															: _rLocal3.id )
														? _rLocal3
														: _eLocal24;
												}
											);
											P( _aLocal2 );
											$(
												'success',
												( 0, I18n.__ )(
													'Coupon '.concat(
														'publish' ===
															( _tLocal0 ===
																null ||
															_tLocal0 === void 0
																? void 0
																: _tLocal0.status )
															? 'enabled'
															: 'disabled',
														' successfully.'
													),
													'ohmylms'
												)
											);
											_eLocal23.n = 3;
											break;
										case 2:
											_eLocal23.p = 2;
											_oLocal = _eLocal23.v;
											showRequestError( _oLocal );
											$(
												'error',
												( 0, I18n.__ )(
													'Something went wrong!',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return _eLocal23.a( 2 );
									}
								}
							},
							_eLocal22,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return _eLocal21.apply( this, _args3 );
				};
			} )(),
			ce = async function () {
				M( true );
				try {
					const response = await l()( {
						path: lN.addQueryArgs( '/ohmylms-commerce/v1/coupons', {
							offset: 6 * ( B - 1 ),
							page: B,
							per_page: 6,
							search: S,
						} ),
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
						},
						parse: false,
					} );
					const items = await response.json();
					P( items );
					G( Number( response.headers.get( 'X-WP-Total' ) ) || 0 );
				} catch ( error ) {
					showRequestError( error );
					$(
						'error',
						I18n.__(
							'Could not load coupons. Please try again.',
							'ohmylms'
						)
					);
				} finally {
					M( false );
				}
			},
			ue = ( function () {
				var _eLocal25 = Fre(
					Mre().m( function _eLocal26() {
						var _tLocal10, _nLocal5, _rLocal4, _oLocal2;
						return Mre().w(
							function ( _eLocal27 ) {
								for (;;) {
									switch ( ( _eLocal27.p = _eLocal27.n ) ) {
										case 0:
											if ( ! Y ) {
												_eLocal27.n = 1;
												break;
											}
											return _eLocal27.a( 2 );
										case 1:
											return (
												( _eLocal27.p = 1 ),
												Q( ! 0 ),
												( _tLocal10 = [] ),
												( _tLocal10 = i
													? [ i ]
													: zre( s ) ),
												( _eLocal27.n = 2 ),
												l()( {
													path: 'ohmylms-commerce/v1/coupons/',
													method: 'DELETE',
													data: {
														ids: _tLocal10,
													},
												} )
											 );
										case 2:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _nLocal5 = _eLocal27.v )
												) &&
												_nLocal5.deleted
											) {
												{
													$(
														'success',
														( 0, I18n.__ )(
															'Deleted Successfully',
															'ohmylms'
														)
													);
													_rLocal4 = C.filter(
														function ( _eLocal28 ) {
															return ! _tLocal10.includes(
																_eLocal28 ===
																	null ||
																	_eLocal28 ===
																		void 0
																	? void 0
																	: _eLocal28.id
															);
														}
													);
													P( _rLocal4 );
												}
											}
											_eLocal27.n = 4;
											break;
										case 3:
											_eLocal27.p = 3;
											_oLocal2 = _eLocal27.v;
											showRequestError( _oLocal2 );
											$(
												'error',
												( 0, I18n.__ )(
													'Something went wrong!',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal27.p = 4 ),
												Q( ! 1 ),
												a( ! 1 ),
												c( null ),
												d( [] ),
												1 === B ? ce() : L( 1 ),
												_eLocal27.f( 4 )
											 );
										case 5:
											return _eLocal27.a( 2 );
									}
								}
							},
							_eLocal26,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal25.apply( this, _args );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var _eLocal29 = ! 0;
					return (
						_eLocal29 && ce( S ),
						function () {
							_eLocal29 = ! 1;
						}
					 );
				},
				[ S, B ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! A && e ) {
						$( t, e );
					}
				},
				[ e ]
			),
			(
				<React.Fragment>
					{ K }
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP
							padding={ 6 }
							margin={ 0 }
							marginBottom={ 0 }
						>
							<Controls.FlexWP
								justify={ 'space-between' }
								align={ 'center' }
								gap={ '4' }
							>
								<Controls.FlexItemWP>
									{ s.length > 0
										? React.createElement( hN, {
												items: s,
												setItems: d,
												bulksActions: te,
												spacerMarginBottom: 0,
											} )
										: React.createElement( aY, {
												handleSearch( _eLocal30 ) {
													R( _eLocal30 );
													L( 1 );
												},
												searchPlaceholder: ( 0,
												I18n.__ )(
													'Search coupon',
													'ohmylms'
												),
												showFilterByDays: ! 1,
												showFilterByPriceType: ! 1,
												showFilterByCategory: ! 1,
												showFilterByStatus: ! 1,
												spacerMarginBottom: 0,
											} ) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP>
									<Controls.ButtonWP
										variant={ 'primary' }
										icon={ React.createElement( nf, null ) }
										onClick={ function () {
											f( ! 0 );
										} }
									>
										{ ( 0, I18n.__ )(
											'Create Coupon',
											'ohmylms'
										) }
									</Controls.ButtonWP>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
							<Controls.SpacerWP marginBottom={ 4 } />
							{ A ? (
								<React.Fragment>
									<Controls.SkeletonWP
										active={ ! 0 }
										rows={ 5 }
									/>
								</React.Fragment>
							) : (
								<React.Fragment>
									<Controls.TableWP
										columns={ ee }
										dataSource={ C }
										rowKey={ 'id' }
										rowSelection={ {
											selectedRowKeys: s,
											onChange( _eLocal31 ) {
												return d( _eLocal31 );
											},
										} }
										loading={ A }
										locale={ {
											emptyText: (
												<EmptyState
													icon={ <EmptyIcon /> }
													title={ ( 0, I18n.__ )(
														'No Coupons Found!',
														'ohmylms'
													) }
													description={
														S
															? ( 0, I18n.__ )(
																	'Try a different search term.',
																	'ohmylms'
																)
															: ( 0, I18n.__ )(
																	'Create your first coupon to see it listed here.',
																	'ohmylms'
																)
													}
												/>
											),
										} }
									/>
									{ ! A &&
										Number( H ) > 6 &&
										React.createElement( fN, {
											total: Number( H ),
											currentPage: B,
											onPageChange: L,
											perPage: 6,
										} ) }
								</React.Fragment>
							) }
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ r && (
						<Ie
							title={
								s.length > 1
									? ( 0, I18n.__ )(
											'Delete Coupons',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Delete Coupon',
											'ohmylms'
										)
							}
							description={
								s.length > 1
									? ( 0, I18n.__ )(
											'After deleting these coupons, they will be moved to the trash.',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'After deleting this coupon, it will be moved to the trash.',
											'ohmylms'
										)
							}
							onClose={ re }
							onDelete={ ue }
							isOpen={ r }
							isDelete={ ! 0 }
							isLoading={ Y }
						/>
					) }
					{ p &&
						React.createElement( jre, {
							isOpen: p,
							setIsOpen: f,
							isFetch: _,
							setIsFetch: w,
							fetchCourses: oe,
							courses: k,
							setCoupons: P,
							createCoupon: ie,
							data: N,
							setSelectedData: D,
						} ) }
				</React.Fragment>
			 )
		 );
	};
}
