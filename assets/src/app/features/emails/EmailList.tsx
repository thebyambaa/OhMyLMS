import { useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
	VisuallyHidden,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Table } from '../../../ui/Table';
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
			<form
				role="search"
				aria-label={ __( 'Search emails', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setFilters( {
						search: query,
						recipient_type: recipient,
					} );
				} }
			>
				<TextControl
					label={ __( 'Search emails', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<SelectControl
					label={ __( 'Sent to', 'ohmylms' ) }
					value={ recipient }
					options={ [
						{ value: 'all', label: __( 'Everyone', 'ohmylms' ) },
						{
							value: 'student',
							label: __( 'Students', 'ohmylms' ),
						},
						{
							value: 'creator',
							label: __( 'Instructors and creators', 'ohmylms' ),
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
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>

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
			{ ! list.loading &&
				! list.error &&
				( list.items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of emails. */
								_n(
									'%d email found.',
									'%d emails found.',
									list.total,
									'ohmylms'
								),
								list.total
							) }
						</p>
						<Table
							caption={ __( 'System emails', 'ohmylms' ) }
							striped
						>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Email', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Sent to', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Subject', 'ohmylms' ) }
									</th>
									<th scope="col">
										{ __( 'Sent', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ list.items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											<Link to={ editorPath( item.id ) }>
												{ item.title }
											</Link>
											<br />
											<small>{ item.description }</small>
										</th>
										<td>
											{ 'student' === item.recipient_type
												? __( 'Student', 'ohmylms' )
												: __(
														'Instructor or creator',
														'ohmylms'
													) }
										</td>
										<td>{ item.subject }</td>
										<td>
											<ToggleControl
												__nextHasNoMarginBottom
												label={
													<VisuallyHidden>
														{ sprintf(
															/* translators: %s: email name. */
															__(
																'Send “%s”',
																'ohmylms'
															),
															item.title
														) }
													</VisuallyHidden>
												}
												checked={ item.enabled }
												disabled={ list.pending.includes(
													item.id
												) }
												onChange={ ( checked ) => {
													void list.toggle(
														item.id,
														checked
													);
												} }
											/>
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
					</>
				) : (
					<p>{ __( 'No emails found.', 'ohmylms' ) }</p>
				) ) }
		</section>
	);
}
