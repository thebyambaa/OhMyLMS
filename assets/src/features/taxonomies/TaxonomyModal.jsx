/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createTaxonomyModal( readRuntime ) {
	return function TaxonomyModal( props ) {
		const {
			EY,
			Pf,
			RY,
			React,
			SY,
			_Y,
			b: I18n,
			g: ReactHooks,
			wY,
		} = readRuntime();
		var t = props.isOpen;
		var n = props.onClose;
		var r = props.onSubmit;
		var a = props.title;
		var o = props.type;
		var i = void 0 === o ? 'category' : o;
		var l = props.initialData;
		var c = void 0 === l ? null : l;
		var u = props.categories;
		var s = void 0 === u ? [] : u;
		var d = props.isSubmitting;
		var m = void 0 !== d && d;
		var p = EY(
			( 0, ReactHooks.useState )( {
				name: '',
				parent: 0,
			} ),
			2
		);
		var f = p[ 0 ];
		var v = p[ 1 ];
		var h = EY( ( 0, ReactHooks.useState )( {} ), 2 );
		var y = h[ 0 ];
		var _ = h[ 1 ];
		( 0, ReactHooks.useEffect )(
			function () {
				v(
					c
						? {
								name: c.name || '',
								parent: c.parent || 0,
							}
						: {
								name: '',
								parent: 0,
							}
				);
				_( {} );
			},
			[ c, t ]
		);
		var w = function ( e, _tLocal ) {
			v( function ( _nLocal ) {
				return _Y( _Y( {}, _nLocal ), {}, wY( {}, e, _tLocal ) );
			} );
			if ( y[ e ] ) {
				_( function ( _tLocal2 ) {
					return _Y( _Y( {}, _tLocal2 ), {}, wY( {}, e, '' ) );
				} );
			}
		};
		var E = function () {
			if ( ! m ) {
				n();
			}
		};
		if ( ! t ) {
			return null;
		}
		var R = ! f.name.trim();
		var S = [
			{
				label: ( 0, I18n.__ )( 'None', 'ohmylms' ),
				value: 0,
			},
		].concat(
			( function ( e ) {
				return (
					( function ( _eLocal ) {
						if ( Array.isArray( _eLocal ) ) {
							return RY( _eLocal );
						}
					} )( e ) ||
					( function ( _eLocal2 ) {
						if (
							( 'undefined' !== typeof Symbol &&
								( ( _nullableValue ) =>
									_nullableValue !== null &&
									_nullableValue !== void 0 )(
									_eLocal2[ Symbol.iterator ]
								) ) ||
							( ( _nullableValue2 ) =>
								_nullableValue2 !== null &&
								_nullableValue2 !== void 0 )(
								_eLocal2[ '@@iterator' ]
							)
						) {
							return Array.from( _eLocal2 );
						}
					} )( e ) ||
					SY( e ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )(
				s
					.filter( function ( e ) {
						return ! c || e.term_id !== c.term_id;
					} )
					.map( function ( e ) {
						return {
							label: e.name,
							value: e.term_id,
						};
					} )
			)
		);
		return (
			<Controls.ModalWP
				title={ a }
				onRequestClose={ E }
				shouldCloseOnEsc={ ! m }
				shouldCloseOnClickOutside={ ! m }
				size={ 'medium' }
			>
				<Controls.CardWP isBorderless={ ! 0 } variant={ 'secondary' }>
					<Controls.SpacerWP padding={ 3 }>
						<Pf
							title={ ( 0, I18n.__ )( 'Name', 'ohmylms' ) }
							description={
								'category' === i
									? ( 0, I18n.__ )(
											'Give this category a meaningful name.',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Give this tag a meaningful name.',
											'ohmylms'
										)
							}
							inputType={ 'text' }
							value={ f.name }
							onChange={ function ( e ) {
								return w( 'name', e );
							} }
							placeholder={
								'category' === i
									? ( 0, I18n.__ )(
											'Enter category name',
											'ohmylms'
										)
									: ( 0, I18n.__ )(
											'Enter tag name',
											'ohmylms'
										)
							}
							required={ ! 0 }
							error={ y.name }
						/>
						{ 'category' === i && (
							<Controls.SpacerWP padding={ 4 } marginBottom={ 2 }>
								<Controls.FlexWP
									gap={ 8 }
									align={ 'flex-start' }
									justify={ 'space-between' }
								>
									<Controls.FlexItemWP isBlock={ ! 0 }>
										<Controls.HeadingWP level={ '4' }>
											{ ( 0, I18n.__ )(
												'Parent Category',
												'ohmylms'
											) }
										</Controls.HeadingWP>
										<Controls.SpacerWP marginBottom={ 1 } />
										<Controls.TextWP>
											{ ( 0, I18n.__ )(
												'Categories can have a parent for hierarchical organization.',
												'ohmylms'
											) }
										</Controls.TextWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP isBlock={ ! 0 }>
										<Controls.SelectWP
											value={ f.parent }
											onChange={ function ( e ) {
												return w(
													'parent',
													parseInt( e )
												);
											} }
											options={ S }
										/>
										{ y.parent && (
											<Controls.TextWP
												as={ 'p' }
												size={ '13px' }
												color={ '#FF4955' }
												align={ 'right' }
												style={ {
													marginTop: '4px',
												} }
											>
												{ y.parent }
											</Controls.TextWP>
										) }
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						) }
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.SpacerWP paddingTop={ 4 }>
					<Controls.FlexWP justify={ 'flex-end' } gap={ 2 }>
						<Controls.ButtonWP
							variant={ 'secondary' }
							onClick={ E }
							disabled={ m }
						>
							{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
						</Controls.ButtonWP>
						<Controls.ButtonWP
							variant={ 'primary' }
							disabled={ R }
							onClick={ function () {
								var e;
								if ( ! m ) {
									{
										e = {};
										if ( ! f.name.trim() ) {
											e.name = ( 0, I18n.__ )(
												'Name is required',
												'ohmylms'
											);
										}
										if (
											'category' === i &&
											c &&
											f.parent === c.term_id
										) {
											e.parent = ( 0, I18n.__ )(
												'A category cannot be its own parent',
												'ohmylms'
											);
										}
										_( e );
										if ( 0 === Object.keys( e ).length ) {
											r( f );
										}
									}
								}
							} }
							isBusy={ m }
						>
							{ c
								? ( 0, I18n.__ )( 'Update', 'ohmylms' )
								: ( 0, I18n.__ )( 'Create', 'ohmylms' ) }
						</Controls.ButtonWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</Controls.ModalWP>
		);
	};
}
