/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createPermalinkSettings( readRuntime ) {
	return function PermalinkSettings( props ) {
		const {
			Pf,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			aJ,
			b: I18n,
			eJ,
			g: ReactHooks,
			l,
			nJ,
			oJ,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			c,
			u,
			s = props.activeTab,
			d = props.handleSave,
			m = props.handleMigration,
			p = props.selectedCourses,
			f = props.isSaving,
			v = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			h = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getPermalinkSettings();
			}, [] ),
			_ = function ( e, _tLocal ) {
				var _nLocal;
				v.updatePermalinkSettings( {
					ohmylms_permalink: {
						value: aJ(
							aJ(
								{},
								h === null ||
									h === void 0 ||
									null ===
										( _nLocal = h.ohmylms_permalink ) ||
									void 0 === _nLocal
									? void 0
									: _nLocal.value
							),
							{},
							oJ( {}, _tLocal, e )
						),
					},
				} );
			},
			w = function ( e ) {
				return String( e )
					.toLowerCase()
					.trim()
					.replace( /[\s_]+/g, '-' )
					.replace( /[^\w\-]+/g, '' )
					.replace( /\-\-+/g, '-' );
			};
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ( function () {
					var _eLocal,
						_tLocal2 =
							( ( _eLocal = eJ().m( function _eLocal2() {
								var _tLocal3;
								return eJ().w( function ( _eLocal3 ) {
									for (;;) {
										switch ( _eLocal3.n ) {
											case 0:
												return (
													v.setLoadingSetting( ! 0 ),
													( _eLocal3.n = 1 ),
													l()( {
														path: 'ohmylms/v1/settings/permalink',
													} )
												 );
											case 1:
												_tLocal3 = _eLocal3.v;
												v.setPermalinkSettings(
													_tLocal3
												);
												v.setLoadingSetting( ! 1 ); // Falls through to the shared async state cleanup.
											case 2:
												return _eLocal3.a( 2 );
										}
									}
								}, _eLocal2 );
							} ) ),
							function ( ..._args ) {
								var _tLocal4 = this,
									_nLocal2 = _args;
								return new Promise( function (
									_rLocal,
									_aLocal
								) {
									var _oLocal = _eLocal.apply(
										_tLocal4,
										_nLocal2
									);
									function _iLocal( _eLocal4 ) {
										nJ(
											_oLocal,
											_rLocal,
											_aLocal,
											_iLocal,
											_lLocal,
											'next',
											_eLocal4
										);
									}
									function _lLocal( _eLocal5 ) {
										nJ(
											_oLocal,
											_rLocal,
											_aLocal,
											_iLocal,
											_lLocal,
											'throw',
											_eLocal5
										);
									}
									_iLocal( void 0 );
								} );
							} );
					return function ( ..._args2 ) {
						return _tLocal2.apply( this, _args2 );
					};
				} )();
				e();
			}, [] ),
			(
				<React.Fragment>
					<Controls.CardWP
						isBorderless={ ! 0 }
						variant={ 'secondary' }
						className={ 'ohmylms-full-screen-height' }
					>
						<Controls.SpacerWP
							padding={ 4 }
							marginTop={ 0 }
							marginBottom={ 0 }
						>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									paddingY={ 4 }
									paddingX={ 2 }
									marginTop={ 0 }
									marginBottom={ 4 }
								>
									<Pf
										title={ ( 0, I18n.__ )(
											'Course Base',
											'ohmylms'
										) }
										description={ 'https://yoursite.com/<code style="background: #27BDFE4D; font-style: italic;">{'.concat(
											h === null ||
												h === void 0 ||
												null ===
													( t =
														h.ohmylms_permalink ) ||
												void 0 === t ||
												null === ( t = t.value ) ||
												void 0 === t
												? void 0
												: t.course_base,
											'}</code>/sample-course'
										) }
										inputType={ 'text' }
										value={
											( h === null ||
											h === void 0 ||
											null ===
												( n = h.ohmylms_permalink ) ||
											void 0 === n ||
											null === ( n = n.value ) ||
											void 0 === n
												? void 0
												: n.course_base ) || ''
										}
										onChange={ function ( e ) {
											return _( w( e ), 'course_base' );
										} }
										isDescriptionHTML={ ! 0 }
									/>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 2 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										<Controls.DividerWP
											color={ '#EDF2FB' }
										/>
									</Controls.SpacerWP>
									<Pf
										title={ ( 0, I18n.__ )(
											'Category Base',
											'ohmylms'
										) }
										description={ 'https://yoursite.com/courses/<code style="background: #27BDFE4D; font-style: italic;">{'.concat(
											h === null ||
												h === void 0 ||
												null ===
													( r =
														h.ohmylms_permalink ) ||
												void 0 === r ||
												null === ( r = r.value ) ||
												void 0 === r
												? void 0
												: r.category_base,
											'}</code>/sample-category/'
										) }
										inputType={ 'text' }
										value={
											( h === null ||
											h === void 0 ||
											null ===
												( a = h.ohmylms_permalink ) ||
											void 0 === a ||
											null === ( a = a.value ) ||
											void 0 === a
												? void 0
												: a.category_base ) || ''
										}
										onChange={ function ( e ) {
											return _( w( e ), 'category_base' );
										} }
										isDescriptionHTML={ ! 0 }
									/>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 2 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										<Controls.DividerWP
											color={ '#EDF2FB' }
										/>
									</Controls.SpacerWP>
									<Pf
										title={ ( 0, I18n.__ )(
											'Lesson Base',
											'ohmylms'
										) }
										description={ 'https://yoursite.com/courses/sample-course/<code style="background: #27BDFE4D; font-style: italic;">{'.concat(
											h === null ||
												h === void 0 ||
												null ===
													( o =
														h.ohmylms_permalink ) ||
												void 0 === o ||
												null === ( o = o.value ) ||
												void 0 === o
												? void 0
												: o.lesson_base,
											'}</code>/sample-lesson/'
										) }
										inputType={ 'text' }
										value={
											( h === null ||
											h === void 0 ||
											null ===
												( i = h.ohmylms_permalink ) ||
											void 0 === i ||
											null === ( i = i.value ) ||
											void 0 === i
												? void 0
												: i.lesson_base ) || ''
										}
										onChange={ function ( e ) {
											return _( w( e ), 'lesson_base' );
										} }
										isDescriptionHTML={ ! 0 }
									/>
									<Controls.SpacerWP
										paddingX={ 4 }
										paddingTop={ 2 }
										paddingBottom={ 4 }
										marginBottom={ 0 }
									>
										<Controls.DividerWP
											color={ '#EDF2FB' }
										/>
									</Controls.SpacerWP>
									<Pf
										title={ ( 0, I18n.__ )(
											'Quiz Base',
											'ohmylms'
										) }
										description={ ' https://yoursite.com/courses/sample-course/<code style="background: #27BDFE4D; font-style: italic;">{'.concat(
											h === null ||
												h === void 0 ||
												null ===
													( c =
														h.ohmylms_permalink ) ||
												void 0 === c ||
												null === ( c = c.value ) ||
												void 0 === c
												? void 0
												: c.quiz_base,
											'}</code>/sample-quiz/'
										) }
										inputType={ 'text' }
										value={
											( h === null ||
											h === void 0 ||
											null ===
												( u = h.ohmylms_permalink ) ||
											void 0 === u ||
											null === ( u = u.value ) ||
											void 0 === u
												? void 0
												: u.quiz_base ) || ''
										}
										onChange={ function ( e ) {
											return _( w( e ), 'quiz_base' );
										} }
										isDescriptionHTML={ ! 0 }
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
							<MemoSettingsActionBar
								activeTab={ s }
								handleSave={ d }
								handleMigration={ m }
								selectedCourses={ p }
								isSaving={ f }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
