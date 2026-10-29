/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
import * as Controls from '../../shared/controls';
import { highlightAdminMenu } from '../../shared/utils';
import { PageHeader } from '../../shared/media';
export function createCouponsPage( readRuntime ) {
	return function CouponsPage() {
		const { Gre, React, b: I18n } = readRuntime();
		highlightAdminMenu( 'ohmylms', 'coupons' );
		var e = useNotifications().contextHolder;
		return (
			<React.Fragment>
				{ e }
				<Controls.ContainerWP>
					<PageHeader
						title={ ( 0, I18n.__ )( 'Coupon', 'ohmylms' ) }
						showAddButton={ ! 1 }
					/>
					<Gre />
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
