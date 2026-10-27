import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { NativeLessonRoute } from './NativeLessonRoute';
export function createLessonEditor( readRuntime ) {
	const LegacyLessonEditor = createLegacyLessonEditor( readRuntime );
	return function LessonEditor( props ) {
		const { y: WordPressData, T: StoreModule } = readRuntime();
		const id = WordPressData.useSelect(
			( select ) =>
				props.id || select( StoreModule.default ).geSelectedLessonId(),
			[ props.id ]
		);
		const { setLesson } = WordPressData.useDispatch( StoreModule.default );
		return id ? (
			<NativeLessonRoute
				{ ...props }
				id={ id }
				onLessonLoaded={ setLesson }
				legacyEditor={ LegacyLessonEditor }
			/>
		) : (
			<LegacyLessonEditor { ...props } />
		);
	};
}
function createLegacyLessonEditor( readRuntime ) {
	return function LegacyLessonEditor( props ) {
		const {
			$r,
			Br,
			I: Controls,
			React,
			T: StoreModule,
			Ur,
			Vr,
			Wr: LearningEditorHeader,
			Yr,
			Zr,
			b: I18n,
			g: ReactHooks,
			kr: LessonContent,
			nr: LessonSettings,
			qr,
			y: WordPressData,
			z: Notifications,
		} = readRuntime();
		var t = ( 0, Notifications.A )(),
			openNotificationWithIcon = t.openNotificationWithIcon,
			contextHolder = t.contextHolder,
			a = props.id,
			chapterId = props.chapterId,
			setOpenModal = props.setOpenModal,
			setLocalData = props.setLocalData,
			c =
				( props.handleAutomation,
				( 0, WordPressData.useDispatch )( StoreModule.default ) ),
			u = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getLesson();
			}, [] ),
			s = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).geSelectedLessonId();
			}, [] ),
			d = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			}, [] ),
			m = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			}, [] ),
			p = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getCourseChaptersContent();
				},
				[ chapterId ]
			),
			f = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			getLesson = f.getLesson,
			resetLessonState = f.resetLessonState,
			w = $r( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ],
			S = w[ 1 ],
			R = $r(
				( 0, ReactHooks.useState )( {
					description: '',
				} ),
				2
			),
			x = R[ 0 ],
			C = R[ 1 ];
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! E && d ) {
					openNotificationWithIcon( m, d );
				}
			},
			[ d ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				c.setSelectedLessonId( a );
			},
			[ a ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				var e = ( function () {
					var _eLocal,
						_tLocal =
							( ( _eLocal = Yr().m( function _eLocal2() {
								var _tLocal2;
								return Yr().w(
									function ( _eLocal3 ) {
										for (;;) {
											switch (
												( _eLocal3.p = _eLocal3.n )
											) {
												case 0:
													if (
														( ( _eLocal3.p = 0 ),
														s )
													) {
														_eLocal3.n = 1;
														break;
													}
													return _eLocal3.a( 2 );
												case 1:
													return (
														S( ! 0 ),
														( _eLocal3.n = 2 ),
														getLesson( s )
													 );
												case 2:
													_eLocal3.n = 4;
													break;
												case 3:
													_eLocal3.p = 3;
													_tLocal2 = _eLocal3.v;
													showRequestError(
														'Error fetching course data:',
														_tLocal2
													);
												// Falls through to the shared async state cleanup.
												case 4:
													return (
														( _eLocal3.p = 4 ),
														S( ! 1 ),
														_eLocal3.f( 4 )
													 );
												case 5:
													return _eLocal3.a( 2 );
											}
										}
									},
									_eLocal2,
									null,
									[ [ 0, 3, 4, 5 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal3 = this,
									n = _args;
								return new Promise( function ( r, _aLocal ) {
									var o = _eLocal.apply( _tLocal3, n );
									function i( _eLocal4 ) {
										Zr(
											o,
											r,
											_aLocal,
											i,
											l,
											'next',
											_eLocal4
										);
									}
									function l( _eLocal5 ) {
										Zr(
											o,
											r,
											_aLocal,
											i,
											l,
											'throw',
											_eLocal5
										);
									}
									i( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return _tLocal.apply( this, _args2 );
					};
				} )();
				return (
					e(),
					function () {
						resetLessonState();
					}
				 );
			},
			[ s ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( u ) {
					C( {
						description:
							( x === null || x === void 0
								? void 0
								: x.description ) ||
							u.description ||
							'',
					} );
				}
			},
			[ u ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( setLocalData ) {
					setLocalData( x );
				}
			},
			[ x ]
		);
		var P = function () {
			if (
				( ( u.description = x.description ),
				c.updateLesson( u === null || u === void 0 ? void 0 : u.id, u ),
				chapterId )
			) {
				var e = p === null || p === void 0 ? void 0 : p.byId;
				e[ u === null || u === void 0 ? void 0 : u.id ] = u;
				var _tLocal4 = Object.values( e ).filter(
					function ( _eLocal6 ) {
						return void 0 !==
							( _eLocal6 === null || _eLocal6 === void 0
								? void 0
								: _eLocal6.chapterId )
							? ( _eLocal6 === null || _eLocal6 === void 0
									? void 0
									: _eLocal6.chapterId ) === chapterId
							: ( _eLocal6 === null || _eLocal6 === void 0
									? void 0
									: _eLocal6.id ) ===
									( u === null || u === void 0
										? void 0
										: u.id );
					}
				);
				c.setContentsToChapter( chapterId, _tLocal4 );
			}
		};
		return (
			<React.Fragment>
				{ ! chapterId && contextHolder }
				{ ! E || ( u !== null && u !== void 0 && u.id ) ? (
					<React.Fragment>
						{ ! chapterId && (
							<LearningEditorHeader
								title={ ( 0, I18n.__ )(
									'Lesson editor',
									'ohmylms'
								) }
								redirection={ '/content-hub/lessons' }
								className={
									'ohmylms-lesson-editor-page-header'
								}
								rightContent={
									<React.Fragment>
										<Controls.ButtonWP
											onClick={ function () {
												if (
													u !== null &&
													u !== void 0 &&
													u.preview_url
												) {
													window.open(
														u === null ||
															u === void 0
															? void 0
															: u.preview_url,
														'_blank'
													);
												}
											} }
											icon={ <Br /> }
											iconPosition={ 'right' }
											className={ 'ohmylms-preview-btn' }
										>
											{ ( 0, I18n.__ )(
												'Preview',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											onClick={ P }
											className={
												'ohmylms-lesson-save-btn'
											}
										>
											{ ( 0, I18n.__ )(
												'Save',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</React.Fragment>
								}
							/>
						) }
						<div
							className={ Vr()(
								'ohmylms-lesson-editor-wrapper',
								! chapterId && 'ohmylms-lesson-editor-page'
							) }
						>
							<LessonContent
								lesson={ u }
								settings={
									<React.Fragment>
										<LessonSettings
											setOpenModal={ setOpenModal }
											lesson={ u }
											chapterId={ chapterId }
										/>
										{ window.ohmylms.extensions.renderSlot(
											'/lesson-edit/:id',
											{
												id: a,
												route: '/lesson-edit/:id',
												hash: window.location.hash,
											},
											'editor-panel'
										) }
									</React.Fragment>
								}
								handleInputChange={ function ( e ) {
									c.setLesson( {
										name: e,
									} );
								} }
								handleEditorContentChange={ function ( e ) {
									C( function ( _tLocal5 ) {
										return Ur(
											Ur( {}, _tLocal5 ),
											{},
											{
												description: e,
											}
										);
									} );
								} }
								handleUploadComplete={ function (
									e,
									_tLocal6
								) {
									var n = {};
									switch ( _tLocal6 ) {
										case 'video':
											c.setLesson(
												Ur(
													Ur( {}, u ),
													{},
													{
														video_id: e.id,
														video_src: e.url,
														external_url: '',
													}
												)
											);
											n = {
												video_id: e.id,
												video_src: e.url,
												external_url: '',
											};
											break;
										case 'audio':
											c.setLesson(
												Ur(
													Ur( {}, u ),
													{},
													{
														audio_id: e.id,
														audio_src: e.url,
														external_url: '',
													}
												)
											);
											n = {
												audio_id: e.id,
												audio_src: e.url,
												external_url: '',
											};
											break;
										default:
											c.setLesson(
												Ur(
													Ur( {}, u ),
													{},
													{
														image_id: e.id,
														image_src: e.url,
													}
												)
											);
											n = {
												image_id: e.id,
												image_src: e.url,
											};
									}
									c.updateLessonWithoutNotice(
										u === null || u === void 0
											? void 0
											: u.id,
										Ur( Ur( {}, u ), n )
									);
								} }
								handleRemoveMedia={ function ( e ) {
									var _tLocal7 = Ur(
										Ur( {}, u ),
										{},
										qr(
											qr(
												qr(
													{},
													''.concat( e, '_id' ),
													null
												),
												''.concat( e, '_src' ),
												null
											),
											'external_url',
											null
										)
									);
									c.setLesson( _tLocal7 );
								} }
								setOpenModal={ setOpenModal }
								saveLesson={ P }
								chapterId={ chapterId }
								onExternalUploadComplete={ function ( e ) {
									c.setLesson(
										Ur(
											Ur( {}, u ),
											{},
											{
												external_url: e.url,
												video_id: null,
												video_src: '',
												audio_id: null,
												audio_src: '',
											}
										)
									);
									c.updateLessonWithoutNotice(
										u === null || u === void 0
											? void 0
											: u.id,
										Ur(
											Ur( {}, u ),
											{},
											{
												external_url: e.url,
												video_id: null,
												video_src: '',
												audio_id: null,
												audio_src: '',
											}
										)
									);
								} }
							/>
						</div>
					</React.Fragment>
				) : (
					<Controls.SkeletonWP rows={ 10 } />
				) }
			</React.Fragment>
		);
	};
}
