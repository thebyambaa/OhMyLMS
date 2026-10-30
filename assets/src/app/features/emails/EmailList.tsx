import { useState } from '@wordpress/element';
import {
	Button,
	Notice,
	ToggleControl,
	VisuallyHidden,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { useEmails } from './hooks';
import { editorPath } from './model';
import type { EmailFilters } from './types';

export function EmailList() {
	const list = useEmails();
	const [ query, setQuery ] = useState( '' );
	const [ recipient, setRecipient ] =
		useState< EmailFilters[ 'recipient_type' ] >( 'all' );

	return (
		<section aria-labelledby="oml-emails-list-title">
			<h3 id="oml-emails-list-title">
				{ __( 'System emails', 'ohmylms' ) }
			</h3>
			{ list.toggleError && (
				<Notice
					status="error"
					onRemove={ list.clearToggleError }
					politeness="assertive"
				>
					{ list.toggleError }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading emails…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'System emails', 'ohmylms' ) }
					formLabel={ __( 'Search emails', 'ohmylms' ) }
					rows={ list.items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No emails found.', 'ohmylms' ) }
					search={ {
						label: __( 'Search emails', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: query,
						onChange: setQuery,
					} }
					filters={
						<ListTableSelect
							label={ __( 'Sent to', 'ohmylms' ) }
							value={ recipient }
							options={ [
								{
									value: 'all',
									label: __( 'Everyone', 'ohmylms' ),
								},
								{
									value: 'student',
									label: __( 'Students', 'ohmylms' ),
								},
								{
									value: 'creator',
									label: __(
										'Instructors and creators',
										'ohmylms'
									),
								},
							] }
							onChange={ ( value ) =>
								setRecipient(
									'student' === value || 'creator' === value
										? value
										: 'all'
								)
							}
						/>
					}
					onFilter={ () =>
						list.setFilters( {
							search: query,
							recipient_type: recipient,
						} )
					}
					pagination={ {
						page: 1,
						totalPages: 1,
						totalItems: list.total,
						itemsLabel: sprintf(
							/* translators: %d: number of emails. */
							_n(
								'%d email found.',
								'%d emails found.',
								list.total,
								'ohmylms'
							),
							list.total
						),
						onChange: () => {},
					} }
					columns={ [
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<>
									<strong>
										<Link
											className="row-title"
											to={ editorPath( item.id ) }
										>
											{ item.title }
										</Link>
									</strong>
									<p className="description">
										{ item.description }
									</p>
								</>
							),
						},
						{
							id: 'recipient',
							label: __( 'Sent to', 'ohmylms' ),
							render: ( item ) =>
								'student' === item.recipient_type
									? __( 'Student', 'ohmylms' )
									: __( 'Instructor or creator', 'ohmylms' ),
						},
						{
							id: 'subject',
							label: __( 'Subject', 'ohmylms' ),
							render: ( item ) => item.subject,
						},
						{
							id: 'enabled',
							label: __( 'Sent', 'ohmylms' ),
							render: ( item ) => (
								<ToggleControl
									__nextHasNoMarginBottom
									label={
										<VisuallyHidden>
											{ sprintf(
												/* translators: %s: email name. */
												__( 'Send “%s”', 'ohmylms' ),
												item.title
											) }
										</VisuallyHidden>
									}
									checked={ item.enabled }
									disabled={ list.pending.includes(
										item.id
									) }
									onChange={ ( checked ) => {
										void list.toggle( item.id, checked );
									} }
								/>
							),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link to={ editorPath( item.id ) }>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</section>
	);
}
