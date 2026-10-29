import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
import { EmptyState, NoDataIllustration } from '../../shared/media';
export function createLevelList( readRuntime ) {
	return function LevelList( props ) {
		const {
			Ie,
			L3: LevelEditor,
			Ne,
			Q3,
			React,
			T: StoreModule,
			U3,
			V3,
			We,
			Y3,
			b: I18n,
			g: ReactHooks,
			l,
			lf,
			q,
			q3,
			y: WordPressData,
		} = readRuntime();
		var setLevelList = props.setLevelList,
			n = q3( ( 0, ReactHooks.useState )( [] ), 2 ),
			r = n[ 0 ],
			a = n[ 1 ],
			o = q3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			i = o[ 0 ],
			c = o[ 1 ],
			u = q3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = q3( ( 0, ReactHooks.useState )( null ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = q3( ( 0, ReactHooks.useState )( null ), 2 ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = q3( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = useNotifications(),
			openNotificationWithIcon = R.openNotificationWithIcon,
			contextHolder = R.contextHolder,
			P = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			}, [] ),
			O = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			}, [] ),
			k = ( 0, ReactHooks.useRef )( null ),
			j = ( 0, ReactHooks.useRef )( null ),
			A = function ( e ) {
				e.target.classList.remove( 'grabbing' );
			},
			M = function ( e ) {
				e.preventDefault();
				k.current = null;
				j.current = null;
			},
			F = function ( e ) {
				e.preventDefault();
			},
			N = [
				{
					title: ( 0, I18n.__ )( 'Level Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					sorter: ! 0,
					width: '90%',
					render( e, t ) {
						return (
							<Controls.FlexWP
								gap={ 4 }
								align={ 'start' }
								justify={ 'start' }
								style={ {
									cursor: 'move',
								} }
							>
								<q.ColorIndicator colorValue={ t.color } />
								<Controls.TextWP
									as={ 'span' }
									color={ '#000d25' }
									size={ 16 }
									numberOfLines={ 2 }
									truncate={ ! 0 }
								>
									{ decodeHtmlEntities( t.name ) }
								</Controls.TextWP>
							</Controls.FlexWP>
						);
					},
				},
				{
					title: 'Action',
					dataIndex: 'action',
					key: 'action',
					width: null,
					render( e, t ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										onClick() {
											return D( t );
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
											return W(
												t === null || t === void 0
													? void 0
													: t.slug
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
			D = function ( e ) {
				f( e );
				d( ! 0 );
			},
			W = function ( e ) {
				S( ! 0 );
				_( e );
			},
			B = ( function () {
				var e = U3(
					V3().m( function _eLocal() {
						var t, _nLocal, _aLocal;
						return V3().w(
							function ( _eLocal2 ) {
								for (;;) {
									switch ( ( _eLocal2.p = _eLocal2.n ) ) {
										case 0:
											return (
												( _eLocal2.p = 0 ),
												c( ! 0 ),
												( t = r.filter(
													function ( _eLocal3 ) {
														return (
															_eLocal3.slug !== h
														);
													}
												) ),
												( _eLocal2.n = 1 ),
												l()( {
													path: '/ohmylms/v1/engagement/levels',
													method: 'DELETE',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify( t ),
												} )
											 );
										case 1:
											if (
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													( _nLocal = _eLocal2.v )
												) &&
												_nLocal.success
											) {
												{
													L();
													S( ! 1 );
													openNotificationWithIcon(
														'success',
														( 0, I18n.__ )(
															'Level deleted successfully!',
															'ohmylms'
														)
													);
												}
											}
											_eLocal2.n = 3;
											break;
										case 2:
											_eLocal2.p = 2;
											_aLocal = _eLocal2.v;
											showRequestError(
												'Error deleting level:',
												_aLocal
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal2.p = 3 ),
												c( ! 1 ),
												_eLocal2.f( 3 )
											 );
										case 4:
											return _eLocal2.a( 2 );
									}
								}
							},
							_eLocal,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			L = ( function () {
				var e = U3(
					V3().m( function _eLocal4() {
						var _nLocal2, _rLocal;
						return V3().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											return (
												( _eLocal5.p = 0 ),
												c( ! 0 ),
												( _eLocal5.n = 1 ),
												l()( {
													path: 'ohmylms/v1/engagement/levels',
												} )
											 );
										case 1:
											_nLocal2 = _eLocal5.v;
											a( _nLocal2 );
											setLevelList( _nLocal2 );
											_eLocal5.n = 3;
											break;
										case 2:
											_eLocal5.p = 2;
											_rLocal = _eLocal5.v;
											showRequestError( _rLocal );
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _eLocal5.p = 3 ),
												c( ! 1 ),
												_eLocal5.f( 3 )
											 );
										case 4:
											return _eLocal5.a( 2 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ! 0;
				return (
					e && L(),
					function () {
						e = ! 1;
					}
				 );
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( P ) {
						openNotificationWithIcon( O, P );
					}
				},
				[ P ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! s ) {
						{
							f( null );
							_( null );
						}
					}
				},
				[ s ]
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
											'Add Level',
											'ohmylms'
										),
										onClick() {
											return d( ! 0 );
										},
									} ) }
								</Controls.FlexWP>
							</Controls.SpacerWP>
							<Controls.TableWP
								rowKey={ 'id' }
								columns={ N }
								dataSource={ r || [] }
								loading={ i }
								scroll={ {
									x: 'max-content',
								} }
								onRow={ function ( e, _nLocal3 ) {
									return {
										draggable: ! 0,
										onDragStart( _eLocal6 ) {
											return ( function ( _eLocal7, t ) {
												k.current = t;
												_eLocal7.target.classList.add(
													'grabbing'
												);
											} )( _eLocal6, _nLocal3 );
										},
										onDragEnter( _eLocal8 ) {
											return ( function (
												_eLocal9,
												_nLocal4
											) {
												_eLocal9.preventDefault();
												j.current = _nLocal4;
												var _oLocal = ( function (
														_eLocal0
													) {
														return (
															( function (
																_eLocal1
															) {
																if (
																	Array.isArray(
																		_eLocal1
																	)
																) {
																	return Q3(
																		_eLocal1
																	);
																}
															} )( _eLocal0 ) ||
															( function (
																_eLocal10
															) {
																if (
																	( 'undefined' !==
																		typeof Symbol &&
																		( (
																			_nullableValue2
																		) =>
																			_nullableValue2 !==
																				null &&
																			_nullableValue2 !==
																				void 0 )(
																			_eLocal10[
																				Symbol
																					.iterator
																			]
																		) ) ||
																	( (
																		_nullableValue3
																	) =>
																		_nullableValue3 !==
																			null &&
																		_nullableValue3 !==
																			void 0 )(
																		_eLocal10[
																			'@@iterator'
																		]
																	)
																) {
																	return Array.from(
																		_eLocal10
																	);
																}
															} )( _eLocal0 ) ||
															Y3( _eLocal0 ) ||
															( function () {
																throw new TypeError(
																	'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
																);
															} )()
														);
													} )( r ),
													_iLocal =
														_oLocal[ k.current ];
												_oLocal.splice( k.current, 1 );
												_oLocal.splice(
													j.current,
													0,
													_iLocal
												);
												k.current = j.current;
												j.current = null;
												a( _oLocal );
												setLevelList( _oLocal );
											} )( _eLocal8, _nLocal3 );
										},
										onDragEnd: A,
										onDrop: M,
										onDragOver: F,
									};
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
												'No level yet!',
												'ohmylms'
											),
											description: ( 0, I18n.__ )(
												"Start building your first level and it'll show up here as soon as you hit publish.",
												'ohmylms'
											),
										}
									),
								} }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ s && (
						<LevelEditor
							data={ p }
							isOpen={ s }
							onClose={ d }
							badgeList={ r }
							setItems={ a }
							fetchData={ L }
						/>
					) }
					{ E && (
						<Ie
							title={ ( 0, I18n.__ )(
								'Delete Level',
								'ohmylms'
							) }
							description={ ( 0, I18n.__ )(
								'Are you sure you want to delete this level?',
								'ohmylms'
							) }
							onClose={ function () {
								S( ! 1 );
							} }
							onDelete={ B }
							isOpen={ E }
							isDelete={ ! 0 }
						/>
					) }
				</React.Fragment>
			 )
		 );
	};
}
