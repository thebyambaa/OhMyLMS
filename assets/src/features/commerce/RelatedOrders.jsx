/**
 * RelatedOrders component (replaces recovered binding kQ).
 * Displays related orders table (linked subscriptions, renewal orders, parent orders).
 */
import { createElement } from '@wordpress/element';

export function createRelatedOrders( readRuntime ) {
	return function RelatedOrders( { relatedOrders = [] } ) {
		const {
			Ge: decodeEntities,
			I: Controls,
			OQ: formatPrice,
			React,
			VY: formatDateTime,
			b: I18n,
		} = readRuntime();

		if ( ! Array.isArray( relatedOrders ) || relatedOrders.length === 0 ) {
			return null;
		}

		const columns = [
			{
				title: I18n.__( 'Order #', 'ohmylms' ),
				key: 'order_number',
				render: ( _, record ) => {
					let link = null;
					if (
						record.relationship ===
						I18n.__( 'Subscription', 'ohmylms' )
					) {
						link = `/wp-admin/admin.php?page=ohmylms#/subscription-edit/${ record.id }`;
					} else if (
						record.relationship ===
							I18n.__( 'Renewal Order', 'ohmylms' ) ||
						record.relationship === I18n.__( 'Parent', 'ohmylms' )
					) {
						link = `/wp-admin/admin.php?page=ohmylms#/order-edit/${ record.id }`;
					}

					if ( link ) {
						return (
							<a
								href={ link }
								target="_blank"
								rel="noopener noreferrer"
							>
								<Controls.TextWP
									as="span"
									color="#000D25"
									weight={ 500 }
									size={ 14 }
								>
									#{ record.id }
								</Controls.TextWP>
							</a>
						);
					}

					return (
						<Controls.TextWP
							as="span"
							color="#000D25"
							weight={ 500 }
							size={ 14 }
						>
							#{ record.id }
						</Controls.TextWP>
					);
				},
			},
			{
				title: I18n.__( 'Relationship', 'ohmylms' ),
				key: 'relationship',
				render: ( _, record ) => (
					<Controls.TextWP
						as="span"
						color="#7A8B9A"
						weight={ 400 }
						size={ 14 }
					>
						{ decodeEntities( record.relationship ) }
					</Controls.TextWP>
				),
			},
			{
				title: I18n.__( 'Date', 'ohmylms' ),
				key: 'date',
				dataIndex: 'date',
				render: ( date ) => (
					<Controls.TextWP
						as="span"
						color="#7A8B9A"
						weight={ 400 }
						size={ 14 }
					>
						{ formatDateTime( date ) ||
							I18n.__( 'N/A', 'ohmylms' ) }
					</Controls.TextWP>
				),
			},
			{
				title: I18n.__( 'Status', 'ohmylms' ),
				key: 'status',
				render: ( _, record ) => (
					<Controls.TextWP
						as="span"
						color="#7A8B9A"
						weight={ 400 }
						size={ 14 }
					>
						{ record.status }
					</Controls.TextWP>
				),
			},
			{
				title: I18n.__( 'Total', 'ohmylms' ),
				key: 'total',
				render: ( _, record ) => (
					<Controls.TextWP
						as="span"
						color="#000D25"
						weight={ 500 }
						size={ 14 }
						dangerouslySetInnerHTML={ {
							__html: formatPrice( record.total ),
						} }
					/>
				),
			},
		];

		return (
			<React.Fragment>
				<Controls.HeadingWP
					level={ 4 }
					size={ 18 }
					weight={ 500 }
					color="#000D25"
				>
					{ I18n.__( 'Related Orders', 'ohmylms' ) }
				</Controls.HeadingWP>
				<Controls.SpacerWP marginBottom={ 4 } />
				<Controls.CardWP isBorderless={ true }>
					<Controls.SpacerWP
						paddingX={ 4 }
						paddingY={ 5 }
						marginBottom={ 0 }
					>
						<Controls.TableWP
							rowKey="order_number"
							columns={ columns }
							dataSource={ relatedOrders }
							className="ohmylms-related-orders-table"
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
