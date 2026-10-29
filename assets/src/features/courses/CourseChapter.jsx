import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { nativeLessonHref } from '../content-hub/editorNavigation.mjs';
import { Skeleton } from '../../shared/skeleton';
export function createCourseChapter( readRuntime ) {
	return function CourseChapter( props ) {
		const {
			$l,
			Al,
			E,
			Jl,
			Kl,
			React,
			T: StoreModule,
			Ul,
			Zl,
			f: Router,
			g: ReactHooks,
			ga,
			ie,
			oo,
			ot,
			ql,
			wt,
			y: WordPressData,
		} = readRuntime();
		var t,
			chapter = props.chapter,
			chapterId = props.chapterId,
			courseId = props.courseId,
			onDelete = props.onDelete,
			chapterIndex = props.chapterIndex,
			isActive = props.isActive,
			onExpand = props.onExpand,
			setAutoSave = props.setAutoSave,
			handleAutomation = props.handleAutomation,
			handleIntegration = props.handleIntegration,
			setIsDraggableItem = props.setIsDraggableItem,
			setIsSaved = props.setIsSaved,
			activeIndex = props.activeIndex,
			b = ( props.showEdit, props.setShowEdit, props.handleSaveName ),
			w = ( 0, Router.g )().id,
			S = false,
			R = ( 0, WordPressData.useDispatch )( 'ohmylms/store' ),
			x = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourse();
			}, [] ),
			C = $l(
				( 0, ReactHooks.useState )( chapter.name || chapter.title ),
				2
			),
			P = ( C[ 0 ], C[ 1 ] ),
			O = $l(
				( 0, ReactHooks.useState )( chapter.description || '' ),
				2
			),
			k = O[ 0 ],
			j = O[ 1 ],
			A = $l(
				( 0, ReactHooks.useState )( 0 === chapterIndex ? [ '1' ] : [] ),
				2
			),
			M = A[ 0 ],
			I = A[ 1 ],
			F = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			N = F[ 0 ],
			D = F[ 1 ],
			W = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			z = W[ 0 ],
			B = W[ 1 ],
			L = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			V = L[ 0 ],
			H = L[ 1 ],
			G = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			U = G[ 0 ],
			q = G[ 1 ],
			Y = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			Q = Y[ 0 ],
			Z = Y[ 1 ],
			$ = $l( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			K = $[ 0 ],
			J = $[ 1 ],
			X = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( 'ohmylms/store' ).getCourseChaptersContent();
				},
				[ chapterId ]
			),
			ee = S
				? ( null === ( t = x[ w - 1 ] ) ||
					void 0 === t ||
					null ===
						( t = t.chapters.find( function ( e ) {
							return e.id === chapterId;
						} ) ) ||
					void 0 === t
						? void 0
						: t.content ) || []
				: X,
			te = ( 0, ReactHooks.useRef )( null ),
			ne = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getChapterLastIndex();
			}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				if (
					chapterIndex ===
					( ne === null || ne === void 0 ? void 0 : ne.lastIndex )
				) {
					I( function ( e ) {
						return e.includes( '1' ) ? [] : [ '1' ];
					} );
				}
			},
			[ ne ]
		);
		var re = ( 0, ReactHooks.useCallback )(
			E()( function ( e, _tLocal ) {
				R.updateChapterFields( e, _tLocal );
			}, 300 ),
			[ R ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( k !== ( chapter.description || '' ) ) {
					re( chapter.id, {
						description: k,
					} );
				}
			},
			[ k, chapter.id, re ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				var e = ! 0;
				return (
					e &&
						M.length > 0 &&
						! S &&
						R.getLessonByChapterId( chapterId ),
					function () {
						e = ! 1;
					}
				 );
			},
			[ chapterId, M, S ]
		);
		var ae = function ( e ) {
				var _tLocal2,
					n =
						null === ( _tLocal2 = Object.values( e ) ) ||
						void 0 === _tLocal2
							? void 0
							: _tLocal2.map( function ( _eLocal ) {
									var _tLocal3;
									return parseInt(
										null !==
											( _tLocal3 =
												_eLocal === null ||
												_eLocal === void 0
													? void 0
													: _eLocal.order_number ) &&
											void 0 !== _tLocal3
											? _tLocal3
											: 0,
										10
									);
								} );
				return n.length
					? Math.max(
							...( function ( _eLocal2 ) {
								return (
									( function ( _eLocal3 ) {
										if ( Array.isArray( _eLocal3 ) ) {
											return Jl( _eLocal3 );
										}
									} )( _eLocal2 ) ||
									( function ( _eLocal4 ) {
										if (
											( 'undefined' !== typeof Symbol &&
												( ( _nullableValue ) =>
													_nullableValue !== null &&
													_nullableValue !== void 0 )(
													_eLocal4[ Symbol.iterator ]
												) ) ||
											( ( _nullableValue2 ) =>
												_nullableValue2 !== null &&
												_nullableValue2 !== void 0 )(
												_eLocal4[ '@@iterator' ]
											)
										) {
											return Array.from( _eLocal4 );
										}
									} )( _eLocal2 ) ||
									Kl( _eLocal2 ) ||
									( function () {
										throw new TypeError(
											'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
										);
									} )()
								);
							} )( n )
						)
					: 0;
			},
			oe = ( function () {
				var e = Zl(
					ql().m( function _eLocal5( ..._args3 ) {
						let [ _tLocal4 ] = _args3;
						var n,
							r,
							a,
							o,
							i,
							l = _args3;
						return ql().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											return (
												( n =
													l.length > 1 &&
													void 0 !== l[ 1 ]
														? l[ 1 ]
														: 'text' ),
												( _eLocal6.p = 1 ),
												B( ! 0 ),
												( r = ae( ee.byId ) ),
												( a =
													parseInt(
														r !== null &&
															r !== void 0
															? r
															: 0
													) + 1 ),
												( _eLocal6.n = 2 ),
												R.addLesson(
													{
														type: n,
														name: '',
														status: 'publish',
														order_number: a,
													},
													_tLocal4
												)
											 );
										case 2:
											if ( ( o = _eLocal6.v ) ) {
												{
													D( ! 1 );
													if (
														[
															'text',
															'audio',
															'video',
														].includes( n )
													) {
														window.location.assign(
															nativeLessonHref(
																o,
																window.location.hash.slice(
																	1
																)
															)
														);
													} else {
														{
															H( ! 0 );
															R.setSelectedLessonId(
																o
															);
														}
													}
												}
											}
											_eLocal6.n = 4;
											break;
										case 3:
											_eLocal6.p = 3;
											i = _eLocal6.v;
											showRequestError( i );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal6.p = 4 ),
												B( ! 1 ),
												_eLocal6.f( 4 )
											 );
										case 5:
											return _eLocal6.a( 2 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 1, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args4 ) {
					return e.apply( this, _args4 );
				};
			} )(),
			le = ( function () {
				var e = Zl(
					ql().m( function _eLocal7() {
						var _tLocal6, n, a;
						return ql().w( function ( _eLocal8 ) {
							for (;;) {
								switch ( _eLocal8.n ) {
									case 0:
										return (
											B( ! 0 ),
											( _tLocal6 = ae( ee.byId ) ),
											( n = _tLocal6 + 1 ),
											( _eLocal8.n = 1 ),
											R.addQuiz(
												{
													name: 'Untitled',
													type: 'quiz',
													description: '',
													order_number: n,
												},
												chapterId
											)
										 );
									case 1:
										a = _eLocal8.v;
										B( ! 1 );
										D( ! 1 );
										R.setIsOpenQuizBuilder( ! 0 );
										R.setSelectedQuizId( a );
									// Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal8.a( 2 );
								}
							}
						}, _eLocal7 );
					} )
				);
				return function ( ..._args ) {
					return e.apply( this, _args );
				};
			} )(),
			ce = ( function () {
				var e = Zl(
					ql().m( function _eLocal9() {
						var _tLocal7, n, a;
						return ql().w( function ( _eLocal0 ) {
							for (;;) {
								switch ( _eLocal0.n ) {
									case 0:
										return (
											B( ! 0 ),
											( _tLocal7 = ae( ee.byId ) ),
											( n = parseInt( _tLocal7 ) + 1 ),
											( _eLocal0.n = 1 ),
											R.addAssignment(
												{
													type: 'assignment',
													name: 'Untitled',
													status: 'publish',
													order_number: n,
												},
												chapterId
											)
										 );
									case 1:
										a = _eLocal0.v;
										B( ! 1 );
										D( ! 1 );
										q( ! 0 );
										R.setSelectedAssignmentId( a );
									// Falls through to the shared async state cleanup.
									// Falls through to the shared async state cleanup.
									case 2:
										return _eLocal0.a( 2 );
								}
							}
						}, _eLocal9 );
					} )
				);
				return function ( ..._args2 ) {
					return e.apply( this, _args2 );
				};
			} )(),
			ue = ( 0, ReactHooks.useCallback )( function ( e ) {
				if ( e.target.closest( '.ant-collapse-arrow' ) ) {
					return (
						I( function ( _eLocal1 ) {
							return _eLocal1.includes( '1' ) ? [] : [ '1' ];
						} ),
						void onExpand( isActive ? null : chapterId )
					 );
				}
				I( [ '1' ] );
				onExpand( chapterId );
			}, [] );
		return (
			<React.Fragment>
				{ activeIndex === chapterId && (
					<div className={ 'ohmylms-single-chapter-content-item' }>
						{ React.createElement( ie, {
							name: chapter.name || chapter.title,
							description: k,
							setName( e ) {
								P( e );
								R.updateChapterFields( chapterId, {
									name: e,
								} );
							},
							setDescription: j,
							chapterId,
							courseId,
							onDelete,
							isActive,
							setAutoSave,
							tabKey: '1',
							handleOpenChapter: ue,
							setIsDraggableItem,
							setIsSaved,
							handleSaveName: b,
						} ) }
						<React.Suspense
							fallback={ <Skeleton active={ ! 0 } /> }
						>
							{ React.createElement( ot, {
								chapter,
								contents: ee,
								handleCreateLesson() {
									te.current = document.activeElement;
									D( ! 0 );
								},
								handleLessonEdit( e, _tLocal8, n ) {
									if (
										[ 'text', 'audio', 'video' ].includes(
											_tLocal8
										)
									) {
										window.location.assign(
											nativeLessonHref(
												e,
												window.location.hash.slice( 1 )
											)
										);
										return;
									}
									if ( 'quiz' === _tLocal8 ) {
										{
											R.setSelectedQuizId( e );
											R.setIsOpenQuizBuilder( ! 0 );
										}
									} else {
										( () => {
											if ( 'assignment' === _tLocal8 ) {
												return (
													q( ! 0 ),
													R.setSelectedAssignmentId(
														e
													)
												 );
											}
											if ( 'session' === _tLocal8 ) {
												return (
													R.setSelectedLessonId( e ),
													'zoom' === n
														? ( J( ! 1 ), Z( ! 0 ) )
														: 'googlemeet' === n &&
															( Z( ! 1 ),
															J( ! 0 ) )
												 );
											}
											return (
												H( ! 0 ),
												R.setSelectedLessonId( e )
											 );
										} )();
									}
								},
								courseId,
								handleAutomation,
								handleIntegration,
							} ) }
						</React.Suspense>
					</div>
				) }
				{ N &&
					React.createElement( wt, {
						openModal: N,
						setOpenModal: D,
						handleCreateLesson( e ) {
							if ( 'assignment' === e ) {
								ce();
							} else if (
								chapter !== null &&
								chapter !== void 0 &&
								chapter.id
							) {
								oe(
									chapter === null || chapter === void 0
										? void 0
										: chapter.id,
									e
								);
							}
						},
						handleCreateQuiz: le,
						loader: z,
						lastFocusedElement: te,
						handleOpenZoom() {
							D( ! 1 );
							J( ! 1 );
							Z( ! 0 );
						},
						handleOpenGoogleMeet() {
							D( ! 1 );
							Z( ! 1 );
							J( ! 0 );
						},
						chapterId,
						courseId,
					} ) }
				{ Q && (
					<Al
						isOpen={ Q }
						onClose={ function () {
							return Z( ! 1 );
						} }
						chapterId={ chapterId }
						courseId={ courseId }
					/>
				) }
				{ K && (
					<Ul
						isOpen={ K }
						onClose={ function () {
							J( ! 1 );
							R.setSelectedLessonId( null );
						} }
						chapterId={ chapterId }
						courseId={ courseId }
					/>
				) }
				{ V &&
					React.createElement( ga, {
						openModal: V,
						setOpenModal: H,
						chapterId,
						handleAutomation,
					} ) }
				{ U &&
					React.createElement( oo, {
						openModal: U,
						setOpenModal: q,
						chapterId,
						handleAutomation,
					} ) }
			</React.Fragment>
		);
	};
}
