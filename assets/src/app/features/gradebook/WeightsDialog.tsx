import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { WidefatTable } from '../../components/WidefatTable';
import { normalizeApiError } from '../../lib/api';
import { saveWeights } from './api';
import { weightsError } from './model';
import type { ClassRef, CourseWeights, Weights } from './types';

interface Line {
	category: string;
	weight: string;
}

function linesOf( weights: Weights, categories: readonly string[] ): Line[] {
	const lines = Object.entries( weights ).map( ( [ category, weight ] ) => ( {
		category,
		weight: String( weight ),
	} ) );
	if ( ! lines.length ) {
		return categories.map( ( category ) => ( { category, weight: '' } ) );
	}
	return lines;
}

/**
 * Edit the category weights of the course or a class override. Weights add up to 100; an empty set
 * means total points (course) or "use the course's weights" (class).
 *
 * @param props            Props.
 * @param props.scope      Scope id.
 * @param props.course     Course id.
 * @param props.weights    Current weights.
 * @param props.categories Categories in use.
 * @param props.classes    Classes the user may override (by id).
 * @param props.canCourse  Whether the user may change the course's weights.
 * @param props.onClose    Closes the dialog.
 * @param props.onSaved    Called after a save.
 */
export function WeightsDialog( {
	scope,
	course,
	weights,
	categories,
	classes,
	canCourse,
	onClose,
	onSaved,
}: {
	scope: string;
	course: number;
	weights: CourseWeights;
	categories: readonly string[];
	classes: readonly ClassRef[];
	canCourse: boolean;
	onClose: () => void;
	onSaved: () => void;
} ) {
	const firstTarget = canCourse ? 0 : ( classes[ 0 ]?.id ?? 0 );
	const [ target, setTarget ] = useState( firstTarget );
	const current = ( id: number ) =>
		0 === id
			? weights.course
			: ( weights.overrides.find( ( row ) => row.class_id === id ) ?? {
					weights: {},
					version: 0,
				} );
	const [ lines, setLines ] = useState< Line[] >( () =>
		linesOf( current( firstTarget ).weights, categories )
	);
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const draft: Weights = {};
	for ( const line of lines ) {
		if ( line.category.trim() && '' !== line.weight.trim() ) {
			draft[ line.category.trim() ] = Number( line.weight );
		}
	}
	const sum = Object.values( draft ).reduce(
		( total, value ) => total + value,
		0
	);
	const problem = weightsError( draft );

	function choose( id: number ) {
		setTarget( id );
		setError( '' );
		setLines( linesOf( current( id ).weights, categories ) );
	}

	async function save( clear = false ) {
		if ( ! clear && problem ) {
			setError( problem );
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			await saveWeights(
				scope,
				course,
				target,
				clear ? {} : draft,
				current( target ).version
			);
			onSaved();
			onClose();
		} catch ( caught ) {
			const apiError = normalizeApiError( caught );
			setError(
				409 === apiError.status
					? __(
							'Someone changed these weights while you were editing. Close this dialog to load the current weights.',
							'ohmylms'
						)
					: apiError.message
			);
		} finally {
			setBusy( false );
		}
	}

	const targets = [
		...( canCourse
			? [ { id: 0, name: __( 'Course (default)', 'ohmylms' ) } ]
			: [] ),
		...classes.map( ( item ) => ( {
			id: item.id,
			name: sprintf(
				/* translators: %s: class name. */
				__( 'Override for %s', 'ohmylms' ),
				item.name
			),
		} ) ),
	];

	return (
		<Modal
			title={ __( 'Category weights', 'ohmylms' ) }
			onRequestClose={ onClose }
			size="medium"
		>
			<p>
				{ __(
					'Each category’s score is its points earned over points possible; the total is the weighted mean of the categories that have graded work. Weights add up to 100.',
					'ohmylms'
				) }
			</p>
			<p>
				<label htmlFor="ohmylms-gradebook-weights-target">
					{ __( 'Weights for', 'ohmylms' ) }
				</label>{ ' ' }
				<select
					id="ohmylms-gradebook-weights-target"
					value={ target }
					onChange={ ( event ) =>
						choose( Number( event.target.value ) )
					}
				>
					{ targets.map( ( option ) => (
						<option key={ option.id } value={ option.id }>
							{ option.name }
						</option>
					) ) }
				</select>
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<WidefatTable caption={ __( 'Category weights', 'ohmylms' ) }>
				<thead>
					<tr>
						<th scope="col">{ __( 'Category', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Weight (%)', 'ohmylms' ) }</th>
						<th scope="col">
							<span className="screen-reader-text">
								{ __( 'Actions', 'ohmylms' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ lines.map( ( line, index ) => (
						<tr key={ index }>
							<td>
								<input
									type="text"
									aria-label={ __( 'Category', 'ohmylms' ) }
									value={ line.category }
									maxLength={ 40 }
									onChange={ ( event ) =>
										setLines(
											lines.map( ( other, at ) =>
												at === index
													? {
															...other,
															category:
																event.target.value.toLowerCase(),
														}
													: other
											)
										)
									}
								/>
							</td>
							<td>
								<input
									type="number"
									aria-label={ sprintf(
										/* translators: %s: category. */
										__( 'Weight of %s', 'ohmylms' ),
										line.category ||
											__( 'new category', 'ohmylms' )
									) }
									min={ 0 }
									max={ 100 }
									step="0.01"
									value={ line.weight }
									onChange={ ( event ) =>
										setLines(
											lines.map( ( other, at ) =>
												at === index
													? {
															...other,
															weight: event.target
																.value,
														}
													: other
											)
										)
									}
								/>
							</td>
							<td>
								<Button
									variant="link"
									isDestructive
									onClick={ () =>
										setLines(
											lines.filter(
												( _, at ) => at !== index
											)
										)
									}
								>
									{ __( 'Remove', 'ohmylms' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
				<tfoot>
					<tr>
						<th scope="row">{ __( 'Total', 'ohmylms' ) }</th>
						<td aria-live="polite">{ `${ Math.round( sum * 100 ) / 100 }%` }</td>
						<td>
							<Button
								variant="link"
								onClick={ () =>
									setLines( [
										...lines,
										{ category: '', weight: '' },
									] )
								}
							>
								{ __( 'Add category', 'ohmylms' ) }
							</Button>
						</td>
					</tr>
				</tfoot>
			</WidefatTable>
			<div className="ohmylms-gradebook-dialog__buttons">
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => save() }
				>
					{ __( 'Save weights', 'ohmylms' ) }
				</Button>
				{ current( target ).version > 0 && (
					<Button
						variant="secondary"
						isDestructive
						disabled={ busy }
						onClick={ () => save( true ) }
					>
						{ 0 === target
							? __( 'Use total points', 'ohmylms' )
							: __( 'Remove override', 'ohmylms' ) }
					</Button>
				) }
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}
