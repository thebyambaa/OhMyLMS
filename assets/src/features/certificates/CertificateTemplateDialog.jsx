/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCertificateTemplateDialog( readRuntime ) {
	return function CertificateTemplateDialog( props ) {
		const {
			I: Controls,
			React,
			_,
			ate,
			b: I18n,
			f: Router,
			g: ReactHooks,
			hB,
			rte: MemoCertificateTemplateCard,
		} = readRuntime();
		var t = props.openModal,
			n = props.setOpenModal,
			r = ate( ( 0, ReactHooks.useState )( '' ), 2 ),
			a =
				( r[ 0 ],
				r[ 1 ],
				ate( ( 0, ReactHooks.useState )( 'industry' ), 2 ) ),
			o = ( a[ 0 ], a[ 1 ], ate( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			i = ( o[ 0 ], o[ 1 ], ate( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			l = i[ 0 ],
			c = i[ 1 ];
		return (
			( 0, Router.Zp )(),
			( 0, ReactHooks.useEffect )( function () {
				c( ! 0 );
				setTimeout( function () {
					c( ! 1 );
				}, 300 );
			}, [] ),
			(
				<React.Fragment>
					{ t && (
						<Controls.ModalWP
							title={ ( 0, I18n.__ )(
								'Choose a template',
								'ohmylms'
							) }
							style={ {
								maxWidth: '1200px',
								minWidth: '900px',
							} }
							shouldCloseOnEsc={ ! 0 }
							shouldCloseOnClickOutside={ ! 0 }
							onRequestClose={ function () {
								return n( ! 1 );
							} }
						>
							<Controls.FlexWP wrap={ 'wrap' } gap={ 3 }>
								{ l ? (
									<_.A active={ ! 0 } />
								) : (
									hB
										.slice()
										.reverse()
										.map( function ( e, _tLocal ) {
											return (
												<Controls.FlexItemWP
													key={ _tLocal }
													isBlock={ ! 0 }
													style={ {
														width: '100%',
													} }
												>
													<MemoCertificateTemplateCard
														template={ e }
														setOpenModal={ n }
													/>
												</Controls.FlexItemWP>
											);
										} )
								) }
							</Controls.FlexWP>
						</Controls.ModalWP>
					) }
				</React.Fragment>
			 )
		 );
	};
}
