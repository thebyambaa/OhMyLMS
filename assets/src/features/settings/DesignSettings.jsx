/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { createThemeSettings } from './ThemeSettings';
import { createQuizDesignSettings } from './QuizDesignSettings';
export function createDesignSettings( readRuntime ) {
	return function DesignSettings( props ) {
		const {
			B0,
			D0,
			GK,
			I: Controls,
			JJ,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			W0,
			b: I18n,
			ep,
			f: Router,
			g: ReactHooks,
			k0,
			l,
			y: WordPressData,
			zK,
		} = readRuntime();
		var t = props.activeTab,
			n = props.handleSave,
			r = props.handleMigration,
			a = props.selectedCourses,
			o = props.isSaving,
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = ( 0, Router.g )(),
			u = c.tab,
			s = c.subTab,
			d = c.subPanel,
			m = ( 0, Router.Zp )(),
			ThemeSettings = ( 0, ReactHooks.useMemo )( function () {
				return createThemeSettings( {
					store: StoreModule.default,
					presets: GK,
					ColorCards: zK,
					Controls,
				} );
			}, [] ),
			p = ( 0, ReactHooks.useMemo )( function () {
				const QuizDesignSettings = createQuizDesignSettings(
					StoreModule.default
				);
				return [
					{
						label: ( 0, I18n.__ )(
							'Quiz & Question Editor',
							'ohmylms'
						),
						key: 'quiz-editor',
						children: React.createElement( QuizDesignSettings ),
					},
					{
						label: ( 0, I18n.__ )(
							'Course Listing Page',
							'ohmylms'
						),
						key: 'course-list',
						children: <JJ />,
					},
					{
						label: ( 0, I18n.__ )(
							'Course Details Page',
							'ohmylms'
						),
						key: 'course-details',
						children: React.createElement( k0, null ),
					},
					{
						label: ( 0, I18n.__ )( 'Checkout Page', 'ohmylms' ),
						key: 'checkout-page',
						children: <D0 />,
					},
					{
						label: ( 0, I18n.__ )( 'Colors & fonts', 'ohmylms' ),
						key: 'colors-fonts',
						children: <ThemeSettings />,
					},
				];
			}, [] );
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ( function () {
					var _eLocal,
						_tLocal =
							( ( _eLocal = W0().m( function _eLocal2() {
								var _tLocal2;
								return W0().w( function ( _eLocal3 ) {
									for (;;) {
										switch ( _eLocal3.n ) {
											case 0:
												return (
													i.setLoadingSetting( ! 0 ),
													( _eLocal3.n = 1 ),
													l()( {
														path: 'ohmylms/v1/settings/design',
													} )
												 );
											case 1:
												_tLocal2 = _eLocal3.v;
												i.setDesignSettings( _tLocal2 );
												i.setLoadingSetting( ! 1 ); // Falls through to the shared async state cleanup.
											case 2:
												return _eLocal3.a( 2 );
										}
									}
								}, _eLocal2 );
							} ) ),
							function ( ..._args ) {
								var _tLocal3 = this,
									_nLocal = _args;
								return new Promise( function (
									_rLocal,
									_aLocal
								) {
									var _oLocal = _eLocal.apply(
										_tLocal3,
										_nLocal
									);
									function _iLocal( _eLocal4 ) {
										B0(
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
										B0(
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
						return _tLocal.apply( this, _args2 );
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
							paddingTop={ 2 }
							marginTop={ 4 }
							marginBottom={ 0 }
						>
							<ep.A
								items={ p }
								onChange={ function ( e ) {
									var _tLocal4 = '/settings/'
										.concat( u, '/' )
										.concat( e );
									m(
										'course-details' === e
											? ''
													.concat( _tLocal4, '/' )
													.concat(
														d !== null &&
															d !== void 0
															? d
															: 'unenroll-view'
													)
											: _tLocal4
									);
								} }
								activekey={ s }
							/>
							<Controls.SpacerWP
								marginBottom={ 0 }
								marginTop={ 4 }
							>
								{ s && 'quiz-editor' !== s && (
									<MemoSettingsActionBar
										activeTab={ t }
										handleSave={ n }
										handleMigration={ r }
										selectedCourses={ a }
										isSaving={ o }
									/>
								) }
							</Controls.SpacerWP>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
