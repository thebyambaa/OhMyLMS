import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createEmailEditor( readRuntime ) {
	return function EmailEditor() {
		const {
			Bee,
			Dee,
			Ea,
			Fee: MemoEmailFields,
			Hee,
			Oee: MemoEmailPreview,
			React,
			Ree: MemoEmailEditorHeader,
			T: StoreModule,
			Vee,
			b: I18n,
			g: ReactHooks,
			y: WordPressData,
		} = readRuntime();
		var e,
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getEmail();
			}, [] ),
			n = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			r = n.updateSingleEmail,
			a = n.updateEmail,
			o = Hee( ( 0, ReactHooks.useState )( 'desktop' ), 2 ),
			i = o[ 0 ],
			l = o[ 1 ],
			c = useNotifications(),
			u = c.openNotificationWithIcon,
			s = c.contextHolder,
			d = Hee(
				( 0, ReactHooks.useState )(
					( t === null || t === void 0
						? void 0
						: t.additional_content ) || ''
				),
				2
			),
			m = d[ 0 ],
			p = d[ 1 ],
			f = Hee(
				( 0, ReactHooks.useState )(
					( t === null || t === void 0 ? void 0 : t.footer_text ) ||
						''
				),
				2
			),
			v = f[ 0 ],
			h = f[ 1 ],
			_ = Hee(
				( 0, ReactHooks.useState )(
					( t === null || t === void 0
						? void 0
						: t.course_suggestion_text ) || ''
				),
				2
			),
			w = _[ 0 ],
			E = _[ 1 ],
			S = ( function () {
				var _eLocal2,
					_nLocal =
						( ( _eLocal2 = Dee().m( function _eLocal3() {
							var _nLocal2, _oLocal, _iLocal;
							return Dee().w(
								function ( _eLocal4 ) {
									for (;;) {
										switch ( ( _eLocal4.p = _eLocal4.n ) ) {
											case 0:
												return (
													( _eLocal4.p = 0 ),
													( _eLocal4.n = 1 ),
													a( {
														additional_content: m,
														footer_text: v,
														course_suggestion_text:
															w,
													} )
												 );
											case 1:
												return (
													( _oLocal = Bee(
														Bee( {}, t ),
														{},
														{
															additional_content:
																m,
															footer_text: v,
															course_suggestion_text:
																w,
														}
													) ),
													( _eLocal4.n = 2 ),
													r(
														t === null ||
															t === void 0 ||
															null ===
																( _nLocal2 =
																	t.basic ) ||
															void 0 === _nLocal2
															? void 0
															: _nLocal2.id,
														_oLocal
													)
												 );
											case 2:
												u(
													'success',
													'Saved successfully'
												);
												_eLocal4.n = 4;
												break;
											case 3:
												_eLocal4.p = 3;
												_iLocal = _eLocal4.v;
												showRequestError( _iLocal );
												u(
													'error',
													'Failed to save! Please try again.'
												);
											// Falls through to the shared async state cleanup.
											case 4:
												return _eLocal4.a( 2 );
										}
									}
								},
								_eLocal3,
								null,
								[ [ 0, 3 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal = this,
								_nLocal3 = _args;
							return new Promise( function ( _rLocal, _aLocal ) {
								var _oLocal2 = _eLocal2.apply(
									_tLocal,
									_nLocal3
								);
								function _iLocal2( _eLocal5 ) {
									Vee(
										_oLocal2,
										_rLocal,
										_aLocal,
										_iLocal2,
										_lLocal,
										'next',
										_eLocal5
									);
								}
								function _lLocal( _eLocal6 ) {
									Vee(
										_oLocal2,
										_rLocal,
										_aLocal,
										_iLocal2,
										_lLocal,
										'throw',
										_eLocal6
									);
								}
								_iLocal2( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _nLocal.apply( this, _args2 );
				};
			} )();
		return (
			<React.Fragment>
				{ s }
				<MemoEmailEditorHeader
					title={
						( t === null ||
						t === void 0 ||
						null === ( e = t.basic ) ||
						void 0 === e
							? void 0
							: e.title ) ||
						( 0, I18n.__ )( 'Email Editor', 'ohmylms' )
					}
					handlePreview={ function ( _eLocal7 ) {
						l( _eLocal7 );
					} }
					handleTestEmail={ function () {
						showRequestError( 'Test Email' );
					} }
					handleSave={ S }
					device={ i }
				/>
				<Ea isBorderless={ ! 0 } variant={ 'secondary' }>
					<Controls.SpacerWP padding={ 5 } gap={ 3 }>
						<Controls.FlexWP
							align={ 'stretch' }
							justify={ 'between' }
							gap={ '3' }
						>
							<Controls.FlexItemWP
								style={ {
									flex: 3,
								} }
							>
								<MemoEmailPreview
									selected={ i }
									content={ m }
									footerText={ v }
									courseSuggestionText={ w }
								/>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP
								style={ {
									flex: 1,
								} }
							>
								<MemoEmailFields
									setAdditionalContent={ p }
									setFooterContent={ h }
									setCourseSuggestionText={ E }
								/>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Ea>
			</React.Fragment>
		);
	};
}
