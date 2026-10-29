/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { MathAnswerResult } from './MathAnswerResult';
import * as Controls from '../../shared/controls';
export function createQuizQuestionResults( readRuntime ) {
	return function QuizQuestionResults( props ) {
		const {
			C$: ReorderResult,
			E$: TextAnswerResult,
			M$: MatchingResult,
			React,
			h$: MultipleChoiceResult,
			p$: QuizQuestionHeader,
			v$: SingleChoiceResult,
		} = readRuntime();
		const data = props.data,
			fetchData = props.fetchData,
			setData = props.setData;
		return (
			<React.Fragment>
				<Controls.FlexWP
					direction={ 'column' }
					gap={ 5 }
					className={ 'ohmylms-report-content' }
				>
					{ data.map( function ( e, t ) {
						var a,
							o =
								e === null ||
								e === void 0 ||
								null === ( a = e.settings ) ||
								void 0 === a
									? void 0
									: a.type;
						return 'single-choice' === o || 'true-false' === o ? (
							<Controls.CardWP key={ t } isBorderless={ ! 0 }>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 5 }
								>
									<SingleChoiceResult
										index={ t }
										data={ e }
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
						) : (
							( () => {
								if ( 'multiple-choice' === o ) {
									return (
										<Controls.CardWP
											key={ t }
											isBorderless={ ! 0 }
										>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 5 }
											>
												<MultipleChoiceResult
													index={ t }
													data={ e }
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									);
								}
								if (
									'short-text' === o ||
									'long-text' === o ||
									'statement' === o ||
									'fill-in-the-blank' === o
								) {
									return (
										<Controls.CardWP
											key={ t }
											isBorderless={ ! 0 }
										>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 5 }
											>
												<TextAnswerResult
													setData={ setData }
													index={ t }
													data={ e }
													type={ o }
													fetchData={ fetchData }
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									);
								}
								return ( () => {
									if ( 'reorder' === o ) {
										return (
											<Controls.CardWP
												key={ t }
												isBorderless={ ! 0 }
											>
												<Controls.SpacerWP
													marginBottom={ 0 }
													padding={ 5 }
												>
													<ReorderResult
														index={ t }
														data={ e }
														type={ o }
													/>
												</Controls.SpacerWP>
											</Controls.CardWP>
										);
									}
									if ( 'matching' === o ) {
										return (
											<Controls.CardWP
												key={ t }
												isBorderless={ ! 0 }
											>
												<Controls.SpacerWP
													marginBottom={ 0 }
													padding={ 5 }
												>
													<MatchingResult
														index={ t }
														data={ e }
														type={ o }
													/>
												</Controls.SpacerWP>
											</Controls.CardWP>
										);
									}
									return (
										// Numerical, structured and extension types (previously not shown at all).
										<Controls.CardWP
											key={ t }
											isBorderless={ ! 0 }
										>
											<Controls.SpacerWP
												marginBottom={ 0 }
												padding={ 5 }
											>
												<MathAnswerResult
													index={ t }
													data={ e }
													setData={ setData }
													fetchData={ fetchData }
													Header={
														QuizQuestionHeader
													}
													Controls={ Controls }
												/>
											</Controls.SpacerWP>
										</Controls.CardWP>
									);
								} )();
							} )()
						);
					} ) }
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
