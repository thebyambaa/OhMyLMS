import { showRequestError } from '../requestFeedback.mjs';
import { validateRefund } from './model.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement, useId } from '@wordpress/element';
import { Tooltip } from '../../shared/tooltip';
import { Table } from '../../shared/table';
import { InfoIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
export function createOrderItems( readRuntime ) {
	return function OrderItems( props ) {
		const _controlId = useId();
		const {
			Ge: decodeEntities,
			React,
			T: StoreModule,
			W: Textarea,
			YH: Price,
			b: I18n,
			g: ReactHooks,
			iQ,
			tQ: Tag,
			wn: NumberInput,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			l,
			order = props.order,
			items = props.items,
			coupon = props.coupon,
			subtotal = props.subtotal,
			total = props.total,
			discount = props.discount,
			taxAmount = props.taxAmount,
			taxRate = props.taxRate,
			dispatch = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			actions = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			fetchOrder = actions.fetchOrder,
			issueRefund = actions.issueRefund,
			R = iQ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			isRefundOpen = R[ 0 ],
			setRefundOpen = R[ 1 ],
			P = iQ( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			isSubmitting = P[ 0 ],
			setSubmitting = P[ 1 ],
			refund = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getRefundState();
			}, [] ),
			totalRefunded = order.refunds.reduce( function ( e, _tLocal ) {
				return e + parseFloat( _tLocal.total );
			}, 0 ),
			canRefund = parseFloat( order.total ) + totalRefunded > 0,
			remainingTotal = parseFloat( order.total ) + totalRefunded,
			closeRefund = function () {
				setRefundOpen( ! 1 );
			},
			submitRefund = async function () {
				if ( isSubmitting ) {
					return;
				}
				const validation = validateRefund(
					refund,
					remainingTotal,
					I18n.__
				);
				if ( ! validation.valid ) {
					showRequestError( validation.error );
					return;
				}
				setSubmitting( true );
				try {
					const result = await issueRefund( order, refund );
					if ( ! result || result.success === false ) {
						dispatch.showNotification(
							result?.message ||
								I18n.__( 'Something went wrong!', 'ohmylms' ),
							'error'
						);
						return;
					}
					await fetchOrder( order.id );
					setRefundOpen( false );
					dispatch.showNotification(
						I18n.__( 'Refunded Successfully', 'ohmylms' ),
						'success'
					);
				} catch ( error ) {
					dispatch.showNotification(
						error?.message ||
							I18n.__( 'Something went wrong!', 'ohmylms' ),
						'error'
					);
				} finally {
					setSubmitting( false );
				}
			},
			updateRefundField = function ( field, value ) {
				dispatch.updateRefund( {
					[ field ]: value,
				} );
			},
			currencyOptions = {
				currencySymbol:
					( order === null ||
					order === void 0 ||
					null === ( t = order.currency ) ||
					void 0 === t
						? void 0
						: t.currency ) || '$',
				currencyPosition:
					( order === null ||
					order === void 0 ||
					null === ( n = order.currency ) ||
					void 0 === n
						? void 0
						: n.currency_pos ) || 'left',
			},
			summaryRow = function ( ..._args ) {
				let [ e, _tLocal2 ] = _args;
				var _nLocal,
					_rLocal,
					_aLocal =
						_args.length > 2 && void 0 !== _args[ 2 ] && _args[ 2 ];
				return (
					<Controls.FlexWP
						justify={ 'space-between' }
						align={ 'center' }
						gap={ 5 }
						className={ 'ohmylms-order-details-tfoot-td-flex' }
					>
						<Controls.FlexItemWP
							className={ 'ohmylms-order-details-tfoot-td-left' }
						>
							<Controls.TextWP
								as={ 'span' }
								color={ '#000D25' }
								weight={ 'Paid' === e ? 700 : 400 }
								size={ 14 }
								align={ 'right' }
								isBlock={ ! 0 }
							>
								{ e }
								{ ':' }
							</Controls.TextWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP
							className={ 'ohmylms-order-details-tfoot-td-right' }
						>
							<Controls.TextWP
								as={ 'span' }
								color={ '#000D25' }
								weight={ 700 }
								size={ 14 }
								align={ 'right' }
								isBlock={ ! 0 }
							>
								{ _aLocal && '-' }
								<Price
									currency={
										order === null ||
										order === void 0 ||
										null === ( _nLocal = order.currency ) ||
										void 0 === _nLocal
											? void 0
											: _nLocal.currency
									}
									currency_pos={
										order === null ||
										order === void 0 ||
										null === ( _rLocal = order.currency ) ||
										void 0 === _rLocal
											? void 0
											: _rLocal.currency_pos
									}
									price={ Number( _tLocal2 ) }
								/>
							</Controls.TextWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				);
			},
			refundSummaryRow = function ( e, _tLocal3 ) {
				var _nLocal2, _rLocal2;
				return (
					<Controls.FlexWP
						justify={ 'space-between' }
						align={ 'center' }
						gap={ 5 }
						className={ 'ohmylms-order-details-tfoot-td-flex' }
					>
						<Controls.FlexItemWP
							className={ 'ohmylms-order-details-tfoot-td-left' }
						>
							<Controls.TextWP
								as={ 'span' }
								color={
									'Refunded' === e ? '#FF4D4F' : '#000D25'
								}
								weight={ 'Net Payment' === e ? 700 : 400 }
								size={ 14 }
								align={ 'right' }
								isBlock={ ! 0 }
							>
								{ e }
								{ ':' }
							</Controls.TextWP>
						</Controls.FlexItemWP>
						<Controls.FlexItemWP
							className={ 'ohmylms-order-details-tfoot-td-right' }
						>
							<Controls.TextWP
								as={ 'span' }
								color={
									'Refunded' === e ? '#FF4D4F' : '#000D25'
								}
								weight={ 700 }
								size={ 14 }
								align={ 'right' }
								isBlock={ ! 0 }
							>
								<Price
									currency={
										order === null ||
										order === void 0 ||
										null ===
											( _nLocal2 = order.currency ) ||
										void 0 === _nLocal2
											? void 0
											: _nLocal2.currency
									}
									currency_pos={
										order === null ||
										order === void 0 ||
										null ===
											( _rLocal2 = order.currency ) ||
										void 0 === _rLocal2
											? void 0
											: _rLocal2.currency_pos
									}
									price={ Number( _tLocal3 ) }
								/>
							</Controls.TextWP>
						</Controls.FlexItemWP>
					</Controls.FlexWP>
				);
			},
			columns = [
				{
					title: ( 0, I18n.__ )( 'Item', 'ohmylms' ),
					dataIndex: 'name',
					key: 'name',
					render( e ) {
						return (
							<Controls.TextWP
								as={ 'p' }
								color={ '#000D25' }
								weight={ 500 }
								size={ 14 }
							>
								{ decodeEntities( e ) }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Price', 'ohmylms' ),
					dataIndex: 'price',
					key: 'price',
					render( e ) {
						var _tLocal4, _nLocal3;
						return (
							<Controls.TextWP
								as={ 'span' }
								color={ '#7A8B9A' }
								weight={ 500 }
								size={ 14 }
							>
								<Price
									currency={
										order === null ||
										order === void 0 ||
										null ===
											( _tLocal4 = order.currency ) ||
										void 0 === _tLocal4
											? void 0
											: _tLocal4.currency
									}
									currency_pos={
										order === null ||
										order === void 0 ||
										null ===
											( _nLocal3 = order.currency ) ||
										void 0 === _nLocal3
											? void 0
											: _nLocal3.currency_pos
									}
									price={ Number( e ) }
								/>
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Quantity', 'ohmylms' ),
					dataIndex: 'quantity',
					key: 'quantity',
					render( e ) {
						return (
							<Controls.TextWP
								as={ 'span' }
								color={ '#7A8B9A' }
								weight={ 500 }
								size={ 14 }
							>
								<svg
									width={ '10' }
									height={ '10' }
									fill={ 'none' }
									viewBox={ '0 0 10 10' }
									xmlns={ 'http://www.w3.org/2000/svg' }
								>
									<path
										stroke={ '#7A8B9A' }
										strokeLinecap={ 'round' }
										strokeLinejoin={ 'round' }
										strokeWidth={ '2' }
										d={ 'M9.01 1l-8 8m0-8l8 8' }
									/>
								</svg>
								{ ' ' }
								{ e }
							</Controls.TextWP>
						);
					},
				},
				{
					title: ( 0, I18n.__ )( 'Total', 'ohmylms' ),
					dataIndex: 'total',
					key: 'total',
					render( e, _tLocal5 ) {
						var _nLocal4, _rLocal3;
						return (
							<Controls.TextWP
								as={ 'span' }
								color={ '#000D25' }
								weight={ 500 }
								size={ 14 }
								align={ 'right' }
							>
								<Price
									currency={
										order === null ||
										order === void 0 ||
										null ===
											( _nLocal4 = order.currency ) ||
										void 0 === _nLocal4
											? void 0
											: _nLocal4.currency
									}
									currency_pos={
										order === null ||
										order === void 0 ||
										null ===
											( _rLocal3 = order.currency ) ||
										void 0 === _rLocal3
											? void 0
											: _rLocal3.currency_pos
									}
									price={
										Number(
											_tLocal5 === null ||
												_tLocal5 === void 0
												? void 0
												: _tLocal5.price
										) *
										Number(
											_tLocal5 === null ||
												_tLocal5 === void 0
												? void 0
												: _tLocal5.quantity
										)
									}
								/>
							</Controls.TextWP>
						);
					},
				},
			];
		return (
			<Controls.CardWP
				isBorderless={ ! 0 }
				style={ {
					width: '100%',
				} }
			>
				<Controls.SpacerWP padding={ 4 }>
					<Table
						rowKey={ 'key' }
						columns={ columns }
						dataSource={ items }
						className={ 'ohmylms-order-details-summary-table' }
					/>
					<Controls.SpacerWP
						marginBottom={ 0 }
						paddingY={ 3 }
						paddingX={ 4 }
					>
						<Controls.FlexWP
							justify={ 'space-between' }
							align={ 'flex-start' }
							gap={ 3 }
							className={ 'ohmylms-order-details-tfoot-row' }
						>
							{ coupon.code ? (
								<div>
									<Controls.TextWP
										as={ 'p' }
										color={ '#000D25' }
										weight={ 500 }
										size={ 14 }
									>
										{ ( 0, I18n.__ )(
											'Coupon(s)',
											'ohmylms'
										) }
									</Controls.TextWP>
									<Controls.SpacerWP marginBottom={ 2 } />
									<Tag.A
										isBorderLess={ ! 0 }
										style={ {
											backgroundColor: '#F4F5F7',
										} }
									>
										{ coupon.code }
									</Tag.A>
								</div>
							) : (
								<div />
							) }
							<Controls.SpacerWP marginBottom={ 0 }>
								{ summaryRow(
									'Item subtotal',
									''.concat( subtotal )
								) }
								<Controls.SpacerWP marginBottom={ 3 } />
								{ discount &&
									summaryRow(
										'Discount',
										''.concat( discount )
									) }
								<Controls.SpacerWP marginBottom={ 3 } />
								{ taxAmount &&
									! (
										order !== null &&
										order !== void 0 &&
										order.is_included_tax
									) &&
									summaryRow(
										taxRate > 0
											? 'Tax ('.concat( taxRate, '%)' )
											: 'Tax',
										''.concat( taxAmount )
									) }
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.FlexWP
									justify={ 'space-between' }
									align={ 'flex-start' }
									gap={ 5 }
									className={
										'ohmylms-order-details-tfoot-td-flex'
									}
								>
									<Controls.FlexItemWP
										className={
											'ohmylms-order-details-tfoot-td-left'
										}
									>
										<Controls.TextWP
											as={ 'span' }
											color={ '#000D25' }
											weight={ 400 }
											size={ 14 }
											align={ 'right' }
											isBlock={ ! 0 }
										>
											{ ( 0, I18n.__ )(
												'Order total',
												'ohmylms'
											) }
											{ ':' }
										</Controls.TextWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP
										className={
											'ohmylms-order-details-tfoot-td-right'
										}
									>
										<Controls.TextWP
											as={ 'span' }
											color={ '#000D25' }
											weight={ 700 }
											size={ 14 }
											align={ 'right' }
											isBlock={ ! 0 }
										>
											<Price
												currency={
													order === null ||
													order === void 0 ||
													null ===
														( r =
															order.currency ) ||
													void 0 === r
														? void 0
														: r.currency
												}
												currency_pos={
													order === null ||
													order === void 0 ||
													null ===
														( a =
															order.currency ) ||
													void 0 === a
														? void 0
														: a.currency_pos
												}
												price={ Number( total ) }
											/>
										</Controls.TextWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
								{ ( order === null || order === void 0
									? void 0
									: order.is_included_tax ) && (
									<React.Fragment>
										<Controls.FlexWP
											align={ 'flex-start' }
											justify={ 'flex-start' }
											gap={ 0 }
											style={ {
												marginLeft: '45px',
											} }
										>
											<small>
												{ '(' }
												{ ( 0, I18n.__ )(
													'Including Tax: ',
													'ohmylms'
												) }
												<Price
													currency={
														order === null ||
														order === void 0 ||
														null ===
															( o =
																order.currency ) ||
														void 0 === o
															? void 0
															: o.currency
													}
													currency_pos={
														order === null ||
														order === void 0 ||
														null ===
															( i =
																order.currency ) ||
														void 0 === i
															? void 0
															: i.currency_pos
													}
													price={ Number(
														taxAmount
													) }
												/>
												{ ')' }
											</small>
										</Controls.FlexWP>
									</React.Fragment>
								) }
								<Controls.SpacerWP marginBottom={ 3 } />
							</Controls.SpacerWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					<Controls.DividerWP marginBottom={ 3 } marginTop={ 3 } />
					<Controls.SpacerWP
						marginBottom={ 0 }
						paddingY={ 3 }
						paddingX={ 4 }
					>
						<Controls.FlexWP
							justify={ 'space-between' }
							align={ 'flex-start' }
							gap={ 3 }
							className={ 'ohmylms-order-details-tfoot-row' }
						>
							<div />
							<Controls.SpacerWP marginBottom={ 0 }>
								{ summaryRow( 'Paid', ''.concat( total ) ) }
								<Controls.SpacerWP marginBottom={ 3 } />
								<Controls.SpacerWP marginTop={ 3 } />
								{ ( order === null ||
								order === void 0 ||
								null === ( l = order.refunds ) ||
								void 0 === l
									? void 0
									: l.length ) > 0 && (
									<React.Fragment>
										<Controls.FlexWP
											justify={ 'space-between' }
											align={ 'flex-start' }
											gap={ 3 }
											className={
												'ohmylms-order-details-tfoot-row'
											}
										>
											<div />
											<Controls.SpacerWP
												marginBottom={ 0 }
											>
												{ order.refunds.map(
													function ( e, _tLocal6 ) {
														return (
															<React.Fragment
																key={ _tLocal6 }
															>
																{ refundSummaryRow(
																	'Refunded',
																	''.concat(
																		e ===
																			null ||
																			e ===
																				void 0
																			? void 0
																			: e.total
																	)
																) }
																<Controls.SpacerWP
																	marginBottom={
																		3
																	}
																/>
															</React.Fragment>
														);
													}
												) }
												{ refundSummaryRow(
													( 0, I18n.__ )(
														'Net Payment',
														'ohmylms'
													),
													''.concat( remainingTotal )
												) }
												<Controls.SpacerWP
													marginBottom={ 3 }
												/>
											</Controls.SpacerWP>
										</Controls.FlexWP>
										<Controls.DividerWP
											marginBottom={ 3 }
											marginTop={ 3 }
										/>
									</React.Fragment>
								) }
								<Controls.SpacerWP marginBottom={ 3 } />
								{ Array.isArray( order.payment_gateway_meta ) &&
									order.payment_gateway_meta.map(
										function ( e, _tLocal7 ) {
											return (
												<React.Fragment
													key={ _tLocal7 }
												>
													{ summaryRow(
														e.label,
														e.value
													) }
													<Controls.SpacerWP
														marginBottom={ 3 }
													/>
												</React.Fragment>
											);
										}
									) }
							</Controls.SpacerWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					<Controls.DividerWP marginBottom={ 3 } marginTop={ 3 } />
					<Controls.SpacerWP
						marginBottom={ 0 }
						paddingTop={ 6 }
						paddingX={ 4 }
					>
						<Controls.FlexWP
							justify={ 'space-between' }
							align={ 'center' }
							gap={ 3 }
						>
							{ canRefund && (
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										setRefundOpen( ! 0 );
									} }
									style={ {
										width: '110px',
										justifyContent: 'center',
									} }
								>
									{ ( 0, I18n.__ )( 'Refund', 'ohmylms' ) }
								</Controls.ButtonWP>
							) }
							<Controls.FlexWP
								gap={ 2 }
								justify={ 'end' }
								direction={ 'row-reverse' }
								align={ 'center' }
							>
								<Controls.TextWP
									color={ '#000D25' }
									weight={ 400 }
									size={ 14 }
									align={ 'right' }
								>
									{ ( 0, I18n.__ )(
										'This order is no longer editable.',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Tooltip
									title={ ( 0, I18n.__ )(
										'Information about order edit.',
										'ohmylms'
									) }
								>
									<InfoIcon />
								</Tooltip>
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
					<div>
						{ isRefundOpen && (
							<Controls.ModalWP
								title={ ( 0, I18n.__ )(
									'Refund Order',
									'ohmylms'
								) }
								open={ isRefundOpen }
								onCancel={ closeRefund }
								shouldCloseOnEsc={ ! 0 }
								shouldCloseOnClickOutside={ ! 0 }
								onRequestClose={ closeRefund }
								size={ 'medium' }
							>
								<Controls.FlexWP
									direction={ 'column' }
									gap={ 4 }
								>
									<div>
										<label
											style={ {
												display: 'block',
												marginBottom: 4,
												fontWeight: 500,
												color: '#000D25',
											} }
											htmlFor={ _controlId + '-field-1' }
										>
											{ ( 0, I18n.__ )(
												'Refund Amount',
												'ohmylms'
											) }{ ' ' }
											<span
												style={ {
													color: 'red',
												} }
											>
												{ '*' }
											</span>
										</label>
										<NumberInput.A
											step={ 0.01 }
											precision={ 2 }
											placeholder={ '0.00' }
											prefix={
												'left' ===
												currencyOptions.currencyPosition
													? currencyOptions.currencySymbol
													: void 0
											}
											suffix={
												'right' ===
												currencyOptions.currencyPosition
													? currencyOptions.currencySymbol
													: void 0
											}
											max={ total }
											min={ 0 }
											value={ refund.amount }
											onChange={ function ( e ) {
												return updateRefundField(
													'amount',
													e
												);
											} }
											onKeyDown={ function ( e ) {
												if ( ! (
													'-' !== e.key &&
													'Minus' !== e.key
												) ) {
													e.preventDefault();
												}
											} }
											id={ _controlId + '-field-1' }
										/>
									</div>
									<div>
										<label
											style={ {
												display: 'block',
												marginBottom: 4,
												fontWeight: 500,
												color: '#000D25',
											} }
											htmlFor={ _controlId + '-field-2' }
										>
											{ ( 0, I18n.__ )(
												'Refund Reason',
												'ohmylms'
											) }{ ' ' }
											<span
												style={ {
													color: 'red',
												} }
											>
												{ '*' }
											</span>
										</label>
										<Textarea.A
											rows={ 4 }
											onChange={ function ( e ) {
												return updateRefundField(
													'reason',
													e
												);
											} }
											id={ _controlId + '-field-2' }
										/>
									</div>
									<div>
										<Controls.ButtonWP
											loading={ isSubmitting }
											onClick={ submitRefund }
											variant={ 'primary' }
										>
											{ ( 0, I18n.__ )(
												'Process Refund',
												'ohmylms'
											) }
										</Controls.ButtonWP>
									</div>
								</Controls.FlexWP>
							</Controls.ModalWP>
						) }
					</div>
				</Controls.SpacerWP>
			</Controls.CardWP>
		);
	};
}
