/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { PlayerTemplatePicker } from './PlayerTemplatePicker';
export function createQuizSettingsFields( readRuntime ) {
	return function QuizSettingsFields( props ) {
		const {
			$m,
			Bt,
			He,
			I: Controls,
			Km,
			Pn,
			React,
			T: StoreModule,
			Um,
			Vm,
			Ym,
			Zm,
			b: I18n,
			g: ReactHooks,
			qm,
			sn,
			vn,
			y: WordPressData,
			zm,
		} = readRuntime();
		var t;
		var n;
		var r;
		var a;
		var o;
		var i;
		var l;
		var c;
		var u;
		var s = true;
		var d =
			( props.chapterId,
			( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getQuizSettings();
			}, [] ) );
		var quiz = ( 0, WordPressData.useSelect )( function ( e ) {
			return e( StoreModule.default ).getQuiz();
		}, [] );
		var p =
			( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getAllQuestions();
			}, [] ) || [];
		var Router =
			'cohort-based' ===
			( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourseType();
			}, [] );
		var setQuiz = ( 0, WordPressData.useDispatch )(
			StoreModule.default
		).setQuiz;
		var h =
			( ( 0, WordPressData.useDispatch )( StoreModule.default ),
			Ym( ( 0, ReactHooks.useState )( ! 1 ), 2 ) );
		var _ = ( h[ 0 ], h[ 1 ], Ym( ( 0, ReactHooks.useState )( ! 1 ), 2 ) );
		var w = ( _[ 0 ], _[ 1 ], Ym( ( 0, ReactHooks.useState )( ! 1 ), 2 ) );
		var E = w[ 0 ];
		var S = w[ 1 ];
		var x = function ( e, _tLocal ) {
			setQuiz( {
				settings: Um( Um( {}, d ), {}, qm( {}, e, _tLocal ) ),
			} );
		};
		var C = function ( e, _tLocal2, _nLocal ) {
			setQuiz(
				_nLocal
					? {
							settings: Um(
								Um( {}, d ),
								{},
								qm(
									{},
									e,
									Um(
										Um(
											{},
											d === null || d === void 0
												? void 0
												: d[ e ]
										),
										{},
										qm( {}, _nLocal, _tLocal2 )
									)
								)
							),
						}
					: {
							settings: Um(
								Um( {}, d ),
								{},
								qm( {}, e, _tLocal2 )
							),
						}
			);
		};
		var P = ( 0, ReactHooks.useCallback )(
			function ( e ) {
				if ( ! ( Number( e ) < 1 ) ) {
					setQuiz(
						Um(
							Um( {}, quiz ),
							{},
							{
								drip_settings: Um(
									Um(
										{},
										quiz === null || quiz === void 0
											? void 0
											: quiz.drip_settings
									),
									{},
									{
										days: e,
									}
								),
							}
						)
					);
				}
			},
			[ quiz, setQuiz ]
		);
		var O = p.reduce( function ( e, _tLocal3 ) {
			var _nLocal2;
			return (
				e +
				Number(
					( _tLocal3 === null ||
					_tLocal3 === void 0 ||
					null === ( _nLocal2 = _tLocal3.settings ) ||
					void 0 === _nLocal2 ||
					null === ( _nLocal2 = _nLocal2.score ) ||
					void 0 === _nLocal2
						? void 0
						: _nLocal2.value ) || 0
				)
			);
		}, 0 );
		return (
			( 0, ReactHooks.useEffect )( function () {
				if ( ! ( d !== null && d !== void 0 && d.allow_attempts ) ) {
					setQuiz( {
						settings: Um(
							Um( {}, d ),
							{},
							{
								allow_attempts: 1,
							}
						),
					} );
				}
			}, [] ),
			(
				<React.Fragment>
					<PlayerTemplatePicker
						value={ d?.player_template || 'playful' }
						onChange={ ( template ) =>
							x( 'player_template', template )
						}
					/>
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )( 'Visibility', 'ohmylms' ),
							description: ( 0, I18n.__ )(
								'Choose whether to publish this quiz for members or save it as a draft to keep editing.',
								'ohmylms'
							),
							className: 'ohmylms-quiz-visibility-settings',
						},
						<vn.A
							value={
								quiz === null || quiz === void 0
									? void 0
									: quiz.status
							}
							onChange={ function ( e ) {
								return setQuiz( {
									status: e,
								} );
							} }
							options={ Zm }
							placeholder={ ( 0, I18n.__ )(
								'Select Visibility',
								'ohmylms'
							) }
							className={ 'ohmylms-quiz-visibility-select' }
						/>
					) }
					<Pn
						onChange={ function () {
							var e;
							{
								var _tLocal4 = ! (
									quiz !== null &&
									quiz !== void 0 &&
									null !== ( e = quiz.drip_settings ) &&
									void 0 !== e &&
									e.enable
								);
								setQuiz(
									Um(
										Um( {}, quiz ),
										{},
										{
											drip_settings: Um(
												Um(
													{},
													quiz === null ||
														quiz === void 0
														? void 0
														: quiz.drip_settings
												),
												{},
												{
													enable: _tLocal4,
												},
												_tLocal4 && {
													type: Router
														? 'cohort-start'
														: 'enrollment-from-x-days',
												}
											),
										}
									)
								);
							}
						} }
						isChecked={
							quiz === null ||
							quiz === void 0 ||
							null === ( t = quiz.drip_settings ) ||
							void 0 === t
								? void 0
								: t.enable
						}
						onDripFeedTypeChange={ function ( e ) {
							{
								var _tLocal5,
									_nLocal3,
									_rLocal = Um(
										Um(
											{},
											quiz === null || quiz === void 0
												? void 0
												: quiz.drip_settings
										),
										{},
										{
											type: e,
										}
									);
								if ( 'specific-date' === e ) {
									delete _rLocal.days;
									_rLocal.date =
										( quiz === null ||
										quiz === void 0 ||
										null ===
											( _tLocal5 = quiz.drip_settings ) ||
										void 0 === _tLocal5
											? void 0
											: _tLocal5.date ) ||
										sn()( new Date() ).format(
											'YYYY-MM-DDTHH:mm:ss.SSSD'
										);
									_rLocal.time =
										( quiz === null ||
										quiz === void 0 ||
										null ===
											( _nLocal3 = quiz.drip_settings ) ||
										void 0 === _nLocal3
											? void 0
											: _nLocal3.time ) ||
										sn()( new Date() ).format(
											'YYYY-MM-DDTHH:mm:ss.SSSD'
										);
								} else if (
									'cohort-from-x-days' === e ||
									'enrollment-from-x-days' === e
								) {
									var _aLocal;
									delete _rLocal.date;
									delete _rLocal.time;
									_rLocal.days =
										( quiz === null ||
										quiz === void 0 ||
										null ===
											( _aLocal = quiz.drip_settings ) ||
										void 0 === _aLocal
											? void 0
											: _aLocal.days ) || 1;
								} else if ( 'cohort-start' === e ) {
									{
										delete _rLocal.days;
										delete _rLocal.date;
										delete _rLocal.time;
									}
								}
								setQuiz(
									Um(
										Um( {}, quiz ),
										{},
										{
											drip_settings: _rLocal,
										}
									)
								);
							}
						} }
						handleDripDatePickerChange={ function ( e ) {
							setQuiz(
								Um(
									Um( {}, quiz ),
									{},
									{
										drip_settings: Um(
											Um(
												{},
												quiz === null || quiz === void 0
													? void 0
													: quiz.drip_settings
											),
											{},
											{
												date: e
													? sn()( e ).format(
															'YYYY-MM-DDTHH:mm:ss.SSS'
														)
													: null,
											}
										),
									}
								)
							);
						} }
						handleDripTimePickerChange={ function ( e ) {
							setQuiz(
								Um(
									Um( {}, quiz ),
									{},
									{
										drip_settings: Um(
											Um(
												{},
												quiz === null || quiz === void 0
													? void 0
													: quiz.drip_settings
											),
											{},
											{
												time: e
													? sn()( e ).format(
															'YYYY-MM-DDTHH:mm:ss.SSS'
														)
													: null,
											}
										),
									}
								)
							);
						} }
						handleDayChange={ P }
						dripFeedType={
							quiz === null ||
							quiz === void 0 ||
							null === ( n = quiz.drip_settings ) ||
							void 0 === n
								? void 0
								: n.type
						}
						dripDate={
							( quiz === null ||
							quiz === void 0 ||
							null === ( r = quiz.drip_settings ) ||
							void 0 === r
								? void 0
								: r.date ) ||
							sn()().startOf( 'day' ).format( 'YYYY-MM-DD' )
						}
						dripTime={
							( quiz === null ||
							quiz === void 0 ||
							null === ( a = quiz.drip_settings ) ||
							void 0 === a
								? void 0
								: a.time ) || new Date()
						}
						enrollmentFromXDays={
							quiz === null ||
							quiz === void 0 ||
							null === ( o = quiz.drip_settings ) ||
							void 0 === o
								? void 0
								: o.days
						}
						isCohortBased={ Router }
						padding={ 0 }
					/>
					<Controls.DividerWP marginStart={ 4 } marginEnd={ 4 } />
					<Controls.SpacerWP marginBottom={ 3 } />
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )( 'Time Limit', 'ohmylms' ),
							description: ( 0, I18n.__ )(
								'Set a time limit for how long students have to complete the quiz.',
								'ohmylms'
							),
							className: 'ohmylms-quiz-time-limit-settings',
						},
						<Controls.FlexWP
							justify={ 'flex-end' }
							align={ 'center' }
							gap={ 2 }
						>
							<Controls.InputNumberWP
								type={ 'number' }
								min={ 0 }
								max={ 1e3 }
								value={
									d === null ||
									d === void 0 ||
									null === ( i = d.time_limit ) ||
									void 0 === i
										? void 0
										: i.value
								}
								controls={ ! 1 }
								style={ {
									width: 100,
								} }
								className={ 'ohmylms-time-limit-input' }
								onChange={ function ( e ) {
									var _tLocal7 = e;
									if ( /^\d*\.?\d*$/.test( _tLocal7 ) ) {
										C( 'time_limit', _tLocal7, 'value' );
									}
								} }
								onKeyDown={ function ( e ) {
									if (
										[
											'e',
											'E',
											'+',
											'-',
											'/',
											'\\',
											'.',
											',',
											'*',
											' ',
										].includes( e.key ) ||
										( /[a-zA-Z]/.test( e.key ) &&
											! [
												'Backspace',
												'Tab',
												'ArrowLeft',
												'ArrowRight',
												'Delete',
												'Enter',
											].includes( e.key ) )
									) {
										e.preventDefault();
									}
								} }
								onBlur={ function () {
									var e;
									if (
										( d === null ||
										d === void 0 ||
										null === ( e = d.time_limit ) ||
										void 0 === e
											? void 0
											: e.value ) < 0
									) {
										C( 'time_limit', 0, 'value' );
									}
								} }
								disabled={ ! s }
							/>
							<vn.A
								value={
									( d === null ||
									d === void 0 ||
									null === ( l = d.time_limit ) ||
									void 0 === l
										? void 0
										: l.type ) || 'minutes'
								}
								onChange={ function ( e ) {
									return C( 'time_limit', e, 'type' );
								} }
								options={ $m }
								placeholder={ ( 0, I18n.__ )(
									'Select Time Type',
									'ohmylms'
								) }
								disabled={ ! s }
								className={ 'ohmylms-time-limit-type-select' }
							/>
						</Controls.FlexWP>
					) }
					<Vm
						handleChange={ C }
						isChecked={
							d === null ||
							d === void 0 ||
							null === ( c = d.passing_grade ) ||
							void 0 === c
								? void 0
								: c.enabled
						}
						value={
							d === null ||
							d === void 0 ||
							null === ( u = d.passing_grade ) ||
							void 0 === u
								? void 0
								: u.value
						}
						maxScore={ O }
					/>
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Hide Answers On Results Page',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'Keep the correct answers hidden after quiz completion to focus students on learning.',
								'ohmylms'
							),
							className: 'ohmylms-quiz-hide-answers-settings',
						},
						<div
							style={ {
								display: 'inline-block',
								opacity: 1,
								cursor: 'pointer',
							} }
						>
							<Bt.A
								checked={
									( d === null || d === void 0
										? void 0
										: d.hide_answers ) || ! 1
								}
								onChange={ function ( e ) {
									return x( 'hide_answers', e );
								} }
								className={ 'ohmylms-hide-answers-switch' }
							/>
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Show Correct Answers On Results Page',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'After submitting, students see their answers next to the correct answers and any explanation. On by default; turn it off to keep the answers private. Unavailable while “Hide Answers On Results Page” is on.',
								'ohmylms'
							),
							className:
								'ohmylms-quiz-show-correct-answers-settings',
						},
						<div
							style={ {
								display: 'inline-block',
								opacity: 1,
								cursor: 'pointer',
							} }
						>
							<Bt.A
								checked={
									( d?.show_correct_answers ?? true ) &&
									! d?.hide_answers
								}
								disabled={ !! d?.hide_answers }
								onChange={ function ( e ) {
									return x( 'show_correct_answers', e );
								} }
								className={
									'ohmylms-show-correct-answers-switch'
								}
							/>
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Move to Next Section Without Passing Grade',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'Allow students to continue to the next section even if they don’t pass the quiz.',
								'ohmylms'
							),
							className: 'ohmylms-quiz-move-next-settings',
						},
						<div
							style={ {
								display: 'inline-block',
								opacity: 1,
								cursor: 'pointer',
							} }
						>
							<Bt.A
								checked={
									( d === null || d === void 0
										? void 0
										: d.move_to_next_section ) || ! 1
								}
								onChange={ function ( e ) {
									return x( 'move_to_next_section', e );
								} }
								className={ 'ohmylms-move-next-switch' }
							/>
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Randomize Quiz Questions',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'Shuffle the question order to create a different quiz experience each time.',
								'ohmylms'
							),
							className:
								'ohmylms-quiz-randomize-questions-settings',
						},
						<div
							style={ {
								display: 'inline-block',
								opacity: 1,
								cursor: 'pointer',
							} }
						>
							<Bt.A
								checked={
									( d === null || d === void 0
										? void 0
										: d.randomize_questions ) || ! 1
								}
								onChange={ function ( e ) {
									return x( 'randomize_questions', e );
								} }
								className={
									'ohmylms-randomize-questions-switch'
								}
							/>
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Attempts Allowed',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'Limit the number of times a student can retake the quiz for better evaluation.',
								'ohmylms'
							),
							className: 'ohmylms-quiz-attempts-allowed-settings',
						},
						<Controls.InputNumberWP
							type={ 'number' }
							min={ 0 }
							max={ 1e3 }
							value={
								d === null || d === void 0
									? void 0
									: d.allow_attempts
							}
							controls={ ! 1 }
							style={ {
								width: 100,
							} }
							onChange={ function ( e ) {
								var _tLocal8 = e;
								if ( /^\d*\.?\d*$/.test( _tLocal8 ) ) {
									x( 'allow_attempts', _tLocal8 );
								}
							} }
							onKeyDown={ function ( e ) {
								if (
									[
										'e',
										'E',
										'+',
										'-',
										'/',
										'\\',
										'.',
										',',
										'*',
										' ',
									].includes( e.key ) ||
									( /[a-zA-Z]/.test( e.key ) &&
										! [
											'Backspace',
											'Tab',
											'ArrowLeft',
											'ArrowRight',
											'Delete',
											'Enter',
										].includes( e.key ) )
								) {
									e.preventDefault();
								}
							} }
							onBlur={ function () {
								if (
									( d === null || d === void 0
										? void 0
										: d.allow_attempts ) < 0
								) {
									x( 'allow_attempts', 0 );
								}
							} }
							className={ 'ohmylms-attempts-allowed-input' }
						/>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Question Layout',
								'ohmylms'
							),
							description: ( 0, I18n.__ )(
								'Choose how your quiz questions are displayed to students.',
								'ohmylms'
							),
							isItProFeature: ! 0,
							className: 'ohmylms-quiz-layout-settings-card',
						},
						<div className={ 'ohmylms-quiz-layout-settings' }>
							<vn.A
								value={
									( d === null || d === void 0
										? void 0
										: d.layout ) || 'one_question_per_page'
								}
								onChange={ function ( e ) {
									return C( 'layout', e );
								} }
								options={ Km }
								placeholder={ ( 0, I18n.__ )(
									'Select layout',
									'ohmylms'
								) }
								showSearch={ ! 1 }
								classNames={ {
									popup: {
										root: 'ohmylms-ant-select-dropdown',
									},
								} }
								disabled={ ! s }
							/>
							{ 'number_of_questions_per_page' ===
								( d === null || d === void 0
									? void 0
									: d.layout ) && (
								<React.Fragment>
									<Controls.SpacerWP />
									<Controls.InputNumberWP
										type={ 'number' }
										min={ 0 }
										max={ 1e3 }
										value={
											d === null || d === void 0
												? void 0
												: d.question_in_one_page
										}
										controls={ ! 1 }
										style={ {
											width: 170,
											marginLeft: 'auto',
										} }
										onChange={ function ( e ) {
											var _tLocal9 = e;
											if (
												/^\d*\.?\d*$/.test( _tLocal9 )
											) {
												x(
													'question_in_one_page',
													_tLocal9
												);
											}
										} }
										onKeyDown={ function ( e ) {
											if (
												[
													'e',
													'E',
													'+',
													'-',
													'/',
													'\\',
													'.',
													',',
													'*',
													' ',
												].includes( e.key ) ||
												( /[a-zA-Z]/.test( e.key ) &&
													! [
														'Backspace',
														'Tab',
														'ArrowLeft',
														'ArrowRight',
														'Delete',
														'Enter',
													].includes( e.key ) )
											) {
												e.preventDefault();
											}
										} }
										onBlur={ function () {
											if (
												( d === null || d === void 0
													? void 0
													: d.question_in_one_page ) <
												0
											) {
												x(
													'question_in_one_page',
													value
												);
											}
										} }
										className={
											'ohmylms-questions-per-page-input'
										}
									/>
								</React.Fragment>
							) }
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Hide Question Number',
								'ohmylms'
							),
							isItProFeature: ! 0,
							className:
								'ohmylms-quiz-hide-question-number-settings',
						},
						<div
							style={ {
								display: 'inline-block',
								opacity: 1,
								cursor: 'pointer',
							} }
						>
							<Bt.A
								checked={
									( d === null || d === void 0
										? void 0
										: d.hide_question_number ) || ! 1
								}
								onChange={ function ( e ) {
									return x( 'hide_question_number', e );
								} }
								className={
									'ohmylms-hide-question-number-switch'
								}
							/>
						</div>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Set Character Limit for Short Answers',
								'ohmylms'
							),
							isItProFeature: ! 0,
							className:
								'ohmylms-quiz-short-answer-limit-settings',
						},
						<Controls.InputNumberWP
							type={ 'number' }
							min={ 0 }
							max={ 1e3 }
							placeholder={ ( 0, I18n.__ )(
								'Set Character Limit',
								'ohmylms'
							) }
							value={
								d === null || d === void 0
									? void 0
									: d.short_text_limit
							}
							controls={ ! 1 }
							style={ {
								width: 170,
							} }
							onChange={ function ( e ) {
								var _tLocal0 = e;
								if ( /^\d*\.?\d*$/.test( _tLocal0 ) ) {
									x( 'short_text_limit', _tLocal0 );
								}
							} }
							onKeyDown={ function ( e ) {
								if (
									[
										'e',
										'E',
										'+',
										'-',
										'/',
										'\\',
										'.',
										',',
										'*',
										' ',
									].includes( e.key ) ||
									( /[a-zA-Z]/.test( e.key ) &&
										! [
											'Backspace',
											'Tab',
											'ArrowLeft',
											'ArrowRight',
											'Delete',
											'Enter',
										].includes( e.key ) )
								) {
									e.preventDefault();
								}
							} }
							onBlur={ function () {
								if (
									( d === null || d === void 0
										? void 0
										: d.short_text_limit ) < 0
								) {
									x( 'short_text_limit', 0 );
								}
							} }
							disabled={ ! s }
							className={ 'ohmylms-short-answer-limit-input' }
						/>
					) }
					{ React.createElement(
						zm,
						{
							title: ( 0, I18n.__ )(
								'Set Character Limit for Long Answers',
								'ohmylms'
							),
							showDivider: ! 1,
							isItProFeature: ! 0,
							className:
								'ohmylms-quiz-long-answer-limit-settings',
						},
						<Controls.InputNumberWP
							type={ 'number' }
							min={ 0 }
							max={ 1e3 }
							placeholder={ ( 0, I18n.__ )(
								'Set Character Limit',
								'ohmylms'
							) }
							value={
								d === null || d === void 0
									? void 0
									: d.long_text_limit
							}
							controls={ ! 1 }
							style={ {
								width: 170,
							} }
							onChange={ function ( e ) {
								var _tLocal1 = e;
								if ( /^\d*\.?\d*$/.test( _tLocal1 ) ) {
									x( 'long_text_limit', _tLocal1 );
								}
							} }
							onKeyDown={ function ( e ) {
								if (
									[
										'e',
										'E',
										'+',
										'-',
										'/',
										'\\',
										'.',
										',',
										'*',
										' ',
									].includes( e.key ) ||
									( /[a-zA-Z]/.test( e.key ) &&
										! [
											'Backspace',
											'Tab',
											'ArrowLeft',
											'ArrowRight',
											'Delete',
											'Enter',
										].includes( e.key ) )
								) {
									e.preventDefault();
								}
							} }
							onBlur={ function () {
								if (
									( d === null || d === void 0
										? void 0
										: d.long_text_limit ) < 0
								) {
									x( 'long_text_limit', 0 );
								}
							} }
							disabled={ ! s }
							className={ 'ohmylms-long-answer-limit-input' }
						/>
					) }
					{ E && (
						<React.Fragment>
							<He.default isOpen={ E } onClose={ S } />
						</React.Fragment>
					) }
				</React.Fragment>
			 )
		 );
	};
}
