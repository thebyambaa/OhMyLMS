/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createReportStudentCell( readRuntime ) {
	return function ReportStudentCell( props ) {
		const {
			Ge,
			I: Controls,
			JU,
			PG,
			React,
			v,
			vG: AnalyticsLinkIcon,
		} = readRuntime();
		var t = true,
			n = props.studentData,
			r = props.isHover,
			a = void 0 !== r && r;
		return (
			<React.Fragment>
				<Controls.FlexWP
					gap={ 5 }
					justify={ 'flex-start' }
					align={ 'flex-start' }
					style={ {
						width: '250px',
						height: '50px',
					} }
				>
					<PG>
						{ n !== null && n !== void 0 && n.profile_image ? (
							<Controls.AvatarWP
								shape={ 'circle' }
								alt={
									n === null || n === void 0 ? void 0 : n.name
								}
								src={
									n === null || n === void 0
										? void 0
										: n.profile_image
								}
								style={ {
									width: '40px',
									height: '40px',
									borderRadius: '50%',
								} }
								size={ 40 }
								wrapperStyle={ {
									height: '40px',
								} }
							/>
						) : (
							<JU />
						) }
					</PG>
					<div className={ 'ohmylms-student-info' }>
						<Controls.HeadingWP
							level={ 4 }
							className={ 'student-name' }
						>
							{ Ge(
								n === null || n === void 0 ? void 0 : n.name
							) }
						</Controls.HeadingWP>
						<Controls.FlexWP
							gap={ 2 }
							className={ 'student-email-wrapper' }
						>
							{ a ? (
								<React.Fragment>
									<v.Link
										disabled={ ! t }
										to={ '/students/'.concat(
											n === null || n === void 0
												? void 0
												: n.student_id,
											'/report'
										) }
										className={ 'student-analytics-link' }
									>
										<AnalyticsLinkIcon />
									</v.Link>
								</React.Fragment>
							) : (
								<React.Fragment>
									<Controls.TagWP
										className={ 'student-email' }
									>
										{ n === null || n === void 0
											? void 0
											: n.email }
									</Controls.TagWP>
								</React.Fragment>
							) }
						</Controls.FlexWP>
					</div>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
