/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createQuizQuestionHeader( readRuntime ) {
	return function QuizQuestionHeader( props ) {
		const {
			Ge: decodeHtml,
			I: Controls,
			Mt: InfoIcon,
			React,
			V: Tooltip,
			b: I18n,
			f: Router,
			l$: IncorrectIcon,
			o$: CorrectIcon,
			s$,
			wn: NumberInput,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			data = props.data,
			index = props.index,
			type = props.type,
			u = void 0 === type ? '' : type,
			s = ( props.fetchData, props.setData ),
			isCorrect = props.isCorrect;
		( 0, Router.g )();
		var p = ( () => {
			if ( void 0 !== isCorrect ) {
				if ( isCorrect ) {
					return 'correct';
				}
				return 'incorrect';
			}
			return ( function ( e ) {
				var _tLocal = e.given_answer,
					_nLocal = e.questions;
				if ( ! _tLocal || ! Array.isArray( _tLocal ) ) {
					return 'in-review';
				}
				var _rLocal = _nLocal
						.filter( function ( _eLocal ) {
							return '1' === _eLocal.is_correct;
						} )
						.map( function ( _eLocal2 ) {
							return _eLocal2.id;
						} ),
					_aLocal = _tLocal.every( function ( _eLocal3 ) {
						return _rLocal.includes( _eLocal3 );
					} );
				return ( () => {
					if ( 'single-choice' === e.settings.type ) {
						if ( _aLocal && 1 === _tLocal.length ) {
							return 'correct';
						}
						return 'incorrect';
					}
					if ( _aLocal && _tLocal.length === _rLocal.length ) {
						return 'correct';
					}
					return 'incorrect';
				} )();
			} )( data );
		} )();
		return (
			<React.Fragment>
				<Controls.FlexWP gap={ 3 } justify={ 'space-between' }>
					<Controls.FlexItemWP>
						<Controls.BadgeWP
							variant={ 'secondary' }
							isBorderLess={ ! 0 }
						>
							{ ( 0, I18n.__ )( 'Question', 'ohmylms' ) }{ ' ' }
							{ index + 1 }
						</Controls.BadgeWP>
						<Controls.BadgeWP
							variant={ 'warning' }
							style={ {
								textTransform: 'capitalize',
								marginLeft: '8px',
							} }
							isBorderLess={ ! 0 }
						>
							{ null !==
								( t =
									data === null ||
									data === void 0 ||
									null === ( n = data.settings ) ||
									void 0 === n
										? void 0
										: n.type.split( '-' ).join( ' ' ) ) &&
							void 0 !== t
								? t
								: u }
						</Controls.BadgeWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP>
						{ 'text-type' === u ? (
							<React.Fragment>
								<Controls.FlexWP
									gap={ 2 }
									justify={ 'flex-start' }
									className={
										'ohmylms-question-status-wrapper'
									}
								>
									<Controls.FlexItemWP>
										<Controls.FlexWP
											gap={ 2 }
											justify={ 'flex-start' }
											className={ 'ohmylms-set-marks' }
										>
											<Controls.FlexItemWP>
												<Controls.FlexWP
													justify={ 'start' }
													gap={ 2 }
												>
													<Controls.TextWP
														as={ 'span' }
													>
														{ ( 0, I18n.__ )(
															'Set Marks',
															'ohmylms'
														) }
													</Controls.TextWP>
													<Tooltip.A
														title={ ( 0, I18n.__ )(
															'This Question Marks: '.concat(
																data === null ||
																	data ===
																		void 0 ||
																	null ===
																		( r =
																			data.settings ) ||
																	void 0 ===
																		r ||
																	null ===
																		( r =
																			r.score ) ||
																	void 0 === r
																	? void 0
																	: r.value
															),
															'ohmylms'
														) }
														className={
															'ohmylms-tooltip'
														}
													>
														<InfoIcon.A />
													</Tooltip.A>
												</Controls.FlexWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP>
												<NumberInput.A
													type={ 'number' }
													min={ 0 }
													max={ Number(
														data === null ||
															data === void 0 ||
															null ===
																( a =
																	data.settings ) ||
															void 0 === a ||
															null ===
																( a =
																	a.score ) ||
															void 0 === a
															? void 0
															: a.value
													) }
													value={
														Number(
															data === null ||
																data === void 0
																? void 0
																: data.achive_mark
														) || 0
													}
													onChange={ function ( e ) {
														return ( function (
															_eLocal4
														) {
															s(
																function (
																	_tLocal2
																) {
																	var _nLocal2,
																		_rLocal2 =
																			s$(
																				{},
																				_tLocal2
																			),
																		_aLocal2 =
																			_rLocal2 ===
																				null ||
																			_rLocal2 ===
																				void 0 ||
																			null ===
																				( _nLocal2 =
																					_rLocal2.report ) ||
																			void 0 ===
																				_nLocal2 ||
																			null ===
																				( _nLocal2 =
																					_nLocal2.questions ) ||
																			void 0 ===
																				_nLocal2
																				? void 0
																				: _nLocal2.map(
																						function (
																							_tLocal3
																						) {
																							return _tLocal3.id ===
																								( data ===
																									null ||
																								data ===
																									void 0
																									? void 0
																									: data.id )
																								? s$(
																										s$(
																											{},
																											_tLocal3
																										),
																										{},
																										{
																											achive_mark:
																												_eLocal4,
																										}
																									)
																								: _tLocal3;
																						}
																					);
																	return s$(
																		s$(
																			{},
																			_rLocal2
																		),
																		{},
																		{
																			report: s$(
																				s$(
																					{},
																					_rLocal2.report
																				),
																				{},
																				{
																					questions:
																						_aLocal2,
																				}
																			),
																		}
																	);
																}
															);
														} )( e );
													} }
													controls={ ! 1 }
												/>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP>
										<Controls.BadgeWP
											isBorderLess={ ! 0 }
											variant={
												'in-review' ===
												( data === null ||
												data === void 0
													? void 0
													: data.status )
													? 'warning'
													: 'success'
											}
										>
											{ 'in-review' ===
											( data === null || data === void 0
												? void 0
												: data.status )
												? ( 0, I18n.__ )(
														'In Review',
														'ohmylms'
													)
												: ( 0, I18n.__ )(
														'Graded',
														'ohmylms'
													) }
										</Controls.BadgeWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							</React.Fragment>
						) : (
							<React.Fragment>
								<Controls.BadgeWP
									variant={
										'correct' === p ? 'success' : 'danger'
									}
									isBorderLess={ ! 0 }
									style={ {
										textTransform: 'capitalize',
									} }
								>
									{ 'correct' === p ? (
										<CorrectIcon />
									) : (
										<IncorrectIcon />
									) }
									{ p }
								</Controls.BadgeWP>
							</React.Fragment>
						) }
					</Controls.FlexItemWP>
				</Controls.FlexWP>
				<Controls.SpacerWP marginY={ 4 }>
					<Controls.HeadingWP
						level={ 3 }
						className={ 'ohmylms-question-name' }
					>
						{ decodeHtml(
							data === null || data === void 0
								? void 0
								: data.name
						) }
						{ ( data === null ||
						data === void 0 ||
						null === ( o = data.settings ) ||
						void 0 === o
							? void 0
							: o.required ) && (
							<span className={ 'ohmylms-required' }>
								{ '*' }
							</span>
						) }
					</Controls.HeadingWP>
					<Controls.SpacerWP marginBottom={ 3 } />
					{ ( data === null || data === void 0
						? void 0
						: data.image ) && (
						<img
							src={
								data === null || data === void 0
									? void 0
									: data.image
							}
							alt={ '' }
							style={ {
								maxWidth: '100%',
								marginBottom: '20px',
								display: 'block',
							} }
						/>
					) }
					{ ( data === null || data === void 0
						? void 0
						: data.video ) && (
						<video
							controls={ ! 0 }
							style={ {
								maxWidth: '100%',
								marginBottom: '20px',
								display: 'block',
							} }
						>
							<source
								src={
									data === null || data === void 0
										? void 0
										: data.video
								}
								type={ 'video/mp4' }
							/>
						</video>
					) }
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
