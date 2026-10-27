/**
 * CustomerHistory component (replaces recovered binding RQ).
 * Displays customer order metrics: total orders, total revenue, average order value.
 */
import { createElement } from '@wordpress/element';

export function createCustomerHistory( readRuntime ) {
	return function CustomerHistory( { order } ) {
		const { I: Controls, React, b: I18n, g: ReactHooks } = readRuntime();

		const [ isOpen, setIsOpen ] = ReactHooks.useState( true );

		return (
			<React.Fragment>
				<Controls.FlexWP
					gap={ 2 }
					justify="space-between"
					align="center"
				>
					<Controls.HeadingWP
						level={ 4 }
						size={ 18 }
						weight={ 500 }
						color="#000D25"
					>
						{ I18n.__( 'Customer history', 'ohmylms' ) }
					</Controls.HeadingWP>
					<Controls.ButtonWP
						size="small"
						onClick={ () => setIsOpen( ! isOpen ) }
					>
						<svg
							style={ {
								transform: isOpen
									? 'rotate(0deg)'
									: 'rotate(180deg)',
							} }
							width="12"
							height="6"
							fill="none"
							viewBox="0 0 12 6"
							xmlns="http://www.w3.org/2000/svg"
						>
							<path
								fill="#000D25"
								d="M11.5 4.4L6 0 .5 4.4l.9 1.2L6 2l4.5 3.6 1-1.2z"
							/>
						</svg>
					</Controls.ButtonWP>
				</Controls.FlexWP>
				{ isOpen && (
					<React.Fragment>
						<Controls.SpacerWP marginTop={ 6 } marginBottom={ 0 } />
						{ order?.total_orders ? (
							<React.Fragment>
								<Controls.TextWP
									as="p"
									color="#7A8B9A"
									size={ 16 }
									weight={ 500 }
									lineHeight={ 1.5 }
								>
									{ I18n.__( 'Total orders', 'ohmylms' ) }
									<span
										style={ {
											color: '#000D25',
											display: 'block',
										} }
										dangerouslySetInnerHTML={ {
											__html: order.total_orders,
										} }
									/>
								</Controls.TextWP>
								<Controls.SpacerWP marginBottom={ 2 } />
								<Controls.TextWP
									as="p"
									size={ 16 }
									weight={ 500 }
									color="#7A8B9A"
								>
									{ I18n.__( 'Total Revenue', 'ohmylms' ) }
									<span
										style={ {
											color: '#000D25',
											display: 'block',
										} }
										dangerouslySetInnerHTML={ {
											__html: order.total_revenue,
										} }
									/>
								</Controls.TextWP>
								<Controls.SpacerWP marginBottom={ 2 } />
								<Controls.TextWP
									as="p"
									color="#7A8B9A"
									size={ 16 }
									weight={ 500 }
									lineHeight={ 1.5 }
								>
									{ I18n.__(
										'Average order value',
										'ohmylms'
									) }
									<span
										style={ {
											color: '#000D25',
											display: 'block',
										} }
										dangerouslySetInnerHTML={ {
											__html: order.aov,
										} }
									/>
								</Controls.TextWP>
							</React.Fragment>
						) : (
							<Controls.TextWP
								as="p"
								color="#7A8B9A"
								size={ 16 }
								weight={ 400 }
								lineHeight={ 1.5 }
							>
								{ I18n.__( 'No history found', 'ohmylms' ) }
							</Controls.TextWP>
						) }
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
