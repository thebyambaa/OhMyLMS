/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities, highlightAdminMenu } from '../../shared/utils';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createTagsPage( readRuntime ) {
	return function TagsPage() {
		const {
			BY,
			Ea,
			Ie,
			NY,
			Ne,
			React,
			We,
			aY,
			b: I18n,
			g: ReactHooks,
			hN,
			l,
			q,
			sN,
			xY: TaxonomyModal,
			zY,
		} = readRuntime();
		highlightAdminMenu( 'ohmylms', 'tags' );
		var e = useNotifications(),
			t = e.openNotificationWithIcon,
			n = e.contextHolder,
			r = BY( ( 0, ReactHooks.useState )( [] ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = BY( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = BY( ( 0, ReactHooks.useState )( '' ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = BY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = BY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			y = h[ 0 ],
			_ = h[ 1 ],
			w = BY( ( 0, ReactHooks.useState )( null ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = BY( ( 0, ReactHooks.useState )( null ), 2 ),
			x = R[ 0 ],
			C = R[ 1 ],
			P = BY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			O = P[ 0 ],
			k = P[ 1 ],
			j = BY( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			A = j[ 0 ],
			M = j[ 1 ],
			T = BY( ( 0, ReactHooks.useState )( [] ), 2 ),
			F = T[ 0 ],
			N = T[ 1 ],
			D = BY( ( 0, ReactHooks.useState )( null ), 2 ),
			W = ( D[ 0 ], D[ 1 ] ),
			B = ( 0, ReactHooks.useCallback )(
				zY(
					NY().m( function _eLocal() {
						var _nLocal;
						return NY().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											return (
												u( ! 0 ),
												( _eLocal2.p = 1 ),
												( _eLocal2.n = 2 ),
												l()( {
													path: '/ohmylms/v1/tags',
												} )
											 );
										case 2:
											_nLocal = _eLocal2.v;
											o( _nLocal || [] );
											_eLocal2.n = 4;
											break;
										case 3:
											_eLocal2.p = 3;
											t(
												'error',
												( 0, I18n.__ )(
													'Failed to load tags',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal2.p = 4 ),
												u( ! 1 ),
												_eLocal2.f( 4 )
											 );
										case 5:
											return _eLocal2.a( 2 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				),
				[]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				B();
			},
			[ B ]
		);
		var L = ( 0, ReactHooks.useCallback )( function ( _eLocal3 ) {
				m( _eLocal3 );
			}, [] ),
			V = ( 0, ReactHooks.useCallback )( function () {
				S( null );
				v( ! 0 );
			}, [] ),
			H = ( 0, ReactHooks.useCallback )( function ( _eLocal4 ) {
				S( _eLocal4 );
				v( ! 0 );
			}, [] ),
			G = ( 0, ReactHooks.useCallback )( function ( _eLocal5 ) {
				C( _eLocal5 );
				_( ! 0 );
			}, [] ),
			U = ( function () {
				var _eLocal6 = zY(
					NY().m( function _eLocal7( _nLocal2 ) {
						var _rLocal;
						return NY().w(
							function ( _eLocal8 ) {
								for (;;) {
									switch ( ( _eLocal8.p = _eLocal8.n ) ) {
										case 0:
											if (
												( k( ! 0 ),
												( _eLocal8.p = 1 ),
												! E )
											) {
												_eLocal8.n = 3;
												break;
											}
											return (
												( _eLocal8.n = 2 ),
												l()( {
													path: '/ohmylms/v1/tags/'.concat(
														E.term_id
													),
													method: 'PUT',
													data: {
														name: _nLocal2.name,
													},
												} )
											 );
										case 2:
											t(
												'success',
												( 0, I18n.__ )(
													'Tag updated successfully',
													'ohmylms'
												)
											);
											_eLocal8.n = 5;
											break;
										case 3:
											return (
												( _eLocal8.n = 4 ),
												l()( {
													path: '/ohmylms/v1/tags',
													method: 'POST',
													data: {
														name: _nLocal2.name,
													},
												} )
											 );
										case 4:
											t(
												'success',
												( 0, I18n.__ )(
													'Tag created successfully',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 5:
											v( ! 1 );
											S( null );
											B();
											_eLocal8.n = 7;
											break;
										case 6:
											_eLocal8.p = 6;
											_rLocal = _eLocal8.v;
											t(
												'error',
												_rLocal.message ||
													( 0, I18n.__ )(
														'Failed to save tag',
														'ohmylms'
													)
											);
										// Falls through to the shared async state cleanup.
										case 7:
											return (
												( _eLocal8.p = 7 ),
												k( ! 1 ),
												_eLocal8.f( 7 )
											 );
										case 8:
											return _eLocal8.a( 2 );
									}
								}
							},
							_eLocal7,
							null,
							[ [ 1, 6, 7, 8 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal6.apply( this, _args2 );
				};
			} )(),
			Y = ( function () {
				var _eLocal9 = zY(
					NY().m( function _eLocal0() {
						var _nLocal3;
						return NY().w(
							function ( _eLocal1 ) {
								for (;;) {
									switch ( ( _eLocal1.p = _eLocal1.n ) ) {
										case 0:
											if ( x || 0 !== F.length ) {
												_eLocal1.n = 1;
												break;
											}
											return _eLocal1.a( 2 );
										case 1:
											if ( ! A ) {
												_eLocal1.n = 2;
												break;
											}
											return _eLocal1.a( 2 );
										case 2:
											if (
												( M( ! 0 ),
												( _eLocal1.p = 3 ),
												! x )
											) {
												_eLocal1.n = 5;
												break;
											}
											return (
												( _eLocal1.n = 4 ),
												l()( {
													path: '/ohmylms/v1/tags/'.concat(
														x.term_id
													),
													method: 'DELETE',
												} )
											 );
										case 4:
											t(
												'success',
												( 0, I18n.__ )(
													'Tag deleted successfully',
													'ohmylms'
												)
											);
											_eLocal1.n = 7;
											break;
										case 5:
											return (
												( _eLocal1.n = 6 ),
												l()( {
													path: '/ohmylms/v1/tags/bulk',
													method: 'DELETE',
													data: {
														ids: F,
													},
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 6:
											t(
												'success',
												( 0, I18n.__ )(
													'Tags deleted successfully',
													'ohmylms'
												)
											);
											N( [] );
										// Falls through to the shared async state cleanup.
										case 7:
											_( ! 1 );
											C( null );
											B();
											_eLocal1.n = 9;
											break;
										case 8:
											_eLocal1.p = 8;
											_nLocal3 = _eLocal1.v;
											t(
												'error',
												_nLocal3.message ||
													( 0, I18n.__ )(
														'Failed to delete tag',
														'ohmylms'
													)
											);
										// Falls through to the shared async state cleanup.
										case 9:
											return (
												( _eLocal1.p = 9 ),
												M( ! 1 ),
												_eLocal1.f( 9 )
											 );
										case 10:
											return _eLocal1.a( 2 );
									}
								}
							},
							_eLocal0,
							null,
							[ [ 3, 8, 9, 10 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal9.apply( this, _args );
				};
			} )(),
			Q = ( 0, ReactHooks.useMemo )(
				function () {
					return a.filter( function ( _eLocal10 ) {
						var _tLocal2;
						return _eLocal10 === null ||
							_eLocal10 === void 0 ||
							null === ( _tLocal2 = _eLocal10.name ) ||
							void 0 === _tLocal2
							? void 0
							: _tLocal2
									.toLowerCase()
									.includes( d.toLowerCase() );
					} );
				},
				[ a, d ]
			),
			Z = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: F,
						onChange: N,
					};
				},
				[ F ]
			),
			$ = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						label: ( 0, I18n.__ )( 'Add Tag', 'ohmylms' ),
						onClick: V,
					};
				},
				[ V ]
			),
			K = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
						value: 'delete',
						action() {
							_( ! 0 );
						},
					},
				];
			}, [] ),
			J = [
				{
					title: ( 0, I18n.__ )( 'ID', 'ohmylms' ),
					dataIndex: 'term_id',
					key: 'term_id',
					width: '80px',
					render( _eLocal11 ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ _eLocal11 }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					render( _eLocal12 ) {
						return (
							<span
								style={ {
									fontWeight: 500,
								} }
							>
								{ _eLocal12 }
							</span>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'No. of Courses', 'ohmylms' ),
					dataIndex: 'count',
					key: 'count',
					width: '150px',
					render( _eLocal13, _tLocal3 ) {
						var _nLocal4;
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
											( _nLocal4 = _tLocal3.courses ) ||
										void 0 === _nLocal4
											? void 0
											: _nLocal4.map(
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
																{ decodeHtmlEntities(
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
					render( _eLocal15, _tLocal4 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										onClick() {
											return H( _tLocal4 );
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
											return G( _tLocal4 );
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
				{ n }
				<Controls.ContainerWP>
					<PageHeader
						title={ ( 0, I18n.__ )( 'Tags', 'ohmylms' ) }
						showAddButton={ ! 0 }
						addButtonConfig={ $ }
					/>
					<Ea
						isBorderless={ ! 0 }
						minHeight={ 'calc(100vh - 200px)' }
					>
						<Controls.SpacerWP padding={ 5 }>
							{ F.length > 0
								? React.createElement( hN, {
										items: F,
										setItems: N,
										bulksActions: K,
									} )
								: React.createElement( aY, {
										handleSearch: L,
										showFilterByDays: ! 1,
										showFilterByStatus: ! 1,
										showFilterByCategory: ! 1,
										showTotalItemsCount: ! 1,
										searchPlaceholder: ( 0, I18n.__ )(
											'Search tags...',
											'ohmylms'
										),
									} ) }
							<sN.A
								rowKey={ 'term_id' }
								columns={ J }
								dataSource={ Q || [] }
								rowSelection={ Z }
								pagination={ ! 1 }
								loading={ c }
								scroll={ {
									x: 'max-content',
								} }
								onRowMouseEnter={ function ( _eLocal16 ) {
									return W(
										_eLocal16 === null ||
											_eLocal16 === void 0
											? void 0
											: _eLocal16.term_id
									);
								} }
								onRowMouseLeave={ function () {
									return W( null );
								} }
								locale={ {
									emptyText: React.createElement(
										EmptyState,
										{
											icon: React.createElement(
												NoDataIllustration,
												null
											),
											title: ( 0, I18n.__ )(
												'No tags yet!',
												'ohmylms'
											),
											description: ( 0, I18n.__ )(
												'Create your first tag and it will show up here.',
												'ohmylms'
											),
											ctaText: ( 0, I18n.__ )(
												'Add Tag',
												'ohmylms'
											),
											ctaHandler: V,
										}
									),
								} }
							/>
						</Controls.SpacerWP>
					</Ea>
				</Controls.ContainerWP>
				{ f && (
					<TaxonomyModal
						isOpen={ f }
						onClose={ function () {
							v( ! 1 );
							S( null );
						} }
						onSubmit={ U }
						title={
							E
								? ( 0, I18n.__ )( 'Edit Tag', 'ohmylms' )
								: ( 0, I18n.__ )( 'Add Tag', 'ohmylms' )
						}
						type={ 'tag' }
						initialData={ E }
						isSubmitting={ O }
					/>
				) }
				{ y && (
					<Ie
						isOpen={ y }
						onClose={ function () {
							_( ! 1 );
							C( null );
						} }
						onDelete={ Y }
						title={ ( 0, I18n.__ )( 'Delete Tag', 'ohmylms' ) }
						description={ ( () => {
							if (
								( x === null || x === void 0
									? void 0
									: x.count ) > 0
							) {
								return ( 0, I18n.__ )(
									'This tag is assigned to courses. Deleting it will remove the tag from those courses. Are you sure you want to continue?',
									'ohmylms'
								);
							}
							if ( F.length > 0 ) {
								return ( 0, I18n.__ )(
									'Are you sure you want to delete the selected tags?',
									'ohmylms'
								);
							}
							return ( 0, I18n.__ )(
								'Are you sure you want to delete this tag?',
								'ohmylms'
							);
						} )() }
						actionBtnText={ ( 0, I18n.__ )( 'Delete', 'ohmylms' ) }
						loading={ A }
						isDelete={ ! 0 }
					/>
				) }
			</React.Fragment>
		);
	};
}
