import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useRequest } from '../../hooks/useRequest';
import { listSkills } from '../content-hub';
import type { Outline, Program } from './model';

export function LearningSettings( {
	draft,
	change,
	disabled,
}: {
	draft: Outline;
	change: ( value: Outline ) => void;
	disabled: boolean;
} ) {
	const [ picker, setPicker ] = useState( false );
	function update( patch: Partial< Program > ) {
		change( { ...draft, program: { ...draft.program, ...patch } } );
	}
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Learning and mastery', 'ohmylms' ) }</legend>
			<SelectControl< string >
				label={ __( 'Learning mode', 'ohmylms' ) }
				value={ String( draft.program.mode ?? 'traditional' ) }
				options={ [
					{
						value: 'traditional',
						label: __( 'Traditional', 'ohmylms' ),
					},
					{
						value: 'skill-based',
						label: __( 'Skill based', 'ohmylms' ),
					},
					{ value: 'blended', label: __( 'Blended', 'ohmylms' ) },
				] }
				onChange={ ( value ) => update( { mode: value } ) }
			/>
			<CheckboxControl
				label={ __( 'Recognize prior mastery', 'ohmylms' ) }
				checked={ draft.program.recognize_prior === true }
				onChange={ ( value ) => update( { recognize_prior: value } ) }
			/>
			<TextControl
				label={ __( 'Mastery evidence age limit (days)', 'ohmylms' ) }
				type="number"
				min="0"
				max="3650"
				step="1"
				value={ Number( draft.program.evidence_days ?? 0 ) }
				onChange={ ( value ) =>
					update( { evidence_days: Number( value ) } )
				}
				help={ __( 'Use 0 for no age limit.', 'ohmylms' ) }
			/>
			<h2>{ __( 'Skill targets', 'ohmylms' ) }</h2>
			{ draft.program.outcomes.map( ( outcome, index ) => (
				<fieldset key={ Number( outcome.term_id ) }>
					<legend>
						{
							/* translators: %d: shared skill ID. */ sprintf(
								__( 'Skill #%d', 'ohmylms' ),
								Number( outcome.term_id )
							)
						}
					</legend>
					<SelectControl< string >
						label={ __( 'Mastery target', 'ohmylms' ) }
						value={ String( outcome.target ) }
						options={ [
							{
								value: 'proficient',
								label: __( 'Proficient', 'ohmylms' ),
							},
							{
								value: 'mastered',
								label: __( 'Mastered', 'ohmylms' ),
							},
						] }
						onChange={ ( value ) =>
							update( {
								outcomes: draft.program.outcomes.map(
									( row, rowIndex ) =>
										rowIndex === index
											? { ...row, target: value }
											: row
								),
							} )
						}
					/>
					<CheckboxControl
						label={ __( 'Required for completion', 'ohmylms' ) }
						checked={ outcome.required === true }
						onChange={ ( value ) =>
							update( {
								outcomes: draft.program.outcomes.map(
									( row, rowIndex ) =>
										rowIndex === index
											? { ...row, required: value }
											: row
								),
							} )
						}
					/>
					<SelectControl< string >
						label={ __( 'Skill unit', 'ohmylms' ) }
						value={ String( outcome.chapter_id ?? 0 ) }
						options={ [
							{
								value: '0',
								label: __( 'Course wide', 'ohmylms' ),
							},
							...draft.units.map( ( unit ) => ( {
								value: String( unit.id ),
								label: unit.title,
							} ) ),
						] }
						onChange={ ( value ) =>
							update( {
								outcomes: draft.program.outcomes.map(
									( row, rowIndex ) =>
										rowIndex === index
											? {
													...row,
													chapter_id: /^\d+$/.test(
														value
													)
														? Number( value )
														: value,
												}
											: row
								),
							} )
						}
					/>
					<Button
						isDestructive
						onClick={ () =>
							update( {
								outcomes: draft.program.outcomes.filter(
									( _, rowIndex ) => rowIndex !== index
								),
							} )
						}
					>
						{ __( 'Remove skill target', 'ohmylms' ) }
					</Button>
				</fieldset>
			) ) }
			<Button onClick={ () => setPicker( true ) }>
				{ __( 'Add skill target', 'ohmylms' ) }
			</Button>
			{ picker && (
				<SkillPicker
					onClose={ () => setPicker( false ) }
					onSelect={ ( id ) => {
						if (
							! draft.program.outcomes.some(
								( row ) => row.term_id === id
							)
						) {
							update( {
								outcomes: [
									...draft.program.outcomes,
									{
										term_id: id,
										target: 'proficient',
										required: true,
										chapter_id: 0,
									},
								],
							} );
						}
						setPicker( false );
					} }
				/>
			) }
		</fieldset>
	);
}

function SkillPicker( {
	onClose,
	onSelect,
}: {
	onClose: () => void;
	onSelect: ( id: number ) => void;
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => listSkills( page, search, signal ),
		[ page, search ]
	);
	const request = useRequest( load );
	return (
		<Modal
			title={ __( 'Choose a shared skill', 'ohmylms' ) }
			onRequestClose={ onClose }
		>
			<TextControl
				label={ __( 'Search skills', 'ohmylms' ) }
				value={ search }
				onChange={ ( value ) => {
					setSearch( value );
					setPage( 1 );
				} }
			/>
			{ request.loading && (
				<p role="status">{ __( 'Loading skills…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! request.loading && request.data && (
				<>
					{ request.data.items
						.filter( ( skill ) => ! skill.archived )
						.map( ( skill ) => (
							<p key={ skill.id }>
								<Button onClick={ () => onSelect( skill.id ) }>
									{ skill.code } { skill.name }
								</Button>
							</p>
						) ) }
					{ request.data.items.length === 0 && (
						<p>{ __( 'No skills found.', 'ohmylms' ) }</p>
					) }
					<Button
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous skills', 'ohmylms' ) }
					</Button>
					<Button
						disabled={ page >= request.data.totalPages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next skills', 'ohmylms' ) }
					</Button>
				</>
			) }
		</Modal>
	);
}
