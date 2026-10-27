import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import * as api from './api.mjs';
import { allGroups } from './syllabus.mjs';

/**
 * Find a skill already in the library and place it in this group.
 * @param root0
 * @param root0.outline
 * @param root0.pending
 * @param root0.onPlace
 * @param root0.defaultOpen
 */
export function ExistingSkill( {
	outline,
	pending,
	onPlace,
	defaultOpen = false,
} ) {
	const [ open, setOpen ] = useState( defaultOpen );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const placed = new Set(
		allGroups( outline ).flatMap( ( { group } ) =>
			group.skills.map( ( skill ) => skill.term_id )
		)
	);
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		let current = true;
		const timer = setTimeout(
			() => {
				api.searchTargets( 'skill', search )
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
	}, [ open, search ] );
	const available = results.filter( ( row ) => ! placed.has( row.id ) );
	return (
		<details
			className="ohmylms-syl-existing"
			open={ open }
			onToggle={ ( event ) => setOpen( event.currentTarget.open ) }
		>
			<summary>
				{ __(
					'Or add a skill that is already in the skill library',
					'ohmylms'
				) }
			</summary>
			<TextControl
				label={ __( 'Find a skill', 'ohmylms' ) }
				type="search"
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			{ error && <p role="alert">{ error }</p> }
			{ open && ! error && ! available.length && (
				<p className="ohmylms-ext-muted">
					{ __( 'No more matches.', 'ohmylms' ) }
				</p>
			) }
			<ul className="ohmylms-cur-link-list ohmylms-cur-link-results">
				{ available.map( ( row ) => (
					<li key={ row.id }>
						<span>
							{ row.title }
							{ row.code ? ` (${ row.code })` : '' }
						</span>
						<Button
							variant="secondary"
							size="small"
							disabled={ pending }
							aria-label={ sprintf(
								/* translators: %s: curriculum item name. */ __(
									'Add %s to this folder',
									'ohmylms'
								),
								row.title
							) }
							onClick={ () => onPlace( row.id ) }
						>
							{ __( 'Add', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
		</details>
	);
}
