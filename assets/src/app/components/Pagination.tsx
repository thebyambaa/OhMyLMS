import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { MouseEvent } from 'react';

export function Pagination( {
	page,
	pages,
	busy,
	onChange,
	href,
}: {
	page: number;
	pages: number;
	busy: boolean;
	onChange: ( next: number ) => void;
	href?: ( next: number ) => string;
} ) {
	const move = ( next: number ) => ( event: MouseEvent ) => {
		if (
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}
		event.preventDefault();
		onChange( next );
	};
	const previousDisabled = busy || page <= 1;
	const nextDisabled = busy || page >= pages;
	return (
		<nav aria-label={ __( 'Pagination', 'ohmylms' ) }>
			<Button
				variant="secondary"
				disabled={ previousDisabled }
				href={ previousDisabled ? undefined : href?.( page - 1 ) }
				onClick={ move( page - 1 ) }
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
				disabled={ nextDisabled }
				href={ nextDisabled ? undefined : href?.( page + 1 ) }
				onClick={ move( page + 1 ) }
			>
				{ __( 'Next page', 'ohmylms' ) }
			</Button>
		</nav>
	);
}
