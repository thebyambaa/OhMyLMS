/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Switcher } from '../../shared/spinner';
export function createCourseCapacity( readRuntime ) {
	return function CourseCapacity( props ) {
		const { I: Controls, React, b: I18n, pz } = readRuntime();
		const capacity = props.capacity,
			handleCapacityChange = props.handleCapacityChange,
			capacityEnabled = props.capacityEnabled,
			handleCapacityEnabled = props.handleCapacityEnabled;
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
							{ ( 0, I18n.__ )( 'Capacity', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Set up a limit of students who can enroll in this course.',
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
							direction={ 'column' }
							gap={ 5 }
							align={ 'flex-end' }
						>
							<Controls.FlexItemWP>
								<Switcher
									checked={ capacityEnabled }
									onChange={ handleCapacityEnabled }
								/>
							</Controls.FlexItemWP>
							{ capacityEnabled && (
								<Controls.FlexItemWP>
									<Controls.InputNumberWP
										value={ capacity }
										min={ 1 }
										max={ 1e4 }
										onChange={ function ( e ) {
											if ( /^\d*$/.test( e ) ) {
												handleCapacityChange(
													Number( e )
												);
											}
										} }
										onKeyDown={ function ( e ) {
											if (
												[
													'e',
													'E',
													'+',
													'-',
													'/',
													'\\',
													'.',
													',',
												].includes( e.key ) ||
												( /[a-zA-Z]/.test( e.key ) &&
													! [
														'Backspace',
														'Tab',
														'ArrowLeft',
														'ArrowRight',
														'Delete',
														'Enter',
													].includes( e.key ) )
											) {
												e.preventDefault();
											}
										} }
										onBlur={ function () {
											if ( capacity < 1 ) {
												handleCapacityChange( 1 );
											}
										} }
									/>
								</Controls.FlexItemWP>
							) }
						</Controls.FlexWP>
						{ capacityEnabled && Number( capacity ) === 0 && (
							<React.Fragment>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.TextWP
									as={ 'p' }
									align={ ( 0, pz.V )() ? 'left' : 'right' }
									color={ '#ffcc00' }
								>
									{ ( 0, I18n.__ )(
										'Capacity is set to 0. No students can enroll in this course.',
										'ohmylms'
									) }
								</Controls.TextWP>
							</React.Fragment>
						) }
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
