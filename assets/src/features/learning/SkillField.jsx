import { createElement, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { SkillPicker } from '../content-hub/public.mjs';

/**
 * Name of a skill for a chip, with its code when it has one.
 *
 * @param {Record<number, {name?: string, code?: string}>} names Skills known by ID.
 * @param {number}                                         id    Skill ID.
 * @return {string} Label.
 */
export function skillLabel( names, id ) {
	const skill = names[ id ];
	if ( ! skill?.name ) {
		/* translators: %d: skill ID. */
		return sprintf( __( 'Skill %d', 'ohmylms' ), id );
	}
	return skill.code ? `${ skill.code } · ${ skill.name }` : skill.name;
}

/**
 * Chosen skills as removable chips, and a button that opens the searchable curriculum picker.
 *
 * @param {Object}                                                                        props
 * @param {string}                                                                        props.label      What is being chosen.
 * @param {number[]}                                                                      props.value      Chosen skill IDs.
 * @param {Record<number, {name?: string, code?: string}>}                                props.names      Skills known by ID.
 * @param {boolean}                                                                       [props.single]   Exactly one skill.
 * @param {boolean}                                                                       [props.disabled] Read-only.
 * @param {string}                                                                        [props.empty]    Text when nothing is chosen.
 * @param {(ids: number[], picked: {id: number, name?: string, code?: string}[]) => void} props.onChange   Receives the new IDs and any skills just picked, for their names.
 * @return {React.ReactElement} Field.
 */
export function SkillField( {
	label,
	value,
	names,
	single = false,
	disabled = false,
	empty = '',
	onChange,
} ) {
	const [ open, setOpen ] = useState( false );
	return (
		<div className="ohmylms-skill-field">
			<p className="ohmylms-skill-field-label">
				<strong>{ label }</strong>
			</p>
			{ value.length > 0 ? (
				<ul className="ohmylms-skill-chips">
					{ value.map( ( id ) => (
						<li key={ id } className="ohmylms-skill-chip">
							<span>{ skillLabel( names, id ) }</span>
							<Button
								size="small"
								icon="no-alt"
								disabled={ disabled }
								label={ sprintf(
									/* translators: %s: skill name. */
									__( 'Remove %s', 'ohmylms' ),
									skillLabel( names, id )
								) }
								onClick={ () =>
									onChange(
										value.filter( ( row ) => row !== id ),
										[]
									)
								}
							/>
						</li>
					) ) }
				</ul>
			) : (
				<p className="ohmylms-skill-field-empty">
					{ empty || __( 'No skill chosen.', 'ohmylms' ) }
				</p>
			) }
			<Button
				variant="secondary"
				size="small"
				disabled={ disabled }
				onClick={ () => setOpen( true ) }
			>
				{ single && value.length
					? __( 'Change skill', 'ohmylms' )
					: __( 'Choose skills', 'ohmylms' ) }
			</Button>
			{ open && (
				<SkillPicker
					title={ label }
					confirmLabel={
						single
							? __( 'Use this skill', 'ohmylms' )
							: __( 'Use these skills', 'ohmylms' )
					}
					single={ single }
					initial={ value.map( ( id ) => ( {
						id,
						name: names[ id ]?.name,
						code: names[ id ]?.code,
					} ) ) }
					onClose={ () => setOpen( false ) }
					onConfirm={ ( picked ) => {
						setOpen( false );
						onChange(
							picked.map( ( skill ) => skill.id ),
							picked
						);
					} }
				/>
			) }
		</div>
	);
}
