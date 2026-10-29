/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createAssignmentGradeForm( readRuntime ) {
	return function AssignmentGradeForm( props ) {
		const { React, W: RichText, b: I18n } = readRuntime();
		var t,
			n,
			r,
			student = props.student,
			additionData = props.additionData,
			setNote = props.setNote,
			setScore = props.setScore,
			note = props.note,
			score = props.score;
		return (
			<Controls.CardWP
				isBorderless={ ! 0 }
				style={ {
					width: '100%',
				} }
			>
				<Controls.SpacerWP padding={ 4 }>
					<Controls.FlexWP
						justify={ 'flex-start' }
						align={ 'center' }
						gap={ 2 }
					>
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
							{ student === null || student === void 0
								? void 0
								: student.display_name }
						</Controls.TextWP>
					</Controls.FlexWP>
					<Controls.SpacerWP />
					<Controls.FlexWP
						justify={ 'flex-start' }
						align={ 'center' }
						gap={ 2 }
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
							} }
						>
							{ decodeHtmlEntities(
								additionData === null || additionData === void 0
									? void 0
									: additionData.course_name
							) }
						</Controls.TextWP>
					</Controls.FlexWP>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
					>
						<Controls.SpacerWP padding={ 2 } marginY={ 4 }>
							<Controls.TextWP size={ '15' }>
								{ ( 0, I18n.__ )( 'Score: ' ) }{ ' ' }
							</Controls.TextWP>
							<Controls.TextWP size={ '15' } variant={ 'muted' }>
								{ ( 0, I18n.__ )( 'Grade out of ' ) }
								{ additionData === null ||
								additionData === void 0
									? void 0
									: additionData.total_marks }
							</Controls.TextWP>
							<Controls.SpacerWP marginBottom={ 1 } />
							<Controls.InputWP
								type={ 'number' }
								value={ score }
								onChange={ function ( e ) {
									if ( ! (
										e >
											Number(
												additionData === null ||
													additionData === void 0
													? void 0
													: additionData.total_marks
											) || 0 > e
									) ) {
										setScore( e );
									}
								} }
								min={ 0 }
								max={
									additionData === null ||
									additionData === void 0
										? void 0
										: additionData.total_marks
								}
								onBlur={ function ( e ) {
									if ( '' === e.target.value ) {
										setScore( 0 );
									}
								} }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.FlexWP
						justify={ 'flex-start' }
						align={ 'flex-start' }
						direction={ 'column' }
						gap={ 2 }
					>
						<Controls.TextWP as={ 'p' } size={ '15' }>
							{ ( 0, I18n.__ )(
								'Did student pass or fail? (optional)',
								'ohmylms'
							) }
						</Controls.TextWP>
						<Controls.FlexWP
							gap={ 2 }
							justify={ 'flex-start' }
							align={ 'center' }
						>
							{ String(
								student === null ||
									student === void 0 ||
									null === ( t = student.submissions[ 0 ] ) ||
									void 0 === t
									? void 0
									: t.status
							) === 'submitted' ? (
								<Controls.BadgeWP
									isBorderLess={ ! 0 }
									variant={ 'warning' }
								>
									{ ( 0, I18n.__ )(
										'Pending Review',
										'ohmylms'
									) }
								</Controls.BadgeWP>
							) : (
								<React.Fragment>
									<Controls.BadgeWP
										isBorderLess={ ! 0 }
										variant={
											'passed' ===
											( student === null ||
											student === void 0 ||
											null ===
												( n =
													student
														.submissions[ 0 ] ) ||
											void 0 === n
												? void 0
												: n.status )
												? 'success'
												: 'secondary'
										}
									>
										{ ( 0, I18n.__ )( 'Pass', 'ohmylms' ) }
										{ student === null || student === void 0
											? void 0
											: student.status }
									</Controls.BadgeWP>
									<Controls.BadgeWP
										isBorderLess={ ! 0 }
										variant={
											'passed' ===
											( student === null ||
											student === void 0 ||
											null ===
												( r =
													student
														.submissions[ 0 ] ) ||
											void 0 === r
												? void 0
												: r.status )
												? 'secondary'
												: 'danger'
										}
									>
										{ ( 0, I18n.__ )( 'Fail', 'ohmylms' ) }
									</Controls.BadgeWP>
								</React.Fragment>
							) }
						</Controls.FlexWP>
					</Controls.FlexWP>
					<Controls.SpacerWP marginTop={ 4 }>
						<Controls.TextWP size={ '15' }>
							{ ( 0, I18n.__ )(
								'Additional comments',
								'ohmylms'
							) }
						</Controls.TextWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<RichText.A
							rows={ 4 }
							value={ note }
							onChange={ function ( e ) {
								return setNote( e );
							} }
							style={ {
								minHeight: '120px',
							} }
						/>
					</Controls.SpacerWP>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
