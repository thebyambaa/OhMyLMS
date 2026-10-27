import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createZoomEditor( readRuntime ) {
	return function ZoomEditor( props ) {
		const {
			Cl,
			El,
			M,
			Pl,
			Qi,
			React,
			T: StoreModule,
			_l,
			b: I18n,
			co,
			g: ReactHooks,
			il,
			jo,
			si,
			sn,
			wl,
			xl,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t,
			isOpen = props.isOpen,
			onClose = props.onClose,
			chapterId = props.chapterId,
			courseId = props.courseId;
		M().noConflict();
		var i;
		var l;
		var c = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var u = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getLesson();
		}, [] );
		var s = ( 0, WordPressData.useSelect )(
			function ( e ) {
				return e( StoreModule.default ).getCourseChaptersContent();
			},
			[ chapterId ]
		);
		var d = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).geSelectedLessonId();
		}, [] );
		var m = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getNotificationMessage();
		}, [] );
		var p = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getNotificationStatus();
		}, [] );
		var f = Pl( ( 0, ReactHooks.useState )( d ), 2 );
		var v = f[ 0 ];
		var h = f[ 1 ];
		var _ = Pl( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var w = _[ 0 ];
		var E = _[ 1 ];
		var S = Pl( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var R = S[ 0 ];
		var x = S[ 1 ];
		Pl( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var k = Pl(
			( 0, ReactHooks.useState )(
				( u === null || u === void 0 ? void 0 : u.timezone ) ||
					sn().tz.guess() ||
					'UTC'
			),
			2
		);
		var j = k[ 0 ];
		var A = k[ 1 ];
		var I = ( 0, Notifications.A )();
		var openNotificationWithIcon = I.openNotificationWithIcon;
		var contextHolder = I.contextHolder;
		var D = true;
		var W = Qi();
		var B = ( u === null || u === void 0 ? void 0 : u.zoom_plan ) || jo;
		var V = function ( e ) {
			var _tLocal,
				n =
					null === ( _tLocal = Object.values( e ) ) ||
					void 0 === _tLocal
						? void 0
						: _tLocal.map( function ( _eLocal ) {
								var _tLocal2;
								return parseInt(
									null !==
										( _tLocal2 =
											_eLocal === null ||
											_eLocal === void 0
												? void 0
												: _eLocal.order_number ) &&
										void 0 !== _tLocal2
										? _tLocal2
										: 0,
									10
								);
							} );
			return n.length ? Math.max( ...Cl( n ) ) : 0;
		};
		var H = ( function () {
			var e = xl(
				El().m( function _eLocal2() {
					var _tLocal3,
						n,
						r,
						_iLocal,
						_lLocal,
						_dLocal,
						_mLocal,
						_pLocal,
						_fLocal,
						_vLocal,
						g,
						y,
						b;
					return El().w(
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
											( _vLocal = V( s.byId ) ),
											( g = _vLocal + 1 ),
											( _eLocal3.n = 2 ),
											c.addZoomClass(
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
													duration:
														u === null ||
														u === void 0
															? void 0
															: u.duration,
													duration_unit: 'min',
													password:
														u === null ||
														u === void 0
															? void 0
															: u.password,
													timezone: j,
													order: g,
													type: 2,
													content_type: 'session',
													platform: 'zoom',
													host_video:
														null !==
															( _tLocal3 =
																u === null ||
																u === void 0
																	? void 0
																	: u.host_video ) &&
														void 0 !== _tLocal3 &&
														_tLocal3,
													participant_video:
														null !==
															( n =
																u === null ||
																u === void 0
																	? void 0
																	: u.participant_video ) &&
														void 0 !== n &&
														n,
													join_before_host:
														null !==
															( r =
																u === null ||
																u === void 0
																	? void 0
																	: u.join_before_host ) &&
														void 0 !== r &&
														r,
													mute_upon_entry:
														null !==
															( _iLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.mute_upon_entry ) &&
														void 0 !== _iLocal &&
														_iLocal,
													auto_record:
														null !==
															( _lLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.auto_record ) &&
														void 0 !== _lLocal &&
														_lLocal,
													zoom_plan:
														null !==
															( _dLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.zoom_plan ) &&
														void 0 !== _dLocal
															? _dLocal
															: jo,
													cover_image_id:
														null !==
															( _mLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.cover_image_id ) &&
														void 0 !== _mLocal
															? _mLocal
															: null,
													cover_video_id:
														null !==
															( _pLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.cover_video_id ) &&
														void 0 !== _pLocal
															? _pLocal
															: null,
													attachments:
														null !==
															( _fLocal =
																u === null ||
																u === void 0
																	? void 0
																	: u.attachments ) &&
														void 0 !== _fLocal
															? _fLocal
															: [],
													course_id: courseId,
												},
												chapterId
											)
										 );
									case 2:
										y = _eLocal3.v;
										h( y );
										_eLocal3.n = 4;
										break;
									case 3:
										_eLocal3.p = 3;
										b = _eLocal3.v;
										showRequestError( b );
									// Falls through to the shared async state cleanup.
									case 4:
										return (
											( _eLocal3.p = 4 ),
											E( ! 1 ),
											_eLocal3.f( 4 )
										 );
									case 5:
										return _eLocal3.a( 2 );
								}
							}
						},
						_eLocal2,
						null,
						[ [ 1, 3, 4, 5 ] ]
					);
				} )
			);
			return function ( ..._args ) {
				return e.apply( this, _args );
			};
		} )();
		var G = ( function () {
			var e = xl(
				El().m( function _eLocal4() {
					var _tLocal4, n, r, o, _iLocal2, _lLocal2, _sLocal;
					return El().w(
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
											c.updateZoomClass(
												v,
												_l(
													_l( {}, u ),
													{},
													{
														duration_unit: 'min',
														type: 2,
														content_type: 'session',
														platform: 'zoom',
														date:
															( u === null ||
															u === void 0
																? void 0
																: u.rawDate ) ||
															( u === null ||
															u === void 0
																? void 0
																: u.date ),
														timezone: j,
														host_video:
															null !==
																( _tLocal4 =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.host_video ) &&
															void 0 !==
																_tLocal4 &&
															_tLocal4,
														participant_video:
															null !==
																( n =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.participant_video ) &&
															void 0 !== n &&
															n,
														join_before_host:
															null !==
																( r =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.join_before_host ) &&
															void 0 !== r &&
															r,
														mute_upon_entry:
															null !==
																( o =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.mute_upon_entry ) &&
															void 0 !== o &&
															o,
														auto_record:
															null !==
																( _iLocal2 =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.auto_record ) &&
															void 0 !==
																_iLocal2 &&
															_iLocal2,
														zoom_plan:
															null !==
																( _lLocal2 =
																	u ===
																		null ||
																	u === void 0
																		? void 0
																		: u.zoom_plan ) &&
															void 0 !== _lLocal2
																? _lLocal2
																: jo,
													}
												),
												chapterId
											)
										 );
									case 2:
										_eLocal5.n = 4;
										break;
									case 3:
										_eLocal5.p = 3;
										_sLocal = _eLocal5.v;
										showRequestError( _sLocal );
									// Falls through to the shared async state cleanup.
									case 4:
										return (
											( _eLocal5.p = 4 ),
											E( ! 1 ),
											_eLocal5.f( 4 )
										 );
									case 5:
										return _eLocal5.a( 2 );
								}
							}
						},
						_eLocal4,
						null,
						[ [ 1, 3, 4, 5 ] ]
					);
				} )
			);
			return function ( ..._args2 ) {
				return e.apply( this, _args2 );
			};
		} )();
		var U = ( 0, ReactHooks.useCallback )(
			function ( e, _tLocal5 ) {
				( () => {
					if ( 'timezone' === e ) {
						return (
							A( _tLocal5 ),
							c.setLesson(
								_l(
									_l( {}, u ),
									{},
									{
										timezone: _tLocal5,
									}
								)
							)
						 );
					}
					if ( 'date' === e ) {
						return c.setLesson(
							_l(
								_l( {}, u ),
								{},
								{
									date: _tLocal5,
									rawDate: _tLocal5,
								}
							)
						);
					}
					return c.setLesson(
						_l( _l( {}, u ), {}, wl( {}, e, _tLocal5 ) )
					);
				} )();
			},
			[ u ]
		);
		var q = ( 0, ReactHooks.useCallback )(
			function ( e ) {
				c.setLesson( _l( _l( {}, u ), e ) );
			},
			[ u ]
		);
		var Y = si( {
			plan: B,
			title: u === null || u === void 0 ? void 0 : u.topic,
			initialAutoRecord:
				null !==
					( t =
						u === null || u === void 0 ? void 0 : u.auto_record ) &&
				void 0 !== t &&
				t,
			initialState:
				( u === null || u === void 0 ? void 0 : u.recording_state ) ||
				'empty',
			initialSource:
				( u === null || u === void 0 ? void 0 : u.recording_source ) ||
				void 0,
			duration:
				u === null || u === void 0 ? void 0 : u.recording_duration,
			onAttach( e ) {
				var _tLocal6 = e.source,
					n = e.url;
				return q( {
					recording_source: _tLocal6,
					recording_url: n || '',
					recording_state: 'attached',
				} );
			},
			onDetach() {
				return q( {
					recording_source: '',
					recording_url: '',
					recording_state: 'empty',
				} );
			},
			pickMedia() {
				return new Promise( function ( e ) {
					W( {
						type: 'video',
						title: ( 0, I18n.__ )(
							'Select or Upload Recording',
							'ohmylms'
						),
						onSelect( _tLocal7 ) {
							return e( {
								id:
									_tLocal7 === null || _tLocal7 === void 0
										? void 0
										: _tLocal7.id,
								url:
									_tLocal7 === null || _tLocal7 === void 0
										? void 0
										: _tLocal7.url,
								fileName:
									( _tLocal7 === null || _tLocal7 === void 0
										? void 0
										: _tLocal7.filename ) ||
									( _tLocal7 === null || _tLocal7 === void 0
										? void 0
										: _tLocal7.title ) ||
									( _tLocal7 === null || _tLocal7 === void 0
										? void 0
										: _tLocal7.name ),
							} );
						},
						onClose() {
							return e( null );
						},
					} );
				} );
			},
		} );
		var Q = ( function () {
			var e = xl(
				El().m( function _eLocal6() {
					var _tLocal8;
					return El().w(
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
											c.getZoomClass( d )
										 );
									case 2:
										_eLocal7.n = 4;
										break;
									case 3:
										_eLocal7.p = 3;
										_tLocal8 = _eLocal7.v;
										showRequestError(
											'Error fetching course data:',
											_tLocal8
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
						e && Q(),
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
								G();
							} else {
								H();
							}
						},
						onPreview() {
							if ( u !== null && u !== void 0 && u.preview_url ) {
								window.open( u.preview_url, '_blank' );
							}
						},
						title: ( 0, I18n.__ )(
							'Live Session (Zoom)',
							'ohmylms'
						),
						saveLabel: v
							? ( 0, I18n.__ )( 'Update Meeting', 'ohmylms' )
							: ( 0, I18n.__ )( 'Create Meeting', 'ohmylms' ),
						saveDisabled:
							! (
								u !== null &&
								u !== void 0 &&
								null !== ( i = u.topic ) &&
								void 0 !== i &&
								i.trim() &&
								u !== null &&
								u !== void 0 &&
								null !== ( l = u.agenda ) &&
								void 0 !== l &&
								l.trim() &&
								j &&
								u !== null &&
								u !== void 0 &&
								u.date &&
								u !== null &&
								u !== void 0 &&
								u.duration
							) ||
							w ||
							! D,
						editor: {
							topic:
								u === null || u === void 0 ? void 0 : u.topic,
							agenda:
								u === null || u === void 0 ? void 0 : u.agenda,
							onChange: U,
							disabled: ! D,
							cover: {
								imageSrc:
									u === null || u === void 0
										? void 0
										: u.cover_image_src,
								videoSrc:
									u === null || u === void 0
										? void 0
										: u.cover_video_src,
								onChangeMany: q,
							},
						},
						settings: {
							timezone: j,
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
							password:
								u === null || u === void 0
									? void 0
									: u.password,
							toggles: {
								mute_upon_entry:
									u === null || u === void 0
										? void 0
										: u.mute_upon_entry,
								join_before_host:
									u === null || u === void 0
										? void 0
										: u.join_before_host,
								host_video:
									u === null || u === void 0
										? void 0
										: u.host_video,
								participant_video:
									u === null || u === void 0
										? void 0
										: u.participant_video,
							},
							autoRecord:
								u === null || u === void 0
									? void 0
									: u.auto_record,
							plan: B,
							recordingBlockProps: Y.blockProps,
							showRecordingBlock: !! v,
							onChange: U,
							attachments:
								( u === null || u === void 0
									? void 0
									: u.attachments ) || [],
							onAddFiles( e ) {
								return q( {
									attachments: [].concat(
										Cl(
											( u === null || u === void 0
												? void 0
												: u.attachments ) || []
										),
										Cl( e )
									),
								} );
							},
							onRemoveFile( e ) {
								return q( {
									attachments: (
										( u === null || u === void 0
											? void 0
											: u.attachments ) || []
									).filter( function ( _tLocal9 ) {
										return _tLocal9.id !== e;
									} ),
								} );
							},
							disabled: ! D,
						},
					} ) }
				</React.Fragment>
			) : null
		 );
	};
}
