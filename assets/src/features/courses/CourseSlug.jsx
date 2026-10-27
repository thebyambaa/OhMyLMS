/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseSlug( readRuntime ) {
	return function CourseSlug( props ) {
		const {
			I: Controls,
			Mz,
			React,
			b: I18n,
			g: ReactHooks,
		} = readRuntime();
		var slug = props.slug,
			handleSlugChange = props.handleSlugChange,
			r = ( function ( e, t ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return _eLocal;
						}
					} )( e ) ||
					( function ( _eLocal2, _tLocal ) {
						var n =
							_eLocal2 === null || _eLocal2 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal2[ Symbol.iterator ] ) ||
									_eLocal2[ '@@iterator' ];
						if ( n !== null && n !== void 0 ) {
							var _rLocal,
								a,
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( n = n.call( _eLocal2 ) ).next ),
									0 === _tLocal )
								) {
									if ( Object( n ) !== n ) {
										return;
									}
									c = ! 1;
								} else {
									for (
										;
										! ( c = ( _rLocal = o.call( n ) )
											.done ) &&
										( l.push( _rLocal.value ),
										l.length !== _tLocal );
										c = ! 0
									) {}
								}
							} catch ( _eLocal3 ) {
								u = ! 0;
								a = _eLocal3;
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
					} )( e, t ) ||
					( function ( _eLocal4, _tLocal2 ) {
						if ( _eLocal4 ) {
							if ( 'string' === typeof _eLocal4 ) {
								return Mz( _eLocal4, _tLocal2 );
							}
							var n = {}.toString.call( _eLocal4 ).slice( 8, -1 );
							return (
								'Object' === n &&
									_eLocal4.constructor &&
									( n = _eLocal4.constructor.name ),
								( () => {
									if ( 'Map' === n || 'Set' === n ) {
										return Array.from( _eLocal4 );
									}
									if (
										'Arguments' === n ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											n
										)
									) {
										return Mz( _eLocal4, _tLocal2 );
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
			} )( ( 0, ReactHooks.useState )( '' ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ];
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'flex-start' }
				>
					<Controls.FlexItemWP
						style={ {
							flex: '5',
						} }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )( 'Slug', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'The slug is all lowercase and contains only letters, numbers, and hyphens.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							flex: '3',
						} }
					>
						<Controls.InputWP
							className={ 'ohmylms-course-settings-slug-input' }
							defaultValue={ slug }
							onChange={ function ( e ) {
								( function ( _eLocal5 ) {
									if ( _eLocal5.trim() ) {
										o( '' );
									} else {
										o(
											( 0, I18n.__ )(
												'Course slug cannot be empty',
												'ohmylms'
											)
										);
									}
									handleSlugChange( _eLocal5 );
								} )( e.replace( /[^a-zA-Z0-9-]/g, '' ) );
							} }
							placeholder={ ( 0, I18n.__ )(
								'Enter course slug',
								'ohmylms'
							) }
							status={ a ? 'error' : '' }
							maxLength={ 200 }
							onKeyDown={ function ( e ) {
								if ( ' ' === e.key ) {
									e.preventDefault();
								}
							} }
						/>
						{ a && (
							<div
								className={ 'ohmylms-input-error' }
								style={ {
									color: 'red',
									fontSize: '12px',
									marginTop: '4px',
								} }
							>
								{ a }
							</div>
						) }
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
