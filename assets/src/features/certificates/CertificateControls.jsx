/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCertificateControls( readRuntime ) {
	return function CertificateControls() {
		const {
			He,
			IL,
			ML: MemoCertificateImageField,
			React,
			T: StoreModule,
			TL,
			b: I18n,
			g: ReactHooks,
			kL: MemoCertificateTextField,
			y: WordPressData,
		} = readRuntime();
		var t = TL(
			( 0, ReactHooks.useState )(
				D( 'instructor_signature_img', 'src' )
			),
			2
		);
		var n = t[ 0 ];
		var r = t[ 1 ];
		var a = TL(
			( 0, ReactHooks.useState )( D( 'director_signature_img', 'src' ) ),
			2
		);
		var o = a[ 0 ];
		var i = a[ 1 ];
		var l = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).selectCertificate();
		}, [] );
		var c = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var u = TL(
			( 0, ReactHooks.useState )( D( 'title_text', 'content' ) ),
			2
		);
		var s = u[ 0 ];
		var d = u[ 1 ];
		var m = TL(
			( 0, ReactHooks.useState )( D( 'subtitle_text', 'content' ) ),
			2
		);
		var p = m[ 0 ];
		var f = m[ 1 ];
		var v = TL(
			( 0, ReactHooks.useState )( D( 'description_text', 'content' ) ),
			2
		);
		var h = v[ 0 ];
		var _ = v[ 1 ];
		var w = TL(
			( 0, ReactHooks.useState )( D( 'surname_text', 'content' ) ),
			2
		);
		var E = w[ 0 ];
		var S =
			( w[ 1 ],
			TL(
				( 0, ReactHooks.useState )(
					D( 'recognition_text', 'content' )
				),
				2
			) );
		var R = S[ 0 ];
		var x = S[ 1 ];
		var C = TL(
			( 0, ReactHooks.useState )(
				D( 'director_signature_text', 'content' )
			),
			2
		);
		var P = C[ 0 ];
		var O = C[ 1 ];
		var k = TL(
			( 0, ReactHooks.useState )(
				D( 'instructor_signature_text', 'content' )
			),
			2
		);
		var j = k[ 0 ];
		var A = k[ 1 ];
		var M = TL( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var F = M[ 0 ];
		var N = M[ 1 ];
		function D( ..._args ) {
			let [ _eLocal2, _tLocal ] = _args;
			var _nLocal,
				_rLocal =
					_args.length > 2 && void 0 !== _args[ 2 ]
						? _args[ 2 ]
						: null,
				_aLocal = function ( _nLocal2 ) {
					if ( _nLocal2 ) {
						var _oLocal,
							_iLocal = ( function ( _eLocal3 ) {
								var _tLocal2 =
									( 'undefined' !== typeof Symbol &&
										_eLocal3[ Symbol.iterator ] ) ||
									_eLocal3[ '@@iterator' ];
								if ( ! _tLocal2 ) {
									if (
										Array.isArray( _eLocal3 ) ||
										( _tLocal2 = IL( _eLocal3 ) )
									) {
										if ( _tLocal2 ) {
											_eLocal3 = _tLocal2;
										}
										var _nLocal3 = 0,
											_rLocal2 = function () {};
										return {
											s: _rLocal2,
											n() {
												return _nLocal3 >=
													_eLocal3.length
													? {
															done: ! 0,
														}
													: {
															done: ! 1,
															value: _eLocal3[
																_nLocal3++
															],
														};
											},
											e( _eLocal4 ) {
												throw _eLocal4;
											},
											f: _rLocal2,
										};
									}
									throw new TypeError(
										'Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
									);
								}
								var _aLocal2,
									_oLocal2 = ! 0,
									_iLocal2 = ! 1;
								return {
									s() {
										_tLocal2 = _tLocal2.call( _eLocal3 );
									},
									n() {
										var _eLocal5 = _tLocal2.next();
										return (
											( _oLocal2 = _eLocal5.done ),
											_eLocal5
										 );
									},
									e( _eLocal6 ) {
										_iLocal2 = ! 0;
										_aLocal2 = _eLocal6;
									},
									f() {
										try {
											if ( ! (
												_oLocal2 ||
												( ( _nullableValue ) =>
													_nullableValue === null ||
													_nullableValue === void 0 )(
													_tLocal2.return
												)
											) ) {
												_tLocal2.return();
											}
										} finally {
											if ( _iLocal2 ) {
												throw _aLocal2;
											}
										}
									},
								};
							} )( _nLocal2 );
						try {
							for (
								_iLocal.s();
								! ( _oLocal = _iLocal.n() ).done;
							) {
								var _lLocal = _oLocal.value;
								if ( _lLocal.selector === _eLocal2 ) {
									var _cLocal;
									if (
										_rLocal &&
										'style' === _tLocal &&
										_lLocal[ _tLocal ]
									) {
										return null ===
											( _cLocal =
												_lLocal[ _tLocal ][
													_rLocal
												] ) || void 0 === _cLocal
											? void 0
											: _cLocal
													.replace( '!important', '' )
													.trim();
									}
									if ( _tLocal in _lLocal ) {
										return 'string' ===
											typeof _lLocal[ _tLocal ]
											? _lLocal[ _tLocal ]
													.replace( '!important', '' )
													.trim()
											: _lLocal[ _tLocal ];
									}
								}
								if ( _lLocal.children ) {
									var _uLocal = _aLocal( _lLocal.children );
									if ( _uLocal ) {
										return _uLocal;
									}
								}
							}
						} catch ( _eLocal7 ) {
							_iLocal.e( _eLocal7 );
						} finally {
							_iLocal.f();
						}
						return null;
					}
				};
			return _aLocal(
				l === null ||
					l === void 0 ||
					null === ( _nLocal = l.contents ) ||
					void 0 === _nLocal
					? void 0
					: _nLocal.elements
			);
		}
		var W = [
				{
					label: ( 0, I18n.__ )( 'Document Color', 'ohmylms' ),
					colors: [
						'#FFFFFF',
						'var(--ohmylms-primary-color)',
						'#F6F6F6',
						'#ED9702',
						'#000D21',
					],
				},
				{
					label: ( 0, I18n.__ )( 'Default Color', 'ohmylms' ),
					colors: [
						'#FFFFFF',
						'#EBEBEB',
						'#D6D6D6',
						'#999999',
						'#707070',
						'#474747',
						'#068EA8',
						'#08C0DF',
						'#5BE1E6',
						'#37B6FE',
						'#5171FF',
						'#0049AC',
						'#04B55F',
						'#7DD856',
						'#C0FF72',
						'#FEDE58',
						'#FFBC59',
						'#FF914D',
						'#5E16EA',
						'#8C51FF',
						'#CB6BE5',
						'#FF57BF',
						'#FF5756',
						'#FE3130',
					],
				},
			],
			z = function ( _tLocal3, _nLocal4, _rLocal3 ) {
				var _aLocal3 = {
					selector: _tLocal3,
					key: _nLocal4,
					value: _rLocal3,
				};
				c.updateClassicData( _aLocal3 );
			};
		return (
			( 0, ReactHooks.useEffect )( function () {
				r( D( 'instructor_signature_img', 'src' ) );
				i( D( 'director_signature_img', 'src' ) );
			}, [] ),
			(
				<React.Fragment>
					<Controls.CardWP
						style={ {
							borderRadius: 0,
						} }
						isBorderless={ ! 0 }
					>
						<Controls.SpacerWP paddingX={ 4 } paddingY={ 3 }>
							<Controls.FlexWP
								align={ 'start' }
								justify={ 'start' }
								direction={ 'column' }
								gap={ 4 }
							>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof s && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Title Text',
												'ohmylms'
											) }
											color={ D(
												'title_text',
												'style',
												'color'
											) }
											value={ s }
											onValueChange={ function (
												_tLocal4
											) {
												d( _tLocal4 );
												z(
													'title_text',
													'content',
													_tLocal4
												);
											} }
											onColorChange={ function (
												_eLocal8
											) {
												return z(
													'title_text',
													'style',
													{
														color: ''.concat(
															_eLocal8,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof p && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Subtitle Text',
												'ohmylms'
											) }
											color={ D(
												'subtitle_text',
												'style',
												'color'
											) }
											value={ p }
											onValueChange={ function (
												_tLocal5
											) {
												f( _tLocal5 );
												z(
													'subtitle_text',
													'content',
													_tLocal5
												);
											} }
											onColorChange={ function (
												_eLocal9
											) {
												return z(
													'subtitle_text',
													'style',
													{
														color: ''.concat(
															_eLocal9,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof h && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Description Text',
												'ohmylms'
											) }
											color={ D(
												'description_text',
												'style',
												'color'
											) }
											value={ h }
											onValueChange={ function (
												_tLocal6
											) {
												_( _tLocal6 );
												z(
													'description_text',
													'content',
													_tLocal6
												);
											} }
											onColorChange={ function (
												_eLocal0
											) {
												return z(
													'description_text',
													'style',
													{
														color: ''.concat(
															_eLocal0,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof E && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Surname Text',
												'ohmylms'
											) }
											color={ D(
												'surname_text',
												'style',
												'color'
											) }
											value={ E }
											onColorChange={ function (
												_eLocal1
											) {
												return z(
													'surname_text',
													'style',
													{
														color: ''.concat(
															_eLocal1,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											disabled={ ! 0 }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof R && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Recognition Text',
												'ohmylms'
											) }
											color={ D(
												'recognition_text',
												'style',
												'color'
											) }
											value={ R }
											onValueChange={ function (
												_tLocal7
											) {
												x( _tLocal7 );
												z(
													'recognition_text',
													'content',
													_tLocal7
												);
											} }
											onColorChange={ function (
												_eLocal10
											) {
												return z(
													'recognition_text',
													'style',
													{
														color: ''.concat(
															_eLocal10,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof j && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Instructor Signature Label',
												'ohmylms'
											) }
											color={ D(
												'instructor_signature_text',
												'style',
												'color'
											) }
											value={ j }
											onValueChange={ function (
												_tLocal8
											) {
												A( _tLocal8 );
												z(
													'instructor_signature_text',
													'content',
													_tLocal8
												);
											} }
											onColorChange={ function (
												_eLocal11
											) {
												return z(
													'instructor_signature_text',
													'style',
													{
														color: ''.concat(
															_eLocal11,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof P && (
										<MemoCertificateTextField
											label={ ( 0, I18n.__ )(
												'Director Signature Label',
												'ohmylms'
											) }
											color={ D(
												'director_signature_text',
												'style',
												'color'
											) }
											value={ P }
											onValueChange={ function (
												_tLocal9
											) {
												O( _tLocal9 );
												z(
													'director_signature_text',
													'content',
													_tLocal9
												);
											} }
											onColorChange={ function (
												_eLocal12
											) {
												return z(
													'director_signature_text',
													'style',
													{
														color: ''.concat(
															_eLocal12,
															' !important'
														),
													}
												);
											} }
											presets={ W }
											isItProFeature={ ! 0 }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof j && (
										<MemoCertificateImageField
											title={ ( 0, I18n.__ )(
												'Instructor Signature',
												'ohmylms'
											) }
											tooltip={ null }
											thumbnail={ n }
											alertTitle={ ( 0, I18n.__ )(
												'Remove the signature',
												'ohmylms'
											) }
											alertDescription={ ( 0, I18n.__ )(
												'Are you sure you want to remove the signature?',
												'ohmylms'
											) }
											onRemove={ function () {
												r( null );
												z(
													'instructor_signature_img',
													'src',
													''
												);
											} }
											onChange={ function ( _eLocal13 ) {
												r(
													_eLocal13 === null ||
														_eLocal13 === void 0
														? void 0
														: _eLocal13.url
												);
												z(
													'instructor_signature_img',
													'src',
													_eLocal13 === null ||
														_eLocal13 === void 0
														? void 0
														: _eLocal13.url
												);
											} }
										/>
									) }
								</Controls.FlexItemWP>
								<Controls.FlexItemWP
									isBlock={ ! 0 }
									style={ {
										width: '100%',
									} }
								>
									{ 'string' === typeof P && (
										<MemoCertificateImageField
											title={ ( 0, I18n.__ )(
												'Director Signature',
												'ohmylms'
											) }
											tooltip={ null }
											thumbnail={ o }
											alertTitle={ ( 0, I18n.__ )(
												'Remove the signature',
												'ohmylms'
											) }
											alertDescription={ ( 0, I18n.__ )(
												'Are you sure you want to remove the signature?',
												'ohmylms'
											) }
											onRemove={ function () {
												i( null );
												z(
													'director_signature_img',
													'src',
													''
												);
											} }
											onChange={ function ( _eLocal14 ) {
												i(
													_eLocal14 === null ||
														_eLocal14 === void 0
														? void 0
														: _eLocal14.url
												);
												z(
													'director_signature_img',
													'src',
													_eLocal14 === null ||
														_eLocal14 === void 0
														? void 0
														: _eLocal14.url
												);
											} }
										/>
									) }
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
					{ F && (
						<React.Fragment>
							<He.default isOpen={ F } onClose={ N } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
