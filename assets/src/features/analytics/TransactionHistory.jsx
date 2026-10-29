/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Table } from '../../shared/table';
export function createTransactionHistory( readRuntime ) {
	return function TransactionHistory( props ) {
		const {
			Eq,
			I: Controls,
			React,
			ZU: AnalyticsDateFilter,
			b: I18n,
			df: EmptyIcon,
			g: ReactHooks,
			uf: EmptyState,
			vn,
		} = readRuntime();
		var t = props.handleTypeFilters,
			n = props.handleOrderTypeFilters,
			r = props.orderTypeOptions,
			a = props.transactionLoading,
			o = props.transactionData,
			i = props.skeletonColumns,
			l = props.columns,
			c = props.dataLoading,
			u =
				( props.currencyData,
				( function ( e, _tLocal ) {
					return (
						( function ( _eLocal ) {
							if ( Array.isArray( _eLocal ) ) {
								return _eLocal;
							}
						} )( e ) ||
						( function ( _eLocal2, _tLocal2 ) {
							var _nLocal =
								_eLocal2 === null || _eLocal2 === void 0
									? null
									: ( 'undefined' !== typeof Symbol &&
											_eLocal2[ Symbol.iterator ] ) ||
										_eLocal2[ '@@iterator' ];
							if ( _nLocal !== null && _nLocal !== void 0 ) {
								var _rLocal,
									_aLocal,
									_oLocal,
									_iLocal,
									_lLocal = [],
									_cLocal = ! 0,
									_uLocal = ! 1;
								try {
									if (
										( ( _oLocal = ( _nLocal =
											_nLocal.call( _eLocal2 ) ).next ),
										0 === _tLocal2 )
									) {
										if ( Object( _nLocal ) !== _nLocal ) {
											return;
										}
										_cLocal = ! 1;
									} else {
										for (
											;
											! ( _cLocal = ( _rLocal =
												_oLocal.call( _nLocal ) )
												.done ) &&
											( _lLocal.push( _rLocal.value ),
											_lLocal.length !== _tLocal2 );
											_cLocal = ! 0
										) {}
									}
								} catch ( _eLocal3 ) {
									_uLocal = ! 0;
									_aLocal = _eLocal3;
								} finally {
									try {
										if (
											! _cLocal &&
											( ( _nullableValue ) =>
												_nullableValue !== null &&
												_nullableValue !== void 0 )(
												_nLocal.return
											) &&
											( ( _iLocal = _nLocal.return() ),
											Object( _iLocal ) !== _iLocal )
										) {
											return;
										}
									} finally {
										if ( _uLocal ) {
											throw _aLocal;
										}
									}
								}
								return _lLocal;
							}
						} )( e, _tLocal ) ||
						( function ( _eLocal4, _tLocal3 ) {
							if ( _eLocal4 ) {
								if ( 'string' === typeof _eLocal4 ) {
									return Eq( _eLocal4, _tLocal3 );
								}
								var _nLocal2 = {}.toString
									.call( _eLocal4 )
									.slice( 8, -1 );
								return (
									'Object' === _nLocal2 &&
										_eLocal4.constructor &&
										( _nLocal2 =
											_eLocal4.constructor.name ),
									( () => {
										if (
											'Map' === _nLocal2 ||
											'Set' === _nLocal2
										) {
											return Array.from( _eLocal4 );
										}
										if (
											'Arguments' === _nLocal2 ||
											/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
												_nLocal2
											)
										) {
											return Eq( _eLocal4, _tLocal3 );
										}
										return void 0;
									} )()
								 );
							}
						} )( e, _tLocal ) ||
						( function () {
							throw new TypeError(
								'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
							);
						} )()
					);
				} )( ( 0, ReactHooks.useState )( ! 0 ), 2 ) ),
			s =
				( u[ 0 ],
				u[ 1 ],
				o.reduce( function ( e, _tLocal4 ) {
					return e + _tLocal4.order_total;
				}, 0 ),
				( 0, ReactHooks.useCallback )( function ( e ) {
					if ( 'custom_range' !== e ) {
						t( e );
					}
				}, [] ) );
		return (
			<React.Fragment>
				<Controls.HeadingWP level={ 3 } size={ 16 }>
					{ ( 0, I18n.__ )( 'Transaction history', 'ohmylms' ) }
				</Controls.HeadingWP>
				<Controls.SpacerWP marginBottom={ 4 } />
				<Controls.FlexWP
					gap={ 2 }
					align={ 'center' }
					justify={ 'flex-start' }
				>
					<Controls.FlexItemWP>
						<AnalyticsDateFilter
							placeholder={ ( 0, I18n.__ )(
								'Filter By Days',
								'ohmylms'
							) }
							onChange={ s }
							onRangeChange={ t }
						/>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP>
						<vn.A
							placeholder={ 'Order Type' }
							onChange={ n }
							options={ r }
							defaultValue={ 'all' }
						/>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
				<Controls.SpacerWP marginBottom={ 4 } />
				<Table
					rowKey={ 'order_id' }
					columns={ a || c ? i : l }
					dataSource={ o }
					locale={ {
						emptyText: (
							<EmptyState
								icon={ <EmptyIcon /> }
								title={ ( 0, I18n.__ )(
									'Your transaction history is waiting to be filled!',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Your course sales and payment records will appear here once transactions occur.',
									'ohmylms'
								) }
							/>
						),
					} }
				/>
			</React.Fragment>
		);
	};
}
