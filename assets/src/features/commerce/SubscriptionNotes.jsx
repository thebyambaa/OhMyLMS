/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createSubscriptionNotes( readRuntime ) {
	return function SubscriptionNotes( props ) {
		const {
			I: Controls,
			JQ,
			JY,
			React,
			b: I18n,
			g: ReactHooks,
			q,
		} = readRuntime();
		var t = props.notes,
			n = void 0 === t ? [] : t,
			r =
				( props.subscription,
				JQ( ( 0, ReactHooks.useState )( '' ), 2 ) ),
			a = ( r[ 0 ], r[ 1 ], JQ( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			o = ( a[ 0 ], a[ 1 ], JQ( ( 0, ReactHooks.useState )( ! 0 ), 2 ) ),
			i = o[ 0 ],
			l = o[ 1 ];
		return (
			<React.Fragment>
				<Controls.FlexWP
					gap={ 2 }
					justify={ 'space-between' }
					align={ 'center' }
				>
					<Controls.HeadingWP
						level={ 4 }
						size={ 18 }
						weight={ 500 }
						color={ '#000D25' }
					>
						{ ( 0, I18n.__ )( 'Subscription Notes', 'ohmylms' ) }
					</Controls.HeadingWP>
					<Controls.ButtonWP
						size={ 'small' }
						onClick={ function () {
							return l( ! i );
						} }
					>
						<svg
							style={ {
								transform: i
									? 'rotate(0deg)'
									: 'rotate(180deg)',
							} }
							width={ '12' }
							height={ '6' }
							fill={ 'none' }
							viewBox={ '0 0 12 6' }
							xmlns={ 'http://www.w3.org/2000/svg' }
						>
							<path
								fill={ '#000D25' }
								d={
									'M11.5 4.4L6 0 .5 4.4l.9 1.2L6 2l4.5 3.6 1-1.2z'
								}
							/>
						</svg>
					</Controls.ButtonWP>
				</Controls.FlexWP>
				{ i && (
					<React.Fragment>
						{ n.length > 0 && (
							<React.Fragment>
								<Controls.SpacerWP
									marginTop={ 6 }
									marginBottom={ 0 }
								/>
								<q.__experimentalScrollable
									style={ {
										maxHeight: 500,
									} }
								>
									{ n.map( function ( e, _tLocal ) {
										return (
											<div key={ _tLocal }>
												<Controls.SpacerWP
													marginBottom={ 0 }
													marginTop={
														0 === _tLocal ? 0 : 4
													}
												>
													<Controls.CardWP
														isBorderless={ ! 0 }
														variant={ 'muted' }
														style={ {
															borderRadius: '7px',
														} }
													>
														<Controls.SpacerWP
															marginBottom={ 0 }
															padding={ 4 }
														>
															<Controls.TextWP
																variant={
																	'muted'
																}
																color={
																	'#000D25'
																}
																weight={ 400 }
																size={ 13 }
															>
																{ e.content }
															</Controls.TextWP>
														</Controls.SpacerWP>
													</Controls.CardWP>
													<Controls.SpacerWP
														marginBottom={ 1 }
													/>
													<Controls.FlexWP
														align={ 'center' }
														gap={ 3 }
														justify={
															'space-between'
														}
													>
														<Controls.TextWP
															as={ 'time' }
															variant={ 'muted' }
															size={ 13 }
															color={ '#8C929B;' }
														>
															{ JY(
																e.date_created
																	.date
															) }
															{ ' - ' }
															{ e.added_by }
														</Controls.TextWP>
													</Controls.FlexWP>
												</Controls.SpacerWP>
											</div>
										);
									} ) }
								</q.__experimentalScrollable>
							</React.Fragment>
						) }
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
