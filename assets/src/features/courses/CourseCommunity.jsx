import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseCommunity( readRuntime ) {
	return function CourseCommunity( props ) {
		const {
			I: Controls,
			If,
			Kt,
			Mf,
			Nf,
			React,
			Wf,
			b: I18n,
			g: ReactHooks,
			jf,
			kf,
			l,
		} = readRuntime();
		var isCommunityEnabled = props.isCommunityEnabled,
			courseId = props.courseId,
			course = props.course,
			onToggleCommunity = props.onToggleCommunity,
			onUpdateCourse = props.onUpdateCourse,
			i = ( 0, ReactHooks.useRef )( ! 1 ),
			c = Wf( ( 0, ReactHooks.useState )( '' ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = Wf(
				( 0, ReactHooks.useState )(
					( course === null || course === void 0
						? void 0
						: course.space_title ) || ''
				),
				2
			),
			m = ( d[ 0 ], d[ 1 ] ),
			p = Wf(
				( 0, ReactHooks.useState )(
					( course === null || course === void 0
						? void 0
						: course.space_description ) || ''
				),
				2
			),
			f = ( p[ 0 ], p[ 1 ] ),
			v = Wf( ( 0, ReactHooks.useState )( '' ), 2 ),
			y = v[ 0 ],
			_ = v[ 1 ];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e = ( function () {
						var _eLocal,
							t =
								( ( _eLocal = Mf().m( function _eLocal2() {
									var _tLocal, a;
									return Mf().w(
										function ( _eLocal3 ) {
											for (;;) {
												switch (
													( _eLocal3.p = _eLocal3.n )
												) {
													case 0:
														return (
															( _eLocal3.p = 0 ),
															( _eLocal3.n = 1 ),
															l()( {
																path: '/ohmylms/v1/communities/space/course/'.concat(
																	courseId
																),
																method: 'GET',
															} )
														 );
													case 1:
														if (
															( _tLocal =
																_eLocal3.v ) &&
															_tLocal.url
														) {
															{
																s(
																	_tLocal.url
																);
																_( '' );
															}
														} else {
															_(
																( 0, I18n.__ )(
																	'Community space not found for this course.',
																	'ohmylms'
																)
															);
														}
														if ( ! (
															! _tLocal ||
															_tLocal === null ||
															_tLocal ===
																void 0 ||
															! _tLocal.title ||
															( course !== null &&
																course !==
																	void 0 &&
																course.space_title )
														) ) {
															{
																m(
																	_tLocal ===
																		null ||
																		_tLocal ===
																			void 0
																		? void 0
																		: _tLocal.title
																);
																if (
																	onUpdateCourse
																) {
																	onUpdateCourse(
																		Nf(
																			Nf(
																				{},
																				course
																			),
																			{},
																			{
																				space_title:
																					_tLocal ===
																						null ||
																					_tLocal ===
																						void 0
																						? void 0
																						: _tLocal.title,
																			}
																		)
																	);
																}
															}
														}
														if ( ! (
															! _tLocal ||
															_tLocal === null ||
															_tLocal ===
																void 0 ||
															! _tLocal.description ||
															( course !== null &&
																course !==
																	void 0 &&
																course.space_description )
														) ) {
															{
																f(
																	_tLocal ===
																		null ||
																		_tLocal ===
																			void 0
																		? void 0
																		: _tLocal.description
																);
																if (
																	onUpdateCourse
																) {
																	onUpdateCourse(
																		Nf(
																			Nf(
																				{},
																				course
																			),
																			{},
																			{
																				space_description:
																					_tLocal ===
																						null ||
																					_tLocal ===
																						void 0
																						? void 0
																						: _tLocal.description,
																			}
																		)
																	);
																}
															}
														}
														_eLocal3.n = 3;
														break;
													case 2:
														_eLocal3.p = 2;
														a = _eLocal3.v;
														showRequestError(
															'Error fetching community data:',
															a
														);
														_(
															( 0, I18n.__ )(
																'Unable to fetch community space. It may have been deleted or there was a network error.',
																'ohmylms'
															)
														);
													// Falls through to the shared async state cleanup.
													case 3:
														return _eLocal3.a( 2 );
												}
											}
										},
										_eLocal2,
										null,
										[ [ 0, 2 ] ]
									);
								} ) ),
								function ( ..._args ) {
									var _tLocal2 = this,
										n = _args;
									return new Promise( function ( r, a ) {
										var o = _eLocal.apply( _tLocal2, n );
										function _iLocal( _eLocal4 ) {
											If(
												o,
												r,
												a,
												_iLocal,
												_lLocal,
												'next',
												_eLocal4
											);
										}
										function _lLocal( _eLocal5 ) {
											If(
												o,
												r,
												a,
												_iLocal,
												_lLocal,
												'throw',
												_eLocal5
											);
										}
										_iLocal( void 0 );
									} );
								} );
						return function ( ..._args2 ) {
							return t.apply( this, _args2 );
						};
					} )();
					if ( 'yes' === isCommunityEnabled ) {
						{
							e();
							i.current = ! 0;
						}
					}
				},
				[ isCommunityEnabled, courseId ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if (
						void 0 !==
						( course === null || course === void 0
							? void 0
							: course.space_title )
					) {
						m( course.space_title );
					}
					if (
						void 0 !==
						( course === null || course === void 0
							? void 0
							: course.space_description )
					) {
						f( course.space_description );
					}
				},
				[
					course === null || course === void 0
						? void 0
						: course.space_title,
					course === null || course === void 0
						? void 0
						: course.space_description,
				]
			),
			(
				<Controls.ContainerWP
					className={ 'ohmylms-community-settings' }
				>
					<Controls.SpacerWP marginBottom={ 0 } paddingY={ 10 }>
						<Controls.CardWP
							variant={ 'secondary' }
							isBorderless={ ! 0 }
						>
							<Controls.SpacerWP
								padding={ 10 }
								marginBottom={ 0 }
							>
								<h2 className={ 'title' }>
									{ ( 0, I18n.__ )(
										'Community Settings',
										'ohmylms'
									) }
								</h2>
								<p className={ 'description' }>
									{ ( 0, I18n.__ )(
										'Enable a discussion space for your students. Once enabled, a dedicated community will be created for this course.',
										'ohmylms'
									) }
								</p>
								<Controls.CardWP isBorderless={ ! 0 }>
									<Controls.SpacerWP
										marginBottom={ 0 }
										padding={ 3 }
									>
										<Controls.FlexWP
											gap={ 4 }
											direction={ 'column' }
										>
											<Kt
												title={ ( 0, I18n.__ )(
													'Enable Community for this Course',
													'ohmylms'
												) }
												description={ ( 0, I18n.__ )(
													'Allow students to participate in course discussions.',
													'ohmylms'
												) }
												isChecked={
													'yes' === isCommunityEnabled
												}
												onChange={ function ( e ) {
													if ( onToggleCommunity ) {
														onToggleCommunity( e );
													}
												} }
												conditionalChild={
													<React.Fragment>
														<Controls.SpacerWP
															marginBottom={ 4 }
														/>
														<Controls.CardWP
															isBorderless={ ! 0 }
															variant={
																'secondary'
															}
														>
															<Controls.SpacerWP
																marginBottom={
																	0
																}
																marginTop={ 2 }
																padding={ 1 }
															>
																<Controls.SpacerWP
																	padding={
																		4
																	}
																>
																	<Controls.FlexWP
																		gap={
																			8
																		}
																		align={
																			'flex-start'
																		}
																		justify={
																			'space-between'
																		}
																	>
																		<Controls.FlexItemWP
																			isBlock={
																				! 0
																			}
																		>
																			<Controls.HeadingWP
																				level={
																					'4'
																				}
																			>
																				{ ( 0,
																				I18n.__ )(
																					'Space URL',
																					'ohmylms'
																				) }
																			</Controls.HeadingWP>
																			<Controls.TextWP>
																				{ ( 0,
																				I18n.__ )(
																					'A unique URL to access the space.',
																					'ohmylms'
																				) }
																			</Controls.TextWP>
																		</Controls.FlexItemWP>
																		<Controls.FlexItemWP
																			isBlock={
																				! 0
																			}
																			className={
																				'ohmylms-coupon-generate ohmylms-community-generate'
																			}
																		>
																			<Controls.FlexWP
																				gap={
																					2
																				}
																			>
																				<Controls.FlexItemWP
																					style={ {
																						position:
																							'relative',
																						width: 'calc(100% - 40px)',
																					} }
																				>
																					<Controls.InputWP
																						type={
																							'text'
																						}
																						value={
																							u
																						}
																						readOnly={
																							! 0
																						}
																					/>
																					<Controls.ButtonWP
																						className={
																							'ohmylms-coupon-generate-btn ohmylms-community-generate-btn'
																						}
																						onClick={ function () {
																							return window.open(
																								u,
																								'_blank'
																							);
																						} }
																					>
																						{ React.createElement(
																							kf,
																							null
																						) }
																					</Controls.ButtonWP>
																				</Controls.FlexItemWP>
																				<jf.A
																					textToCopy={
																						u
																					}
																				/>
																			</Controls.FlexWP>
																		</Controls.FlexItemWP>
																	</Controls.FlexWP>
																</Controls.SpacerWP>
															</Controls.SpacerWP>
														</Controls.CardWP>
													</React.Fragment>
												}
											/>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.SpacerWP>
					{ y && (
						<Controls.NoticeWP status={ 'error' }>
							{ y }
						</Controls.NoticeWP>
					) }
				</Controls.ContainerWP>
			 )
		 );
	};
}
