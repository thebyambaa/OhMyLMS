/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { MCPSettings } from '../settings/MCPSettings';
export function createIntegrationConfig( readRuntime ) {
	return function IntegrationConfig( props ) {
		const {
			I: Controls,
			Nr,
			O7,
			P7,
			React,
			b: I18n,
			g: ReactHooks,
			j7: IntegrationSettings,
			k7,
		} = readRuntime();
		var t = props.integration,
			n = props.onSave,
			r = props.onCancel,
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
					( function ( _eLocal4, _tLocal3 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return k7( _eLocal4, _tLocal3 );
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
										return k7( _eLocal4, _tLocal3 );
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
			} )( ( 0, ReactHooks.useState )( t.settings || {} ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			l = IntegrationSettings[ t.key ];
		return (
			<React.Fragment>
				<Nr onClick={ r } />
				<Controls.SpacerWP marginBottom={ 5 } />
				<Controls.FlexWP gap={ 3 } justify={ 'flex-start' }>
					{ t.icon && (
						<img
							src={ t.icon }
							alt={ t.label }
							style={ {
								width: 50,
								height: 50,
								borderRadius: '4px',
								background: '#fff',
								border: '1px solid #eee',
								objectFit: 'contain',
								padding: '4px',
							} }
						/>
					) }
					<Controls.HeadingWP
						level={ 3 }
						size={ 20 }
						weight={ 500 }
						color={ '#000D25' }
					>
						{ ( 0, I18n.__ )( t.label, 'ohmylms' ) }
					</Controls.HeadingWP>
				</Controls.FlexWP>
				<Controls.SpacerWP marginBottom={ 2 } />
				<Controls.TextWP size={ 14 } variant={ 'muted' }>
					{ t.description }
				</Controls.TextWP>
				{ ( () => {
					if ( t.key === 'mcp' ) {
						return <MCPSettings />;
					}
					if ( l ) {
						return React.createElement( l, {
							settings: o,
							onChange( e, _tLocal4 ) {
								i(
									P7( P7( {}, o ), {}, O7( {}, e, _tLocal4 ) )
								);
							},
							onSave: n,
							onCancel: r,
						} );
					}
					return (
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'No settings available for this integration.',
								'ohmylms'
							) }
						</Controls.TextWP>
					);
				} )() }
			</React.Fragment>
		);
	};
}
