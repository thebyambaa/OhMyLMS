/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Switcher } from '../../shared/spinner';
import * as Controls from '../../shared/controls';
export function createCourseReviews( readRuntime ) {
	return function CourseReviews( props ) {
		const { React, b: I18n } = readRuntime();
		const reviewEnabled = props.reviewEnabled,
			handleReviewEnabled = props.handleReviewEnabled;
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
							{ ( 0, I18n.__ )( 'Review', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Enable reviews for courses to increase authority.',
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
							<Switcher
								checked={ reviewEnabled }
								onChange={ handleReviewEnabled }
							/>
						</Controls.FlexWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
