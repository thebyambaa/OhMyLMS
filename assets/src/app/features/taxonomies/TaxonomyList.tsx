import { Table } from '../../../ui/Table';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { Pagination } from '../../components/Pagination';
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

	return (
		<>
			<p>{ info.replacedBy }</p>
			{ canManage && (
				<p>
					<a href={ info.manageUrl }>{ info.manageLabel }</a>
				</p>
			) }
			<form
				role="search"
				aria-label={ sprintf(
					/* translators: %s: Categories or Tags. */
					__( 'Search %s', 'ohmylms' ),
					info.title
				) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setSearch( text );
				} }
			>
				<TextControl
					label={ sprintf(
						/* translators: %s: Categories or Tags. */
						__( 'Search %s', 'ohmylms' ),
						info.title
					) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
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
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of items. */
								_n(
									'%d item found.',
									'%d items found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table caption={ info.title } striped>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Name', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Slug', 'ohmylms' ) }
									</th>
									{ hierarchical && (
										<th scope="col">
											{ __( 'Parent', 'ohmylms' ) }
										</th>
									) }
									<th scope="col">
										{ __( 'Courses', 'ohmylms' ) }
									</th>
									{ canManage && (
										<th scope="col">
											{ __( 'Actions', 'ohmylms' ) }
										</th>
									) }
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">{ item.name }</th>
										<td>{ item.slug }</td>
										{ hierarchical && (
											<td>
												{ parentLabel( item, items ) }
											</td>
										) }
										<td>{ item.count }</td>
										{ canManage && (
											<td>
												<Button
													variant="link"
													aria-label={ sprintf(
														/* translators: %s: category or tag name. */
														__(
															'Show courses in %s',
															'ohmylms'
														),
														item.name
													) }
													onClick={ () =>
														setOpened( item )
													}
												>
													{ __(
														'Show courses',
														'ohmylms'
													) }
												</Button>
											</td>
										) }
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.query.page }
							pages={ list.data?.totalPages ?? 1 }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'Nothing found.', 'ohmylms' ) }</p>
				) ) }
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
