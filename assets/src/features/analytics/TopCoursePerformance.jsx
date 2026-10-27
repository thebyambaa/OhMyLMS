/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createTopCoursePerformance( readRuntime ) {
	return function TopCoursePerformance( props ) {
		const {
			EG,
			Ge,
			I: Controls,
			PG,
			React,
			SG,
			_,
			b: I18n,
			f: Router,
			jG,
			lf,
		} = readRuntime();
		var t,
			n = props.data,
			r = props.totalCourses,
			a = props.dataLoading,
			o = props.handleAddCourse,
			i = ( 0, Router.Zp )(),
			l =
				( null === ( t = window.ohmylms_params ) || void 0 === t
					? void 0
					: t.plugin_assets ) + 'images',
			c = ''.concat( l, '/dummy-thumbnail-image.svg' ),
			u = {
				background: '#2A85FF',
				borderRadius: '2px',
				width: '10px',
				height: '10px',
				display: 'inline-block',
				marginInlineEnd: '10px',
			};
		return (
			<React.Fragment>
				<Controls.CardWP
					isBorderless={ ! 0 }
					style={ {
						height: '100%',
					} }
				>
					<Controls.SpacerWP
						padding={ 6 }
						margin={ 0 }
						marginBottom={ 0 }
						style={ {
							height: '100%',
						} }
					>
						<Controls.HeadingWP level={ 3 } size={ '16px' }>
							{ ( 0, I18n.__ )(
								'Top Course Performance',
								'ohmylms'
							) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 3 } />
						{ a ? (
							<_.A rows={ 3 } active={ ! 0 } />
						) : (
							<React.Fragment>
								{ n ? (
									<React.Fragment>
										<PG>
											<img
												src={ ( () => {
													if (
														n !== null &&
														n !== void 0 &&
														n.image_src
													) {
														if (
															n === null ||
															n === void 0
														) {
															return void 0;
														}
														return n.image_src;
													}
													return c;
												} )() }
												alt={
													n === null || n === void 0
														? void 0
														: n.title
												}
												style={ {
													width: '100%',
													height: '170px',
													objectFit: 'cover',
												} }
											/>
										</PG>
										<Controls.SpacerWP marginBottom={ 3 } />
										<Controls.TextWP
											as={ 'a' }
											size={ 16 }
											href={
												n === null || n === void 0
													? void 0
													: n.url
											}
											target={ '_blank' }
											weight={ '700' }
										>
											{ Ge(
												n === null || n === void 0
													? void 0
													: n.title
											) || 'Untitled' }
										</Controls.TextWP>
										<Controls.SpacerWP
											paddingTop={ 1 }
											paddingBottom={ 1 }
											marginBottom={ 0 }
										/>
										<EG.A
											align={ 'center' }
											gap={ 4 }
											justify={ 'flex-start' }
										>
											<SG.A>
												<Controls.BadgeWP
													variant={ 'secondary' }
													isBorderLess={ ! 0 }
												>
													{ ( 0, I18n.__ )(
														'Within last 30 days',
														'ohmylms'
													) }
												</Controls.BadgeWP>
											</SG.A>
											<SG.A>
												<EG.A
													justify={ 'flex-start' }
													gap={ 2 }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '12' }
														weight={ '700' }
													>
														{ n === null ||
														n === void 0
															? void 0
															: n.ratings }
													</Controls.TextWP>
													<svg
														width={ '12' }
														height={ '12' }
														fill={ 'none' }
														viewBox={ '0 0 12 12' }
														xmlns={
															'http://www.w3.org/2000/svg'
														}
													>
														<path
															fill={ '#FFC554' }
															d={
																'M4.81.88a1.245 1.245 0 012.38 0l.596 1.782c.173.518.637.87 1.164.88l1.814.039c1.194.025 1.687 1.603.735 2.354l-1.446 1.14c-.42.331-.597.9-.444 1.424l.525 1.806c.346 1.189-.945 2.164-1.925 1.455l-1.49-1.078a1.22 1.22 0 00-1.439 0L3.791 11.76c-.98.71-2.271-.266-1.925-1.455l.525-1.806a1.34 1.34 0 00-.444-1.424L.5 5.935c-.952-.75-.459-2.329.735-2.354l1.814-.039a1.265 1.265 0 001.164-.88L4.81.88z'
															}
														/>
													</svg>
												</EG.A>
											</SG.A>
										</EG.A>
										<Controls.DividerWP
											marginStart={ 4 }
											marginEnd={ 4 }
										/>
										<div
											className={
												'top-course-analytics-stats'
											}
										>
											<EG.A
												align={ 'center' }
												gap={ 3 }
												justify={ 'space-between' }
											>
												<SG.A
													style={ {
														width: 'calc(100% - 14px)',
													} }
												>
													<span style={ u } />
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
														weight={ '700' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Ranking by Course Enrolled',
															'ohmylms'
														) }
													</Controls.TextWP>
												</SG.A>
												<SG.A
													style={ {
														width: '80px',
														textAlign: 'right',
													} }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
													>
														{ '1 of ' }
														{ r }
													</Controls.TextWP>
												</SG.A>
											</EG.A>
											<Controls.SpacerWP
												paddingTop={ 1 }
												paddingBottom={ 1 }
												marginBottom={ 0 }
											/>
											<EG.A
												align={ 'center' }
												gap={ 3 }
												justify={ 'space-between' }
											>
												<SG.A
													style={ {
														width: 'calc(100% - 92px)',
													} }
												>
													<span
														style={ jG(
															jG( {}, u ),
															{},
															{
																background:
																	'#83BF6E',
															}
														) }
													/>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
														weight={ '700' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Total Students',
															'ohmylms'
														) }
													</Controls.TextWP>
												</SG.A>
												<SG.A
													style={ {
														width: '80px',
														textAlign: 'right',
													} }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
													>
														{ ( n === null ||
														n === void 0
															? void 0
															: n.total_students ) ||
															0 }
													</Controls.TextWP>
												</SG.A>
											</EG.A>
											<Controls.SpacerWP
												paddingTop={ 1 }
												paddingBottom={ 1 }
												marginBottom={ 0 }
											/>
											<EG.A
												align={ 'center' }
												gap={ 3 }
												justify={ 'space-between' }
											>
												<SG.A
													style={ {
														width: 'calc(100% - 92px)',
													} }
												>
													<span
														style={ jG(
															jG( {}, u ),
															{},
															{
																background:
																	'#27B0E7',
															}
														) }
													/>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
														weight={ '700' }
														variant={ 'muted' }
													>
														{ ( 0, I18n.__ )(
															'Course Completion',
															'ohmylms'
														) }
													</Controls.TextWP>
												</SG.A>
												<SG.A
													style={ {
														width: '80px',
														textAlign: 'right',
													} }
												>
													<Controls.TextWP
														as={ 'span' }
														size={ '14' }
													>
														{ ( n === null ||
														n === void 0
															? void 0
															: n.total_completed ) ||
															0 }
													</Controls.TextWP>
												</SG.A>
											</EG.A>
										</div>
										<Controls.SpacerWP
											marginBottom={ 0 }
											marginTop={ 5 }
										>
											<Controls.ButtonWP
												variant={ 'primary' }
												title={ ( 0, I18n.__ )(
													'Go to course analytics',
													'ohmylms'
												) }
												onClick={ function () {
													i(
														'/course/'.concat(
															n === null ||
																n === void 0
																? void 0
																: n.id,
															'/report'
														)
													);
												} }
											>
												{ ( 0, I18n.__ )(
													'Go to course analytics',
													'ohmylms'
												) }
											</Controls.ButtonWP>
										</Controls.SpacerWP>
									</React.Fragment>
								) : (
									<Controls.CardWP
										variant={ 'secondary' }
										isBorderless={ ! 0 }
										style={ {
											height: 'calc(100% - 40px)',
										} }
									>
										<Controls.SpacerWP
											marginBottom={ 0 }
											padding={ 7.5 }
											style={ {
												height: '100%',
											} }
										>
											<EG.A
												justify={ 'center' }
												align={ 'center' }
												gap={ 6 }
												direction={ 'column' }
											>
												<div className={ 'icon' }>
													<svg
														width={ '145' }
														height={ '139' }
														fill={ 'none' }
														viewBox={
															'0 0 145 139'
														}
														xmlns={
															'http://www.w3.org/2000/svg'
														}
													>
														<path
															fill={ '#7A8B9A' }
															fillOpacity={
																'.09'
															}
															d={
																'M136.552 105.819c8.605-6.596 13.401-36.3-5.373-66.087C112.406 9.944 91.093-1.93 70.742.252 50.39 2.434 43.763 14.825 34.693 17.18c-9.07 2.354-23.613-1.057-27.986 14.575-4.372 15.631 10.817 27.752 7.97 35.2C11.827 74.4 8.734 68.03 2.56 78.638c-6.175 10.609-2.865 37.568 28.811 51.136 31.677 13.568 68.94 11.226 77.631-.886 8.691-12.112-3.013-17.194 3.515-19.976 6.528-2.783 15.429 3.502 24.034-3.094z'
															}
														/>
														<path
															stroke={ '#000D25' }
															strokeLinecap={
																'round'
															}
															strokeLinejoin={
																'round'
															}
															strokeWidth={
																'1.366'
															}
															d={
																'M20.47 30.456l12.22 1.233m86.981-1.233l-12.22 1.233M33.126 13.418l8.857 5.565m65.032-5.565l-8.857 5.565'
															}
														/>
														<path
															fill={ '#000D25' }
															d={
																'M33.97 52.523l31.848 12.768c3.085 1.237 4.628 1.855 6.248 1.855s3.164-.618 6.249-1.855l31.849-12.767c1.678-.673 2.517-1.01 2.517-1.588a.563.563 0 00-.04-.208c-.184-.461-1.01-.792-2.477-1.38L78.315 36.58c-3.085-1.237-4.63-1.856-6.25-1.856-1.619 0-3.162.618-6.247 1.855L33.969 49.347c-1.467.589-2.293.92-2.477 1.38a.554.554 0 00-.04.209c0 .578.84.915 2.517 1.587zm80.155 27.917L90.281 90c.807.552 2.706 1.859 3.846 2.665 1.425 1.009 2.598 1.15 5.365 0 2.213-.92 10.677-4.248 14.633-5.796V80.44z'
															}
														/>
														<path
															fill={ '#000D25' }
															d={
																'M72.066 67.146L64.74 85.414c-.898 2.158-1.527 3.673-2.227 4.686.597.235 2.07.804 3.184 1.208 1.394.504 3.456-.227 3.949-2.29.492-2.061 2.419-10.083 2.419-10.083v-11.79zm-6.248 48.724c3.085 1.237 4.628 1.855 6.248 1.855v-4.885c0 3.03-3.619 4.084-6.248 3.03z'
															}
														/>
														<path
															fill={ '#000D25' }
															d={
																'M72.066 117.725c1.62 0 3.164-.618 6.249-1.855-2.686 1.077-6.25 0-6.25-3.03v4.885z'
															}
														/>
														<path
															stroke={ '#000D25' }
															strokeLinecap={
																'round'
															}
															strokeWidth={
																'1.214'
															}
															d={
																'M65.818 115.87l-25.235-10.116c-5.122-2.053-7.683-3.08-9.129-5.221-1.446-2.14-1.446-4.904-1.446-10.433v-6.08m35.81 31.85c3.085 1.237 4.628 1.855 6.248 1.855m-6.248-1.855c2.63 1.054 6.248 0 6.248-3.03m0 4.885c1.62 0 3.164-.618 6.249-1.855m-6.25 1.855v-4.885m0 4.885v-4.885m0-45.694c-1.619 0-3.162-.618-6.247-1.855L33.969 52.523c-1.678-.672-2.517-1.009-2.517-1.588 0-.072.013-.141.04-.207m40.574 16.418L64.74 85.414c-1.505 3.62-2.258 5.43-3.848 6.1-1.59.67-3.407-.059-7.04-1.515l-26.07-10.451c-3.888-1.558-5.831-2.337-6.496-4.03-.666-1.694.226-3.592 2.01-7.388l7.866-16.745.329-.657m40.574 16.418c1.62 0 3.164-.618 6.249-1.855l31.849-12.767c1.678-.673 2.517-1.01 2.517-1.588a.563.563 0 00-.04-.208M72.066 67.146l7.326 18.268c1.505 3.62 2.258 5.431 3.848 6.1 1.59.67 3.407-.058 7.04-1.515l23.845-9.559m-42.06-13.294v45.694M31.493 50.728c.184-.461 1.01-.792 2.477-1.38L65.818 36.58c3.085-1.236 4.628-1.855 6.248-1.855s3.164.619 6.249 1.856l31.849 12.767c1.467.588 2.293.919 2.477 1.38M78.315 115.87l25.235-10.116c5.122-2.053 7.683-3.079 9.129-5.22 1.446-2.141 1.446-4.905 1.446-10.434v-9.66m-35.81 35.43c-2.686 1.077-6.25 0-6.25-3.03m42.06-32.4l2.225-.892c3.887-1.558 5.831-2.337 6.496-4.03.666-1.694-.226-3.591-2.009-7.387l-7.867-16.746-.329-.657'
															}
														/>
														<circle
															cx={ '70.072' }
															cy={ '32.303' }
															r={ '16.303' }
															fill={
																'var(--ohmylms-primary-color)'
															}
														/>
														<path
															fill={ '#fff' }
															fillRule={
																'evenodd'
															}
															d={
																'M69.93 19.119c-4.999 0-9.296 3.008-11.178 7.315a.567.567 0 11-1.039-.454c2.056-4.705 6.752-7.995 12.217-7.995a.567.567 0 110 1.134z'
															}
															clipRule={
																'evenodd'
															}
														/>
														<path
															fill={ '#fff' }
															d={
																'M70.918 23.066l2.293 4.86a.982.982 0 00.292.363.926.926 0 00.421.179l5.125.773a.93.93 0 01.47.224.996.996 0 01.288.45c.055.174.062.36.02.54a1.005 1.005 0 01-.253.47l-3.703 3.782a.997.997 0 00-.24.404 1.032 1.032 0 00-.032.474l.876 5.34c.03.182.01.37-.056.541a.987.987 0 01-.322.43.913.913 0 01-1 .075l-4.583-2.528a.916.916 0 00-.883 0l-4.584 2.527a.913.913 0 01-.998-.076.987.987 0 01-.32-.429 1.034 1.034 0 01-.057-.54l.874-5.34a1.033 1.033 0 00-.032-.474.998.998 0 00-.24-.404l-3.703-3.782a1.005 1.005 0 01-.254-.47 1.036 1.036 0 01.02-.54.995.995 0 01.288-.45.93.93 0 01.47-.224l5.126-.778a.928.928 0 00.42-.18.983.983 0 00.293-.363l2.293-4.859c.08-.163.201-.3.35-.395a.918.918 0 01.992.003c.15.096.27.234.349.397z'
															}
														/>
														<g
															filter={
																'url(#filter0_f_2323_915)'
															}
														>
															<ellipse
																cx={ '69.969' }
																cy={ '50.356' }
																fill={
																	'#6B6B6B'
																}
																fillOpacity={
																	'.39'
																}
																rx={ '14.783' }
																ry={ '3.168' }
															/>
														</g>
														<defs>
															<filter
																id={
																	'filter0_f_2323_915'
																}
																width={
																	'63.054'
																}
																height={
																	'39.823'
																}
																x={ '38.442' }
																y={ '30.444' }
																colorInterpolationFilters={
																	'sRGB'
																}
																filterUnits={
																	'userSpaceOnUse'
																}
															>
																<feFlood
																	floodOpacity={
																		'0'
																	}
																	result={
																		'BackgroundImageFix'
																	}
																/>
																<feBlend
																	in={
																		'SourceGraphic'
																	}
																	in2={
																		'BackgroundImageFix'
																	}
																	result={
																		'shape'
																	}
																/>
																<feGaussianBlur
																	result={
																		'effect1_foregroundBlur_2323_915'
																	}
																	stdDeviation={
																		'8.372'
																	}
																/>
															</filter>
														</defs>
													</svg>
												</div>
												<div
													style={ {
														textAlign: 'center',
													} }
												>
													<Controls.HeadingWP
														level={ 6 }
														size={ '14px' }
														align={ 'center' }
													>
														{ ( 0, I18n.__ )(
															'Your top course could be just one click away!',
															'ohmylms'
														) }
													</Controls.HeadingWP>
													<Controls.SpacerWP
														marginBottom={ 2 }
													/>
													<Controls.TextWP
														variant={ 'muted' }
														size={ '12px' }
														align={ 'center' }
													>
														{ ( 0, I18n.__ )(
															'Create a course that inspires, engages, and climbs to the top — we’ll spotlight your success right here.',
															'ohmylms'
														) }
													</Controls.TextWP>
													{ Number( r ) === 0 && (
														<React.Fragment>
															<Controls.SpacerWP
																marginBottom={
																	0
																}
																marginTop={ 6 }
															/>
															<Controls.FlexBlockWP>
																{ React.createElement(
																	lf,
																	{
																		label: ( 0,
																		I18n.__ )(
																			'Add Course',
																			'ohmylms'
																		),
																		onClick:
																			o,
																	}
																) }
															</Controls.FlexBlockWP>
														</React.Fragment>
													) }
												</div>
											</EG.A>
										</Controls.SpacerWP>
									</Controls.CardWP>
								) }
							</React.Fragment>
						) }
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
