/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { loadDashboard, sortRecentCourses } from './model.mjs';
export function createDashboardOverview( readRuntime ) {
	return function DashboardOverview( props ) {
		const {
			D: Buttons,
			I: Controls,
			MG: TopCoursePerformance,
			React,
			T: StoreModule,
			UH,
			_G: RecentCourses,
			b: I18n,
			f: Router,
			g: ReactHooks,
			kf,
			l,
			mG: EarningsSummaryCards,
			sn,
			wG: DashboardStats,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			c,
			u,
			s,
			d,
			m = props.handleAddCourse,
			p = ( 0, Router.Zp )(),
			v = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDashboardLoader();
			}, [] ),
			_ = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDashboardOverview();
			}, [] ),
			w = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			E = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getDashboardFilter();
			}, [] ),
			S = 'YYYY/MM/DD',
			R = UH(
				_ === null || _ === void 0 ? void 0 : _.currency,
				_ === null || _ === void 0 ? void 0 : _.currency_pos,
				( _ === null ||
				_ === void 0 ||
				null === ( t = _.earning ) ||
				void 0 === t
					? void 0
					: t.total_earning ) || '0'
			),
			x = UH(
				_ === null || _ === void 0 ? void 0 : _.currency,
				_ === null || _ === void 0 ? void 0 : _.currency_pos,
				( _ === null ||
				_ === void 0 ||
				null === ( n = _.earning ) ||
				void 0 === n
					? void 0
					: n.refund ) || '0'
			),
			C = UH(
				_ === null || _ === void 0 ? void 0 : _.currency,
				_ === null || _ === void 0 ? void 0 : _.currency_pos,
				( _ === null ||
				_ === void 0 ||
				null === ( r = _.earning ) ||
				void 0 === r
					? void 0
					: r.net_income ) || '0'
			),
			P = [
				{
					label: ( 0, I18n.__ )( 'Income', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Total income in the past 30 days',
						'ohmylms'
					),
					value: R,
					progression_percent:
						Math.abs(
							Number(
								_ === null ||
									_ === void 0 ||
									null === ( a = _.earning ) ||
									void 0 === a ||
									null === ( a = a.growth ) ||
									void 0 === a
									? void 0
									: a.earning
							)
						) + '%',
					progression_text: ( 0, I18n.__ )(
						'within last',
						'ohmylms'
					),
					progression_delay: ( 0, I18n.__ )( '30 days', 'ohmylms' ),
					progression_state:
						Number(
							_ === null ||
								_ === void 0 ||
								null === ( o = _.earning ) ||
								void 0 === o ||
								null === ( o = o.growth ) ||
								void 0 === o
								? void 0
								: o.earning
						) > -1
							? 'success'
							: 'danger',
					card_class: 'card-earning',
					iconColor: 'var(--ohmylms-primary-color)',
				},
				{
					label: ( 0, I18n.__ )( 'Refund', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Total refund in the past 30 days',
						'ohmylms'
					),
					value: x || '0',
					progression_percent:
						Math.abs(
							Number(
								_ === null ||
									_ === void 0 ||
									null === ( i = _.earning ) ||
									void 0 === i ||
									null === ( i = i.growth ) ||
									void 0 === i
									? void 0
									: i.refund
							)
						) + '%',
					progression_text: 'within last',
					progression_delay: '30day',
					progression_state:
						Number(
							_ === null ||
								_ === void 0 ||
								null === ( c = _.earning ) ||
								void 0 === c ||
								null === ( c = c.growth ) ||
								void 0 === c
								? void 0
								: c.refund
						) > 0
							? 'danger'
							: 'success',
					card_class: 'card-refund',
					iconColor: '#ff4955',
				},
				{
					label: ( 0, I18n.__ )( 'Net Income', 'ohmylms' ),
					tooltip: ( 0, I18n.__ )(
						'Total net income in the past 30 days',
						'ohmylms'
					),
					value: C || '0',
					progression_percent:
						Math.abs(
							Number(
								_ === null ||
									_ === void 0 ||
									null === ( u = _.earning ) ||
									void 0 === u ||
									null === ( u = u.growth ) ||
									void 0 === u
									? void 0
									: u.net
							)
						) + '%',
					progression_text: 'within last',
					progression_delay: '10day',
					progression_state:
						Number(
							_ === null ||
								_ === void 0 ||
								null === ( s = _.earning ) ||
								void 0 === s ||
								null === ( s = s.growth ) ||
								void 0 === s
								? void 0
								: s.net
						) > -1
							? 'success'
							: 'danger',
					card_class: 'card-net-income',
				},
			];
		return (
			ReactHooks.useEffect( () => {
				const request = loadDashboard( l(), w, E, ( date ) =>
					sn()( date, S ).format( 'YYYY-MM-DD' )
				);
				return request.cancel;
			}, [ E ] ),
			(
				<Controls.CardWP variant={ 'secondary' } isBorderless={ ! 0 }>
					<Controls.SpacerWP
						marginBottom={ 0 }
						paddingX={ 7.5 }
						paddingY={ 6 }
					>
						<Controls.FlexWP
							gap={ 4 }
							align={ 'stretch' }
							className={ 'ohmylms-overview-cards-wrapper' }
						>
							<Controls.FlexItemWP
								style={ {
									flex: '9',
								} }
								className={ 'ohmylms-overview-left-cards' }
							>
								<Controls.FlexWP
									direction={ 'column' }
									align={ 'space-between' }
									justify={ 'space-between' }
								>
									<Controls.CardWP isBorderless={ ! 0 }>
										<Controls.SpacerWP
											marginBottom={ 0 }
											padding={ 6 }
										>
											<Controls.FlexWP
												wrap={ ! 0 }
												gap={ 4 }
											>
												<Controls.FlexWP
													align={ 'center' }
													justify={ 'space-between' }
													gap={ 3 }
												>
													<Controls.HeadingWP
														level={ 3 }
														size={ '16px' }
													>
														{ ( 0, I18n.__ )(
															'Earnings',
															'ohmylms'
														) }
													</Controls.HeadingWP>
													<Buttons.A
														title={ ( 0, I18n.__ )(
															'View Earnings Report',
															'ohmylms'
														) }
														variant={ 'link' }
														onClick={ function () {
															p(
																'/earnings-report'
															);
														} }
														icon={ React.createElement(
															kf,
															null
														) }
														iconPosition={ 'right' }
													>
														{ ( 0, I18n.__ )(
															'Earning Report'
														) }
													</Buttons.A>
												</Controls.FlexWP>
												<EarningsSummaryCards
													dashboardCardData={ P }
													dataLoading={ v }
												/>
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.CardWP>
									<DashboardStats
										data={ _ }
										dataLoading={ v }
									/>
								</Controls.FlexWP>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP
								style={ {
									flex: '4',
								} }
								className={ 'ohmylms-overview-right-cards' }
							>
								<TopCoursePerformance
									data={
										_ === null || _ === void 0
											? void 0
											: _.top_course
									}
									totalCourses={
										_ === null || _ === void 0
											? void 0
											: _.total_course
									}
									dataLoading={ v }
									handleAddCourse={ m }
								/>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
						<Controls.SpacerWP marginBottom={ 4 } />
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP marginBottom={ 0 } padding={ 6 }>
								<Controls.FlexWP
									align={ 'center' }
									justify={ 'space-between' }
									gap={ 4 }
								>
									<Controls.HeadingWP
										level={ 3 }
										size={ '16px' }
									>
										{ ( 0, I18n.__ )(
											'Recent Published Courses ',
											'ohmylms'
										) }
										{ ! v && (
											<Controls.TextWP
												as={ 'span' }
												variant={ 'muted' }
												size={ '16px' }
												weight={ '400' }
											>
												{ '(' }
												{ _ === null ||
												_ === void 0 ||
												null ===
													( d = _.recent_courses ) ||
												void 0 === d
													? void 0
													: d.length }
												{ ')' }
											</Controls.TextWP>
										) }
									</Controls.HeadingWP>
									<Buttons.A
										variant={ 'primary' }
										size={ 'md' }
										onClick={ function () {
											p( '/courses' );
										} }
									>
										{ ( 0, I18n.__ )(
											'View All Courses',
											'ohmylms'
										) }
									</Buttons.A>
								</Controls.FlexWP>
								<Controls.SpacerWP marginBottom={ 4 } />
								<RecentCourses
									popularCourses={ sortRecentCourses(
										_?.recent_courses || []
									) }
									dataLoading={ v }
									currency={ _.currency }
									currency_pos={ _.currency_pos }
									handleAddCourse={ m }
								/>
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			 )
		 );
	};
}
