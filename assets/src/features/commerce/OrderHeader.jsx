/**
 * OrderHeader component (replaces recovered binding XY).
 * Displays order title, ID, payment method, transaction link, and creation date.
 */
import { createElement } from '@wordpress/element';
export function createOrderHeader( readRuntime ) {
	return function OrderHeader( { order } ) {
		const {
			Ge: decodeEntities,
			I: Controls,
			JY: formatOrderDate,
			React,
			b: I18n,
		} = readRuntime();
		if ( ! order ) {
			return null;
		}
		return (
			<React.Fragment>
				<Controls.HeadingWP
					level={ 4 }
					size={ 18 }
					weight={ 500 }
					color="#000D25"
				>
					Order #{ order.id }
				</Controls.HeadingWP>
				<Controls.FlexWP gap={ 2 } justify="start" align="center">
					<Controls.BadgeWP isBorderLess={ true }>
						<Controls.TextWP>
							{ I18n.__( 'Payment via', 'ohmylms' ) }{ ' ' }
							{ decodeEntities( order.payment_method_title ) }
							&nbsp;
							{ order.transaction_id && order.transaction_url && (
								<Controls.ButtonWP
									href={ order.transaction_url }
									target="_blank"
									variant="link"
									style={ {
										textDecoration: 'none',
									} }
								>
									({ order.transaction_id })
								</Controls.ButtonWP>
							) }
						</Controls.TextWP>
					</Controls.BadgeWP>
					<Controls.BadgeWP isBorderLess={ true }>
						<Controls.TextWP>
							{ I18n.__( 'Created on', 'ohmylms' ) }{ ' ' }
							{ formatOrderDate( order.date_created ) }
						</Controls.TextWP>
					</Controls.BadgeWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
