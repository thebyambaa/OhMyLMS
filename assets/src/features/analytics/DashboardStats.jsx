/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Tooltip } from '../../shared/tooltip';
import { InfoIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createDashboardStats( readRuntime ) {
	return function DashboardStats( props ) {
		const { React, b: I18n, f: Router } = readRuntime();
		var t;
		var n;
		var r;
		var a;
		var o;
		var i;
		var l = props.data;
		var c = props.dataLoading;
		var u = ( 0, Router.Zp )();
		return (
			<React.Fragment>
				<Controls.SpacerWP marginBottom={ 0 } marginTop={ 4 }>
					<Controls.FlexWP gap={ 4 } align={ 'stretch' }>
						<Controls.FlexBlockWP className={ 'card-courses-sold' }>
							<Controls.CardWP
								style={ {
									height: '100%',
								} }
								isBorderless={ ! 0 }
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 4 }
								>
									{ c ? (
										<Controls.SkeletonWP
											rows={ 3 }
											active={ ! 0 }
										/>
									) : (
										<React.Fragment>
											<Controls.HeadingWP
												level={ 3 }
												size={ '16px' }
											>
												<Controls.FlexWP
													justify={ 'flex-start' }
													align={ 'center' }
													gap={ 3 }
												>
													{ ( 0, I18n.__ )(
														'Courses Sold ',
														'ohmylms'
													) }
													<Controls.TextWP
														as={ 'em' }
														variant={ 'muted' }
														size={ '12' }
													>
														{ ( 0, I18n.__ )(
															'Last 30 days',
															'ohmylms'
														) }
													</Controls.TextWP>
												</Controls.FlexWP>
											</Controls.HeadingWP>
											<Controls.SpacerWP marginY={ 4 }>
												<Controls.FlexWP
													align={ 'center' }
													gap={ 2 }
													justify={ 'flex-start' }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Total Sales',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Tooltip
														text={
															'Total courses sold in the past 30 days'
														}
														className={
															'ohmylms-tooltip'
														}
														placement={ 'top' }
													>
														<React.Fragment>
															<InfoIcon />
														</React.Fragment>
													</Tooltip>
													<Controls.FlexItemWP>
														<Controls.BadgeWP
															variant={
																0 <=
																( l === null ||
																l === void 0
																	? void 0
																	: l.sales_growth_rate )
																	? 'success'
																	: 'danger'
															}
															isBorderLess={ ! 0 }
														>
															{ Number(
																l === null ||
																	l === void 0
																	? void 0
																	: l.sales_growth_rate
															) !== 0 && (
																<svg
																	style={ {
																		transform:
																			'rotate('.concat(
																				0 <
																					( l ===
																						null ||
																					l ===
																						void 0
																						? void 0
																						: l.sales_growth_rate )
																					? '0deg'
																					: '180deg',
																				')'
																			),
																	} }
																	width={
																		'12'
																	}
																	height={
																		'14'
																	}
																	fill={
																		'none'
																	}
																	viewBox={
																		'0 0 12 14'
																	}
																	xmlns={
																		'http://www.w3.org/2000/svg'
																	}
																>
																	<path
																		stroke={
																			'currentColor'
																		}
																		strokeLinecap={
																			'round'
																		}
																		strokeLinejoin={
																			'round'
																		}
																		strokeWidth={
																			'2'
																		}
																		d={
																			'M1 6l5-5 5 5'
																		}
																	/>
																	<path
																		stroke={
																			'currentColor'
																		}
																		strokeLinecap={
																			'round'
																		}
																		strokeWidth={
																			'2'
																		}
																		d={
																			'M6 13V1'
																		}
																	/>
																</svg>
															) }
															<span>
																{ Math.abs(
																	l ===
																		null ||
																		l ===
																			void 0
																		? void 0
																		: l.sales_growth_rate
																) }
																{ '%' }
															</span>
														</Controls.BadgeWP>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.SpacerWP>
											<span
												style={ {
													fontSize: '48px',
													fontWeight: '500',
													display: 'block',
													lineHeight: 1,
												} }
												className={
													'ohmylms-card-value'
												}
											>
												{ l === null || l === void 0
													? void 0
													: l.course_sold }
											</span>
											<Controls.SpacerWP
												marginBottom={ 4 }
											/>
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ function () {
													u( '/orders' );
												} }
											>
												{ ( 0, I18n.__ )(
													'Go to all orders',
													'ohmylms'
												) }
											</Controls.ButtonWP>
										</React.Fragment>
									) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexBlockWP>
						<Controls.FlexBlockWP className={ 'card-students' }>
							<Controls.CardWP
								style={ {
									height: '100%',
								} }
								isBorderless={ ! 0 }
							>
								<Controls.SpacerWP
									marginBottom={ 0 }
									padding={ 4 }
								>
									{ c ? (
										<Controls.SkeletonWP
											rows={ 3 }
											active={ ! 0 }
										/>
									) : (
										<React.Fragment>
											<Controls.HeadingWP
												level={ 3 }
												size={ '16px' }
											>
												<Controls.FlexWP
													justify={ 'flex-start' }
													align={ 'center' }
													gap={ 3 }
												>
													{ ( 0, I18n.__ )(
														'Students',
														'ohmylms'
													) }
													<Controls.TextWP
														as={ 'em' }
														variant={ 'muted' }
														size={ '12' }
													>
														{ ( 0, I18n.__ )(
															'Last 30 days',
															'ohmylms'
														) }
													</Controls.TextWP>
												</Controls.FlexWP>
											</Controls.HeadingWP>
											<Controls.SpacerWP marginY={ 4 }>
												<Controls.FlexWP
													align={ 'center' }
													gap={ 2 }
													justify={ 'flex-start' }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Enrollees',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Tooltip
														text={
															'New Students in the past 30 days'
														}
														className={
															'ohmylms-tooltip'
														}
														placement={ 'top' }
													>
														<React.Fragment>
															<InfoIcon />
														</React.Fragment>
													</Tooltip>
													<Controls.FlexItemWP>
														<Controls.BadgeWP
															variant={
																0 <=
																( l === null ||
																l === void 0
																	? void 0
																	: l.enrollment_growth_rate )
																	? 'success'
																	: 'danger'
															}
															isBorderLess={ ! 0 }
														>
															{ Number(
																l === null ||
																	l === void 0
																	? void 0
																	: l.enrollment_growth_rate
															) !== 0 && (
																<svg
																	style={ {
																		transform:
																			'rotate('.concat(
																				0 <
																					( l ===
																						null ||
																					l ===
																						void 0
																						? void 0
																						: l.enrollment_growth_rate )
																					? '0deg'
																					: '180deg',
																				')'
																			),
																	} }
																	width={
																		'12'
																	}
																	height={
																		'14'
																	}
																	fill={
																		'none'
																	}
																	viewBox={
																		'0 0 12 14'
																	}
																	xmlns={
																		'http://www.w3.org/2000/svg'
																	}
																>
																	<path
																		stroke={
																			'currentColor'
																		}
																		strokeLinecap={
																			'round'
																		}
																		strokeLinejoin={
																			'round'
																		}
																		strokeWidth={
																			'2'
																		}
																		d={
																			'M1 6l5-5 5 5'
																		}
																	/>
																	<path
																		stroke={
																			'currentColor'
																		}
																		strokeLinecap={
																			'round'
																		}
																		strokeWidth={
																			'2'
																		}
																		d={
																			'M6 13V1'
																		}
																	/>
																</svg>
															) }
															<span>
																{ Math.abs(
																	l ===
																		null ||
																		l ===
																			void 0
																		? void 0
																		: l.enrollment_growth_rate
																) }
																{ '%' }
															</span>
														</Controls.BadgeWP>
													</Controls.FlexItemWP>
												</Controls.FlexWP>
											</Controls.SpacerWP>
											<span
												style={ {
													fontSize: '48px',
													fontWeight: '500',
													display: 'block',
													lineHeight: 1,
												} }
												className={
													'ohmylms-card-value'
												}
											>
												{ l === null || l === void 0
													? void 0
													: l.total_enrollments }
											</span>
											<Controls.SpacerWP
												marginBottom={ 4 }
											/>
											<Controls.ButtonWP
												variant={ 'secondary' }
												onClick={ function () {
													u( '/accounthub' );
												} }
											>
												{ ( 0, I18n.__ )(
													'Go to all students',
													'ohmylms'
												) }
											</Controls.ButtonWP>
										</React.Fragment>
									) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexBlockWP>
						<Controls.FlexBlockWP>
							{ window.ohmylms_params.is_communities_enabled ? (
								<Controls.CardWP
									style={ {
										height: '100%',
									} }
									isBorderless={ ! 0 }
								>
									<Controls.SpacerWP
										marginBottom={ 0 }
										padding={ 4 }
									>
										<Controls.FlexWP
											align={ 'center' }
											gap={ 2 }
										>
											<Controls.HeadingWP
												level={ 3 }
												size={ '18px' }
											>
												{ ( 0, I18n.__ )(
													'Communities',
													'ohmylms'
												) }
											</Controls.HeadingWP>
										</Controls.FlexWP>
										<Controls.SpacerWP marginBottom={ 4 } />
										<Controls.FlexWP
											justify={ 'space-between' }
											align={ 'flex-end' }
											marginBottom={ 4 }
											marginTop={ 4 }
											gap={ 26 }
										>
											<Controls.FlexBlockWP>
												<Controls.TextWP
													as={ 'span' }
													size={ '32' }
													style={ {
														fontWeight: 600,
													} }
												>
													{ null !==
														( t =
															l === null ||
															l === void 0 ||
															null ===
																( n =
																	l.communities ) ||
															void 0 === n
																? void 0
																: n.total ) &&
													void 0 !== t
														? t
														: 0 }
												</Controls.TextWP>
												<Controls.TextWP
													as={ 'div' }
													variant={ 'muted' }
													size={ '14' }
												>
													{ ( l === null ||
													l === void 0 ||
													null ===
														( r = l.communities ) ||
													void 0 === r
														? void 0
														: r.total ) <= '1'
														? ( 0, I18n.__ )(
																'Space',
																'ohmylms'
															)
														: ( 0, I18n.__ )(
																'Spaces',
																'ohmylms'
															) }
												</Controls.TextWP>
											</Controls.FlexBlockWP>
											<Controls.FlexBlockWP>
												<Controls.TextWP
													as={ 'span' }
													size={ '32' }
													style={ {
														fontWeight: 600,
													} }
												>
													{ null !==
														( a =
															l === null ||
															l === void 0 ||
															null ===
																( o =
																	l.communities ) ||
															void 0 === o
																? void 0
																: o.members ) &&
													void 0 !== a
														? a
														: 0 }
												</Controls.TextWP>
												<Controls.TextWP
													as={ 'div' }
													variant={ 'muted' }
													size={ '14' }
												>
													{
														( l === null ||
															l === void 0 ||
															null ===
																( i =
																	l.communities ) ||
															void 0 === i ||
															i.members,
														( 0, I18n.__ )(
															'Member',
															'ohmylms'
														) )
													}
												</Controls.TextWP>
											</Controls.FlexBlockWP>
										</Controls.FlexWP>
										<Controls.SpacerWP marginBottom={ 8 } />
										<Controls.ButtonWP
											variant={ 'secondary' }
											onClick={ function () {
												var e;
												return window.open(
													null ===
														( e =
															window.ohmylms_params ) ||
														void 0 === e ||
														null ===
															( e =
																e.community ) ||
														void 0 === e
														? void 0
														: e.communityPageUrl,
													'_blank'
												);
											} }
										>
											{ ( 0, I18n.__ )(
												'Manage Communities',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							) : (
								<Controls.CardWP
									style={ {
										height: '100%',
									} }
									isBorderless={ ! 0 }
								>
									<Controls.SpacerWP
										marginBottom={ 0 }
										padding={ 4 }
									>
										<Controls.HeadingWP
											level={ 3 }
											size={ '16px' }
										>
											{ ( 0, I18n.__ )(
												"What's New in LMS",
												'ohmylms'
											) }
										</Controls.HeadingWP>
										<Controls.SpacerWP marginBottom={ 0 }>
											<p>
												{ ( 0, I18n.__ )(
													'Manual Student Enrollment — Admins can now enroll students directly from the Course Students tab with automatic email notifications.',
													'ohmylms'
												) }
											</p>
											<p>
												{ ( 0, I18n.__ )(
													'Assignment & Quiz Submission Notifications — Admins and instructors receive email alerts when students submit work for review.',
													'ohmylms'
												) }
											</p>
											<p>
												{ ( 0, I18n.__ )(
													'SCORM Support is now available in the free version — import SCORM courses without upgrading to Pro.',
													'ohmylms'
												) }
											</p>
										</Controls.SpacerWP>
									</Controls.SpacerWP>
								</Controls.CardWP>
							) }
						</Controls.FlexBlockWP>
					</Controls.FlexWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
