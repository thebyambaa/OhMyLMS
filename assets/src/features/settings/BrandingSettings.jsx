/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createBrandingSettings( readRuntime ) {
	return function BrandingSettings( props ) {
		const {
			BK,
			GK,
			HK,
			M,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			VK,
			b: I18n,
			g: ReactHooks,
			hu,
			l,
			y: WordPressData,
			zK,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			c = props.activeTab,
			u = props.handleSave,
			s = props.handleMigration,
			d = props.selectedCourses,
			m = props.isSaving;
		M().noConflict();
		var p = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			f = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDesignSettings();
			}, [] ),
			v =
				( f === null ||
				f === void 0 ||
				null === ( t = f.ohmylms_video_player_logo ) ||
				void 0 === t
					? void 0
					: t.value ) || '',
			h =
				( f === null ||
				f === void 0 ||
				null === ( n = f.ohmylms_video_player_logo_bg_color ) ||
				void 0 === n
					? void 0
					: n.value ) || '#6E42D3',
			_ = ( function ( e, _tLocal ) {
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
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return HK( _eLocal4, _tLocal3 );
							}
							var _nLocal2 = {}.toString
								.call( _eLocal4 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal2 &&
									_eLocal4.constructor &&
									( _nLocal2 = _eLocal4.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal2 ||
										'Set' === _nLocal2
									) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === _nLocal2 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal2
										)
									) {
										return HK( _eLocal4, _tLocal3 );
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
			} )( ( 0, ReactHooks.useState )( '' ), 2 ),
			w = _[ 0 ],
			E = _[ 1 ],
			S = ( 0, ReactHooks.useRef )( ! 1 ),
			R = ( 0, ReactHooks.useCallback )(
				function () {
					var e, _tLocal4, _nLocal3, _rLocal2;
					return {
						primary:
							f === null ||
							f === void 0 ||
							null === ( e = f.ohmylms_primary_color_scheme ) ||
							void 0 === e ||
							null === ( e = e.value ) ||
							void 0 === e
								? void 0
								: e.toLowerCase(),
						heading:
							f === null ||
							f === void 0 ||
							null ===
								( _tLocal4 = f.ohmylms_heading_color_scheme ) ||
							void 0 === _tLocal4 ||
							null === ( _tLocal4 = _tLocal4.value ) ||
							void 0 === _tLocal4
								? void 0
								: _tLocal4.toLowerCase(),
						text:
							f === null ||
							f === void 0 ||
							null ===
								( _nLocal3 =
									f.ohmylms_body_text_color_scheme ) ||
							void 0 === _nLocal3 ||
							null === ( _nLocal3 = _nLocal3.value ) ||
							void 0 === _nLocal3
								? void 0
								: _nLocal3.toLowerCase(),
						progress:
							f === null ||
							f === void 0 ||
							null ===
								( _rLocal2 =
									f.ohmylms_body_progress_color_scheme ) ||
							void 0 === _rLocal2 ||
							null === ( _rLocal2 = _rLocal2.value ) ||
							void 0 === _rLocal2
								? void 0
								: _rLocal2.toLowerCase(),
					};
				},
				[ f ]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				var e, _tLocal5;
				if (
					! S.current &&
					f !== null &&
					f !== void 0 &&
					null !== ( e = f.ohmylms_primary_color_scheme ) &&
					void 0 !== e &&
					e.value
				) {
					S.current = ! 0;
					var _nLocal4 =
						f === null ||
						f === void 0 ||
						null === ( _tLocal5 = f.ohmylms_color_preset ) ||
						void 0 === _tLocal5
							? void 0
							: _tLocal5.value;
					if (
						_nLocal4 &&
						GK.find( function ( _eLocal5 ) {
							return _eLocal5.value === _nLocal4;
						} )
					) {
						E( _nLocal4 );
					} else {
						var _rLocal3 = R(),
							_aLocal2 = GK.find( function ( _eLocal6 ) {
								return (
									_eLocal6.colors.primary.toLowerCase() ===
										_rLocal3.primary &&
									_eLocal6.colors.heading.toLowerCase() ===
										_rLocal3.heading &&
									_eLocal6.colors.text.toLowerCase() ===
										_rLocal3.text &&
									_eLocal6.colors.progress.toLowerCase() ===
										_rLocal3.progress
								);
							} );
						E(
							( _aLocal2 === null || _aLocal2 === void 0
								? void 0
								: _aLocal2.value ) || GK[ 0 ].value
						);
					}
				}
			},
			[ f, R ]
		);
		var x = ( 0, ReactHooks.useMemo )(
			function () {
				var e = GK.find( function ( _eLocal7 ) {
					return _eLocal7.value === w;
				} );
				if ( ! e ) {
					return ! 1;
				}
				var _tLocal6 = R();
				return (
					e.colors.primary.toLowerCase() !== _tLocal6.primary ||
					e.colors.heading.toLowerCase() !== _tLocal6.heading ||
					e.colors.text.toLowerCase() !== _tLocal6.text ||
					e.colors.progress.toLowerCase() !== _tLocal6.progress
				);
			},
			[ w, R ]
		);
		if ( x ) {
			''.concat( w, '-customized' );
		} else {
		}
		( 0, ReactHooks.useMemo )(
			function () {
				var e = GK.map( function ( _eLocal8 ) {
					return {
						label: _eLocal8.label,
						value: _eLocal8.value,
					};
				} );
				if ( x ) {
					var _tLocal7 = GK.find( function ( _eLocal9 ) {
						return _eLocal9.value === w;
					} );
					if ( _tLocal7 ) {
						e.push( {
							label: ''
								.concat( _tLocal7.label, ' (' )
								.concat(
									( 0, I18n.__ )( 'Customized', 'ohmylms' ),
									')'
								),
							value: ''.concat( w, '-customized' ),
						} );
					}
				}
				return e;
			},
			[ x, w ]
		);
		( 0, ReactHooks.useCallback )(
			function ( e ) {
				if ( ! e.endsWith( '-customized' ) ) {
					var _tLocal8 = GK.find( function ( _tLocal9 ) {
						return _tLocal9.value === e;
					} );
					if ( _tLocal8 ) {
						{
							E( e );
							p.updateDesignSettings( {
								ohmylms_color_preset: {
									value: e,
								},
								ohmylms_primary_color_scheme: {
									value: _tLocal8.colors.primary,
								},
								ohmylms_heading_color_scheme: {
									value: _tLocal8.colors.heading,
								},
								ohmylms_body_text_color_scheme: {
									value: _tLocal8.colors.text,
								},
								ohmylms_body_progress_color_scheme: {
									value: _tLocal8.colors.progress,
								},
							} );
						}
					}
				}
			},
			[ p ]
		);
		var k = ( 0, ReactHooks.useMemo )(
			function () {
				var e = GK.find( function ( _eLocal0 ) {
					return _eLocal0.value === w;
				} );
				return (
					( e === null || e === void 0 ? void 0 : e.colors ) ||
					GK[ 0 ].colors
				);
			},
			[ w ]
		);
		void [
			{
				title: ( 0, I18n.__ )( 'Primary Color', 'ohmylms' ),
				description: ( 0, I18n.__ )(
					'Choose the main brand color used for buttons and highlights.',
					'ohmylms'
				),
				isShowResetBtn: ! 0,
				defaultColor: k.primary,
				initialColor:
					f === null ||
					f === void 0 ||
					null === ( r = f.ohmylms_primary_color_scheme ) ||
					void 0 === r
						? void 0
						: r.value,
				onChange( e ) {
					p.updateDesignSettings( {
						ohmylms_primary_color_scheme: {
							value: e,
						},
					} );
				},
			},
			{
				title: ( 0, I18n.__ )( 'Heading Color', 'ohmylms' ),
				description: ( 0, I18n.__ )(
					'Set the color for all main headings and titles.',
					'ohmylms'
				),
				isShowResetBtn: ! 0,
				defaultColor: k.heading,
				initialColor:
					f === null ||
					f === void 0 ||
					null === ( a = f.ohmylms_heading_color_scheme ) ||
					void 0 === a
						? void 0
						: a.value,
				onChange( e ) {
					p.updateDesignSettings( {
						ohmylms_heading_color_scheme: {
							value: e,
						},
					} );
				},
			},
			{
				title: ( 0, I18n.__ )( 'Text Color', 'ohmylms' ),
				description: ( 0, I18n.__ )(
					'Define the default color for body text.',
					'ohmylms'
				),
				isShowResetBtn: ! 0,
				defaultColor: k.text,
				initialColor:
					f === null ||
					f === void 0 ||
					null === ( o = f.ohmylms_body_text_color_scheme ) ||
					void 0 === o
						? void 0
						: o.value,
				onChange( e ) {
					p.updateDesignSettings( {
						ohmylms_body_text_color_scheme: {
							value: e,
						},
					} );
				},
			},
			{
				title: ( 0, I18n.__ )( 'Progress bar Color', 'ohmylms' ),
				description: ( 0, I18n.__ )(
					'Choose the color used for course progress bars.',
					'ohmylms'
				),
				isShowResetBtn: ! 0,
				defaultColor: k.progress,
				initialColor:
					f === null ||
					f === void 0 ||
					null === ( i = f.ohmylms_body_progress_color_scheme ) ||
					void 0 === i
						? void 0
						: i.value,
				onChange( e ) {
					p.updateDesignSettings( {
						ohmylms_body_progress_color_scheme: {
							value: e,
						},
					} );
				},
			},
		];
		var A = function () {
			var e = wp.media( {
				title: ( 0, I18n.__ )( 'Select Logo Image', 'ohmylms' ),
				button: {
					text: ( 0, I18n.__ )( 'Use this image', 'ohmylms' ),
				},
				multiple: ! 1,
			} );
			e.on( 'select', function () {
				var _tLocal0 = e.state().get( 'selection' ).first().toJSON();
				if ( 'image' === _tLocal0.type ) {
					p.updateDesignSettings( {
						ohmylms_video_player_logo: {
							value: _tLocal0.url,
						},
					} );
				}
			} );
			e.open();
		};
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ( function () {
					var _eLocal1,
						_tLocal1 =
							( ( _eLocal1 = BK().m( function _eLocal10() {
								var _tLocal10;
								return BK().w( function ( _eLocal11 ) {
									for (;;) {
										switch ( _eLocal11.n ) {
											case 0:
												return (
													p.setLoadingSetting( ! 0 ),
													( _eLocal11.n = 1 ),
													l()( {
														path: 'ohmylms/v1/settings/design',
													} )
												 );
											case 1:
												_tLocal10 = _eLocal11.v;
												p.setDesignSettings(
													_tLocal10
												);
												p.setLoadingSetting( ! 1 ); // Falls through to the shared async state cleanup.
											case 2:
												return _eLocal11.a( 2 );
										}
									}
								}, _eLocal10 );
							} ) ),
							function ( ..._args ) {
								var _tLocal11 = this,
									_nLocal5 = _args;
								return new Promise( function (
									_rLocal4,
									_aLocal3
								) {
									var _oLocal2 = _eLocal1.apply(
										_tLocal11,
										_nLocal5
									);
									function _iLocal2( _eLocal12 ) {
										VK(
											_oLocal2,
											_rLocal4,
											_aLocal3,
											_iLocal2,
											_lLocal2,
											'next',
											_eLocal12
										);
									}
									function _lLocal2( _eLocal13 ) {
										VK(
											_oLocal2,
											_rLocal4,
											_aLocal3,
											_iLocal2,
											_lLocal2,
											'throw',
											_eLocal13
										);
									}
									_iLocal2( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return _tLocal1.apply( this, _args2 );
					};
				} )();
				e();
			}, [] ),
			(
				<React.Fragment>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						className={ 'ohmylms-full-screen-height' }
					>
						<Controls.SpacerWP
							padding={ 4 }
							marginTop={ 0 }
							marginBottom={ 0 }
						>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									padding={ 6 }
									marginBottom={ 4 }
								>
									<h3
										style={ {
											margin: '0px 0px 8px',
											fontSize: '16px',
											fontWeight: 600,
										} }
									>
										{ ( 0, I18n.__ )(
											'Video Player Branding',
											'ohmylms'
										) }
									</h3>
									<p
										style={ {
											marginTop: '0px',
											marginBottom: '16px',
											color: '#687784',
											fontSize: '14px',
										} }
									>
										{ ( 0, I18n.__ )(
											'Upload your logo to display on the video player during lesson playback.',
											'ohmylms'
										) }
									</p>
									{ v ? (
										<Controls.FlexWP
											align={ 'flex-start' }
											justify={ 'flex-start' }
											direction={ 'column' }
											gap={ '3' }
										>
											<Controls.AvatarWP
												shape={ 'square' }
												src={ v }
												alt={ 'logo' }
												style={ {
													height: 'auto',
													maxHeight: '50px',
													width: 'auto',
												} }
											/>
											{ React.createElement( hu, {
												handleEdit: A,
												handleDelete() {
													p.updateDesignSettings( {
														ohmylms_video_player_logo:
															{
																value: '',
															},
													} );
												},
												justify: 'flex-start',
												alertTitle: ( 0, I18n.__ )(
													'Remove video player logo',
													'ohmylms'
												),
												alertDescription: ( 0,
												I18n.__ )(
													'Are you sure you want to remove the video player logo?',
													'ohmylms'
												),
											} ) }
										</Controls.FlexWP>
									) : (
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ A }
										>
											{ ( 0, I18n.__ )(
												'Upload Logo',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									) }
									<Controls.SpacerWP
										marginTop={ 4 }
										marginBottom={ 0 }
									>
										{ React.createElement( zK, {
											colorsConfig: [
												{
													title: ( 0, I18n.__ )(
														'Logo Background Color',
														'ohmylms'
													),
													description: ( 0, I18n.__ )(
														'Background color shown behind the logo on the video player.',
														'ohmylms'
													),
													isShowResetBtn: ! 0,
													defaultColor: '#6E42D3',
													initialColor: h,
													onChange( e ) {
														return p.updateDesignSettings(
															{
																ohmylms_video_player_logo_bg_color:
																	{
																		value: e,
																	},
															}
														);
													},
												},
											],
											showDivider: ! 1,
										} ) }
									</Controls.SpacerWP>
								</Controls.SpacerWP>
							</Controls.CardWP>
							<MemoSettingsActionBar
								activeTab={ c }
								handleSave={ u }
								handleMigration={ s }
								selectedCourses={ d }
								isSaving={ m }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
