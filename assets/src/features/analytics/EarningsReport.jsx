import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Skeleton } from '../../shared/skeleton';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities, isValidDateRange } from '../../shared/utils';
import * as wpDate from '../../shared/utils/wpDate.mjs';
import { EmptyState, NoDataIllustration } from '../../shared/media';
export function createEarningsReport( readRuntime ) {
	return function EarningsReport() {
		const {
			$U,
			Aq,
			Fq,
			LU: EarningsChart,
			Mq,
			Nr: BackButton,
			Pq,
			React,
			Rq: TransactionHistory,
			Tq,
			ZU: AnalyticsDateFilter,
			b: I18n,
			dc,
			f: Router,
			g: ReactHooks,
			kq,
			l,
			lN,
			mG: EarningsSummaryCards,
			sn,
		} = readRuntime();
		var e;
		var t;
		var n;
		var r;
		var a;
		var o;
		var i = Tq( ( 0, ReactHooks.useState )( ! 0 ), 2 );
		var c = i[ 0 ];
		var u = i[ 1 ];
		var s = Tq( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var d = s[ 0 ];
		var m = s[ 1 ];
		var p = Tq( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var v = p[ 0 ];
		var y = p[ 1 ];
		var w = Tq( ( 0, ReactHooks.useState )( 'last_30_days' ), 2 );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var R = Tq( ( 0, ReactHooks.useState )( {} ), 2 );
		var x = R[ 0 ];
		var C = R[ 1 ];
		var P = Tq( ( 0, ReactHooks.useState )( {} ), 2 );
		var O = P[ 0 ];
		var k = P[ 1 ];
		var j = Tq( ( 0, ReactHooks.useState )( [] ), 2 );
		var A = j[ 0 ];
		var M = j[ 1 ];
		var T = Tq( ( 0, ReactHooks.useState )( 0 ), 2 );
		var F = T[ 0 ];
		var N = T[ 1 ];
		var D = Tq( ( 0, ReactHooks.useState )( 'all' ), 2 );
		var W = D[ 0 ];
		var z = D[ 1 ];
		var B = Tq( ( 0, ReactHooks.useState )( {} ), 2 );
		var V = B[ 0 ];
		var H = B[ 1 ];
		var G = Tq( ( 0, ReactHooks.useState )( '30 days' ), 2 );
		var U = G[ 0 ];
		var q = G[ 1 ];
		var Y = ( 0, Router.Zp )();
		var Z = [
			{
				label: ( 0, I18n.__ )( 'Income', 'ohmylms' ),
				tooltip: ( 0, I18n.__ )(
					'Total income '.concat( U ? 'within ' + U : 'of all time' ),
					'ohmylms'
				),
				value: (
					<Fq
						currency={ V.currency || '$' }
						currency_pos={ V.currency_pos || 'left' }
						price={ Number(
							( x === null || x === void 0
								? void 0
								: x.total_revenue ) || '0'
						) }
					/>
				),
				progression_percent:
					Math.abs(
						( x === null ||
						x === void 0 ||
						null === ( e = x.growth ) ||
						void 0 === e
							? void 0
							: e.total_revenue ) || 0
					) + '%',
				progression_text: 'within last',
				progression_delay: '30day',
				progression_state:
					Number(
						x === null ||
							x === void 0 ||
							null === ( t = x.growth ) ||
							void 0 === t
							? void 0
							: t.total_revenue
					) > -1
						? 'success'
						: 'danger',
				card_class: 'card-earning',
				iconColor: '#6e42d3',
			},
			{
				label: ( 0, I18n.__ )( 'Refund', 'ohmylms' ),
				tooltip: ( 0, I18n.__ )(
					'Total refund '.concat(
						U ? 'within ' + U : 'of all time',
						'.'
					),
					'ohmylms'
				),
				value: (
					<Fq
						currency={ V.currency || '$' }
						currency_pos={ V.currency_pos || 'left' }
						price={ Number(
							( x === null || x === void 0
								? void 0
								: x.total_refund ) || '0'
						) }
					/>
				),
				progression_percent:
					Math.abs(
						( x === null ||
						x === void 0 ||
						null === ( n = x.growth ) ||
						void 0 === n
							? void 0
							: n.total_refund ) || 0
					) + '%',
				progression_text: 'within last',
				progression_delay: '30day',
				progression_state:
					Number(
						x === null ||
							x === void 0 ||
							null === ( r = x.growth ) ||
							void 0 === r
							? void 0
							: r.total_refund
					) > 0
						? 'danger'
						: 'success',
				card_class: 'card-refund',
				iconColor: '#ff4955',
			},
			{
				label: ( 0, I18n.__ )( 'Net Income', 'ohmylms' ),
				tooltip: ( 0, I18n.__ )(
					'Total net income '.concat(
						U ? 'within ' + U : 'of all time',
						'.'
					),
					'ohmylms'
				),
				value: (
					<Fq
						currency={ V.currency || '$' }
						currency_pos={ V.currency_pos || 'left' }
						price={ Number(
							( x === null || x === void 0
								? void 0
								: x.net_amount ) || '0'
						) }
					/>
				),
				progression_percent:
					Math.abs(
						( x === null ||
						x === void 0 ||
						null === ( a = x.growth ) ||
						void 0 === a
							? void 0
							: a.net_amount ) || 0
					) + '%',
				progression_text: 'within last',
				progression_delay: '10day',
				progression_state:
					Number(
						x === null ||
							x === void 0 ||
							null === ( o = x.growth ) ||
							void 0 === o
							? void 0
							: o.net_amount
					) > -1
						? 'success'
						: 'danger',
				card_class: 'card-net-income',
			},
		];
		var $ = [
			{
				title: 'Date',
				dataIndex: 'date',
				key: 'date',
				render( _eLocal ) {
					if ( ! _eLocal ) {
						return 'N/A';
					}
					try {
						return ( 0, wpDate.format )(
							'M d, Y',
							new Date( _eLocal )
						);
					} catch {
						return 'Invalid Date';
					}
				},
				sorter( _eLocal3, _tLocal ) {
					var _nLocal, _rLocal;
					return (
						sn()(
							null ===
								( _nLocal = _eLocal3[ 'date_created-date' ] ) ||
								void 0 === _nLocal
								? void 0
								: _nLocal.date
						).valueOf() -
						sn()(
							null ===
								( _rLocal = _tLocal[ 'date_created-date' ] ) ||
								void 0 === _rLocal
								? void 0
								: _rLocal.date
						).valueOf()
					);
				},
				showSorterTooltip: {
					target: 'sorter-icon',
				},
			},
			{
				title: 'Order',
				dataIndex: 'order_id',
				key: 'order_id',
				render( _eLocal4, _tLocal2 ) {
					return (
						<span>
							{ '#' }
							{ _tLocal2 === null || _tLocal2 === void 0
								? void 0
								: _tLocal2.order_id }
						</span>
					);
				},
				sorter( _eLocal5, _tLocal3 ) {
					return _eLocal5.order_id - _tLocal3.order_id;
				},
				showSorterTooltip: {
					target: 'sorter-icon',
				},
			},
			{
				title: 'Type',
				dataIndex: 'type',
				key: 'type',
				className: 'ohmylms-transaction-history-type',
				render( _eLocal6 ) {
					return (
						<React.Fragment>{ _eLocal6 || 'N/A' }</React.Fragment>
					);
				},
			},
			{
				title: 'Details',
				dataIndex: 'order_items',
				key: 'order_items',
				className: 'ohmylms-transaction-history-details',
				render( _eLocal7, _tLocal5 ) {
					var _nLocal2;
					return ( null === ( _nLocal2 = _tLocal5.order_items ) ||
					void 0 === _nLocal2
						? void 0
						: _nLocal2.length ) > 0 ? (
						<React.Fragment>
							{ _tLocal5.order_items.map(
								function ( _eLocal8, _tLocal6 ) {
									return (
										<span key={ _tLocal6 }>
											{ decodeHtmlEntities(
												_eLocal8 === null ||
													_eLocal8 === void 0
													? void 0
													: _eLocal8.course_name
											) || 'Untitled' }
										</span>
									);
								}
							) }
						</React.Fragment>
					) : (
						'-'
					);
				},
			},
			{
				title: 'Earnings',
				dataIndex: 'order_total',
				key: 'order_total',
				render( _eLocal9 ) {
					return (
						<Fq
							currency={
								V === null || V === void 0 ? void 0 : V.currency
							}
							currency_pos={
								V === null || V === void 0
									? void 0
									: V.currency_pos
							}
							price={ Number( _eLocal9 ) }
						/>
					);
				},
				sorter( _eLocal0, _tLocal8 ) {
					return _eLocal0.order_total - _tLocal8.order_total;
				},
				showSorterTooltip: {
					target: 'sorter-icon',
				},
			},
		];
		var K = $.map( function ( _eLocal1 ) {
			return Aq(
				Aq( {}, _eLocal1 ),
				{},
				{
					render() {
						return <Skeleton active={ ! 0 } paragraph={ ! 1 } />;
					},
				}
			);
		} );
		var J =
			( ( 0, I18n.__ )( 'CSV', 'ohmylms' ),
			( 0, I18n.__ )( 'PDF', 'ohmylms' ),
			( 0, ReactHooks.useCallback )(
				( function () {
					var _eLocal10,
						_tLocal9 =
							( ( _eLocal10 = Pq().m( function _eLocal11(
								..._args2
							) {
								let [ _tLocal0 ] = _args2;
								var _nLocal3,
									_rLocal2,
									_aLocal,
									_oLocal,
									_iLocal,
									_cLocal,
									_uLocal,
									_sLocal,
									_dLocal = _args2;
								return Pq().w(
									function ( _eLocal12 ) {
										for (;;) {
											switch (
												( _eLocal12.p = _eLocal12.n )
											) {
												case 0:
													{
														_nLocal3 =
															_dLocal.length >
																1 &&
															void 0 !==
																_dLocal[ 1 ]
																? _dLocal[ 1 ]
																: 'last_30_days';
														_rLocal2 =
															_dLocal.length >
																2 &&
															void 0 !==
																_dLocal[ 2 ]
																? _dLocal[ 2 ]
																: '';
														_aLocal =
															_dLocal.length >
																3 &&
															void 0 !==
																_dLocal[ 3 ]
																? _dLocal[ 3 ]
																: 'all';
														_oLocal =
															_dLocal.length >
																4 &&
															void 0 !==
																_dLocal[ 4 ]
																? _dLocal[ 4 ]
																: 'date';
														_iLocal =
															_dLocal.length >
																5 &&
															void 0 !==
																_dLocal[ 5 ]
																? _dLocal[ 5 ]
																: 'DESC';
														{
															_eLocal12.n = 1;
															break;
														}
													}
													return (
														C(
															null === $U.$C ||
																void 0 === $U.$C
																? void 0
																: $U.$C
																		.earning_graph
														),
														k(
															null === $U.$C ||
																void 0 === $U.$C
																? void 0
																: $U.$C
																		.order_by_country
														),
														M(
															null === $U.$C ||
																void 0 === $U.$C
																? void 0
																: $U.$C
																		.transactions
														),
														N(
															null === $U.$C ||
																void 0 === $U.$C
																? void 0
																: $U.$C
																		.count_unchecked_orders
														),
														H( {
															currency:
																null ===
																	$U.$C ||
																void 0 === $U.$C
																	? void 0
																	: $U.$C
																			.currency,
															currency_pos:
																null ===
																	$U.$C ||
																void 0 === $U.$C
																	? void 0
																	: $U.$C
																			.currency_pos,
														} ),
														_tLocal0( ! 1 ),
														_eLocal12.a( 2 )
													 );
												case 1:
													return (
														_tLocal0( ! 0 ),
														( _eLocal12.p = 2 ),
														( _cLocal = {
															filter: _nLocal3,
															data_type: _aLocal,
															type: _rLocal2,
															order: _oLocal,
															sort_by: _iLocal,
														} ),
														isValidDateRange(
															_nLocal3,
															sn
														) &&
															( ( _cLocal.filter =
																'custom' ),
															( _cLocal.start_date =
																sn()(
																	_nLocal3[ 0 ]
																).format(
																	'YYYY-MM-DD'
																) ),
															( _cLocal.end_date =
																sn()(
																	_nLocal3[ 1 ]
																).format(
																	'YYYY-MM-DD'
																) ) ),
														( _eLocal12.n = 3 ),
														l()( {
															path: ( 0,
															lN.addQueryArgs )(
																'/ohmylms/v1/analytics/earnings',
																_cLocal
															),
															method: 'GET',
															headers: {
																'Content-Type':
																	'application/json',
															},
														} )
													 );
												case 3:
													_uLocal = _eLocal12.v;
													if ( 'all' === _aLocal ) {
														{
															C(
																_uLocal ===
																	null ||
																	_uLocal ===
																		void 0
																	? void 0
																	: _uLocal.earning_graph
															);
															k(
																_uLocal ===
																	null ||
																	_uLocal ===
																		void 0
																	? void 0
																	: _uLocal.order_by_country
															);
															M(
																_uLocal ===
																	null ||
																	_uLocal ===
																		void 0
																	? void 0
																	: _uLocal.transactions
															);
															N(
																_uLocal ===
																	null ||
																	_uLocal ===
																		void 0
																	? void 0
																	: _uLocal.count_unchecked_orders
															);
															H( {
																currency:
																	_uLocal ===
																		null ||
																	_uLocal ===
																		void 0
																		? void 0
																		: _uLocal.currency,
																currency_pos:
																	_uLocal ===
																		null ||
																	_uLocal ===
																		void 0
																		? void 0
																		: _uLocal.currency_pos,
															} );
														}
													} else if (
														'earning' === _aLocal
													) {
														C(
															_uLocal === null ||
																_uLocal ===
																	void 0
																? void 0
																: _uLocal.earning_graph
														);
													} else if (
														'order' === _aLocal
													) {
														M(
															_uLocal === null ||
																_uLocal ===
																	void 0
																? void 0
																: _uLocal.transactions
														);
													}
													_eLocal12.n = 5;
													break;
												case 4:
													_eLocal12.p = 4;
													_sLocal = _eLocal12.v;
													showRequestError(
														'Error fetching data:',
														_sLocal
													);
												// Falls through to the shared async state cleanup.
												case 5:
													return (
														( _eLocal12.p = 5 ),
														_tLocal0( ! 1 ),
														_eLocal12.f( 5 )
													 );
												case 6:
													return _eLocal12.a( 2 );
											}
										}
									},
									_eLocal11,
									null,
									[ [ 2, 4, 5, 6 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal1 = this,
									_nLocal4 = _args;
								return new Promise( function (
									_rLocal3,
									_aLocal2
								) {
									var _oLocal2 = _eLocal10.apply(
										_tLocal1,
										_nLocal4
									);
									function _iLocal2( _eLocal13 ) {
										kq(
											_oLocal2,
											_rLocal3,
											_aLocal2,
											_iLocal2,
											_lLocal,
											'next',
											_eLocal13
										);
									}
									function _lLocal( _eLocal14 ) {
										kq(
											_oLocal2,
											_rLocal3,
											_aLocal2,
											_iLocal2,
											_lLocal,
											'throw',
											_eLocal14
										);
									}
									_iLocal2( void 0 );
								} );
							} );
					return function ( ..._args3 ) {
						return _tLocal9.apply( this, _args3 );
					};
				} )(),
				[]
			) );
		var X =
			( ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All Times', 'ohmylms' ),
					},
					{
						value: 'last_30_days',
						label: ( 0, I18n.__ )( 'Last 30 days', 'ohmylms' ),
					},
					{
						value: 'current_month',
						label: ( 0, I18n.__ )( 'Current month', 'ohmylms' ),
					},
					{
						value: 'previous_month',
						label: ( 0, I18n.__ )( 'Previous month', 'ohmylms' ),
					},
					{
						value: 'current_year',
						label: ( 0, I18n.__ )( 'Current year', 'ohmylms' ),
					},
					{
						value: 'last_12_months',
						label: ( 0, I18n.__ )( 'Last 12 months', 'ohmylms' ),
					},
				];
			}, [] ),
			( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: (
							<span>{ ( 0, I18n.__ )( 'All', 'ohmylms' ) }</span>
						),
						value: 'all',
					},
					{
						label: (
							<span>
								{ ( 0, I18n.__ )( 'Course', 'ohmylms' ) }
							</span>
						),
						value: 'course',
					},
					{
						label: (
							<span>
								{ ( 0, I18n.__ )( 'Membership', 'ohmylms' ) }
							</span>
						),
						value: 'membership',
					},
				];
			}, [] ) );
		( 0, ReactHooks.useEffect )( function () {
			var _eLocal16 = ! 0;
			return (
				_eLocal16 && J( u ),
				function () {
					_eLocal16 = ! 1;
				}
			 );
		}, [] );
		var ee = {
			background: '#6e42d3',
			borderRadius: '2px',
			width: '10px',
			height: '10px',
			display: 'inline-block',
			marginInlineEnd: '15px',
		};
		return (
			<React.Fragment>
				<Controls.SurfaceWP>
					<Controls.ContainerWP>
						<Controls.SpacerWP paddingY={ 5 }>
							<Controls.FlexWP
								gap={ 2 }
								align={ 'center' }
								justify={ 'flex-start' }
							>
								<BackButton
									onClick={ function () {
										Y( '/dashboard' );
									} }
								/>
								<Controls.HeadingWP level={ '2' } size={ 20 }>
									{ ( 0, I18n.__ )(
										'Earnings Report',
										'ohmylms'
									) }
								</Controls.HeadingWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP
								padding={ 7.5 }
								marginBottom={ 0 }
							>
								<Controls.FlexWP
									gap={ 4 }
									align={ 'stretch' }
									className={
										'ohmylms-earning-report-cards-wrapper'
									}
								>
									<Controls.FlexItemWP
										style={ {
											width: 'calc(67% - 8px)',
										} }
										className={
											'ohmylms-earning-report-left-card'
										}
									>
										<Controls.CardWP isBorderless={ ! 0 }>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 6 }
											>
												<Controls.FlexWP
													gap={ 2 }
													align={ 'center' }
													justify={ 'space-between' }
												>
													<Controls.HeadingWP
														level={ 3 }
													>
														{ ( 0, I18n.__ )(
															'Earnings',
															'ohmylms'
														) }
													</Controls.HeadingWP>
													<div
														className={
															'ohmylms-dashboard-filter'
														}
													>
														<AnalyticsDateFilter
															placeholder={ ( 0,
															I18n.__ )(
																'Filter By Days',
																'ohmylms'
															) }
															onChange={ function (
																_eLocal17
															) {
																if (
																	'custom_range' !==
																	_eLocal17
																) {
																	J(
																		m,
																		_eLocal17,
																		'',
																		'earning'
																	);
																}
																q(
																	( () => {
																		if (
																			'all' ===
																			_eLocal17
																		) {
																			return null;
																		}
																		if (
																			'last_30_days' ===
																			_eLocal17
																		) {
																			return 'last 30 days';
																		}
																		return ( () => {
																			if (
																				'current_month' ===
																				_eLocal17
																			) {
																				return 'current month';
																			}
																			if (
																				'previous_month' ===
																				_eLocal17
																			) {
																				return 'previous month';
																			}
																			return ( () => {
																				if (
																					'current_year' ===
																					_eLocal17
																				) {
																					return 'current year';
																				}
																				if (
																					'last_12_months' ===
																					_eLocal17
																				) {
																					return 'last 12 months';
																				}
																				return 'custom range';
																			} )();
																		} )();
																	} )()
																);
															} }
															direction={
																'start'
															}
															onRangeChange={ function (
																_eLocal18
															) {
																J(
																	m,
																	_eLocal18,
																	'',
																	'earning'
																);
															} }
														/>
													</div>
												</Controls.FlexWP>
												<Controls.SpacerWP
													marginBottom={ 4 }
												/>
												<Controls.FlexWP
													gap={ 4 }
													align={ 'stretch' }
												>
													<EarningsSummaryCards
														dashboardCardData={ Z }
														dataLoading={ d || c }
														withIn={ U }
													/>
												</Controls.FlexWP>
											</Controls.SpacerWP>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 6 }
											>
												{ c ? (
													<Skeleton
														paragraph={ {
															rows: 3,
														} }
														active={ ! 0 }
													/>
												) : (
													<React.Fragment>
														<Controls.SpacerWP
															marginBottom={ 4 }
															style={ {
																height: '330px',
															} }
														>
															<EarningsChart
																currency={
																	V ===
																		null ||
																	V === void 0
																		? void 0
																		: V.currency
																}
																currency_pos={
																	V ===
																		null ||
																	V === void 0
																		? void 0
																		: V.currency_pos
																}
																graphData={
																	( x ===
																		null ||
																	x === void 0
																		? void 0
																		: x.graph_data ) ||
																	{}
																}
																filterTypeParam={ {
																	type: 'custom',
																} }
															/>
														</Controls.SpacerWP>
														<Controls.FlexWP
															gap={ 5 }
															align={ 'center' }
															justify={ 'center' }
														>
															<Controls.FlexItemWP
																style={ Mq(
																	{},
																	'--base-color',
																	'#6e42d3'
																) }
															>
																<span
																	style={ ee }
																/>
																{ ( 0,
																I18n.__ )(
																	'Income',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
															<Controls.FlexItemWP
																style={ Mq(
																	{},
																	'--base-color',
																	'#FF4955'
																) }
															>
																<span
																	style={ Aq(
																		Aq(
																			{},
																			ee
																		),
																		{},
																		{
																			background:
																				'#FF4955',
																		}
																	) }
																/>
																{ ( 0,
																I18n.__ )(
																	'Refund',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
															<Controls.FlexItemWP
																style={ Mq(
																	{},
																	'--base-color',
																	'#33A646'
																) }
															>
																<span
																	style={ Aq(
																		Aq(
																			{},
																			ee
																		),
																		{},
																		{
																			background:
																				'#33A646',
																		}
																	) }
																/>
																{ ( 0,
																I18n.__ )(
																	'Net Income',
																	'ohmylms'
																) }
															</Controls.FlexItemWP>
														</Controls.FlexWP>
													</React.Fragment>
												) }
											</Controls.SpacerWP>
										</Controls.CardWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP
										style={ {
											width: 'calc(33% - 8px)',
										} }
										className={
											'ohmylms-earning-report-right-card'
										}
									>
										<Controls.FlexWP
											align={ 'start' }
											justify={ 'start' }
											direction={ 'column' }
											gap={ 4 }
										>
											<Controls.FlexItemWP
												fullWidth={ ! 0 }
											>
												<Controls.CardWP
													isBorderless={ ! 0 }
													fullWidth={ ! 0 }
													fullHeight={ ! 0 }
												>
													<Controls.SpacerWP
														marginBottom={ 0 }
														padding={ 6 }
													>
														<Controls.HeadingWP
															level={ 3 }
															size={ 16 }
														>
															{ ( 0, I18n.__ )(
																'Order report',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														<Controls.SpacerWP
															marginBottom={ 5 }
															marginTop={ 8 }
														>
															{ c ? (
																<Skeleton
																	paragraph={ {
																		rows: 3,
																	} }
																	active={
																		! 0
																	}
																/>
															) : (
																<React.Fragment>
																	<Controls.FlexWP
																		gap={
																			2
																		}
																		align={
																			'center'
																		}
																		justify={
																			'flex-start'
																		}
																	>
																		<Controls.BadgeWP
																			variant={
																				'secondary'
																			}
																			isBorderLess={
																				! 0
																			}
																			isRounded={
																				! 0
																			}
																		>
																			<svg
																				width={
																					'24'
																				}
																				height={
																					'24'
																				}
																				fill={
																					'none'
																				}
																				viewBox={
																					'0 0 24 24'
																				}
																				xmlns={
																					'http://www.w3.org/2000/svg'
																				}
																			>
																				<path
																					fill={
																						'#83BF6E'
																					}
																					fillRule={
																						'evenodd'
																					}
																					d={
																						'M5.586 7l4.293-4.293a3 3 0 014.242 0L18.414 7h2.433a1 1 0 01.99 1.141l-1.469 10.283A3 3 0 0117.398 21H6.602a3 3 0 01-2.97-2.576L2.163 8.141A1 1 0 013.153 7h2.433zm5.707-2.879a1 1 0 011.414 0L15.586 7H8.414l2.879-2.879zM4.306 9l1.306 9.141a1 1 0 00.99.859h10.796a1 1 0 00.99-.859L19.694 9H4.306z'
																					}
																					clipRule={
																						'evenodd'
																					}
																				/>
																				<path
																					fill={
																						'#83BF6E'
																					}
																					fillRule={
																						'evenodd'
																					}
																					d={
																						'M8 11a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1zm4 0a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1zm4 0a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1z'
																					}
																					clipRule={
																						'evenodd'
																					}
																				/>
																			</svg>
																		</Controls.BadgeWP>
																		{ F >
																		0 ? (
																			<p>
																				{ ( 0,
																				I18n.__ )(
																					'You have',
																					'ohmylms'
																				) }{ ' ' }
																				<strong>
																					{ ' ' }
																					{
																						F
																					}{ ' ' }
																					{ ( 0,
																					I18n.__ )(
																						'unchecked orders',
																						'ohmylms'
																					) }{ ' ' }
																				</strong>
																				{
																					' 👀'
																				}
																			</p>
																		) : (
																			<p>
																				{ ( 0,
																				I18n.__ )(
																					'You have no new order',
																					'ohmylms'
																				) }
																			</p>
																		) }
																	</Controls.FlexWP>
																</React.Fragment>
															) }
														</Controls.SpacerWP>
														<Controls.ButtonWP
															variant={
																'secondary'
															}
															onClick={ function () {
																Y( '/orders' );
															} }
														>
															{ ( 0, I18n.__ )(
																'Review Orders',
																'ohmylms'
															) }
														</Controls.ButtonWP>
													</Controls.SpacerWP>
												</Controls.CardWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP
												flex={ '1' }
												fullWidth={ ! 0 }
											>
												<Controls.CardWP
													isBorderless={ ! 0 }
													fullWidth={ ! 0 }
													fullHeight={ ! 0 }
												>
													<Controls.SpacerWP
														marginBottom={ 0 }
														padding={ 6 }
													>
														<Controls.HeadingWP
															level={ 3 }
															size={ 16 }
														>
															{ ( 0, I18n.__ )(
																'Earning from top countries',
																'ohmylms'
															) }
														</Controls.HeadingWP>
														{ c ? (
															<Skeleton
																paragraph={ {
																	rows: 3,
																} }
																active={ ! 0 }
															/>
														) : (
															<React.Fragment>
																{ Object.keys(
																	O
																).length > 0 ? (
																	<Controls.SpacerWP
																		marginBottom={
																			0
																		}
																		marginTop={
																			6
																		}
																	>
																		{ Object.entries(
																			O
																		).map(
																			function (
																				_eLocal19
																			) {
																				var _tLocal10 =
																						Tq(
																							_eLocal19,
																							2
																						),
																					_nLocal5 =
																						_tLocal10[ 0 ],
																					_rLocal4 =
																						_tLocal10[ 1 ];
																				return (
																					<Controls.SpacerWP
																						key={
																							_nLocal5
																						}
																						marginBottom={
																							4
																						}
																					>
																						<Controls.FlexWP
																							gap={
																								2
																							}
																							align={
																								'center'
																							}
																							justify={
																								'space-between'
																							}
																						>
																							<Controls.FlexBlockWP>
																								<Controls.FlexWP
																									gap={
																										2
																									}
																									align={
																										'center'
																									}
																									justify={
																										'flex-start'
																									}
																								>
																									<span
																										className={
																											'flag'
																										}
																									>
																										{ React.createElement(
																											dc,
																											{
																												style: {
																													diplay: 'block',
																												},
																											}
																										) }
																									</span>
																									{
																										_nLocal5
																									}
																								</Controls.FlexWP>
																							</Controls.FlexBlockWP>
																							<Controls.FlexBlockWP
																								style={ {
																									textAlign:
																										'right',
																								} }
																							>
																								<Fq
																									currency={
																										V ===
																											null ||
																										V ===
																											void 0
																											? void 0
																											: V.currency
																									}
																									currency_pos={
																										V ===
																											null ||
																										V ===
																											void 0
																											? void 0
																											: V.currency_pos
																									}
																									price={ Number(
																										_rLocal4
																									) }
																								/>
																							</Controls.FlexBlockWP>
																						</Controls.FlexWP>
																					</Controls.SpacerWP>
																				);
																			}
																		) }
																	</Controls.SpacerWP>
																) : (
																	<React.Fragment>
																		<EmptyState
																			icon={
																				<NoDataIllustration />
																			}
																			title={ ( 0,
																			I18n.__ )(
																				'No global earnings to show yet!',
																				'ohmylms'
																			) }
																			description={ ( 0,
																			I18n.__ )(
																				'As learners from around the world purchase your courses, their countries will show up here.',
																				'ohmylms'
																			) }
																		/>
																	</React.Fragment>
																) }
															</React.Fragment>
														) }
													</Controls.SpacerWP>
												</Controls.CardWP>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
								<Controls.SpacerWP marginBottom={ 4 } />
								<Controls.CardWP isBorderless={ ! 0 }>
									<Controls.SpacerWP
										marginBottom={ 0 }
										padding={ 6 }
									>
										<TransactionHistory
											handleTypeFilters={ function (
												_eLocal20
											) {
												S( _eLocal20 );
												J( y, _eLocal20, W, 'order' );
											} }
											handleOrderTypeFilters={ function (
												_eLocal21
											) {
												z( _eLocal21 );
												J( y, E, _eLocal21, 'order' );
											} }
											orderTypeOptions={ X }
											transactionLoading={ v }
											transactionData={ A }
											skeletonColumns={ K }
											columns={ $ }
											dataLoading={ c }
											currencyData={ V }
										/>
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.ContainerWP>
					<Controls.SpacerWP marginBottom={ 0 } paddingBottom={ 5 } />
				</Controls.SurfaceWP>
			</React.Fragment>
		);
	};
}
