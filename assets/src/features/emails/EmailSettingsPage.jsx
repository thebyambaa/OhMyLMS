/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createEmailSettingsPage( readRuntime ) {
	return function EmailSettingsPage() {
		const {
			D: Buttons,
			HG,
			I: Controls,
			R4: MemoEmailPersonalization,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			u4: MemoEmailTemplateList,
			x4,
			y: WordPressData,
		} = readRuntime();
		HG( 'ohmylms', 'emails' );
		var e = ( function ( _eLocal, t ) {
				return (
					( function ( _eLocal2 ) {
						if ( Array.isArray( _eLocal2 ) ) {
							return _eLocal2;
						}
					} )( _eLocal ) ||
					( function ( _eLocal3, _tLocal ) {
						var n =
							_eLocal3 === null || _eLocal3 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal3[ Symbol.iterator ] ) ||
									_eLocal3[ '@@iterator' ];
						if ( n !== null && n !== void 0 ) {
							var r,
								a,
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( n = n.call( _eLocal3 ) ).next ),
									0 === _tLocal )
								) {
									if ( Object( n ) !== n ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( r = o.call( n ) ).done ) &&
										( l.push( r.value ),
										l.length !== _tLocal );
										c = ! 0
									) {}
								}
							} catch ( _eLocal4 ) {
								u = ! 0;
								a = _eLocal4;
							} finally {
								try {
									if (
										! c &&
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
									if ( u ) {
										throw a;
									}
								}
							}
							return l;
						}
					} )( _eLocal, t ) ||
					( function ( _eLocal5, _tLocal2 ) {
						if ( _eLocal5 ) {
							if ( 'string' === typeof _eLocal5 ) {
								return x4( _eLocal5, _tLocal2 );
							}
							var n = {}.toString.call( _eLocal5 ).slice( 8, -1 );
							return (
								'Object' === n &&
									_eLocal5.constructor &&
									( n = _eLocal5.constructor.name ),
								( () => {
									if ( 'Map' === n || 'Set' === n ) {
										return Array.from( _eLocal5 );
									}
									if (
										'Arguments' === n ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											n
										)
									) {
										return x4( _eLocal5, _tLocal2 );
									}
									return void 0;
								} )()
							 );
						}
					} )( _eLocal, t ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = useNotifications().contextHolder,
			a = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).getEmailSettings;
		return (
			( 0, ReactHooks.useEffect )( function () {
				a();
			}, [] ),
			(
				<React.Fragment>
					{ r }
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						className={ 'ohmylms-full-screen-height' }
					>
						<Controls.SpacerWP
							padding={ 4 }
							paddingTop={ 1 }
							marginTop={ 4 }
							marginBottom={ 0 }
						>
							<Buttons.A
								variant={ 'text' }
								onClick={ function () {
									n( ! 0 );
								} }
								style={ {
									position: 'absolute',
									right: '12px',
									top: '8px',
									color: '#6E42D3',
								} }
							>
								<Controls.FlexWP align={ 'center' }>
									<svg
										xmlns={ 'http://www.w3.org/2000/svg' }
										width={ '24' }
										height={ '24' }
										viewBox={ '0 0 24 24' }
										fill={ 'none' }
									>
										<path
											fillRule={ 'evenodd' }
											clipRule={ 'evenodd' }
											d={
												'M10.2892 4.836C10.328 4.6025 10.4484 4.39034 10.6289 4.23726C10.8095 4.08418 11.0385 4.00011 11.2752 4H12.5812C12.818 3.99987 13.0473 4.08384 13.228 4.23694C13.4088 4.39004 13.5293 4.60233 13.5682 4.836L13.8122 6.302C14.5992 6.562 15.3152 6.981 15.9202 7.52L17.3132 6.998C17.5348 6.91523 17.7786 6.91372 18.0012 6.99373C18.2239 7.07374 18.4109 7.23009 18.5292 7.435L19.1822 8.565C19.3008 8.77005 19.3429 9.01056 19.3007 9.2437C19.2586 9.47683 19.1351 9.68744 18.9522 9.838L17.8042 10.782C17.9699 11.5852 17.9699 12.4138 17.8042 13.217L18.9532 14.163C19.1358 14.3135 19.2592 14.5239 19.3013 14.7568C19.3434 14.9898 19.3016 15.2301 19.1832 15.435L18.5302 16.565C18.4119 16.7699 18.2249 16.9263 18.0022 17.0063C17.7796 17.0863 17.5358 17.0848 17.3142 17.002L15.9202 16.48C15.3152 17.02 14.6002 17.438 13.8122 17.698L13.5682 19.164C13.5293 19.3977 13.4088 19.61 13.228 19.7631C13.0473 19.9162 12.818 20.0001 12.5812 20H11.2752C11.0385 19.9999 10.8095 19.9158 10.6289 19.7627C10.4484 19.6097 10.328 19.3975 10.2892 19.164L10.0452 17.698C9.26659 17.4405 8.54913 17.0259 7.93717 16.48L6.54317 17.002C6.32153 17.0851 6.07759 17.0869 5.85474 17.0071C5.6319 16.9273 5.4446 16.771 5.32617 16.566L4.67317 15.435C4.55477 15.2301 4.51291 14.9898 4.55502 14.7568C4.59714 14.5239 4.7205 14.3135 4.90317 14.163L6.05217 13.217C5.88647 12.4138 5.88647 11.5852 6.05217 10.782L4.90417 9.838C4.7215 9.68749 4.59814 9.47707 4.55602 9.24416C4.51391 9.01125 4.55577 8.77095 4.67417 8.566L5.32717 7.435C5.44544 7.22986 5.63268 7.07335 5.85553 6.99333C6.07839 6.91331 6.32242 6.91496 6.54417 6.998L7.93717 7.52C8.54912 6.97407 9.26658 6.55952 10.0452 6.302L10.2892 4.836ZM14.9292 12C14.9292 12.394 14.8516 12.7841 14.7008 13.1481C14.55 13.512 14.3291 13.8427 14.0505 14.1213C13.7719 14.3999 13.4412 14.6209 13.0772 14.7716C12.7132 14.9224 12.3231 15 11.9292 15C11.5352 15 11.1451 14.9224 10.7811 14.7716C10.4171 14.6209 10.0864 14.3999 9.80785 14.1213C9.52927 13.8427 9.30829 13.512 9.15753 13.1481C9.00677 12.7841 8.92917 12.394 8.92917 12C8.92917 11.2044 9.24524 10.4413 9.80785 9.87868C10.3705 9.31607 11.1335 9 11.9292 9C12.7248 9 13.4879 9.31607 14.0505 9.87868C14.6131 10.4413 14.9292 11.2044 14.9292 12Z'
											}
											fill={ '#6E42D3' }
										/>
									</svg>
									<Controls.TextWP
										size={ '13px' }
										color={ '#6E42D3' }
									>
										{ ( 0, I18n.__ )(
											'Personalize Email',
											'ohmylms'
										) }
									</Controls.TextWP>
								</Controls.FlexWP>
							</Buttons.A>
							<MemoEmailTemplateList />
							{ t && (
								<MemoEmailPersonalization
									isOpen={ t }
									setIsOpen={ n }
								/>
							) }
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
