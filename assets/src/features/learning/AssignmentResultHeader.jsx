/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createAssignmentResultHeader( readRuntime ) {
	return function AssignmentResultHeader( props ) {
		const { Ge, React, b: I18n, f: Router, oK, r$, sn, v } = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			data = props.data,
			submissions = props.submissions,
			handleUpgradeGrade = props.handleUpgradeGrade,
			u = ( 0, Router.g )().id;
		return (
			<React.Fragment>
				<Controls.FlexWP>
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.FlexWP
							justify={ 'center' }
							align={ 'center' }
							gap={ 2 }
						>
							<Controls.SpacerWP padding={ 2 } marginBottom={ 0 }>
								<Controls.FlexWP
									justify={ 'center' }
									align={ 'center' }
									gap={ 2 }
								>
									<v.Link to={ '/assignments' }>
										<Controls.FlexWP
											justify={ 'flex-start' }
											align={ 'center' }
											gap={ 1 }
										>
											{ React.createElement( oK, null ) }
											<Controls.TextWP size={ 15 }>
												{ ( 0, I18n.__ )(
													'Assignments /',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexWP>
									</v.Link>
									<v.Link
										to={ '/assignment-report/'.concat( u ) }
									>
										<Controls.TextWP size={ 15 }>
											{ ( 0, I18n.__ )(
												'Results /',
												'ohmylms'
											) }
										</Controls.TextWP>
									</v.Link>
									<Controls.TextWP size={ 15 }>
										{ Ge(
											( data === null || data === void 0
												? void 0
												: data.display_name ) ||
												( 0, I18n.__ )(
													'Assignment Result',
													'ohmylms'
												)
										) }
									</Controls.TextWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.FlexWP>
					</Controls.CardWP>
					<Controls.FlexItemWP>
						<Controls.FlexWP gap={ 4 } justify={ 'flex-start' }>
							<Controls.FlexItemWP>
								<Controls.FlexWP gap={ 2 } justify={ 'center' }>
									{ React.createElement( r$, null ) }
									<time
										style={ {
											fontSize: '15px',
										} }
									>
										{ sn()(
											null === ( t = submissions[ 0 ] ) ||
												void 0 === t
												? void 0
												: t.submitted_date
										).format( 'MMM D, YYYY h:mm A' ) }
									</time>
								</Controls.FlexWP>
							</Controls.FlexItemWP>
							<Controls.FlexItemWP>
								<Controls.BadgeWP
									style={ {
										textTransform: 'capitalize',
									} }
									isBorderLess={ ! 0 }
									variant={ ( () => {
										if (
											'submitted' ===
											( null ===
												( n = submissions[ 0 ] ) ||
											void 0 === n
												? void 0
												: n.status )
										) {
											return 'warning';
										}
										if (
											'passed' ===
											( null ===
												( r = submissions[ 0 ] ) ||
											void 0 === r
												? void 0
												: r.status )
										) {
											return 'success';
										}
										return 'danger';
									} )() }
								>
									{ ( () => {
										if (
											'submitted' ===
											( null ===
												( a = submissions[ 0 ] ) ||
											void 0 === a
												? void 0
												: a.status )
										) {
											return 'Pending';
										}
										if (
											null === ( o = submissions[ 0 ] ) ||
											void 0 === o
										) {
											return void 0;
										}
										return o.status;
									} )() }
								</Controls.BadgeWP>
							</Controls.FlexItemWP>
						</Controls.FlexWP>
					</Controls.FlexItemWP>
					<Controls.ButtonWP
						variant={ 'primary' }
						onClick={ handleUpgradeGrade }
					>
						{ ( 0, I18n.__ )( 'Upgrade Grade', 'ohmylms' ) }
					</Controls.ButtonWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
