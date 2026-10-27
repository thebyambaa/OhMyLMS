/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createEmailEditorPage( readRuntime ) {
	return function EmailEditorPage() {
		const {
			Ea,
			I: Controls,
			Nr,
			React,
			T: StoreModule,
			b: I18n,
			f: Router,
			g: ReactHooks,
			qee: MemoEmailEditor,
			v,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, Router.g )().id,
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getEmail();
			}, [] ),
			n = ( 0, Router.Zp )();
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var r;
					if ( ! (
						Boolean( t ) &&
						( t === null ||
						t === void 0 ||
						null === ( r = t.basic ) ||
						void 0 === r
							? void 0
							: r.id ) === e
					) ) {
						n( '/settings/emails-settings' );
					}
				},
				[ e, t, n ]
			),
			(
				<Ea>
					<Controls.SpacerWP padding={ 5 }>
						<Controls.FlexWP
							justify={ 'start' }
							align={ 'center' }
							gap={ '2' }
						>
							<Controls.TextWP variant={ 'muted' } size={ '16' }>
								<v.Link
									to={ '/settings/emails-settings' }
									style={ {
										boxShadow: 'none',
										textDecoration: 'none',
										color: 'inherit',
									} }
								>
									<Controls.FlexWP
										gap={ 4 }
										justify={ 'flex-start' }
									>
										<Nr />
										<Controls.HeadingWP
											level={ 2 }
											size={ 20 }
										>
											{ ( function ( _eLocal2 ) {
												return _eLocal2
													.split( '_' )
													.map(
														function ( _eLocal3 ) {
															return (
																_eLocal3
																	.charAt( 0 )
																	.toUpperCase() +
																_eLocal3
																	.slice( 1 )
																	.toLowerCase()
															);
														}
													)
													.join( ' ' );
											} )( e ) ||
												( 0, I18n.__ )(
													'Email Editor',
													'ohmylms'
												) }
										</Controls.HeadingWP>
									</Controls.FlexWP>
								</v.Link>
							</Controls.TextWP>
						</Controls.FlexWP>
						<Controls.DividerWP marginStart={ 4 } />
						<MemoEmailEditor />
					</Controls.SpacerWP>
				</Ea>
			 )
		 );
	};
}
