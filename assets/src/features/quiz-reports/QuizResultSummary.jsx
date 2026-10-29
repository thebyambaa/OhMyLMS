/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createQuizResultSummary( readRuntime ) {
	return function QuizResultSummary( props ) {
		const { React, b: I18n } = readRuntime();
		const data = props.data;
		return (
			<React.Fragment>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP padding={ 6 } marginBottom={ 0 }>
						<Controls.FlexWP gap={ 2 } justify={ 'flex-start' }>
							<Controls.TextWP
								size={ '15' }
								weight={ '700' }
								style={ {
									width: '72px',
								} }
							>
								{ ( 0, I18n.__ )( 'Name: ', 'ohmylms' ) }
							</Controls.TextWP>
							<Controls.TextWP
								size={ '15' }
								style={ {
									width: 'calc(100% - 80px)',
								} }
							>
								{ data === null || data === void 0
									? void 0
									: data.student_name }
							</Controls.TextWP>
						</Controls.FlexWP>
						<Controls.SpacerWP />
						<Controls.FlexWP
							gap={ 2 }
							justify={ 'flex-start' }
							align={ 'flex-start' }
						>
							<Controls.TextWP
								size={ '15' }
								weight={ '700' }
								style={ {
									width: '72px',
								} }
							>
								{ ( 0, I18n.__ )( 'Course: ', 'ohmylms' ) }
							</Controls.TextWP>
							<Controls.TextWP
								size={ '15' }
								style={ {
									width: 'calc(100% - 80px)',
									wordWrap: 'break-word',
								} }
							>
								{ data === null || data === void 0
									? void 0
									: data.course_name }
							</Controls.TextWP>
						</Controls.FlexWP>
						<Controls.SpacerWP marginBottom={ 4 } />
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
								<Controls.FlexWP
									gap={ 3 }
									justify={ 'space-between' }
								>
									<Controls.HeadingWP
										level={ 4 }
										weight={ '400' }
									>
										<strong>
											{ ( 0, I18n.__ )(
												'Score: ',
												'ohmylms'
											) }
										</strong>
										<span>
											{ data === null || data === void 0
												? void 0
												: data.score }
										</span>
									</Controls.HeadingWP>
									<Controls.DividerWP
										orientation={ 'vertical' }
										style={ {
											color: '#FFFFFF',
											height: '20px',
										} }
									/>
									<Controls.HeadingWP
										level={ 4 }
										weight={ '400' }
									>
										<strong>
											{ ( 0, I18n.__ )(
												'Correct: ',
												'ohmylms'
											) }
										</strong>
										<span>
											{ data === null || data === void 0
												? void 0
												: data.correct }
										</span>
									</Controls.HeadingWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<Controls.SpacerWP marginBottom={ 4 } />
						<div className={ 'ohmylms-report-final-result' }>
							<Controls.TextWP as={ 'p' } size={ 15 }>
								{ ( 0, I18n.__ )(
									'Did student pass or fail? (optional)',
									'ohmylms'
								) }
							</Controls.TextWP>
							<Controls.SpacerWP />
							<Controls.FlexWP gap={ 4 }>
								{ ( () => {
									if (
										'in-review' ===
										( data === null || data === void 0
											? void 0
											: data.status )
									) {
										return (
											<Controls.BadgeWP
												isBorderLess={ ! 0 }
												variant={ 'warning' }
											>
												{ ( 0, I18n.__ )(
													'Pending',
													'ohmylms'
												) }
											</Controls.BadgeWP>
										);
									}
									if (
										data !== null &&
										data !== void 0 &&
										data.isPass
									) {
										return (
											<Controls.BadgeWP
												isBorderLess={ ! 0 }
												variant={ 'success' }
											>
												{ ( 0, I18n.__ )(
													'Pass',
													'ohmylms'
												) }
											</Controls.BadgeWP>
										);
									}
									return (
										<Controls.BadgeWP
											isBorderLess={ ! 0 }
											variant={ 'danger' }
										>
											{ ( 0, I18n.__ )(
												'Fail',
												'ohmylms'
											) }
										</Controls.BadgeWP>
									);
								} )() }
							</Controls.FlexWP>
						</div>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
