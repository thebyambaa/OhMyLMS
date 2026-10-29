/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createReorderEditor( readRuntime ) {
	return function ReorderEditor() {
		const {
			$d,
			$e,
			Jd,
			Qd,
			React,
			T: StoreModule,
			We,
			b: I18n,
			g: ReactHooks,
			gc,
			q,
			qd,
			xs,
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
			question = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectQuestion();
			}, [] ),
			hasValidationErrors = ( 0, WordPressData.useSelect )( function (
				e
			) {
				return e( StoreModule.default ).selectQuizzesError();
			}, [] ),
			a = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			addContentToQuestion = a.addContentToQuestion,
			updateQuestionData = a.updateQuestionData,
			l = Jd( ( 0, ReactHooks.useState )( null ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = Jd( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = function ( n, r ) {
				addContentToQuestion(
					questionId,
					r
						? options.map( function ( e ) {
								return e.id === n
									? $d(
											$d( {}, e ),
											{},
											{
												thumbnail_id: r.id,
												image_url: r.url,
											}
										)
									: e;
							} )
						: options.map( function ( e ) {
								return e.id === n
									? $d(
											$d( {}, e ),
											{},
											{
												thumbnail_id: '',
												image_url: '',
											}
										)
									: e;
							} )
				);
			},
			Router = Jd( ( 0, ReactHooks.useState )( null ), 2 ),
			v = ( Router[ 0 ], Router[ 1 ] ),
			h = function ( e ) {
				e.preventDefault();
			},
			_ = function ( e ) {
				e.currentTarget.classList.remove( 'dragging' );
			};
		return (
			<React.Fragment>
				{ ( 0, xs.I )( options ).map( function ( _aLocal2, _lLocal ) {
					return (
						<Controls.CardWP
							key={
								_aLocal2 === null || _aLocal2 === void 0
									? void 0
									: _aLocal2.id
							}
							isBorderless={ ! 0 }
							draggable={ d !== _aLocal2.id }
							onDragStart={ function ( e ) {
								return ( function ( _eLocal, t ) {
									v( t );
									localStorage.setItem(
										'draggedItemIndex',
										t
									);
									_eLocal.currentTarget.classList.add(
										'dragging'
									);
								} )( e, _lLocal );
							} }
							onDragOver={ h }
							onDrop={ function ( n ) {
								return ( function ( _nLocal, r ) {
									_nLocal.preventDefault();
									var _aLocal3 =
										localStorage.getItem(
											'draggedItemIndex'
										);
									if (
										null !== _aLocal3 &&
										String( _aLocal3 ) !== String( r )
									) {
										var i = Qd( options ),
											_lLocal2 = Jd(
												i.splice( _aLocal3, 1 ),
												1
											)[ 0 ];
										i.splice( r, 0, _lLocal2 );
										i.forEach( function ( e, t ) {
											e.order_number = t + 1;
										} );
										addContentToQuestion( questionId, i );
										v( null );
										localStorage.removeItem(
											'draggedItemIndex'
										);
									}
								} )( n, _lLocal );
							} }
							onDragEnd={ _ }
							padding={ '4px' }
							margin={ '0 0 16px' }
						>
							<Controls.FlexWP justify={ 'flex-start' } gap={ 4 }>
								{ React.createElement( gc, {
									className: 'ohmylms-drag-icon',
								} ) }
								<Controls.FlexBlockWP>
									{ React.createElement( qd, {
										id:
											_aLocal2 === null ||
											_aLocal2 === void 0
												? void 0
												: _aLocal2.id,
										showOrder: ! 0,
										orderNumber: _lLocal + 1,
										value:
											_aLocal2 === null ||
											_aLocal2 === void 0
												? void 0
												: _aLocal2.answer,
										imgSrc:
											_aLocal2 === null ||
											_aLocal2 === void 0
												? void 0
												: _aLocal2.image_url,
										onChange( n ) {
											return ( function ( _nLocal2, r ) {
												addContentToQuestion(
													questionId,
													options.map(
														function ( e ) {
															return e.id ===
																_nLocal2
																? $d(
																		$d(
																			{},
																			e
																		),
																		{},
																		{
																			answer: r,
																		}
																	)
																: e;
														}
													)
												);
											} )( _aLocal2.id, n );
										},
										onImageChange: p,
										onFocus() {
											return (
												( e = _aLocal2.id ),
												void m( e )
											 );
											var e;
										},
										onBlur() {
											return (
												_aLocal2.id,
												void m( null )
											 );
										},
										showError: hasValidationErrors,
										placeholder: ( 0, I18n.__ )(
											'Reorder Option',
											'ohmylms'
										),
										isMatching: ! 1,
									} ) }
								</Controls.FlexBlockWP>
								<Controls.ButtonWP
									icon={ <We /> }
									onClick={ function () {
										return ( function ( r ) {
											if ( 3 > options.length ) {
												return (
													u(
														( 0, I18n.__ )(
															'You must have at least 2 options',
															'ohmylms'
														)
													),
													void setTimeout(
														function () {
															u( null );
														},
														3e3
													)
												 );
											}
											var _aLocal4 = options.filter(
													function ( e ) {
														return e.id !== r;
													}
												),
												o = $d( {}, question );
											o.questions = _aLocal4;
											updateQuestionData( questionId, o );
										} )( _aLocal2.id );
									} }
								/>
							</Controls.FlexWP>
						</Controls.CardWP>
					);
				} ) }
				<Controls.SpacerWP marginY={ 4 }>
					{ c && (
						<Controls.TextWP as={ 'p' } color={ 'red' }>
							{ c }
						</Controls.TextWP>
					) }
				</Controls.SpacerWP>
				<Controls.ButtonWP
					icon={
						<q.Icon
							icon={ $e.A }
							width={ '18px' }
							height={ '18px' }
						/>
					}
					onClick={ function () {
						var r = $d( {}, question ),
							_aLocal5 = {
								id: Date.now(),
								answer: '',
								is_correct: ! 1,
								order_number: options.length + 1,
								temp: ! 0,
								thumbnail_id: '',
								image_url: '',
								matching_data: {
									label: '',
									image_id: '',
									image_url: '',
								},
							};
						r.questions = [].concat( Qd( r.questions ), [
							$d( {}, _aLocal5 ),
						] );
						updateQuestionData( questionId, r );
						addContentToQuestion(
							questionId,
							[].concat( Qd( options ), [ $d( {}, _aLocal5 ) ] )
						);
					} }
					variant={ 'secondary' }
					size={ 'small' }
				>
					{ ( 0, I18n.__ )( 'Add Option', 'ohmylms' ) }
				</Controls.ButtonWP>
			</React.Fragment>
		);
	};
}
