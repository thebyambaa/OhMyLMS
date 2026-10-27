/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, SelectControl } from '@wordpress/components';
import { readComparison, writeComparison } from './comparison.mjs';
import { usePlacementOptions } from './usePlacementOptions';

/**
 * Domain and level band of a shared skill, on its subject's profile. Shown only for shared skills; other skills render nothing.
 *
 * @param {Object} props         Field props.
 * @param {number} props.skillId Skill term ID.
 */
export function SkillPlacementFields( { skillId } ) {
	const [ skill, setSkill ] = useState( null );
	const options = usePlacementOptions( skill?.subject || '' );
	const [ values, setValues ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	useEffect( () => {
		let current = true;
		readComparison( `shared-skills/${ skillId }` )
			.then( ( data ) => {
				if ( current ) {
					setSkill( data );
					setValues( {
						domain: data.domain || '',
						grade_from: data.grade_from || 0,
						grade_to: data.grade_to || 0,
					} );
				}
			} )
			.catch( () => current && setSkill( null ) );
		return () => {
			current = false;
		};
	}, [ skillId ] );
	if ( ! skill || ! values || ! options ) {
		return null;
	}
	const changed =
		values.domain !== ( skill.domain || '' ) ||
		values.grade_from !== ( skill.grade_from || 0 ) ||
		values.grade_to !== ( skill.grade_to || 0 );
	const grades = ( minimum ) => [
		{ label: __( 'Not set', 'ohmylms' ), value: '0' },
		...options.grades
			.filter( ( grade ) => grade.value >= minimum )
			.map( ( grade ) => ( {
				label: grade.label,
				value: String( grade.value ),
			} ) ),
	];
	async function save() {
		setBusy( true );
		setMessage( '' );
		try {
			const saved = await writeComparison( 'shared-skills/save', {
				id: skill.id,
				name: skill.name,
				description: skill.description,
				topic: skill.topic,
				subject: skill.subject,
				...values,
				expected_revision: skill.revision,
			} );
			setSkill( saved );
			setMessage( __( 'Saved.', 'ohmylms' ) );
		} catch ( cause ) {
			setMessage( cause.message );
		} finally {
			setBusy( false );
		}
	}
	return (
		<fieldset className="ohmylms-skill-placement">
			<legend>{ __( 'Domain and levels', 'ohmylms' ) }</legend>
			<SelectControl
				label={ __( 'Domain', 'ohmylms' ) }
				value={ values.domain }
				options={ [
					{ label: __( 'Not set', 'ohmylms' ), value: '' },
					...Object.entries( options.domains ).map(
						( [ value, label ] ) => ( { value, label } )
					),
				] }
				onChange={ ( domain ) => setValues( { ...values, domain } ) }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Usually learned from', 'ohmylms' ) }
				value={ String( values.grade_from ) }
				options={ grades( 1 ) }
				onChange={ ( value ) => {
					const from = Number( value );
					setValues( {
						...values,
						grade_from: from,
						grade_to: Math.max( from, values.grade_to ),
					} );
				} }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Until', 'ohmylms' ) }
				value={ String( values.grade_to ) }
				options={ grades( values.grade_from || 1 ) }
				onChange={ ( value ) =>
					setValues( { ...values, grade_to: Number( value ) } )
				}
				__nextHasNoMarginBottom
			/>
			<Button
				variant="secondary"
				disabled={ busy || ! changed }
				isBusy={ busy }
				onClick={ save }
			>
				{ __( 'Save domain and levels', 'ohmylms' ) }
			</Button>
			{ message && <span role="status">{ message }</span> }
		</fieldset>
	);
}
