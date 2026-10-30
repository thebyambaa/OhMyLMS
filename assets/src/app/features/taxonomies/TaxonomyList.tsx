import { ListTable } from '../../../ui/ListTable';
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { parentLabel } from './model';
import { useTermDetail, useTerms } from './useTerms';
import type { TaxonomyInfo } from './model';
import type { Term } from './types';

function CoursesDialog( {
	info,
	term,
	onClose,
}: {
	info: TaxonomyInfo;
	term: Term;
	onClose: () => void;
} ) {
	const detail = useTermDetail( info.kind, term.id );
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: category or tag name. */
				__( 'Courses in %s', 'ohmylms' ),
				term.name
			) }
			onRequestClose={ onClose }
		>
			{ detail.loading && (
				<p role="status">{ __( 'Loading courses…', 'ohmylms' ) }</p>
			) }
			{ detail.error && (
				<Notice status="error" isDismissible={ false }>
					{ detail.error.message }
				</Notice>
			) }
			{ ! detail.loading &&
				detail.data &&
				( detail.data.courses.length ? (
					<ul>
						{ detail.data.courses.map( ( course ) => (
							<li key={ course.id }>{ course.title }</li>
						) ) }
					</ul>
				) : (
					<p>{ __( 'No courses are linked.', 'ohmylms' ) }</p>
				) ) }
			<Button variant="primary" onClick={ onClose }>
				{ __( 'Close', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}

/**
 * Searchable, paged, read-only list of one taxonomy.
 *
 * @param props      Component props.
 * @param props.info Taxonomy to show.
 */
export function TaxonomyList( { info }: { info: TaxonomyInfo } ) {
	const { user } = useBootstrap();
	const list = useTerms( info.kind );
	const [ text, setText ] = useState( '' );
	const [ opened, setOpened ] = useState< Term | null >( null );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const hierarchical = 'categories' === info.kind;
	const canManage = true === user.capabilities.manage_options;
	const searchLabel = sprintf(
		/* translators: %s: Categories or Tags. */
		__( 'Search %s', 'ohmylms' ),
		info.title
	);

	return (
		<>
			<p>{ info.replacedBy }</p>
			{ canManage && (
				<p>
					<a href={ info.manageUrl }>{ info.manageLabel }</a>
				</p>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">
					{ sprintf(
						/* translators: %s: Categories or Tags. */
						__( 'Loading %s…', 'ohmylms' ),
						info.title.toLowerCase()
					) }
				</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable< Term >
					caption={ info.title }
					formLabel={ searchLabel }
					rows={ items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'Nothing found.', 'ohmylms' ) }
					search={ {
						label: searchLabel,
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						onChange: setText,
						onSubmit: () => list.setSearch( text ),
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of items. */
							_n(
								'%d item found.',
								'%d items found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong className="row-title">
									{ item.name }
								</strong>
							),
						},
						{
							id: 'slug',
							label: __( 'Slug', 'ohmylms' ),
							render: ( item ) => item.slug,
						},
						{
							id: 'parent',
							label: __( 'Parent', 'ohmylms' ),
							hidden: ! hierarchical,
							render: ( item ) => parentLabel( item, items ),
						},
						{
							id: 'posts',
							label: __( 'Courses', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.count,
						},
					] }
					rowActions={ ( item ) => [
						canManage && {
							key: 'view',
							label: __( 'Show courses', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: category or tag name. */
								__( 'Show courses in %s', 'ohmylms' ),
								item.name
							),
							onClick: () => setOpened( item ),
						},
					] }
				/>
			) }
			{ opened && (
				<CoursesDialog
					info={ info }
					term={ opened }
					onClose={ () => setOpened( null ) }
				/>
			) }
		</>
	);
}
