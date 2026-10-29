/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Tooltip } from '../../shared/tooltip';
import * as Controls from '../../shared/controls';
export function createTrueFalseEditor( readRuntime ) {
	return function TrueFalseEditor() {
		const {
			React,
			T: StoreModule,
			Xs,
			b: I18n,
			y: WordPressData,
		} = readRuntime();
		var questionId = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectSelectedQuestionId();
			}, [] ),
			options = ( 0, WordPressData.useSelect )(
				function ( e ) {
					return e( StoreModule.default ).getQuestionContents();
				},
				[ questionId ]
			),
			hasValidationErrors = ( 0, WordPressData.useSelect )( function (
				e
			) {
				return e( StoreModule.default ).selectQuizzesError();
			}, [] ),
			r = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			addContentToQuestion = r.addContentToQuestion;
		return (
			r.updateQuestion,
			(
				<React.Fragment>
					<div
						className={
							'ohmylms-options-list ohmylms-options-list-true-false'
						}
					>
						{ options.map( function ( _rLocal, o ) {
							var i, l;
							return (
								<div
									className={ 'ohmylms-option-item-wrapper ohmylms-quiz-option-item ohmylms-quiz-option-item-'.concat(
										o
									) }
									key={
										( _rLocal === null || _rLocal === void 0
											? void 0
											: _rLocal.id ) || o
									}
								>
									<Controls.CardWP
										isBorderless={ ! 0 }
										className={ 'ohmylms-option-item '.concat(
											Number(
												_rLocal === null ||
													_rLocal === void 0
													? void 0
													: _rLocal.is_correct
											) === 1
												? 'ohmylms-option-item--correct'
												: ''
										) }
									>
										<Controls.FlexWP>
											<Tooltip
												title={ ( 0, I18n.__ )(
													'Mark as correct answer',
													'ohmylms'
												) }
											>
												<Controls.RadioWP
													onChange={ function () {
														return (
															( n =
																_rLocal ===
																	null ||
																_rLocal ===
																	void 0
																	? void 0
																	: _rLocal.id ),
															void addContentToQuestion(
																questionId,
																options.map(
																	function (
																		e
																	) {
																		return Xs(
																			Xs(
																				{},
																				e
																			),
																			{},
																			{
																				is_correct:
																					e.id ===
																					n,
																			}
																		);
																	}
																)
															)
														 );
														var n;
													} }
													selected={ ( () => {
														if (
															Number(
																_rLocal ===
																	null ||
																	_rLocal ===
																		void 0
																	? void 0
																	: _rLocal.is_correct
															) === 1
														) {
															if (
																_rLocal ===
																	null ||
																_rLocal ===
																	void 0
															) {
																return void 0;
															}
															return _rLocal.answer;
														}
														return null;
													} )() }
													options={ [
														{
															level: '',
															value:
																_rLocal ===
																	null ||
																_rLocal ===
																	void 0
																	? void 0
																	: _rLocal.answer,
														},
													] }
												/>
											</Tooltip>
											<Controls.FlexItemWP
												isBlock={ ! 0 }
											>
												<Controls.InputWP
													placeholder={ ( 0,
													I18n.__ )(
														'Option',
														'ohmylms'
													) }
													value={
														_rLocal === null ||
														_rLocal === void 0
															? void 0
															: _rLocal.answer
													}
													readOnly={ ! 0 }
													style={ {
														width: '100%',
													} }
												/>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</Controls.CardWP>
									{ ! hasValidationErrors ||
									( _rLocal !== null &&
										_rLocal !== void 0 &&
										null !== ( i = _rLocal.answer ) &&
										void 0 !== i &&
										i.trim() ) ? (
										<React.Fragment />
									) : (
										<div
											className={
												'ohmylms-option-correct'
											}
											style={ {
												color: 'red',
												marginTop: 4,
											} }
										>
											{ ( 0, I18n.__ )(
												'Field cannot be empty',
												'ohmylms'
											) }
										</div>
									) }
									{ Number( _rLocal.is_correct ) === 1 &&
										Boolean(
											_rLocal === null ||
												_rLocal === void 0 ||
												null ===
													( l = _rLocal.answer ) ||
												void 0 === l
												? void 0
												: l.trim()
										) && (
											<span
												className={
													'ohmylms-option-correct'
												}
											>
												{ ( 0, I18n.__ )(
													'This answer is correct',
													'ohmylms'
												) }
											</span>
										) }
								</div>
							);
						} ) }
						{ hasValidationErrors &&
							! options.some( function ( e ) {
								return Number( e.is_correct ) === 1;
							} ) && (
								<p className={ 'ohmylms-option-error-msg' }>
									{ ( 0, I18n.__ )(
										'Please select at least one correct answer',
										'ohmylms'
									) }
								</p>
							) }
					</div>
				</React.Fragment>
			 )
		 );
	};
}
