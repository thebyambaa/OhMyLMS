/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createDeleteLearningItem( readRuntime ) {
	return function DeleteLearningItem( props ) {
		const {
			Bn,
			Ie,
			Ln,
			React,
			Vn,
			b: I18n,
			g: ReactHooks,
			zn,
		} = readRuntime();
		var label = props.label,
			n =
				void 0 === label
					? ( 0, I18n.__ )( 'Delete', 'ohmylms' )
					: label,
			className = props.className,
			a = void 0 === className ? '' : className,
			onDelete = props.onDelete,
			onCancel = props.onCancel,
			onClick = props.onClick,
			alertTitle = props.alertTitle,
			u =
				void 0 === alertTitle
					? ( 0, I18n.__ )( 'Delete', 'ohmylms' )
					: alertTitle,
			alertDescription = props.alertDescription,
			d =
				void 0 === alertDescription
					? ( 0, I18n.__ )(
							'Are you sure you want to delete this?',
							'ohmylms'
						)
					: alertDescription,
			m = ( function ( e, t ) {
				if ( e === null || e === void 0 ) {
					return {};
				}
				var _nLocal,
					r,
					_aLocal = ( function ( _eLocal, _tLocal ) {
						if ( _eLocal === null || _eLocal === void 0 ) {
							return {};
						}
						var _nLocal2 = {};
						for ( var _rLocal in _eLocal ) {
							if ( {}.hasOwnProperty.call( _eLocal, _rLocal ) ) {
								if ( -1 !== _tLocal.indexOf( _rLocal ) ) {
									continue;
								}
								_nLocal2[ _rLocal ] = _eLocal[ _rLocal ];
							}
						}
						return _nLocal2;
					} )( e, t );
				if ( Object.getOwnPropertySymbols ) {
					var o = Object.getOwnPropertySymbols( e );
					for ( r = 0; r < o.length; r++ ) {
						_nLocal = o[ r ];
						if (
							-1 === t.indexOf( _nLocal ) &&
							{}.propertyIsEnumerable.call( e, _nLocal )
						) {
							_aLocal[ _nLocal ] = e[ _nLocal ];
						}
					}
				}
				return _aLocal;
			} )( props, Bn ),
			p = ( function ( e, t ) {
				return (
					( function ( _eLocal2 ) {
						if ( Array.isArray( _eLocal2 ) ) {
							return _eLocal2;
						}
					} )( e ) ||
					( function ( _eLocal3, _tLocal2 ) {
						var _nLocal3 =
							_eLocal3 === null || _eLocal3 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal3[ Symbol.iterator ] ) ||
									_eLocal3[ '@@iterator' ];
						if ( _nLocal3 !== null && _nLocal3 !== void 0 ) {
							var r,
								_aLocal2,
								o,
								i,
								l = [],
								c = ! 0,
								_uLocal = ! 1;
							try {
								if (
									( ( o = ( _nLocal3 =
										_nLocal3.call( _eLocal3 ) ).next ),
									0 === _tLocal2 )
								) {
									if ( Object( _nLocal3 ) !== _nLocal3 ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( r = o.call( _nLocal3 ) )
											.done ) &&
										( l.push( r.value ),
										l.length !== _tLocal2 );
										c = ! 0
									) {}
								}
							} catch ( _eLocal4 ) {
								_uLocal = ! 0;
								_aLocal2 = _eLocal4;
							} finally {
								try {
									if (
										! c &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal3.return
										) &&
										( ( i = _nLocal3.return() ),
										Object( i ) !== i )
									) {
										return;
									}
								} finally {
									if ( _uLocal ) {
										throw _aLocal2;
									}
								}
							}
							return l;
						}
					} )( e, t ) ||
					( function ( _eLocal5, _tLocal3 ) {
						if ( _eLocal5 ) {
							if ( 'string' === typeof _eLocal5 ) {
								return Vn( _eLocal5, _tLocal3 );
							}
							var _nLocal4 = {}.toString
								.call( _eLocal5 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal4 &&
									_eLocal5.constructor &&
									( _nLocal4 = _eLocal5.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal4 ||
										'Set' === _nLocal4
									) {
										return Array.from( _eLocal5 );
									}
									if (
										'Arguments' === _nLocal4 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal4
										)
									) {
										return Vn( _eLocal5, _tLocal3 );
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
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			f = p[ 0 ],
			v = p[ 1 ],
			y = ( 0, ReactHooks.useCallback )(
				function () {
					v( ! 0 );
					if ( onClick ) {
						onClick();
					}
				},
				[ onClick ]
			),
			_ = ( 0, ReactHooks.useCallback )(
				function () {
					v( ! 1 );
					if ( onDelete ) {
						onDelete();
					}
				},
				[ onDelete ]
			),
			w = ( 0, ReactHooks.useCallback )(
				function () {
					v( ! 1 );
					if ( onCancel ) {
						onCancel();
					}
				},
				[ onCancel ]
			);
		return (
			<React.Fragment>
				<Controls.ButtonWP
					{ ...Ln(
						{
							variant: 'text',
							title: n,
							className: 'ohmylms-outline-delete-button '.concat(
								a
							),
							icon: React.createElement( zn, null ),
							onClick: y,
						},
						m
					) }
				>
					{ n }
				</Controls.ButtonWP>
				{ f && (
					<Ie
						title={ u }
						description={ d }
						onClose={ w }
						onDelete={ _ }
						isOpen={ f }
						className={ 'ohmylms-outline-delete-alert' }
						isDelete={ ! 0 }
					/>
				) }
			</React.Fragment>
		);
	};
}
