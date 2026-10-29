/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createPaymentGatewaysSettings( readRuntime ) {
	return function PaymentGatewaysSettings( props ) {
		const {
			J0,
			React,
			T: StoreModule,
			b: I18n,
			d1,
			f1,
			g: ReactHooks,
			l,
			s1,
			u1,
			v1,
			y: WordPressData,
		} = readRuntime();
		var t = props.handleSave,
			n = props.isLoading,
			r = props.setIsLoading,
			a = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getPaymentSettings();
			}, [] ),
			o = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			i = v1( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = v1( ( 0, ReactHooks.useState )( null ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = v1( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			h = ( 0, ReactHooks.useMemo )(
				function () {
					return {
						handleSave: t,
						isLoading: n,
						setIsLoading: r,
						handleManage( e ) {
							m( e );
							u( ! 0 );
						},
					};
				},
				[ t, n, r ]
			),
			_ = ( 0, ReactHooks.useCallback )(
				f1(
					d1().m( function e() {
						var _tLocal;
						return d1().w( function ( _eLocal ) {
							for (;;) {
								switch ( _eLocal.n ) {
									case 0:
										return (
											o.setLoadingSetting( ! 0 ),
											( _eLocal.n = 1 ),
											l()( {
												path: 'ohmylms/v1/settings/payment-gateway',
											} )
										 );
									case 1:
										_tLocal = _eLocal.v;
										o.setPaymentSettings( _tLocal );
										o.setLoadingSetting( ! 1 );
										v( ! 1 ); // Falls through to the shared async state cleanup.
									case 2:
										return _eLocal.a( 2 );
								}
							}
						}, e );
					} )
				),
				[ o ]
			);
		( 0, ReactHooks.useEffect )(
			function () {
				_();
			},
			[ _ ]
		);
		var w,
			E = function () {
				u( ! 1 );
				setTimeout( function () {
					return m( null );
				}, 300 );
			};
		return (
			<React.Fragment>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP
						padding={ 6 }
						marginTop={ 2.5 }
						marginBottom={ 0 }
					>
						{ c ? (
							<React.Fragment>
								<Controls.FlexWP
									gap={ 2 }
									align={ 'center' }
									justify={ 'flex-start' }
								>
									<Controls.ButtonWP
										variant={ 'secondary' }
										onClick={ E }
										className={ 'ohmylms-back-button '.concat(
											d === null || d === void 0
												? void 0
												: d.id
										) }
										aria-label={ ( 0, I18n.__ )(
											'Back to payment gateways',
											'ohmylms'
										) }
									>
										<svg
											width={ '17' }
											height={ '12' }
											fill={ 'none' }
											viewBox={ '0 0 17 12' }
											xmlns={
												'http://www.w3.org/2000/svg'
											}
										>
											<path
												fill={ '#7A8B9A' }
												d={
													'M16.1 5.2H2.9l3.7-3.7-1-1L0 6l5.6 5.5 1-1-3.7-3.7h13.2V5.2z'
												}
											/>
										</svg>
									</Controls.ButtonWP>
									<Controls.HeadingWP
										level={ 3 }
										size={ 18 }
										weight={ 600 }
										color={ '#000D25' }
									>
										{ 'offline' ===
										( d === null || d === void 0
											? void 0
											: d.id ) ? (
											<React.Fragment>
												{ d === null || d === void 0
													? void 0
													: d.name }
												{ ( 0, I18n.__ )(
													' Payment',
													'ohmylms'
												) }
											</React.Fragment>
										) : (
											<React.Fragment>
												{ d === null || d === void 0
													? void 0
													: d.name }
												{ ( 0, I18n.__ )(
													' Payment Method',
													'ohmylms'
												) }
											</React.Fragment>
										) }
									</Controls.HeadingWP>
								</Controls.FlexWP>
								<Controls.SpacerWP marginBottom={ 3 } />
								{ ( function () {
									if ( ! d ) {
										return null;
									}
									if (
										( _rLocal = d ).settings_fields &&
										Array.isArray(
											_rLocal.settings_fields
										) &&
										_rLocal.settings_fields.length > 0
									) {
										var e = 'ohmylms_'.concat(
												d.id,
												'_settings'
											),
											_nLocal = a[ e ] || {};
										return React.createElement( u1, {
											gateway: d,
											onSave: t,
											onCancel: E,
											settings: _nLocal,
										} );
									}
									var _rLocal;
									return (
										<Controls.CardWP
											isBorderless={ ! 0 }
											variant={ 'secondary' }
										>
											<Controls.SpacerWP
												padding={ 4 }
												margin={ 0 }
												marginBottom={ 0 }
											>
												<Controls.TextWP
													align={ 'center' }
													color={ '#666' }
												>
													{ ( 0, I18n.__ )(
														'No configuration available for this gateway.',
														'ohmylms'
													) }
												</Controls.TextWP>
											</Controls.SpacerWP>
										</Controls.CardWP>
									);
								} )() }
							</React.Fragment>
						) : (
							<React.Fragment>
								<Controls.FlexWP
									justify={ 'flex-start' }
									gap={ 5 }
									wrap={ ! 0 }
									className={ 'ohmylms-payment-wrapper' }
								>
									{
										( ( w = ( function () {
											var e,
												_tLocal2 =
													( null === ( e = window ) ||
													void 0 === e ||
													null ===
														( e =
															e.ohmylms_params ) ||
													void 0 === e
														? void 0
														: e.payment_gateways ) ||
													{},
												_nLocal2 = [];
											return (
												Array.isArray( _tLocal2 )
													? ( _nLocal2 = _tLocal2 )
													: 'object' ===
															s1( _tLocal2 ) &&
														null !== _tLocal2 &&
														( _nLocal2 =
															Object.values(
																_tLocal2
															) ),
												_nLocal2.filter(
													function ( _eLocal2 ) {
														return !! (
															_eLocal2 &&
															_eLocal2.id &&
															_eLocal2.title
														);
													}
												)
											 );
										} )() ),
										( () => {
											if ( f ) {
												return (
													<Controls.FlexBlockWP
														className={
															'ohmylms-payment-flex-item'
														}
													>
														<Controls.CardWP
															isBorderless={ ! 0 }
															variant={
																'secondary'
															}
														>
															<Controls.SpacerWP
																padding={ 4 }
																margin={ 0 }
																marginBottom={
																	0
																}
															>
																<Controls.TextWP
																	align={
																		'center'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'Loading payment gateways...',
																		'ohmylms'
																	) }
																</Controls.TextWP>
															</Controls.SpacerWP>
														</Controls.CardWP>
													</Controls.FlexBlockWP>
												);
											}
											if ( 0 === w.length ) {
												return (
													<Controls.FlexBlockWP
														className={
															'ohmylms-payment-flex-item'
														}
													>
														<Controls.CardWP
															isBorderless={ ! 0 }
															variant={
																'secondary'
															}
														>
															<Controls.SpacerWP
																padding={ 4 }
																margin={ 0 }
																marginBottom={
																	0
																}
															>
																<Controls.TextWP
																	align={
																		'center'
																	}
																	color={
																		'#666'
																	}
																>
																	{ ( 0,
																	I18n.__ )(
																		'No payment gateways available. Please check your configuration.',
																		'ohmylms'
																	) }
																</Controls.TextWP>
															</Controls.SpacerWP>
														</Controls.CardWP>
													</Controls.FlexBlockWP>
												);
											}
											return w.map( function ( e ) {
												var _tLocal3 =
														'ohmylms_'.concat(
															e.id,
															'_settings'
														),
													_nLocal3 =
														a[ _tLocal3 ] || {};
												return (
													<Controls.FlexBlockWP
														key={ e.id }
														className={
															'ohmylms-payment-flex-item'
														}
													>
														<J0
															gateway={ e }
															config={ h }
															settings={
																_nLocal3
															}
														/>
													</Controls.FlexBlockWP>
												);
											} );
										} )() )
									}
								</Controls.FlexWP>
							</React.Fragment>
						) }
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
