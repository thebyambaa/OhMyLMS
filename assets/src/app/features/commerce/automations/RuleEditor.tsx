import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../../lib/api';
import { SearchPicker } from '../SearchPicker';
import { useOperation } from '../useOperation';
import { createRule, searchChoices, updateRule } from './api';
import {
	ID_CONDITIONS,
	RULE_NAME_MAX,
	actionLabel,
	emptyRuleDraft,
	keepConditions,
	newAction,
	ruleBody,
	ruleDraft,
	validateRule,
	type ActionType,
	type ChoiceType,
	type IdCondition,
	type Rule,
	type RuleAction,
	type RuleDraft,
	type RuleOptions,
} from './model';

type Labels = Record< string, string >;

const labelKey = ( type: ChoiceType, id: number ) => `${ type }:${ id }`;

function messagesOf( error: unknown ): Record< string, string > {
	const details = normalizeApiError( error ).details;
	const params =
		details && 'object' === typeof details
			? ( details as { params?: unknown } ).params
			: null;
	return params && 'object' === typeof params
		? ( params as Record< string, string > )
		: {};
}

/**
 * Picker type and id of each action's target.
 * @param action
 */
function actionTarget(
	action: RuleAction
): { type: ChoiceType; id: number; field: string } | null {
	switch ( action.type ) {
		case 'enrol_course':
			return { type: 'course', id: action.course_id, field: 'course_id' };
		case 'add_to_cohort':
			return { type: 'cohort', id: action.cohort_id, field: 'cohort_id' };
		case 'grant_membership':
			return {
				type: 'membership',
				id: action.membership_id,
				field: 'membership_id',
			};
		case 'send_coupon':
			return { type: 'coupon', id: action.coupon_id, field: 'coupon_id' };
		default:
			return null;
	}
}

function withTarget( action: RuleAction, id: number ): RuleAction {
	switch ( action.type ) {
		case 'enrol_course':
			return { ...action, course_id: id };
		case 'add_to_cohort':
			return { ...action, cohort_id: id };
		case 'grant_membership':
			return { ...action, membership_id: id };
		case 'send_coupon':
			return { ...action, coupon_id: id };
		default:
			return action;
	}
}

/**
 * Load the titles of the ids a stored rule refers to, once.
 *
 * @param rule Rule being edited, or null.
 * @return Labels keyed by `type:id`, and a setter for picked items.
 */
function useLabels( rule: Rule | null ) {
	const [ labels, setLabels ] = useState< Labels >( {} );
	const loaded = useRef( false );
	useEffect( () => {
		if ( ! rule || loaded.current ) {
			return undefined;
		}
		loaded.current = true;
		const controller = new AbortController();
		const wanted = new Map< ChoiceType, number[] >();
		const want = ( type: ChoiceType, id: number ) => {
			if ( id ) {
				wanted.set( type, [ ...( wanted.get( type ) ?? [] ), id ] );
			}
		};
		for ( const [ key, type ] of Object.entries( ID_CONDITIONS ) ) {
			for ( const id of rule.conditions[ key as IdCondition ] ?? [] ) {
				want( type, id );
			}
		}
		for ( const action of rule.actions ) {
			const target = actionTarget( action );
			if ( target ) {
				want( target.type, target.id );
			}
		}
		for ( const [ type, list ] of wanted ) {
			searchChoices( type, '', list, controller.signal ).then(
				( found ) => {
					if ( ! controller.signal.aborted ) {
						setLabels( ( current ) => ( {
							...current,
							...Object.fromEntries(
								found.map( ( item ) => [
									labelKey( type, item.id ),
									item.title,
								] )
							),
						} ) );
					}
				},
				() => undefined
			);
		}
		return () => controller.abort();
	}, [ rule ] );
	return {
		label: ( type: ChoiceType, id: number ) =>
			labels[ labelKey( type, id ) ] ??
			sprintf(
				/* translators: %d: item ID. */
				__( '#%d', 'ohmylms' ),
				id
			),
		remember: ( type: ChoiceType, id: number, title: string ) =>
			setLabels( ( current ) => ( {
				...current,
				[ labelKey( type, id ) ]: title,
			} ) ),
	};
}

const CONDITION_LABELS: Readonly< Record< IdCondition, () => string > > = {
	course_ids: () => __( 'Only for these courses', 'ohmylms' ),
	membership_ids: () => __( 'Only for these plans', 'ohmylms' ),
	lesson_ids: () => __( 'Only for these lessons', 'ohmylms' ),
	quiz_ids: () => __( 'Only for these quizzes', 'ohmylms' ),
	assignment_ids: () => __( 'Only for these assignments', 'ohmylms' ),
};

/**
 * Editor of one "when <event> then <actions>" rule. Every choice comes from the server's allow-list,
 * so a rule is data only.
 *
 * @param props          Component props.
 * @param props.rule     Rule to edit, or null for a new one.
 * @param props.options  Events, actions and limits.
 * @param props.onSaved  Receives the stored rule.
 * @param props.onCancel Leaves the editor.
 */
export function RuleEditor( {
	rule,
	options,
	onSaved,
	onCancel,
}: {
	rule: Rule | null;
	options: RuleOptions;
	onSaved: ( rule: Rule ) => void;
	onCancel: () => void;
} ) {
	const [ draft, setDraft ] = useState< RuleDraft >(
		rule ? ruleDraft( rule ) : emptyRuleDraft( options )
	);
	const [ errors, setErrors ] = useState< Record< string, string > >( {} );
	const operation = useOperation();
	const labels = useLabels( rule );
	const event = options.events.find( ( item ) => item.id === draft.event );
	const supported = event?.conditions ?? [];

	const change = ( next: Partial< RuleDraft >, field?: string ) => {
		setDraft( ( current ) => ( { ...current, ...next } ) );
		if ( field ) {
			setErrors( ( current ) => {
				const copy = { ...current };
				delete copy[ field ];
				return copy;
			} );
		}
	};
	const setAction = ( index: number, action: RuleAction ) =>
		setDraft( ( current ) => ( {
			...current,
			actions: current.actions.map( ( item, position ) =>
				position === index ? action : item
			),
		} ) );

	const save = async () => {
		const invalid = validateRule( draft, options );
		setErrors( invalid );
		if ( Object.keys( invalid ).length ) {
			return;
		}
		let saved: Rule | null = null;
		const done = await operation.run( async ( signal ) => {
			try {
				saved = rule
					? await updateRule( rule.id, ruleBody( draft ), signal )
					: await createRule( ruleBody( draft ), signal );
			} catch ( failure ) {
				setErrors( messagesOf( failure ) );
				throw failure;
			}
		} );
		if ( done && saved ) {
			onSaved( saved );
		}
	};

	const idCondition = ( key: IdCondition ) => {
		const type = ID_CONDITIONS[ key ];
		const chosen = draft.conditions[ key ] ?? [];
		return (
			<fieldset key={ key }>
				<legend>{ CONDITION_LABELS[ key ]() }</legend>
				{ chosen.length ? (
					<ul>
						{ chosen.map( ( id ) => (
							<li key={ id }>
								{ labels.label( type, id ) }{ ' ' }
								<Button
									variant="link"
									isDestructive
									aria-label={ sprintf(
										/* translators: %s: item title. */
										__( 'Remove %s', 'ohmylms' ),
										labels.label( type, id )
									) }
									onClick={ () =>
										change(
											{
												conditions: {
													...draft.conditions,
													[ key ]: chosen.filter(
														( item ) => item !== id
													),
												},
											},
											`conditions.${ key }`
										)
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) : (
					<p>{ __( 'Any.', 'ohmylms' ) }</p>
				) }
				{ chosen.length < options.maxIds && (
					<SearchPicker
						key={ chosen.join( ',' ) }
						label={ __( 'Add', 'ohmylms' ) }
						value={ 0 }
						valueLabel=""
						error={ errors[ `conditions.${ key }` ] }
						search={ ( term, signal ) =>
							searchChoices( type, term, [], signal )
						}
						onChange={ ( id, title ) => {
							if ( ! id || chosen.includes( id ) ) {
								return;
							}
							labels.remember( type, id, title );
							change(
								{
									conditions: {
										...draft.conditions,
										[ key ]: [ ...chosen, id ],
									},
								},
								`conditions.${ key }`
							);
						} }
					/>
				) }
			</fieldset>
		);
	};

	const actionFields = ( action: RuleAction, index: number ) => {
		const key = ( field: string ) => `actions.${ index }.${ field }`;
		const target = actionTarget( action );
		return (
			<>
				{ target && (
					<SearchPicker
						label={ actionLabel( options, action.type ) }
						value={ target.id }
						valueLabel={ labels.label( target.type, target.id ) }
						error={ errors[ key( target.field ) ] }
						search={ ( term, signal ) =>
							searchChoices( target.type, term, [], signal )
						}
						onChange={ ( id, title ) => {
							labels.remember( target.type, id, title );
							setAction( index, withTarget( action, id ) );
						} }
					/>
				) }
				{ 'send_email' === action.type && (
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Send to', 'ohmylms' ) }
						value={ action.to }
						options={ [
							{
								value: 'learner',
								label: __( 'The learner', 'ohmylms' ),
							},
							{
								value: 'admin',
								label: __(
									'The site administrator',
									'ohmylms'
								),
							},
						] }
						onChange={ ( to ) =>
							setAction( index, {
								...action,
								to: 'admin' === to ? 'admin' : 'learner',
							} )
						}
					/>
				) }
				{ ( 'send_email' === action.type ||
					'send_coupon' === action.type ) && (
					<>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Subject', 'ohmylms' ) }
							value={ action.subject }
							maxLength={ options.maxSubject }
							help={ errors[ key( 'subject' ) ] }
							aria-invalid={
								errors[ key( 'subject' ) ] ? true : undefined
							}
							onChange={ ( subject ) =>
								setAction( index, { ...action, subject } )
							}
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Message (plain text)', 'ohmylms' ) }
							value={ action.body }
							rows={ 5 }
							maxLength={ options.maxBody }
							help={
								errors[ key( 'body' ) ] ??
								sprintf(
									/* translators: %s: list of placeholders. */
									__(
										'Placeholders: %s. Markup is removed.',
										'ohmylms'
									),
									options.mergeTags.join( ', ' )
								)
							}
							onChange={ ( body ) =>
								setAction( index, { ...action, body } )
							}
						/>
					</>
				) }
			</>
		);
	};

	return (
		<form
			noValidate
			aria-label={
				rule
					? __( 'Edit rule', 'ohmylms' )
					: __( 'New rule', 'ohmylms' )
			}
			onSubmit={ ( submitEvent ) => {
				submitEvent.preventDefault();
				void save();
			} }
		>
			<h2>
				{ rule
					? __( 'Edit rule', 'ohmylms' )
					: __( 'New rule', 'ohmylms' ) }
			</h2>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Name', 'ohmylms' ) }
				value={ draft.name }
				maxLength={ RULE_NAME_MAX }
				help={ errors.name }
				aria-invalid={ errors.name ? true : undefined }
				onChange={ ( name ) => change( { name }, 'name' ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Active', 'ohmylms' ) }
				help={ __( 'A paused rule never runs.', 'ohmylms' ) }
				checked={ 'active' === draft.status }
				onChange={ ( on ) =>
					change( { status: on ? 'active' : 'paused' }, 'status' )
				}
			/>
			<h3>{ __( 'When', 'ohmylms' ) }</h3>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Event', 'ohmylms' ) }
				value={ draft.event }
				help={ errors.event }
				options={ options.events.map( ( item ) => ( {
					value: item.id,
					label: item.label,
				} ) ) }
				onChange={ ( id ) => {
					const next = options.events.find(
						( item ) => item.id === id
					);
					change(
						{
							event: id,
							conditions: keepConditions(
								draft.conditions,
								next?.conditions ?? []
							),
						},
						'event'
					);
				} }
			/>
			{ ( Object.keys( ID_CONDITIONS ) as IdCondition[] )
				.filter( ( key ) => supported.includes( key ) )
				.map( idCondition ) }
			{ supported.includes( 'rate' ) && (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Progress', 'ohmylms' ) }
						value={ draft.conditions.operator ?? 'gte' }
						options={ [
							{
								value: 'gte',
								label: __( 'At least', 'ohmylms' ),
							},
							{ value: 'lte', label: __( 'At most', 'ohmylms' ) },
							{ value: 'eq', label: __( 'Exactly', 'ohmylms' ) },
						] }
						onChange={ ( operator ) =>
							change( {
								conditions: {
									...draft.conditions,
									operator:
										'lte' === operator || 'eq' === operator
											? operator
											: 'gte',
								},
							} )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 0 }
						max={ 100 }
						label={ __( 'Rate (%)', 'ohmylms' ) }
						value={
							undefined === draft.conditions.rate
								? ''
								: String( draft.conditions.rate )
						}
						help={ errors[ 'conditions.rate' ] }
						aria-invalid={
							errors[ 'conditions.rate' ] ? true : undefined
						}
						onChange={ ( value ) =>
							change(
								{
									conditions: {
										...draft.conditions,
										rate:
											'' === value
												? undefined
												: Number( value ),
									},
								},
								'conditions.rate'
							)
						}
					/>
				</>
			) }
			{ supported.includes( 'result' ) && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Grade', 'ohmylms' ) }
					value={ draft.conditions.result ?? '' }
					options={ [
						{ value: '', label: __( 'Pass or fail', 'ohmylms' ) },
						{ value: 'pass', label: __( 'Passed', 'ohmylms' ) },
						{ value: 'fail', label: __( 'Failed', 'ohmylms' ) },
					] }
					onChange={ ( result ) => {
						const conditions = { ...draft.conditions };
						if ( 'pass' === result || 'fail' === result ) {
							conditions.result = result;
						} else {
							delete conditions.result;
						}
						change( { conditions } );
					} }
				/>
			) }

			<h3>{ __( 'Then', 'ohmylms' ) }</h3>
			{ errors.actions && (
				<Notice status="error" isDismissible={ false }>
					{ errors.actions }
				</Notice>
			) }
			<ol>
				{ draft.actions.map( ( action, index ) => (
					<li key={ index }>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ sprintf(
								/* translators: %d: action number. */
								__( 'Action %d', 'ohmylms' ),
								index + 1
							) }
							value={ action.type }
							options={ options.actions.map( ( item ) => ( {
								value: item.id,
								label: item.label,
							} ) ) }
							onChange={ ( type ) =>
								setAction(
									index,
									newAction( type as ActionType )
								)
							}
						/>
						{ actionFields( action, index ) }
						<Button
							variant="link"
							isDestructive
							disabled={ 1 === draft.actions.length }
							accessibleWhenDisabled
							onClick={ () =>
								change( {
									actions: draft.actions.filter(
										( _item, position ) =>
											position !== index
									),
								} )
							}
						>
							{ sprintf(
								/* translators: %d: action number. */
								__( 'Remove action %d', 'ohmylms' ),
								index + 1
							) }
						</Button>
					</li>
				) ) }
			</ol>
			{ draft.actions.length < options.maxActions && (
				<p>
					<Button
						variant="secondary"
						onClick={ () =>
							change(
								{
									actions: [
										...draft.actions,
										newAction( 'send_email' ),
									],
								},
								'actions'
							)
						}
					>
						{ __( 'Add action', 'ohmylms' ) }
					</Button>
				</p>
			) }
			<p>
				<Button
					type="submit"
					variant="primary"
					isBusy={ operation.busy }
					disabled={ operation.busy }
				>
					{ rule
						? __( 'Save rule', 'ohmylms' )
						: __( 'Create rule', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onCancel }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</p>
		</form>
	);
}
