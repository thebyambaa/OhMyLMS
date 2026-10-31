import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { saveItem } from './api';
import { kindLabel } from './model';
import type { ClassRef, GradeItem } from './types';

/**
 * Local `YYYY-MM-DDTHH:MM` of an ISO date (for a datetime-local input).
 *
 * @param iso ISO 8601 date with offset.
 * @return Input value ('' for none).
 */
export function toLocalInput( iso: string | null ): string {
	if ( ! iso ) {
		return '';
	}
	const match = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/.exec( iso );
	return match?.[ 1 ] ?? '';
}

/**
 * Gradebook settings of one item: category, maximum, audience (everyone, some classes, individual
 * learners) and due date (site time). Only course managers see it.
 *
 * @param props         Props.
 * @param props.scope   Scope id.
 * @param props.course  Course id.
 * @param props.item    Item.
 * @param props.classes Classes linked to the course that the scope shows.
 * @param props.onClose Closes the dialog.
 * @param props.onSaved Called after a save.
 */
export function ItemDialog( {
	scope,
	course,
	item,
	classes,
	onClose,
	onSaved,
}: {
	scope: string;
	course: number;
	item: GradeItem;
	classes: readonly ClassRef[];
	onClose: () => void;
	onSaved: () => void;
} ) {
	const [ category, setCategory ] = useState( item.category );
	const [ max, setMax ] = useState( String( item.max || '' ) );
	const [ audience, setAudience ] = useState( item.audience );
	const [ classIds, setClassIds ] = useState< number[] >( item.class_ids );
	const [ due, setDue ] = useState( toLocalInput( item.due_at ) );
	const [ errors, setErrors ] = useState< Record< string, string > >( {} );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	async function save() {
		setBusy( true );
		setErrors( {} );
		setMessage( '' );
		try {
			await saveItem( scope, course, item.id, {
				category,
				max_marks: '' === max.trim() ? null : Number( max ),
				audience,
				class_ids: 'classes' === audience ? classIds : [],
				due_at: due || null,
			} );
			onSaved();
			onClose();
		} catch ( caught ) {
			const apiError = normalizeApiError( caught );
			const params = (
				apiError.details as
					{ params?: Record< string, string > } | undefined
			 )?.params;
			setErrors( params ?? {} );
			setMessage( apiError.message );
		} finally {
			setBusy( false );
		}
	}

	const field = ( name: string ) =>
		errors[ name ] ? (
			<p
				className="ohmylms-gradebook-error"
				id={ `ohmylms-gradebook-item-${ name }-error` }
			>
				{ errors[ name ] }
			</p>
		) : null;

	return (
		<Modal title={ item.title } onRequestClose={ onClose } size="medium">
			<p className="ohmylms-gradebook-muted">
				{ kindLabel( item.kind ) }
				{ item.module ? ` · ${ item.module }` : '' }
			</p>
			{ message && (
				<Notice status="error" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<form
				className="ohmylms-gradebook-dialog__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<p>
					<label htmlFor="ohmylms-gradebook-item-category">
						{ __( 'Category', 'ohmylms' ) }
					</label>
					<br />
					<input
						id="ohmylms-gradebook-item-category"
						type="text"
						value={ category }
						maxLength={ 40 }
						aria-invalid={ Boolean( errors.category ) }
						aria-describedby={
							errors.category
								? 'ohmylms-gradebook-item-category-error'
								: undefined
						}
						onChange={ ( event ) =>
							setCategory( event.target.value.toLowerCase() )
						}
					/>
				</p>
				{ field( 'category' ) }
				<p>
					<label htmlFor="ohmylms-gradebook-item-max">
						{ __( 'Maximum marks', 'ohmylms' ) }
					</label>
					<br />
					<input
						id="ohmylms-gradebook-item-max"
						type="number"
						min={ 0 }
						step="any"
						value={ max }
						onChange={ ( event ) => setMax( event.target.value ) }
					/>
				</p>
				{ field( 'max_marks' ) }
				<fieldset>
					<legend>{ __( 'Assigned to', 'ohmylms' ) }</legend>
					{ (
						[
							[ 'all', __( 'Everyone enrolled', 'ohmylms' ) ],
							[ 'classes', __( 'Some classes', 'ohmylms' ) ],
							[
								'individuals',
								__(
									'Individual learners (in no class of the course)',
									'ohmylms'
								),
							],
						] as const
					 ).map( ( [ value, label ] ) => (
						<span key={ value } className="ohmylms-gradebook-radio">
							<input
								id={ `ohmylms-gradebook-audience-${ value }` }
								type="radio"
								name="ohmylms-gradebook-audience"
								value={ value }
								checked={ audience === value }
								onChange={ () => setAudience( value ) }
							/>{ ' ' }
							<label
								htmlFor={ `ohmylms-gradebook-audience-${ value }` }
							>
								{ label }
							</label>
						</span>
					) ) }
					{ 'classes' === audience &&
						classes.map( ( linked ) => (
							<span
								key={ linked.id }
								className="ohmylms-gradebook-radio is-nested"
							>
								<input
									id={ `ohmylms-gradebook-class-${ linked.id }` }
									type="checkbox"
									checked={ classIds.includes( linked.id ) }
									onChange={ ( event ) =>
										setClassIds(
											event.target.checked
												? [ ...classIds, linked.id ]
												: classIds.filter(
														( id ) =>
															id !== linked.id
													)
										)
									}
								/>{ ' ' }
								<label
									htmlFor={ `ohmylms-gradebook-class-${ linked.id }` }
								>
									{ linked.name }
								</label>
							</span>
						) ) }
				</fieldset>
				{ field( 'audience' ) }
				{ field( 'class_ids' ) }
				<p>
					<label htmlFor="ohmylms-gradebook-item-due">
						{ __( 'Due (site time)', 'ohmylms' ) }
					</label>
					<br />
					<input
						id="ohmylms-gradebook-item-due"
						type="datetime-local"
						value={ due }
						onChange={ ( event ) => setDue( event.target.value ) }
					/>
				</p>
				<p className="description">
					{ __(
						'After the due date, work that was never submitted shows as missing. Without a due date it stays “not submitted”.',
						'ohmylms'
					) }
				</p>
				{ field( 'due_at' ) }
				<div className="ohmylms-gradebook-dialog__buttons">
					<Button
						variant="primary"
						type="submit"
						isBusy={ busy }
						disabled={ busy }
					>
						{ __( 'Save item', 'ohmylms' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
