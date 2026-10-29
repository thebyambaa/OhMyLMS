import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createAssignmentReport( readRuntime ) {
	return function AssignmentReport() {
		const {
			Cm,
			HG,
			Q$,
			QZ,
			React,
			U$,
			Y$,
			b: I18n,
			df,
			f: Router,
			fN,
			g: ReactHooks,
			l,
			sN,
			uf,
			v,
		} = readRuntime();
		HG( 'ohmylms', 'assignments' );
		var e = Q$( ( 0, ReactHooks.useState )( '' ), 2 ),
			t = e[ 0 ],
			n = e[ 1 ],
			r = Q$( ( 0, ReactHooks.useState )( 1 ), 2 ),
			a = r[ 0 ],
			o = r[ 1 ],
			i = Q$( ( 0, ReactHooks.useState )( [] ), 2 ),
			c = i[ 0 ],
			u = i[ 1 ],
			s = Q$( ( 0, ReactHooks.useState )( ! 0 ), 2 ),
			d = s[ 0 ],
			m = s[ 1 ],
			p = Q$( ( 0, ReactHooks.useState )( 10 ), 1 )[ 0 ],
			h = ( 0, Router.Zp )(),
			y = ( 0, Router.g )().id;
		( 0, ReactHooks.useEffect )(
			function () {
				var _eLocal = ( function () {
					var _eLocal2,
						_tLocal =
							( ( _eLocal2 = U$().m( function _eLocal3() {
								var _tLocal2, _nLocal;
								return U$().w(
									function ( _eLocal4 ) {
										for (;;) {
											switch (
												( _eLocal4.p = _eLocal4.n )
											) {
												case 0:
													return (
														( _eLocal4.p = 0 ),
														m( ! 0 ),
														( _eLocal4.n = 1 ),
														l()( {
															path: '/ohmylms/v1/assignment/'.concat(
																y,
																'/report'
															),
															method: 'GET',
															headers: {
																'Content-Type':
																	'application/json',
															},
														} )
													 );
												case 1:
													if (
														( _tLocal2 =
															_eLocal4.v )
													) {
														_eLocal4.n = 2;
														break;
													}
													_tLocal2 = [];
												// Falls through to the shared async state cleanup.
												case 2:
													u( _tLocal2 || [] );
													_eLocal4.n = 4;
													break;
												case 3:
													_eLocal4.p = 3;
													_nLocal = _eLocal4.v;
													showRequestError( _nLocal );
												// Falls through to the shared async state cleanup.
												case 4:
													return (
														( _eLocal4.p = 4 ),
														m( ! 1 ),
														_eLocal4.f( 4 )
													 );
												case 5:
													return _eLocal4.a( 2 );
											}
										}
									},
									_eLocal3,
									null,
									[ [ 0, 3, 4, 5 ] ]
								);
							} ) ),
							function ( ..._args ) {
								var _tLocal3 = this,
									_nLocal2 = _args;
								return new Promise( function (
									_rLocal,
									_aLocal
								) {
									var _oLocal = _eLocal2.apply(
										_tLocal3,
										_nLocal2
									);
									function _iLocal( _eLocal5 ) {
										Y$(
											_oLocal,
											_rLocal,
											_aLocal,
											_iLocal,
											_lLocal,
											'next',
											_eLocal5
										);
									}
									function _lLocal( _eLocal6 ) {
										Y$(
											_oLocal,
											_rLocal,
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
						return _tLocal.apply( this, _args2 );
					};
				} )();
				_eLocal();
			},
			[ y ]
		);
		var _ = c.filter( function ( _eLocal7 ) {
				return Object.values( _eLocal7 ).some( function ( _eLocal8 ) {
					return String( _eLocal8 ).toLowerCase().includes( t );
				} );
			} ),
			w = [
				{
					title: 'Name',
					dataIndex: 'display_name',
					key: 'display_name',
				},
				{
					title: 'Email',
					dataIndex: 'user_email',
					key: 'user_email',
				},
				{
					title: 'Total Submitted',
					dataIndex: 'submissions',
					key: 'submissions',
					render( _eLocal9 ) {
						return (
							( _eLocal9 === null || _eLocal9 === void 0
								? void 0
								: _eLocal9.length ) || 0
						);
					},
				},
				{
					title: 'Status',
					dataIndex: 'status',
					key: 'status',
					render( _eLocal0, _tLocal4 ) {
						var _nLocal3 =
							_tLocal4 === null || _tLocal4 === void 0
								? void 0
								: _tLocal4.submissions[ 0 ].status;
						return (
							<Controls.BadgeWP
								style={ {
									textTransform: 'capitalize',
								} }
								variant={ ( () => {
									if ( 'submitted' === _nLocal3 ) {
										return 'warning';
									}
									if ( 'passed' === _nLocal3 ) {
										return 'success';
									}
									return 'danger';
								} )() }
							>
								{ 'submitted' === _nLocal3
									? ( 0, I18n.__ )( 'Pending', 'ohmylms' )
									: _nLocal3 }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: 'Action',
					key: 'action',
					render( _eLocal1, _tLocal5 ) {
						return (
							<Controls.ButtonWP
								variant={ 'primary' }
								onClick={ function () {
									return (
										( _eLocal10 =
											_tLocal5 === null ||
											_tLocal5 === void 0
												? void 0
												: _tLocal5.user_id ),
										void h(
											'grade-assignment/'.concat(
												_eLocal10
											)
										)
									 );
									var _eLocal10;
								} }
							>
								{ ( 0, I18n.__ )( 'Details', 'ohmylms' ) }
							</Controls.ButtonWP>
						);
					},
				},
			];
		return (
			<React.Fragment>
				<Controls.ContainerWP>
					<Controls.SpacerWP marginY={ 5 }>
						<Controls.FlexWP
							align={ 'center' }
							justify={ 'space-between' }
							gap={ 2 }
						>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.FlexWP
									justify={ 'center' }
									align={ 'center' }
									gap={ 2 }
								>
									<Controls.SpacerWP
										padding={ 2 }
										marginBottom={ 0 }
									>
										<Controls.FlexWP
											justify={ 'center' }
											align={ 'center' }
											gap={ 2 }
										>
											<v.Link to={ '/assignments' }>
												<Controls.FlexWP
													justify={ 'flex-start' }
													align={ 'center' }
													gap={ 1 }
												>
													<QZ />
													<Controls.TextWP
														size={ 15 }
													>
														{ ( 0, I18n.__ )(
															'Assignments /',
															'ohmylms'
														) }
													</Controls.TextWP>
												</Controls.FlexWP>
											</v.Link>
											<Controls.TextWP size={ 15 }>
												{ ( 0, I18n.__ )(
													'Result',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexWP>
									</Controls.SpacerWP>
								</Controls.FlexWP>
							</Controls.CardWP>
							<Controls.CardWP isBorderless={ ! 0 }>
								<Controls.SpacerWP
									padding={ 2 }
									marginBottom={ 0 }
								>
									<Cm
										placeholder={ ( 0, I18n.__ )(
											'Search Submission',
											'ohmylms'
										) }
										onChange={ function ( _eLocal11 ) {
											n( _eLocal11.toLowerCase() );
										} }
									/>
								</Controls.SpacerWP>
							</Controls.CardWP>
						</Controls.FlexWP>
						<Controls.CardWP isBorderless={ ! 0 }>
							<Controls.SpacerWP padding={ 5 } marginTop={ 4 }>
								<sN.A
									columns={ w }
									rowKey={ 'user_email' }
									dataSource={ _.slice(
										( a - 1 ) * p,
										a * p
									) }
									pagination={ ! 1 }
									loading={ d }
									locale={ {
										emptyText: React.createElement( uf, {
											icon: React.createElement(
												df,
												null
											),
											title: ( 0, I18n.__ )(
												'No submission yet!',
												'ohmylms'
											),
										} ),
									} }
								/>
								{ _.length > p &&
									React.createElement( fN, {
										total: _.length,
										currentPage: a,
										onPageChange( _eLocal12 ) {
											o( _eLocal12 );
										},
										perPage: p,
									} ) }
							</Controls.SpacerWP>
						</Controls.CardWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
