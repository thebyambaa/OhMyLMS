import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Badge } from '../../../ui/Badge';
import { useOperation } from '../../hooks/useOperation';
import {
	addItemLink,
	removeItemLink,
	searchLinkTargets,
	type ItemLinks,
	type LinkRow,
	type LinkType,
} from './api';

/**
 * One kind of content linked to the open item: what is linked, a search to link more, and a way to take
 * a link off. Linking only says the content belongs under the item; the content is never copied or deleted.
 *
 * @param props          Component props.
 * @param props.item     Open item ID.
 * @param props.type     What this pane links.
 * @param props.rows     What is linked now.
 * @param props.empty    Text when nothing is linked.
 * @param props.addLabel Label of the search box.
 * @param props.href     Where a linked row opens, when it can.
 * @param props.onLinks  Receives the item's links after a change.
 */
export function LinksPane( {
	item,
	type,
	rows,
	empty,
	addLabel,
	href,
	onLinks,
}: {
	item: number;
	type: LinkType;
	rows: readonly LinkRow[];
	empty: string;
	addLabel: string;
	href?: ( id: number ) => string;
	onLinks: ( links: ItemLinks ) => void;
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState<
		{ id: number; title: string; status: string }[]
	>( [] );
	const operation = useOperation();
	useEffect( () => {
		if ( ! search.trim() ) {
			setFound( [] );
			return undefined;
		}
		const controller = new AbortController();
		const timer = setTimeout( () => {
			searchLinkTargets( type, search, controller.signal ).then(
				( result ) => ! controller.signal.aborted && setFound( result ),
				() => undefined
			);
		}, 250 );
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ search, type ] );
	const linked = new Set( rows.map( ( row ) => row.id ) );
	const options = found.filter( ( row ) => ! linked.has( row.id ) );
	return (
		<div className="oml-cw-links">
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ rows.length > 0 ? (
				<ul className="oml-cw-linklist">
					{ rows.map( ( row ) => (
						<li key={ row.id }>
							{ href && row.available ? (
								<Link to={ href( row.id ) }>{ row.title }</Link>
							) : (
								<span>
									{ row.title ||
										__( 'Unavailable item', 'ohmylms' ) }
								</span>
							) }
							<Badge>{ row.status }</Badge>
							<Button
								size="small"
								variant="tertiary"
								isDestructive
								disabled={ operation.busy }
								label={ sprintf(
									/* translators: %s: title of the linked item. */
									__( 'Remove %s from this item', 'ohmylms' ),
									row.title
								) }
								onClick={ () =>
									void operation.run(
										( signal ) =>
											removeItemLink(
												item,
												type,
												row.id,
												signal
											),
										onLinks
									)
								}
							>
								{ __( 'Remove', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p className="oml-cw-empty">{ empty }</p>
			) }
			<TextControl
				label={ addLabel }
				type="search"
				value={ search }
				onChange={ setSearch }
			/>
			{ options.length > 0 && (
				<ul className="oml-cw-linklist is-results">
					{ options.map( ( row ) => (
						<li key={ row.id }>
							<span>{ row.title }</span>
							<Badge>{ row.status }</Badge>
							<Button
								size="small"
								variant="secondary"
								disabled={ operation.busy }
								label={ sprintf(
									/* translators: %s: title of the item to add. */
									__( 'Add %s', 'ohmylms' ),
									row.title
								) }
								onClick={ () =>
									void operation.run(
										( signal ) =>
											addItemLink(
												item,
												type,
												row.id,
												signal
											),
										( links ) => {
											setSearch( '' );
											onLinks( links );
										}
									)
								}
							>
								{ __( 'Add', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
