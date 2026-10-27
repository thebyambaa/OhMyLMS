/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createQuestionList( readRuntime ) {
	return function QuestionList() {
		const {
			Bc,
			Ec: QuestionValidation,
			Gc,
			I: Controls,
			Lc,
			React,
			T: StoreModule,
			Uc,
			Vc,
			b: I18n,
			g: ReactHooks,
			uc,
			y: WordPressData,
		} = readRuntime();
		var e,
			t = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			quiz = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getQuiz();
			}, [] ),
			r =
				( 0, WordPressData.useSelect )(
					function ( _eLocal2 ) {
						return _eLocal2(
							StoreModule.default
						).getAllQuestions();
					},
					[ quiz === null || quiz === void 0 ? void 0 : quiz.id ]
				) || [],
			a = ( function ( _eLocal3, _tLocal ) {
				return (
					( function ( _eLocal4 ) {
						if ( Array.isArray( _eLocal4 ) ) {
							return _eLocal4;
						}
					} )( _eLocal3 ) ||
					( function ( _eLocal5, _tLocal2 ) {
						var n =
							_eLocal5 === null || _eLocal5 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal5[ Symbol.iterator ] ) ||
									_eLocal5[ '@@iterator' ];
						if ( n !== null && n !== void 0 ) {
							var _rLocal,
								_aLocal,
								o,
								i,
								l = [],
								c = ! 0,
								u = ! 1;
							try {
								if (
									( ( o = ( n = n.call( _eLocal5 ) ).next ),
									0 === _tLocal2 )
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
										l.length !== _tLocal2 );
										c = ! 0
									) {}
								}
							} catch ( _eLocal6 ) {
								u = ! 0;
								_aLocal = _eLocal6;
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
										throw _aLocal;
									}
								}
							}
							return l;
						}
					} )( _eLocal3, _tLocal ) ||
					( function ( _eLocal7, _tLocal3 ) {
						if ( _eLocal7 ) {
							if ( 'string' === typeof _eLocal7 ) {
								return Uc( _eLocal7, _tLocal3 );
							}
							var n = {}.toString.call( _eLocal7 ).slice( 8, -1 );
							return (
								'Object' === n &&
									_eLocal7.constructor &&
									( n = _eLocal7.constructor.name ),
								( () => {
									if ( 'Map' === n || 'Set' === n ) {
										return Array.from( _eLocal7 );
									}
									if (
										'Arguments' === n ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											n
										)
									) {
										return Uc( _eLocal7, _tLocal3 );
									}
									return void 0;
								} )()
							 );
						}
					} )( _eLocal3, _tLocal ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			o = ( a[ 0 ], a[ 1 ] ),
			i =
				( ( 0, WordPressData.useSelect )( function ( _eLocal8 ) {
					return _eLocal8( StoreModule.default ).getQuestionErrors();
				}, [] ),
				( 0, WordPressData.useSelect )( function ( _eLocal9 ) {
					return _eLocal9( StoreModule.default ).selectQuestion();
				}, [] ) ),
			l =
				( ( 0, WordPressData.useSelect )( function ( _eLocal0 ) {
					return _eLocal0( StoreModule.default ).selectQuizzesError();
				}, [] ),
				Lc().isValidQuestion,
				( 0, QuestionValidation.$ )( i ).isValid,
				( function () {
					var _eLocal1,
						n =
							( ( _eLocal1 = Vc().m( function _eLocal10() {
								var _nLocal;
								return Vc().w( function ( _eLocal11 ) {
									for (;;) {
										switch ( _eLocal11.n ) {
											case 0:
												if (
													( 0, QuestionValidation.$ )(
														i
													).isValid ||
													! ( 0 < r.length )
												) {
													_eLocal11.n = 1;
													break;
												}
												return (
													t.setQuizError( ! 0 ),
													_eLocal11.a( 2 )
												 );
											case 1:
												t.setQuizError( ! 1 ); // Falls through to the shared async state cleanup.
											// Falls through to the shared async state cleanup.
											case 2:
												o( ! 0 );
												_nLocal = new Date().getTime();
												t.setQuestions( {
													name: 'Untitled',
													description: '',
													order_number: r.length + 1,
													id: _nLocal,
													temp: ! 0,
												} );
												o( ! 1 );
												t.setSelectedQuestionId(
													_nLocal
												); // Falls through to the shared async state cleanup.
											// Falls through to the shared async state cleanup.
											case 3:
												return _eLocal11.a( 2 );
										}
									}
								}, _eLocal10 );
							} ) ),
							function ( ..._args ) {
								var _tLocal4 = this,
									_nLocal2 = _args;
								return new Promise( function (
									_rLocal2,
									_aLocal2
								) {
									var _oLocal = _eLocal1.apply(
										_tLocal4,
										_nLocal2
									);
									function _iLocal( _eLocal12 ) {
										Gc(
											_oLocal,
											_rLocal2,
											_aLocal2,
											_iLocal,
											_lLocal,
											'next',
											_eLocal12
										);
									}
									function _lLocal( _eLocal13 ) {
										Gc(
											_oLocal,
											_rLocal2,
											_aLocal2,
											_iLocal,
											_lLocal,
											'throw',
											_eLocal13
										);
									}
									_iLocal( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return n.apply( this, _args2 );
					};
				} )() );
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					if ( 0 === r.length ) {
						l();
					}
				},
				[ r ]
			),
			(
				<React.Fragment>
					<div className={ 'ohmylms-editor-left-sidebar' }>
						<div className={ 'ohmylms-add-question-wrapper' }>
							<Controls.ButtonWP
								variant={ 'secondary' }
								icon={ React.createElement( uc, null ) }
								onClick={ l }
								disabled={
									1 === r.length &&
									! (
										i !== null &&
										i !== void 0 &&
										null !== ( e = i.settings ) &&
										void 0 !== e &&
										e.type
									)
								}
							>
								{ ( 0, I18n.__ )( 'Add Question', 'ohmylms' ) }
							</Controls.ButtonWP>
						</div>
						<Bc />
					</div>
				</React.Fragment>
			 )
		 );
	};
}
