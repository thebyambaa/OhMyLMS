/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createCourseToolbar( readRuntime ) {
	return function CourseToolbar( props ) {
		const {
			He,
			IW,
			JW,
			Nr,
			React,
			Sc,
			T: StoreModule,
			UW,
			b: I18n,
			f: Router,
			g: ReactHooks,
			q,
			rz,
			tz,
			y: WordPressData,
		} = readRuntime();
		var activeStep = props.activeStep;
		var r = ( props.setActiveStep, props.courseDescription );
		var setLocalCourse = props.setLocalCourse;
		var loading = props.loading;
		var handleAutomation = props.handleAutomation;
		var handleIntegration = props.handleIntegration;
		var courseId = props.courseId;
		var courseName = props.courseName;
		var onSave = props.onSave;
		const nativeNavigate = ( 0, Router.Zp )();
		var d = props.onNavigate || nativeNavigate;
		var subStep = ( 0, Router.g )().subStep;
		var p = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var v = false;
		var _ = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getCourse();
		}, [] );
		var w = rz( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var R = rz( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var x = R[ 0 ];
		var C = R[ 1 ];
		var P = rz( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var O = P[ 0 ];
		var k = P[ 1 ];
		var j = JW();
		var steps = j.steps.filter( ( step ) => step.id !== 'preview' );
		var totalSteps = steps.length;
		var F = function ( e ) {
			var _tLocal = '/course-edit/'.concat( courseId, '/' ).concat( e );
			if ( 'settings' === e ) {
				var _rLocal =
					'settings' === activeStep && subStep ? subStep : 'basics';
				d( ''.concat( _tLocal, '/' ).concat( _rLocal ) );
			} else {
				d( 'funnel' === e ? ''.concat( _tLocal ) : _tLocal );
			}
		};
		var N = function () {
			var e = steps.findIndex( function ( _eLocal ) {
					return activeStep === _eLocal.id;
				} ),
				_tLocal2 = steps[ e + 1 ],
				_rLocal2 =
					_tLocal2 === null || _tLocal2 === void 0
						? void 0
						: _tLocal2.id;
			F( _rLocal2 );
		};
		var Buttons = function () {
			var e = steps.findIndex( function ( _eLocal2 ) {
					return activeStep === _eLocal2.id;
				} ),
				_tLocal3 = steps[ e - 1 ],
				_rLocal3 =
					_tLocal3 === null || _tLocal3 === void 0
						? void 0
						: _tLocal3.id;
			F( _rLocal3 );
		};
		var RichText = [
			{
				title:
					'future' ===
					( _ === null || _ === void 0 ? void 0 : _.status )
						? ( 0, I18n.__ )( 'Scheduled', 'ohmylms' )
						: ( 0, I18n.__ )( 'Schedule', 'ohmylms' ),
				key: '1',
				onClick() {
					C( ! 0 );
				},
			},
		];
		var Notifications = async function ( e, _tLocal4 ) {
			if ( await onSave( e, _tLocal4 ) ) {
				if ( 'preview' === activeStep ) {
					k( true );
				}
			}
		};
		return (
			<React.Fragment>
				<Controls.CardWP
					padding={ '10px 10px 10px 20px' }
					className={ 'ohmylms-top-navigation '
						.concat(
							v ? ' ai-course' : '',
							' ohmylms-active-step-'
						)
						.concat( activeStep ) }
				>
					<Controls.FlexWP
						align={ 'center' }
						justify={ 'space-between' }
						className={ 'ohmylms-top-navigation-inner ohmylms-steps-'.concat(
							totalSteps
						) }
					>
						<Controls.FlexItemWP
							className={ 'ohmylms-back-button-wrapper' }
						>
							<Controls.TooltipWP
								title={ ( 0, I18n.__ )(
									'Exit the builder',
									'ohmylms'
								) }
							>
								<Nr
									onClick={ function () {
										d( '/courses' );
									} }
								/>
							</Controls.TooltipWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP
							className={ 'ohmylms-course-single-steps-item' }
						>
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'center' }
								gap={ 10 }
								className={
									'ohmylms-course-single-steps-container'
								}
							>
								{ steps.map( function ( e, _tLocal5 ) {
									return (
										<div
											key={ e.id }
											className={ 'ohmylms-course-single-steps-wrapper '
												.concat(
													activeStep === e.id
														? 'active'
														: '',
													' '
												)
												.concat(
													steps.findIndex(
														function ( _eLocal3 ) {
															return (
																_eLocal3.id ===
																activeStep
															);
														}
													) > _tLocal5
														? 'completed'
														: '',
													' '
												)
												.concat(
													O ? 'completed' : ''
												) }
										>
											<Controls.ButtonWP
												variant={ 'default' }
												onClick={ function () {
													return (
														( _tLocal6 = e.id ),
														void (
															v ||
															( ( _ === null ||
															_ === void 0
																? void 0
																: _.description ) !==
																r &&
																( p.setCourse(
																	tz(
																		tz(
																			{},
																			_
																		),
																		{},
																		{
																			description:
																				r,
																		}
																	)
																),
																setLocalCourse(
																	r
																) ),
															F( _tLocal6 ) )
														)
													 );
													var _tLocal6;
												} }
												disabled={ v || loading }
												className={ 'ohmylms-course-single-steps '
													.concat(
														activeStep === e.id
															? 'active'
															: '',
														' '
													)
													.concat(
														steps.findIndex(
															function (
																_eLocal4
															) {
																return (
																	_eLocal4.id ===
																	activeStep
																);
															}
														) > _tLocal5
															? 'completed'
															: '',
														' '
													)
													.concat(
														O ? 'completed' : ''
													) }
											>
												<div
													className={
														'ohmylms-course-single-steps-indicator'
													}
												>
													{ steps.findIndex(
														function ( _eLocal5 ) {
															return (
																_eLocal5.id ===
																activeStep
															);
														}
													) > _tLocal5 || O ? (
														<q.Icon icon={ IW.A } />
													) : (
														_tLocal5 + 1
													) }
												</div>
												{ e.title }
											</Controls.ButtonWP>
										</div>
									);
								} ) }
							</Controls.FlexWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP
							className={ 'ohmylms-course-actions-wrapper' }
						>
							<Controls.FlexWP
								align={ 'center' }
								justify={ 'flex-end' }
								gap={ 1 }
							>
								{ props.courseUrl && (
									<React.Fragment>
										<Controls.ButtonWP
											variant={ 'primary' }
											href={ props.courseUrl }
											target="_blank"
											rel="noopener noreferrer"
											disabled={ loading }
										>
											{ ( 0, I18n.__ )(
												'View course',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</React.Fragment>
								) }
								{ 'settings' === activeStep && (
									<Controls.FlexWP
										justify={ 'flex-end' }
										align={ 'stretch' }
										gap={ 5 }
									>
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ Buttons }
										>
											{ ( 0, I18n.__ )(
												'Previous',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'primary' }
											onClick={ () => onSave() }
											disabled={ loading }
										>
											{ ( 0, I18n.__ )(
												'Save course',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								) }
								{ 'community' === activeStep && (
									<Controls.FlexWP
										justify={ 'flex-end' }
										align={ 'stretch' }
										gap={ 5 }
									>
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ Buttons }
										>
											{ ( 0, I18n.__ )(
												'Previous',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'primary' }
											onClick={ N }
										>
											{ ( 0, I18n.__ )(
												'Next',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								) }
								{ 'funnel' === activeStep && (
									<Controls.FlexWP
										justify={ 'flex-end' }
										align={ 'stretch' }
										gap={ 5 }
									>
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ Buttons }
										>
											{ ( 0, I18n.__ )(
												'Previous',
												'ohmylms'
											) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant={ 'primary' }
											onClick={ N }
										>
											{ ( 0, I18n.__ )(
												'Next',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								) }
								{ 'preview' === activeStep && (
									<React.Fragment>
										<Controls.FlexWP
											justify={ 'flex-end' }
											align={ 'stretch' }
											gap={ 5 }
										>
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ Buttons }
											>
												{ ( 0, I18n.__ )(
													'Previous',
													'ohmylms'
												) }
											</Controls.ButtonWP>
											<Controls.DropdownButtonWP
												menuItems={ RichText }
												buttonLabel={ ( 0, I18n.__ )(
													''.concat(
														'draft' ===
															( _ === null ||
															_ === void 0
																? void 0
																: _.status ) ||
															'future' ===
																( _ === null ||
																_ === void 0
																	? void 0
																	: _.status )
															? 'Publish'
															: 'Update'
													),
													'ohmylms'
												) }
												onClick={ function () {
													return Notifications(
														'publish'
													);
												} }
												loading={ loading }
											/>
										</Controls.FlexWP>
									</React.Fragment>
								) }
								<Controls.DropdownMenuWP
									className={
										'ohmylms-more-options-dropdown'
									}
									contentClassName={
										'ohmylms-more-options-dropdown-content'
									}
									icon={ <q.Icon icon={ Sc.A } /> }
									controls={ [
										{
											title: ( 0, I18n.__ )(
												'Integrations',
												'ohmylms'
											),
											onClick() {
												if ( ! v ) {
													if ( handleIntegration ) {
														handleIntegration(
															'course',
															courseId,
															courseName
														);
													}
												}
											},
										},
										{
											title: ( 0, I18n.__ )(
												'Course Automation',
												'ohmylms'
											),
											onClick() {
												var e;
												if ( ! v ) {
													return null !==
														( e = window ) &&
														void 0 !== e &&
														null !==
															( e =
																e.ohmylms_params ) &&
														void 0 !== e &&
														e.is_mailmint_active
														? void handleAutomation(
																'course',
																courseId,
																courseName
															)
														: ( S( ! 0 ),
															p.updateProModalTitle(
																( 0, I18n.__ )(
																	'Missing Mail Mint Plugin!',
																	'ohmylms'
																)
															),
															p.updateProModalContent(
																( 0, I18n.__ )(
																	'Mail Mint is required to enable automation. Please install and activate the plugin.',
																	'ohmylms'
																)
															),
															p.updateProModalButtonText(
																( 0, I18n.__ )(
																	'Install and Activate',
																	'ohmylms'
																)
															),
															void p.updateProModalButtonAction(
																'activate-mail-mint'
															) );
												}
											},
										},
									] }
								/>
							</Controls.FlexWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				</Controls.CardWP>
				{ E && (
					<React.Fragment>
						<He.default isOpen={ E } onClose={ S } />
					</React.Fragment>
				) }
				{ x && (
					<React.Fragment>
						<UW
							isOpen={ x }
							onClose={ function () {
								return C( ! 1 );
							} }
							onSave={ Notifications }
						/>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
