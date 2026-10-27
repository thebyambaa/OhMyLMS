import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useCurriculum } from './context';
import * as api from './api.mjs';
const RELATIONS = () => [
	{
		value: 'equivalent',
		label: __(
			'Equivalent: its evidence counts toward the shared skill',
			'ohmylms'
		),
	},
	{
		value: 'related',
		label: __(
			'Related: shown for reference only, adds no evidence',
			'ohmylms'
		),
	},
];

/**
 * Explicit links from the skills in this curriculum item to shared skills. Skills with the same
 * name are never merged automatically: each keeps its own requirements, difficulty and evidence.
 * @param root0
 * @param root0.detail
 * @param root0.onChange
 */
export function SkillMappings( { detail, onChange } ) {
	useCurriculum();
	const skills = detail.links?.skill || [];
	if ( ! skills.length ) {
		return null;
	}
	return (
		<section className="ohmylms-cur-mappings">
			<h3>{ __( 'Shared skills', 'ohmylms' ) }</h3>
			<p className="ohmylms-ext-muted">
				{ __(
					'Map a skill here to a shared skill so learners can see related skills from different curricula together. Matching names are never merged on their own, and a mapping never completes a course.',
					'ohmylms'
				) }
			</p>
			{ skills.map( ( skill ) => (
				<MappingRow
					key={ skill.id }
					skill={ skill }
					mappings={
						detail.skill_mappings?.[ skill.id ] || {
							maps_to: [],
							mapped_from: [],
						}
					}
					onChange={ ( skillMappings ) =>
						onChange( ( previous ) => ( {
							...previous,
							skill_mappings: {
								...previous.skill_mappings,
								[ skill.id ]: skillMappings,
							},
						} ) )
					}
				/>
			) ) }
		</section>
	);
}
function MappingRow( { skill, mappings, onChange } ) {
	const c = useCurriculum();
	const [ open, setOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ shared, setShared ] = useState( null );
	const [ relation, setRelation ] = useState( 'equivalent' );
	const [ note, setNote ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	useEffect( () => {
		if ( ! open || ! search.trim() ) {
			return setResults( [] );
		}
		let current = true;
		const timer = setTimeout( () => {
			api.searchTargets( 'skill', search )
				.then(
					( rows ) =>
						current &&
						setResults(
							( rows || [] ).filter(
								( row ) => row.id !== skill.id
							)
						)
				)
				.catch( () => current && setResults( [] ) );
		}, 300 );
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ open, search, skill.id ] );
	async function save() {
		if ( ! shared ) {
			return;
		}
		setSaving( true );
		setError( '' );
		try {
			const response = await api.saveMapping( {
				specific_id: skill.id,
				shared_id: shared.id,
				relation,
				note,
			} );
			onChange( response.skill_mappings );
			c.actions.say(
				'success',
				sprintf(
					/* translators: %1$s: source skill name; %2$s: shared skill name. */ __(
						'Mapped %1$s to %2$s.',
						'ohmylms'
					),
					skill.title,
					shared.title
				)
			);
			setShared( null );
			setSearch( '' );
			setNote( '' );
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'The mapping could not be saved.', 'ohmylms' )
			);
		} finally {
			setSaving( false );
		}
	}
	async function remove( mapping ) {
		setError( '' );
		try {
			const response = await api.removeMapping(
				mapping.specific_id,
				mapping.shared_id
			);
			onChange( response.skill_mappings );
			c.actions.say( 'success', __( 'Mapping removed.', 'ohmylms' ) );
		} catch ( cause ) {
			setError(
				cause?.message ||
					__( 'The mapping could not be removed.', 'ohmylms' )
			);
		}
	}
	const relationLabel = ( value ) =>
		value === 'equivalent'
			? __( 'Equivalent', 'ohmylms' )
			: __( 'Related', 'ohmylms' );
	return (
		<details
			className="ohmylms-cur-mapping"
			open={ open }
			onToggle={ ( event ) => setOpen( event.currentTarget.open ) }
		>
			<summary>
				{ skill.title }
				{ skill.code ? ` (${ skill.code })` : '' }
				{ mappings.maps_to.length > 0 && (
					<span className="ohmylms-cur-badge">
						{ sprintf(
							/* translators: %d: shared skill identifier. */ __(
								'Mapped to %d',
								'ohmylms'
							),
							mappings.maps_to.length
						) }
					</span>
				) }
				{ mappings.mapped_from.length > 0 && (
					<span className="ohmylms-cur-badge">
						{ __( 'Shared skill', 'ohmylms' ) }
					</span>
				) }
			</summary>
			{ mappings.maps_to.map( ( mapping ) => (
				<p key={ mapping.id } className="ohmylms-cur-mapping-line">
					{ sprintf(
						/* translators: %1$s: shared skill name; %2$s: comma-separated differences. */ __(
							'Maps to shared skill “%1$s” — %2$s.',
							'ohmylms'
						),
						mapping.shared?.name || mapping.shared_id,
						relationLabel( mapping.relation )
					) }
					{ mapping.note && (
						<em>
							{ ' ' }
							{ sprintf(
								/* translators: %s: comma-separated skill differences. */ __(
									'Differences: %s',
									'ohmylms'
								),
								mapping.note
							) }
						</em>
					) }{ ' ' }
					<Button
						variant="link"
						isDestructive
						aria-label={ sprintf(
							/* translators: %1$s: source skill name; %2$s: shared skill name. */ __(
								'Remove mapping of %1$s to %2$s',
								'ohmylms'
							),
							skill.title,
							mapping.shared?.name || mapping.shared_id
						) }
						onClick={ () => remove( mapping ) }
					>
						{ __( 'Remove', 'ohmylms' ) }
					</Button>
				</p>
			) ) }
			{ mappings.mapped_from.length > 0 && (
				<p className="ohmylms-cur-mapping-line">
					{ sprintf(
						/* translators: %s: comma-separated names of linked skills. */ __(
							'This is the shared skill for: %s.',
							'ohmylms'
						),
						mappings.mapped_from
							.map(
								( mapping ) =>
									mapping.specific?.name ||
									mapping.specific_id
							)
							.join( ', ' )
					) }
				</p>
			) }
			{ mappings.mapped_from.length === 0 && (
				<div className="ohmylms-cur-mapping-form">
					<TextControl
						label={ sprintf(
							/* translators: %s: skill or linked content name. */ __(
								'Find a shared skill for %s',
								'ohmylms'
							),
							skill.title
						) }
						type="search"
						value={ shared ? shared.title : search }
						onChange={ ( value ) => {
							setShared( null );
							setSearch( value );
						} }
						__nextHasNoMarginBottom
					/>
					{ ! shared && results.length > 0 && (
						<ul className="ohmylms-cur-link-list ohmylms-cur-link-results">
							{ results.map( ( row ) => (
								<li key={ row.id }>
									<span>
										{ row.title }
										{ row.code ? ` (${ row.code })` : '' }
									</span>
									<Button
										variant="secondary"
										size="small"
										aria-label={ sprintf(
											/* translators: %s: skill or linked content name. */ __(
												'Choose %s as the shared skill',
												'ohmylms'
											),
											row.title
										) }
										onClick={ () => setShared( row ) }
									>
										{ __( 'Choose', 'ohmylms' ) }
									</Button>
								</li>
							) ) }
						</ul>
					) }
					<SelectControl
						label={ __( 'Relationship', 'ohmylms' ) }
						value={ relation }
						options={ RELATIONS() }
						onChange={ setRelation }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __(
							'How do the requirements, difficulty or syllabus version differ? (optional)',
							'ohmylms'
						) }
						value={ note }
						onChange={ setNote }
						maxLength={ 1000 }
						__nextHasNoMarginBottom
					/>
					{ error && <p role="alert">{ error }</p> }
					<Button
						variant="secondary"
						isBusy={ saving }
						disabled={ ! shared || saving }
						onClick={ save }
					>
						{ __( 'Save mapping', 'ohmylms' ) }
					</Button>
				</div>
			) }
		</details>
	);
}
