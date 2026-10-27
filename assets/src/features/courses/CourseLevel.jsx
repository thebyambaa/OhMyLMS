/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseLevel( readRuntime ) {
	return function CourseLevel( props ) {
		const { I: Controls, React, b: I18n } = readRuntime();
		var experienceLevel = props.experienceLevel,
			handleExperienceLevelChange = props.handleExperienceLevelChange,
			r = [
				{
					value: 'all',
					label: ( 0, I18n.__ )( 'All levels', 'ohmylms' ),
				},
				{
					value: 'beginner',
					label: ( 0, I18n.__ )( 'Beginner', 'ohmylms' ),
				},
				{
					value: 'experience',
					label: ( 0, I18n.__ )( 'Experience', 'ohmylms' ),
				},
				{
					value: 'expert',
					label: ( 0, I18n.__ )( ' Expert', 'ohmylms' ),
				},
			];
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'flex-start' }
				>
					<Controls.FlexItemWP
						style={ {
							flex: '5',
						} }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )( 'Level', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Select the required level of knowledge and skills expected of the student.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							flex: '3',
						} }
					>
						<Controls.FlexWP justify={ 'flex-end' }>
							<Controls.RadioGroupWP
								isBlock={ ! 0 }
								options={ r }
								value={ experienceLevel }
								onChange={ handleExperienceLevelChange }
								optionType={ 'button' }
								buttonStyle={ 'solid' }
							/>
						</Controls.FlexWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
