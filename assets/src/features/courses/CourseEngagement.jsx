/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseEngagement( readRuntime ) {
	return function CourseEngagement() {
		const {
			CH,
			I: Controls,
			Kt,
			PH,
			React,
			T: StoreModule,
			b: I18n,
			y: WordPressData,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getCourse();
			}, [] ),
			n = function ( _nLocal ) {
				return function ( r ) {
					e.setCourse(
						CH(
							CH( {}, t ),
							{},
							PH( {}, _nLocal, r ? 'yes' : 'no' )
						)
					);
				};
			};
		return (
			<React.Fragment>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP
						padding={ 0 }
						marginTop={ 6 }
						marginBottom={ 6 }
					>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Controls.FlexWP
								align={ 'flex-start' }
								justify={ 'space-between' }
								gap={ 5 }
								direction={ 'column' }
							>
								<Controls.FlexItemWP fullWidth={ ! 0 }>
									<Kt
										title={ ( 0, I18n.__ )(
											'Disable leaderboard',
											'ohmylms'
										) }
										isChecked={
											'yes' ===
											( t === null || t === void 0
												? void 0
												: t.leaderboard_disabled )
										}
										onChange={ n( 'leaderboard_disabled' ) }
										spacerPadding={ 0 }
										description={ ( 0, I18n.__ )(
											'Disable leaderboard for this course. This will hide the leaderboard from students.',
											'ohmylms'
										) }
									/>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP
						padding={ 0 }
						marginTop={ 6 }
						marginBottom={ 6 }
					>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Controls.FlexWP
								align={ 'flex-start' }
								justify={ 'space-between' }
								gap={ 5 }
								direction={ 'column' }
							>
								<Controls.FlexItemWP fullWidth={ ! 0 }>
									<Kt
										title={ ( 0, I18n.__ )(
											'Disable Bonus Point',
											'ohmylms'
										) }
										isChecked={
											'yes' ===
											( t === null || t === void 0
												? void 0
												: t.point_disabled )
										}
										onChange={ n( 'point_disabled' ) }
										spacerPadding={ 0 }
										description={ ( 0, I18n.__ )(
											'Bonus points motivate students by rewarding them for course activities. Disable this if you don’t want to use bonus points in this course.',
											'ohmylms'
										) }
									/>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
				<Controls.CardWP isBorderless={ ! 0 }>
					<Controls.SpacerWP
						padding={ 0 }
						marginTop={ 6 }
						marginBottom={ 6 }
					>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Controls.FlexWP
								align={ 'flex-start' }
								justify={ 'space-between' }
								gap={ 5 }
								direction={ 'column' }
							>
								<Controls.FlexItemWP fullWidth={ ! 0 }>
									<Kt
										title={ ( 0, I18n.__ )(
											'Disable Reward Point',
											'ohmylms'
										) }
										isChecked={
											'yes' ===
											( t === null || t === void 0
												? void 0
												: t.reward_disabled )
										}
										onChange={ n( 'reward_disabled' ) }
										spacerPadding={ 0 }
										description={ ( 0, I18n.__ )(
											'Reward points motivate students by rewarding them for course activities. Disable this if you don’t want to use reward points in this course.',
											'ohmylms'
										) }
									/>
								</Controls.FlexItemWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
