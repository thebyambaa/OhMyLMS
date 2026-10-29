import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { createAchievementRules } from './AchievementRules';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createLevelEditor( readRuntime ) {
	const AchievementRules = createAchievementRules( readRuntime );
	return function LevelEditor( props ) {
		const {
			F3,
			FK,
			N3,
			O3,
			Pf,
			React,
			T: StoreModule,
			T3,
			b: I18n,
			g: ReactHooks,
			j3,
			k3,
			l,
			y: WordPressData,
		} = readRuntime();
		const { useState, useEffect, useMemo } = ReactHooks;
		const { useSelect } = WordPressData;
		var data = props.data;
		var isOpen = props.isOpen;
		var onClose = props.onClose;
		var fetchData = props.fetchData;
		var setItems = props.setItems;
		var i = useMemo(
			() => ( {
				name: '',
				description: '',
				color: '#6e42d3',
				textColor: '#ffffff',
				rules: [
					{
						dataLabel: 'Points',
						dataValue: 'points',
						dataFieldType: 'select',
						compareSign: '>=',
						compareData: 0,
						compareDataFieldType: 'input',
					},
				],
			} ),
			[]
		);
		var [ u, s ] = useState( F3( {}, i ) );
		var [ m, p ] = useState( ! 1 );
		var f = useNotifications();
		var openNotificationWithIcon = f.openNotificationWithIcon;
		var contextHolder = f.contextHolder;
		var _ = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getNotificationMessage();
			},
			[ StoreModule.default ]
		);
		var w = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getNotificationStatus();
			},
			[ StoreModule.default ]
		);
		var E = function () {
			if ( ! m ) {
				onClose( ! 1 );
			}
		};
		var S = function ( e, t ) {
			s( function ( n ) {
				return F3( F3( {}, n ), {}, N3( {}, e, t ) );
			} );
		};
		if (
			( useEffect(
				function () {
					if ( data ) {
						s( data );
					}
				},
				[ data ]
			),
			useEffect(
				function () {
					if ( ! m && _ ) {
						openNotificationWithIcon( w, _ );
					}
				},
				[ _, m, openNotificationWithIcon, w ]
			),
			useEffect(
				function () {
					if ( isOpen ) {
						s( data || F3( {}, i ) );
					}
				},
				[ F3, data, i, isOpen ]
			),
			! isOpen )
		) {
			return null;
		}
		var P =
			! u.name ||
			'' === u.name.trim() ||
			u.rules.some( function ( e ) {
				return (
					null === e.compareData ||
					'' === e.compareData ||
					e.compareData < 0
				);
			} );
		var R = ( function () {
			var e = T3(
				j3().m( function _eLocal() {
					var t;
					return j3().w(
						function ( _eLocal2 ) {
							for (;;) {
								switch ( ( _eLocal2.p = _eLocal2.n ) ) {
									case 0:
										return (
											( _eLocal2.p = 0 ),
											p( ! 0 ),
											( _eLocal2.n = 1 ),
											l()( {
												path: '/ohmylms/v1/engagement/levels',
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
												},
												body: JSON.stringify( u ),
											} )
										 );
									case 1:
										if ( _eLocal2.v.success ) {
											{
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Level updated successfully!',
														'ohmylms'
													)
												);
												setItems(
													function ( _eLocal3 ) {
														return _eLocal3.map(
															function (
																_eLocal4
															) {
																return _eLocal4.slug ===
																	u.slug
																	? F3(
																			F3(
																				{},
																				_eLocal4
																			),
																			u
																		)
																	: _eLocal4;
															}
														);
													}
												);
											}
										}
										_eLocal2.n = 3;
										break;
									case 2:
										_eLocal2.p = 2;
										t = _eLocal2.v;
										showRequestError( t );
									// Falls through to the shared async state cleanup.
									case 3:
										return (
											( _eLocal2.p = 3 ),
											p( ! 1 ),
											_eLocal2.f( 3 )
										 );
									case 4:
										return _eLocal2.a( 2 );
								}
							}
						},
						_eLocal,
						null,
						[ [ 0, 2, 3, 4 ] ]
					);
				} )
			);
			return function ( ..._args ) {
				return e.apply( this, _args );
			};
		} )();
		var x = ( function () {
			var e = T3(
				j3().m( function _eLocal5() {
					return j3().w(
						function ( _eLocal6 ) {
							for (;;) {
								switch ( ( _eLocal6.p = _eLocal6.n ) ) {
									case 0:
										return (
											( _eLocal6.p = 0 ),
											p( ! 0 ),
											( _eLocal6.n = 1 ),
											l()( {
												path: '/ohmylms/v1/engagement/levels',
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
												},
												body: JSON.stringify( u ),
											} )
										 );
									case 1:
										if ( _eLocal6.v.success ) {
											{
												openNotificationWithIcon(
													'success',
													( 0, I18n.__ )(
														'Level created successfully!',
														'ohmylms'
													)
												);
												setItems(
													function ( _eLocal7 ) {
														return [ u ].concat(
															k3( _eLocal7 )
														);
													}
												);
											}
										}
										_eLocal6.n = 3;
										break;
									case 2:
										_eLocal6.p = 2;
										openNotificationWithIcon(
											'error',
											( 0, I18n.__ )(
												'Something went wrong!',
												'ohmylms'
											)
										);
									// Falls through to the shared async state cleanup.
									case 3:
										return (
											( _eLocal6.p = 3 ),
											p( ! 1 ),
											onClose( ! 1 ),
											_eLocal6.f( 3 )
										 );
									case 4:
										return _eLocal6.a( 2 );
								}
							}
						},
						_eLocal5,
						null,
						[ [ 0, 2, 3, 4 ] ]
					);
				} )
			);
			return function ( ..._args2 ) {
				return e.apply( this, _args2 );
			};
		} )();
		var C = ( function () {
			var e = T3(
				j3().m( function _eLocal8() {
					var n;
					var r;
					var o;
					var _iLocal;
					return j3().w(
						function ( _eLocal9 ) {
							for (;;) {
								switch ( ( _eLocal9.p = _eLocal9.n ) ) {
									case 0:
										if ( u.name && '' !== u.name.trim() ) {
											_eLocal9.n = 1;
											break;
										}
										return (
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'The fields cannot be empty.',
													'ohmylms'
												)
											),
											_eLocal9.a( 2 )
										 );
									case 1:
										n = O3( u.rules );
										_eLocal9.p = 2;
										n.s();
									// Falls through to the shared async state cleanup.
									case 3:
										if ( ( r = n.n() ).done ) {
											_eLocal9.n = 5;
											break;
										}
										if ( ! (
											null ===
												( o = r.value ).compareData ||
											'' === o.compareData ||
											o.compareData < 0
										) ) {
											_eLocal9.n = 4;
											break;
										}
										return (
											openNotificationWithIcon(
												'error',
												( 0, I18n.__ )(
													'The fields cannot be empty or negative.',
													'ohmylms'
												)
											),
											_eLocal9.a( 2 )
										 );
									case 4:
										_eLocal9.n = 3;
										break;
									case 5:
										_eLocal9.n = 7;
										break;
									case 6:
										_eLocal9.p = 6;
										_iLocal = _eLocal9.v;
										n.e( _iLocal );
									// Falls through to the shared async state cleanup.
									case 7:
										return (
											( _eLocal9.p = 7 ),
											n.f(),
											_eLocal9.f( 7 )
										 );
									case 8:
										if (
											data === null ||
											data === void 0 ||
											! data.slug
										) {
											_eLocal9.n = 10;
											break;
										}
										return ( ( _eLocal9.n = 9 ), R() );
									case 9:
										_eLocal9.n = 11;
										break;
									case 10:
										return ( ( _eLocal9.n = 11 ), x() );
									case 11:
										fetchData();
									// Falls through to the shared async state cleanup.
									case 12:
										return _eLocal9.a( 2 );
								}
							}
						},
						_eLocal8,
						null,
						[ [ 2, 6, 7, 8 ] ]
					);
				} )
			);
			return function ( ..._args3 ) {
				return e.apply( this, _args3 );
			};
		} )();
		return (
			<React.Fragment>
				{ contextHolder }
				<Controls.ModalWP
					title={
						data !== null && data !== void 0 && data.slug
							? ( 0, I18n.__ )( 'Edit Learner Level', 'ohmylms' )
							: ( 0, I18n.__ )(
									'Add New Learner Level',
									'ohmylms'
								)
					}
					onRequestClose={ E }
					shouldCloseOnEsc={ ! 0 }
					shouldCloseOnClickOutside={ ! 0 }
					size={ 'large' }
				>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
					>
						<Controls.SpacerWP padding={ 3 }>
							<Pf
								title={ ( 0, I18n.__ )(
									'Level Name',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Give this level a meaning name learners will recognize.',
									'ohmylms'
								) }
								inputType={ 'text' }
								value={
									u === null || u === void 0 ? void 0 : u.name
								}
								onChange={ function ( e ) {
									return S( 'name', e );
								} }
								placeholder={ ( 0, I18n.__ )(
									'Enter level name',
									'ohmylms'
								) }
								required={ ! 0 }
							/>
							<Pf
								title={ ( 0, I18n.__ )(
									'Level Description',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Explain what this level represents or how it’s achieved.',
									'ohmylms'
								) }
								inputType={ 'textarea' }
								value={
									u === null || u === void 0
										? void 0
										: u.description
								}
								onChange={ function ( e ) {
									return S( 'description', e );
								} }
								placeholder={ ( 0, I18n.__ )(
									'Enter level description',
									'ohmylms'
								) }
							/>
							<Controls.SpacerWP marginBottom={ 4 }>
								<FK
									title={ ( 0, I18n.__ )(
										'BackgroundColor',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Choose a background color for the level.',
										'ohmylms'
									) }
									initialColor={
										u === null || u === void 0
											? void 0
											: u.color
									}
									onChange={ function ( e ) {
										return S( 'color', e );
									} }
									variant={ 'secondary' }
									isBorderless={ ! 0 }
									isShowResetBtn={ ! 0 }
									defaultColor={ '#6e42d3' }
								/>
							</Controls.SpacerWP>
							<Controls.SpacerWP marginBottom={ 4 }>
								<FK
									title={ ( 0, I18n.__ )(
										'Text Color',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Choose a text color for the level.',
										'ohmylms'
									) }
									initialColor={
										u === null || u === void 0
											? void 0
											: u.textColor
									}
									onChange={ function ( e ) {
										return S( 'textColor', e );
									} }
									variant={ 'secondary' }
									isBorderless={ ! 0 }
									isShowResetBtn={ ! 0 }
									defaultColor={ '#ffffff' }
								/>
							</Controls.SpacerWP>
							<AchievementRules
								rules={
									u === null || u === void 0
										? void 0
										: u.rules
								}
								setRules={ function ( e ) {
									return S( 'rules', e );
								} }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.SpacerWP paddingTop={ 4 }>
						<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
							<Controls.ButtonWP
								variant={ 'secondary' }
								onClick={ E }
								disabled={ m }
							>
								{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.ButtonWP
								variant={ 'primary' }
								disabled={ P }
								onClick={ C }
								isBusy={ m }
							>
								{ data !== null && data !== void 0 && data.slug
									? ( 0, I18n.__ )( 'Update', 'ohmylms' )
									: ( 0, I18n.__ )( 'Create', 'ohmylms' ) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.ModalWP>
			</React.Fragment>
		);
	};
}
