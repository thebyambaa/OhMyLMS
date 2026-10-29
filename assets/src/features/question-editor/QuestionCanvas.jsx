/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { removeQuestionFromQuiz } from '../quiz-editor/api.mjs';
import { QuestionForm } from './QuestionForm';
import { questionWorkspaceKey } from './choiceModel.mjs';
import { QUESTION_BLOCK_TYPES, questionTypePatch } from './questionBlocks.mjs';
import * as Controls from '../../shared/controls';
export function createQuestionCanvas( readRuntime ) {
	return function QuestionCanvas( props ) {
		const {
			Eu,
			Ie,
			Lc,
			Pu,
			React,
			Ru,
			T: StoreModule,
			Tu,
			b: I18n,
			du,
			fc,
			g: ReactHooks,
			ju,
			lu,
			nu,
			tu,
			y: WordPressData,
		} = readRuntime();
		const { useSelect, useDispatch } = WordPressData;
		const { useState } = ReactHooks;
		const previewOptions = useSelect(
			( select ) => select( StoreModule.default ).getQuestionContents(),
			[ StoreModule.default ]
		);
		var t;
		var n;
		var r;
		var a;
		const _questionSelection = useSelect(
			function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getQuizTypes();
			},
			[ StoreModule.default ]
		);
		const _questionSelection2 = useSelect(
			function ( _eLocal2 ) {
				return _eLocal2(
					StoreModule.default
				).getInteractiveQuizTypes();
			},
			[ StoreModule.default ]
		);
		const _questionSelection3 = useSelect(
			function ( _eLocal3 ) {
				return _eLocal3( StoreModule.default ).selectQuestion();
			},
			[ StoreModule.default ]
		);
		var question = ( function () {
			if ( ! _questionSelection3 ) {
				return {
					edit: tu,
					showDefault: ! 0,
				};
			}
			var _aLocal = _questionSelection3.settings;
			if ( _aLocal === null || _aLocal === void 0 || ! _aLocal.type ) {
				return {
					edit: tu,
					showDefault: ! 0,
				};
			}
			var _nLocal = [].concat(
				nu( _questionSelection ),
				nu( _questionSelection2 )
			);
			var o = _nLocal.find( function ( _eLocal4 ) {
				return (
					( _eLocal4 === null || _eLocal4 === void 0
						? void 0
						: _eLocal4.type ) ===
					( _aLocal === null || _aLocal === void 0
						? void 0
						: _aLocal.type )
				);
			} );
			return o
				? {
						edit: o.edit,
						showDefault: ! 1,
					}
				: {
						edit: tu,
						showDefault: ! 0,
					};
		} )();
		var edit = question.edit;
		var showDefault = question.showDefault;
		var s = useDispatch( StoreModule.default );
		var quizId = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getSelectedQuizId();
			},
			[ StoreModule.default ]
		);
		var m =
			( useSelect(
				function ( e ) {
					return e( StoreModule.default ).getQuiz();
				},
				[ StoreModule.default ]
			),
			useSelect(
				function ( e ) {
					return e( StoreModule.default ).selectSelectedQuestionId();
				},
				[ StoreModule.default ]
			),
			useSelect(
				function ( e ) {
					return e( StoreModule.default ).selectQuestion();
				},
				[ StoreModule.default ]
			) );
		var p =
			( useSelect(
				function ( e ) {
					return e( StoreModule.default ).getCourseChaptersContent();
				},
				[ StoreModule.default ]
			),
			useSelect(
				function ( e ) {
					return e( StoreModule.default ).getAllQuestions();
				},
				[ StoreModule.default ]
			) );
		var Router = useSelect(
			function ( e ) {
				return e( StoreModule.default ).getQuestionErrors();
			},
			[ StoreModule.default ]
		);
		var v = useState( ! 1 );
		var h = v[ 0 ];
		var _ = v[ 1 ];
		var w =
			( Lc().isValidQuestion,
			useSelect(
				function ( e ) {
					var _tLocal5;
					var _nLocal4;
					var _rLocal3;
					var _aLocal3;
					var o;
					var _iLocal2;
					var l;
					var c;
					return {
						question:
							null ===
								( _tLocal5 = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === _tLocal5
								? void 0
								: _tLocal5.name,
						videoSrc:
							null ===
								( _nLocal4 = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === _nLocal4
								? void 0
								: _nLocal4.video_src,
						imgSrc:
							null ===
								( _rLocal3 = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === _rLocal3
								? void 0
								: _rLocal3.image_src,
						options:
							null ===
								( _aLocal3 = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === _aLocal3
								? void 0
								: _aLocal3.options,
						correctAnswerId:
							null ===
								( o = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === o
								? void 0
								: o.correctAnswerId,
						id:
							null ===
								( _iLocal2 = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === _iLocal2
								? void 0
								: _iLocal2.id,
						quizType:
							null ===
								( l = e(
									StoreModule.default
								).selectQuestion() ) ||
							void 0 === l ||
							null === ( l = l.settings ) ||
							void 0 === l
								? void 0
								: l.type,
						description:
							null ===
								( c = e(
									StoreModule.default
								).selectQuestion() ) || void 0 === c
								? void 0
								: c.description,
					};
				},
				[ StoreModule.default ]
			) );
		var E = w.question;
		var videoSrc = w.videoSrc;
		var imgSrc = w.imgSrc;
		var x = ( w.options, w.correctAnswerId, w.id );
		var quizType = w.quizType;
		var description = w.description;
		var O = fc( quizType );
		var k =
			( ( t = quizType ),
			( n = useSelect(
				function ( e ) {
					return e( StoreModule.default ).getQuizTypes();
				},
				[ StoreModule.default ]
			) ),
			( r = useSelect(
				function ( e ) {
					return e( StoreModule.default ).getInteractiveQuizTypes();
				},
				[ StoreModule.default ]
			) ),
			( a = [].concat( Eu( n ), Eu( r ) ) ),
			t
				? a.find( function ( e ) {
						return e.type === t;
					} )
				: null );
		const _questionSelection4 = useSelect(
			function ( _eLocal9 ) {
				return _eLocal9( StoreModule.default ).getQuizTypes();
			},
			[ StoreModule.default ]
		);
		const _questionSelection5 = useSelect(
			function ( _eLocal0 ) {
				return _eLocal0(
					StoreModule.default
				).getInteractiveQuizTypes();
			},
			[ StoreModule.default ]
		);
		var j = ( function ( e ) {
			if ( ! e ) {
				return null;
			}
			var _rLocal4 = [].concat(
				Ru( _questionSelection4 ),
				Ru( _questionSelection5 )
			);
			var _aLocal4 = _rLocal4.find( function ( _tLocal7 ) {
				return _tLocal7.type === e;
			} );
			return (
				( _aLocal4 === null || _aLocal4 === void 0
					? void 0
					: _aLocal4.otherContent ) || null
			);
		} )( quizType );
		var M = ( function () {
			var e = Tu(
				Pu().m( function _eLocal1() {
					var _tLocal8;
					return Pu().w( function ( _eLocal10 ) {
						for (;;) {
							switch ( _eLocal10.n ) {
								case 0:
									if ( quizId ) {
										_eLocal10.n = 1;
										break;
									}
									return _eLocal10.a( 2 );
								case 1: {
									_eLocal10.n = 2;
									break;
								}
								case 2:
									( _tLocal8 = ju( {}, m ) ).order_number =
										p.length + 1;
									_tLocal8.id = new Date().getTime();
									_tLocal8.temp = ! 0;
									s.setQuestion( _tLocal8 );
									s.setQuestions( _tLocal8 );
									s.setSelectedQuestionId( _tLocal8.id );
								// Falls through to the shared async state cleanup.
								// Falls through to the shared async state cleanup.
								case 3:
									return _eLocal10.a( 2 );
							}
						}
					}, _eLocal1 );
				} )
			);
			return function ( ..._args ) {
				return e.apply( this, _args );
			};
		} )();
		var F = async function () {
			// Deleting from the quiz editor removes the question from this quiz only;
			// the question and its learner history stay in the question bank.
			if ( m !== null && m !== void 0 && ! m.temp ) {
				if ( ! quizId ) {
					await s.deleteQuestion( m.id );
					_( ! 1 );
					return;
				}
				try {
					await removeQuestionFromQuiz( quizId, m.id );
				} catch ( cause ) {
					s.showNotification?.(
						( cause && cause.message ) ||
							( 0, I18n.__ )(
								'Could not remove the question.',
								'ohmylms'
							),
						'error'
					);
					_( ! 1 );
					return;
				}
			}
			s.deleteTempQuestion( m === null || m === void 0 ? void 0 : m.id );
			_( ! 1 );
		};
		const answerControls = (
			<React.Fragment>
				{ ! showDefault && React.createElement( edit, null ) }
				{ j && React.createElement( j, null ) }
			</React.Fragment>
		);
		return (
			<React.Fragment>
				<QuestionForm
					key={ questionWorkspaceKey( m?.id, quizType ) }
					question={ {
						...m,
						name: E === 'Untitled' ? '' : E,
						description: description || '',
						questions: previewOptions || [],
					} }
					label={ ( 0, I18n.__ )( 'Question', 'ohmylms' ) }
					titleLabel={ ( 0, I18n.__ )( 'Question title', 'ohmylms' ) }
					titlePlaceholder={ ( 0, I18n.__ )(
						'Type your question here ...',
						'ohmylms'
					) }
					workspaceLabel={ ( 0, I18n.__ )(
						'Question form editor',
						'ohmylms'
					) }
					compact={ props.formCard }
					readOnly={ !! m?.readonly }
					onChange={ ( patch ) => {
						if ( m?.readonly ) {
							return;
						}
						const { questions, ...fields } = patch;
						if ( questions ) {
							s.addContentToQuestion( x, questions );
						}
						if ( Object.keys( fields ).length ) {
							s.updateQuestionData( x, fields );
						}
					} }
					types={ QUESTION_BLOCK_TYPES.map(
						( [ value, label ] ) => ( {
							value,
							label,
						} )
					) }
					onTypeChange={ ( type ) => {
						if ( m?.readonly ) {
							return;
						}
						const patch = questionTypePatch( m, type );
						if ( patch.description !== undefined ) {
							s.updateQuestionData( x, {
								description: patch.description,
							} );
						}
						if ( patch.name ) {
							s.updateQuestionData( x, {
								name: patch.name,
							} );
						}
						if ( patch.settings ) {
							s.updateQuestionData( x, {
								settings: patch.settings,
							} );
						}
						if ( patch.questions ) {
							s.addContentToQuestion( x, patch.questions );
						}
					} }
					questionType={ quizType }
					previewQuestion={ {
						...m,
						questions: previewOptions || [],
					} }
					toolbarActions={ props.toolbarActions }
					toolbarLeading={ props.toolbarLeading }
					settings={ props.settings }
					extraSettings={ props.extraSettings }
					outline={ props.outline }
					beforeContent={
						<React.Fragment>
							{ ! props.formCard && (
								<Controls.FlexWP
									align="center"
									justify="space-between"
								>
									{ React.createElement( lu, {
										icon: O,
										label: k?.name,
										iconColor:
											'var(--ohmylms-primary-color)',
									} ) }
									{ ! m?.readonly &&
										React.createElement( du, {
											handleCopy: M,
											handleDelete: () => _( ! 0 ),
										} ) }
								</Controls.FlexWP>
							) }
							{ Router?.name && (
								<p role="alert">
									{ ( 0, I18n.__ )(
										'Enter the question.',
										'ohmylms'
									) }
								</p>
							) }
							{ imgSrc && (
								<img
									className="ohmylms-question-legacy-media"
									src={ imgSrc }
									alt={ ( 0, I18n.__ )(
										'Question image',
										'ohmylms'
									) }
								/>
							) }
							{ videoSrc && (
								<video
									className="ohmylms-question-legacy-media"
									src={ videoSrc }
									controls
								/>
							) }
						</React.Fragment>
					}
					answers={
						QUESTION_BLOCK_TYPES.some(
							( [ type ] ) => type === quizType
						)
							? undefined
							: answerControls
					}
				/>
				{ h && (
					<Ie
						title={ ( 0, I18n.__ )( 'Delete Question', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'Are you sure you want to delete this question?',
							'ohmylms'
						) }
						onClose={ () => _( ! 1 ) }
						onDelete={ F }
						isOpen={ h }
						wrapClassName="ohmylms-delete-question-modal"
						isDelete={ ! 0 }
					/>
				) }
			</React.Fragment>
		);
	};
}
