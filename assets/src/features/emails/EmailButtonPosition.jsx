/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createEmailButtonPosition( readRuntime ) {
	return function EmailButtonPosition() {
		const {
			I: Controls,
			React,
			T: StoreModule,
			Tt,
			b: I18n,
			d4,
			m4,
			p4,
			q,
			s4,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).selectSettingsMap();
			}, [] ),
			t = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateSettings;
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'start' }
					gap={ '4' }
					className={ 'ohmylms-email-button-position-wrapper' }
				>
					<Controls.FlexItemWP
						style={ {
							maxWidth: '300px',
						} }
					>
						<Controls.SpacerWP marginY={ 5 }>
							<Controls.HeadingWP level={ '4' }>
								{ ( 0, I18n.__ )(
									'Button Position',
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.TextWP>
								{ ( 0, I18n.__ )(
									'Select the alignment of your Call To Action buttons in the email.',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.SpacerWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							marginLeft: 'auto',
						} }
						className={ 'ohmylms-email-button-position-options' }
					>
						<Controls.RadioGroupWP
							onChange={ function ( _eLocal2 ) {
								return ( function ( _eLocal3, n, r ) {
									t(
										'ohmylms_email_button_possition',
										( function (
											_eLocal4,
											_tLocal,
											_nLocal
										) {
											return (
												( _tLocal = ( function (
													_eLocal5
												) {
													var _tLocal2 = ( function (
														_eLocal6
													) {
														if (
															'object' !==
																p4(
																	_eLocal6
																) ||
															! _eLocal6
														) {
															return _eLocal6;
														}
														var _tLocal3 =
															_eLocal6[
																Symbol
																	.toPrimitive
															];
														if (
															void 0 !== _tLocal3
														) {
															var _nLocal2 =
																_tLocal3.call(
																	_eLocal6,
																	'string'
																);
															if (
																'object' !==
																p4( _nLocal2 )
															) {
																return _nLocal2;
															}
															throw new TypeError(
																'@@toPrimitive must return a primitive value.'
															);
														}
														return String(
															_eLocal6
														);
													} )( _eLocal5 );
													return 'symbol' ===
														p4( _tLocal2 )
														? _tLocal2
														: _tLocal2 + '';
												} )( _tLocal ) ) in _eLocal4
													? Object.defineProperty(
															_eLocal4,
															_tLocal,
															{
																value: _nLocal,
																enumerable: ! 0,
																configurable:
																	! 0,
																writable: ! 0,
															}
														)
													: ( _eLocal4[ _tLocal ] =
															_nLocal ),
												_eLocal4
											 );
										} )( {}, 'value', r )
									);
								} )( 0, 0, _eLocal2 );
							} }
							value={
								e === null || e === void 0
									? void 0
									: e.ohmylms_email_button_possition
							}
							options={ [
								{
									value: 'left',
									label: (
										<q.Icon
											icon={ s4.A }
											width={ '24px' }
											height={ '24px' }
										/>
									),
								},
								{
									value: 'center',
									label: (
										<q.Icon
											icon={ d4.A }
											width={ '24px' }
											height={ '24px' }
										/>
									),
								},
								{
									value: 'right',
									label: (
										<q.Icon
											icon={ m4.A }
											width={ '24px' }
											height={ '24px' }
										/>
									),
								},
							] }
							isBlock={ ! 1 }
						/>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
				<Tt.A />
			</React.Fragment>
		);
	};
}
