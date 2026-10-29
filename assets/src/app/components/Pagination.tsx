import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export function Pagination( {
	page,
	pages,
	busy,
	onChange,
}: {
	page: number;
	pages: number;
	busy: boolean;
	onChange: ( next: number ) => void;
} ) {
	return (
		<nav aria-label={ __( 'Pagination', 'ohmylms' ) }>
			<Button
				variant="secondary"
				disabled={ busy || page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			>
				{ __( 'Previous page', 'ohmylms' ) }
			</Button>{ ' ' }
			<span>
				{ sprintf(
					/* translators: %1$d: current page; %2$d: total pages. */
					__( 'Page %1$d of %2$d', 'ohmylms' ),
					page,
					Math.max( 1, pages )
				) }
			</span>{ ' ' }
			<Button
				variant="secondary"
				disabled={ busy || page >= pages }
				onClick={ () => onChange( page + 1 ) }
			>
				{ __( 'Next page', 'ohmylms' ) }
			</Button>
		</nav>
	);
}
