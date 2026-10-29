/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { InfoIcon } from '../../shared/icons';
export function createEmailSenderOptions( readRuntime ) {
	return function EmailSenderOptions() {
		const {
			I: Controls,
			React,
			T: StoreModule,
			W: RichText,
			b: I18n,
			g4,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).selectSettingsMap();
			}, [] ),
			t = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateSettings,
			n = function ( _eLocal2, _nLocal, r ) {
				t(
					_eLocal2,
					( function ( _eLocal3, _tLocal, _nLocal2 ) {
						return (
							( _tLocal = ( function ( _eLocal4 ) {
								var _tLocal2 = ( function ( _eLocal5 ) {
									if (
										'object' !== g4( _eLocal5 ) ||
										! _eLocal5
									) {
										return _eLocal5;
									}
									var _tLocal3 =
										_eLocal5[ Symbol.toPrimitive ];
									if ( void 0 !== _tLocal3 ) {
										var _nLocal3 = _tLocal3.call(
											_eLocal5,
											'string'
										);
										if ( 'object' !== g4( _nLocal3 ) ) {
											return _nLocal3;
										}
										throw new TypeError(
											'@@toPrimitive must return a primitive value.'
										);
									}
									return String( _eLocal5 );
								} )( _eLocal4 );
								return 'symbol' === g4( _tLocal2 )
									? _tLocal2
									: _tLocal2 + '';
							} )( _tLocal ) ) in _eLocal3
								? Object.defineProperty( _eLocal3, _tLocal, {
										value: _nLocal2,
										enumerable: ! 0,
										configurable: ! 0,
										writable: ! 0,
									} )
								: ( _eLocal3[ _tLocal ] = _nLocal2 ),
							_eLocal3
						 );
					} )( {}, _nLocal, r )
				);
			};
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'start' }
					gap={ '4' }
				>
					<Controls.FlexItemWP
						style={ {
							maxWidth: '300px',
						} }
					>
						<Controls.SpacerWP marginY={ 5 }>
							<Controls.HeadingWP level={ '4' }>
								{ ( 0, I18n.__ )(
									'Email sender options',
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.TextWP>
								{ ( 0, I18n.__ )(
									'Set up sender details and email footer options.',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.SpacerWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							width: 'calc(100% - 300px - 4*4px)',
						} }
					>
						<Controls.SpacerWP marginY={ 5 }>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP>
									{ ( 0, I18n.__ )(
										'Sender Email Address',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Enter the email address that will be used to send emails.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<Controls.InputWP
								value={
									e === null || e === void 0
										? void 0
										: e.ohmylms_email_sender_email_address
								}
								onChange={ function ( _eLocal6 ) {
									return n(
										'ohmylms_email_sender_email_address',
										'value',
										_eLocal6
									);
								} }
							/>
						</Controls.SpacerWP>
						<Controls.SpacerWP marginY={ 5 }>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP>
									{ ( 0, I18n.__ )(
										'Sender Name',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Enter the name that will be used to send emails.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<Controls.InputWP
								value={
									e === null || e === void 0
										? void 0
										: e.ohmylms_email_sender_name
								}
								onChange={ function ( _eLocal7 ) {
									return n(
										'ohmylms_email_sender_name',
										'value',
										_eLocal7
									);
								} }
							/>
						</Controls.SpacerWP>
						<Controls.SpacerWP marginY={ 5 }>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP>
									{ ( 0, I18n.__ )(
										'Email Footer Text',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Enter the footer text that will be used to send emails.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<RichText.A
								value={
									e === null || e === void 0
										? void 0
										: e.ohmylms_email_footer_text
								}
								onChange={ function ( _eLocal8 ) {
									return n(
										'ohmylms_email_footer_text',
										'value',
										_eLocal8
									);
								} }
							/>
						</Controls.SpacerWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
