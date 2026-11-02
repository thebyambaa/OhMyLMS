import { Button, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { searchSkills } from './api';
import type { SkillRef } from './types';

/**
 * Pick skills from the catalogue: the chosen skills with a remove button each, and a search that
 * offers more until `max` are chosen.
 *
 * @param props          Component props.
 * @param props.label    What the skills are for, such as "Primary skill".
 * @param props.selected Chosen skill ids.
 * @param props.max      Most skills.
 * @param props.names    Known skills by id.
 * @param props.disabled Whether editing is off.
 * @param props.onFound  Receives skills found by a search, so their names are known.
 * @param props.onChange Receives the new skill ids.
 */
export function SkillPicker( {
	label,
	selected,
	max,
	names,
	disabled,
	onFound,
	onChange,
}: {
	label: string;
	selected: number[];
	max: number;
	names: ReadonlyMap< number, SkillRef >;
	disabled: boolean;
	onFound: ( skills: SkillRef[] ) => void;
	onChange: ( next: number[] ) => void;
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState< SkillRef[] | null >( null );
	const [ failure, setFailure ] = useState< string | null >( null );
	const pending = useRef< AbortController | null >( null );
	useEffect( () => () => pending.current?.abort(), [] );
	const describe = ( id: number ) => {
		const skill = names.get( id );
		if ( ! skill ) {
			return sprintf(
				/* translators: %d: skill id. */
				__( 'Skill #%d', 'ohmylms' ),
				id
			);
		}
		return skill.code ? `${ skill.name } (${ skill.code })` : skill.name;
	};
	function find() {
		pending.current?.abort();
		const controller = new AbortController();
		pending.current = controller;
		setFailure( null );
		searchSkills( search, controller.signal ).then(
			( found ) => {
				if ( ! controller.signal.aborted ) {
					onFound( found );
					setResults( found );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( normalizeApiError( problem ).message );
				}
			}
		);
	}
	const offered = ( results ?? [] ).filter(
		( skill ) => ! selected.includes( skill.id )
	);
	return (
		<div className="ohmylms-question-skills">
			<p>
				<strong>{ label }</strong>
			</p>
			{ selected.length ? (
				<ul>
					{ selected.map( ( id ) => (
						<li key={ id }>
							{ describe( id ) }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ disabled }
								aria-label={ sprintf(
									/* translators: 1: skill name, 2: role such as "Primary skill". */
									__( 'Remove %1$s from %2$s', 'ohmylms' ),
									describe( id ),
									label
								) }
								onClick={ () =>
									onChange(
										selected.filter(
											( item ) => item !== id
										)
									)
								}
							>
								{ __( 'Remove', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'None chosen.', 'ohmylms' ) }</p>
			) }
			{ selected.length < max && (
				<div role="search">
					<TextControl
						label={ sprintf(
							/* translators: %s: role such as "Primary skill". */
							__( 'Search skills for %s', 'ohmylms' ),
							label
						) }
						value={ search }
						disabled={ disabled }
						onChange={ setSearch }
						onKeyDown={ ( event ) => {
							if ( 'Enter' === event.key ) {
								event.preventDefault();
								find();
							}
						} }
					/>
					<Button
						variant="secondary"
						size="small"
						disabled={ disabled }
						onClick={ find }
					>
						{ __( 'Search', 'ohmylms' ) }
					</Button>
					{ failure && <p role="alert">{ failure }</p> }
					{ null !== results && (
						<p role="status">
							{ offered.length
								? sprintf(
										/* translators: %d: number of skills found. */
										__( '%d skills found.', 'ohmylms' ),
										offered.length
									)
								: __( 'No skills found.', 'ohmylms' ) }
						</p>
					) }
					{ offered.length > 0 && (
						<ul>
							{ offered.map( ( skill ) => (
								<li key={ skill.id }>
									<Button
										variant="link"
										disabled={ disabled }
										onClick={ () => {
											onChange( [
												...selected,
												skill.id,
											] );
											setResults( null );
											setSearch( '' );
										} }
									>
										{ sprintf(
											/* translators: %s: skill name. */
											__( 'Add %s', 'ohmylms' ),
											skill.code
												? `${ skill.name } (${ skill.code })`
												: skill.name
										) }
									</Button>
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</div>
	);
}
