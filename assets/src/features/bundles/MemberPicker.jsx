import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import * as api from './api.mjs';
import { memberKey, MAX_MEMBERS } from './model.mjs';
const STATUS = {
	draft: () => __( 'Draft', 'ohmylms' ),
	pending: () => __( 'Pending', 'ohmylms' ),
	private: () => __( 'Private', 'ohmylms' ),
	future: () => __( 'Scheduled', 'ohmylms' ),
};

/**
 * Find courses or curriculum items to add. A bundle holds only what an administrator picks:
 * nothing is added because it shares a subject or name with another member.
 * @param root0
 * @param root0.members
 * @param root0.onAdd
 * @param root0.disabled
 */
export function MemberPicker( { members, onAdd, disabled } ) {
	const [ type, setType ] = useState( 'course' );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const keys = new Set( members.map( memberKey ) );
	const full = members.length >= MAX_MEMBERS;
	useEffect( () => {
		let current = true;
		const timer = setTimeout(
			() => {
				api.searchMembers( type, search )
					.then(
						( rows ) =>
							current &&
							( setResults( rows || [] ), setError( '' ) )
					)
					.catch(
						( cause ) =>
							current &&
							( setResults( [] ),
							setError(
								cause?.message ||
									__( 'Could not search.', 'ohmylms' )
							) )
					);
			},
			search ? 300 : 0
		);
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ type, search ] );
	const candidates = results.filter(
		( row ) => ! keys.has( `${ type }:${ row.id }` )
	);
	return (
		<div className="ohmylms-bundle-picker">
			<SelectControl
				label={ __( 'Add from', 'ohmylms' ) }
				value={ type }
				options={ [
					{
						value: 'course',
						label: __( 'Courses', 'ohmylms' ),
					},
					{
						value: 'curriculum',
						label: __(
							'Curriculum items and syllabuses',
							'ohmylms'
						),
					},
				] }
				onChange={ ( value ) => {
					setType( value );
					setSearch( '' );
				} }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={
					type === 'course'
						? __( 'Find a course', 'ohmylms' )
						: __( 'Find a curriculum item', 'ohmylms' )
				}
				type="search"
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			{ full && (
				<p role="note">
					{ sprintf(
						/* translators: %d: number of bundle members. */ __(
							'A bundle can have at most %d members.',
							'ohmylms'
						),
						MAX_MEMBERS
					) }
				</p>
			) }
			{ error && <p role="alert">{ error }</p> }
			{ ! error && ! candidates.length && (
				<p className="ohmylms-ext-muted">
					{ __( 'No more matches.', 'ohmylms' ) }
				</p>
			) }
			<ul className="ohmylms-cur-link-list ohmylms-cur-link-results">
				{ candidates.map( ( row ) => (
					<li key={ row.id }>
						<span>
							{ row.title }
							{ type === 'curriculum' && row.path?.length ? (
								<small className="ohmylms-ext-muted">
									{ ' ' }
									{ row.path.join( ' › ' ) }
								</small>
							) : null }
							{ type === 'curriculum' && row.code
								? ` (${ row.code }${ row.version ? `, ${ row.version }` : '' })`
								: '' }
						</span>
						{ STATUS[ row.status ] && (
							<span className="ohmylms-cur-badge">
								{ STATUS[ row.status ]() }
							</span>
						) }
						<Button
							variant="secondary"
							size="small"
							disabled={ disabled || full }
							aria-label={ sprintf(
								/* translators: %s: course bundle or member title. */ __(
									'Add %s to the bundle',
									'ohmylms'
								),
								row.title
							) }
							onClick={ () =>
								onAdd( {
									type,
									id: row.id,
									title: row.title,
									status: row.status || 'active',
									available: true,
									path: row.path || [],
									code: row.code || '',
									version: row.version || '',
									item_type: row.item_type || '',
								} )
							}
						>
							{ __( 'Add', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
		</div>
	);
}
