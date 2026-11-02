import { createPortal } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useAdminPlace } from '../routing/place';

/**
 * Id of the element after `h1.wp-heading-inline` that includes/Admin/AdminNavigation.php prints for the
 * screen's own actions.
 */
export const TITLE_ACTIONS_ID = 'ohmylms-admin-title-actions';

/**
 * A screen's primary actions ("Add course", "Import quiz"), shown as WordPress shows them: as
 * `.page-title-action` next to the page heading (`h1.wp-heading-inline`). On the admin pages they are
 * rendered into the heading region the server printed; elsewhere (tests, a memory router) in place.
 *
 * @param props          Component props.
 * @param props.children `PageTitleAction` elements.
 */
export function PageTitleActions( { children }: { children: ReactNode } ) {
	const place = useAdminPlace();
	const slot = place ? document.getElementById( TITLE_ACTIONS_ID ) : null;
	if ( slot ) {
		return createPortal( children, slot );
	}
	return <p className="ohmylms-title-actions">{ children }</p>;
}

type ActionProps = {
	children: ReactNode;
	/** Accessible name when it differs from the text. */
	label?: string;
} & (
	| { to: string; onClick?: undefined; disabled?: undefined }
	| { onClick: () => void; disabled?: boolean; to?: undefined }
);

/**
 * One `.page-title-action`: a link to a route (`to`, a real address that can be opened in a new tab) or
 * a button that opens a dialog (`onClick`). Use inside `PageTitleActions`.
 *
 * @param props Action properties.
 */
export function PageTitleAction( props: ActionProps ) {
	if ( undefined !== props.to ) {
		return (
			<>
				{ ' ' }
				<Link
					className="page-title-action"
					to={ props.to }
					aria-label={ props.label }
				>
					{ props.children }
				</Link>
			</>
		);
	}
	return (
		<>
			{ ' ' }
			<button
				type="button"
				className="page-title-action"
				onClick={ props.onClick }
				disabled={ props.disabled }
				aria-label={ props.label }
			>
				{ props.children }
			</button>
		</>
	);
}
