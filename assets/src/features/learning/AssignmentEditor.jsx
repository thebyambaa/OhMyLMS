import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createAssignmentEditor( readRuntime ) {
	return function AssignmentEditor( props ) {
		const {
			$a,
			Br,
			Da: AssignmentSettings,
			La,
			React,
			T: StoreModule,
			Ua,
			Va,
			Wr: LearningEditorHeader,
			Ya,
			Za,
			b: I18n,
			f: Router,
			g: ReactHooks,
			qa,
			y: WordPressData,
			ya: AssignmentContent,
			za,
		} = readRuntime();
		var t = useNotifications(),
			openNotificationWithIcon = t.openNotificationWithIcon,
			contextHolder = t.contextHolder,
			a = props.id,
			chapterId = props.chapterId,
			setOpenModal = props.setOpenModal,
			setLocalData = props.setLocalData,
			c = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			u = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).isLoading();
			}, [] ),
			s = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getAssignment();
			}, [] ),
			d = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).geSelectedAssignmentId();
			}, [] ),
			m = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			}, [] ),
			p = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			}, [] ),
			v = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getCourseChaptersContent();
				},
				[ chapterId ]
			),
			h = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			getAssignment = h.getAssignment,
			resetAssignmentState = h.resetAssignmentState,
			E = ( function ( e, _tLocal ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return _eLocal;
						}
					} )( e ) ||
					( function ( _eLocal2, _tLocal2 ) {
						var n =
							_eLocal2 === null || _eLocal2 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal2[ Symbol.iterator ] ) ||
									_eLocal2[ '@@iterator' ];
						if ( n !== null && n !== void 0 ) {
							var r,
								_aLocal,
								o,
								i,
								l = [],
								_cLocal = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( o = ( n = n.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( n ) !== n ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( r = o.call( n ) )
											.done ) &&
										( l.push( r.value ),
										l.length !== _tLocal2 );
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
											n.return
										) &&
										( ( i = n.return() ),
										Object( i ) !== i )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal;
									}
								}
							}
							return l;
						}
					} )( e, _tLocal ) ||
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return $a( _eLocal4, _tLocal3 );
							}
							var n = {}.toString.call( _eLocal4 ).slice( 8, -1 );
							return (
								'Object' === n &&
									_eLocal4.constructor &&
									( n = _eLocal4.constructor.name ),
								( () => {
									if ( 'Map' === n || 'Set' === n ) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === n ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											n
										)
									) {
										return $a( _eLocal4, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )(
				( 0, ReactHooks.useState )( {
					description: '',
				} ),
				2
			),
			S = E[ 0 ],
			R = E[ 1 ],
			x = ( 0, Router.Zp )();
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! u && m ) {
					openNotificationWithIcon( p, m );
				}
			},
			[ m ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				var e = ( function () {
					var _eLocal5,
						_tLocal4 =
							( ( _eLocal5 = Ya().m( function _eLocal6() {
								var _tLocal5;
								return Ya().w(
									function ( _eLocal7 ) {
										for (;;) {
											switch (
												( _eLocal7.p = _eLocal7.n )
											) {
												case 0:
													if (
														( ( _eLocal7.p = 0 ),
														d )
													) {
														_eLocal7.n = 1;
														break;
													}
													return _eLocal7.a( 2 );
												case 1:
													return (
														( _eLocal7.n = 2 ),
														getAssignment( d )
													 );
												case 2:
													_eLocal7.n = 4;
													break;
												case 3:
													_eLocal7.p = 3;
													_tLocal5 = _eLocal7.v;
													showRequestError(
														'Error fetching course data:',
														_tLocal5
													);
												// Falls through to the shared async state cleanup.
												case 4:
													return _eLocal7.a( 2 );
											}
										}
									},
									_eLocal6,
									null,
									[ [ 0, 3 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal6 = this,
									n = _args;
								return new Promise( function ( r, _aLocal2 ) {
									var o = _eLocal5.apply( _tLocal6, n );
									function i( _eLocal8 ) {
										Za(
											o,
											r,
											_aLocal2,
											i,
											l,
											'next',
											_eLocal8
										);
									}
									function l( _eLocal9 ) {
										Za(
											o,
											r,
											_aLocal2,
											i,
											l,
											'throw',
											_eLocal9
										);
									}
									i( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return _tLocal4.apply( this, _args2 );
					};
				} )();
				return (
					e(),
					function () {
						resetAssignmentState();
					}
				 );
			},
			[ d ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( s ) {
					R( {
						description:
							( S === null || S === void 0
								? void 0
								: S.description ) ||
							s.description ||
							'',
					} );
				}
			},
			[ s ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( setLocalData ) {
					setLocalData( S );
				}
			},
			[ S ]
		);
		var C = function () {
			if (
				La( s ) &&
				( ( s.description = S.description ),
				c.updateAssignment(
					s === null || s === void 0 ? void 0 : s.id,
					s
				),
				chapterId )
			) {
				var e = v === null || v === void 0 ? void 0 : v.byId;
				e[ s === null || s === void 0 ? void 0 : s.id ] = s;
				var _tLocal7 = Object.values( e ).filter(
					function ( _eLocal0 ) {
						return void 0 !==
							( _eLocal0 === null || _eLocal0 === void 0
								? void 0
								: _eLocal0.chapterId )
							? ( _eLocal0 === null || _eLocal0 === void 0
									? void 0
									: _eLocal0.chapterId ) === chapterId
							: ( _eLocal0 === null || _eLocal0 === void 0
									? void 0
									: _eLocal0.id ) ===
									( s === null || s === void 0
										? void 0
										: s.id );
					}
				);
				c.setContentsToChapter( chapterId, _tLocal7 );
			}
		};
		return (
			<React.Fragment>
				{ ! chapterId && contextHolder }
				{ ! u || ( s !== null && s !== void 0 && s.id ) ? (
					<React.Fragment>
						{ ! chapterId && (
							<LearningEditorHeader
								title={ ( 0, I18n.__ )(
									'Assignment Outline',
									'ohmylms'
								) }
								redirection={ '/assignments' }
								rightContent={
									<React.Fragment>
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ function () {
												x(
													'/assignment-report/'.concat(
														a
													)
												);
											} }
											icon={ React.createElement(
												za,
												null
											) }
										>
											{ ( 0, I18n.__ )(
												'Result',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ function () {
												if (
													s !== null &&
													s !== void 0 &&
													s.preview_url
												) {
													window.open(
														s === null ||
															s === void 0
															? void 0
															: s.preview_url,
														'_blank'
													);
												}
											} }
											icon={ <Br /> }
										>
											{ ( 0, I18n.__ )(
												'Preview',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'primary' }
											onClick={ C }
											disabled={ ! La( s ) || u }
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
						<Controls.SpacerWP
							paddingX={ chapterId ? 0 : 4 }
							paddingY={ 2 }
						>
							<Controls.FlexWP align={ 'stretch' } gap={ 4 }>
								<Controls.FlexBlockWP
									style={ {
										flex: 3,
									} }
								>
									<AssignmentContent
										assignment={ s }
										handleInputChange={ function ( e ) {
											c.setAssignment( {
												name: e,
											} );
										} }
										handleEditorContentChange={ function (
											e
										) {
											R( function ( _tLocal8 ) {
												return Ua(
													Ua( {}, _tLocal8 ),
													{},
													{
														description: e,
													}
												);
											} );
										} }
										handleUploadComplete={ function (
											e,
											_tLocal9
										) {
											switch ( _tLocal9 ) {
												case 'video':
													c.setAssignment(
														Ua(
															Ua( {}, s ),
															{},
															{
																video_id: e.id,
																video_src:
																	e.url,
															}
														)
													);
													break;
												case 'audio':
													c.setAssignment(
														Ua(
															Ua( {}, s ),
															{},
															{
																audio_id: e.id,
																audio_src:
																	e.url,
															}
														)
													);
													break;
												default:
													c.setAssignment(
														Ua(
															Ua( {}, s ),
															{},
															{
																image_id: e.id,
																image_src:
																	e.url,
															}
														)
													);
											}
										} }
										handleRemoveMedia={ function ( e ) {
											c.setAssignment(
												Ua(
													Ua( {}, s ),
													{},
													qa(
														qa(
															{},
															''.concat(
																e,
																'_id'
															),
															null
														),
														''.concat( e, '_src' ),
														null
													)
												)
											);
										} }
										setOpenModal={ setOpenModal }
										saveLesson={ C }
										chapterId={ chapterId }
										onExternalUploadComplete={ function (
											e
										) {
											c.setAssignment(
												Ua(
													Ua( {}, s ),
													{},
													{
														external_url: e.url,
													}
												)
											);
										} }
									/>
								</Controls.FlexBlockWP>
								<Controls.FlexBlockWP
									style={ {
										flex: 2,
									} }
								>
									<AssignmentSettings
										setOpenModal={ setOpenModal }
										assignment={ s }
										chapterId={ chapterId }
									/>
								</Controls.FlexBlockWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</React.Fragment>
				) : (
					<Va.A
						spinning={ u }
						delay={ 0 }
						style={ {
							position: 'fixed',
							top: '50%',
							left: '50%',
							transform: 'translate(-50%, -50%)',
							zIndex: 9999,
						} }
					/>
				) }
			</React.Fragment>
		);
	};
}
