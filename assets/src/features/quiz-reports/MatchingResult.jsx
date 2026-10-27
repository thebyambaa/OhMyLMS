/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMatchingResult( readRuntime ) {
	return function MatchingResult( props ) {
		const {
			I: Controls,
			React,
			b: I18n,
			g: ReactHooks,
			j$,
			p$: QuizQuestionHeader,
		} = readRuntime();
		var t,
			n,
			r,
			data = props.data,
			index = props.index,
			i =
				( props.type,
				( 0, ReactHooks.useMemo )(
					function () {
						return j$(
							data === null || data === void 0
								? void 0
								: data.given_answer
						);
					},
					[ data ]
				) );
		return (
			<React.Fragment>
				<QuizQuestionHeader
					data={ data }
					index={ index }
					isCorrect={ i }
				/>
				<Controls.SpacerWP marginBottom={ 2 } />
				<Controls.TextWP as={ 'p' } size={ 14 } variant={ 'muted' }>
					{ i
						? ( 0, I18n.__ )( 'Answer', 'ohmylms' )
						: ( 0, I18n.__ )( "Student's Answer", 'ohmylms' ) }
				</Controls.TextWP>
				<Controls.SpacerWP marginBottom={ 2 } />
				{ i ? (
					<React.Fragment>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							padding={ '16px' }
							style={ {
								background: '#E6F7E9',
							} }
						>
							{ data === null ||
							data === void 0 ||
							null === ( r = data.questions ) ||
							void 0 === r ||
							null === ( r = r.slice() ) ||
							void 0 === r ||
							null ===
								( r = r.sort( function ( e, _tLocal ) {
									return (
										Number( e.order_number ) -
										Number( _tLocal.order_number )
									);
								} ) ) ||
							void 0 === r
								? void 0
								: r.map( function ( e, _tLocal2 ) {
										var _nLocal, _rLocal, a, o;
										return (
											<Controls.CardWP
												key={ e.id }
												isBorderless={ ! 0 }
												padding={ '12px 16px' }
												margin={
													Number( _tLocal2 ) === 0
														? '0'
														: '16px 0 0'
												}
											>
												<Controls.FlexWP gap={ 6 }>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														variant={ 'secondary' }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP>
																{ e === null ||
																e === void 0
																	? void 0
																	: e.answer }
															</Controls.TextWP>
															{ ( e === null ||
															e === void 0
																? void 0
																: e.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.image_url
																		}
																		alt={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.answer
																		}
																		style={ {
																			width: '53px',
																			height: '30px',
																			objectFit:
																				'fill',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														variant={ 'secondary' }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP>
																{ e === null ||
																e === void 0 ||
																null ===
																	( _nLocal =
																		e.matching_data ) ||
																void 0 ===
																	_nLocal
																	? void 0
																	: _nLocal.label }
															</Controls.TextWP>
															{ ( e === null ||
															e === void 0 ||
															null ===
																( _rLocal =
																	e.matching_data ) ||
															void 0 === _rLocal
																? void 0
																: _rLocal.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			e ===
																				null ||
																			e ===
																				void 0 ||
																			null ===
																				( a =
																					e.matching_data ) ||
																			void 0 ===
																				a
																				? void 0
																				: a.image_url
																		}
																		alt={
																			e ===
																				null ||
																			e ===
																				void 0 ||
																			null ===
																				( o =
																					e.matching_data ) ||
																			void 0 ===
																				o
																				? void 0
																				: o.label
																		}
																		style={ {
																			width: '53px',
																			height: '30px',
																			objectFit:
																				'fill',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
												</Controls.FlexWP>
											</Controls.CardWP>
										);
									} ) }
						</Controls.CardWP>
					</React.Fragment>
				) : (
					<React.Fragment>
						<Controls.CardWP padding={ '16px' }>
							{ data === null ||
							data === void 0 ||
							null === ( t = data.questions ) ||
							void 0 === t ||
							null === ( t = t.slice() ) ||
							void 0 === t ||
							null ===
								( t = t.sort( function ( e, _tLocal3 ) {
									return (
										Number( e.order_number ) -
										Number( _tLocal3.order_number )
									);
								} ) ) ||
							void 0 === t
								? void 0
								: t.map( function ( e, _tLocal4 ) {
										var _nLocal2,
											_rLocal2,
											o,
											_iLocal,
											l,
											c,
											u =
												data === null ||
												data === void 0 ||
												null ===
													( _nLocal2 =
														data.given_answer ) ||
												void 0 === _nLocal2
													? void 0
													: _nLocal2[
															e === null ||
															e === void 0
																? void 0
																: e.id
														],
											s = ( () => {
												if (
													( e === null || e === void 0
														? void 0
														: e.id ) === u
												) {
													return e;
												}
												if (
													data === null ||
													data === void 0 ||
													null ===
														( _rLocal2 =
															data.questions ) ||
													void 0 === _rLocal2
												) {
													return void 0;
												}
												return _rLocal2.find(
													function ( _eLocal ) {
														return (
															String(
																_eLocal.id
															) === String( u )
														);
													}
												);
											} )();
										return (
											<Controls.CardWP
												key={ e.id }
												isBorderless={ ! 0 }
												padding={ '12px 16px' }
												margin={
													Number( _tLocal4 ) === 0
														? '0'
														: '16px 0 0'
												}
												style={ {
													background:
														( e === null ||
														e === void 0
															? void 0
															: e.id ) === u
															? '#E6F7E9'
															: '#FFEDEE',
												} }
											>
												<Controls.FlexWP gap={ 6 }>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP
																color={
																	( e ===
																		null ||
																	e === void 0
																		? void 0
																		: e.id ) ===
																	u
																		? 'primary'
																		: '#FF4955'
																}
															>
																{ e === null ||
																e === void 0
																	? void 0
																	: e.answer }
															</Controls.TextWP>
															{ ( e === null ||
															e === void 0
																? void 0
																: e.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.image_url
																		}
																		alt={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.answer
																		}
																		style={ {
																			width: '30px',
																			height: '30px',
																			objectFit:
																				'cover',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP
																color={
																	( e ===
																		null ||
																	e === void 0
																		? void 0
																		: e.id ) ===
																	u
																		? 'primary'
																		: '#FF4955'
																}
															>
																{ s === null ||
																s === void 0 ||
																null ===
																	( o =
																		s.matching_data ) ||
																void 0 === o
																	? void 0
																	: o.label }
															</Controls.TextWP>
															{ ( s === null ||
															s === void 0 ||
															null ===
																( _iLocal =
																	s.matching_data ) ||
															void 0 === _iLocal
																? void 0
																: _iLocal.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			s ===
																				null ||
																			s ===
																				void 0 ||
																			null ===
																				( l =
																					s.matching_data ) ||
																			void 0 ===
																				l
																				? void 0
																				: l.image_url
																		}
																		alt={
																			s ===
																				null ||
																			s ===
																				void 0 ||
																			null ===
																				( c =
																					s.matching_data ) ||
																			void 0 ===
																				c
																				? void 0
																				: c.label
																		}
																		style={ {
																			width: '30px',
																			height: '30px',
																			objectFit:
																				'cover',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
												</Controls.FlexWP>
											</Controls.CardWP>
										);
									} ) }
						</Controls.CardWP>
						<Controls.SpacerWP marginTop={ 4 }>
							<Controls.TextWP
								as={ 'p' }
								size={ 14 }
								variant={ 'muted' }
							>
								{ ( 0, I18n.__ )(
									'Correct Answer',
									'ohmylms'
								) }
							</Controls.TextWP>
						</Controls.SpacerWP>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							padding={ '16px' }
							style={ {
								background: '#E6F7E9',
							} }
						>
							{ data === null ||
							data === void 0 ||
							null === ( n = data.questions ) ||
							void 0 === n ||
							null === ( n = n.slice() ) ||
							void 0 === n ||
							null ===
								( n = n.sort( function ( e, _tLocal5 ) {
									return (
										Number( e.order_number ) -
										Number( _tLocal5.order_number )
									);
								} ) ) ||
							void 0 === n
								? void 0
								: n.map( function ( e, _tLocal6 ) {
										var _nLocal3, _rLocal3, a, o;
										return (
											<Controls.CardWP
												key={ e.id }
												isBorderless={ ! 0 }
												padding={ '12px 16px' }
												margin={
													Number( _tLocal6 ) === 0
														? '0'
														: '16px 0 0'
												}
											>
												<Controls.FlexWP gap={ 6 }>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														variant={ 'secondary' }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP>
																{ e === null ||
																e === void 0
																	? void 0
																	: e.answer }
															</Controls.TextWP>
															{ ( e === null ||
															e === void 0
																? void 0
																: e.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.image_url
																		}
																		alt={
																			e ===
																				null ||
																			e ===
																				void 0
																				? void 0
																				: e.answer
																		}
																		style={ {
																			width: '30px',
																			height: '30px',
																			objectFit:
																				'cover',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
													<Controls.CardWP
														width={ '100%' }
														isBorderless={ ! 0 }
														variant={ 'secondary' }
														padding={ '8px' }
													>
														<Controls.FlexWP>
															<Controls.TextWP>
																{ e === null ||
																e === void 0 ||
																null ===
																	( _nLocal3 =
																		e.matching_data ) ||
																void 0 ===
																	_nLocal3
																	? void 0
																	: _nLocal3.label }
															</Controls.TextWP>
															{ ( e === null ||
															e === void 0 ||
															null ===
																( _rLocal3 =
																	e.matching_data ) ||
															void 0 === _rLocal3
																? void 0
																: _rLocal3.image_url ) && (
																<React.Fragment>
																	<img
																		src={
																			e ===
																				null ||
																			e ===
																				void 0 ||
																			null ===
																				( a =
																					e.matching_data ) ||
																			void 0 ===
																				a
																				? void 0
																				: a.image_url
																		}
																		alt={
																			e ===
																				null ||
																			e ===
																				void 0 ||
																			null ===
																				( o =
																					e.matching_data ) ||
																			void 0 ===
																				o
																				? void 0
																				: o.label
																		}
																		style={ {
																			width: '30px',
																			height: '30px',
																			objectFit:
																				'cover',
																		} }
																	/>
																</React.Fragment>
															) }
														</Controls.FlexWP>
													</Controls.CardWP>
												</Controls.FlexWP>
											</Controls.CardWP>
										);
									} ) }
						</Controls.CardWP>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
