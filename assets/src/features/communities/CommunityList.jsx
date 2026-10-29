import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Skeleton } from '../../shared/skeleton';
import { Tooltip } from '../../shared/tooltip';
import { Table } from '../../shared/table';
import { InfoIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createCommunityList( readRuntime ) {
	return function CommunityList() {
		const {
			B9,
			Br,
			D9,
			Ge,
			L9,
			N9: CommunityEditor,
			Ne,
			React,
			Rt,
			SB,
			T: StoreModule,
			b: I18n,
			df: EmptyIcon,
			g: ReactHooks,
			l,
			q,
			uf: EmptyState,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = L9(
				( 0, ReactHooks.useState )( [
					{
						label: ( 0, I18n.__ )( 'Total members', 'ohmylms' ),
						value: '-',
						tooltip: '',
						progression_percent: '',
						progression_text: '',
						progression_delay: '',
						progression_state: '',
						card_class: '',
						iconColor: 'var(--ohmylms-primary-color)',
					},
					{
						label: ( 0, I18n.__ )( 'Total posts', 'ohmylms' ),
						value: '-',
						tooltip: '',
						progression_percent: '',
						progression_text: '',
						progression_delay: '',
						progression_state: '',
						card_class: '',
						iconColor: '#ff4955',
					},
					{
						label: ( 0, I18n.__ )( 'Engagement rate', 'ohmylms' ),
						value: '-',
						tooltip: '',
						progression_percent: '',
						progression_text: '',
						progression_delay: '',
						progression_state: '',
						card_class: '',
						iconColor: '',
					},
				] ),
				2
			),
			n = t[ 0 ],
			r = t[ 1 ],
			a = L9( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			c = L9( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = L9( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			m = d[ 0 ],
			p = d[ 1 ],
			f = L9( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			v = f[ 0 ],
			w = f[ 1 ],
			E = ( 0, ReactHooks.useCallback )(
				B9(
					D9().m( function _tLocal() {
						var _nLocal;
						return D9().w(
							function ( _tLocal2 ) {
								for (;;) {
									switch ( ( _tLocal2.p = _tLocal2.n ) ) {
										case 0:
											return (
												s( ! 0 ),
												( _tLocal2.p = 1 ),
												( _tLocal2.n = 2 ),
												e.fetchCommunities( {} )
											 );
										case 2:
											_tLocal2.n = 4;
											break;
										case 3:
											_tLocal2.p = 3;
											_nLocal = _tLocal2.v;
											showRequestError(
												'Error in fetchData:',
												_nLocal
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
				[ e ]
			),
			S = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				try {
					var _tLocal3, _nLocal2;
					return (
						( null ===
							( _tLocal3 = ( _nLocal2 = _eLocal(
								StoreModule.default
							) ).getCommunities ) || void 0 === _tLocal3
							? void 0
							: _tLocal3.call( _nLocal2 ) ) || []
					);
				} catch {
					return [];
				}
			}, [] ),
			R = ( function () {
				var _tLocal4 = B9(
					D9().m( function _tLocal5( _nLocal3 ) {
						var _rLocal, _aLocal;
						return D9().w(
							function ( _tLocal6 ) {
								for (;;) {
									switch ( ( _tLocal6.p = _tLocal6.n ) ) {
										case 0:
											if ( ! v && _nLocal3 ) {
												_tLocal6.n = 1;
												break;
											}
											return _tLocal6.a( 2 );
										case 1:
											return (
												( _tLocal6.p = 1 ),
												w( ! 0 ),
												( _tLocal6.n = 2 ),
												l()( {
													path: '/ohmylms/v1/community/spaces/'.concat(
														_nLocal3
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 2:
											_rLocal = _tLocal6.v;
											e.setCommunity( _rLocal );
											_tLocal6.n = 4;
											break;
										case 3:
											_tLocal6.p = 3;
											_aLocal = _tLocal6.v;
											showRequestError( _aLocal );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _tLocal6.p = 4 ),
												w( ! 1 ),
												_tLocal6.f( 4 )
											 );
										case 5:
											return _tLocal6.a( 2 );
									}
								}
							},
							_tLocal5,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _tLocal4.apply( this, _args );
				};
			} )(),
			x = function ( _eLocal4 ) {
				p( ! 0 );
				R(
					_eLocal4 === null || _eLocal4 === void 0
						? void 0
						: _eLocal4.id
				);
			},
			C = [
				{
					title: ( 0, I18n.__ )( 'Space Name', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					render( _eLocal5, _tLocal7 ) {
						var _nLocal4;
						return (
							<React.Fragment>
								<Controls.FlexWP
									align={ 'start' }
									justify={ 'start' }
									gap={ 4 }
									onClick={ function () {
										return x( _tLocal7 );
									} }
									style={ {
										cursor: 'pointer',
									} }
								>
									{ _tLocal7 !== null &&
									_tLocal7 !== void 0 &&
									_tLocal7.image ? (
										<Controls.AvatarWP
											shape={ 'square' }
											src={ _tLocal7.image }
											size={ 100 }
										/>
									) : (
										<span>
											<SB />
										</span>
									) }
									<Controls.TextWP
										as={ 'span' }
										color={ '#000d25' }
										size={ 16 }
										numberOfLines={ 2 }
										truncate={ ! 0 }
									>
										{ Ge(
											( () => {
												if (
													null !==
														( _nLocal4 =
															_tLocal7 === null ||
															_tLocal7 === void 0
																? void 0
																: _tLocal7.name ) &&
													void 0 !== _nLocal4
												) {
													return _nLocal4;
												}
												if (
													_tLocal7 === null ||
													_tLocal7 === void 0
												) {
													return void 0;
												}
												return _tLocal7.title;
											} )()
										) }
									</Controls.TextWP>
								</Controls.FlexWP>
							</React.Fragment>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Members', 'ohmylms' ),
					dataIndex: 'members',
					key: 'members',
					render( _eLocal6 ) {
						return (
							<Controls.TextWP>
								{ _eLocal6 !== null && _eLocal6 !== void 0
									? _eLocal6
									: '-' }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Posts', 'ohmylms' ),
					dataIndex: 'posts',
					key: 'posts',
					render( _eLocal7 ) {
						return (
							<Controls.TextWP>
								{ _eLocal7 !== null && _eLocal7 !== void 0
									? _eLocal7
									: '-' }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Engagement', 'ohmylms' ),
					dataIndex: 'engagement',
					key: 'engagement',
					render( _eLocal8 ) {
						return (
							<Controls.TextWP>
								{ _eLocal8 !== null && _eLocal8 !== void 0
									? _eLocal8
									: '-' }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Last Activity', 'ohmylms' ),
					dataIndex: 'last_activity',
					key: 'last_activity',
					render( _eLocal9 ) {
						return (
							<Controls.TextWP>
								{ _eLocal9 !== null && _eLocal9 !== void 0
									? _eLocal9
									: '-' }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Actions', 'ohmylms' ),
					key: 'actions',
					render( _eLocal0, _tLocal8 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'View',
											'ohmylms'
										),
										onClick() {
											return (
												( _eLocal1 =
													_tLocal8 === null ||
													_tLocal8 === void 0
														? void 0
														: _tLocal8.url ),
												void window.open(
													_eLocal1,
													'_blank'
												)
											 );
											var _eLocal1;
										},
										icon: <Br />,
									},
									{
										title: ( 0, I18n.__ )(
											'Settings',
											'ohmylms'
										),
										onClick() {
											return x( _tLocal8 );
										},
										icon: <Rt width={ '16' } />,
									},
								] }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			];
		return (
			( 0, ReactHooks.useEffect )( function () {
				l()( {
					path: '/ohmylms/v1/communities/analytics',
					method: 'GET',
				} ).then( function ( _eLocal10 ) {
					var _tLocal9, _nLocal5, _aLocal2, _oLocal, _lLocal, _cLocal;
					r( [
						{
							label: ( 0, I18n.__ )( 'Total members', 'ohmylms' ),
							tooltip: ( 0, I18n.__ )(
								'Total members in the community',
								'ohmylms'
							),
							value:
								null !==
									( _tLocal9 =
										null ===
											( _nLocal5 = _eLocal10.members ) ||
										void 0 === _nLocal5
											? void 0
											: _nLocal5.total ) &&
								void 0 !== _tLocal9
									? _tLocal9
									: '-',
							progression_percent: '',
							progression_text: '',
							progression_delay: '',
							progression_state: 'success',
							card_class: 'card-earning',
							iconColor: 'var(--ohmylms-primary-color)',
						},
						{
							label: ( 0, I18n.__ )( 'Total posts', 'ohmylms' ),
							tooltip: ( 0, I18n.__ )(
								'Total posts in the community',
								'ohmylms'
							),
							value:
								null !==
									( _aLocal2 =
										null ===
											( _oLocal = _eLocal10.posts ) ||
										void 0 === _oLocal
											? void 0
											: _oLocal.total ) &&
								void 0 !== _aLocal2
									? _aLocal2
									: '-',
							progression_percent: '',
							progression_text: '',
							progression_delay: '',
							progression_state: 'success',
							card_class: 'card-refund',
							iconColor: '#ff4955',
						},
						{
							label: ( 0, I18n.__ )(
								'Engagement rate',
								'ohmylms'
							),
							tooltip: ( 0, I18n.__ )(
								'Engagement rate in the community',
								'ohmylms'
							),
							value:
								null !==
									( _lLocal =
										null ===
											( _cLocal =
												_eLocal10.engagement ) ||
										void 0 === _cLocal
											? void 0
											: _cLocal.total ) &&
								void 0 !== _lLocal
									? _lLocal
									: '-',
							progression_percent: '',
							progression_text: '',
							progression_delay: '',
							progression_state: 'success',
							card_class: 'card-net-income',
							iconColor: '',
						},
					] );
					i( ! 1 );
				} );
			}, [] ),
			( 0, ReactHooks.useEffect )( function () {
				var _eLocal11 = ! 0;
				return (
					_eLocal11 && E(),
					function () {
						_eLocal11 = ! 1;
					}
				 );
			}, [] ),
			(
				<React.Fragment>
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP padding={ 5 }>
							<Controls.CardWP
								variant={ 'secondary' }
								isBorderless={ ! 0 }
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									paddingX={ 7.5 }
									paddingY={ 6 }
								>
									<Controls.FlexWP
										gap={ 4 }
										align={ 'stretch' }
										className={
											'ohmylms-overview-cards-wrapper'
										}
									>
										<Controls.FlexItemWP
											style={ {
												flex: '9',
											} }
											className={
												'ohmylms-overview-left-cards'
											}
										>
											<Controls.FlexWP
												direction={ 'column' }
												align={ 'space-between' }
												justify={ 'space-between' }
											>
												<Controls.CardWP
													isBorderless={ ! 0 }
												>
													<Controls.SpacerWP
														marginBottom={ 0 }
														padding={ 6 }
													>
														<Controls.FlexWP
															wrap={ ! 0 }
															gap={ 4 }
														>
															{ n.map(
																function (
																	_eLocal12,
																	_tLocal0
																) {
																	var _nLocal6 =
																		( _eLocal12 ===
																			null ||
																		_eLocal12 ===
																			void 0
																			? void 0
																			: _eLocal12.iconColor ) ||
																		'#33A646';
																	return (
																		<Controls.FlexBlockWP
																			key={
																				_eLocal12.label +
																				_tLocal0
																			}
																		>
																			<Controls.CardWP
																				isBorderless={
																					! 0
																				}
																				variant={
																					'secondary'
																				}
																				key={
																					_eLocal12.label
																				}
																			>
																				<Controls.SpacerWP
																					paddingX={
																						4
																					}
																					paddingY={
																						5
																					}
																					marginBottom={
																						0
																					}
																				>
																					{ o ? (
																						<Skeleton
																							rows={
																								3
																							}
																							active={
																								! 0
																							}
																						/>
																					) : (
																						<React.Fragment>
																							<Controls.BadgeWP
																								isBorderLess={
																									! 0
																								}
																								isRounded={
																									! 0
																								}
																								padding={
																									'7px'
																								}
																							>
																								<svg
																									width={
																										'13'
																									}
																									height={
																										'12'
																									}
																									fill={
																										'none'
																									}
																									viewBox={
																										'0 0 13 12'
																									}
																									xmlns={
																										'http://www.w3.org/2000/svg'
																									}
																								>
																									<path
																										fill={
																											_nLocal6
																										}
																										fillRule={
																											'evenodd'
																										}
																										d={
																											'M4.982 2.253c-.069-.285-.523-.285-.591 0L3.669 5.26c-.179.746-.917 1.28-1.772 1.28H1.06C.728 6.54.457 6.297.457 6c0-.298.27-.54.604-.54h.836c.285 0 .53-.177.59-.426l.722-3.007c.341-1.422 2.613-1.422 2.954 0l1.853 7.72c.068.284.522.284.59 0l.722-3.007c.18-.747.918-1.28 1.773-1.28h.835c.334 0 .604.242.604.54 0 .298-.27.54-.604.54h-.835c-.285 0-.532.177-.591.426l-.722 3.007c-.341 1.422-2.613 1.422-2.954 0l-1.852-7.72z'
																										}
																										clipRule={
																											'evenodd'
																										}
																									/>
																								</svg>
																							</Controls.BadgeWP>
																							<Controls.SpacerWP
																								marginBottom={
																									5
																								}
																							/>
																							<Controls.FlexWP
																								direction={
																									'column'
																								}
																								gap={
																									2
																								}
																								className={
																									'content-area'
																								}
																							>
																								<Controls.FlexWP
																									align={
																										'center'
																									}
																									gap={
																										2
																									}
																									justify={
																										'flex-start'
																									}
																								>
																									<Controls.TextWP
																										as={
																											'span'
																										}
																										size={
																											'14'
																										}
																										variant={
																											'muted'
																										}
																									>
																										{
																											_eLocal12.label
																										}
																									</Controls.TextWP>
																									{ _eLocal12.tooltip && (
																										<Tooltip
																											text={
																												_eLocal12.tooltip
																											}
																											className={
																												'ohmylms-tooltip'
																											}
																											placement={
																												'top'
																											}
																										>
																											<React.Fragment>
																												<InfoIcon />
																											</React.Fragment>
																										</Tooltip>
																									) }
																								</Controls.FlexWP>
																								<span
																									style={ {
																										fontSize:
																											'36px',
																										fontWeight:
																											'500',
																										lineHeight: 1,
																									} }
																								>
																									{
																										_eLocal12.value
																									}
																								</span>
																							</Controls.FlexWP>
																						</React.Fragment>
																					) }
																				</Controls.SpacerWP>
																			</Controls.CardWP>
																		</Controls.FlexBlockWP>
																	);
																}
															) }
														</Controls.FlexWP>
													</Controls.SpacerWP>
												</Controls.CardWP>
											</Controls.FlexWP>
										</Controls.FlexItemWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</Controls.CardWP>
							<Controls.SpacerWP
								marginTop={ 6 }
								marginBottom={ 0 }
							/>
							<Controls.CardWP
								variant={ 'secondary' }
								isBorderless={ ! 0 }
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									paddingX={ 7.5 }
									paddingY={ 6 }
								>
									<Controls.FlexWP
										direction={ 'column' }
										gap={ 4 }
									>
										<Controls.FlexItemWP>
											<Controls.TextWP
												as={ 'h2' }
												size={ '20' }
												weight={ '700' }
											>
												{ ( 0, I18n.__ )(
													'Community Spaces',
													'ohmylms'
												) }
											</Controls.TextWP>
											<Controls.TextWP
												as={ 'div' }
												size={ '14' }
												variant={ 'muted' }
											>
												{ ( 0, I18n.__ )(
													'Manage your course communities and track engagement',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexItemWP>
										<Controls.CardWP
											isBorderless={ ! 0 }
											padding={ '24px' }
										>
											<Table
												columns={ C }
												dataSource={ S }
												loading={ u }
												rowKey={ 'id' }
												style={ {
													marginTop: 0,
												} }
												locale={ {
													emptyText: (
														<EmptyState
															icon={
																<EmptyIcon />
															}
															title={ ( 0,
															I18n.__ )(
																'No community spaces yet!',
																'ohmylms'
															) }
															description={ ( 0,
															I18n.__ )(
																'Navigate to your course editor and create a community space.',
																'ohmylms'
															) }
														/>
													),
												} }
											/>
										</Controls.CardWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ m && (
						<CommunityEditor
							isOpen={ m }
							setIsOpen={ p }
							isLoading={ v }
							onSuccess={ E }
						/>
					) }
				</React.Fragment>
			 )
		 );
	};
}
