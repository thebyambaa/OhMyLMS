/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createMembershipDetails( readRuntime ) {
	return function MembershipDetails( props ) {
		const {
			Ge,
			J7: MembershipPricing,
			Nm,
			Pf,
			React,
			T: StoreModule,
			b: I18n,
			b8,
			d8,
			f8,
			g: ReactHooks,
			h8,
			i8: MembershipSaleSchedule,
			m8,
			p8,
			v8,
			y: WordPressData,
			y8,
		} = readRuntime();
		var t,
			n,
			errors = props.errors,
			a = ( props.setErrors, props.validate ),
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectMembershipPlanData();
			}, [] ),
			i = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getMemberships();
			}, [] ),
			updateMembershipPlan = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateMembershipPlan,
			c = ( () => {
				if ( 0 < ( i === null || i === void 0 ? void 0 : i.length ) ) {
					if ( null === ( t = i[ 0 ] ) || void 0 === t ) {
						return void 0;
					}
					return t.currency;
				}
				if (
					null === ( n = window ) ||
					void 0 === n ||
					null === ( n = n.ohmylms_params ) ||
					void 0 === n
				) {
					return void 0;
				}
				return n.currency;
			} )(),
			u = function ( e, _tLocal ) {
				updateMembershipPlan( e, _tLocal );
				a( h8( h8( {}, o ), {}, y8( {}, e, _tLocal ) ) );
			},
			s = function ( ..._args ) {
				let [ e, _tLocal2 ] = _args;
				var _nLocal =
					_args.length > 2 && void 0 !== _args[ 2 ] ? _args[ 2 ] : '';
				for ( var r in _tLocal2 ) {
					var _aLocal = _nLocal
						? ''.concat( _nLocal, '.' ).concat( r )
						: r;
					( () => {
						if (
							'object' === v8( _tLocal2[ r ] ) &&
							null !== _tLocal2[ r ]
						) {
							if ( e[ r ] && 'object' === v8( e[ r ] ) ) {
								return s( e[ r ], _tLocal2[ r ], _aLocal );
							}
							return updateMembershipPlan(
								_aLocal,
								_tLocal2[ r ]
							);
						}
						return (
							( e.hasOwnProperty( r ) && e[ r ] ) ||
							updateMembershipPlan( _aLocal, _tLocal2[ r ] )
						);
					} )();
				}
			},
			d = d8;
		return (
			( () => {
				if (
					'month' ===
					( o === null || o === void 0
						? void 0
						: o.subscription_period )
				) {
					return ( d = m8 );
				}
				if (
					'week' ===
					( o === null || o === void 0
						? void 0
						: o.subscription_period )
				) {
					return ( d = p8 );
				}
				return (
					'day' ===
						( o === null || o === void 0
							? void 0
							: o.subscription_period ) && ( d = f8 )
				);
			} )(),
			( 0, ReactHooks.useEffect )( function () {
				s( o, b8 );
				a( o );
			}, [] ),
			(
				<React.Fragment>
					<Pf
						title={ ( 0, I18n.__ )( 'Title', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'What would you like to call this plan?',
							'ohmylms'
						) }
						tooltip={ ( 0, I18n.__ )(
							'This is the title of the plan.',
							'ohmylms'
						) }
						value={ Ge(
							o === null || o === void 0 ? void 0 : o.name
						) }
						onChange={ function ( e ) {
							return u( 'name', e );
						} }
						error={
							errors === null || errors === void 0
								? void 0
								: errors.name
						}
						className={ 'ohmylms-membership-plan-name-input' }
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<Pf
						title={ ( 0, I18n.__ )( 'Description', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'Describe what the membership plan offers.',
							'ohmylms'
						) }
						value={ Ge(
							o === null || o === void 0 ? void 0 : o.description
						) }
						onChange={ function ( e ) {
							return u( 'description', e );
						} }
						inputType={ 'textarea' }
						className={
							'ohmylms-membership-plan-description-input'
						}
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<MembershipPricing errors={ errors } validate={ a } />
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					{ 'one_time' !==
						( o === null || o === void 0
							? void 0
							: o.subscription_period ) && (
						<React.Fragment>
							<Nm
								title={ ( 0, I18n.__ )(
									'Stop renewing after',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Automatically stop renewing the subscription after this length of time',
									'ohmylms'
								) }
								tooltip={ ( 0, I18n.__ )(
									'This is the title of the plan.',
									'ohmylms'
								) }
								placeholder={ ( 0, I18n.__ )(
									'Type to Select Option',
									'ohmylms'
								) }
								data={ d }
								notFoundMessage={ ( 0, I18n.__ )(
									'Nothing Found',
									'ohmylms'
								) }
								isMultiple={ ! 1 }
								onChange={ function ( e ) {
									u( 'subscription_period_interval', '1' );
									u( 'subscription_length', e );
								} }
								value={
									o === null || o === void 0
										? void 0
										: o.subscription_length
								}
								staticSearch={ ! 0 }
								className={
									'ohmylms-stop-renewing-after-select'
								}
							/>
							<Controls.DividerWP
								marginStart={ '2' }
								marginEnd={ '2' }
							/>
						</React.Fragment>
					) }
					<Pf
						title={ ( 0, I18n.__ )(
							'Sign-up fee ('.concat( c, ')' ),
							'ohmylms'
						) }
						description={ ( 0, I18n.__ )(
							'Optionally include an amount to be charged at the outset of the subscription',
							'ohmylms'
						) }
						tooltip={ ( 0, I18n.__ )(
							'This is the title of the plan.',
							'ohmylms'
						) }
						value={
							o === null || o === void 0 ? void 0 : o.sign_up_fee
						}
						placeholder={ ( 0, I18n.__ )( 'e.g. 5.90', 'ohmylms' ) }
						inputType={ 'number' }
						onChange={ function ( e ) {
							var _tLocal3 = e;
							if ( /^\d*\.?\d*$/.test( _tLocal3 ) ) {
								u( 'sign_up_fee', _tLocal3 );
							}
						} }
						onKeyDown={ function ( e ) {
							if ( ! (
								'-' !== e.key &&
								'+' !== e.key &&
								'e' !== e.key
							) ) {
								e.preventDefault();
							}
						} }
						onBlur={ function () {
							if (
								( o === null || o === void 0
									? void 0
									: o.sign_up_fee ) < 0
							) {
								u( 'sign_up_fee', '0' );
							}
						} }
						min={ 0 }
						max={ 99999999 }
						className={ 'ohmylms-sign-up-fee-input' }
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<MembershipSaleSchedule errors={ errors } validate={ a } />
				</React.Fragment>
			 )
		 );
	};
}
