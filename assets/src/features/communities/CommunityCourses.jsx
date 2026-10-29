import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCommunityCourses( readRuntime ) {
	return function CommunityCourses() {
		const {
			I: RecoveredControls,
			C9,
			E9,
			Ea: Card,
			R9,
			React,
			T: StoreModule,
			b: I18n,
			b9,
			g: ReactHooks,
			l,
			x9,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).selectCommunity();
			}, [] ),
			setCommunity = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).setCommunity,
			n = C9( ( 0, ReactHooks.useState )( [] ), 2 ),
			r = n[ 0 ],
			a = n[ 1 ],
			o = C9( ( 0, ReactHooks.useState )( null ), 2 ),
			i = o[ 0 ],
			c = o[ 1 ],
			u = C9( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			s = u[ 0 ],
			d = u[ 1 ],
			m = function ( _nLocal, _rLocal, _aLocal ) {
				var _oLocal = R9( {}, e );
				_oLocal[ _nLocal ] = _aLocal
					? R9(
							R9( {}, _oLocal[ _nLocal ] ),
							{},
							x9( {}, _aLocal, _rLocal )
						)
					: _rLocal;
				setCommunity( _oLocal );
			},
			p = function ( _eLocal2 ) {
				return _eLocal2 && Array.isArray( _eLocal2 )
					? _eLocal2
							.filter( function ( _eLocal3 ) {
								return (
									'yes' !==
									( _eLocal3 === null || _eLocal3 === void 0
										? void 0
										: _eLocal3.has_community )
								);
							} )
							.map( function ( _eLocal4 ) {
								return {
									id:
										_eLocal4 === null || _eLocal4 === void 0
											? void 0
											: _eLocal4.id,
									name:
										_eLocal4 === null || _eLocal4 === void 0
											? void 0
											: _eLocal4.name,
									image_src:
										( _eLocal4 === null ||
										_eLocal4 === void 0
											? void 0
											: _eLocal4.image_src ) || null,
									date_created:
										( _eLocal4 === null ||
										_eLocal4 === void 0
											? void 0
											: _eLocal4.date_created ) || {},
								};
							} )
					: [];
			},
			f = ( function () {
				var _eLocal5 = E9(
					b9().m( function _eLocal6( t ) {
						var _nLocal2, _rLocal2, _oLocal2;
						return b9().w(
							function ( _eLocal7 ) {
								for (;;) {
									switch ( ( _eLocal7.p = _eLocal7.n ) ) {
										case 0:
											return (
												( _eLocal7.p = 0 ),
												d( ! 0 ),
												( _eLocal7.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses?search='.concat(
														t,
														'&post_status=publish'
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											if ( ( _rLocal2 = _eLocal7.v ) ) {
												_eLocal7.n = 2;
												break;
											}
											_rLocal2 = [];
										// Falls through to the shared async state cleanup.
										case 2:
											_nLocal2 = p( _rLocal2 );
											a(
												_nLocal2.map(
													function ( _eLocal8 ) {
														return {
															label: _eLocal8.name,
															value: _eLocal8.id,
														};
													}
												)
											);
											_eLocal7.n = 4;
											break;
										case 3:
											_eLocal7.p = 3;
											_oLocal2 = _eLocal7.v;
											showRequestError( _oLocal2 );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal7.p = 4 ),
												d( ! 1 ),
												_eLocal7.f( 4 )
											 );
										case 5:
											return _eLocal7.a( 2 );
									}
								}
							},
							_eLocal6,
							null,
							[ [ 0, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal5.apply( this, _args );
				};
			} )(),
			v = ( function () {
				var _eLocal9 = E9(
					b9().m( function _eLocal0( t ) {
						var _nLocal3, _rLocal3, _aLocal2;
						return b9().w(
							function ( _eLocal1 ) {
								for (;;) {
									switch ( ( _eLocal1.p = _eLocal1.n ) ) {
										case 0:
											return (
												( _eLocal1.p = 0 ),
												( _eLocal1.n = 1 ),
												l()( {
													path: '/ohmylms/v1/courses/'.concat(
														t
													),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											if ( ( _nLocal3 = _eLocal1.v ) ) {
												{
													_rLocal3 = {
														id: _nLocal3.id,
														name: _nLocal3.name,
													};
													c( _rLocal3 );
												}
											}
											_eLocal1.n = 3;
											break;
										case 2:
											_eLocal1.p = 2;
											_aLocal2 = _eLocal1.v;
											showRequestError(
												'Error fetching course by ID:',
												_aLocal2
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return _eLocal1.a( 2 );
									}
								}
							},
							_eLocal0,
							null,
							[ [ 0, 2 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _eLocal9.apply( this, _args2 );
				};
			} )(),
			h = ( 0, ReactHooks.useCallback )(
				function ( _eLocal10 ) {
					if ( _eLocal10 ) {
						var t = {
							id: _eLocal10.value,
							name: _eLocal10.label,
						};
						c( t );
						m( 'parent_id', _eLocal10.value );
					} else {
						c( null );
						m( 'parent_id', null );
					}
				},
				[ e ]
			),
			_ = ( 0, ReactHooks.useCallback )( function ( _eLocal11 ) {
				f( _eLocal11 );
			}, [] );
		return (
			( 0, ReactHooks.useEffect )( function () {
				f( '' );
			}, [] ),
			( 0, ReactHooks.useEffect )(
				function () {
					if ( e !== null && e !== void 0 && e.parent_id && ! i ) {
						v( e.parent_id );
					}
				},
				[ e === null || e === void 0 ? void 0 : e.parent_id, i ]
			),
			(
				<Controls.SpacerWP marginTop={ 4 }>
					<Card isBorderless={ ! 0 } variant={ 'secondary' }>
						<Controls.SpacerWP padding={ 6 } margin={ 0 }>
							<Controls.HeadingWP level={ 4 }>
								{ ( 0, I18n.__ )( 'Course', 'ohmylms' ) }
							</Controls.HeadingWP>
							<Controls.SpacerWP />
							<RecoveredControls.AdvancedSelectWP
								isClearable={ ! 0 }
								closeMenuOnSelect={ ! 0 }
								value={
									i
										? {
												label: i.name,
												value: i.id,
											}
										: null
								}
								options={ r }
								onChange={ h }
								isDisabled={ s }
								onSearch={ _ }
								placeholder={ ( 0, I18n.__ )(
									'Select a course...',
									'ohmylms'
								) }
							/>
						</Controls.SpacerWP>
					</Card>
				</Controls.SpacerWP>
			 )
		 );
	};
}
