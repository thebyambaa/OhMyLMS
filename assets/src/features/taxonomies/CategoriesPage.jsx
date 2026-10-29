/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
export function createCategoriesPage( readRuntime ) {
	return function CategoriesPage() {
		const {
			Ea,
			Ge,
			HG,
			I: Controls,
			IY,
			Ie,
			Ne,
			PY,
			React,
			T: StoreModule,
			TY,
			We,
			YG,
			aY,
			b: I18n,
			df,
			g: ReactHooks,
			hN,
			jY,
			l,
			q,
			sN,
			uf,
			xY: TaxonomyModal,
			y: WordPressData,
		} = readRuntime();
		HG( 'ohmylms', 'categories' );
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = useNotifications(),
			n = t.openNotificationWithIcon,
			r = t.contextHolder,
			a = IY( ( 0, ReactHooks.useState )( [] ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			c = IY( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = IY( ( 0, ReactHooks.useState )( '' ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			f = IY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			v = f[ 0 ],
			h = f[ 1 ],
			_ = IY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = IY( ( 0, ReactHooks.useState )( null ), 2 ),
			R = S[ 0 ],
			x = S[ 1 ],
			C = IY( ( 0, ReactHooks.useState )( null ), 2 ),
			P = C[ 0 ],
			O = C[ 1 ],
			k = IY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = IY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			F = M[ 0 ],
			N = M[ 1 ],
			D = IY( ( 0, ReactHooks.useState )( [] ), 2 ),
			W = D[ 0 ],
			B = D[ 1 ],
			L = IY( ( 0, ReactHooks.useState )( null ), 2 ),
			V = ( L[ 0 ], L[ 1 ] ),
			H = ( 0, ReactHooks.useCallback )(
				TY(
					PY().m( function _tLocal() {
						var _rLocal, _aLocal;
						return PY().w(
							function ( _tLocal2 ) {
								for (;;) {
									switch ( ( _tLocal2.p = _tLocal2.n ) ) {
										case 0:
											return (
												s( ! 0 ),
												( _tLocal2.p = 1 ),
												( _tLocal2.n = 2 ),
												l()( {
													path: '/ohmylms/v1/categories',
												} )
											 );
										case 2:
											_rLocal = _tLocal2.v;
											i( _rLocal || [] );
											_aLocal = ( _rLocal || [] ).map(
												function ( _eLocal ) {
													return jY(
														jY( {}, _eLocal ),
														{},
														{
															id: _eLocal.term_id,
														}
													);
												}
											);
											e.setGlobalDataViaKey(
												'categories',
												_aLocal
											);
											_tLocal2.n = 4;
											break;
										case 3:
											_tLocal2.p = 3;
											n(
												'error',
												( 0, I18n.__ )(
													'Failed to load categories',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _tLocal2.p = 4 ),
												s( ! 1 ),
												_tLocal2.f( 4 )
											 );
										case 5:
											return _tLocal2.a( 2 );
									}
								}
							},
							_tLocal,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				),
				[]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				H();
			},
			[ H ]
		);
		var G = ( 0, ReactHooks.useCallback )( function ( _eLocal2 ) {
				p( _eLocal2 );
			}, [] ),
			U = ( 0, ReactHooks.useCallback )( function () {
				x( null );
				h( ! 0 );
			}, [] ),
			Y = ( 0, ReactHooks.useCallback )( function ( _eLocal3 ) {
				x( _eLocal3 );
				h( ! 0 );
			}, [] ),
			Q = ( 0, ReactHooks.useCallback )( function ( _eLocal4 ) {
				O( _eLocal4 );
				E( ! 0 );
			}, [] ),
			Z = ( function () {
				var _eLocal5 = TY(
					PY().m( function _eLocal6( _tLocal3 ) {
						var _rLocal2;
						return PY().w(
							function ( _eLocal7 ) {
								for (;;) {
									switch ( ( _eLocal7.p = _eLocal7.n ) ) {
										case 0:
											if (
												( A( ! 0 ),
												( _eLocal7.p = 1 ),
												! R )
											) {
												_eLocal7.n = 3;
												break;
											}
											return (
												( _eLocal7.n = 2 ),
												l()( {
													path: '/ohmylms/v1/categories/'.concat(
														R.term_id
													),
													method: 'PUT',
													data: {
														name: _tLocal3.name,
														parent:
															_tLocal3.parent ||
															0,
													},
												} )
											 );
										case 2:
											n(
												'success',
												( 0, I18n.__ )(
													'Category updated successfully',
													'ohmylms'
												)
											);
											_eLocal7.n = 5;
											break;
										case 3:
											return (
												( _eLocal7.n = 4 ),
												l()( {
													path: '/ohmylms/v1/categories',
													method: 'POST',
													data: {
														name: _tLocal3.name,
														parent:
															_tLocal3.parent ||
															0,
													},
												} )
											 );
										case 4:
											n(
												'success',
												( 0, I18n.__ )(
													'Category created successfully',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 5:
											h( ! 1 );
											x( null );
											H();
											_eLocal7.n = 7;
											break;
										case 6:
											_eLocal7.p = 6;
											_rLocal2 = _eLocal7.v;
											n(
												'error',
												_rLocal2.message ||
													( 0, I18n.__ )(
														'Failed to save category',
														'ohmylms'
													)
											);
										// Falls through to the shared async state cleanup.
										case 7:
											return (
												( _eLocal7.p = 7 ),
												A( ! 1 ),
												_eLocal7.f( 7 )
											 );
										case 8:
											return _eLocal7.a( 2 );
									}
								}
							},
							_eLocal6,
							null,
							[ [ 1, 6, 7, 8 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal5.apply( this, _args2 );
				};
			} )(),
			$ = ( function () {
				var _eLocal8 = TY(
					PY().m( function _eLocal9() {
						var _tLocal5;
						return PY().w(
							function ( _eLocal0 ) {
								for (;;) {
									switch ( ( _eLocal0.p = _eLocal0.n ) ) {
										case 0:
											if ( P || 0 !== W.length ) {
												_eLocal0.n = 1;
												break;
											}
											return _eLocal0.a( 2 );
										case 1:
											if ( ! F ) {
												_eLocal0.n = 2;
												break;
											}
											return _eLocal0.a( 2 );
										case 2:
											if (
												( N( ! 0 ),
												( _eLocal0.p = 3 ),
												! P )
											) {
												_eLocal0.n = 5;
												break;
											}
											return (
												( _eLocal0.n = 4 ),
												l()( {
													path: '/ohmylms/v1/categories/'.concat(
														P.term_id
													),
													method: 'DELETE',
												} )
											 );
										case 4:
											n(
												'success',
												( 0, I18n.__ )(
													'Category deleted successfully',
													'ohmylms'
												)
											);
											_eLocal0.n = 7;
											break;
										case 5:
											return (
												( _eLocal0.n = 6 ),
												l()( {
													path: '/ohmylms/v1/categories/bulk',
													method: 'DELETE',
													data: {
														ids: W,
													},
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 6:
											n(
												'success',
												( 0, I18n.__ )(
													'Categories deleted successfully',
													'ohmylms'
												)
											);
											B( [] );
										// Falls through to the shared async state cleanup.
										case 7:
											E( ! 1 );
											O( null );
											H();
											_eLocal0.n = 9;
											break;
										case 8:
											_eLocal0.p = 8;
											_tLocal5 = _eLocal0.v;
											n(
												'error',
												_tLocal5.message ||
													( 0, I18n.__ )(
														'Failed to delete category',
														'ohmylms'
													)
											);
										// Falls through to the shared async state cleanup.
										case 9:
											return (
												( _eLocal0.p = 9 ),
												N( ! 1 ),
												_eLocal0.f( 9 )
											 );
										case 10:
											return _eLocal0.a( 2 );
									}
								}
							},
							_eLocal9,
							null,
							[ [ 3, 8, 9, 10 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal8.apply( this, _args );
				};
			} )(),
			K = ( 0, ReactHooks.useMemo )(
				function () {
					return o.filter( function ( _eLocal1 ) {
						var _tLocal6;
						return _eLocal1 === null ||
							_eLocal1 === void 0 ||
							null === ( _tLocal6 = _eLocal1.name ) ||
							void 0 === _tLocal6
							? void 0
							: _tLocal6
									.toLowerCase()
									.includes( m.toLowerCase() );
					} );
				},
				[ o, m ]
			),
			J = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: W,
						onChange: B,
					};
				},
				[ W ]
			),
			X = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						label: ( 0, I18n.__ )( 'Add Category', 'ohmylms' ),
						onClick: U,
					};
				},
				[ U ]
			),
			ee = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
						value: 'delete',
						action() {
							E( ! 0 );
						},
					},
				];
			}, [] ),
			te = [
				{
					title: ( 0, I18n.__ )( 'ID', 'ohmylms' ),
					dataIndex: 'term_id',
					key: 'term_id',
					width: '80px',
					render( _eLocal10 ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ _eLocal10 }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					render( _eLocal11, _tLocal7 ) {
						var _nLocal;
						return (
							<div>
								<span
									style={ {
										fontWeight: 500,
									} }
								>
									{ _eLocal11 }
								</span>
								{ _tLocal7.parent > 0 && (
									<div
										style={ {
											fontSize: '12px',
											color: '#666',
											marginTop: '4px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Parent: ',
											'ohmylms'
										) }
										{ ( null ===
											( _nLocal = o.find(
												function ( _eLocal12 ) {
													return (
														_eLocal12.term_id ===
														_tLocal7.parent
													);
												}
											) ) || void 0 === _nLocal
											? void 0
											: _nLocal.name ) || '—' }
									</div>
								) }
							</div>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'No. of Courses', 'ohmylms' ),
					dataIndex: 'count',
					key: 'count',
					width: '150px',
					render( _eLocal13, _tLocal8 ) {
						var _nLocal2;
						return _eLocal13 && 0 !== _eLocal13 ? (
							<Controls.TooltipWP
								text={
									<div
										style={ {
											maxWidth: '300px',
										} }
									>
										<div
											style={ {
												fontWeight: 'bold',
												marginBottom: '8px',
											} }
										>
											{ ( 0, I18n.__ )(
												'Courses:',
												'ohmylms'
											) }
										</div>
										{ null ===
											( _nLocal2 = _tLocal8.courses ) ||
										void 0 === _nLocal2
											? void 0
											: _nLocal2.map(
													function ( _eLocal14 ) {
														return (
															<div
																key={
																	_eLocal14.id
																}
																style={ {
																	padding:
																		'4px 0',
																} }
															>
																{ '• ' }
																{ Ge(
																	_eLocal14.title
																) }
															</div>
														);
													}
												) }
									</div>
								}
								position={ 'top' }
							>
								<Controls.BadgeWP
									variant={ 'secondary' }
									isBorderLess={ ! 0 }
								>
									{ _eLocal13 }
								</Controls.BadgeWP>
							</Controls.TooltipWP>
						) : (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ '0' }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Actions', 'ohmylms' ),
					dataIndex: 'action',
					key: 'action',
					width: '100px',
					render( _eLocal15, _tLocal9 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										onClick() {
											return Y( _tLocal9 );
										},
										icon: (
											<span>
												<EditIcon />
											</span>
										),
									},
									{
										title: ( 0, I18n.__ )(
											'Delete',
											'ohmylms'
										),
										onClick() {
											return Q( _tLocal9 );
										},
										icon: <We />,
									},
								] }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			];
		return (
			<React.Fragment>
				{ r }
				<Controls.ContainerWP>
					<YG
						title={ ( 0, I18n.__ )( 'Categories', 'ohmylms' ) }
						showAddButton={ ! 0 }
						addButtonConfig={ X }
					/>
					<Ea
						isBorderless={ ! 0 }
						minHeight={ 'calc(100vh - 200px)' }
					>
						<Controls.SpacerWP padding={ 5 }>
							{ W.length > 0
								? React.createElement( hN, {
										items: W,
										setItems: B,
										bulksActions: ee,
									} )
								: React.createElement( aY, {
										handleSearch: G,
										showFilterByDays: ! 1,
										showFilterByStatus: ! 1,
										showFilterByCategory: ! 1,
										showTotalItemsCount: ! 1,
										searchPlaceholder: ( 0, I18n.__ )(
											'Search categories...',
											'ohmylms'
										),
									} ) }
							<sN.A
								rowKey={ 'term_id' }
								columns={ te }
								dataSource={ K || [] }
								rowSelection={ J }
								pagination={ ! 1 }
								loading={ u }
								scroll={ {
									x: 'max-content',
								} }
								onRowMouseEnter={ function ( _eLocal16 ) {
									return V(
										_eLocal16 === null ||
											_eLocal16 === void 0
											? void 0
											: _eLocal16.term_id
									);
								} }
								onRowMouseLeave={ function () {
									return V( null );
								} }
								locale={ {
									emptyText: React.createElement( uf, {
										icon: React.createElement( df, null ),
										title: ( 0, I18n.__ )(
											'No categories yet!',
											'ohmylms'
										),
										description: ( 0, I18n.__ )(
											'Create your first category and it will show up here.',
											'ohmylms'
										),
										ctaText: ( 0, I18n.__ )(
											'Add Category',
											'ohmylms'
										),
										ctaHandler: U,
									} ),
								} }
							/>
						</Controls.SpacerWP>
					</Ea>
				</Controls.ContainerWP>
				{ v && (
					<TaxonomyModal
						isOpen={ v }
						onClose={ function () {
							h( ! 1 );
							x( null );
						} }
						onSubmit={ Z }
						title={
							R
								? ( 0, I18n.__ )( 'Edit Category', 'ohmylms' )
								: ( 0, I18n.__ )( 'Add Category', 'ohmylms' )
						}
						type={ 'category' }
						initialData={ R }
						categories={ o }
						isSubmitting={ j }
					/>
				) }
				{ w && (
					<Ie
						isOpen={ w }
						onClose={ function () {
							E( ! 1 );
							O( null );
						} }
						onDelete={ $ }
						title={ ( 0, I18n.__ )( 'Delete Category', 'ohmylms' ) }
						description={ ( () => {
							if (
								( P === null || P === void 0
									? void 0
									: P.count ) > 0
							) {
								return ( 0, I18n.__ )(
									'This category is assigned to courses. Deleting it will remove the category from those courses. Are you sure you want to continue?',
									'ohmylms'
								);
							}
							if ( W.length > 0 ) {
								return ( 0, I18n.__ )(
									'Are you sure you want to delete the selected categories?',
									'ohmylms'
								);
							}
							return ( 0, I18n.__ )(
								'Are you sure you want to delete this category?',
								'ohmylms'
							);
						} )() }
						actionBtnText={ ( 0, I18n.__ )( 'Delete', 'ohmylms' ) }
						loading={ F }
						isDelete={ ! 0 }
					/>
				) }
			</React.Fragment>
		);
	};
}
