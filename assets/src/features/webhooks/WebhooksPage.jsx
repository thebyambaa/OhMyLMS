/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { highlightAdminMenu } from '../../shared/utils';
import * as wpDate from '../../shared/utils/wpDate.mjs';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createWebhooksPage( readRuntime ) {
	return function WebhooksPage() {
		const {
			Ea,
			He,
			Ie,
			J5,
			K5: MemoWebhookEditorModal,
			Ne,
			React,
			T: StoreModule,
			We,
			aY,
			b: I18n,
			fN,
			g: ReactHooks,
			hN,
			n4,
			q,
			t4,
			y: WordPressData,
		} = readRuntime();
		var e;
		highlightAdminMenu( 'ohmylms', 'webhooks' );
		var t = true,
			n = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			r = useNotifications(),
			a = r.openNotificationWithIcon,
			o = r.contextHolder,
			i = n4( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			l = i[ 0 ],
			c = i[ 1 ],
			u = n4( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = n4( ( 0, ReactHooks.useState )( null ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = n4( ( 0, ReactHooks.useState )( '' ), 2 ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = n4( ( 0, ReactHooks.useState )( 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = n4( ( 0, ReactHooks.useState )( 10 ), 2 ),
			x = R[ 0 ],
			C = ( R[ 1 ], n4( ( 0, ReactHooks.useState )( [] ), 2 ) ),
			P = C[ 0 ],
			O = C[ 1 ],
			k = n4( ( 0, ReactHooks.useState )( 'all' ), 2 ),
			j = k[ 0 ],
			A = k[ 1 ],
			M = n4( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			F = M[ 0 ],
			N = M[ 1 ],
			D = n4( ( 0, ReactHooks.useState )( null ), 2 ),
			W = D[ 0 ],
			B = D[ 1 ],
			V = n4( ( 0, ReactHooks.useState )( null ), 2 ),
			H = ( V[ 0 ], V[ 1 ], n4( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			G = H[ 0 ],
			U = H[ 1 ],
			Y =
				( 0, WordPressData.useSelect )( function ( _eLocal ) {
					return _eLocal( StoreModule.default ).getWebhooks();
				}, [] ) || [],
			Q =
				( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
					return _eLocal2( StoreModule.default ).getWebhookTotal();
				}, [] ) || 0,
			Z = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getAllIntegrations();
			}, [] ),
			$ =
				1 ===
				( Z === null ||
				Z === void 0 ||
				null === ( e = Z.webhooks ) ||
				void 0 === e
					? void 0
					: e.is_enable ),
			K = ( 0, ReactHooks.useCallback )(
				t4(
					J5().m( function _eLocal4( ..._args ) {
						var _tLocal,
							_rLocal,
							_aLocal,
							_oLocal = _args;
						return J5().w( function ( _eLocal5 ) {
							for (;;) {
								switch ( _eLocal5.n ) {
									case 0:
										_tLocal =
											_oLocal.length > 0 &&
											void 0 !== _oLocal[ 0 ]
												? _oLocal[ 0 ]
												: 'created_at';
										_rLocal =
											_oLocal.length > 1 &&
											void 0 !== _oLocal[ 1 ]
												? _oLocal[ 1 ]
												: 'DESC';
										c( ! 0 );
										_aLocal = {
											page: E,
											per_page: x,
											search: h,
											status: j,
											orderby: _tLocal,
											order: _rLocal,
										};
										n.getWebhooks( _aLocal ).finally(
											function () {
												c( ! 1 );
											}
										);
									// Falls through to the shared async state cleanup.
									case 1:
										return _eLocal5.a( 2 );
								}
							}
						}, _eLocal4 );
					} )
				),
				[ E, x, h, j, n, t, $ ]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				K();
			},
			[ E, x, h, j, n, t, $ ]
		);
		var J = ( 0, ReactHooks.useCallback )( function ( _eLocal6 ) {
				_( _eLocal6 );
				S( 1 );
			}, [] ),
			X = ( 0, ReactHooks.useCallback )( function ( _eLocal7 ) {
				A( _eLocal7 );
				S( 1 );
			}, [] ),
			ee = ( 0, ReactHooks.useCallback )( function ( _eLocal8 ) {
				S( _eLocal8 );
				O( [] );
			}, [] ),
			te = ( 0, ReactHooks.useCallback )(
				function () {
					if ( $ ) {
						{
							f( null );
							d( ! 0 );
						}
					} else {
						U( ! 0 );
					}
				},
				[ t, $ ]
			),
			ne = ( 0, ReactHooks.useCallback )(
				function ( _eLocal9 ) {
					if ( $ ) {
						var _nLocal = Y.find( function ( _tLocal2 ) {
							return _tLocal2.id === _eLocal9;
						} );
						if ( _nLocal ) {
							{
								f( _nLocal );
								d( ! 0 );
							}
						}
					} else {
						U( ! 0 );
					}
				},
				[ Y, t, $ ]
			),
			re = ( 0, ReactHooks.useCallback )(
				function ( _eLocal0 ) {
					if ( $ ) {
						{
							N( ! 0 );
							B( _eLocal0 );
						}
					} else {
						U( ! 0 );
					}
				},
				[ t, $ ]
			),
			ae = ( 0, ReactHooks.useCallback )( function () {
				N( ! 1 );
				B( null );
			}, [] ),
			oe = ( 0, ReactHooks.useCallback )(
				t4(
					J5().m( function _eLocal1() {
						var _tLocal3;
						return J5().w(
							function ( _eLocal10 ) {
								for (;;) {
									switch ( ( _eLocal10.p = _eLocal10.n ) ) {
										case 0:
											if (
												( ( _tLocal3 = W ? [ W ] : P ),
												( _eLocal10.p = 1 ),
												! W )
											) {
												_eLocal10.n = 3;
												break;
											}
											return (
												( _eLocal10.n = 2 ),
												n.deleteWebhookById( W )
											 );
										case 2:
											_eLocal10.n = 4;
											break;
										case 3:
											return (
												( _eLocal10.n = 4 ),
												n.bulkWebhookAction(
													'delete',
													P
												)
											 );
										case 4:
											a(
												'success',
												_tLocal3.length > 1
													? ( 0, I18n.__ )(
															'Webhooks deleted successfully',
															'ohmylms'
														)
													: ( 0, I18n.__ )(
															'Webhook deleted successfully',
															'ohmylms'
														)
											);
											K();
											O( [] );
											S( 1 );
											_eLocal10.n = 6;
											break;
										case 5:
											_eLocal10.p = 5;
											a(
												'error',
												( 0, I18n.__ )(
													'Failed to delete webhook(s)',
													'ohmylms'
												)
											);
										// Falls through to the shared async state cleanup.
										case 6:
											return (
												( _eLocal10.p = 6 ),
												B( null ),
												N( ! 1 ),
												_eLocal10.f( 6 )
											 );
										case 7:
											return _eLocal10.a( 2 );
									}
								}
							},
							_eLocal1,
							null,
							[ [ 1, 5, 6, 7 ] ]
						);
					} )
				),
				[ P, W, n, K, a ]
			),
			ie = ( 0, ReactHooks.useCallback )(
				function () {
					d( ! 1 );
					f( null );
					K();
				},
				[ K ]
			),
			le = ( 0, ReactHooks.useCallback )( function () {
				d( ! 1 );
				f( null );
			}, [] ),
			ce = ( 0, ReactHooks.useCallback )(
				function ( _eLocal11, _tLocal4, _nLocal2 ) {
					var _rLocal2 = _nLocal2.field || 'created_at',
						_aLocal2 =
							{
								ascend: 'ASC',
								descend: 'DESC',
							}[ _nLocal2.order ] || 'DESC';
					S( 1 );
					K( _rLocal2, _aLocal2 );
				},
				[ K ]
			),
			ue = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							label: ( 0, I18n.__ )( 'Active', 'ohmylms' ),
							value: 'active',
							action:
								( ( _rLocal3 = t4(
									J5().m( function _eValue() {
										return J5().w(
											function ( _eLocal12 ) {
												for (;;) {
													switch (
														( _eLocal12.p =
															_eLocal12.n )
													) {
														case 0:
															if ( $ ) {
																_eLocal12.n = 1;
																break;
															}
															return (
																U( ! 0 ),
																_eLocal12.a( 2 )
															 );
														case 1:
															return (
																( _eLocal12.p = 1 ),
																( _eLocal12.n = 2 ),
																n.bulkWebhookAction(
																	'active',
																	P
																)
															 );
														case 2:
															a(
																'success',
																( 0, I18n.__ )(
																	'Webhooks activated successfully',
																	'ohmylms'
																)
															);
															K();
															O( [] );
															_eLocal12.n = 4;
															break;
														case 3:
															_eLocal12.p = 3;
															a(
																'error',
																( 0, I18n.__ )(
																	'Failed to activate webhooks',
																	'ohmylms'
																)
															);
														// Falls through to the shared async state cleanup.
														case 4:
															return _eLocal12.a(
																2
															);
													}
												}
											},
											_eValue,
											null,
											[ [ 1, 3 ] ]
										);
									} )
								) ),
								function ( ..._args2 ) {
									return _rLocal3.apply( this, _args2 );
								} ),
						},
						{
							label: ( 0, I18n.__ )( 'Inactive', 'ohmylms' ),
							value: 'inactive',
							action:
								( ( _eLocal14 = t4(
									J5().m( function _eValue2() {
										return J5().w(
											function ( _eLocal13 ) {
												for (;;) {
													switch (
														( _eLocal13.p =
															_eLocal13.n )
													) {
														case 0:
															if ( $ ) {
																_eLocal13.n = 1;
																break;
															}
															return (
																U( ! 0 ),
																_eLocal13.a( 2 )
															 );
														case 1:
															return (
																( _eLocal13.p = 1 ),
																( _eLocal13.n = 2 ),
																n.bulkWebhookAction(
																	'inactive',
																	P
																)
															 );
														case 2:
															a(
																'success',
																( 0, I18n.__ )(
																	'Webhooks inactive successfully',
																	'ohmylms'
																)
															);
															K();
															O( [] );
															_eLocal13.n = 4;
															break;
														case 3:
															_eLocal13.p = 3;
															a(
																'error',
																( 0, I18n.__ )(
																	'Failed to inactive webhooks',
																	'ohmylms'
																)
															);
														// Falls through to the shared async state cleanup.
														case 4:
															return _eLocal13.a(
																2
															);
													}
												}
											},
											_eValue2,
											null,
											[ [ 1, 3 ] ]
										);
									} )
								) ),
								function ( ..._args3 ) {
									return _eLocal14.apply( this, _args3 );
								} ),
						},
						{
							label: ( 0, I18n.__ )( 'Delete', 'ohmylms' ),
							value: 'delete',
							action() {
								if ( $ ) {
									N( ! 0 );
								} else {
									U( ! 0 );
								}
							},
						},
					];
					var _eLocal14, _rLocal3;
				},
				[ P, n, K, a, t, $ ]
			),
			se = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All', 'ohmylms' ),
					},
					{
						value: 'active',
						label: ( 0, I18n.__ )( 'Active', 'ohmylms' ),
					},
					{
						value: 'inactive',
						label: ( 0, I18n.__ )( 'Inactive', 'ohmylms' ),
					},
				];
			}, [] ),
			de = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						selectedRowKeys: P,
						onChange: O,
					};
				},
				[ P ]
			),
			me = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						label: ( 0, I18n.__ )( 'Add Webhook', 'ohmylms' ),
						onClick: te,
					};
				},
				[ te ]
			),
			pe = [
				{
					title: ( 0, I18n.__ )( 'ID', 'ohmylms' ),
					dataIndex: 'id',
					key: 'id',
					sorter: ! 1,
					render( _eLocal15 ) {
						return (
							<Controls.ButtonWP
								variant={ 'link' }
								onClick={ function () {
									return ne( _eLocal15 );
								} }
							>
								{ '#' }
								{ _eLocal15 }
							</Controls.ButtonWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Webhook Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					sorter: ! 1,
					render( _eLocal16, _tLocal5 ) {
						return (
							<div>
								<div
									style={ {
										fontWeight: 500,
										marginBottom: '4px',
									} }
								>
									{ _eLocal16 || 'Untitled' }
								</div>
								<Controls.BadgeWP
									isBorderLess={ ! 0 }
									variant={ 'secondary' }
									style={ {
										fontSize: '11px',
									} }
								>
									{ _tLocal5.http_method }
									{ ' - ' }
									{ _tLocal5.data_type.toUpperCase() }
								</Controls.BadgeWP>
							</div>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Trigger Event', 'ohmylms' ),
					dataIndex: 'trigger_event',
					key: 'trigger_event',
					sorter: ! 1,
					render( _eLocal17 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={ 'secondary' }
							>
								{ ( function ( _eLocal18 ) {
									return (
										{
											course_purchase: ( 0, I18n.__ )(
												'Course Purchase',
												'ohmylms'
											),
											course_enrollment: ( 0, I18n.__ )(
												'Course Enrollment',
												'ohmylms'
											),
											course_completion: ( 0, I18n.__ )(
												'Course Completion',
												'ohmylms'
											),
											lesson_completion: ( 0, I18n.__ )(
												'Lesson Completion',
												'ohmylms'
											),
											quiz_submission: ( 0, I18n.__ )(
												'Quiz Submission',
												'ohmylms'
											),
											quiz_achievement: ( 0, I18n.__ )(
												'Quiz Achievement',
												'ohmylms'
											),
											assignment_submission: ( 0,
											I18n.__ )(
												'Assignment Submission',
												'ohmylms'
											),
											assignment_achievement: ( 0,
											I18n.__ )(
												'Assignment Achievement',
												'ohmylms'
											),
										}[ _eLocal18 ] || _eLocal18
									);
								} )( _eLocal17 ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Webhook URL', 'ohmylms' ),
					dataIndex: 'webhook_url',
					key: 'webhook_url',
					render( _eLocal19 ) {
						return (
							<div
								style={ {
									maxWidth: '175px',
									overflow: 'hidden',
									textOverflow: 'ellipsis',
									whiteSpace: 'nowrap',
									fontSize: '13px',
									color: '#6B7280',
								} }
								title={ _eLocal19 }
							>
								{ _eLocal19 }
							</div>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
					dataIndex: 'status',
					key: 'status',
					sorter: ! 1,
					render( _eLocal20 ) {
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={
									'active' === _eLocal20
										? 'success'
										: 'secondary'
								}
								style={ {
									textTransform: 'capitalize',
								} }
							>
								{ 'active' === _eLocal20
									? ( 0, I18n.__ )( 'Active', 'ohmylms' )
									: ( 0, I18n.__ )( 'Inactive', 'ohmylms' ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Last Updated', 'ohmylms' ),
					dataIndex: 'updated_at',
					key: 'updated_at',
					sorter: ! 1,
					render( _eLocal21 ) {
						var _tLocal6,
							_nLocal3 =
								( null ===
									( _tLocal6 = window.ohmylms_params ) ||
								void 0 === _tLocal6
									? void 0
									: _tLocal6.date_format ) || 'F j, Y',
							_rLocal4 = _eLocal21
								? ( 0, wpDate.dateI18n )( _nLocal3, _eLocal21 )
								: '-';
						return (
							<Controls.BadgeWP
								isBorderLess={ ! 0 }
								variant={ 'secondary' }
							>
								{ _rLocal4 }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Action', 'ohmylms' ),
					dataIndex: 'action',
					key: 'action',
					render( _eLocal22, _tLocal7 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										key: 'edit',
										onClick() {
											return ne(
												_tLocal7 === null ||
													_tLocal7 === void 0
													? void 0
													: _tLocal7.id
											);
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
										key: 'delete',
										onClick() {
											return re(
												_tLocal7 === null ||
													_tLocal7 === void 0
													? void 0
													: _tLocal7.id
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
			];
		return (
			<React.Fragment>
				{ o }
				<Controls.CardWP
					isBorderless={ ! 0 }
					variant={ 'secondary' }
					className={ 'ohmylms-full-screen-height' }
				>
					<Controls.SpacerWP
						padding={ 4 }
						paddingTop={ 1 }
						marginTop={ 4 }
						marginBottom={ 0 }
					>
						<PageHeader
							title={ ( 0, I18n.__ )(
								'All Webhooks',
								'ohmylms'
							) }
							showAddButton={ ! 0 }
							addButtonConfig={ me }
							paddingY={ 4 }
						/>
						<Ea
							isBorderless={ ! 0 }
							minHeight={ 'calc(100vh - 30px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								{ P.length > 0
									? React.createElement( hN, {
											items: P,
											setItems: O,
											bulksActions: ue,
										} )
									: React.createElement( aY, {
											handleSearch: J,
											searchPlaceholder: ( 0, I18n.__ )(
												'Search Webhooks',
												'ohmylms'
											),
											handleFilterByStatus: X,
											filterByStatusOptions: se,
											filterByStatus: j,
											currentPage: E,
											totalItems: Q,
											perPage: x,
											showFilterByCategory: ! 1,
											showFilterByPriceType: ! 1,
											showFilterByDays: ! 1,
										} ) }
								<Controls.TableWP
									rowKey={ 'id' }
									columns={ pe }
									dataSource={ Y || [] }
									rowSelection={ de }
									pagination={ ! 1 }
									loading={ l }
									onChange={ ce }
									scroll={ {
										x: 'max-content',
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
													'No Webhooks yet!',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													'Start building your first webhook and it will show up here as soon as you create it.',
													'ohmylms'
												),
												ctaText: ( 0, I18n.__ )(
													'Add Webhook',
													'ohmylms'
												),
												ctaHandler: te,
											}
										),
									} }
								/>
								{ ! l &&
									t &&
									$ &&
									Q > x &&
									React.createElement( fN, {
										total: Q,
										currentPage: E,
										onPageChange: ee,
										perPage: x,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.SpacerWP>
				</Controls.CardWP>
				{ F && (
					<Ie
						title={
							P.length > 1
								? ( 0, I18n.__ )( 'Delete Webhooks', 'ohmylms' )
								: ( 0, I18n.__ )( 'Delete Webhook', 'ohmylms' )
						}
						description={
							P.length > 1
								? ( 0, I18n.__ )(
										'Are you sure you want to delete these webhooks?',
										'ohmylms'
									)
								: ( 0, I18n.__ )(
										'Are you sure you want to delete this webhook?',
										'ohmylms'
									)
						}
						onClose={ ae }
						onDelete={ oe }
						isOpen={ F }
						isDelete={ ! 0 }
					/>
				) }
				<MemoWebhookEditorModal
					webhook={ p }
					isOpen={ s }
					onSave={ ie }
					onClose={ le }
				/>
				{ G && <He.default isOpen={ G } onClose={ U } /> }
			</React.Fragment>
		);
	};
}
