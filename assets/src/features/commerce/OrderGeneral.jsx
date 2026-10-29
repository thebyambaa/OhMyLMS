/**
 * OrderGeneral component (replaces recovered binding PQ).
 * Displays order general information (assigned student details).
 */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createOrderGeneral( readRuntime ) {
	return function OrderGeneral( {
		student_name: _studentName,
		student_email: _studentEmail,
		student_id: _studentId,
	} ) {
		const { React, b: I18n } = readRuntime();
		return (
			<React.Fragment>
				<Controls.HeadingWP
					level={ 4 }
					size={ 18 }
					weight={ 500 }
					color="#000D25"
				>
					{ I18n.__( 'General', 'ohmylms' ) }
				</Controls.HeadingWP>
				<div>
					<Controls.SpacerWP marginBottom={ 4 } />
					<Controls.FlexWP
						gap={ 3 }
						align="center"
						justify="flex-start"
					>
						<Controls.TextWP
							as="p"
							size={ 14 }
							weight={ 500 }
							color="#000D21"
							style={ {
								width: '100px',
							} }
						>
							{ I18n.__( 'Student:', 'ohmylms' ) + ' ' }
						</Controls.TextWP>
						<Controls.FlexWP
							align="center"
							justify="flex-start"
							gap={ 2 }
							style={ {
								width: 'calc(100% - 112px)',
							} }
						>
							<Controls.FlexItemWP
								style={ {
									width: 'calc(100% - 53px)',
								} }
							>
								<Controls.TextWP
									as="p"
									size={ 14 }
									weight={ 500 }
									color="#000D21"
								>
									{ _studentName &&
									_studentId &&
									_studentEmail
										? `${ _studentName } (#${ _studentId } – ${ _studentEmail })`
										: I18n.__(
												'No student assigned',
												'ohmylms'
											) }
								</Controls.TextWP>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</Controls.FlexWP>
				</div>
			</React.Fragment>
		);
	};
}
