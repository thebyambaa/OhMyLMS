/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createAssignmentSubmission( readRuntime ) {
	return function AssignmentSubmission( props ) {
		const { Ge, I: Controls, React, b: I18n, kn, tK } = readRuntime();
		var t,
			n,
			r,
			submission = props.submission,
			additionData = props.additionData;
		return (
			<Controls.CardWP
				isBorderless={ ! 0 }
				fullWidth={ ! 0 }
				style={ {
					minHeight: '414px',
				} }
			>
				<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
					<Controls.HeadingWP level={ 2 }>
						{ Ge(
							additionData === null || additionData === void 0
								? void 0
								: additionData.assignment_name
						) }
					</Controls.HeadingWP>
					<Controls.DividerWP marginStart={ 4 } marginEnd={ 4 } />
					<Controls.TextWP>
						{ ( submission === null || submission === void 0
							? void 0
							: submission.content ) ||
							( 0, I18n.__ )( 'No content added', 'ohmylms' ) }
					</Controls.TextWP>
					<Controls.DividerWP marginStart={ 4 } marginEnd={ 4 } />
					{ ( submission === null || submission === void 0
						? void 0
						: submission.files ) && (
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							style={ {
								borderRadius: '4px',
							} }
						>
							<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
								<Controls.FlexWP>
									<Controls.FlexWP
										justify={ 'flex-start' }
										gap={ 2 }
										align={ 'center' }
									>
										{ React.createElement( kn, null ) }
										<Controls.FlexWP
											justify={ 'flex-start' }
											gap={ 2 }
											align={ 'center' }
										>
											<Controls.TextWP as={ 'span' }>
												{ ( submission === null ||
												submission === void 0 ||
												null ===
													( t = submission.files ) ||
												void 0 === t
													? void 0
													: t.file_name ) ||
													( 0, I18n.__ )(
														'No file name',
														'ohmylms'
													) }
											</Controls.TextWP>
											<Controls.TextWP
												as={ 'span' }
												variant={ 'muted' }
											>
												{ ( submission === null ||
												submission === void 0 ||
												null ===
													( n = submission.files ) ||
												void 0 === n
													? void 0
													: n.file_size ) || '67 KB' }
											</Controls.TextWP>
										</Controls.FlexWP>
									</Controls.FlexWP>
									<a
										href={
											submission === null ||
											submission === void 0 ||
											null === ( r = submission.files ) ||
											void 0 === r
												? void 0
												: r.url
										}
										download={ ! 0 }
									>
										{ React.createElement( tK, null ) }
									</a>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
					) }
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
