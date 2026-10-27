/**
 * CustomerProfile component (replaces recovered binding CQ).
 * Displays student profile card, avatar, email, and link to report.
 */
import { createElement } from '@wordpress/element';
export function createCustomerProfile( readRuntime ) {
	return function CustomerProfile( {
		student_name: _studentName,
		student_email: _studentEmail,
		student_id: _studentId,
		student_image: _studentImage,
	} ) {
		const { I: Controls, React, b: I18n, g: ReactHooks } = readRuntime();
		const [ isOpen, setIsOpen ] = ReactHooks.useState( true );
		return (
			<React.Fragment>
				<Controls.FlexWP
					gap={ 2 }
					justify="space-between"
					align="center"
				>
					<Controls.HeadingWP
						level={ 4 }
						size={ 18 }
						weight={ 500 }
						color="#000D25"
					>
						{ I18n.__( 'Customer Profile', 'ohmylms' ) }
					</Controls.HeadingWP>
					<Controls.ButtonWP
						size="small"
						onClick={ () => setIsOpen( ! isOpen ) }
					>
						<svg
							style={ {
								transform: isOpen
									? 'rotate(0deg)'
									: 'rotate(180deg)',
							} }
							width="12"
							height="6"
							fill="none"
							viewBox="0 0 12 6"
							xmlns="http://www.w3.org/2000/svg"
						>
							<path
								fill="#000D25"
								d="M11.5 4.4L6 0 .5 4.4l.9 1.2L6 2l4.5 3.6 1-1.2z"
							/>
						</svg>
					</Controls.ButtonWP>
				</Controls.FlexWP>
				{ isOpen && (
					<React.Fragment>
						<Controls.SpacerWP marginTop={ 6 } marginBottom={ 0 } />
						<Controls.FlexWP
							gap={ 2 }
							justify="space-between"
							align="center"
							className="customer-profile-avater"
						>
							<Controls.FlexItemWP
								style={ {
									width: 'calc(100% - 128px)',
								} }
							>
								<Controls.FlexWP
									gap={ 5 }
									align="center"
									justify="flex-start"
								>
									<Controls.AvatarWP
										src={ _studentImage }
										size={ 48 }
										shape="circle"
									/>
									<Controls.FlexItemWP
										style={ {
											width: 'calc(100% - 68px)',
										} }
									>
										<Controls.TextWP
											as="p"
											size={ 14 }
											weight={ 600 }
											color="#000D25"
											style={ {
												wordWrap: 'break-word',
											} }
										>
											{ _studentName }
										</Controls.TextWP>
										<Controls.ButtonWP
											href={ `mailto:${ _studentEmail }` }
											variant="link"
											size={ 12 }
											weight={ 500 }
											color="#7A8B9A"
											style={ {
												textDecoration: 'underline',
											} }
										>
											{ _studentEmail }
										</Controls.ButtonWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP
								style={ {
									textAlign: 'center',
								} }
							>
								<Controls.ButtonWP
									variant="secondary"
									style={ {
										backgroundColor: '#fff',
									} }
									href={ `/wp-admin/admin.php?page=ohmylms#/students/${ _studentId }/report` }
									rel="noopener noreferrer"
								>
									{ I18n.__( 'View profile', 'ohmylms' ) }
								</Controls.ButtonWP>
								<br />
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
