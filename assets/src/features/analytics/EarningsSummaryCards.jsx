/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Skeleton } from '../../shared/skeleton';
import { Tooltip } from '../../shared/tooltip';
import { InfoIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createEarningsSummaryCards( readRuntime ) {
	return function EarningsSummaryCards( props ) {
		const { React, b: I18n } = readRuntime();
		var t = props.dashboardCardData,
			n = props.dataLoading,
			r = props.withIn,
			a = void 0 === r ? 'last 30 days' : r,
			o = props.variant,
			i = void 0 === o ? 'secondary' : o;
		return (
			<React.Fragment>
				{ t.map( function ( e, _tLocal ) {
					var _rLocal,
						_oLocal =
							( e === null || e === void 0
								? void 0
								: e.iconColor ) || '#33A646';
					return (
						<Controls.FlexBlockWP
							key={ e.label + _tLocal }
							className={
								null !==
									( _rLocal =
										e === null || e === void 0
											? void 0
											: e.card_class ) &&
								void 0 !== _rLocal
									? _rLocal
									: ''
							}
						>
							<Controls.CardWP
								isBorderless={ ! 0 }
								variant={ i }
								key={ e.label }
							>
								<Controls.SpacerWP
									paddingX={ 4 }
									paddingY={ 5 }
									marginBottom={ 0 }
								>
									{ n ? (
										<Skeleton rows={ 3 } active={ ! 0 } />
									) : (
										<React.Fragment>
											<Controls.BadgeWP
												isBorderLess={ ! 0 }
												isRounded={ ! 0 }
												padding={ '7px' }
											>
												<svg
													width={ '13' }
													height={ '12' }
													fill={ 'none' }
													viewBox={ '0 0 13 12' }
													xmlns={
														'http://www.w3.org/2000/svg'
													}
												>
													<path
														fill={ _oLocal }
														fillRule={ 'evenodd' }
														d={
															'M4.982 2.253c-.069-.285-.523-.285-.591 0L3.669 5.26c-.179.746-.917 1.28-1.772 1.28H1.06C.728 6.54.457 6.297.457 6c0-.298.27-.54.604-.54h.836c.285 0 .53-.177.59-.426l.722-3.007c.341-1.422 2.613-1.422 2.954 0l1.853 7.72c.068.284.522.284.59 0l.722-3.007c.18-.747.918-1.28 1.773-1.28h.835c.334 0 .604.242.604.54 0 .298-.27.54-.604.54h-.835c-.285 0-.532.177-.591.426l-.722 3.007c-.341 1.422-2.613 1.422-2.954 0l-1.852-7.72z'
														}
														clipRule={ 'evenodd' }
													/>
												</svg>
											</Controls.BadgeWP>
											<Controls.SpacerWP
												marginBottom={ 5 }
											/>
											<Controls.FlexWP
												direction={ 'column' }
												gap={ 2 }
												className={ 'content-area' }
											>
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
														{ e.label }
													</Controls.TextWP>
													{ e.tooltip && (
														<Tooltip
															text={ e.tooltip }
															className={
																'ohmylms-tooltip'
															}
															placement={ 'top' }
														>
															<React.Fragment>
																<InfoIcon />
															</React.Fragment>
														</Tooltip>
													) }
												</Controls.FlexWP>
												<span
													style={ {
														fontSize: '36px',
														fontWeight: '500',
														lineHeight: 1,
													} }
													className={
														'ohmylms-card-value'
													}
												>
													{ e.value }
												</span>
												{ a && (
													<Controls.BadgeWP
														isBorderLess={ ! 0 }
														variant={
															e === null ||
															e === void 0
																? void 0
																: e.progression_state
														}
														isRounded={ ! 1 }
														style={ {
															width: 'fit-content',
														} }
													>
														<Controls.FlexWP
															justify={
																'flex-start'
															}
															gap={ 3 }
															align={ 'center' }
														>
															{ '0%' !==
																e.progression_percent && (
																<React.Fragment>
																	<svg
																		style={ {
																			transform:
																				'rotate('.concat(
																					( () => {
																						if (
																							'card-refund' ===
																							( e ===
																								null ||
																							e ===
																								void 0
																								? void 0
																								: e.card_class )
																						) {
																							if (
																								'success' !==
																								( e ===
																									null ||
																								e ===
																									void 0
																									? void 0
																									: e.progression_state )
																							) {
																								return '0deg';
																							}
																							return '180deg';
																						}
																						if (
																							'success' ===
																							( e ===
																								null ||
																							e ===
																								void 0
																								? void 0
																								: e.progression_state )
																						) {
																							return '0deg';
																						}
																						return '180deg';
																					} )(),
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
																</React.Fragment>
															) }
															<Controls.TextWP
																html={ ! 0 }
																as={ 'p' }
																size={ '12px' }
																variant={
																	'muted'
																}
															>
																{ ( 0,
																I18n.__ )(
																	''
																		.concat(
																			e.progression_percent,
																			' within '
																		)
																		.concat(
																			a
																		),
																	'ohmylms'
																) }
															</Controls.TextWP>
														</Controls.FlexWP>
													</Controls.BadgeWP>
												) }
											</Controls.FlexWP>
										</React.Fragment>
									) }
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexBlockWP>
					);
				} ) }
			</React.Fragment>
		);
	};
}
