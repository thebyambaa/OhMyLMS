import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createGoogleMeetEditor( readRuntime ) {
	return function GoogleMeetEditor( props ) {
		const {
			Bl,
			Fl,
			Il,
			Ll,
			M,
			Nl,
			Qi,
			React,
			T: StoreModule,
			b: I18n,
			co,
			g: ReactHooks,
			il,
			si,
			sn,
			ul,
			y: WordPressData,

			zl,
		} = readRuntime();
		const isOpen = props.isOpen,
			onClose = props.onClose,
			chapterId = props.chapterId,
			courseId = props.courseId;
		M().noConflict();
		var o,
			i,
			l,
			c = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			u = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getLesson();
			}, [] ),
			s = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getCourseChaptersContent();
				},
				[ chapterId ]
			),
			d = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).geSelectedLessonId();
			}, [] ),
			m = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			}, [] ),
			p = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			}, [] ),
			f = Ll( ( 0, ReactHooks.useState )( d ), 2 ),
			v = f[ 0 ],
			h = f[ 1 ],
			_ = Ll( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = Ll( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			R = S[ 0 ],
			x = S[ 1 ],
			C = Ll(
				( 0, ReactHooks.useState )(
					( u === null || u === void 0 ? void 0 : u.timezone ) ||
						sn().tz.guess() ||
						'UTC'
				),
				2
			),
			P = C[ 0 ],
			O = C[ 1 ],
			k = useNotifications(),
			openNotificationWithIcon = k.openNotificationWithIcon,
			contextHolder = k.contextHolder,
			I = true,
			F = Qi(),
			N = function ( e ) {
				var t,
					n =
						null === ( t = Object.values( e ) ) || void 0 === t
							? void 0
							: t.map( function ( _eLocal ) {
									var _tLocal;
									return parseInt(
										null !==
											( _tLocal =
												_eLocal === null ||
												_eLocal === void 0
													? void 0
													: _eLocal.order_number ) &&
											void 0 !== _tLocal
											? _tLocal
											: 0,
										10
									);
								} );
				return n.length ? Math.max( ...Bl( n ) ) : 0;
			},
			D = function () {
				var e =
						( u === null || u === void 0 ? void 0 : u.rawDate ) ||
						( u === null || u === void 0 ? void 0 : u.date ),
					t =
						parseInt(
							u === null || u === void 0 ? void 0 : u.duration,
							10
						) || 60;
				return e
					? sn()( e )
							.add( t, 'minute' )
							.format( 'YYYY-MM-DDTHH:mm:ss' )
					: '';
			},
			W = ( function () {
				var e = zl(
					Nl().m( function _eLocal2() {
						var t, n, _oLocal, _iLocal, _lLocal;
						return Nl().w(
							function ( _eLocal3 ) {
								for (;;) {
									switch ( ( _eLocal3.p = _eLocal3.n ) ) {
										case 0:
											{
												_eLocal3.n = 1;
												break;
											}
											return _eLocal3.a( 2 );
										case 1:
											return (
												( _eLocal3.p = 1 ),
												E( ! 0 ),
												( n = N( s.byId ) ),
												( _oLocal = n + 1 ),
												( _eLocal3.n = 2 ),
												c.addGoogleMeetClass(
													{
														topic:
															u === null ||
															u === void 0
																? void 0
																: u.topic,
														agenda:
															u === null ||
															u === void 0
																? void 0
																: u.agenda,
														date:
															( u === null ||
															u === void 0
																? void 0
																: u.rawDate ) ||
															( u === null ||
															u === void 0
																? void 0
																: u.date ),
														endDate: D(),
														duration:
															parseInt(
																u === null ||
																	u === void 0
																	? void 0
																	: u.duration,
																10
															) || 60,
														timezone: P,
														order: _oLocal,
														content_type: 'session',
														platform: 'googlemeet',
														attachments:
															null !==
																( t =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.attachments ) &&
															void 0 !== t
																? t
																: [],
														course_id: courseId,
													},
													chapterId
												)
											 );
										case 2:
											if (
												( ( _iLocal = _eLocal3.v ),
												h( _iLocal ),
												! chapterId )
											) {
												_eLocal3.n = 3;
												break;
											}
											return (
												( _eLocal3.n = 3 ),
												c.getLessonByChapterId(
													chapterId
												)
											 );
										case 3:
											_eLocal3.n = 5;
											break;
										case 4:
											_eLocal3.p = 4;
											_lLocal = _eLocal3.v;
											showRequestError( _lLocal );
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal3.p = 5 ),
												E( ! 1 ),
												_eLocal3.f( 5 )
											 );
										case 6:
											return _eLocal3.a( 2 );
									}
								}
							},
							_eLocal2,
							null,
							[ [ 1, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			B = ( function () {
				var e = zl(
					Nl().m( function _eLocal4() {
						var t;
						return Nl().w(
							function ( _eLocal5 ) {
								for (;;) {
									switch ( ( _eLocal5.p = _eLocal5.n ) ) {
										case 0:
											{
												_eLocal5.n = 1;
												break;
											}
											return _eLocal5.a( 2 );
										case 1:
											return (
												( _eLocal5.p = 1 ),
												E( ! 0 ),
												( _eLocal5.n = 2 ),
												c.updateGoogleMeetClass(
													v,
													Il(
														Il( {}, u ),
														{},
														{
															content_type:
																'session',
															platform:
																'googlemeet',
															date:
																( u === null ||
																u === void 0
																	? void 0
																	: u.rawDate ) ||
																( u === null ||
																u === void 0
																	? void 0
																	: u.date ),
															endDate: D(),
															duration:
																parseInt(
																	u ===
																		null ||
																		u ===
																			void 0
																		? void 0
																		: u.duration,
																	10
																) || 60,
															timezone: P,
														}
													),
													chapterId
												)
											 );
										case 2:
											if ( ! chapterId ) {
												_eLocal5.n = 3;
												break;
											}
											return (
												( _eLocal5.n = 3 ),
												c.getLessonByChapterId(
													chapterId
												)
											 );
										case 3:
											_eLocal5.n = 5;
											break;
										case 4:
											_eLocal5.p = 4;
											t = _eLocal5.v;
											showRequestError( t );
										// Falls through to the shared async state cleanup.
										case 5:
											return (
												( _eLocal5.p = 5 ),
												E( ! 1 ),
												_eLocal5.f( 5 )
											 );
										case 6:
											return _eLocal5.a( 2 );
									}
								}
							},
							_eLocal4,
							null,
							[ [ 1, 4, 5, 6 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			V = ( 0, ReactHooks.useCallback )(
				function ( e, t ) {
					( () => {
						if ( 'timezone' === e ) {
							return (
								O( t ),
								c.setLesson(
									Il(
										Il( {}, u ),
										{},
										{
											timezone: t,
										}
									)
								)
							 );
						}
						if ( 'date' === e ) {
							return c.setLesson(
								Il(
									Il( {}, u ),
									{},
									{
										date: t,
										rawDate: t,
									}
								)
							);
						}
						return c.setLesson(
							Il( Il( {}, u ), {}, Fl( {}, e, t ) )
						);
					} )();
				},
				[ u ]
			),
			H = ( 0, ReactHooks.useCallback )(
				function ( e ) {
					c.setLesson( Il( Il( {}, u ), e ) );
				},
				[ u ]
			),
			G = si( {
				title: u === null || u === void 0 ? void 0 : u.topic,
				initialState:
					( u === null || u === void 0
						? void 0
						: u.recording_state ) || 'empty',
				initialSource:
					( u === null || u === void 0
						? void 0
						: u.recording_source ) || void 0,
				onAttach( e ) {
					var t = e.source,
						n = e.url;
					return H( {
						recording_source: t,
						recording_url: n || '',
						recording_state: 'attached',
					} );
				},
				onDetach() {
					return H( {
						recording_source: '',
						recording_url: '',
						recording_state: 'empty',
					} );
				},
				pickMedia() {
					return new Promise( function ( e ) {
						F( {
							type: 'video',
							title: ( 0, I18n.__ )(
								'Select or Upload Recording',
								'ohmylms'
							),
							onSelect( t ) {
								return e( {
									id:
										t === null || t === void 0
											? void 0
											: t.id,
									url:
										t === null || t === void 0
											? void 0
											: t.url,
									fileName:
										( t === null || t === void 0
											? void 0
											: t.filename ) ||
										( t === null || t === void 0
											? void 0
											: t.title ) ||
										( t === null || t === void 0
											? void 0
											: t.name ),
								} );
							},
							onClose() {
								return e( null );
							},
						} );
					} );
				},
			} ),
			U = ( function () {
				var e = zl(
					Nl().m( function _eLocal6() {
						var t;
						return Nl().w(
							function ( _eLocal7 ) {
								for (;;) {
									switch ( ( _eLocal7.p = _eLocal7.n ) ) {
										case 0:
											if ( ( ( _eLocal7.p = 0 ), d ) ) {
												_eLocal7.n = 1;
												break;
											}
											return _eLocal7.a( 2 );
										case 1:
											return (
												x( ! 0 ),
												( _eLocal7.n = 2 ),
												c.getGoogleMeetClass( d )
											 );
										case 2:
											_eLocal7.n = 4;
											break;
										case 3:
											_eLocal7.p = 3;
											t = _eLocal7.v;
											showRequestError(
												'Error fetching course data:',
												t
											);
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal7.p = 4 ),
												x( ! 1 ),
												_eLocal7.f( 4 )
											 );
										case 5:
											return _eLocal7.a( 2 );
									}
								}
							},
							_eLocal6,
							null,
							[ [ 0, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args3 ) {
					return e.apply( this, _args3 );
				};
			} )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var e = ! 0;
					return (
						e && U(),
						function () {
							e = ! 1;
							c.resetLessonState();
						}
					 );
				},
				[ d ]
			),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! R && m ) {
						openNotificationWithIcon( p, m );
					}
				},
				[ m ]
			),
			isOpen ? (
				<React.Fragment>
					{ ! chapterId && contextHolder }
					{ React.createElement( il, {
						isOpen,
						loading: R,
						saving: w,
						onClose() {
							if ( ! ( w || R ) ) {
								onClose();
							}
						},
						onSave() {
							if ( v ) {
								B();
							} else {
								W();
							}
						},
						onPreview() {
							if ( u !== null && u !== void 0 && u.preview_url ) {
								window.open( u.preview_url, '_blank' );
							}
						},
						title: ( 0, I18n.__ )(
							'Live Session (Google Meet)',
							'ohmylms'
						),
						saveLabel: v
							? ( 0, I18n.__ )( 'Update Meeting', 'ohmylms' )
							: ( 0, I18n.__ )( 'Create Meeting', 'ohmylms' ),
						saveDisabled:
							( ( l =
								( u === null || u === void 0
									? void 0
									: u.rawDate ) ||
								( u === null || u === void 0
									? void 0
									: u.date ) ),
							! (
								u !== null &&
								u !== void 0 &&
								null !== ( o = u.topic ) &&
								void 0 !== o &&
								o.trim() &&
								u !== null &&
								u !== void 0 &&
								null !== ( i = u.agenda ) &&
								void 0 !== i &&
								i.trim() &&
								P &&
								l &&
								parseInt(
									u === null || u === void 0
										? void 0
										: u.duration,
									10
								) > 0
							) ||
								w ||
								! I ),
						SettingsPanel: ul,
						editor: {
							topic:
								u === null || u === void 0 ? void 0 : u.topic,
							agenda:
								u === null || u === void 0 ? void 0 : u.agenda,
							onChange: V,
							disabled: ! I,
						},
						settings: {
							timezone: P,
							timezoneOptions: co,
							date:
								( u === null || u === void 0
									? void 0
									: u.rawDate ) ||
								( u === null || u === void 0
									? void 0
									: u.date ),
							duration:
								u === null || u === void 0
									? void 0
									: u.duration,
							recordingBlockProps: G.blockProps,
							showRecordingBlock: !! v,
							onChange: V,
							attachments:
								( u === null || u === void 0
									? void 0
									: u.attachments ) || [],
							onAddFiles( e ) {
								return H( {
									attachments: [].concat(
										Bl(
											( u === null || u === void 0
												? void 0
												: u.attachments ) || []
										),
										Bl( e )
									),
								} );
							},
							onRemoveFile( e ) {
								return H( {
									attachments: (
										( u === null || u === void 0
											? void 0
											: u.attachments ) || []
									).filter( function ( t ) {
										return t.id !== e;
									} ),
								} );
							},
							disabled: ! I,
						},
					} ) }
				</React.Fragment>
			) : null
		 );
	};
}
