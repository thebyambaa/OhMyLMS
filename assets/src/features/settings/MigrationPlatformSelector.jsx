import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMigrationPlatformSelector( readRuntime ) {
	return function MigrationPlatformSelector() {
		const {
			$4,
			B4,
			G4,
			H4,
			I: Controls,
			K4,
			React,
			T: StoreModule,
			U4,
			V4,
			W4: MemoMigrationProgressModal,
			_6,
			b: I18n,
			g: ReactHooks,
			l,
			y: WordPressData,
		} = readRuntime();
		var e = H4(
				( 0, ReactHooks.useState )( function () {
					var _eLocal, t;
					return null !==
						( _eLocal =
							null === ( t = _6[ 0 ] ) || void 0 === t
								? void 0
								: t.value ) && void 0 !== _eLocal
						? _eLocal
						: null;
				} ),
				2
			),
			t = e[ 0 ],
			n = e[ 1 ],
			r = H4( ( 0, ReactHooks.useState )( [] ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = H4( ( 0, ReactHooks.useState )( [] ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = H4( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			f = _6.find( function ( _eLocal2 ) {
				return _eLocal2.value === t;
			} );
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( ! t ) {
						return ( o( [] ), void u( [] ) );
					}
					var _eLocal3 = ( function () {
						var _eLocal4,
							_nLocal =
								( ( _eLocal4 = B4().m( function _eLocal5() {
									var _nLocal2, _rLocal;
									return B4().w(
										function ( _eLocal6 ) {
											for (;;) {
												switch (
													( _eLocal6.p = _eLocal6.n )
												) {
													case 0:
														return (
															m( ! 0 ),
															( _eLocal6.p = 1 ),
															( _eLocal6.n = 2 ),
															l()( {
																path: '/ohmylms/v1/migrations/'.concat(
																	t,
																	'/courses'
																),
															} )
														 );
													case 2:
														if (
															( (
																_nullableValue
															) =>
																_nullableValue !==
																	null &&
																_nullableValue !==
																	void 0 )(
																( _nLocal2 =
																	_eLocal6.v )
															) &&
															_nLocal2.courses
														) {
															{
																o(
																	_nLocal2.courses
																);
																u(
																	_nLocal2.courses.map(
																		function (
																			_eLocal7
																		) {
																			return _eLocal7.id;
																		}
																	)
																);
															}
														}
														_eLocal6.n = 4;
														break;
													case 3:
														_eLocal6.p = 3;
														_rLocal = _eLocal6.v;
														showRequestError(
															'Failed to fetch migration courses',
															_rLocal
														);
													// Falls through to the shared async state cleanup.
													case 4:
														return (
															( _eLocal6.p = 4 ),
															m( ! 1 ),
															_eLocal6.f( 4 )
														 );
													case 5:
														return _eLocal6.a( 2 );
												}
											}
										},
										_eLocal5,
										null,
										[ [ 1, 3, 4, 5 ] ]
									);
								} ) ),
								function ( ..._args ) {
									var _tLocal = this,
										_nLocal3 = _args;
									return new Promise( function (
										_rLocal2,
										_aLocal
									) {
										var _oLocal = _eLocal4.apply(
											_tLocal,
											_nLocal3
										);
										function _iLocal( _eLocal8 ) {
											V4(
												_oLocal,
												_rLocal2,
												_aLocal,
												_iLocal,
												_lLocal,
												'next',
												_eLocal8
											);
										}
										function _lLocal( _eLocal9 ) {
											V4(
												_oLocal,
												_rLocal2,
												_aLocal,
												_iLocal,
												_lLocal,
												'throw',
												_eLocal9
											);
										}
										_iLocal( void 0 );
									} );
								} );
						return function ( ..._args2 ) {
							return _nLocal.apply( this, _args2 );
						};
					} )();
					_eLocal3();
				},
				[ t ]
			),
			(
				<React.Fragment>
					<Controls.CardWP isBorderless={ ! 0 } padding={ '24px' }>
						<Controls.HeadingWP
							as={ 'h4' }
							size={ '18px' }
							color={ '#000D25' }
							weight={ '600' }
						>
							{ ( 0, I18n.__ )(
								'Which platform do you want to migrate from?',
								'ohmylms'
							) }
						</Controls.HeadingWP>
						<Controls.CardWP
							variant={ 'secondary' }
							isBorderless={ ! 0 }
							padding={ '16px' }
							margin={ '24px 0 0' }
						>
							<Controls.FlexWP
								wrap={ 'wrap' }
								gap={ 4 }
								justify={ 'flex-start' }
								align={ 'stretch' }
							>
								{ _6.map( function ( _eLocal0 ) {
									return (
										<$4
											key={ _eLocal0.value }
											lms={ _eLocal0 }
											isSelected={ t === _eLocal0.value }
											onSelect={ n }
										/>
									);
								} ) }
							</Controls.FlexWP>
						</Controls.CardWP>
						{ f && (
							<K4
								lms={ f }
								courses={ a }
								selectedIds={ c }
								onToggle={ function ( _eLocal1 ) {
									u( function ( _tLocal2 ) {
										return _tLocal2.includes( _eLocal1 )
											? _tLocal2.filter(
													function ( _tLocal3 ) {
														return (
															_tLocal3 !==
															_eLocal1
														);
													}
												)
											: [].concat(
													( function ( _eLocal10 ) {
														return (
															( function (
																_eLocal11
															) {
																if (
																	Array.isArray(
																		_eLocal11
																	)
																) {
																	return U4(
																		_eLocal11
																	);
																}
															} )( _eLocal10 ) ||
															( function (
																_eLocal12
															) {
																if (
																	( 'undefined' !==
																		typeof Symbol &&
																		( (
																			_nullableValue2
																		) =>
																			_nullableValue2 !==
																				null &&
																			_nullableValue2 !==
																				void 0 )(
																			_eLocal12[
																				Symbol
																					.iterator
																			]
																		) ) ||
																	( (
																		_nullableValue3
																	) =>
																		_nullableValue3 !==
																			null &&
																		_nullableValue3 !==
																			void 0 )(
																		_eLocal12[
																			'@@iterator'
																		]
																	)
																) {
																	return Array.from(
																		_eLocal12
																	);
																}
															} )( _eLocal10 ) ||
															G4( _eLocal10 ) ||
															( function () {
																throw new TypeError(
																	'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
																);
															} )()
														);
													} )( _tLocal2 ),
													[ _eLocal1 ]
												);
									} );
								} }
								onToggleAll={ function () {
									u(
										c.length === a.length
											? []
											: a.map( function ( _eLocal13 ) {
													return _eLocal13.id;
												} )
									);
								} }
								isLoading={ d }
							/>
						) }
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
								onClick={ function () {
									if ( t && 0 !== c.length ) {
										var _eLocal14 = a.filter(
											function ( _eLocal15 ) {
												return c.includes(
													_eLocal15.id
												);
											}
										);
										p.setMigrationTool( t );
										p.setMigrationToolCourses(
											_eLocal14.map(
												function ( _eLocal16 ) {
													return {
														value: _eLocal16.id,
														label:
															_eLocal16.title ||
															_eLocal16.label,
													};
												}
											)
										);
										p.setMigrationCourses( c );
										p.setMigrationStatus( 'migrating' );
										p.setMigrationModalOpen( ! 0 );
									}
								} }
								disabled={ ! t || 0 === c.length }
							>
								{ ( 0, I18n.__ )(
									'Migrate courses',
									'ohmylms'
								) }
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					{ f && <MemoMigrationProgressModal lms={ f.label } /> }
				</React.Fragment>
			 )
		 );
	};
}
