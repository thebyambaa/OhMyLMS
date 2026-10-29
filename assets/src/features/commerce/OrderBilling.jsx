/**
 * OrderBilling component (replaces recovered binding eQ).
 * Displays customer billing info: name, address, email, phone.
 */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createOrderBilling( readRuntime ) {
	return function OrderBilling( { order, address, email } ) {
		const { Ge: decodeEntities, React, b: I18n } = readRuntime();
		return (
			<React.Fragment>
				<Controls.HeadingWP
					level={ 4 }
					size={ 18 }
					weight={ 500 }
					color="#000D25"
				>
					{ I18n.__( 'Billing', 'ohmylms' ) }
				</Controls.HeadingWP>
				<Controls.SpacerWP marginBottom={ 4 } />
				<Controls.FlexWP
					direction="column"
					gap={ 3 }
					justify="start"
					align="start"
				>
					<Controls.TextWP size="14px" color="#7A8B9A">
						<strong
							style={ {
								color: '#000D21',
							} }
						>
							{ I18n.__( 'Name:', 'ohmylms' ) + ' ' }
						</strong>{ ' ' }
						{ decodeEntities( order?.student_name ) }
					</Controls.TextWP>
					<Controls.TextWP
						variant="muted"
						size="14px"
						color="#7A8B9A"
					>
						<strong
							style={ {
								color: '#000D21',
							} }
						>
							{ I18n.__( 'Address:', 'ohmylms' ) + ' ' }
						</strong>{ ' ' }
						{ decodeEntities( address ) }
					</Controls.TextWP>
					<Controls.TextWP size="14px" color="#7A8B9A">
						<strong
							style={ {
								color: '#000D21',
							} }
						>
							{ I18n.__( 'Email address:', 'ohmylms' ) + ' ' }
						</strong>{ ' ' }
						<Controls.ButtonWP
							variant="link"
							href={ `mailto:${ email }` }
						>
							{ email }
						</Controls.ButtonWP>
					</Controls.TextWP>
					{ order?.student_phone && (
						<Controls.TextWP size="14px" color="#7A8B9A">
							<strong
								style={ {
									color: '#000D21',
								} }
							>
								{ I18n.__( 'Phone:', 'ohmylms' ) + ' ' }
							</strong>{ ' ' }
							{ order.student_phone }
						</Controls.TextWP>
					) }
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
