import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createMigrationImport( readRuntime ) {
	return function MigrationImport() {
		const {
			He,
			React,
			T: StoreModule,
			a6,
			b: I18n,
			f: Router,
			g: ReactHooks,
			i6,
			l6,
			r6: MemoMigrationFileDropzone,
			u6,
			y: WordPressData,
		} = readRuntime();
		var e = l6( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = l6( ( 0, ReactHooks.useState )( null ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = l6( ( 0, ReactHooks.useState )( 'json' ), 2 ),
			l = i[ 0 ],
			c = i[ 1 ],
			u = l6( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			p = ( 0, Router.Zp )(),
			v = true,
			h = ( function () {
				var _eLocal,
					_tLocal =
						( ( _eLocal = a6().m( function _eLocal2() {
							var _tLocal2, _rLocal, _oLocal;
							return a6().w(
								function ( _eLocal3 ) {
									for (;;) {
										switch ( ( _eLocal3.p = _eLocal3.n ) ) {
											case 0:
												if ( 'json' !== l || v ) {
													_eLocal3.n = 1;
													break;
												}
												return (
													d( ! 0 ),
													_eLocal3.a( 2 )
												 );
											case 1:
												if ( a ) {
													_eLocal3.n = 2;
													break;
												}
												return _eLocal3.a( 2 );
											case 2:
												if (
													( ( _eLocal3.p = 2 ),
													n( ! 0 ),
													'scorm' !== l )
												) {
													_eLocal3.n = 4;
													break;
												}
												return (
													( _eLocal3.n = 3 ),
													m.importScormCourse( a )
												 );
											case 3:
												_rLocal = _eLocal3.v;
												_eLocal3.n = 6;
												break;
											case 4:
												return (
													( _eLocal3.n = 5 ),
													m.importCourse( a )
												 );
											case 5:
												_rLocal = _eLocal3.v;
											// Falls through to the shared async state cleanup.
											case 6:
												if (
													( ( _nullableValue ) =>
														_nullableValue !==
															null &&
														_nullableValue !==
															void 0 )(
														( _tLocal2 = _rLocal )
													) &&
													_tLocal2.success
												) {
													p( '/courses' );
												}
												_eLocal3.n = 8;
												break;
											case 7:
												_eLocal3.p = 7;
												_oLocal = _eLocal3.v;
												showRequestError(
													'Error importing course:',
													_oLocal
												);
											// Falls through to the shared async state cleanup.
											case 8:
												return (
													( _eLocal3.p = 8 ),
													n( ! 1 ),
													_eLocal3.f( 8 )
												 );
											case 9:
												return _eLocal3.a( 2 );
										}
									}
								},
								_eLocal2,
								null,
								[ [ 2, 7, 8, 9 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal3 = this,
								_nLocal = _args;
							return new Promise( function ( _rLocal2, _aLocal ) {
								var _oLocal2 = _eLocal.apply(
									_tLocal3,
									_nLocal
								);
								function _iLocal( _eLocal4 ) {
									i6(
										_oLocal2,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'next',
										_eLocal4
									);
								}
								function _lLocal( _eLocal5 ) {
									i6(
										_oLocal2,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'throw',
										_eLocal5
									);
								}
								_iLocal( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _tLocal.apply( this, _args2 );
				};
			} )();
		return (
			<React.Fragment>
				<Controls.CardWP isBorderless={ ! 0 } padding={ '24px' }>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						padding={ '24px 16px' }
					>
						<Controls.FlexWP
							direction={ 'column' }
							gap={ 6 }
							justify={ 'center' }
							align={ 'center' }
							style={ {
								textAlign: 'center',
								minHeight: '258px',
							} }
						>
							{ React.createElement( u6, null ) }
							<Controls.HeadingWP
								as={ 'h3' }
								size={ '20' }
								weight={ '400' }
								color={ '#444d5e' }
								style={ {
									margin: 0,
								} }
							>
								{ ( 0, I18n.__ )(
									'No supported plugin detected on this site.',
									'ohmylms'
								) }
							</Controls.HeadingWP>
							<Controls.TextWP
								as={ 'p' }
								size={ '14' }
								color={ '#000d25' }
								align={ 'center' }
								weight={ '400' }
								style={ {
									margin: 0,
									maxWidth: '729px',
								} }
							>
								{ ( 0, I18n.__ )(
									'We currently support migration from ',
									'ohmylms'
								) }
								<strong>
									{ ( 0, I18n.__ )(
										'Tutor LMS, LearnPress, LearDash and MasterStudy LMS. ',
										'ohmylms'
									) }
								</strong>
								{ ( 0, I18n.__ )(
									'To use migration, please install and activate the supported platform on the same site as CLMS or use ',
									'ohmylms'
								) }
								<strong>
									{ ( 0, I18n.__ )(
										'Import option',
										'ohmylms'
									) }
								</strong>
								{ ( 0, I18n.__ )( '.', 'ohmylms' ) }
							</Controls.TextWP>
						</Controls.FlexWP>
					</Controls.CardWP>
					<Controls.SpacerWP marginBottom={ 0 } marginTop={ 12 }>
						<Controls.HeadingWP
							as={ 'h4' }
							size={ '18px' }
							color={ '#000D25' }
							weight={ '600' }
						>
							{ ( 0, I18n.__ )(
								'You can import your data instead',
								'ohmylms'
							) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 0 } marginTop={ 6 }>
							<MemoMigrationFileDropzone
								onFileChange={ function ( _eLocal6, _tLocal4 ) {
									if ( 'json' !== _tLocal4 || v ) {
										{
											o( _eLocal6 );
											c( _tLocal4 );
										}
									} else {
										d( ! 0 );
									}
								} }
								jsonImportEnabled={ v }
							/>
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.SpacerWP
					padding={ 0 }
					marginBottom={ 0 }
					marginTop={ 4 }
				>
					<Controls.FlexWP justify={ 'flex-end' }>
						<Controls.ButtonWP
							variant={ 'primary' }
							size={ 'md' }
							onClick={ h }
							isBusy={ t }
							disabled={ ! a || t }
						>
							{ ( 0, I18n.__ )( 'Import', 'ohmylms' ) }
						</Controls.ButtonWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
				{ s && <He.default isOpen={ s } onClose={ d } /> }
			</React.Fragment>
		);
	};
}
