/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createGeneralSettings( readRuntime ) {
	return function GeneralSettings( props ) {
		const {
			CK,
			PK,
			RK,
			React,
			SK: MemoSettingsActionBar,
			T: StoreModule,
			g: ReactHooks,
			l,
			wK: MemoGeneralSettingsFields,
			y: WordPressData,
		} = readRuntime();
		var t = props.activeTab,
			n = props.handleSave,
			r = props.handleMigration,
			a = props.selectedCourses,
			o = props.isSaving,
			i = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = PK(
				( 0, ReactHooks.useState )( [
					{
						disabled: ! 0,
						label: 'Select an page',
						value: '',
					},
				] ),
				2
			),
			u = c[ 0 ],
			s = c[ 1 ],
			d = function ( e ) {
				return e
					? Object.entries( e ).map( function ( _eLocal ) {
							var _tLocal = PK( _eLocal, 2 ),
								_nLocal = _tLocal[ 0 ];
							return {
								label: _tLocal[ 1 ],
								value: _nLocal,
							};
						} )
					: [];
			};
		return (
			( 0, ReactHooks.useEffect )( function () {
				var e = ( function () {
					var _eLocal2,
						_tLocal2 =
							( ( _eLocal2 = RK().m( function _eLocal3() {
								var _tLocal3, _nLocal2, _rLocal;
								return RK().w( function ( _eLocal4 ) {
									for (;;) {
										switch ( _eLocal4.n ) {
											case 0:
												return (
													i.setLoadingSetting( ! 0 ),
													( _eLocal4.n = 1 ),
													l()( {
														path: 'ohmylms/v1/settings/general',
													} )
												 );
											case 1:
												return (
													( _tLocal3 = _eLocal4.v ),
													i.setGeneralSettings(
														_tLocal3
													),
													( _eLocal4.n = 2 ),
													l()( {
														path: '/ohmylms/v1/page/search?value=',
														method: 'GET',
														headers: {
															'Content-Type':
																'application/json',
														},
													} )
												 );
											case 2:
												return (
													( _nLocal2 = _eLocal4.v ),
													( _eLocal4.n = 3 ),
													d( _nLocal2 )
												 );
											case 3:
												_rLocal = _eLocal4.v;
												s( _rLocal );
												i.setLoadingSetting( ! 1 ); // Falls through to the shared async state cleanup.
											case 4:
												return _eLocal4.a( 2 );
										}
									}
								}, _eLocal3 );
							} ) ),
							function ( ..._args ) {
								var _tLocal4 = this,
									_nLocal3 = _args;
								return new Promise( function (
									_rLocal2,
									_aLocal
								) {
									var _oLocal = _eLocal2.apply(
										_tLocal4,
										_nLocal3
									);
									function _iLocal( _eLocal5 ) {
										CK(
											_oLocal,
											_rLocal2,
											_aLocal,
											_iLocal,
											_lLocal,
											'next',
											_eLocal5
										);
									}
									function _lLocal( _eLocal6 ) {
										CK(
											_oLocal,
											_rLocal2,
											_aLocal,
											_iLocal,
											_lLocal,
											'throw',
											_eLocal6
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
							<MemoGeneralSettingsFields
								pages={ u }
								setPages={ s }
							/>
							<MemoSettingsActionBar
								activeTab={ t }
								handleSave={ n }
								handleMigration={ r }
								selectedCourses={ a }
								isSaving={ o }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</React.Fragment>
			 )
		 );
	};
}
