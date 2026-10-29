import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
export function createBadgeList( readRuntime ) {
	return function BadgeList() {
		const {
			Ge,
			I: Controls,
			Ie,
			Ne,
			React,
			T: StoreModule,
			We,
			b: I18n,
			d3,
			df,
			g: ReactHooks,
			i3: BadgeEditor,
			l,
			l3,
			lf,
			q,
			s3,
			uf,
			y: WordPressData,
		} = readRuntime();
		var e = d3( ( 0, ReactHooks.useState )( [] ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = d3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = d3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = d3( ( 0, ReactHooks.useState )( null ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = d3( ( 0, ReactHooks.useState )( null ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = d3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			_ = h[ 0 ],
			w = h[ 1 ],
			E = useNotifications(),
			openNotificationWithIcon = E.openNotificationWithIcon,
			contextHolder = E.contextHolder,
			x = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			C = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] ),
			P = d3( ( 0, ReactHooks.useState )( null ), 2 ),
			O = P[ 0 ],
			k = P[ 1 ],
			j = [
				{
					title: ( 0, I18n.__ )( 'Badge Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					sorter: ! 0,
					width: '90%',
					render( _eLocal3, _tLocal ) {
						return (
							<Controls.FlexWP
								gap={ 4 }
								align={ 'start' }
								justify={ 'start' }
							>
								<Controls.AvatarWP
									shape={ 'square' }
									src={ _tLocal.image }
									size={ 80 }
								/>
								<Controls.FlexItemWP>
									<Controls.TextWP
										as={ 'span' }
										color={ '#000d25' }
										size={ 16 }
										numberOfLines={ 2 }
										truncate={ ! 0 }
										onClick={ function () {
											return A( _tLocal );
										} }
										style={ {
											cursor: 'pointer',
										} }
									>
										{ Ge( _tLocal.name ) }
									</Controls.TextWP>
									{ O ===
										( _tLocal === null || _tLocal === void 0
											? void 0
											: _tLocal.slug ) && (
										<Controls.ButtonWP
											onClick={ function () {
												return A( _tLocal );
											} }
											label={ ( 0, I18n.__ )(
												'Edit',
												'ohmylms'
											) }
											variant={ 'text' }
											style={ {
												height: '26px',
											} }
										>
											<EditIcon />
										</Controls.ButtonWP>
									) }
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						);
					},
				},
				{
					title: 'Action',
					dataIndex: 'action',
					key: 'action',
					width: null,
					render( _eLocal4, _tLocal2 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										onClick() {
											return A( _tLocal2 );
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
											return M(
												_tLocal2 === null ||
													_tLocal2 === void 0
													? void 0
													: _tLocal2.slug
											);
										},
										icon: <We />,
									},
								] }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			],
			A = function ( _eLocal5 ) {
				m( _eLocal5 );
				u( ! 0 );
			},
			M = function ( _eLocal6 ) {
				w( ! 0 );
				v( _eLocal6 );
			},
			F = ( function () {
				var _eLocal7 = s3(
					l3().m( function _eLocal8() {
						var _nLocal, _rLocal, _aLocal;
						return l3().w(
							function ( _eLocal9 ) {
								for (;;) {
									switch ( ( _eLocal9.p = _eLocal9.n ) ) {
										case 0:
											return (
												( _eLocal9.p = 0 ),
												o( ! 0 ),
												( _nLocal = t.filter(
													function ( _eLocal0 ) {
														return (
															_eLocal0.slug !== f
														);
													}
												) ),
												( _eLocal9.n = 1 ),
												l()( {
													path: '/ohmylms/v1/engagement/badges',
													method: 'DELETE',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_nLocal
													),
												} )
											 );
										case 1:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _rLocal = _eLocal9.v )
												) &&
												_rLocal.success
											) {
												{
													N();
													w( ! 1 );
													openNotificationWithIcon(
														'success',
														( 0, I18n.__ )(
															'Badge deleted successfully!',
															'ohmylms'
														)
													);
												}
											}
											_eLocal9.n = 3;
											break;
										case 2:
											_eLocal9.p = 2;
											_aLocal = _eLocal9.v;
											showRequestError(
												'Error deleting badge:',
												_aLocal
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal9.p = 3 ),
												o( ! 1 ),
												_eLocal9.f( 3 )
											 );
										case 4:
											return _eLocal9.a( 2 );
									}
								}
							},
							_eLocal8,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal7.apply( this, _args );
				};
			} )(),
			N = ( function () {
				var _eLocal1 = s3(
					l3().m( function _eLocal10() {
						var _tLocal3, _rLocal2;
						return l3().w(
							function ( _eLocal11 ) {
								for (;;) {
									switch ( ( _eLocal11.p = _eLocal11.n ) ) {
										case 0:
											return (
												( _eLocal11.p = 0 ),
												o( ! 0 ),
												( _eLocal11.n = 1 ),
												l()( {
													path: 'ohmylms/v1/engagement/badges',
												} )
											 );
										case 1:
											_tLocal3 = _eLocal11.v;
											n( _tLocal3 );
											_eLocal11.n = 3;
											break;
										case 2:
											_eLocal11.p = 2;
											_rLocal2 = _eLocal11.v;
											showRequestError( _rLocal2 );
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal11.p = 3 ),
												o( ! 1 ),
												_eLocal11.f( 3 )
											 );
										case 4:
											return _eLocal11.a( 2 );
									}
								}
							},
							_eLocal10,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal1.apply( this, _args2 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )( function () {
				var _eLocal12 = ! 0;
				return (
					_eLocal12 && N(),
					function () {
						_eLocal12 = ! 1;
					}
				 );
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! a && x ) {
						openNotificationWithIcon( C, x );
					}
				},
				[ x ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! c ) {
						{
							m( null );
							v( null );
						}
					}
				},
				[ c ]
			),
			(
				<React.Fragment>
					{ contextHolder }
					<Controls.CardWP isBorderless={ ! 0 } fullWidth={ ! 0 }>
						<Controls.SpacerWP padding={ 5 }>
							<Controls.SpacerWP marginBottom={ 4 }>
								<Controls.FlexWP
									gap={ 3 }
									justify={ 'flex-end' }
								>
									{ React.createElement( lf, {
										label: ( 0, I18n.__ )(
											'Add Badge',
											'ohmylms'
										),
										onClick() {
											return u( ! 0 );
										},
									} ) }
								</Controls.FlexWP>
							</Controls.SpacerWP>
							<Controls.TableWP
								rowKey={ 'id' }
								columns={ j }
								dataSource={ t || [] }
								loading={ a }
								scroll={ {
									x: 'max-content',
								} }
								onMouseEnterOnRow={ function ( _eLocal13 ) {
									return k(
										_eLocal13 === null ||
											_eLocal13 === void 0
											? void 0
											: _eLocal13.slug
									);
								} }
								onMouseLeaveOnRow={ function () {
									return k( null );
								} }
								locale={ {
									emptyText: React.createElement( uf, {
										icon: React.createElement( df, null ),
										title: ( 0, I18n.__ )(
											'No badge yet!',
											'ohmylms'
										),
										description: ( 0, I18n.__ )(
											"Start building your first badge and it'll show up here as soon as you hit publish.",
											'ohmylms'
										),
									} ),
								} }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ c && (
						<BadgeEditor
							data={ d }
							isOpen={ c }
							onClose={ u }
							badgeList={ t }
							setItems={ n }
							fetchData={ N }
						/>
					) }
					{ _ && (
						<Ie
							title={ ( 0, I18n.__ )(
								'Delete Badge',
								'ohmylms'
							) }
							description={ ( 0, I18n.__ )(
								'Are you sure you want to delete this badge?',
								'ohmylms'
							) }
							onClose={ function () {
								w( ! 1 );
							} }
							onDelete={ F }
							isOpen={ _ }
							isDelete={ ! 0 }
						/>
					) }
				</React.Fragment>
			 )
		 );
	};
}
