import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
import { highlightAdminMenu } from '../../shared/utils';
export function createAssignmentGrading( readRuntime ) {
	return function AssignmentGrading() {
		const {
			React,
			T: StoreModule,
			X$: AssignmentGradeForm,
			b: I18n,
			cK,
			dK,
			f: Router,
			g: ReactHooks,
			l,
			lK: AssignmentResultHeader,
			mK,
			rK: AssignmentSubmission,
			y: WordPressData,
		} = readRuntime();
		highlightAdminMenu( 'ohmylms', 'assignments' );
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = mK( ( 0, ReactHooks.useState )( [] ), 2 ),
			n = t[ 0 ],
			r = t[ 1 ],
			a = mK( ( 0, ReactHooks.useState )( {} ), 2 ),
			o = a[ 0 ],
			i = a[ 1 ],
			c = mK( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ],
			d = ( ( 0, Router.Zp )(), ( 0, Router.g )() ),
			m = d.id,
			assignmentId = d.assignmentId,
			v = mK(
				( 0, ReactHooks.useState )(
					( n === null || n === void 0 ? void 0 : n.score ) || 0
				),
				2
			),
			h = v[ 0 ],
			_ = v[ 1 ],
			w = mK(
				( 0, ReactHooks.useState )(
					( n === null || n === void 0 ? void 0 : n.note ) || ''
				),
				2
			),
			E = w[ 0 ],
			S = w[ 1 ],
			R = useNotifications(),
			openNotificationWithIcon = R.openNotificationWithIcon,
			contextHolder = R.contextHolder,
			P = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getNotificationMessage();
			}, [] ),
			O = ( 0, WordPressData.useSelect )( function ( _eLocal2 ) {
				return _eLocal2( StoreModule.default ).getNotificationStatus();
			}, [] );
		( 0, ReactHooks.useEffect )( function () {
			var _eLocal3 = ( function () {
				var _eLocal4 = dK(
					cK().m( function _eLocal5() {
						var _tLocal, _nLocal, _aLocal;
						return cK().w(
							function ( _eLocal6 ) {
								for (;;) {
									switch ( ( _eLocal6.p = _eLocal6.n ) ) {
										case 0:
											return (
												( _eLocal6.p = 0 ),
												s( ! 0 ),
												( _eLocal6.n = 1 ),
												l()( {
													path: '/ohmylms/v1/assignment/'
														.concat( m, '/report/' )
														.concat( assignmentId ),
													method: 'GET',
													headers: {
														'Content-Type':
															'application/json',
													},
												} )
											 );
										case 1:
											if ( ( _nLocal = _eLocal6.v ) ) {
												_eLocal6.n = 2;
												break;
											}
											_nLocal = [];
										// Falls through to the shared async state cleanup.
										case 2:
											r(
												( ( _nullableValue ) =>
													_nullableValue === null ||
													_nullableValue === void 0 )(
													( _tLocal = _nLocal )
												)
													? void 0
													: _tLocal.report[ 0 ]
											);
											i(
												_tLocal === null ||
													_tLocal === void 0
													? void 0
													: _tLocal.additional_data
											);
											_eLocal6.n = 4;
											break;
										case 3:
											_eLocal6.p = 3;
											_aLocal = _eLocal6.v;
											showRequestError( _aLocal );
										// Falls through to the shared async state cleanup.
										case 4:
											return (
												( _eLocal6.p = 4 ),
												s( ! 1 ),
												_eLocal6.f( 4 )
											 );
										case 5:
											return _eLocal6.a( 2 );
									}
								}
							},
							_eLocal5,
							null,
							[ [ 0, 3, 4, 5 ] ]
						);
					} )
				);
				return function ( ..._args ) {
					return _eLocal4.apply( this, _args );
				};
			} )();
			_eLocal3();
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				if ( ! u && P ) {
					openNotificationWithIcon( O, P );
				}
			},
			[ P ]
		);
		var k = ( function () {
				var _tLocal2 = dK(
					cK().m( function _tLocal3() {
						var _aLocal2, _cLocal, _uLocal, _dLocal;
						return cK().w(
							function ( _tLocal4 ) {
								for (;;) {
									switch ( ( _tLocal4.p = _tLocal4.n ) ) {
										case 0:
											return (
												( _tLocal4.p = 0 ),
												( _cLocal = [
													{
														id:
															n === null ||
															n === void 0 ||
															null ===
																( _aLocal2 =
																	n
																		.submissions[ 0 ] ) ||
															void 0 === _aLocal2
																? void 0
																: _aLocal2.id,
														score: Number( h ),
														note: E,
														status:
															( o === null ||
															o === void 0
																? void 0
																: o.pass_marks ) <=
															h
																? 'passed'
																: 'failed',
													},
												] ),
												( _tLocal4.n = 1 ),
												l()( {
													path: '/ohmylms/v1/assignment/'
														.concat( m, '/report/' )
														.concat( assignmentId ),
													method: 'POST',
													headers: {
														'Content-Type':
															'application/json',
													},
													body: JSON.stringify(
														_cLocal
													),
												} )
											 );
										case 1:
											_uLocal = _tLocal4.v;
											r(
												_uLocal === null ||
													_uLocal === void 0
													? void 0
													: _uLocal.report[ 0 ]
											);
											i(
												_uLocal === null ||
													_uLocal === void 0
													? void 0
													: _uLocal.additional_data
											);
											e.showNotification(
												( 0, I18n.__ )(
													'Grade Upgraded Successfully',
													'ohmylms'
												),
												'success'
											);
											_tLocal4.n = 3;
											break;
										case 2:
											_tLocal4.p = 2;
											_dLocal = _tLocal4.v;
											showRequestError( _dLocal );
											e.showNotification(
												( 0, I18n.__ )(
													'Something went wrong',
													'ohmylms'
												),
												'error'
											);
										// Falls through to the shared async state cleanup.
										case 3:
											return (
												( _tLocal4.p = 3 ),
												s( ! 1 ),
												_tLocal4.f( 3 )
											 );
										case 4:
											return _tLocal4.a( 2 );
									}
								}
							},
							_tLocal3,
							null,
							[ [ 0, 2, 3, 4 ] ]
						);
					} )
				);
				return function ( ..._args2 ) {
					return _tLocal2.apply( this, _args2 );
				};
			} )(),
			j = ( n === null || n === void 0 ? void 0 : n.submissions ) || [];
		return (
			( 0, ReactHooks.useEffect )(
				function () {
					var _eLocal7, _tLocal5, _rLocal;
					if (
						( n === null ||
						n === void 0 ||
						null === ( _eLocal7 = n.submissions ) ||
						void 0 === _eLocal7
							? void 0
							: _eLocal7.length ) > 0
					) {
						{
							_(
								n === null ||
									n === void 0 ||
									null ===
										( _tLocal5 = n.submissions[ 0 ] ) ||
									void 0 === _tLocal5
									? void 0
									: _tLocal5.score
							);
							S(
								n === null ||
									n === void 0 ||
									null === ( _rLocal = n.submissions[ 0 ] ) ||
									void 0 === _rLocal
									? void 0
									: _rLocal.note
							);
						}
					}
				},
				[ n ]
			),
			(
				<React.Fragment>
					{ contextHolder }
					<Controls.ContainerWP>
						<Controls.SpacerWP marginY={ 5 }>
							<AssignmentResultHeader
								data={ n }
								submissions={ j }
								handleUpgradeGrade={ k }
							/>
							<Controls.SpacerWP marginTop={ 8 }>
								{ u ? (
									<Controls.SkeletonWP
										active={ ! 0 }
										rows={ 10 }
									/>
								) : (
									<React.Fragment>
										<Controls.FlexWP
											gap={ 8 }
											justify={ 'space-between' }
											align={ 'start' }
											style={ {
												height: '100%',
											} }
										>
											<Controls.FlexItemWP flex={ 5 }>
												<Controls.FlexWP
													direction={ 'column' }
													size={ 'middle' }
													style={ {
														height: '100%',
													} }
													items={ 'flex-start' }
												>
													{ n.submissions.map(
														function ( _eLocal8 ) {
															return (
																<AssignmentSubmission
																	key={
																		_eLocal8.id
																	}
																	additionData={
																		o
																	}
																	submission={
																		_eLocal8
																	}
																/>
															);
														}
													) }
												</Controls.FlexWP>
											</Controls.FlexItemWP>
											<Controls.FlexItemWP flex={ 3 }>
												<AssignmentGradeForm
													student={ n }
													additionData={ o }
													setScore={ _ }
													setNote={ S }
													note={ E }
													score={ h }
												/>
											</Controls.FlexItemWP>
										</Controls.FlexWP>
									</React.Fragment>
								) }
							</Controls.SpacerWP>
						</Controls.SpacerWP>
					</Controls.ContainerWP>
				</React.Fragment>
			 )
		 );
	};
}
