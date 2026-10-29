import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
export function createWebhookEditorModal( readRuntime ) {
	return function WebhookEditorModal( props ) {
		const {
			B5: MemoWebhookDataMapping,
			G5,
			M5: MemoWebhookDetails,
			Q5,
			React,
			T: StoreModule,
			V5,
			b: I18n,
			g: ReactHooks,
			q5,
			y: WordPressData,
		} = readRuntime();
		var t = props.webhook,
			n = props.isOpen,
			r = props.onClose,
			a = props.onSave,
			o = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			i = useNotifications(),
			l = i.openNotificationWithIcon,
			c = i.contextHolder,
			u = Q5( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = Q5( ( 0, ReactHooks.useState )( '1' ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = Q5( ( 0, ReactHooks.useState )( {} ), 2 ),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = Q5( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			E = w[ 0 ];
		( 0, ReactHooks.useEffect )(
			function () {
				if ( n ) {
					if ( t ) {
						var e = [
							{
								key: '',
								value: '',
							},
						];
						if ( t.data_mapping ) {
							if ( 'string' === typeof t.data_mapping ) {
								try {
									e = JSON.parse( t.data_mapping );
								} catch ( _tLocal ) {
									showRequestError(
										'Failed to parse data_mapping:',
										_tLocal
									);
									e = [
										{
											key: '',
											value: '',
										},
									];
								}
							} else if ( Array.isArray( t.data_mapping ) ) {
								e = t.data_mapping;
							}
						}
						o.setWebhookFormData(
							q5(
								q5( {}, t ),
								{},
								{
									data_mapping: e,
								}
							)
						);
					} else {
						o.setWebhookFormData( {
							name: '',
							webhook_url: '',
							trigger_event: 'course_purchase',
							http_method: 'POST',
							data_type: 'json',
							status: 'active',
							description: '',
							headers: '',
							retry_attempts: 3,
							timeout: 30,
							data_mapping: [
								{
									key: 'User ID',
									value: 'user_id',
								},
							],
						} );
					}
				}
			},
			[ n, t, o ]
		);
		var S = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectWebhookFormData();
			}, [] ),
			R = function ( e ) {
				var _tLocal2,
					_nLocal,
					_rLocal = {};
				return (
					( e !== null &&
						e !== void 0 &&
						null !== ( _tLocal2 = e.name ) &&
						void 0 !== _tLocal2 &&
						_tLocal2.trim() ) ||
						( _rLocal.name = ( 0, I18n.__ )(
							'Name is required.',
							'ohmylms'
						) ),
					e !== null &&
					e !== void 0 &&
					null !== ( _nLocal = e.webhook_url ) &&
					void 0 !== _nLocal &&
					_nLocal.trim()
						? /^https?:\/\/.+/.test( e.webhook_url ) ||
							( _rLocal.webhook_url = ( 0, I18n.__ )(
								'Please enter a valid URL.',
								'ohmylms'
							) )
						: ( _rLocal.webhook_url = ( 0, I18n.__ )(
								'Webhook URL is required.',
								'ohmylms'
							) ),
					( e !== null && e !== void 0 && e.trigger_event ) ||
						( _rLocal.trigger_event = ( 0, I18n.__ )(
							'Trigger event is required.',
							'ohmylms'
						) ),
					_rLocal
				 );
			},
			x = function ( e ) {
				var _tLocal3 = {};
				return (
					e !== null &&
						e !== void 0 &&
						e.data_mapping &&
						e.data_mapping.length > 0 &&
						( e.data_mapping.filter( function ( _eLocal ) {
							return _eLocal.key && _eLocal.value;
						} ),
						e.data_mapping.some( function ( _eLocal2 ) {
							return ! (
								( ! _eLocal2.key && ! _eLocal2.value ) ||
								( _eLocal2.key && _eLocal2.value )
							);
						} ) &&
							( _tLocal3.data_mapping = ( 0, I18n.__ )(
								'Please fill in all field mapping keys and values',
								'ohmylms'
							) ) ),
					_tLocal3
				 );
			},
			C = function ( e ) {
				var _tLocal4 = R( e ),
					_nLocal2 = x( e ),
					_rLocal2 = q5( q5( {}, _tLocal4 ), _nLocal2 );
				return ( _( _rLocal2 ), 0 === Object.keys( _rLocal2 ).length );
			},
			P = function () {
				if ( r && 'function' === typeof r ) {
					r();
				}
				f( '1' );
				_( {} );
				o.clearWebhookFormData();
			},
			O = ( function () {
				var e,
					_nLocal3 =
						( ( e = V5().m( function _eLocal3() {
							var _nLocal4, _rLocal3, _iLocal;
							return V5().w(
								function ( _eLocal4 ) {
									for (;;) {
										switch ( ( _eLocal4.p = _eLocal4.n ) ) {
											case 0:
												if ( '1' !== p ) {
													_eLocal4.n = 1;
													break;
												}
												return (
													( _nLocal4 = R( S ) ),
													_( _nLocal4 ),
													0 ===
														Object.keys( _nLocal4 )
															.length && f( '2' ),
													_eLocal4.a( 2 )
												 );
											case 1:
												if ( s ) {
													_eLocal4.n = 10;
													break;
												}
												if ( C( S ) ) {
													_eLocal4.n = 2;
													break;
												}
												return _eLocal4.a( 2 );
											case 2:
												if (
													( d( ! 0 ),
													( _eLocal4.p = 3 ),
													( _rLocal3 = q5(
														q5( {}, S ),
														{},
														{
															data_mapping:
																JSON.stringify(
																	S.data_mapping
																),
														}
													) ),
													! t || ! t.id )
												) {
													_eLocal4.n = 5;
													break;
												}
												return (
													( _eLocal4.n = 4 ),
													o.updateWebhookById(
														t.id,
														_rLocal3
													)
												 );
											case 4:
												l(
													'success',
													( 0, I18n.__ )(
														'Webhook updated successfully!',
														'ohmylms'
													)
												);
												_eLocal4.n = 7;
												break;
											case 5:
												return (
													( _eLocal4.n = 6 ),
													o.createWebhook( _rLocal3 )
												 );
											case 6:
												l(
													'success',
													( 0, I18n.__ )(
														'Webhook created successfully!',
														'ohmylms'
													)
												);
											// Falls through to the shared async state cleanup.
											case 7:
												if (
													a &&
													'function' === typeof a
												) {
													a( _rLocal3 );
												}
												P();
												_eLocal4.n = 9;
												break;
											case 8:
												_eLocal4.p = 8;
												_iLocal = _eLocal4.v;
												showRequestError(
													'Error saving webhook:',
													_iLocal
												);
												l(
													'error',
													( 0, I18n.__ )(
														'Failed to save webhook. Please try again.',
														'ohmylms'
													)
												);
											// Falls through to the shared async state cleanup.
											case 9:
												return (
													( _eLocal4.p = 9 ),
													d( ! 1 ),
													_eLocal4.f( 9 )
												 );
											case 10:
												return _eLocal4.a( 2 );
										}
									}
								},
								_eLocal3,
								null,
								[ [ 3, 8, 9, 10 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal5 = this,
								_nLocal5 = _args;
							return new Promise( function ( _rLocal4, _aLocal ) {
								var _oLocal = e.apply( _tLocal5, _nLocal5 );
								function _iLocal2( _eLocal5 ) {
									G5(
										_oLocal,
										_rLocal4,
										_aLocal,
										_iLocal2,
										_lLocal,
										'next',
										_eLocal5
									);
								}
								function _lLocal( _eLocal6 ) {
									G5(
										_oLocal,
										_rLocal4,
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
					return _nLocal3.apply( this, _args2 );
				};
			} )();
		( 0, ReactHooks.useEffect )( function () {
			return function () {
				o.clearWebhookFormData();
			};
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				var e = ! 0;
				if ( e && S ) {
					if ( '1' === p ) {
						var _tLocal6 = R( S );
						_( _tLocal6 );
					} else {
						var _nLocal6 = x( S );
						_( _nLocal6 );
					}
				}
				return function () {
					e = ! 1;
				};
			},
			[ S, p ]
		);
		var k = [
				{
					label: (
						<Controls.TextWP
							as={ 'span' }
							size={ '16' }
							weight={ '500' }
						>
							{ ( 0, I18n.__ )( 'Details', 'ohmylms' ) }
						</Controls.TextWP>
					),
					key: '1',
					children: (
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP
								marginBottom={ 0 }
								padding={ 3 }
								marginTop={ 4 }
							>
								<MemoWebhookDetails
									errors={ h }
									setErrors={ _ }
									validate={ C }
								/>
							</Controls.SpacerWP>
						</Controls.CardWP>
					),
				},
				{
					label: (
						<Controls.TextWP
							as={ 'span' }
							size={ '16' }
							weight={ '500' }
						>
							{ ( 0, I18n.__ )( 'Data Mapping', 'ohmylms' ) }
						</Controls.TextWP>
					),
					key: '2',
					children: (
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP
								marginBottom={ 0 }
								padding={ 3 }
								marginTop={ 4 }
							>
								<MemoWebhookDataMapping errors={ h } />
							</Controls.SpacerWP>
						</Controls.CardWP>
					),
				},
			],
			j = ( 0, ReactHooks.useMemo )( function () {
				return {
					width: '830px',
					background: '#F5F5F5',
				};
			}, [] ),
			A = ( 0, ReactHooks.useMemo )(
				function () {
					return R( S );
				},
				[ S ]
			),
			M =
				'1' === p
					? Object.keys( A ).length > 0
					: Object.keys( h ).length > 0;
		return (
			<React.Fragment>
				{ c }
				{ n && (
					<Controls.ModalWP
						title={
							t !== null && t !== void 0 && t.id
								? ( 0, I18n.__ )( 'Edit Webhook', 'ohmylms' )
								: ( 0, I18n.__ )( 'Add Webhook', 'ohmylms' )
						}
						style={ j }
						onRequestClose={ P }
						shouldCloseOnEsc={ ! 0 }
						shouldCloseOnClickOutside={ ! 0 }
						className={ 'ohmylms-full-height-modal' }
						size={ 'large' }
					>
						{ E ? (
							<Controls.SkeletonWP rows={ 10 } />
						) : (
							<React.Fragment>
								<Controls.TabsWP
									items={ k }
									activekey={ p }
									onChange={ function () {} }
									className={
										'ohmylms-tab-has-custom-navigation'
									}
								/>
								<Controls.DividerWP marginStart={ 4 } />
								<Controls.SpacerWP marginTop={ 4 }>
									<Controls.FlexWP
										justify={ 'space-between' }
										align={ 'center' }
										gap={ 2 }
									>
										<div>
											{ '2' === p && (
												<Controls.ButtonWP
													variant={ 'secondary' }
													onClick={ function () {
														return f( '1' );
													} }
												>
													{ ( 0, I18n.__ )(
														'Back',
														'ohmylms'
													) }
												</Controls.ButtonWP>
											) }
										</div>
										<Controls.FlexWP
											justify={ 'flex-end' }
											align={ 'center' }
											gap={ 2 }
										>
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ P }
											>
												{ ( 0, I18n.__ )(
													'Cancel',
													'ohmylms'
												) }
											</Controls.ButtonWP>
											<Controls.ButtonWP
												variant={ 'primary' }
												onClick={ O }
												disabled={ M }
												isBusy={ s }
											>
												{ '1' === p
													? ( 0, I18n.__ )(
															'Next',
															'ohmylms'
														)
													: ( 0, I18n.__ )(
															'Save',
															'ohmylms'
														) }
											</Controls.ButtonWP>
										</Controls.FlexWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</React.Fragment>
						) }
					</Controls.ModalWP>
				) }
			</React.Fragment>
		);
	};
}
