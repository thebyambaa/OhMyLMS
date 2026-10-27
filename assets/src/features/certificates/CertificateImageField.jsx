import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCertificateImageField( readRuntime ) {
	return function CertificateImageField( props ) {
		const {
			I: Controls,
			M,
			Mt,
			React,
			V,
			b: I18n,
			g: ReactHooks,
			hu,
			jL,
		} = readRuntime();
		M().noConflict();
		var t = props.thumbnail,
			n = void 0 === t ? null : t,
			r = props.onChange,
			a = props.onRemove,
			o = props.title,
			i = void 0 === o ? ( 0, I18n.__ )( 'Thumbnail', 'ohmylms' ) : o,
			l = props.tooltip,
			c =
				void 0 === l
					? ( 0, I18n.__ )( 'Information about thumbnail', 'ohmylms' )
					: l,
			u = props.alertTitle,
			s =
				void 0 === u
					? ( 0, I18n.__ )( 'Remove the thumbnail', 'ohmylms' )
					: u,
			d = props.alertDescription,
			m =
				void 0 === d
					? ( 0, I18n.__ )(
							'Are you sure you want to remove the thumbnail?',
							'ohmylms'
						)
					: d,
			p = props.imgMaxWidth,
			f = void 0 === p ? '100%' : p,
			v = [ '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg' ],
			h = ( function ( e, _tLocal ) {
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
								_oLocal,
								_iLocal,
								_lLocal = [],
								_cLocal = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( _oLocal = ( _nLocal =
										_nLocal.call( _eLocal2 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal ) !== _nLocal ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( _rLocal =
											_oLocal.call( _nLocal ) ).done ) &&
										( _lLocal.push( _rLocal.value ),
										_lLocal.length !== _tLocal2 );
										_cLocal = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								_uLocal = ! 0;
								_aLocal = _eLocal3;
							} finally {
								try {
									if (
										! _cLocal &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal.return
										) &&
										( ( _iLocal = _nLocal.return() ),
										Object( _iLocal ) !== _iLocal )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal;
									}
								}
							}
							return _lLocal;
						}
					} )( e, _tLocal ) ||
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return jL( _eLocal4, _tLocal3 );
							}
							var _nLocal2 = {}.toString
								.call( _eLocal4 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal2 &&
									_eLocal4.constructor &&
									( _nLocal2 = _eLocal4.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal2 ||
										'Set' === _nLocal2
									) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === _nLocal2 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal2
										)
									) {
										return jL( _eLocal4, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			y =
				( h[ 0 ],
				h[ 1 ],
				function ( e ) {
					var _tLocal4 = wp.media( {
						title: 'Select or Upload Media',
						button: {
							text: 'Use this media',
						},
						multiple: ! 1,
					} );
					_tLocal4.on( 'select', function () {
						var _nLocal3 = _tLocal4
								.state()
								.get( 'selection' )
								.first()
								.toJSON(),
							_aLocal2 = v.some( function ( _eLocal5 ) {
								return _nLocal3.url
									.toLowerCase()
									.endsWith( _eLocal5 );
							} ),
							_oLocal2 =
								( 'image' === e &&
									'image' === _nLocal3.type ) ||
								( 'video' === e && 'video' === _nLocal3.type );
						if ( _aLocal2 && _oLocal2 ) {
							if ( 'image' === e ) {
								r( _nLocal3 );
							}
						} else {
							showRequestError(
								'Invalid file type or media type.'
							);
						}
					} );
					_tLocal4.open();
				} ),
			_ = {
				maxWidth: f,
				objectFit: 'contain',
			};
		return (
			<Controls.CardWP isBorderless={ ! 0 } variant={ 'secondary' }>
				<Controls.SpacerWP padding={ 3 }>
					<Controls.FlexWP
						direction={ 'column' }
						align={ 'flex-start' }
						justify={ 'flex-start' }
						gap={ 4 }
					>
						<Controls.FlexWP gap={ 'small' } align={ 'center' }>
							<Controls.HeadingWP level={ 4 }>
								{ i }
							</Controls.HeadingWP>
							{ c && (
								<V.A title={ c }>
									<React.Fragment>
										<Mt.A />
									</React.Fragment>
								</V.A>
							) }
						</Controls.FlexWP>
						{ n ? (
							<Controls.FlexItemWP
								isBlock={ ! 0 }
								style={ {
									position: 'relative',
									width: '100%',
								} }
							>
								<Controls.FlexWP
									direction={ 'column' }
									align={ 'center' }
									justify={ 'center' }
									gap={ 2 }
								>
									<img
										style={ _ }
										src={ n }
										alt={ ( 0, I18n.__ )(
											'Thumbnail',
											'ohmylms'
										) }
									/>
									{ React.createElement( hu, {
										handleEdit() {
											return y( 'image' );
										},
										handleDelete() {
											a();
										},
										alertTitle: s,
										alertDescription: m,
									} ) }
								</Controls.FlexWP>
							</Controls.FlexItemWP>
						) : (
							<Controls.FlexWP
								direction={ 'column' }
								align={ 'center' }
								justify={ 'center' }
								gap={ 2 }
								style={ {
									width: '100%',
								} }
							>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										return y( 'image' );
									} }
								>
									{ ( 0, I18n.__ )(
										'Select a file',
										'ohmylms'
									) }
								</Controls.ButtonWP>
								<p>
									{ ( 0, I18n.__ )(
										'Supported files: .png, .jpg, jpeg, .gif',
										'ohmylms'
									) }
								</p>
							</Controls.FlexWP>
						) }
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
