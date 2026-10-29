/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { PriceDisplay, decodeHtmlEntities } from '../../shared/utils';
export function createCoursePreview( readRuntime ) {
	return function CoursePreview( props ) {
		const {
			$H,
			Br,
			QH,
			React,
			T: StoreModule,
			ZH,
			b: I18n,
			f: Router,
			g: ReactHooks,
			sn,
			y: WordPressData,
		} = readRuntime();
		var t,
			completedSteps = props.completedSteps,
			r = ( props.onSave, ( 0, Router.g )().id ),
			a =
				( ( 0, WordPressData.useDispatch )( StoreModule.default ),
				( 0, WordPressData.useSelect )(
					function ( e ) {
						return e( StoreModule.default ).getCourse();
					},
					[ r ]
				) ),
			o = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getCourseChapters();
				},
				[ r ]
			),
			i = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( 'ohmylms/store' ).getCourseChaptersContent();
				},
				[ r ]
			),
			l = ( function ( e ) {
				return ( 0, ReactHooks.useMemo )(
					function () {
						var _tLocal = [ 'text', 'video', 'audio' ],
							n = 0,
							_rLocal = 0,
							_aLocal = 0,
							_oLocal = 0;
						return (
							Object.values( e ).forEach( function ( _eLocal ) {
								if (
									_tLocal.includes(
										_eLocal === null || _eLocal === void 0
											? void 0
											: _eLocal.type
									)
								) {
									n++;
								} else {
									( () => {
										if (
											'quiz' ===
											( _eLocal === null ||
											_eLocal === void 0
												? void 0
												: _eLocal.type )
										) {
											return _rLocal++;
										}
										if (
											'assignment' ===
											( _eLocal === null ||
											_eLocal === void 0
												? void 0
												: _eLocal.type )
										) {
											return _aLocal++;
										}
										return (
											'session' === _eLocal.type &&
											_oLocal++
										);
									} )();
								}
							} ),
							{
								lessons: n,
								quizzes: _rLocal,
								assignments: _aLocal,
								sessions: _oLocal,
							}
						 );
					},
					[ e ]
				);
			} )( i === null || i === void 0 ? void 0 : i.byId ),
			lessons = l.lessons,
			quizzes = l.quizzes,
			assignments = l.assignments,
			sessions = l.sessions,
			m = QH( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		void ( m[ 0 ], m[ 1 ], QH( ( 0, ReactHooks.useState )( ! 1 ), 2 ) );
		QH( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		function h( e ) {
			if ( ! e || ( ! e.hour && ! e.min && ! e.sec ) ) {
				return ( 0, I18n.__ )( 'Not Set', 'ohmylms' );
			}
			var _tLocal2 = [];
			if ( e.hour ) {
				var n = ( 0, I18n.sprintf )(
					( 0, I18n._n )( '%d hour', '%d hours', parseInt( e.hour ) ),
					parseInt( e.hour )
				);
				_tLocal2.push( n );
			}
			if ( e.min ) {
				var _rLocal2 = ( 0, I18n.sprintf )(
					( 0, I18n._n )(
						'%d minute',
						'%d minutes',
						parseInt( e.min )
					),
					parseInt( e.min )
				);
				_tLocal2.push( _rLocal2 );
			}
			if ( e.sec ) {
				var _aLocal2 = ( 0, I18n.sprintf )(
					( 0, I18n._n )(
						'%d second',
						'%d seconds',
						parseInt( e.sec )
					),
					parseInt( e.sec )
				);
				_tLocal2.push( _aLocal2 );
			}
			return _tLocal2.join( ', ' );
		}
		var _ = ( 0, ReactHooks.useMemo )(
				function () {
					var e, _tLocal3;
					return [
						{
							id: 0,
							label: ( 0, I18n.__ )( 'Course Title', 'ohmylms' ),
							value: decodeHtmlEntities(
								null !==
									( e =
										a === null || a === void 0
											? void 0
											: a.name ) && void 0 !== e
									? e
									: ( 0, I18n.__ )( 'Not set', 'ohmylms' )
							),
						},
						{
							id: 1,
							label: ( 0, I18n.__ )( 'Categories', 'ohmylms' ),
							value: ( () => {
								if (
									0 ===
									( a === null || a === void 0
										? void 0
										: a.categories.length )
								) {
									return ( 0, I18n.__ )(
										'Not set',
										'ohmylms'
									);
								}
								if ( a === null || a === void 0 ) {
									return void 0;
								}
								return a.categories
									.map( function ( _eLocal2 ) {
										return decodeHtmlEntities(
											_eLocal2.name
										);
									} )
									.join( ', ' );
							} )(),
						},
						{
							id: 2,
							label: ( 0, I18n.__ )( 'Chapters', 'ohmylms' ),
							value:
								null ===
									( _tLocal3 = Object.values(
										o === null || o === void 0
											? void 0
											: o.byId
									) ) || void 0 === _tLocal3
									? void 0
									: _tLocal3.length,
						},
						{
							id: 3,
							label: ( 0, I18n.__ )( 'Lessons', 'ohmylms' ),
							value: lessons,
						},
					].concat(
						( function ( _eLocal3 ) {
							return (
								( function ( _eLocal4 ) {
									if ( Array.isArray( _eLocal4 ) ) {
										return $H( _eLocal4 );
									}
								} )( _eLocal3 ) ||
								( function ( _eLocal5 ) {
									if (
										( 'undefined' !== typeof Symbol &&
											( ( _nullableValue ) =>
												_nullableValue !== null &&
												_nullableValue !== void 0 )(
												_eLocal5[ Symbol.iterator ]
											) ) ||
										( ( _nullableValue2 ) =>
											_nullableValue2 !== null &&
											_nullableValue2 !== void 0 )(
											_eLocal5[ '@@iterator' ]
										)
									) {
										return Array.from( _eLocal5 );
									}
								} )( _eLocal3 ) ||
								ZH( _eLocal3 ) ||
								( function () {
									throw new TypeError(
										'Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
									);
								} )()
							);
						} )(
							a !== null && a !== void 0 && a.is_cohort
								? [
										{
											id: 10,
											label: ( 0, I18n.__ )(
												'Sessions',
												'cretorlms'
											),
											value: sessions,
										},
									]
								: []
						),
						[
							{
								id: 4,
								label: ( 0, I18n.__ )( 'Quizzes', 'ohmylms' ),
								value: quizzes,
							},
							{
								id: 5,
								label: ( 0, I18n.__ )(
									'Assignments',
									'ohmylms'
								),
								value: assignments,
							},
							{
								id: 6,
								label: ( 0, I18n.__ )( 'Pricing', 'ohmylms' ),
								value: ( () => {
									if (
										'free' ===
										( a === null || a === void 0
											? void 0
											: a.price_type )
									) {
										return ( 0, I18n.__ )(
											'Free',
											'ohmylms'
										);
									}
									if (
										a !== null &&
										a !== void 0 &&
										a.sale_price
									) {
										return (
											<React.Fragment>
												<PriceDisplay
													currency={
														( a === null ||
														a === void 0
															? void 0
															: a.currency ) ||
														'$'
													}
													currency_pos={
														( a === null ||
														a === void 0
															? void 0
															: a.currency_pos ) ||
														'left'
													}
													price={ Number(
														a === null ||
															a === void 0
															? void 0
															: a.sale_price
													) }
												/>
												{ ' ' }
												<del>
													<PriceDisplay
														currency={
															( a === null ||
															a === void 0
																? void 0
																: a.currency ) ||
															'$'
														}
														currency_pos={
															( a === null ||
															a === void 0
																? void 0
																: a.currency_pos ) ||
															'left'
														}
														price={ Number(
															a === null ||
																a === void 0
																? void 0
																: a.regular_price
														) }
														del={ ! 0 }
													/>
												</del>
											</React.Fragment>
										);
									}
									return (
										<PriceDisplay
											currency={
												( a === null || a === void 0
													? void 0
													: a.currency ) || '$'
											}
											currency_pos={
												( a === null || a === void 0
													? void 0
													: a.currency_pos ) || 'left'
											}
											price={ Number(
												a === null || a === void 0
													? void 0
													: a.regular_price
											) }
										/>
									);
								} )(),
							},
							{
								id: 7,
								label: ( 0, I18n.__ )(
									'Course Duration',
									'ohmylms'
								),
								value: h(
									a === null || a === void 0
										? void 0
										: a.duration
								),
							},
							{
								id: 8,
								label: ( 0, I18n.__ )( 'Level', 'ohmylms' ),
								value: (
									<span
										style={ {
											textTransform:
												'all' ===
												( a === null || a === void 0
													? void 0
													: a.level )
													? 'inherit'
													: 'capitalize',
										} }
									>
										{ ( () => {
											if (
												'all' ===
												( a === null || a === void 0
													? void 0
													: a.level )
											) {
												return ( 0, I18n.__ )(
													'All levels',
													'ohmylms'
												);
											}
											if ( a === null || a === void 0 ) {
												return void 0;
											}
											return a.level;
										} )() }
									</span>
								),
							},
							{
								id: 9,
								label: ( 0, I18n.__ )( 'Slug', 'ohmylms' ),
								value:
									a === null || a === void 0
										? void 0
										: a.slug,
							},
						]
					);
				},
				[ a, o, lessons, quizzes, assignments, sessions ]
			),
			w = [
				{
					id: 0,
					label: ( 0, I18n.__ )( 'Course title', 'ohmylms' ),
					completed: Boolean(
						a === null || a === void 0 ? void 0 : a.name
					),
				},
				{
					id: 1,
					label: ( 0, I18n.__ )( 'Course description', 'ohmylms' ),
					completed: Boolean(
						a === null || a === void 0 ? void 0 : a.description
					),
				},
				{
					id: 2,
					label: ( 0, I18n.__ )( 'Course Thumbnail', 'ohmylms' ),
					completed:
						Boolean(
							a === null || a === void 0 ? void 0 : a.video_src
						) ||
						Boolean(
							a === null || a === void 0 ? void 0 : a.image_src
						),
				},
				{
					id: 3,
					label: ( 0, I18n.__ )( 'Course Content', 'ohmylms' ),
					completed:
						0 !== lessons || 0 !== assignments || 0 !== quizzes,
				},
				{
					id: 4,
					label: ( 0, I18n.__ )( 'Pricing', 'ohmylms' ),
					completed:
						completedSteps === null || completedSteps === void 0
							? void 0
							: completedSteps.includes( 1 ),
				},
			];
		return (
			<React.Fragment>
				<Controls.ContainerWP>
					<Controls.SpacerWP
						paddingY={ 6 }
						marginBottom={ 0 }
						className={ 'ohmylms-course-preview' }
					>
						<Controls.CardWP
							variant={ 'secondary' }
							isBorderless={ ! 0 }
						>
							<Controls.SpacerWP
								padding={ 10 }
								marginBottom={ 0 }
							>
								<Controls.FlexWP
									gap={ 5 }
									align={ 'flex-start' }
									className={
										'ohmylms-course-preview-wrapper'
									}
								>
									<Controls.CardWP
										style={ {
											flex: '5',
										} }
										isBorderless={ ! 0 }
									>
										<Controls.SpacerWP
											padding={ 5 }
											marginBottom={ 0 }
										>
											<Controls.FlexWP
												gap={ 4 }
												align={ 'center' }
												justify={ 'space-between' }
											>
												<Controls.HeadingWP
													level={ '3' }
												>
													{ ( 0, I18n.__ )(
														'Review Course Summary',
														'ohmylms'
													) }
												</Controls.HeadingWP>
												<Controls.ButtonWP
													href={
														a === null ||
														a === void 0
															? void 0
															: a.course_url
													}
													target={ '_blank' }
													variant={ 'link' }
													style={ {
														gap: '5px',
														textDecoration: 'none',
													} }
												>
													<Br />
													{ ( 0, I18n.__ )(
														'Preview',
														'ohmylms'
													) }
												</Controls.ButtonWP>
											</Controls.FlexWP>
											<Controls.SpacerWP
												marginBottom={ 4 }
											/>
											<Controls.FlexWP
												gap={ 0 }
												direction={ 'column' }
												className={
													'ohmylms-review-summery-list'
												}
											>
												{ _.map( function ( e ) {
													return (
														<div
															key={
																e === null ||
																e === void 0
																	? void 0
																	: e.id
															}
														>
															<Controls.DividerWP
																marginStart={
																	0
																}
																marginEnd={ 0 }
															/>
															<Controls.SpacerWP
																paddingY={ 3 }
																marginBottom={
																	0
																}
															>
																<Controls.FlexWP
																	gap={ 2 }
																	align={
																		'center'
																	}
																	justify={
																		'space-between'
																	}
																>
																	<Controls.TextWP
																		as={
																			'span'
																		}
																		size={
																			14
																		}
																	>
																		{ decodeHtmlEntities(
																			e.label
																		) }
																	</Controls.TextWP>
																	<Controls.TextWP
																		as={
																			'span'
																		}
																		size={
																			14
																		}
																		style={ {
																			textOverflow:
																				'ellipsis',
																			overflow:
																				'hidden',
																			whiteSpace:
																				'nowrap',
																			maxWidth:
																				'70%',
																		} }
																	>
																		{
																			e.value
																		}
																	</Controls.TextWP>
																</Controls.FlexWP>
															</Controls.SpacerWP>
														</div>
													);
												} ) }
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.CardWP>
									<Controls.CardWP
										style={ {
											flex: '2',
										} }
										isBorderless={ ! 0 }
									>
										<Controls.SpacerWP
											padding={ 5 }
											marginBottom={ 0 }
										>
											<Controls.HeadingWP level={ '3' }>
												{ ( 0, I18n.__ )(
													'Publish Checklist',
													'ohmylms'
												) }
											</Controls.HeadingWP>
											{ w.filter( function ( e ) {
												return e.completed;
											} ).length < w.length && (
												<React.Fragment>
													<Controls.SpacerWP
														marginBottom={ 4 }
													/>
													<Controls.CardWP
														isBorderless={ ! 0 }
														variant={ 'secondary' }
													>
														<Controls.SpacerWP
															marginBottom={ 0 }
															padding={ 4 }
														>
															<Controls.FlexWP
																align={
																	'flex-start'
																}
																gap={ 2 }
																justify={
																	'flex-start'
																}
															>
																<Controls.FlexItemWP
																	style={ {
																		width: '18px',
																		position:
																			'relative',
																		top: '1px',
																	} }
																>
																	<svg
																		fill={
																			'none'
																		}
																		width={
																			'18'
																		}
																		height={
																			'18'
																		}
																		viewBox={
																			'0 0 12 12'
																		}
																		xmlns={
																			'http://www.w3.org/2000/svg'
																		}
																	>
																		<path
																			fill={
																				'#7A8B9A'
																			}
																			fillRule={
																				'evenodd'
																			}
																			d={
																				'M11 6A5 5 0 111 6a5 5 0 0110 0zm-5-.5a.5.5 0 01.5.5v2.5a.5.5 0 11-1 0V6a.5.5 0 01.5-.5zm0-1a.5.5 0 100-1 .5.5 0 000 1z'
																			}
																			clipRule={
																				'evenodd'
																			}
																		/>
																	</svg>
																</Controls.FlexItemWP>
																<Controls.FlexItemWP
																	style={ {
																		width: 'calc(100% - 20px)',
																	} }
																>
																	{ w.filter(
																		function (
																			e
																		) {
																			return e.completed;
																		}
																	).length <
																		w.length && (
																		<Controls.TextWP
																			as={
																				'p'
																			}
																			size={
																				14
																			}
																		>
																			{ ( 0,
																			I18n.__ )(
																				'Your course needs attention. Please finish the following checklist before publishing:',
																				'ohmylms'
																			) }
																		</Controls.TextWP>
																	) }
																</Controls.FlexItemWP>
															</Controls.FlexWP>
														</Controls.SpacerWP>
													</Controls.CardWP>
												</React.Fragment>
											) }
											<Controls.SpacerWP
												marginBottom={ 4 }
											/>
											<Controls.CardWP
												isBorderless={ ! 0 }
												variant={ 'secondary' }
											>
												<Controls.SpacerWP
													marginBottom={ 0 }
													padding={ 4 }
												>
													<Controls.FlexWP
														gap={ 3 }
														direction={ 'column' }
													>
														{ w.map(
															function ( e ) {
																return (
																	<Controls.FlexWP
																		gap={
																			2
																		}
																		align={
																			'center'
																		}
																		justify={
																			'flex-start'
																		}
																		key={
																			e.id
																		}
																		className={ ' '.concat(
																			e.completed
																				? 'completed'
																				: ''
																		) }
																	>
																		<svg
																			width={
																				'19'
																			}
																			height={
																				'19'
																			}
																			fill={
																				'none'
																			}
																			viewBox={
																				'0 0 19 19'
																			}
																			xmlns={
																				'http://www.w3.org/2000/svg'
																			}
																		>
																			<rect
																				width={
																					'18'
																				}
																				height={
																					'18'
																				}
																				x={
																					'.5'
																				}
																				y={
																					'.5'
																				}
																				fill={
																					e.completed
																						? 'green'
																						: '#7A8B9A'
																				}
																				rx={
																					'9'
																				}
																			/>
																			<path
																				fill={
																					'#fff'
																				}
																				fillRule={
																					'evenodd'
																				}
																				d={
																					'M15.502 5.63a.731.731 0 010 1.033l-6.279 6.28a2.194 2.194 0 01-3.103 0L3.498 10.32a.731.731 0 111.034-1.034l2.622 2.622a.731.731 0 001.035 0l6.279-6.279a.731.731 0 011.034 0z'
																				}
																				clipRule={
																					'evenodd'
																				}
																			/>
																		</svg>
																		<span
																			style={ {
																				color: e.completed
																					? 'green'
																					: '#7A8B9A',
																			} }
																		>
																			{
																				e.label
																			}
																		</span>
																	</Controls.FlexWP>
																);
															}
														) }
													</Controls.FlexWP>
												</Controls.SpacerWP>
											</Controls.CardWP>
											{ 'future' ===
												( a === null || a === void 0
													? void 0
													: a.status ) && (
												<div
													className={
														'ohmylms-course-publish-status'
													}
												>
													<p>
														{ ( 0, I18n.__ )(
															'Your course is scheduled for publishing on ',
															'ohmylms'
														) }
														<strong>
															{ sn()(
																a === null ||
																	a ===
																		void 0 ||
																	null ===
																		( t =
																			a.post_date ) ||
																	void 0 === t
																	? void 0
																	: t.date
															).format(
																'MMMM D, YYYY [at] h:mm A'
															) }
														</strong>
													</p>
												</div>
											) }
										</Controls.SpacerWP>
									</Controls.CardWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
