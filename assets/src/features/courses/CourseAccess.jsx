/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseAccess( readRuntime ) {
	return function CourseAccess( props ) {
		const { I: Controls, React, b: I18n, bz, hz, ls } = readRuntime();
		var access = props.access,
			handleAccessChange = props.handleAccessChange,
			r = [
				{
					label: ( 0, I18n.__ )( 'Public', 'ohmylms' ),
					value: 'public',
					icon: hz,
				},
				{
					label: ( 0, I18n.__ )( 'Password Protected', 'ohmylms' ),
					value: 'password_protected',
					icon: bz,
				},
			];
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'flex-start' }
					className={ 'ohmylms-course-access-section' }
				>
					<Controls.FlexItemWP
						style={ {
							flex: '5',
						} }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )( 'Visibility Status', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Choose who can view the course and enroll in this course.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							flex: '3',
						} }
					>
						<Controls.FlexWP
							justify={ 'flex-end' }
							align={ 'flex-end' }
							direction={ 'column' }
						>
							{ React.createElement( ls, {
								isBlock: ! 0,
								options: r,
								defaultValue: access,
								onChange: handleAccessChange,
							} ) }
						</Controls.FlexWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
