import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	DIFFICULTIES,
	difficultyLabel,
	listBanks,
	SkillPicker,
	type BankRef,
	type SkillRef,
} from '../questions/public';
import {
	assignSection,
	formatMarks,
	MAX_SLOT_MARKS,
	slotMarks,
	slotName,
} from './model';
import type {
	QuizPool,
	QuizPoolInput,
	QuizQuestionSlot,
	QuizSection,
} from './types';

/**
 * Change the marks of one question in this quiz, or go back to the question's own points.
 *
 * @param props         Component props.
 * @param props.slot    The question in the quiz.
 * @param props.busy    A change is being saved.
 * @param props.error   Message of the last failed save.
 * @param props.onSave  Saves marks, or `null` for the question's points.
 * @param props.onClose Closes the dialog.
 */
export function MarksDialog( {
	slot,
	busy,
	error,
	onSave,
	onClose,
}: {
	slot: QuizQuestionSlot;
	busy: boolean;
	error: string | null;
	onSave: ( marks: number | null ) => void;
	onClose: () => void;
} ) {
	const [ value, setValue ] = useState( formatMarks( slot.marks ) );
	const [ problem, setProblem ] = useState< string | null >( null );
	const name = slotName( slot );
	function submit() {
		const marks = slotMarks( value );
		if ( null === marks ) {
			setProblem(
				sprintf(
					/* translators: %d: maximum marks. */
					__(
						'Enter marks from 0 to %d with at most four decimals.',
						'ohmylms'
					),
					MAX_SLOT_MARKS
				)
			);
			return;
		}
		setProblem( null );
		onSave( marks );
	}
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: question name. */
				__( 'Marks of %s in this quiz', 'ohmylms' ),
				name
			) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ false }
		>
			<form
				noValidate
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				<p>
					{ sprintf(
						/* translators: %s: points of the question. */
						__(
							'The question itself is worth %s. Marks set here apply in this quiz only and replace any section marks.',
							'ohmylms'
						),
						formatMarks( slot.question_marks )
					) }
				</p>
				<TextControl
					label={ __( 'Marks in this quiz', 'ohmylms' ) }
					type="number"
					min={ 0 }
					max={ MAX_SLOT_MARKS }
					step="any"
					value={ value }
					aria-invalid={ problem ? true : undefined }
					aria-describedby={
						problem ? 'oml-quiz-marks-error' : undefined
					}
					onChange={ setValue }
				/>
				{ problem && (
					<p id="oml-quiz-marks-error" role="alert">
						{ problem }
					</p>
				) }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<Button
					type="submit"
					variant="primary"
					disabled={ busy }
					isBusy={ busy }
				>
					{ __( 'Save marks', 'ohmylms' ) }
				</Button>{ ' ' }
				{ null !== slot.marks_override && (
					<>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => onSave( null ) }
						>
							{ sprintf(
								/* translators: %s: points of the question. */
								__(
									'Use the question’s points (%s)',
									'ohmylms'
								),
								formatMarks( slot.question_marks )
							) }
						</Button>{ ' ' }
					</>
				) }
				<Button
					variant="tertiary"
					disabled={ busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}

interface SectionDraft {
	key: string;
	title: string;
	question_ids: number[];
	new_page: boolean;
}

let nextSection = 0;

function sectionKey(): string {
	nextSection += 1;
	return `section-${ nextSection }`;
}

/**
 * Edit the sections of the quiz paper: titles, order, which questions each holds and whether a section
 * starts on a new page. Questions in no section come first; each question is in at most one section.
 *
 * @param props          Component props.
 * @param props.sections Sections as loaded.
 * @param props.slots    Questions of the quiz in order.
 * @param props.busy     A change is being saved.
 * @param props.error    Message of the last failed save.
 * @param props.onSave   Saves every section.
 * @param props.onClose  Closes the dialog.
 */
export function SectionsDialog( {
	sections,
	slots,
	busy,
	error,
	onSave,
	onClose,
}: {
	sections: QuizSection[];
	slots: QuizQuestionSlot[];
	busy: boolean;
	error: string | null;
	onSave: ( sections: QuizSection[] ) => void;
	onClose: () => void;
} ) {
	const [ drafts, setDrafts ] = useState< SectionDraft[] >( () =>
		sections.map( ( section ) => ( { ...section, key: sectionKey() } ) )
	);
	const [ problem, setProblem ] = useState< string | null >( null );
	const order = slots.map( ( slot ) => slot.question_id );
	const update = ( index: number, patch: Partial< SectionDraft > ) =>
		setDrafts( ( current ) =>
			current.map( ( item, position ) =>
				position === index ? { ...item, ...patch } : item
			)
		);
	function assign( questionId: number, target: number ) {
		setDrafts( ( current ) =>
			assignSection( current, order, questionId, target )
		);
	}
	function move( index: number, delta: -1 | 1 ) {
		setDrafts( ( current ) => {
			const next = [ ...current ];
			const moved = next[ index ];
			const other = next[ index + delta ];
			if ( ! moved || ! other ) {
				return current;
			}
			next[ index ] = other;
			next[ index + delta ] = moved;
			return next;
		} );
	}
	function submit() {
		if ( drafts.some( ( item ) => ! item.title.trim() ) ) {
			setProblem( __( 'Give every section a title.', 'ohmylms' ) );
			return;
		}
		setProblem( null );
		onSave(
			drafts.map( ( { title, question_ids: ids, new_page: page } ) => ( {
				title: title.trim(),
				question_ids: ids,
				new_page: page,
			} ) )
		);
	}
	const sectionOf = ( questionId: number ) =>
		drafts.findIndex( ( item ) =>
			item.question_ids.includes( questionId )
		);
	return (
		<Modal
			title={ __( 'Sections', 'ohmylms' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ __(
					'Sections group questions on the paper under a title. Questions in no section come first, then each section in this order.',
					'ohmylms'
				) }
			</p>
			{ drafts.map( ( item, index ) => (
				<fieldset key={ item.key }>
					<legend>
						{ sprintf(
							/* translators: %d: section number. */
							__( 'Section %d', 'ohmylms' ),
							index + 1
						) }
					</legend>
					<TextControl
						label={ sprintf(
							/* translators: %d: section number. */
							__( 'Title of section %d', 'ohmylms' ),
							index + 1
						) }
						value={ item.title }
						maxLength={ 190 }
						onChange={ ( title ) => update( index, { title } ) }
					/>
					<CheckboxControl
						label={ __( 'Start on a new page', 'ohmylms' ) }
						checked={ item.new_page }
						onChange={ ( page ) =>
							update( index, { new_page: page } )
						}
					/>
					<Button
						variant="secondary"
						size="small"
						disabled={ 0 === index }
						onClick={ () => move( index, -1 ) }
					>
						{ __( 'Move section up', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						size="small"
						disabled={ index === drafts.length - 1 }
						onClick={ () => move( index, 1 ) }
					>
						{ __( 'Move section down', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						size="small"
						isDestructive
						onClick={ () =>
							setDrafts( ( current ) =>
								current.filter(
									( _, position ) => position !== index
								)
							)
						}
					>
						{ __( 'Remove section', 'ohmylms' ) }
					</Button>
				</fieldset>
			) ) }
			<p>
				<Button
					variant="secondary"
					disabled={ drafts.length >= 50 }
					onClick={ () =>
						setDrafts( ( current ) => [
							...current,
							{
								key: sectionKey(),
								title: '',
								question_ids: [],
								new_page: false,
							},
						] )
					}
				>
					{ __( 'Add section', 'ohmylms' ) }
				</Button>
			</p>
			{ drafts.length > 0 && slots.length > 0 && (
				<fieldset>
					<legend>
						{ __( 'Section of each question', 'ohmylms' ) }
					</legend>
					{ slots.map( ( slot ) => (
						<SelectControl
							key={ slot.question_id }
							label={ sprintf(
								/* translators: 1: position, 2: question name. */
								__( '%1$d. %2$s', 'ohmylms' ),
								slot.position,
								slotName( slot )
							) }
							value={ String( sectionOf( slot.question_id ) ) }
							options={ [
								{
									value: '-1',
									label: __( 'No section', 'ohmylms' ),
								},
								...drafts.map( ( item, index ) => ( {
									value: String( index ),
									label:
										item.title.trim() ||
										sprintf(
											/* translators: %d: section number. */
											__( 'Section %d', 'ohmylms' ),
											index + 1
										),
								} ) ),
							] }
							onChange={ ( next ) =>
								assign( slot.question_id, Number( next ) )
							}
						/>
					) ) }
				</fieldset>
			) }
			{ problem && <p role="alert">{ problem }</p> }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button
				variant="primary"
				disabled={ busy }
				isBusy={ busy }
				onClick={ submit }
			>
				{ __( 'Save sections', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}

interface PoolDraft {
	key: string;
	title: string;
	skill: { id: number; name: string };
	count: string;
	marks: string;
	difficulty: string;
	bank_id: number;
	include_secure: boolean;
}

let nextPool = 0;

function poolKey(): string {
	nextPool += 1;
	return `pool-${ nextPool }`;
}

/**
 * Edit the random pools: each draws a number of approved questions of a skill (and its sub-skills) at
 * every attempt, optionally only of a difficulty or from one bank, each worth the same marks.
 *
 * @param props         Component props.
 * @param props.pools   Pools as loaded.
 * @param props.busy    A change is being saved.
 * @param props.error   Message of the last failed save.
 * @param props.onSave  Saves every pool.
 * @param props.onClose Closes the dialog.
 */
export function PoolsDialog( {
	pools,
	busy,
	error,
	onSave,
	onClose,
}: {
	pools: QuizPool[];
	busy: boolean;
	error: string | null;
	onSave: ( pools: QuizPoolInput[] ) => void;
	onClose: () => void;
} ) {
	const [ drafts, setDrafts ] = useState< PoolDraft[] >( () =>
		pools.map( ( pool ) => ( {
			key: poolKey(),
			title: pool.title,
			skill: pool.skill,
			count: String( pool.count ),
			marks: formatMarks( pool.marks ),
			difficulty: pool.difficulty,
			bank_id: pool.bank_id,
			include_secure: pool.include_secure,
		} ) )
	);
	const [ names, setNames ] = useState< Map< number, SkillRef > >(
		() =>
			new Map(
				pools.map( ( pool ) => [
					pool.skill.id,
					{ id: pool.skill.id, name: pool.skill.name, code: '' },
				] )
			)
	);
	const [ banks, setBanks ] = useState< BankRef[] >( [] );
	const [ problems, setProblems ] = useState< Record< string, string > >(
		{}
	);
	useEffect( () => {
		const controller = new AbortController();
		listBanks( controller.signal ).then(
			( loaded ) => {
				if ( ! controller.signal.aborted ) {
					setBanks( loaded );
				}
			},
			() => undefined
		);
		return () => controller.abort();
	}, [] );
	const update = ( index: number, patch: Partial< PoolDraft > ) =>
		setDrafts( ( current ) =>
			current.map( ( item, position ) =>
				position === index ? { ...item, ...patch } : item
			)
		);
	function remember( found: SkillRef[] ) {
		setNames( ( current ) => {
			const next = new Map( current );
			found.forEach( ( skill ) => next.set( skill.id, skill ) );
			return next;
		} );
	}
	function submit() {
		const found: Record< string, string > = {};
		const inputs: QuizPoolInput[] = [];
		drafts.forEach( ( item, index ) => {
			const count = Number( item.count );
			const marks = slotMarks( item.marks );
			if ( ! item.skill.id ) {
				found[ `${ index }.skill` ] = __(
					'Choose a skill.',
					'ohmylms'
				);
			}
			if (
				! /^\d+$/.test( item.count.trim() ) ||
				count < 1 ||
				count > 50
			) {
				found[ `${ index }.count` ] = __(
					'Draw from 1 to 50 questions.',
					'ohmylms'
				);
			}
			if ( null === marks ) {
				found[ `${ index }.marks` ] = sprintf(
					/* translators: %d: maximum marks. */
					__( 'Enter marks from 0 to %d.', 'ohmylms' ),
					MAX_SLOT_MARKS
				);
			}
			inputs.push( {
				title: item.title.trim(),
				skill_id: item.skill.id,
				count,
				marks: marks ?? 0,
				difficulty: item.difficulty,
				bank_id: item.bank_id,
				include_secure: item.include_secure,
			} );
		} );
		setProblems( found );
		if ( ! Object.keys( found ).length ) {
			onSave( inputs );
		}
	}
	const field = ( index: number, name: string ) => {
		const message = problems[ `${ index }.${ name }` ];
		return message ? (
			<p id={ `oml-pool-${ index }-${ name }-error` } role="alert">
				{ message }
			</p>
		) : null;
	};
	const describedBy = ( index: number, name: string ) =>
		problems[ `${ index }.${ name }` ]
			? `oml-pool-${ index }-${ name }-error`
			: undefined;
	return (
		<Modal
			title={ __( 'Random pools', 'ohmylms' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ false }
		>
			<p>
				{ __(
					'At every attempt each pool draws approved questions of its skill, from different question families and not already in the quiz. They come after the questions above.',
					'ohmylms'
				) }
			</p>
			{ drafts.map( ( item, index ) => (
				<fieldset key={ item.key }>
					<legend>
						{ sprintf(
							/* translators: %d: pool number. */
							__( 'Pool %d', 'ohmylms' ),
							index + 1
						) }
					</legend>
					<TextControl
						label={ __( 'Title', 'ohmylms' ) }
						value={ item.title }
						maxLength={ 190 }
						onChange={ ( title ) => update( index, { title } ) }
					/>
					<SkillPicker
						label={ sprintf(
							/* translators: %d: pool number. */
							__( 'Skill of pool %d', 'ohmylms' ),
							index + 1
						) }
						selected={ item.skill.id ? [ item.skill.id ] : [] }
						max={ 1 }
						names={ names }
						disabled={ busy }
						onFound={ remember }
						onChange={ ( next ) => {
							const id = next[ 0 ] ?? 0;
							update( index, {
								skill: {
									id,
									name: names.get( id )?.name ?? '',
								},
							} );
						} }
					/>
					{ field( index, 'skill' ) }
					<TextControl
						label={ __( 'Questions to draw', 'ohmylms' ) }
						type="number"
						min={ 1 }
						max={ 50 }
						value={ item.count }
						aria-invalid={
							describedBy( index, 'count' ) ? true : undefined
						}
						aria-describedby={ describedBy( index, 'count' ) }
						onChange={ ( count ) => update( index, { count } ) }
					/>
					{ field( index, 'count' ) }
					<TextControl
						label={ __( 'Marks of each question', 'ohmylms' ) }
						type="number"
						min={ 0 }
						step="any"
						value={ item.marks }
						aria-invalid={
							describedBy( index, 'marks' ) ? true : undefined
						}
						aria-describedby={ describedBy( index, 'marks' ) }
						onChange={ ( marks ) => update( index, { marks } ) }
					/>
					{ field( index, 'marks' ) }
					<SelectControl
						label={ __( 'Difficulty', 'ohmylms' ) }
						value={ item.difficulty }
						options={ [
							{
								value: '',
								label: __( 'Any difficulty', 'ohmylms' ),
							},
							...DIFFICULTIES.map( ( value ) => ( {
								value: value as string,
								label: difficultyLabel( value ),
							} ) ),
						] }
						onChange={ ( difficulty ) =>
							update( index, { difficulty } )
						}
					/>
					<SelectControl
						label={ __( 'Bank', 'ohmylms' ) }
						value={ String( item.bank_id ) }
						options={ [
							{ value: '0', label: __( 'Any bank', 'ohmylms' ) },
							...banks.map( ( bank ) => ( {
								value: String( bank.id ),
								label: bank.name,
							} ) ),
							...( item.bank_id > 0 &&
							! banks.some( ( bank ) => bank.id === item.bank_id )
								? [
										{
											value: String( item.bank_id ),
											label: sprintf(
												/* translators: %d: bank id. */
												__( 'Bank #%d', 'ohmylms' ),
												item.bank_id
											),
										},
									]
								: [] ),
						] }
						onChange={ ( bank ) =>
							update( index, { bank_id: Number( bank ) } )
						}
					/>
					<CheckboxControl
						label={ __( 'Include secure questions', 'ohmylms' ) }
						checked={ item.include_secure }
						onChange={ ( secure ) =>
							update( index, { include_secure: secure } )
						}
					/>
					<Button
						variant="secondary"
						size="small"
						isDestructive
						onClick={ () =>
							setDrafts( ( current ) =>
								current.filter(
									( _, position ) => position !== index
								)
							)
						}
					>
						{ __( 'Remove pool', 'ohmylms' ) }
					</Button>
				</fieldset>
			) ) }
			<p>
				<Button
					variant="secondary"
					disabled={ drafts.length >= 20 }
					onClick={ () =>
						setDrafts( ( current ) => [
							...current,
							{
								key: poolKey(),
								title: '',
								skill: { id: 0, name: '' },
								count: '1',
								marks: '1',
								difficulty: '',
								bank_id: 0,
								include_secure: true,
							},
						] )
					}
				>
					{ __( 'Add pool', 'ohmylms' ) }
				</Button>
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button
				variant="primary"
				disabled={ busy }
				isBusy={ busy }
				onClick={ submit }
			>
				{ __( 'Save pools', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="tertiary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
