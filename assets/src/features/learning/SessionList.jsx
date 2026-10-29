/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { EmptyState, NoDataIllustration, PageHeader } from '../../shared/media';
export function createSessionList( readRuntime ) {
	return function SessionList() {
		const {
			Al: ZoomEditor,
			Ea,
			Gp,
			Ne,
			RZ,
			React,
			T: StoreModule,
			Ul: GoogleMeetEditor,
			b: I18n,
			fN,
			g: ReactHooks,
			q,
			xZ,
			y: WordPressData,
		} = readRuntime();
		var e,
			t,
			n = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			r = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).selectSessions();
			}, [] ),
			a = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2(
					StoreModule.default
				).selectSessionsPagination();
			}, [] ),
			o = ( 0, WordPressData.useSelect )( function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).getLoading();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( _eLocal4 ) {
				return _eLocal4( StoreModule.default ).getError();
			}, [] ),
			l = xZ( ( 0, ReactHooks.useState )( 1 ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = xZ( ( 0, ReactHooks.useState )( 10 ), 2 ),
			d = s[ 0 ],
			m = ( s[ 1 ], xZ( ( 0, ReactHooks.useState )( '' ), 2 ) ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = xZ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = xZ( ( 0, ReactHooks.useState )( null ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = ( 0, ReactHooks.useCallback )(
				function () {
					var _eLocal5 = {
						page: c,
						per_page: d,
						search: p,
					};
					n.fetchSessions( _eLocal5 );
				},
				[ n, c, d, p ]
			),
			x =
				( ( 0, ReactHooks.useCallback )( function ( _eLocal6 ) {
					f( _eLocal6 );
					u( 1 );
				}, [] ),
				( 0, ReactHooks.useCallback )( function ( _eLocal7 ) {
					u( _eLocal7 );
				}, [] ) ),
			C = ( 0, ReactHooks.useCallback )(
				function ( _eLocal8 ) {
					n.setSelectedLessonId( _eLocal8.id );
					S( _eLocal8 );
					_( ! 0 );
				},
				[ n ]
			),
			P = ( 0, ReactHooks.useCallback )( function ( _eLocal9, _tLocal ) {
				if ( _eLocal9[ _tLocal ] ) {
					window.open( _eLocal9[ _tLocal ], '_blank' );
				}
			}, [] ),
			O = ( 0, ReactHooks.useCallback )(
				function () {
					_( ! 1 );
					S( null );
					n.setSelectedLessonId( null );
					R();
				},
				[ n, R ]
			),
			k = ( 0, ReactHooks.useCallback )( function ( _eLocal0 ) {
				var _tLocal2 = {
					upcoming: {
						variant: 'primary',
						label: ( 0, I18n.__ )( 'Upcoming', 'ohmylms' ),
					},
					running: {
						variant: 'warning',
						label: ( 0, I18n.__ )( 'Running', 'ohmylms' ),
					},
					expired: {
						variant: 'secondary',
						label: ( 0, I18n.__ )( 'Expired', 'ohmylms' ),
					},
				}[
					_eLocal0 === null || _eLocal0 === void 0
						? void 0
						: _eLocal0.toLowerCase()
				] || {
					variant: 'secondary',
					label: _eLocal0,
				};
				return (
					<Controls.BadgeWP
						variant={ _tLocal2.variant }
						isBorderLess={ ! 0 }
					>
						{ _tLocal2.label }
					</Controls.BadgeWP>
				);
			}, [] ),
			j = ( 0, ReactHooks.useMemo )(
				function () {
					return [
						{
							title: ( 0, I18n.__ )( 'Meeting Name', 'ohmylms' ),
							dataIndex: 'topic',
							key: 'topic',
							width: '250px',
							render( _eLocal1, _tLocal3 ) {
								return (
									<div>
										<div
											style={ {
												fontWeight: '500',
												marginBottom: '4px',
											} }
										>
											{ _eLocal1 }
										</div>
										<div
											style={ {
												fontSize: '12px',
												color: '#666',
											} }
										>
											{ _tLocal3.course_title
												? 'Course: '.concat(
														_tLocal3.course_title
													)
												: '-' }
										</div>
									</div>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Platform', 'ohmylms' ),
							dataIndex: 'platform',
							key: 'platform',
							width: '120px',
							render( _eLocal10 ) {
								var _tLocal4 =
									{
										zoom: 'Zoom',
										googlemeet: 'Google Meet',
									}[
										_eLocal10 === null ||
										_eLocal10 === void 0
											? void 0
											: _eLocal10.toLowerCase()
									] || _eLocal10;
								return (
									<Controls.BadgeWP
										variant={ 'secondary' }
										isBorderLess={ ! 0 }
									>
										{ _tLocal4 }
									</Controls.BadgeWP>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Password', 'ohmylms' ),
							dataIndex: 'password',
							key: 'password',
							width: '120px',
							render( _eLocal11 ) {
								return <span>{ _eLocal11 || '-' }</span>;
							},
						},
						{
							title: ( 0, I18n.__ )(
								'Start Time & Duration',
								'ohmylms'
							),
							key: 'start_time_duration',
							width: '200px',
							render( _eLocal12, _tLocal5 ) {
								var _nLocal = '-';
								if ( _tLocal5.duration ) {
									var _rLocal = parseInt(
											_tLocal5.duration,
											10
										),
										_aLocal = Math.floor( _rLocal / 60 ),
										_oLocal = _rLocal % 60;
									_nLocal = ( () => {
										if ( _aLocal > 0 && _oLocal > 0 ) {
											return ''
												.concat( _aLocal, 'h ' )
												.concat( _oLocal, 'm' );
										}
										if ( _aLocal > 0 ) {
											return ''.concat( _aLocal, 'h' );
										}
										return ''.concat( _oLocal, 'm' );
									} )();
								}
								return (
									<div>
										<div
											style={ {
												fontWeight: '500',
												marginBottom: '4px',
											} }
										>
											{ _tLocal5.date }
										</div>
										<div
											style={ {
												fontSize: '12px',
												color: '#666',
											} }
										>
											{ _nLocal }
										</div>
									</div>
								);
							},
						},
						{
							title: ( 0, I18n.__ )( 'Status', 'ohmylms' ),
							dataIndex: 'status',
							key: 'status',
							width: '120px',
							render( _eLocal13 ) {
								return k( _eLocal13 );
							},
						},
						{
							title: ( 0, I18n.__ )( 'Actions', 'ohmylms' ),
							key: 'actions',
							width: '150px',
							render( _eLocal14, _tLocal6 ) {
								var _nLocal2,
									_rLocal2,
									_aLocal2 =
										null ===
											( _nLocal2 = _tLocal6.status ) ||
										void 0 === _nLocal2
											? void 0
											: _nLocal2.toLowerCase(),
									_oLocal2 =
										( null ===
											( _rLocal2 = _tLocal6.platform ) ||
											void 0 === _rLocal2 ||
											_rLocal2.toLowerCase(),
										[] );
								return (
									'upcoming' === _aLocal2 &&
									_tLocal6.start_url
										? _oLocal2.push( {
												title: ( 0, I18n.__ )(
													'Start Meeting',
													'ohmylms'
												),
												onClick() {
													return P(
														_tLocal6,
														'start_url'
													);
												},
												icon: <Gp />,
											} )
										: 'running' === _aLocal2 &&
											_tLocal6.start_url &&
											_oLocal2.push( {
												title: ( 0, I18n.__ )(
													'Join Meeting',
													'ohmylms'
												),
												onClick() {
													return P(
														_tLocal6,
														'start_url'
													);
												},
												icon: <Gp />,
											} ),
									_oLocal2.push( {
										title: ( 0, I18n.__ )(
											'Edit Meeting',
											'ohmylms'
										),
										onClick() {
											return C( _tLocal6 );
										},
										icon: <EditIcon />,
									} ),
									'expired' === _aLocal2 &&
										_oLocal2.push( {
											title: ( 0, I18n.__ )(
												'Start Meeting',
												'ohmylms'
											),
											icon: <RZ />,
											disabled: ! 0,
										} ),
									(
										<Controls.DropdownMenuWP
											controls={ _oLocal2 }
											icon={ <q.Icon icon={ Ne.A } /> }
										/>
									 )
								 );
							},
						},
					];
				},
				[ k, P, C ]
			);
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					R();
				},
				[ R ]
			),
			(
				<React.Fragment>
					<Controls.ContainerWP>
						<PageHeader
							title={ ( 0, I18n.__ )( 'Sessions', 'ohmylms' ) }
						/>
						{ i && (
							<Controls.NoticeWP
								status={ 'error' }
								isDismissible={ ! 1 }
							>
								{ i }
							</Controls.NoticeWP>
						) }
						<Ea
							isBorderLess={ ! 0 }
							minHeight={ 'calc(100vh - 200px)' }
						>
							<Controls.SpacerWP padding={ 5 }>
								<Controls.TableWP
									rowKey={ 'id' }
									columns={ j }
									dataSource={ r }
									loading={ o }
									pagination={ ! 1 }
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
													'No sessions yet!',
													'ohmylms'
												),
												description: ( 0, I18n.__ )(
													"Create your first session and it'll show up here.",
													'ohmylms'
												),
											}
										),
									} }
								/>
								{ ( a === null || a === void 0
									? void 0
									: a.totalSessions ) > d &&
									React.createElement( fN, {
										total: a.totalSessions,
										currentPage: c,
										onPageChange: x,
										perPage: d,
									} ) }
							</Controls.SpacerWP>
						</Ea>
					</Controls.ContainerWP>
					{ h &&
						E &&
						'googlemeet' ===
							( null === ( e = E.platform ) || void 0 === e
								? void 0
								: e.toLowerCase() ) && (
							<GoogleMeetEditor
								isOpen={ h }
								onClose={ O }
								chapterId={ E.chapter_id }
								courseId={ E.course_id }
							/>
						) }
					{ h &&
						E &&
						'googlemeet' !==
							( null === ( t = E.platform ) || void 0 === t
								? void 0
								: t.toLowerCase() ) && (
							<ZoomEditor
								isOpen={ h }
								onClose={ O }
								chapterId={ E.chapter_id }
								courseId={ E.course_id }
							/>
						) }
				</React.Fragment>
			 )
		 );
	};
}
