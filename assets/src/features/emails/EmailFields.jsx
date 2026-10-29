/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { InfoIcon } from '../../shared/icons';
export function createEmailFields( readRuntime ) {
	return function EmailFields( props ) {
		const {
			Aee,
			I: Controls,
			Mee,
			React,
			T: StoreModule,
			W: RichText,
			b: I18n,
			g: ReactHooks,
			jee,
			ne,
			y: WordPressData,
		} = readRuntime();
		var t = props.setAdditionalContent,
			n = props.setFooterContent,
			r = props.setCourseSuggestionText,
			a = ( function ( e, _tLocal ) {
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
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( _nLocal =
										_nLocal.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( _rLocal = o.call( _nLocal ) )
											.done ) &&
										( l.push( _rLocal.value ),
										l.length !== _tLocal2 );
										c = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								u = ! 0;
								_aLocal = _eLocal3;
							} finally {
								try {
									if (
										! c &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal.return
										) &&
										( ( i = _nLocal.return() ),
										Object( i ) !== i )
									) {
										return;
									}
								} finally {
									if ( u ) {
										throw _aLocal;
									}
								}
							}
							return l;
						}
					} )( e, _tLocal ) ||
					Mee( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( '' ), 2 ),
			o = ( a[ 0 ], a[ 1 ] ),
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getEmail();
			}, [] ),
			l = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateEmail,
			c = function ( e, _tLocal3 ) {
				if ( 'recipient_email' === e ) {
					var _nLocal2 = _tLocal3
							.split( ',' )
							.map( function ( _eLocal4 ) {
								return ( function ( _eLocal5 ) {
									return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
										_eLocal5
									);
								} )( _eLocal4.trim() )
									? _eLocal4.trim()
									: null;
							} )
							.filter( Boolean ),
						_rLocal2 =
							( i === null || i === void 0
								? void 0
								: i.recipient_email ) || [];
					l(
						jee(
							{},
							e,
							[].concat( Aee( _rLocal2 ), Aee( _nLocal2 ) )
						)
					);
					o( '' );
				} else {
					l( jee( {}, e, _tLocal3 ) );
				}
			};
		return (
			<Controls.CardWP isBorderless={ ! 0 }>
				<Controls.SpacerWP padding={ 5 } gap={ 4 }>
					<Controls.FlexWP direction={ 'column' } gap={ 3 }>
						<Controls.HeadingWP level={ '3' }>
							{ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.FlexItemWP>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP size={ 16 }>
									{ ( 0, I18n.__ )( 'Subject', 'ohmylms' ) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Set the subject of the email.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<RichText.A
								value={
									i === null || i === void 0
										? void 0
										: i.subject
								}
								onChange={ function ( e ) {
									return c( 'subject', e );
								} }
							/>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP size={ 16 }>
									{ ( 0, I18n.__ )(
										'Email heading',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Set the heading of the email.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<Controls.InputWP
								value={
									i === null || i === void 0
										? void 0
										: i.heading
								}
								onChange={ function ( e ) {
									return c( 'heading', e );
								} }
							/>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP>
							<Controls.FlexWP
								justify={ 'start' }
								align={ 'center' }
								gap={ '2' }
							>
								<Controls.TextWP size={ 16 }>
									{ ( 0, I18n.__ )(
										'Additional content',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.TooltipWP
									title={ ( 0, I18n.__ )(
										'Add additional content to the email.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Controls.TooltipWP>
							</Controls.FlexWP>
							<Controls.SpacerWP />
							<Controls.CardWP>
								<Controls.SpacerWP
									paddingX={ 2 }
									marginBottom={ 0 }
									paddingY={ 1 }
								>
									{ React.createElement( ne, {
										onContentChange( e ) {
											return t( e );
										},
										content:
											i === null || i === void 0
												? void 0
												: i.additional_content,
										commandsConfig: {
											image: ! 1,
											horizontalRule: ! 1,
											customHTML: ! 1,
										},
										showAddButton: ! 1,
										placeholder: ( 0, I18n.__ )(
											'Add additional content to the email.',
											'ohmylms'
										),
									} ) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexItemWP>
						{ ( i === null || i === void 0
							? void 0
							: i.footer_text ) && (
							<Controls.FlexItemWP>
								<Controls.FlexWP
									justify={ 'start' }
									align={ 'center' }
									gap={ '2' }
								>
									<Controls.TextWP size={ 16 }>
										{ ( 0, I18n.__ )(
											'Footer Text',
											'ohmylms'
										) }
									</Controls.TextWP>
									<Controls.TooltipWP
										title={ ( 0, I18n.__ )(
											'Set the footer text of the email.',
											'ohmylms'
										) }
									>
										<InfoIcon />
									</Controls.TooltipWP>
								</Controls.FlexWP>
								<Controls.SpacerWP />
								<Controls.CardWP>
									<Controls.SpacerWP
										paddingX={ 2 }
										marginBottom={ 0 }
										paddingY={ 1 }
									>
										{ React.createElement( ne, {
											onContentChange( e ) {
												return n( e );
											},
											content:
												i === null || i === void 0
													? void 0
													: i.footer_text,
											commandsConfig: {
												image: ! 1,
												horizontalRule: ! 1,
												customHTML: ! 1,
											},
											showAddButton: ! 1,
											placeholder: ( 0, I18n.__ )(
												'Add footer text to the email.',
												'ohmylms'
											),
										} ) }
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.FlexItemWP>
						) }
						{ ( i === null || i === void 0
							? void 0
							: i.course_suggestion_text ) && (
							<Controls.FlexItemWP>
								<Controls.FlexWP
									size={ 16 }
									justify={ 'start' }
									align={ 'center' }
									gap={ '2' }
								>
									<Controls.TextWP>
										{ ( 0, I18n.__ )(
											'Course Suggestion Text',
											'ohmylms'
										) }
									</Controls.TextWP>
									<Controls.TooltipWP
										title={ ( 0, I18n.__ )(
											'Set the course suggestion text.',
											'ohmylms'
										) }
									>
										<InfoIcon />
									</Controls.TooltipWP>
								</Controls.FlexWP>
								<Controls.SpacerWP />
								<Controls.CardWP>
									<Controls.SpacerWP
										paddingX={ 2 }
										marginBottom={ 0 }
										paddingY={ 1 }
									>
										{ React.createElement( ne, {
											onContentChange( e ) {
												return r( e );
											},
											content:
												i === null || i === void 0
													? void 0
													: i.course_suggestion_text,
											commandsConfig: {
												image: ! 1,
												horizontalRule: ! 1,
												customHTML: ! 1,
											},
											showAddButton: ! 1,
											placeholder: ( 0, I18n.__ )(
												'Add course suggestion text.',
												'ohmylms'
											),
										} ) }
									</Controls.SpacerWP>
								</Controls.CardWP>
							</Controls.FlexItemWP>
						) }
						{ ( i === null || i === void 0
							? void 0
							: i.hasOwnProperty( 'button_text' ) ) && (
							<Controls.FlexItemWP>
								<Controls.FlexWP
									justify={ 'start' }
									align={ 'center' }
									gap={ '2' }
								>
									<Controls.TextWP size={ 16 }>
										{ ( 0, I18n.__ )(
											'Email Button',
											'ohmylms'
										) }
									</Controls.TextWP>
									<Controls.TooltipWP
										title={ ( 0, I18n.__ )(
											'Set the email button text.',
											'ohmylms'
										) }
									>
										<InfoIcon />
									</Controls.TooltipWP>
								</Controls.FlexWP>
								<Controls.SpacerWP />
								<Controls.InputWP
									value={
										i === null || i === void 0
											? void 0
											: i.button_text
									}
									onChange={ function ( e ) {
										return c( 'button_text', e );
									} }
								/>
							</Controls.FlexItemWP>
						) }
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
