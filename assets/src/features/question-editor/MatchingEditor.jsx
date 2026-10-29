/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createMatchingEditor( readRuntime ) {
	return function MatchingEditor() {
		const {
			$e,
			React,
			T: StoreModule,
			We,
			b: I18n,
			dm,
			g: ReactHooks,
			gc,
			pm,
			q,
			qd,
			um,
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
			l = pm( ( 0, ReactHooks.useState )( null ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = pm( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = function ( n, r, _aLocal ) {
				addContentToQuestion(
					questionId,
					_aLocal
						? options.map( function ( e ) {
								return e.id === n
									? dm(
											dm( {}, e ),
											{},
											{
												matching_data: dm(
													dm( {}, e.matching_data ),
													{},
													{
														label: r,
													}
												),
											}
										)
									: e;
							} )
						: options.map( function ( e ) {
								return e.id === n
									? dm(
											dm( {}, e ),
											{},
											{
												answer: r,
											}
										)
									: e;
							} )
				);
			},
			Router = function ( n, r, _aLocal2 ) {
				addContentToQuestion(
					questionId,
					options.map( function ( e ) {
						return e.id !== n
							? e
							: dm(
									dm( {}, e ),
									{},
									_aLocal2
										? {
												matching_data: dm(
													dm( {}, e.matching_data ),
													{},
													{
														image_id: r ? r.id : '',
														image_url: r
															? r.url
															: '',
													}
												),
											}
										: {
												thumbnail_id: r ? r.id : '',
												image_url: r ? r.url : '',
											}
								);
					} )
				);
			},
			v = pm( ( 0, ReactHooks.useState )( null ), 2 ),
			h = ( v[ 0 ], v[ 1 ] ),
			_ = function ( e ) {
				e.preventDefault();
			},
			w = function ( e ) {
				e.currentTarget.classList.remove( 'dragging' );
			},
			E = function ( e ) {
				m( e );
			},
			S = function () {
				m( null );
			};
		return (
			<React.Fragment>
				<Controls.FlexWP
					style={ {
						padding: '10px 60px 10px 30px',
					} }
				>
					<Controls.FlexBlockWP>
						<Controls.TextWP
							as={ 'p' }
							width={ '500' }
							size={ '16px' }
						>
							{ ( 0, I18n.__ )( 'Match Item', 'ohmylms' ) }
						</Controls.TextWP>
					</Controls.FlexBlockWP>
					<Controls.FlexBlockWP>
						<Controls.TextWP
							as={ 'p' }
							width={ '500' }
							size={ '16px' }
						>
							{ ( 0, I18n.__ )(
								'Matching definition',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexBlockWP>
				</Controls.FlexWP>
				{ ( 0, xs.I )( options ).map( function ( _aLocal3, _lLocal ) {
					var _cLocal, _sLocal;
					return (
						<Controls.CardWP
							key={ _aLocal3.id }
							isBorderless={ ! 0 }
							draggable={ d !== _aLocal3.id }
							onDragStart={ function ( e ) {
								return ( function ( _eLocal, t ) {
									h( t );
									localStorage.setItem(
										'draggedItemIndex',
										t
									);
									_eLocal.currentTarget.classList.add(
										'dragging'
									);
								} )( e, _lLocal );
							} }
							onDragOver={ _ }
							onDrop={ function ( n ) {
								return ( function ( _nLocal, r ) {
									_nLocal.preventDefault();
									var _aLocal4 =
										localStorage.getItem(
											'draggedItemIndex'
										);
									if (
										null !== _aLocal4 &&
										String( _aLocal4 ) !== String( r )
									) {
										var i = um( options ),
											_lLocal2 = pm(
												i.splice( _aLocal4, 1 ),
												1
											)[ 0 ];
										i.splice( r, 0, _lLocal2 );
										i.forEach( function ( e, t ) {
											e.order_number = t + 1;
										} );
										addContentToQuestion( questionId, i );
										h( null );
										localStorage.removeItem(
											'draggedItemIndex'
										);
									}
								} )( n, _lLocal );
							} }
							onDragEnd={ w }
							padding={ '4px' }
							margin={ '0 0 16px' }
						>
							<Controls.FlexWP justify={ 'flex-start' } gap={ 4 }>
								{ React.createElement( gc, {
									className: 'ohmylms-drag-icon',
								} ) }
								<Controls.FlexBlockWP>
									<Controls.CardWP>
										<Controls.FlexWP>
											<Controls.FlexBlockWP>
												{ React.createElement( qd, {
													id:
														_aLocal3 === null ||
														_aLocal3 === void 0
															? void 0
															: _aLocal3.id,
													value:
														_aLocal3 === null ||
														_aLocal3 === void 0
															? void 0
															: _aLocal3.answer,
													imgSrc:
														_aLocal3 === null ||
														_aLocal3 === void 0
															? void 0
															: _aLocal3.image_url,
													onChange( e ) {
														return p(
															_aLocal3.id,
															e,
															! 1
														);
													},
													onImageChange: Router,
													onFocus() {
														return E( _aLocal3.id );
													},
													onBlur() {
														return S( _aLocal3.id );
													},
													showError:
														hasValidationErrors,
													isBorderless: ! 0,
													placeholder: ( 0, I18n.__ )(
														'Match Item',
														'ohmylms'
													),
													isMatching: ! 1,
												} ) }
											</Controls.FlexBlockWP>
											<Controls.DividerWP
												orientation={ 'vertical' }
												style={ {
													width: '1px',
													height: '56px',
													borderColor: '#e8e8e8',
												} }
											/>
											<Controls.FlexBlockWP>
												{ React.createElement( qd, {
													id:
														_aLocal3 === null ||
														_aLocal3 === void 0
															? void 0
															: _aLocal3.id,
													value:
														_aLocal3 === null ||
														_aLocal3 === void 0 ||
														null ===
															( _cLocal =
																_aLocal3.matching_data ) ||
														void 0 === _cLocal
															? void 0
															: _cLocal.label,
													imgSrc:
														_aLocal3 === null ||
														_aLocal3 === void 0 ||
														null ===
															( _sLocal =
																_aLocal3.matching_data ) ||
														void 0 === _sLocal
															? void 0
															: _sLocal.image_url,
													onChange( e ) {
														return p(
															_aLocal3.id,
															e,
															! 0
														);
													},
													onImageChange( e, t ) {
														return Router(
															e,
															t,
															! 0
														);
													},
													onFocus() {
														return E( _aLocal3.id );
													},
													onBlur() {
														return S( _aLocal3.id );
													},
													showError:
														hasValidationErrors,
													isBorderless: ! 0,
													placeholder: ( 0, I18n.__ )(
														'Matching definition',
														'ohmylms'
													),
													isMatching: ! 0,
												} ) }
											</Controls.FlexBlockWP>
										</Controls.FlexWP>
									</Controls.CardWP>
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
											var _aLocal5 = options.filter(
													function ( e ) {
														return e.id !== r;
													}
												),
												o = dm( {}, question );
											o.questions = _aLocal5;
											updateQuestionData( questionId, o );
										} )( _aLocal3.id );
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
						var r = dm( {}, question ),
							_aLocal6 = {
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
						r.questions = [].concat( um( r.questions ), [
							dm( {}, _aLocal6 ),
						] );
						updateQuestionData( questionId, r );
						addContentToQuestion(
							questionId,
							[].concat( um( options ), [ dm( {}, _aLocal6 ) ] )
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
