import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMigrationProgressModal( readRuntime ) {
	return function MigrationProgressModal() {
		const {
			I: Controls,
			M4,
			N4,
			React,
			T: StoreModule,
			T4,
			b: I18n,
			f: Router,
			g: ReactHooks,
			j4,
			y: WordPressData,
		} = readRuntime();
		const { useCallback } = ReactHooks;
		const { useSelect } = WordPressData;
		var t = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var n = ( 0, Router.Zp )();
		var r = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getMigrationModalOpen();
		}, [] );
		var a = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getMigrationStatus();
		}, [] );
		var o = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getMigrationCourses();
		}, [] );
		var i = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getMigrationToolCourses();
			},
			[ StoreModule.default ]
		);
		var l = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getMigratedCourse();
			},
			[ StoreModule.default ]
		);
		var c = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getMigrationTool();
		}, [] );
		var u = ( function ( e, _tLocal ) {
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
						var _rLocal;
						var _aLocal;
						var _oLocal;
						var _iLocal;
						var _lLocal = [];
						var _cLocal = ! 0;
						var _uLocal = ! 1;
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
							return T4( _eLocal4, _tLocal3 );
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
									return T4( _eLocal4, _tLocal3 );
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
		} )( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var s = u[ 0 ];
		var d = u[ 1 ];
		var m = ( 0, ReactHooks.useRef )( ! 1 );
		( 0, ReactHooks.useEffect )(
			function () {
				if (
					r &&
					'migrating' === a &&
					! m.current &&
					c &&
					0 !== o.length
				) {
					m.current = ! 0;
					t.setMigratedCourse( [] );
					var e = ( function () {
						var _eLocal5;
						var _nLocal3 =
							( ( _eLocal5 = j4().m( function _eLocal6() {
								var _nLocal4;
								var _rLocal2;
								return j4().w(
									function ( _eLocal7 ) {
										for (;;) {
											switch (
												( _eLocal7.p = _eLocal7.n )
											) {
												case 0:
													_eLocal7.p = 0;
													_nLocal4 = 0;
												// Falls through to the shared async state cleanup.
												case 1:
													if (
														! (
															_nLocal4 < o.length
														)
													) {
														_eLocal7.n = 3;
														break;
													}
													return (
														( _eLocal7.n = 2 ),
														t.migrateSingleCourse(
															c,
															o[ _nLocal4 ]
														)
													 );
												case 2:
													_nLocal4++;
													_eLocal7.n = 1;
													break;
												case 3:
													_eLocal7.n = 5;
													break;
												case 4:
													_eLocal7.p = 4;
													_rLocal2 = _eLocal7.v;
													showRequestError(
														'Migration failed',
														_rLocal2
													);
												// Falls through to the shared async state cleanup.
												case 5:
													return (
														( _eLocal7.p = 5 ),
														t.setMigrationStatus(
															null
														),
														d( ! 0 ),
														_eLocal7.f( 5 )
													 );
												case 6:
													return _eLocal7.a( 2 );
											}
										}
									},
									_eLocal6,
									null,
									[ [ 0, 4, 5, 6 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal4 = this;
								var _nLocal5 = _args;
								return new Promise( function (
									_rLocal3,
									_aLocal2
								) {
									var _oLocal2 = _eLocal5.apply(
										_tLocal4,
										_nLocal5
									);
									function _iLocal2( _eLocal8 ) {
										M4(
											_oLocal2,
											_rLocal3,
											_aLocal2,
											_iLocal2,
											_lLocal2,
											'next',
											_eLocal8
										);
									}
									function _lLocal2( _eLocal9 ) {
										M4(
											_oLocal2,
											_rLocal3,
											_aLocal2,
											_iLocal2,
											_lLocal2,
											'throw',
											_eLocal9
										);
									}
									_iLocal2( void 0 );
								} );
							} );
						return function ( ..._args2 ) {
							return _nLocal3.apply( this, _args2 );
						};
					} )();
					e();
				}
			},
			[ r, a ]
		);
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! r ) {
					{
						m.current = ! 1;
						d( ! 1 );
						t.setMigratedCourse( [] );
					}
				}
			},
			[ r ]
		);
		var p = useCallback(
			function () {
				if ( 'migrating' !== a ) {
					{
						t.setMigrationModalOpen( ! 1 );
						t.setMigrationStatus( null );
						t.setMigrationCourses( [] );
						t.setMigratedCourse( [] );
						d( ! 1 );
						m.current = ! 1;
					}
				}
			},
			[ a, d, m, t ]
		);
		var v = useCallback(
			function () {
				t.setMigrationModalOpen( ! 1 );
				t.setMigrationStatus( null );
				t.setMigrationCourses( [] );
				t.setMigratedCourse( [] );
				d( ! 1 );
				m.current = ! 1;
				n( '/courses' );
			},
			[ d, m, n, t ]
		);
		if ( ! r ) {
			return null;
		}
		var h = o.length;
		var _ = l.length;
		var w = h > 0 ? Math.round( ( _ / h ) * 100 ) : 0;
		var E = _ < h ? _ : -1;
		return s ? (
			<Controls.ModalWP
				title={ '' }
				onRequestClose={ p }
				style={ {
					maxWidth: '720px',
					width: '100%',
				} }
			>
				<div
					style={ {
						padding: '56px 24px',
						display: 'flex',
						flexDirection: 'column',
						alignItems: 'center',
						gap: '56px',
					} }
				>
					<div
						style={ {
							display: 'flex',
							flexDirection: 'column',
							alignItems: 'center',
							gap: '16px',
						} }
					>
						<Controls.HeadingWP
							as={ 'h2' }
							size={ '40px' }
							weight={ '700' }
							color={ '#000d25' }
							style={ {
								margin: 0,
								lineHeight: '1.4',
								letterSpacing: '-0.4px',
								textAlign: 'center',
							} }
						>
							{ '🎉 ' }
							{ ( 0, I18n.__ )(
								'Migration Complete!',
								'ohmylms'
							) }
							<br />
							{ ( 0, I18n.__ )(
								'Your courses are safe.',
								'ohmylms'
							) }
						</Controls.HeadingWP>
						<Controls.TextWP
							as={ 'p' }
							size={ '18' }
							weight={ '500' }
							color={ '#687784' }
							style={ {
								margin: 0,
								maxWidth: '668px',
								lineHeight: '1.3',
								textAlign: 'center',
							} }
						>
							{ ( 0, I18n.__ )(
								'Your courses have been successfully imported. Everything is safe and private — ready for you to review and refine.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</div>
					<div
						style={ {
							display: 'flex',
							flexDirection: 'column',
							alignItems: 'center',
							gap: '16px',
						} }
					>
						<Controls.ButtonWP variant={ 'primary' } onClick={ v }>
							<Controls.TextWP
								size={ '16px' }
								color={ '#FFFFFF' }
							>
								{ ( 0, I18n.__ )(
									'Go to My Courses',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.ButtonWP>
						<Controls.TextWP
							as={ 'p' }
							size={ '14' }
							color={ '#687784' }
							style={ {
								margin: 0,
								textAlign: 'center',
								maxWidth: '452px',
							} }
						>
							{ ( 0, I18n.__ )(
								'Nothing is published yet. Review your course whenever you like.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</div>
				</div>
			</Controls.ModalWP>
		) : (
			<Controls.ModalWP
				title={ ( 0, I18n.__ )( 'Content migration', 'ohmylms' ) }
				onRequestClose={ p }
				style={ {
					maxWidth: '720px',
					width: '100%',
				} }
			>
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: '24px',
					} }
				>
					<div
						style={ {
							background: 'white',
							border: '1px solid rgba(200,210,233,0.5)',
							borderRadius: '4px',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'space-between',
							padding: '8px 9px',
						} }
					>
						<div
							style={ {
								padding: '8px',
								display: 'flex',
								flexDirection: 'column',
								gap: '20px',
							} }
						>
							<Controls.TextWP
								as={ 'p' }
								size={ '18' }
								weight={ '600' }
								color={ '#000d25' }
								style={ {
									margin: 0,
								} }
							>
								{ ( 0, I18n.__ )(
									'Migration Progress',
									'ohmylms'
								) }
							</Controls.TextWP>
							<Controls.TextWP
								as={ 'p' }
								size={ '14' }
								weight={ '500' }
								color={ '#000d25' }
								style={ {
									margin: 0,
									letterSpacing: '-0.14px',
								} }
							>
								{ ( 0, I18n.__ )(
									'Migrating your content...',
									'ohmylms'
								) }
							</Controls.TextWP>
						</div>
						<div
							style={ {
								padding: '8px',
								textAlign: 'right',
							} }
						>
							<Controls.TextWP
								as={ 'p' }
								size={ '14' }
								color={ '#444d5e' }
								style={ {
									margin: '0 0 4px',
								} }
							>
								{ ( 0, I18n.__ )( 'Progress', 'ohmylms' ) }
							</Controls.TextWP>
							<span
								style={ {
									fontSize: '18px',
									fontWeight: 600,
									color: '#0cae32',
									letterSpacing: '-0.44px',
								} }
							>
								{ _ }
								{ ' / ' }
								{ h }
							</span>
						</div>
					</div>
					<div
						style={ {
							height: '12px',
							backgroundColor: 'rgba(200,210,233,0.5)',
							borderRadius: '9999px',
							overflow: 'hidden',
						} }
					>
						<div
							style={ {
								height: '100%',
								width: ''.concat( w, '%' ),
								backgroundColor: '#0cae32',
								borderRadius: '9999px',
								transition: 'width 0.5s ease',
							} }
						/>
					</div>
					<div
						style={ {
							backgroundColor: '#f4f5f7',
							borderRadius: '8px',
							padding: '24px 24px 30px',
						} }
					>
						<Controls.TextWP
							as={ 'p' }
							size={ '18' }
							weight={ '600' }
							color={ '#000d25' }
							style={ {
								margin: '0 0 24px',
							} }
						>
							{ ( 0, I18n.__ )(
								'Migration Checklist',
								'ohmylms'
							) }
						</Controls.TextWP>
						<Controls.FlexWP direction={ 'column' } gap={ 3 }>
							{ o.map( function ( e, _tLocal5 ) {
								var _nLocal6 = i.find( function ( _tLocal6 ) {
									return _tLocal6.value === e;
								} );
								var _rLocal4 =
									( _nLocal6 === null || _nLocal6 === void 0
										? void 0
										: _nLocal6.label ) || String( e );
								var _aLocal3 = ( () => {
									if ( l.includes( e ) ) {
										return 'complete';
									}
									if ( _tLocal5 === E ) {
										return 'processing';
									}
									return 'pending';
								} )();
								return (
									<N4
										key={ e }
										label={ _rLocal4 }
										index={ _tLocal5 }
										status={ _aLocal3 }
									/>
								);
							} ) }
						</Controls.FlexWP>
					</div>
				</div>
			</Controls.ModalWP>
		);
	};
}
