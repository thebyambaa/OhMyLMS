import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createBadgeImageField( readRuntime ) {
	return function BadgeImageField( props ) {
		const {
			D2,
			FK,
			I: Controls,
			M,
			React,
			Tt,
			W2,
			b: I18n,
			g: ReactHooks,
			hu,
			z2,
		} = readRuntime();
		M().noConflict();
		var title = props.title,
			description = props.description,
			brandingImg = props.brandingImg,
			colorsConfig = props.colorsConfig,
			handleChange = props.handleChange,
			handleRemove = props.handleRemove,
			showDivider = props.showDivider,
			c = void 0 === showDivider || showDivider,
			alertTitle = props.alertTitle,
			s =
				void 0 === alertTitle
					? ( 0, I18n.__ )( 'Remove the branding logo', 'ohmylms' )
					: alertTitle,
			alertDescription = props.alertDescription,
			m =
				void 0 === alertDescription
					? ( 0, I18n.__ )(
							'Are you sure you want to remove the branding logo?',
							'ohmylms'
						)
					: alertDescription,
			maxWidth = props.maxWidth,
			f = void 0 === maxWidth ? 'unset' : maxWidth,
			v =
				( ( function ( e, t ) {
					if ( e === null || e === void 0 ) {
						return {};
					}
					var n,
						r,
						a = ( function ( _eLocal, _tLocal ) {
							if ( _eLocal === null || _eLocal === void 0 ) {
								return {};
							}
							var _nLocal = {};
							for ( var _rLocal in _eLocal ) {
								if (
									{}.hasOwnProperty.call( _eLocal, _rLocal )
								) {
									if ( -1 !== _tLocal.indexOf( _rLocal ) ) {
										continue;
									}
									_nLocal[ _rLocal ] = _eLocal[ _rLocal ];
								}
							}
							return _nLocal;
						} )( e, t );
					if ( Object.getOwnPropertySymbols ) {
						var o = Object.getOwnPropertySymbols( e );
						for ( r = 0; r < o.length; r++ ) {
							n = o[ r ];
							if (
								-1 === t.indexOf( n ) &&
								{}.propertyIsEnumerable.call( e, n )
							) {
								a[ n ] = e[ n ];
							}
						}
					}
				} )( props, D2 ),
				( function ( e, t ) {
					return (
						( function ( _eLocal2 ) {
							if ( Array.isArray( _eLocal2 ) ) {
								return _eLocal2;
							}
						} )( e ) ||
						( function ( _eLocal3, _tLocal2 ) {
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
									_cLocal = ! 0,
									u = ! 1;
								try {
									if (
										( ( o = ( n = n.call( _eLocal3 ) )
											.next ),
										0 === _tLocal2 )
									) {
										if ( Object( n ) !== n ) {
											return;
										}
										_cLocal = ! 1;
									} else {
										for (
											;
											! ( _cLocal = ( r = o.call( n ) )
												.done ) &&
											( l.push( r.value ),
											l.length !== _tLocal2 );
											_cLocal = ! 0
										) {}
									}
								} catch ( _eLocal4 ) {
									u = ! 0;
									a = _eLocal4;
								} finally {
									try {
										if (
											! _cLocal &&
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
						} )( e, t ) ||
						( function ( _eLocal5, _tLocal3 ) {
							if ( _eLocal5 ) {
								if ( 'string' === typeof _eLocal5 ) {
									return z2( _eLocal5, _tLocal3 );
								}
								var n = {}.toString
									.call( _eLocal5 )
									.slice( 8, -1 );
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
											return z2( _eLocal5, _tLocal3 );
										}
										return void 0;
									} )()
								 );
							}
						} )( e, t ) ||
						( function () {
							throw new TypeError(
								'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
							);
						} )()
					);
				} )( ( 0, ReactHooks.useState )( null ), 2 ) ),
			h = ( v[ 0 ], v[ 1 ] ),
			y = [ '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg' ],
			_ = function ( e ) {
				var t = wp.media( {
					title: 'Select or Upload Media',
					button: {
						text: 'Use this media',
					},
					multiple: ! 1,
				} );
				t.on( 'select', function () {
					var n = t.state().get( 'selection' ).first().toJSON(),
						r = y.some( function ( _eLocal6 ) {
							return n.url.toLowerCase().endsWith( _eLocal6 );
						} ),
						a =
							( 'image' === e && 'image' === n.type ) ||
							( 'video' === e && 'video' === n.type );
					if ( r && a ) {
						if ( 'image' === e ) {
							{
								handleChange(
									n.url,
									n === null || n === void 0 ? void 0 : n.id
								);
								h( n.url );
							}
						}
					} else {
						showRequestError( 'Invalid file type or media type.' );
					}
				} );
				t.open();
			};
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'start' }
					gap={ '4' }
				>
					{ ( title || description ) && (
						<Controls.FlexItemWP
							style={ {
								maxWidth: '300px',
							} }
						>
							<Controls.SpacerWP marginY={ 5 }>
								{ title && (
									<Controls.HeadingWP level={ '4' }>
										{ title }
									</Controls.HeadingWP>
								) }
								{ description && (
									<Controls.TextWP>
										{ description }
									</Controls.TextWP>
								) }
							</Controls.SpacerWP>
						</Controls.FlexItemWP>
					) }
					<Controls.FlexItemWP
						style={ {
							maxWidth: f,
						} }
						isBlock={ ! 0 }
					>
						<Controls.FlexWP direction={ 'column' } gap={ '4' }>
							<Controls.CardWP>
								<Controls.SpacerWP padding={ '4' }>
									{ Boolean( brandingImg ) ? (
										<Controls.FlexWP
											direction={ 'column' }
											gap={ '2' }
											align={ 'center' }
										>
											<Controls.AvatarWP
												shape={ 'square' }
												src={ brandingImg }
												alt={ 'logo' }
												style={ {
													height: 'auto',
												} }
											/>
											{ React.createElement( hu, {
												handleEdit() {
													return _( 'image' );
												},
												handleDelete: handleRemove,
												alertTitle: s,
												alertDescription: m,
											} ) }
										</Controls.FlexWP>
									) : (
										<Controls.FlexWP
											direction={ 'column' }
											gap={ '2' }
											align={ 'center' }
											justify={ 'center' }
										>
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ function () {
													return _( 'image' );
												} }
											>
												{ ( 0, I18n.__ )(
													'Select a file',
													'ohmylms'
												) }
											</Controls.ButtonWP>
											<Controls.TextWP
												variant={ 'muted' }
												size={ 'small' }
											>
												{ ( 0, I18n.__ )(
													'Size: 100x36 pixels, Max height: 50px',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexWP>
									) }
								</Controls.SpacerWP>
							</Controls.CardWP>
							{ colorsConfig &&
								colorsConfig.map( function ( e, t ) {
									return (
										<FK
											{ ...W2(
												{
													format: 'hex',
												},
												e
											) }
											key={ t }
										/>
									);
								} ) }
						</Controls.FlexWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
				{ c && <Tt.A /> }
			</React.Fragment>
		);
	};
}
