/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createLessonSettings( readRuntime ) {
	return function LessonSettings( props ) {
		const {
			$n,
			Dn: DownloadResources,
			Gn: DeleteLearningItem,
			He,
			Jn,
			Kt,
			Pn: DripSettings,
			Qn,
			React,
			Rt,
			T: StoreModule,
			Xn,
			b: I18n,
			cn: PrerequisiteSettings,
			g: ReactHooks,
			l,
			qn,
			sn,
			y: WordPressData,
			zt,
		} = readRuntime();
		var t;
		var n;
		var r;
		var a;
		var o;
		var i;
		var c;
		var u;
		var lesson = props.lesson;
		var chapterId = props.chapterId;
		var setOpenModal = props.setOpenModal;
		var byId = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getCourseChapters();
		}, [] ).byId;
		var v = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getCourseChaptersContent();
		}, [] );
		var h = byId && byId[ chapterId ];
		var _ = ( function ( e, _tLocal ) {
			return (
				( function ( _eLocal ) {
					if ( Array.isArray( _eLocal ) ) {
						return _eLocal;
					}
				} )( e ) ||
				( function ( _eLocal2, _tLocal2 ) {
					var _nLocal =
						_eLocal2 === null || _eLocal2 === void 0
							? null
							: ( 'undefined' !== typeof Symbol &&
									_eLocal2[ Symbol.iterator ] ) ||
								_eLocal2[ '@@iterator' ];
					if ( _nLocal !== null && _nLocal !== void 0 ) {
						var _rLocal,
							_aLocal,
							_oLocal,
							_iLocal,
							_lLocal = [],
							_cLocal = ! 0,
							_uLocal = ! 1;
						try {
							if (
								( ( _oLocal = ( _nLocal =
									_nLocal.call( _eLocal2 ) ).next ),
								0 === _tLocal2 )
							) {
								if ( Object( _nLocal ) !== _nLocal ) {
									return;
								}
								_cLocal = ! 1;
							} else {
								for (
									;
									! ( _cLocal = ( _rLocal =
										_oLocal.call( _nLocal ) ).done ) &&
									( _lLocal.push( _rLocal.value ),
									_lLocal.length !== _tLocal2 );
									_cLocal = ! 0
								) {}
							}
						} catch ( _eLocal3 ) {
							_uLocal = ! 0;
							_aLocal = _eLocal3;
						} finally {
							try {
								if (
									! _cLocal &&
									( ( _nullableValue ) =>
										_nullableValue !== null &&
										_nullableValue !== void 0 )(
										_nLocal.return
									) &&
									( ( _iLocal = _nLocal.return() ),
									Object( _iLocal ) !== _iLocal )
								) {
									return;
								}
							} finally {
								if ( _uLocal ) {
									throw _aLocal;
								}
							}
						}
						return _lLocal;
					}
				} )( e, _tLocal ) ||
				Xn( e, _tLocal ) ||
				( function () {
					throw new TypeError(
						'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
					);
				} )()
			);
		} )( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var w = _[ 0 ];
		var E = _[ 1 ];
		var S = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var R =
			'cohort-based' ===
			( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourseType();
			}, [] );
		var x = ( function () {
			var e,
				_tLocal3 =
					( ( e = $n().m( function _eLocal4( ..._args ) {
						var _tLocal4,
							_nLocal2,
							_rLocal2,
							_aLocal2,
							_oLocal2,
							_iLocal2,
							_cLocal2 = _args;
						return $n().w( function ( _eLocal5 ) {
							for (;;) {
								switch ( _eLocal5.n ) {
									case 0:
										return (
											( _tLocal4 =
												_cLocal2.length > 0 &&
												void 0 !== _cLocal2[ 0 ]
													? _cLocal2[ 0 ]
													: '' ),
											( _eLocal5.n = 1 ),
											l()( {
												path: '/ohmylms/v1/chapters/'
													.concat(
														chapterId,
														'/search-contents?term='
													)
													.concat( _tLocal4 ),
												method: 'GET',
												headers: {
													'Content-Type':
														'application/json',
												},
											} )
										 );
									case 1:
										return (
											( _nLocal2 = _eLocal5.v ),
											( _rLocal2 =
												h === null || h === void 0
													? void 0
													: h.content.map( String ) ),
											( _aLocal2 = _rLocal2.indexOf(
												String( lesson.id )
											) ),
											( _oLocal2 = _nLocal2.data.filter(
												function ( _eLocal6 ) {
													return (
														String(
															_eLocal6 === null ||
																_eLocal6 ===
																	void 0
																? void 0
																: _eLocal6.value
														) !==
															String(
																lesson.id
															) &&
														_rLocal2.indexOf(
															String(
																_eLocal6 ===
																	null ||
																	_eLocal6 ===
																		void 0
																	? void 0
																	: _eLocal6.value
															)
														) < _aLocal2
													);
												}
											) ),
											( _iLocal2 = _oLocal2.filter(
												function ( _eLocal7 ) {
													var _tLocal5,
														_nLocal3,
														_rLocal3,
														_aLocal3,
														_oLocal3;
													return ! (
														( () => {
															if (
																null !==
																	( _tLocal5 =
																		v ===
																			null ||
																		v ===
																			void 0
																			? void 0
																			: v.byId ) &&
																void 0 !==
																	_tLocal5
															) {
																return _tLocal5;
															}
															if (
																v === null ||
																v === void 0
															) {
																return void 0;
															}
															return v.byId[
																_eLocal7.value
															];
														} )() &&
														v !== null &&
														v !== void 0 &&
														null !==
															( _nLocal3 =
																v.byId[
																	_eLocal7
																		.value
																] ) &&
														void 0 !== _nLocal3 &&
														_nLocal3.prerequisites &&
														v !== null &&
														v !== void 0 &&
														null !==
															( _rLocal3 =
																v.byId[
																	_eLocal7
																		.value
																] ) &&
														void 0 !== _rLocal3 &&
														null !==
															( _rLocal3 =
																_rLocal3.prerequisites ) &&
														void 0 !== _rLocal3 &&
														_rLocal3.enable &&
														( v === null ||
														v === void 0 ||
														null ===
															( _aLocal3 =
																v.byId[
																	_eLocal7
																		.value
																] ) ||
														void 0 === _aLocal3 ||
														null ===
															( _aLocal3 =
																_aLocal3.prerequisites ) ||
														void 0 === _aLocal3 ||
														null ===
															( _aLocal3 =
																_aLocal3.data ) ||
														void 0 === _aLocal3
															? void 0
															: _aLocal3.length ) >
															0 &&
														( v === null ||
														v === void 0 ||
														null ===
															( _oLocal3 =
																v.byId[
																	_eLocal7
																		.value
																] ) ||
														void 0 === _oLocal3 ||
														null ===
															( _oLocal3 =
																_oLocal3.prerequisites ) ||
														void 0 === _oLocal3
															? void 0
															: _oLocal3.data
														).some(
															function (
																_eLocal8
															) {
																return (
																	String(
																		_eLocal8.value
																	) ===
																	String(
																		lesson.id
																	)
																);
															}
														)
													);
												}
											) ),
											_eLocal5.a( 2, {
												data: _iLocal2,
											} )
										 );
								}
							}
						}, _eLocal4 );
					} ) ),
					function ( ..._args2 ) {
						var _tLocal6 = this,
							_nLocal4 = _args2;
						return new Promise( function ( _rLocal4, _aLocal4 ) {
							var _oLocal4 = e.apply( _tLocal6, _nLocal4 );
							function _iLocal3( _eLocal9 ) {
								Jn(
									_oLocal4,
									_rLocal4,
									_aLocal4,
									_iLocal3,
									_lLocal2,
									'next',
									_eLocal9
								);
							}
							function _lLocal2( _eLocal0 ) {
								Jn(
									_oLocal4,
									_rLocal4,
									_aLocal4,
									_iLocal3,
									_lLocal2,
									'throw',
									_eLocal0
								);
							}
							_iLocal3( void 0 );
						} );
					} );
			return function ( ..._args3 ) {
				return _tLocal3.apply( this, _args3 );
			};
		} )();
		var C = ( 0, ReactHooks.useCallback )(
			function () {
				if ( lesson !== null && lesson !== void 0 && lesson.id ) {
					{
						S.deleteLessonFromChapter(
							lesson === null || lesson === void 0
								? void 0
								: lesson.id,
							chapterId
						);
						setOpenModal( ! 1 );
					}
				}
			},
			[ chapterId, lesson, S ]
		);
		var P = ( 0, ReactHooks.useCallback )(
			function ( e ) {
				if ( ! ( Number( e ) < 1 ) ) {
					S.setLesson(
						Qn(
							Qn( {}, lesson ),
							{},
							{
								drip_settings: Qn(
									Qn( {}, lesson.drip_settings ),
									{},
									{
										days: e,
									}
								),
							}
						)
					);
				}
			},
			[ S, lesson ]
		);
		return (
			<React.Fragment>
				<Controls.SpacerWP
					paddingX={ 5 }
					paddingTop={ 5 }
					paddingBottom={ 0 }
				>
					<Controls.FlexWP
						align={ 'center' }
						gap={ 3 }
						justify={ 'flex-start' }
					>
						<Rt />
						{ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
					</Controls.FlexWP>
				</Controls.SpacerWP>
				{ React.createElement( zt, {
					visibility:
						lesson === null || lesson === void 0
							? void 0
							: lesson.status,
					onVisibilityChange( e ) {
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									status: e,
								}
							)
						);
					},
				} ) }
				<Kt
					title={ ( 0, I18n.__ )(
						'Allow Lesson Preview',
						'ohmylms'
					) }
					tooltip={ ( 0, I18n.__ )(
						'Allow students to preview the lesson without enrolling in the course.',
						'ohmylms'
					) }
					onChange={ function () {
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									preview_enable: ! (
										lesson !== null &&
										lesson !== void 0 &&
										lesson.preview_enable
									),
								}
							)
						);
					} }
					isChecked={
						lesson === null || lesson === void 0
							? void 0
							: lesson.preview_enable
					}
				/>
				{ chapterId && (
					<PrerequisiteSettings
						title={ ( 0, I18n.__ )( 'Prerequisites', 'ohmylms' ) }
						tooltip={ ( 0, I18n.__ )(
							'Set content that students must complete before accessing this one.',
							'ohmylms'
						) }
						onChange={ function () {
							var e;
							S.setLesson(
								Qn(
									Qn( {}, lesson ),
									{},
									{
										prerequisites: Qn(
											Qn( {}, lesson.prerequisites ),
											{},
											{
												enable: ! (
													lesson !== null &&
													lesson !== void 0 &&
													null !==
														( e =
															lesson.prerequisites ) &&
													void 0 !== e &&
													e.enable
												),
											}
										),
									}
								)
							);
						} }
						isChecked={
							lesson === null ||
							lesson === void 0 ||
							null === ( t = lesson.prerequisites ) ||
							void 0 === t
								? void 0
								: t.enable
						}
						childTitle={ ( 0, I18n.__ )(
							'Members can access this content if they have completed all of the following content:',
							'ohmylms'
						) }
						childPlaceholder={ ( 0, I18n.__ )(
							'Type to search course modules',
							'ohmylms'
						) }
						childNotFoundMessage={ ( 0, I18n.__ )(
							'No Lessons Found',
							'ohmylms'
						) }
						isMultiple={ ! 0 }
						onSearch={ x }
						onChildChange={ function ( e ) {
							var _tLocal7 = e.map( function ( _eLocal1 ) {
								return {
									label: _eLocal1.label,
									value: _eLocal1.value,
								};
							} );
							S.setLesson(
								Qn(
									Qn( {}, lesson ),
									{},
									{
										prerequisites: Qn(
											Qn( {}, lesson.prerequisites ),
											{},
											{
												data: qn( _tLocal7 ),
											}
										),
									}
								)
							);
						} }
						defaultValue={
							lesson === null ||
							lesson === void 0 ||
							null === ( n = lesson.prerequisites ) ||
							void 0 === n
								? void 0
								: n.data
						}
					/>
				) }
				<DripSettings
					onChange={ function () {
						{
							var e = ! lesson.drip_settings.enable;
							S.setLesson(
								Qn(
									Qn( {}, lesson ),
									{},
									{
										drip_settings: Qn(
											Qn(
												{},
												lesson === null ||
													lesson === void 0
													? void 0
													: lesson.drip_settings
											),
											{},
											{
												enable: e,
											},
											e && {
												type: R
													? 'cohort-start'
													: 'enrollment-from-x-days',
											}
										),
									}
								)
							);
						}
					} }
					onDripFeedTypeChange={ function ( e ) {
						{
							var _tLocal8,
								_nLocal5,
								_rLocal5 = Qn(
									Qn( {}, lesson.drip_settings ),
									{},
									{
										type: e,
									}
								);
							if ( 'specific-date' === e ) {
								delete _rLocal5.days;
								_rLocal5.date =
									( lesson === null ||
									lesson === void 0 ||
									null ===
										( _tLocal8 = lesson.drip_settings ) ||
									void 0 === _tLocal8
										? void 0
										: _tLocal8.date ) ||
									sn()( new Date() ).format(
										'YYYY-MM-DDTHH:mm:ss.SSSD'
									);
								_rLocal5.time =
									( lesson === null ||
									lesson === void 0 ||
									null ===
										( _nLocal5 = lesson.drip_settings ) ||
									void 0 === _nLocal5
										? void 0
										: _nLocal5.time ) ||
									sn()( new Date() ).format(
										'YYYY-MM-DDTHH:mm:ss.SSSD'
									);
							} else if (
								'cohort-from-x-days' === e ||
								'enrollment-from-x-days' === e
							) {
								var _aLocal5;
								delete _rLocal5.date;
								delete _rLocal5.time;
								_rLocal5.days =
									( lesson === null ||
									lesson === void 0 ||
									null ===
										( _aLocal5 = lesson.drip_settings ) ||
									void 0 === _aLocal5
										? void 0
										: _aLocal5.days ) || 1;
							} else if ( 'cohort-start' === e ) {
								{
									delete _rLocal5.days;
									delete _rLocal5.date;
									delete _rLocal5.time;
								}
							}
							S.setLesson(
								Qn(
									Qn( {}, lesson ),
									{},
									{
										drip_settings: _rLocal5,
									}
								)
							);
						}
					} }
					handleDripDatePickerChange={ function ( e ) {
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									drip_settings: Qn(
										Qn( {}, lesson.drip_settings ),
										{},
										{
											date: e
												? sn()( e ).format(
														'YYYY-MM-DDTHH:mm:ss.SSS'
													)
												: null,
										}
									),
								}
							)
						);
					} }
					handleDripTimePickerChange={ function ( e ) {
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									drip_settings: Qn(
										Qn( {}, lesson.drip_settings ),
										{},
										{
											time: e
												? sn()( e ).format(
														'YYYY-MM-DDTHH:mm:ss.SSS'
													)
												: null,
										}
									),
								}
							)
						);
					} }
					handleDayChange={ P }
					isChecked={
						lesson === null ||
						lesson === void 0 ||
						null === ( r = lesson.drip_settings ) ||
						void 0 === r
							? void 0
							: r.enable
					}
					dripFeedType={
						lesson === null ||
						lesson === void 0 ||
						null === ( a = lesson.drip_settings ) ||
						void 0 === a
							? void 0
							: a.type
					}
					dripDate={
						( lesson === null ||
						lesson === void 0 ||
						null === ( o = lesson.drip_settings ) ||
						void 0 === o
							? void 0
							: o.date ) ||
						sn()().startOf( 'day' ).format( 'YYYY-MM-DD' )
					}
					dripTime={
						( lesson === null ||
						lesson === void 0 ||
						null === ( i = lesson.drip_settings ) ||
						void 0 === i
							? void 0
							: i.time ) || new Date()
					}
					enrollmentFromXDays={
						lesson === null ||
						lesson === void 0 ||
						null === ( c = lesson.drip_settings ) ||
						void 0 === c
							? void 0
							: c.days
					}
					isCohortBased={ R }
				/>
				<DownloadResources
					resources={
						( lesson === null ||
						lesson === void 0 ||
						null === ( u = lesson.download_resource ) ||
						void 0 === u
							? void 0
							: u.file ) || []
					}
					handleResources={ function ( e ) {
						var _tLocal0;
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									download_resource: Qn(
										Qn(
											{},
											lesson === null || lesson === void 0
												? void 0
												: lesson.download_resource
										),
										{},
										{
											file: [].concat(
												qn(
													( lesson === null ||
													lesson === void 0 ||
													null ===
														( _tLocal0 =
															lesson.download_resource ) ||
													void 0 === _tLocal0
														? void 0
														: _tLocal0.file ) || []
												),
												qn( e )
											),
										}
									),
								}
							)
						);
					} }
					handleDeleteResource={ function ( e ) {
						var _tLocal1,
							_nLocal6 =
								lesson === null ||
								lesson === void 0 ||
								null ===
									( _tLocal1 = lesson.download_resource ) ||
								void 0 === _tLocal1 ||
								null === ( _tLocal1 = _tLocal1.file ) ||
								void 0 === _tLocal1
									? void 0
									: _tLocal1.filter( function ( _tLocal10 ) {
											return _tLocal10.id !== e;
										} );
						S.setLesson(
							Qn(
								Qn( {}, lesson ),
								{},
								{
									download_resource: Qn(
										Qn( {}, lesson.download_resource ),
										{},
										{
											file: _nLocal6,
										}
									),
								}
							)
						);
					} }
					tooltipText={ ( 0, I18n.__ )(
						'Upload downloadable files or materials for students to access with this lesson.',
						'ohmylms'
					) }
				/>
				{ chapterId && (
					<Controls.SpacerWP padding={ 5 } marginBottom={ 0 }>
						<DeleteLearningItem
							label={ ( 0, I18n.__ )(
								'Delete Lesson',
								'ohmylms'
							) }
							onDelete={ C }
							alertTitle={ ( 0, I18n.__ )(
								'Delete Lesson',
								'ohmylms'
							) }
							alertDescription={ ( 0, I18n.__ )(
								'Are you sure you want to delete this lesson?',
								'ohmylms'
							) }
							className={
								'ohmylms-lesson-settings-delete-button'
							}
						/>
					</Controls.SpacerWP>
				) }
				{ w && (
					<React.Fragment>
						<He.default isOpen={ w } onClose={ E } />
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
