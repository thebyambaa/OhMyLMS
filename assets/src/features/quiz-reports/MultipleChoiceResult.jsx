/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createMultipleChoiceResult( readRuntime ) {
	return function MultipleChoiceResult( props ) {
		const { React, b: I18n, p$: QuizQuestionHeader } = readRuntime();
		var t,
			n,
			data = props.data,
			index = props.index,
			o = ( function ( e ) {
				var _tLocal,
					_nLocal = e.given_answer,
					r = e.questions;
				if ( ! _nLocal || ! Array.isArray( _nLocal ) ) {
					return 'in-review';
				}
				var a = r
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
					_oLocal =
						_nLocal === null || _nLocal === void 0
							? void 0
							: _nLocal.every( function ( _eLocal3 ) {
									return a === null || a === void 0
										? void 0
										: a.includes( _eLocal3 );
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
							_oLocal &&
							Number(
								_nLocal === null || _nLocal === void 0
									? void 0
									: _nLocal.length
							) === 1 &&
							a !== null &&
							a !== void 0 &&
							a.includes( _nLocal[ 0 ] )
						) {
							return 'correct';
						}
						return 'incorrect';
					}
					return ( () => {
						if ( 'multiple-choice' === e.settings.type ) {
							if (
								_oLocal &&
								( _nLocal === null || _nLocal === void 0
									? void 0
									: _nLocal.length ) ===
									( a === null || a === void 0
										? void 0
										: a.length )
							) {
								return 'correct';
							}
							return 'incorrect';
						}
						return 'in-review';
					} )();
				} )();
			} )( data );
		return (
			<React.Fragment>
				<div
					className={ 'ohmylms-question-types ohmylms-multiple-choice-question ohmylms-'.concat(
						o
					) }
				>
					<QuizQuestionHeader data={ data } index={ index } />
					<div>
						<Controls.TextWP
							as={ 'p' }
							size={ 14 }
							variant={ 'muted' }
						>
							{ ( 0, I18n.__ )( 'Select multiple', 'ohmylms' ) }
						</Controls.TextWP>
						<Controls.SpacerWP marginBottom={ 2 } />
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							style={ {
								padding: '16px',
							} }
						>
							<Controls.FlexWP direction={ 'column' } gap={ 3 }>
								{ data === null ||
								data === void 0 ||
								null === ( t = data.questions ) ||
								void 0 === t
									? void 0
									: t.map( function ( e, _tLocal2 ) {
											var _nLocal2, a, _oLocal2, i;
											return (
												<Controls.CheckboxWP
													key={ _tLocal2 }
													value={
														e === null ||
														e === void 0
															? void 0
															: e.id
													}
													disabled={ ! 0 }
													checked={
														( data === null ||
														data === void 0 ||
														null ===
															( _nLocal2 =
																data.given_answer ) ||
														void 0 === _nLocal2
															? void 0
															: _nLocal2.includes(
																	Number(
																		e ===
																			null ||
																			e ===
																				void 0
																			? void 0
																			: e.id
																	)
																) ) ||
														( data === null ||
														data === void 0 ||
														null ===
															( a =
																data.given_answer ) ||
														void 0 === a
															? void 0
															: a.includes(
																	e ===
																		null ||
																		e ===
																			void 0
																		? void 0
																		: e.id
																) )
													}
													className={ '\n                                                '
														.concat(
															( data !== null &&
																data !==
																	void 0 &&
																null !==
																	( _oLocal2 =
																		data.given_answer ) &&
																void 0 !==
																	_oLocal2 &&
																_oLocal2.includes(
																	Number(
																		e ===
																			null ||
																			e ===
																				void 0
																			? void 0
																			: e.id
																	)
																) ) ||
																( data !==
																	null &&
																	data !==
																		void 0 &&
																	null !==
																		( i =
																			data.given_answer ) &&
																	void 0 !==
																		i &&
																	i.includes(
																		e ===
																			null ||
																			e ===
																				void 0
																			? void 0
																			: e.id
																	) )
																? 'ohmylms-selected'
																: '',
															'\n                                                    '
														)
														.concat(
															Number(
																e === null ||
																	e === void 0
																	? void 0
																	: e.is_correct
															) === 1
																? 'ohmylms-correct'
																: '',
															'\n                                            '
														) }
													label={
														e === null ||
														e === void 0
															? void 0
															: e.answer
													}
												/>
											);
										} ) }
							</Controls.FlexWP>
						</Controls.CardWP>
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
								{ ( 0, I18n.__ )( 'Answer of this question:' ) }
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
												( e === null || e === void 0
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
										( 0, I18n.__ )( 'No correct answer' ) }
								</strong>
							</Controls.HeadingWP>
						</Controls.CardWP>
					</div>
				</div>
			</React.Fragment>
		);
	};
}
