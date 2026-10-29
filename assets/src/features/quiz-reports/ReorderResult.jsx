/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createReorderResult( readRuntime ) {
	return function ReorderResult( props ) {
		const {
			R$,
			React,
			b: I18n,
			g: ReactHooks,
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
						return R$(
							data === null || data === void 0
								? void 0
								: data.given_answer,
							data === null || data === void 0
								? void 0
								: data.questions
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
																	e === void 0
																		? void 0
																		: e.image_url
																}
																alt={
																	e ===
																		null ||
																	e === void 0
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
										);
									} ) }
						</Controls.CardWP>
					</React.Fragment>
				) : (
					<React.Fragment>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							padding={ '16px' }
							style={ {
								background: '#FFEDEE',
							} }
						>
							{ data === null ||
							data === void 0 ||
							null === ( t = data.given_answer ) ||
							void 0 === t
								? void 0
								: t.map( function ( e, _tLocal3 ) {
										var _nLocal,
											_rLocal =
												data === null ||
												data === void 0 ||
												null ===
													( _nLocal =
														data.questions ) ||
												void 0 === _nLocal
													? void 0
													: _nLocal.find(
															function (
																_tLocal4
															) {
																return (
																	String(
																		_tLocal4.id
																	) ===
																	String( e )
																);
															}
														);
										return (
											<Controls.CardWP
												key={ e }
												isBorderless={ ! 0 }
												padding={ '24px' }
												margin={
													Number( _tLocal3 ) === 0
														? '0'
														: '20px 0 0'
												}
											>
												<Controls.FlexWP>
													<Controls.TextWP>
														{ _rLocal === null ||
														_rLocal === void 0
															? void 0
															: _rLocal.answer }
													</Controls.TextWP>
													{ ( _rLocal === null ||
													_rLocal === void 0
														? void 0
														: _rLocal.image_url ) && (
														<React.Fragment>
															<img
																src={
																	_rLocal ===
																		null ||
																	_rLocal ===
																		void 0
																		? void 0
																		: _rLocal.image_url
																}
																alt={
																	_rLocal ===
																		null ||
																	_rLocal ===
																		void 0
																		? void 0
																		: _rLocal.answer
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
										);
									} ) }
						</Controls.CardWP>
						<Controls.SpacerWP margin={ 2 }>
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
																	e === void 0
																		? void 0
																		: e.image_url
																}
																alt={
																	e ===
																		null ||
																	e === void 0
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
										);
									} ) }
						</Controls.CardWP>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
