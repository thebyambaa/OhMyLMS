/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Tooltip } from '../../shared/tooltip';
import * as Controls from '../../shared/controls';
export function createTextAnswerResult( readRuntime ) {
	return function TextAnswerResult( props ) {
		const {
			React,
			_$,
			b: I18n,
			b$: AnswerIcon,
			g: ReactHooks,
			p$: QuizQuestionHeader,
		} = readRuntime();
		var t,
			n,
			index = props.index,
			data = props.data,
			o = ( props.type, props.fetchData ),
			setData = props.setData,
			l = ( function ( e ) {
				var _tLocal,
					_nLocal,
					r = e.given_answer,
					a = e.questions;
				if ( ! r || ! Array.isArray( r ) ) {
					return 'in-review';
				}
				var _oLocal = a
						.filter( function ( _eLocal ) {
							return (
								Number(
									_eLocal === null || _eLocal === void 0
										? void 0
										: _eLocal.is_correct
								) === 1
							);
						} )
						.map( function ( _eLocal2 ) {
							return _eLocal2 === null || _eLocal2 === void 0
								? void 0
								: _eLocal2.id;
						} ),
					i = r.every( function ( _eLocal3 ) {
						return _oLocal === null || _oLocal === void 0
							? void 0
							: _oLocal.includes( _eLocal3 );
					} );
				return ( () => {
					if (
						'single-choice' ===
						( e === null ||
						e === void 0 ||
						null === ( _tLocal = e.settings ) ||
						void 0 === _tLocal
							? void 0
							: _tLocal.type )
					) {
						if (
							i &&
							1 ===
								( r === null || r === void 0
									? void 0
									: r.length ) &&
							_oLocal !== null &&
							_oLocal !== void 0 &&
							_oLocal.includes( r[ 0 ] )
						) {
							return 'correct';
						}
						return 'incorrect';
					}
					return ( () => {
						if (
							'multiple-choice' ===
							( e === null ||
							e === void 0 ||
							null === ( _nLocal = e.settings ) ||
							void 0 === _nLocal
								? void 0
								: _nLocal.type )
						) {
							if (
								i &&
								( r === null || r === void 0
									? void 0
									: r.length ) ===
									( _oLocal === null || _oLocal === void 0
										? void 0
										: _oLocal.length )
							) {
								return 'correct';
							}
							return 'incorrect';
						}
						return 'in-review';
					} )();
				} )();
			} )( data ),
			c = ( function ( e, _tLocal2 ) {
				return (
					( function ( _eLocal4 ) {
						if ( Array.isArray( _eLocal4 ) ) {
							return _eLocal4;
						}
					} )( e ) ||
					( function ( _eLocal5, _tLocal3 ) {
						var _nLocal2 =
							_eLocal5 === null || _eLocal5 === void 0
								? null
								: ( 'undefined' !== typeof Symbol &&
										_eLocal5[ Symbol.iterator ] ) ||
									_eLocal5[ '@@iterator' ];
						if ( _nLocal2 !== null && _nLocal2 !== void 0 ) {
							var r,
								a,
								_oLocal2,
								i,
								_lLocal = [],
								_cLocal = ! 0,
								u = ! 1;
							try {
								if (
									( ( _oLocal2 = ( _nLocal2 =
										_nLocal2.call( _eLocal5 ) ).next ),
									0 === _tLocal3 )
								) {
									if ( Object( _nLocal2 ) !== _nLocal2 ) {
										return;
									}
									_cLocal = ! 1;
								} else {
									for (
										;
										! ( _cLocal = ( r =
											_oLocal2.call( _nLocal2 ) )
											.done ) &&
										( _lLocal.push( r.value ),
										_lLocal.length !== _tLocal3 );
										_cLocal = ! 0
									) {}
								}
							} catch ( _eLocal6 ) {
								u = ! 0;
								a = _eLocal6;
							} finally {
								try {
									if (
										! _cLocal &&
										( ( _nullableValue ) =>
											_nullableValue !== null &&
											_nullableValue !== void 0 )(
											_nLocal2.return
										) &&
										( ( i = _nLocal2.return() ),
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
							return _lLocal;
						}
					} )( e, _tLocal2 ) ||
					( function ( _eLocal7, _tLocal4 ) {
						if ( _eLocal7 ) {
							if ( 'string' === typeof _eLocal7 ) {
								return _$( _eLocal7, _tLocal4 );
							}
							var _nLocal3 = {}.toString
								.call( _eLocal7 )
								.slice( 8, -1 );
							return (
								'Object' === _nLocal3 &&
									_eLocal7.constructor &&
									( _nLocal3 = _eLocal7.constructor.name ),
								( () => {
									if (
										'Map' === _nLocal3 ||
										'Set' === _nLocal3
									) {
										return Array.from( _eLocal7 );
									}
									if (
										'Arguments' === _nLocal3 ||
										/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
											_nLocal3
										)
									) {
										return _$( _eLocal7, _tLocal4 );
									}
									return void 0;
								} )()
							 );
						}
					} )( e, _tLocal2 ) ||
					( function () {
						throw new TypeError(
							'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
						);
					} )()
				);
			} )( ( 0, ReactHooks.useState )( 0 ), 2 );
		return (
			c[ 0 ],
			c[ 1 ],
			(
				<React.Fragment>
					<div
						className={ 'ohmylms-question-types ohmylms-text-type-question ohmylms-'
							.concat( l, ' ' )
							.concat(
								data !== null &&
									data !== void 0 &&
									data.explanation
									? 'ohmylms-has-explanation'
									: ''
							) }
					>
						<QuizQuestionHeader
							setData={ setData }
							data={ data }
							index={ index }
							type={ 'text-type' }
							fetchData={ o }
						/>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							style={ {
								padding: '16px',
							} }
						>
							<Controls.TextWP
								as={ 'p' }
								size={ 14 }
								variant={ 'muted' }
							>
								{ ( 0, I18n.__ )(
									"Student's response:",
									'ohmylms'
								) }
							</Controls.TextWP>
							<div
								className={
									'ohmylms-question-options ohmylms-text-type'
								}
							>
								{ ( data !== null &&
									data !== void 0 &&
									data.given_answer &&
									( data === null || data === void 0
										? void 0
										: data.given_answer[ 0 ] ) ) ||
									( 0, I18n.__ )(
										'No answer given',
										'ohmylms'
									) }
							</div>
							{ ( data === null || data === void 0
								? void 0
								: data.explanation ) && (
								<Tooltip
									title={
										( data === null || data === void 0
											? void 0
											: data.explanation ) ||
										( 0, I18n.__ )(
											'No explanation provided',
											'ohmylms'
										)
									}
								>
									<span
										className={ 'ohmylms-explanation-icon' }
									>
										<AnswerIcon />
									</span>
								</Tooltip>
							) }
						</Controls.CardWP>
						{ 'fill-in-the-blank' ===
							( data === null ||
							data === void 0 ||
							null === ( t = data.settings ) ||
							void 0 === t
								? void 0
								: t.type ) && (
							<React.Fragment>
								<Controls.SpacerWP />
								<Controls.CardWP
									style={ {
										padding: '16px',
									} }
								>
									<Controls.HeadingWP
										level={ 4 }
										size={ '14' }
										weight={ '400' }
									>
										{ ( 0, I18n.__ )(
											'Answer of this question:'
										) }
										<br />
										<strong>
											{ ( data === null ||
											data === void 0 ||
											null === ( n = data.questions ) ||
											void 0 === n ||
											null ===
												( n = n.filter( function ( e ) {
													return (
														'1' ===
														( e === null ||
														e === void 0
															? void 0
															: e.is_correct )
													);
												} ) ) ||
											void 0 === n ||
											null ===
												( n = n.map( function ( e ) {
													return e.answer;
												} ) ) ||
											void 0 === n
												? void 0
												: n.join( ', ' ) ) ||
												( 0, I18n.__ )(
													'No correct answer'
												) }
										</strong>
									</Controls.HeadingWP>
								</Controls.CardWP>
							</React.Fragment>
						) }
					</div>
				</React.Fragment>
			 )
		 );
	};
}
